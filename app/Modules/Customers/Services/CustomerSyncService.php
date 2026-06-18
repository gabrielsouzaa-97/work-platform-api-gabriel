<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\ClusterServer;
use App\Modules\Customers\Dto\SyncReport;
use App\Modules\Integration\Dto\SyncTenantCommand;
use App\Modules\Integration\Services\PlatformPortFactory;

final class CustomerSyncService
{
    public function __construct(private readonly PlatformPortFactory $factory) {}

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
        $result = $this->factory->for($cluster)->syncTenant(new SyncTenantCommand($cluster));

        $report = new SyncReport;
        $report->inserted = $result->inserted;
        $report->updated = $result->updated;
        $report->deleted = $result->deleted;

        return $report;
    }
}
