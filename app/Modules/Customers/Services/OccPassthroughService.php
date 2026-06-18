<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\Customer;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\OccPassthroughCommand;
use App\Modules\Integration\Dto\OccPassthroughOperation;
use App\Modules\Integration\Services\PlatformPortFactory;

final class OccPassthroughService
{
    public function __construct(
        private readonly PlatformPortFactory $factory,
    ) {}

    /**
     * Execute an OCC subcommand synchronously via `nextcloud-manage <client> occ-exec <subcmd>`.
     *
     * [E1,E8] No domain positional argument — slug directly followed by occ-exec.
     *
     * @param  array<int, string>  $args  Extra positional/flag arguments for the OCC subcommand.
     * @return array<string, mixed>
     *
     * @throws ClusterUnreachableException
     * @throws SshTimeoutException
     * @throws SshRemoteException
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
     * Apply theming keys one at a time — OCC `theming:config` accepts only `<key> <value>` per invocation (P-10).
     *
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
            'user:list' => OccPassthroughOperation::UserList,
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

    /**
     * @throws ClusterUnreachableException
     */
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
        $customer->loadMissing('clusterServer');
        $cluster = $customer->clusterServer;

        return $this->factory->for($cluster);
    }
}
