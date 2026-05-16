<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Dto\SyncReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CustomerSyncService
{
    public function __construct(private readonly SshClientInterface $ssh) {}

    /**
     * Syncs the local customer replica for a given cluster against the upstream.
     *
     * nextcloud-manage list --json schema (v1):
     * {
     *   "schema_version": "1",
     *   "instances": [{"name": "<slug>", "domain": "<domain>", "status": "running"}, ...],
     *   "shared_services": [...]
     * }
     *
     * Only `instances` entries are synced. `shared_services` are infrastructure — ignored.
     * Upstream status "running" maps to local "active".
     */
    public function sync(ClusterServer $cluster): SyncReport
    {
        $resp = $this->ssh->run($cluster, 'nextcloud-manage', ['list', '--json'], null, 30);

        if ($resp->exitCode !== 0) {
            Log::warning('customers.sync: nextcloud-manage list returned non-zero exit — skipping sync', [
                'cluster_id' => $cluster->id,
                'exit_code' => $resp->exitCode,
                'stderr' => $resp->stderr,
            ]);

            return new SyncReport;
        }

        $parsed = $resp->parsedJson;

        if (! is_array($parsed)) {
            Log::warning('customers.sync: --json response could not be parsed — skipping sync', [
                'cluster_id' => $cluster->id,
                'stdout_preview' => mb_substr($resp->stdout, 0, 200),
            ]);

            return new SyncReport;
        }

        $instances = $parsed['instances'] ?? null;

        if (! is_array($instances)) {
            Log::warning('customers.sync: --json response missing "instances" key — skipping sync', [
                'cluster_id' => $cluster->id,
                'schema_version' => $parsed['schema_version'] ?? 'unknown',
            ]);

            return new SyncReport;
        }

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

        $upstreamSlugs = array_column($upstream, 'slug');
        $report = new SyncReport;

        // Pre-load all local customers for this cluster in a single query to avoid N+1.
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
                $report->inserted++;
                $this->auditDiverged('customer_sync_inserted', $u['slug'], $u, $cluster->id);
            } elseif ($local->status !== $u['status'] || $local->domain !== $u['domain']) {
                $local->update([
                    'status' => $u['status'],
                    'domain' => $u['domain'],
                    'last_sync_at' => now(),
                ]);
                $report->updated++;
                $this->auditDiverged('customer_sync_updated', $u['slug'], [
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
            ->each(function (Customer $c) use ($report, $cluster): void {
                $previousStatus = $c->status;
                $c->update(['status' => 'removed']);
                $c->delete();
                $report->deleted++;
                $this->auditDiverged('customer_sync_removed', $c->slug, [
                    'previous_status' => $previousStatus,
                ], $cluster->id);
            });

        return $report;
    }

    /**
     * Maps upstream instance status to local customer status.
     * Upstream uses "running" for healthy instances; local schema uses "active".
     */
    private function translateInstanceStatus(string $upstreamStatus): string
    {
        return match ($upstreamStatus) {
            'running' => 'active',
            'stopped' => 'removed',
            default => $upstreamStatus,
        };
    }

    private function auditDiverged(string $action, string $slug, array $payload, string $clusterId): void
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
}
