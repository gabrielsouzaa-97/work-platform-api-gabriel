<?php

declare(strict_types=1);

namespace App\Modules\Integration\Adapters;

use App\Models\ClusterServer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Services\OccPassthroughService;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\BrandingResult;
use App\Modules\Integration\Dto\CreateTenantCommand;
use App\Modules\Integration\Dto\EnableAppsCommand;
use App\Modules\Integration\Dto\ManageAsyncCommand;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Integration\Dto\SetBrandingCommand;

final class SshPlatformAdapter implements PlatformPort
{
    public function __construct(
        private readonly SshClientInterface $ssh,
        private readonly OccPassthroughService $occ,
    ) {}

    public function createTenant(CreateTenantCommand $command): AsyncJobRef
    {
        return $this->toJobRef($this->runManageAsync(
            $command->cluster,
            $command->manageArgs,
            $command->stdinJson,
        ));
    }

    public function enableApps(EnableAppsCommand $command): AsyncJobRef
    {
        return $this->toJobRef($this->runManageAsync(
            $command->cluster,
            $command->manageArgs,
            null,
        ));
    }

    public function setBranding(SetBrandingCommand $command): BrandingResult
    {
        $payload = $this->occ->execThemingConfig($command->customer, $command->fields);

        return new BrandingResult($payload);
    }

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport
    {
        $customer = $command->customer;
        $cluster = $customer->clusterServer ?? $customer->load('clusterServer')->clusterServer;

        if ($cluster === null || $cluster->status !== 'active') {
            return new ReadinessReport(false);
        }

        try {
            $resp = $this->ssh->run(
                $cluster,
                'nextcloud-manage',
                [$customer->slug, 'occ-exec', 'user:list', '--json'],
                null,
                (int) config('services.customer_readiness.probe_timeout_seconds', 30),
            );

            return new ReadinessReport($resp->exitCode === 0);
        } catch (SshConnectionException|SshTimeoutException) {
            return new ReadinessReport(false);
        }
    }

    public function dispatchManageAsync(ManageAsyncCommand $command): AsyncJobRef
    {
        return $this->toJobRef($this->runManageAsync(
            $command->cluster,
            $command->manageArgs,
            $command->stdinJson,
        ));
    }

    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     * @throws SshTimeoutException
     */
    private function runManageAsync(
        ClusterServer $cluster,
        array $manageArgs,
        ?string $stdinJson,
    ): SshResponse {
        try {
            return $this->ssh->runAsync($cluster, 'nextcloud-manage', $manageArgs, $stdinJson);
        } catch (SshConnectionException) {
            throw new ClusterUnreachableException;
        }
    }

    private function toJobRef(SshResponse $resp): AsyncJobRef
    {
        $jobId = $resp->parsedJson['job_id'] ?? null;
        if (! is_string($jobId) || $jobId === '') {
            throw new \RuntimeException('Upstream did not return job_id in async response');
        }

        return new AsyncJobRef($jobId);
    }
}
