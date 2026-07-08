<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\Customer;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\OccPassthroughCommand;
use App\Modules\Integration\Dto\OccPassthroughOperation;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Integration\Services\PlatformPortFactory;

final class OccPassthroughService
{
    public function __construct(
        private readonly PlatformPortFactory $factory,
    ) {}

    /**
     * @param  array<int, string>  $args
     * @return array<string, mixed>
     *
     * @throws ClusterUnreachableException
     * @throws CapabilityBlockedException
     * @throws UpstreamUnavailableException
     */
    public function exec(Customer $customer, string $subcmd, array $args = [], int $timeoutSec = 60): array
    {
        $this->assertClusterReachable($customer);

        $result = $this->portFor($customer)->runOccPassthrough(
            $this->passthroughCommandFor($customer, $subcmd, $args, $timeoutSec),
        );

        return $result->payload;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    public function execThemingConfig(Customer $customer, array $fields, int $timeoutSec = 60): array
    {
        $this->assertClusterReachable($customer);

        $result = $this->portFor($customer)->runOccPassthrough(
            new OccPassthroughCommand(
                customer: $customer,
                operation: OccPassthroughOperation::SetBranding,
                fields: $fields,
                timeoutSec: $timeoutSec,
            ),
        );

        return $result->payload;
    }

    /** Pre-defined quota options — no SSH call needed. */
    public static function quotaOptions(): array
    {
        return [
            '512 MB', '1 GB', '2 GB', '5 GB', '10 GB', '20 GB', '50 GB', '100 GB', 'none', 'default',
        ];
    }

    private function assertClusterReachable(Customer $customer): void
    {
        $customer->loadMissing('clusterServer');
        $cluster = $customer->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            throw new ClusterUnreachableException;
        }
    }

    private function portFor(Customer $customer): PlatformPort
    {
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        return $this->factory->for($cluster);
    }

    /**
     * @param  array<int, string>  $args
     */
    private function passthroughCommandFor(
        Customer $customer,
        string $subcmd,
        array $args,
        int $timeoutSec,
    ): OccPassthroughCommand {
        $operation = match ($subcmd) {
            'user:list', 'group:list' => OccPassthroughOperation::UserList,
            'theming:config' => OccPassthroughOperation::SetBranding,
            'maintenance:mode' => OccPassthroughOperation::ToggleMaintenance,
            'files:scan' => OccPassthroughOperation::FilesRescan,
            'app:enable' => OccPassthroughOperation::AppEnable,
            'user:setting' => OccPassthroughOperation::SetQuota,
            'config:app:set' => OccPassthroughOperation::SetQuotaDefault,
            default => OccPassthroughOperation::UserList,
        };

        $fields = $operation === OccPassthroughOperation::UserList && $subcmd !== 'user:list'
            ? ['__subcmd' => $subcmd]
            : [];

        return new OccPassthroughCommand(
            customer: $customer,
            operation: $operation,
            args: $args,
            fields: $fields,
            timeoutSec: $timeoutSec,
        );
    }
}
