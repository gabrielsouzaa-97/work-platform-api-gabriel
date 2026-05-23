<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Models\Customer;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;

final class CustomerReadinessProbe
{
    public function __construct(private readonly SshClientInterface $ssh) {}

    public function isReady(Customer $customer): bool
    {
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            return false;
        }

        try {
            $resp = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                [$customer->slug, 'occ-exec', 'user:list', '--json'],
                null,
                (int) config('services.customer_readiness.probe_timeout_seconds', 30),
            );

            return $resp->exitCode === 0;
        } catch (SshConnectionException|SshTimeoutException) {
            return false;
        }
    }
}
