<?php

declare(strict_types=1);

namespace App\Modules\Integration\Adapters;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\BrandingResult;
use App\Modules\Integration\Dto\CancelJobCommand;
use App\Modules\Integration\Dto\CancelJobResult;
use App\Modules\Integration\Dto\ClusterHealthReport;
use App\Modules\Integration\Dto\CreateTenantCommand;
use App\Modules\Integration\Dto\EnableAppsCommand;
use App\Modules\Integration\Dto\FetchJobLogsCommand;
use App\Modules\Integration\Dto\JobLogsResult;
use App\Modules\Integration\Dto\JobStatusResult;
use App\Modules\Integration\Dto\ManageAsyncCommand;
use App\Modules\Integration\Dto\OccPassthroughCommand;
use App\Modules\Integration\Dto\OccPassthroughOperation;
use App\Modules\Integration\Dto\OccPassthroughResult;
use App\Modules\Integration\Dto\PollJobStatusCommand;
use App\Modules\Integration\Dto\ProbeClusterHealthCommand;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Integration\Dto\SetBrandingCommand;
use App\Modules\Integration\Dto\SyncTenantCommand;
use App\Modules\Integration\Dto\SyncTenantResult;
use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SshPlatformAdapter implements PlatformPort
{
    private const EXIT_NOT_IMPLEMENTED = 99;

    private const REDACT_PATTERN = '/(password|token|secret|pwd)\s*[:=]\s*\S+/i';

    public function __construct(
        private readonly SshClientInterface $ssh,
        private readonly TransportObservability $observability,
    ) {}

    public function createTenant(CreateTenantCommand $command): AsyncJobRef
    {
        return $this->toJobRef($this->runManageAsync(
            $command->cluster,
            $command->manageArgs,
            $command->stdinJson,
        ));
    }

    public function enableApps(EnableAppsCommand $command): AsyncJobRef
    {
        return $this->toJobRef($this->runManageAsync(
            $command->cluster,
            $command->manageArgs,
            null,
        ));
    }

    public function setBranding(SetBrandingCommand $command): BrandingResult
    {
        $result = $this->runOccPassthrough(new OccPassthroughCommand(
            customer: $command->customer,
            operation: OccPassthroughOperation::SetBranding,
            fields: $command->fields,
        ));

        return new BrandingResult($result->payload);
    }

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport
    {
        $customer = $command->customer;
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            return new ReadinessReport(false);
        }

        try {
            $resp = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                [$customer->slug, 'occ-exec', 'user:list', '--json'],
                null,
                (int) config('services.customer_readiness.probe_timeout_seconds', 30),
            );

            return new ReadinessReport($resp->exitCode === 0);
        } catch (SshConnectionException|SshTimeoutException) {
            return new ReadinessReport(false);
        }
    }

    public function probeClusterHealth(ProbeClusterHealthCommand $command): ClusterHealthReport
    {
        $resp = $this->ssh->ping($command->cluster, $command->timeoutSec);

        return new ClusterHealthReport($resp->exitCode);
    }

    public function fetchJobLogs(FetchJobLogsCommand $command): JobLogsResult
    {
        $job = $command->job;
        $cluster = $command->cluster;
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
                return new JobLogsResult($this->fetchJobLogsViaStatus($job, $cluster, $timeoutSec));
            }

            throw $e;
        } catch (SshClientException $e) {
            throw $e;
        }

        if ($response->exitCode === self::EXIT_NOT_IMPLEMENTED) {
            return new JobLogsResult($this->fetchJobLogsViaStatus($job, $cluster, $timeoutSec));
        }

        if ($response->exitCode !== 0) {
            throw new \RuntimeException(
                "nextcloud-manage job logs returned exit_code={$response->exitCode} for job {$job->job_id}",
            );
        }

        $lines = $this->parseAndSanitizeJobLogs($response->stdout);
        if ($lines !== []) {
            return new JobLogsResult($lines);
        }

        return new JobLogsResult($this->fetchJobLogsViaStatus($job, $cluster, $timeoutSec));
    }

    public function cancelJob(CancelJobCommand $command): CancelJobResult
    {
        $job = $command->job;

        $this->ssh->run(
            cluster: $job->clusterServer,
            cmd: 'nextcloud-manage',
            args: ['job', $job->job_id, 'cancel', '--json'],
        );

        return new CancelJobResult;
    }

    public function pollJobStatus(PollJobStatusCommand $command): JobStatusResult
    {
        $resp = $this->ssh->run(
            $command->cluster,
            'nextcloud-manage',
            ['job', $command->job->job_id, 'status', '--json'],
            null,
            30,
        );

        $data = $resp->parsedJson;
        if (! is_array($data) || ! isset($data['state'])) {
            throw new \RuntimeException(
                "No valid JSON response for job {$command->job->job_id}",
            );
        }

        return new JobStatusResult((string) $data['state'], $data);
    }

    public function syncTenant(SyncTenantCommand $command): SyncTenantResult
    {
        $cluster = $command->cluster;
        $resp = $this->ssh->run($cluster, 'nextcloud-manage', ['list', '--json'], null, 30);

        if ($resp->exitCode !== 0) {
            Log::warning('customers.sync: nextcloud-manage list --json returned non-zero exit — skipping sync', [
                'cluster_id' => $cluster->id,
                'exit_code' => $resp->exitCode,
                'stderr' => $resp->stderr,
            ]);

            return new SyncTenantResult;
        }

        $parsed = $resp->parsedJson;
        if (! is_array($parsed)) {
            Log::warning('customers.sync: --json response could not be parsed — skipping sync', [
                'cluster_id' => $cluster->id,
                'stdout_preview' => mb_substr($resp->stdout, 0, 200),
            ]);

            return new SyncTenantResult;
        }

        $instances = $parsed['instances'] ?? null;
        if (! is_array($instances)) {
            Log::warning('customers.sync: --json response missing "instances" key — skipping sync', [
                'cluster_id' => $cluster->id,
                'schema_version' => $parsed['schema_version'] ?? 'unknown',
            ]);

            return new SyncTenantResult;
        }

        return new SyncTenantResult(...$this->applyTenantSync($cluster, $instances));
    }

    public function runOccPassthrough(OccPassthroughCommand $command): OccPassthroughResult
    {
        if ($command->operation === OccPassthroughOperation::SetBranding && $command->fields !== []) {
            return new OccPassthroughResult($this->execThemingFields($command));
        }

        [$subcmd, $args] = $this->resolveOccArgv($command);
        $payload = $this->execOccSubcmd($command->customer, $subcmd, $args, $command->timeoutSec);

        return new OccPassthroughResult($payload);
    }

    public function dispatchManageAsync(ManageAsyncCommand $command): AsyncJobRef
    {
        return $this->toJobRef($this->runManageAsync(
            $command->cluster,
            $command->manageArgs,
            $command->stdinJson,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function execThemingFields(OccPassthroughCommand $command): array
    {
        if ($command->fields === []) {
            return $this->execOccSubcmd($command->customer, 'theming:config', [], $command->timeoutSec);
        }

        $last = [];
        foreach ($command->fields as $key => $value) {
            $last = $this->execOccSubcmd(
                $command->customer,
                'theming:config',
                [$key, $value],
                $command->timeoutSec,
            );
        }

        return $last;
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function resolveOccArgv(OccPassthroughCommand $command): array
    {
        if (isset($command->fields['__subcmd'])) {
            return [$command->fields['__subcmd'], $command->args];
        }

        return match ($command->operation) {
            OccPassthroughOperation::UserList => ['user:list', $command->args],
            OccPassthroughOperation::SetQuota => ['user:setting', $command->args],
            OccPassthroughOperation::SetQuotaDefault => ['config:app:set', $command->args],
            OccPassthroughOperation::QuotaAudit => ['user:list', $command->args],
            OccPassthroughOperation::SetBranding => ['theming:config', $command->args],
            OccPassthroughOperation::ToggleMaintenance => ['maintenance:mode', $command->args],
            OccPassthroughOperation::FilesRescan => ['files:scan', $command->args],
            OccPassthroughOperation::AppEnable => ['app:enable', $command->args],
        };
    }

    /**
     * @param  array<int, string>  $args
     * @return array<string, mixed>
     *
     * @throws ClusterUnreachableException
     * @throws SshTimeoutException
     * @throws SshRemoteException
     */
    private function execOccSubcmd(
        Customer $customer,
        string $subcmd,
        array $args,
        int $timeoutSec,
    ): array {
        $customer->loadMissing('clusterServer');
        $cluster = $customer->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }

        $sshArgs = array_merge(
            [$customer->slug, 'occ-exec', $subcmd],
            $args,
            ['--json'],
        );

        try {
            $resp = $this->ssh->run($cluster, 'nextcloud-manage', $sshArgs, null, $timeoutSec);
        } catch (SshConnectionException) {
            throw new ClusterUnreachableException;
        }

        return $resp->parsedJson
            ?? throw new \RuntimeException("OCC '{$subcmd}' did not return valid JSON (stdout: {$resp->stdout})");
    }

    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     * @throws SshTimeoutException
     */
    private function runManageAsync(
        ClusterServer $cluster,
        array $manageArgs,
        ?string $stdinJson,
    ): SshResponse {
        $correlationId = Log::sharedContext()['correlation_id'] ?? null;
        if (is_string($correlationId) && $correlationId !== '') {
            Log::withContext(['correlation_id' => $correlationId]);
        }

        try {
            return $this->ssh->runAsync($cluster, 'nextcloud-manage', $manageArgs, $stdinJson);
        } catch (SshConnectionException) {
            throw new ClusterUnreachableException;
        }
    }

    private function toJobRef(SshResponse $resp): AsyncJobRef
    {
        $jobId = $resp->parsedJson['job_id'] ?? null;
        if (! is_string($jobId) || $jobId === '') {
            throw new \RuntimeException('Upstream did not return job_id in async response');
        }

        $this->observability->recordDispatch(TransportObservability::TRANSPORT_SSH, $jobId);

        return new AsyncJobRef($jobId);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function applyTenantSync(ClusterServer $cluster, array $instances): array
    {
        $upstream = $this->parseUpstreamInstances($instances);
        $upstreamSlugs = array_column($upstream, 'slug');
        $inserted = 0;
        $updated = 0;
        $deleted = 0;

        $existing = Customer::where('cluster_server_id', $cluster->id)
            ->get()
            ->keyBy('slug');

        foreach ($upstream as $u) {
            $local = $existing->get($u['slug']);
            if (! $local) {
                Customer::create([
                    'slug' => $u['slug'],
                    'cluster_server_id' => $cluster->id,
                    'domain' => $u['domain'],
                    'status' => $u['status'],
                    'last_sync_at' => now(),
                ]);
                $inserted++;
                $this->auditTenantSync('customer_sync_inserted', $u['slug'], $u, $cluster->id);
            } elseif ($local->status !== $u['status'] || $local->domain !== $u['domain']) {
                $updates = [
                    'domain' => $u['domain'],
                    'last_sync_at' => now(),
                ];

                if (! in_array($local->status, CustomerLifecycleStatus::USER_OPS_BLOCKED, true)) {
                    $updates['status'] = $u['status'];
                }

                $local->update($updates);
                $updated++;
                $this->auditTenantSync('customer_sync_updated', $u['slug'], [
                    'new_status' => $u['status'],
                    'new_domain' => $u['domain'],
                ], $cluster->id);
            } else {
                $local->update(['last_sync_at' => now()]);
            }
        }

        Customer::where('cluster_server_id', $cluster->id)
            ->whereNotIn('slug', $upstreamSlugs)
            ->whereNull('deleted_at')
            ->each(function (Customer $c) use (&$deleted, $cluster): void {
                $previousStatus = $c->status;
                $c->update(['status' => 'removed']);
                $c->delete();
                $deleted++;
                $this->auditTenantSync('customer_sync_removed', $c->slug, [
                    'previous_status' => $previousStatus,
                ], $cluster->id);
            });

        return [$inserted, $updated, $deleted];
    }

    /**
     * @return list<array{slug: string, domain: string, status: string}>
     */
    private function parseUpstreamInstances(array $instances): array
    {
        $upstream = [];
        foreach ($instances as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $slug = (string) ($entry['name'] ?? '');
            if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
                continue;
            }
            $upstream[] = [
                'slug' => $slug,
                'domain' => (string) ($entry['domain'] ?? ''),
                'status' => $this->translateInstanceStatus((string) ($entry['status'] ?? '')),
            ];
        }

        return $upstream;
    }

    private function translateInstanceStatus(string $upstreamStatus): string
    {
        return match ($upstreamStatus) {
            'running' => 'active',
            'stopped' => 'removed',
            default => $upstreamStatus,
        };
    }

    private function auditTenantSync(string $action, string $slug, array $payload, string $clusterId): void
    {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => $action,
            'resource_type' => 'customer',
            'resource_id' => $slug,
            'payload' => $payload,
            'cluster_server_id' => $clusterId,
        ]);
    }

    /**
     * @return list<string>
     */
    private function fetchJobLogsViaStatus(Job $job, ClusterServer $cluster, int $timeoutSec): array
    {
        $response = $this->ssh->run(
            $cluster,
            'nextcloud-manage',
            ['job', $job->job_id, 'status', '--json'],
            null,
            $timeoutSec,
        );

        if ($response->exitCode !== 0) {
            throw new \RuntimeException(
                "nextcloud-manage job status returned exit_code={$response->exitCode} for job {$job->job_id}",
            );
        }

        $decoded = json_decode($response->stdout, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Could not parse JSON from job status for {$job->job_id}");
        }

        return $this->sanitizeJobLogLines($this->extractJobLogLines($decoded));
    }

    /**
     * @return list<string>
     */
    private function parseAndSanitizeJobLogs(string $stdout): array
    {
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            return $this->sanitizeJobLogLines(preg_split('/\R/', $stdout) ?: []);
        }

        return $this->sanitizeJobLogLines($this->extractJobLogLines($decoded));
    }

    /**
     * @return list<mixed>
     */
    private function extractJobLogLines(array $decoded): array
    {
        $lines = $this->jobLogLinesFromPayload($decoded);
        if ($lines !== []) {
            return $lines;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $lines = $this->jobLogLinesFromPayload($decoded['data']);
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
     * @return list<string>
     */
    private function jobLogLinesFromPayload(array $payload): array
    {
        foreach (['lines', 'summary', 'logs'] as $key) {
            if (! isset($payload[$key])) {
                continue;
            }

            if (is_array($payload[$key])) {
                return $this->flattenJobLogLineValues($payload[$key]);
            }

            if (is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return $this->flattenJobLogLineValues(preg_split('/\R/', $payload[$key]) ?: []);
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

        return $this->flattenJobLogLineValues(preg_split('/\R/', implode("\n", $textBlocks)) ?: []);
    }

    /**
     * @param  array<mixed>  $lines
     * @return list<string>
     */
    private function flattenJobLogLineValues(array $lines): array
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
     * @return list<string>
     */
    private function sanitizeJobLogLines(array $lines): array
    {
        $sanitized = [];
        foreach ($lines as $line) {
            if (! is_string($line) || trim($line) === '') {
                continue;
            }
            $sanitized[] = preg_replace(self::REDACT_PATTERN, '$1=[REDACTED]', $line);
        }

        return $sanitized;
    }
}
