<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Integration\Services\PlatformPortFactory;

final class CustomerReadinessProbe
{
    public function __construct(
        private readonly PlatformPortFactory $factory,
    ) {}

    public function isReady(Customer $customer): bool
    {
        return $this->probe($customer)->ready;
    }

    public function probe(Customer $customer): ReadinessReport
    {
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            return new ReadinessReport(false, 'cluster not active', 'cluster');
        }

        return $this->portFor($cluster)->probeReadiness(new ProbeReadinessCommand($customer));
    }

    private function portFor(ClusterServer $cluster): PlatformPort
    {
        return $this->factory->for($cluster);
    }
}
