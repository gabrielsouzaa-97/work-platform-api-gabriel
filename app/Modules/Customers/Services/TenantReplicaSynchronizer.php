<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class TenantReplicaSynchronizer
{
    /**
     * @param  array<mixed>  $instances
     * @return array{inserted: int, updated: int, deleted: int}
     */
    public function apply(ClusterServer $cluster, array $instances): array
    {
        $upstream = $this->parseUpstreamInstances($instances);
        $counts = ['inserted' => 0, 'updated' => 0, 'deleted' => 0];
        $existing = Customer::where('cluster_server_id', $cluster->id)->get()->keyBy('slug');

        foreach ($upstream as $entry) {
            $this->syncUpstreamEntry($cluster, $entry, $existing, $counts);
        }

        $this->removeStaleCustomers($cluster, array_column($upstream, 'slug'), $counts);

        return $counts;
    }

    /**
     * @param  array{slug: string, domain: string, status: string}  $entry
     * @param  Collection<string, Customer>  $existing
     * @param  array{inserted: int, updated: int, deleted: int}  $counts
     */
    private function syncUpstreamEntry(
        ClusterServer $cluster,
        array $entry,
        Collection $existing,
        array &$counts,
    ): void {
        $local = $existing->get($entry['slug']);
        if ($local === null) {
            $this->insertUpstreamCustomer($cluster, $entry, $counts);

            return;
        }

        if ($local->status !== $entry['status'] || $local->domain !== $entry['domain']) {
            $this->updateUpstreamCustomer($local, $entry, $counts);

            return;
        }

        $local->update(['last_sync_at' => now()]);
    }

    /**
     * @param  array{slug: string, domain: string, status: string}  $entry
     * @param  array{inserted: int, updated: int, deleted: int}  $counts
     */
    private function insertUpstreamCustomer(ClusterServer $cluster, array $entry, array &$counts): void
    {
        Customer::create([
            'slug' => $entry['slug'],
            'cluster_server_id' => $cluster->id,
            'domain' => $entry['domain'],
            'status' => $entry['status'],
            'last_sync_at' => now(),
        ]);
        $counts['inserted']++;
        $this->auditTenantSync('customer_sync_inserted', $entry['slug'], $entry, $cluster->id);
    }

    /**
     * @param  array{slug: string, domain: string, status: string}  $entry
     * @param  array{inserted: int, updated: int, deleted: int}  $counts
     */
    private function updateUpstreamCustomer(Customer $local, array $entry, array &$counts): void
    {
        $updates = ['domain' => $entry['domain'], 'last_sync_at' => now()];
        if (! in_array($local->status, CustomerLifecycleStatus::USER_OPS_BLOCKED, true)) {
            $updates['status'] = $entry['status'];
        }

        $local->update($updates);
        $counts['updated']++;
        $this->auditTenantSync('customer_sync_updated', $entry['slug'], [
            'new_status' => $entry['status'],
            'new_domain' => $entry['domain'],
        ], (string) $local->cluster_server_id);
    }

    /**
     * @param  list<string>  $upstreamSlugs
     * @param  array{inserted: int, updated: int, deleted: int}  $counts
     */
    private function removeStaleCustomers(ClusterServer $cluster, array $upstreamSlugs, array &$counts): void
    {
        Customer::where('cluster_server_id', $cluster->id)
            ->whereNotIn('slug', $upstreamSlugs)
            ->whereNull('deleted_at')
            ->each(function (Customer $customer) use (&$counts, $cluster): void {
                $previousStatus = $customer->status;
                $customer->update(['status' => 'removed']);
                $customer->delete();
                $counts['deleted']++;
                $this->auditTenantSync('customer_sync_removed', $customer->slug, [
                    'previous_status' => $previousStatus,
                ], $cluster->id);
            });
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
}
