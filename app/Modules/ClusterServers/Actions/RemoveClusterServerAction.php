<?php

declare(strict_types=1);

namespace App\Modules\ClusterServers\Actions;

use App\Models\ClusterServer;
use App\Models\Customer;
use RuntimeException;

final class RemoveClusterServerAction
{
    public const ERR_ACTIVE_CUSTOMERS = 'cluster_has_active_customers';

    public function execute(ClusterServer $cluster): void
    {
        if ($this->hasActiveCustomers($cluster)) {
            throw new RuntimeException(self::ERR_ACTIVE_CUSTOMERS);
        }

        $cluster->delete();
    }

    private function hasActiveCustomers(ClusterServer $cluster): bool
    {
        return Customer::query()
            ->where('cluster_server_id', $cluster->id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
