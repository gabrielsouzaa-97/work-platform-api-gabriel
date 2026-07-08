<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\TenantGroup;
use App\Modules\Customers\Dto\TenantGroupSyncReport;
use App\Modules\Customers\Support\TenantGroupListParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TenantGroupSyncService
{
    private const GRACE_MINUTES = 5;

    public function __construct(
        private readonly OccPassthroughService $occ,
    ) {}

    public function sync(Customer $customer): TenantGroupSyncReport
    {
        $report = new TenantGroupSyncReport;
        $upstreamNames = TenantGroupListParser::parseUpstreamList(
            $this->occ->exec($customer, 'group:list', ['--output=json'], 30),
        );

        $localByName = TenantGroup::query()
            ->where('customer_slug', $customer->slug)
            ->get()
            ->keyBy('name');

        $this->reconcileUpstream($customer, $upstreamNames, $localByName, $report);
        $this->deleteStaleRows(
            $customer,
            $localByName,
            $upstreamNames,
            Carbon::now()->subMinutes(self::GRACE_MINUTES),
            $report,
        );

        return $report;
    }

    /**
     * @param  list<string>  $upstreamNames
     * @param  Collection<string, TenantGroup>  $localByName
     */
    private function reconcileUpstream(
        Customer $customer,
        array $upstreamNames,
        Collection $localByName,
        TenantGroupSyncReport $report,
    ): void {
        $refreshedExisting = 0;

        foreach ($upstreamNames as $name) {
            $local = $localByName->get($name);

            if ($local === null) {
                $this->recordDrift($customer, $name, 'manual_creation', $report);
                $this->insertFromSync($customer, $name);
                $report->inserted++;

                continue;
            }

            if ($local->synced_at === null) {
                $local->update(['synced_at' => Carbon::now()]);
                $refreshedExisting++;
            }
        }

        $report->updated += $refreshedExisting;
    }

    /**
     * @param  list<string>  $upstreamNames
     * @param  Collection<string, TenantGroup>  $localByName
     */
    private function deleteStaleRows(
        Customer $customer,
        Collection $localByName,
        array $upstreamNames,
        Carbon $graceCutoff,
        TenantGroupSyncReport $report,
    ): void {
        foreach ($localByName as $name => $local) {
            if (in_array($name, $upstreamNames, true)) {
                continue;
            }

            if ($local->created_at !== null && $local->created_at->gte($graceCutoff)) {
                continue;
            }

            $local->delete();
            $report->deleted++;
        }
    }

    private function insertFromSync(Customer $customer, string $name): void
    {
        TenantGroup::create([
            'customer_slug' => $customer->slug,
            'name' => $name,
            'origin' => 'sync',
            'synced_at' => Carbon::now(),
        ]);
    }

    private function recordDrift(
        Customer $customer,
        string $name,
        string $kind,
        TenantGroupSyncReport $report,
    ): void {
        $report->driftDetected++;

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'tenant_group_drift',
            'resource_type' => 'customer',
            'resource_id' => $customer->slug,
            'payload' => [
                'customer_slug' => $customer->slug,
                'name' => $name,
                'kind' => $kind,
            ],
            'cluster_server_id' => $customer->cluster_server_id,
        ]);
    }
}
