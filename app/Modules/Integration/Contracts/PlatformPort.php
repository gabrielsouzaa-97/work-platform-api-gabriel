<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contracts;

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
use App\Modules\Integration\Dto\RemoveTenantCommand;
use App\Modules\Integration\Dto\SetBrandingCommand;
use App\Modules\Integration\Dto\SyncTenantCommand;
use App\Modules\Integration\Dto\SyncTenantResult;
use App\Modules\Integration\Dto\SyncWebhookSecretCommand;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Integration\Exceptions\PortIdempotencyConflictException;
use App\Modules\Integration\Exceptions\PortStateConflictException;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;

interface PlatformPort
{
    /**
     * @throws ClusterUnreachableException
     * @throws PortIdempotencyConflictException
     * @throws PortStateConflictException
     * @throws UpstreamUnavailableException
     */
    public function createTenant(CreateTenantCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws PortStateConflictException
     * @throws UpstreamUnavailableException
     */
    public function enableApps(EnableAppsCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws PortStateConflictException
     * @throws UpstreamUnavailableException
     */
    public function dispatchManageAsync(ManageAsyncCommand $command): AsyncJobRef;

    /**
     * @throws ClusterUnreachableException
     * @throws CapabilityBlockedException
     * @throws UpstreamUnavailableException
     */
    public function setBranding(SetBrandingCommand $command): BrandingResult;

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport;

    /**
     * @throws UpstreamUnavailableException
     */
    public function probeClusterHealth(ProbeClusterHealthCommand $command): ClusterHealthReport;

    /**
     * @throws UpstreamUnavailableException
     */
    public function fetchJobLogs(FetchJobLogsCommand $command): JobLogsResult;

    /**
     * @throws UpstreamUnavailableException
     */
    public function cancelJob(CancelJobCommand $command): CancelJobResult;

    /**
     * @throws UpstreamUnavailableException
     */
    public function pollJobStatus(PollJobStatusCommand $command): JobStatusResult;

    public function syncTenant(SyncTenantCommand $command): SyncTenantResult;

    /**
     * @throws ClusterUnreachableException
     * @throws CapabilityBlockedException
     * @throws UpstreamUnavailableException
     */
    public function runOccPassthrough(OccPassthroughCommand $command): OccPassthroughResult;

    /**
     * @throws ClusterUnreachableException
     * @throws PortStateConflictException
     * @throws UpstreamUnavailableException
     */
    public function removeTenant(RemoveTenantCommand $command): AsyncJobRef;

    /**
     * @throws UpstreamUnavailableException
     */
    public function syncWebhookSecret(SyncWebhookSecretCommand $command): void;
}
