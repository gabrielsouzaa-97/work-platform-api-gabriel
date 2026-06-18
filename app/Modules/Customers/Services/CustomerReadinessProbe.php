<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Services\PlatformPortFactory;

final class CustomerReadinessProbe
{
    public function __construct(
        private readonly PlatformPortFactory $factory,
    ) {}

    public function isReady(Customer $customer): bool
    {
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            return false;
        }

        return $this->portFor($cluster)->probeReadiness(new ProbeReadinessCommand($customer))->ready;
    }

    private function portFor(ClusterServer $cluster): PlatformPort
    {
        return $this->factory->for($cluster);
    }
}
