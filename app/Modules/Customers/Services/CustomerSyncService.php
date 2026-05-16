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
     * nextcloud-manage list returns tab-delimited text (not JSON) — one customer per line:
     * "<slug>  <domain>  <status>"
     */
    public function sync(ClusterServer $cluster): SyncReport
    {
        $resp = $this->ssh->run($cluster, 'nextcloud-manage', ['list'], null, 30);

        $lines = array_filter(array_map('trim', explode("\n", trim($resp->stdout))));
        $upstream = [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line, 3);
            $slug = $parts[0] ?? '';
            if ($slug === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
                continue;
            }
            $upstream[] = [
                'slug' => $slug,
                'domain' => $parts[1] ?? '',
                'status' => $parts[2] ?? 'active',
            ];
        }

        // Guard: abort sync if exit code is non-zero — a failed command should not
        // be treated as "0 customers" and wipe the local replica.
        if ($resp->exitCode !== 0) {
            Log::warning('customers.sync: nextcloud-manage list returned non-zero exit — skipping sync', [
                'cluster_id' => $cluster->id,
                'exit_code' => $resp->exitCode,
                'stderr' => $resp->stderr,
            ]);

            return new SyncReport;
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
