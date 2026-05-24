<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Services;

use App\Models\ClusterServer;
use App\Models\Job;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Jobs\Exceptions\JobLogFetchException;

/**
 * Fetches job log lines from the upstream nextcloud-saas-manager via SSH.
 *
 * Primary command: `nextcloud-manage job <job_id> logs --json`
 * Fallback (exit_code=99 / not-implemented): `nextcloud-manage job <job_id> status --json`
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

        try {
            $response = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                ['job', $job->job_id, 'logs', '--json'],
                null,
                $timeoutSec,
            );
        } catch (SshRemoteException $e) {
            if ($e->notImplemented) {
                return $this->fetchViaStatus($job, $cluster, $timeoutSec);
            }

            throw new JobLogFetchException(
                "SSH failed fetching logs for job {$job->job_id}: {$e->getMessage()}",
                previous: $e,
            );
        } catch (SshClientException $e) {
            throw new JobLogFetchException(
                "SSH failed fetching logs for job {$job->job_id}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->exitCode === self::EXIT_NOT_IMPLEMENTED) {
            return $this->fetchViaStatus($job, $cluster, $timeoutSec);
        }

        if ($response->exitCode !== 0) {
            throw new JobLogFetchException(
                "nextcloud-manage job logs returned exit_code={$response->exitCode} for job {$job->job_id}",
            );
        }

        $lines = $this->parseAndSanitize($response->stdout, $job->job_id);
        if ($lines !== []) {
            return $lines;
        }

        return $this->fetchViaStatus($job, $cluster, $timeoutSec);
    }

    /**
     * @return array<int, string>
     *
     * @throws JobLogFetchException
     */
    private function fetchViaStatus(Job $job, ClusterServer $cluster, int $timeoutSec): array
    {
        try {
            $response = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                ['job', $job->job_id, 'status', '--json'],
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

        return $this->sanitizeLines($this->extractLines($decoded));
    }

    /**
     * @return array<int, string>
     */
    private function parseAndSanitize(string $stdout, string $jobId): array
    {
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            // Upstream may return plain text lines instead of JSON.
            return $this->sanitizeLines(preg_split('/\R/', $stdout) ?: []);
        }

        return $this->sanitizeLines($this->extractLines($decoded));
    }

    /**
     * @return array<int, mixed>
     */
    private function extractLines(array $decoded): array
    {
        $lines = $this->linesFromPayload($decoded);
        if ($lines !== []) {
            return $lines;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $lines = $this->linesFromPayload($decoded['data']);
            if ($lines !== []) {
                return $lines;
            }
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function linesFromPayload(array $payload): array
    {
        foreach (['lines', 'summary', 'logs'] as $key) {
            if (! isset($payload[$key])) {
                continue;
            }

            if (is_array($payload[$key])) {
                return $this->flattenLineValues($payload[$key]);
            }

            if (is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return $this->flattenLineValues(preg_split('/\R/', $payload[$key]) ?: []);
            }
        }

        $textBlocks = [];
        foreach (['stdout', 'stderr', 'log', 'output', 'content'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || trim($payload[$key]) === '') {
                continue;
            }
            $textBlocks[] = $payload[$key];
        }

        if ($textBlocks === []) {
            return [];
        }

        return $this->flattenLineValues(preg_split('/\R/', implode("\n", $textBlocks)) ?: []);
    }

    /**
     * @param  array<mixed>  $lines
     * @return array<int, string>
     */
    private function flattenLineValues(array $lines): array
    {
        $flat = [];
        foreach ($lines as $line) {
            if (is_string($line)) {
                $flat[] = $line;
            }
        }

        return $flat;
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
