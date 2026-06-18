<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contracts;

use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\BrandingResult;
use App\Modules\Integration\Dto\CancelJobCommand;
use App\Modules\Integration\Dto\CancelJobResult;
use App\Modules\Integration\Dto\ClusterHealthReport;
use App\Modules\Integration\Dto\CreateTenantCommand;
use App\Modules\Integration\Dto\EnableAppsCommand;
use App\Modules\Integration\Dto\FetchJobLogsCommand;
use App\Modules\Integration\Dto\JobLogsResult;
use App\Modules\Integration\Dto\JobStatusResult;
use App\Modules\Integration\Dto\ManageAsyncCommand;
use App\Modules\Integration\Dto\OccPassthroughCommand;
use App\Modules\Integration\Dto\OccPassthroughResult;
use App\Modules\Integration\Dto\PollJobStatusCommand;
use App\Modules\Integration\Dto\ProbeClusterHealthCommand;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Integration\Dto\SetBrandingCommand;
use App\Modules\Integration\Dto\SyncTenantCommand;
use App\Modules\Integration\Dto\SyncTenantResult;

interface PlatformPort
{
    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     */
    public function createTenant(CreateTenantCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     */
    public function enableApps(EnableAppsCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws SshRemoteException
     * @throws SshTimeoutException
     */
    public function dispatchManageAsync(ManageAsyncCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws SshTimeoutException
     * @throws SshRemoteException
     */
    public function setBranding(SetBrandingCommand $command): BrandingResult;

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport;

    /**
     * @throws SshClientException
     * @throws SshTimeoutException
     */
    public function probeClusterHealth(ProbeClusterHealthCommand $command): ClusterHealthReport;

    public function fetchJobLogs(FetchJobLogsCommand $command): JobLogsResult;

    public function cancelJob(CancelJobCommand $command): CancelJobResult;

    public function pollJobStatus(PollJobStatusCommand $command): JobStatusResult;

    public function syncTenant(SyncTenantCommand $command): SyncTenantResult;

    public function runOccPassthrough(OccPassthroughCommand $command): OccPassthroughResult;
}
