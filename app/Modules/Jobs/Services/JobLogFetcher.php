<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Services;

use App\Models\ClusterServer;
use App\Models\Job;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Jobs\Exceptions\JobLogFetchException;

/**
 * Fetches job log lines from the upstream nextcloud-saas-manager via SSH.
 *
 * Primary command: `nextcloud-manage <client> job <job_id> logs --json`
 * Fallback (exit_code=99 / not-implemented): `nextcloud-manage <client> job <job_id> status --json`
 *   → extracts `data.summary` or `data.logs` from the status response.
 *
 * All lines are sanitised to redact credentials before persistence.
 */
final class JobLogFetcher
{
    /** exit_code returned by upstream when a command is not yet implemented. */
    private const EXIT_NOT_IMPLEMENTED = 99;

    private const REDACT_PATTERN = '/(password|token|secret|pwd)\s*[:=]\s*\S+/i';

    public function __construct(
        private readonly SshClientInterface $ssh,
    ) {}

    /**
     * @return array<int, string> Sanitised log lines, or [] if no lines available or idempotent.
     *
     * @throws JobLogFetchException on SSH/parse failure (caller handles gracefully).
     */
    public function fetch(Job $job, ClusterServer $cluster): array
    {
        if (! empty($job->summary)) {
            return [];
        }

        $timeoutSec = (int) config('services.ssh.log_fetch_timeout_seconds', 15);
        $client = $job->customer_slug ?? '_';

        try {
            $response = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                [$client, 'job', $job->job_id, 'logs', '--json'],
                null,
                $timeoutSec,
            );
        } catch (SshClientException $e) {
            throw new JobLogFetchException(
                "SSH failed fetching logs for job {$job->job_id}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->exitCode === self::EXIT_NOT_IMPLEMENTED) {
            return $this->fetchViaStatus($job, $cluster, $client, $timeoutSec);
        }

        if ($response->exitCode !== 0) {
            throw new JobLogFetchException(
                "nextcloud-manage job logs returned exit_code={$response->exitCode} for job {$job->job_id}",
            );
        }

        return $this->parseAndSanitize($response->stdout, $job->job_id);
    }

    /**
     * @return array<int, string>
     *
     * @throws JobLogFetchException
     */
    private function fetchViaStatus(Job $job, ClusterServer $cluster, string $client, int $timeoutSec): array
    {
        try {
            $response = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                [$client, 'job', $job->job_id, 'status', '--json'],
                null,
                $timeoutSec,
            );
        } catch (SshClientException $e) {
            throw new JobLogFetchException(
                "SSH failed fetching status for job {$job->job_id}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->exitCode !== 0) {
            throw new JobLogFetchException(
                "nextcloud-manage job status returned exit_code={$response->exitCode} for job {$job->job_id}",
            );
        }

        $decoded = json_decode($response->stdout, true);
        if (! is_array($decoded)) {
            throw new JobLogFetchException("Could not parse JSON from job status for {$job->job_id}");
        }

        $lines = $decoded['data']['summary']
            ?? $decoded['data']['logs']
            ?? $decoded['summary']
            ?? $decoded['logs']
            ?? [];

        if (! is_array($lines)) {
            $lines = is_string($lines) ? [$lines] : [];
        }

        return $this->sanitizeLines($lines);
    }

    /**
     * @return array<int, string>
     *
     * @throws JobLogFetchException
     */
    private function parseAndSanitize(string $stdout, string $jobId): array
    {
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            throw new JobLogFetchException("Could not parse JSON from job logs for {$jobId}");
        }

        // Accept either a plain array of lines or an object with a lines/summary key.
        $lines = match (true) {
            isset($decoded['lines']) && is_array($decoded['lines']) => $decoded['lines'],
            isset($decoded['summary']) && is_array($decoded['summary']) => $decoded['summary'],
            array_is_list($decoded) => $decoded,
            default => [],
        };

        return $this->sanitizeLines($lines);
    }

    /**
     * @param  array<mixed>  $lines
     * @return array<int, string>
     */
    private function sanitizeLines(array $lines): array
    {
        $sanitized = [];
        foreach ($lines as $line) {
            if (! is_string($line) || trim($line) === '') {
                continue;
            }
            // Redact credential patterns.
            $sanitized[] = preg_replace(self::REDACT_PATTERN, '$1=[REDACTED]', $line);
        }

        return $sanitized;
    }
}
