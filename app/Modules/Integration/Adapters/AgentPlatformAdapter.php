<?php

declare(strict_types=1);

namespace App\Modules\Integration\Adapters;

use App\Modules\Agents\Exceptions\AgentTransportException;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Adapters\Concerns\MapsTransportExceptions;
use App\Modules\Integration\Contracts\PlatformPort;
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
use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Support\Facades\Log;

final class AgentPlatformAdapter implements PlatformPort
{
    use MapsTransportExceptions;

    public function __construct(
        private readonly AgentUpstreamGateway $agentGateway,
        private readonly AgentTransportResolver $transportResolver,
        private readonly SshPlatformAdapter $sshFallback,
        private readonly TransportObservability $observability,
    ) {}

    public function createTenant(CreateTenantCommand $command): AsyncJobRef
    {
        if ($command->stagingId !== null) {
            return $this->sshFallback->createTenant($command);
        }

        $correlationId = Log::sharedContext()['correlation_id'] ?? null;
        if (is_string($correlationId) && $correlationId !== '') {
            Log::withContext(['correlation_id' => $correlationId]);
        }

        try {
            $resp = $this->agentGateway->runAsync(
                $command->cluster,
                'nextcloud-manage',
                $command->manageArgs,
                $command->stdinJson,
            );
        } catch (AgentTransportException) {
            throw new ClusterUnreachableException;
        } catch (\Throwable $e) {
            $this->mapTransportException($e);
        }

        return $this->jobRefFromResponse($resp);
    }

    public function enableApps(EnableAppsCommand $command): AsyncJobRef
    {
        return $this->sshFallback->enableApps($command);
    }

    public function dispatchManageAsync(ManageAsyncCommand $command): AsyncJobRef
    {
        $correlationId = Log::sharedContext()['correlation_id'] ?? null;
        if (is_string($correlationId) && $correlationId !== '') {
            Log::withContext(['correlation_id' => $correlationId]);
        }

        try {
            $resp = $this->agentGateway->runAsync(
                $command->cluster,
                'nextcloud-manage',
                $command->manageArgs,
                $command->stdinJson,
            );
        } catch (AgentTransportException) {
            throw new ClusterUnreachableException;
        } catch (\Throwable $e) {
            $this->mapTransportException($e);
        }

        return $this->jobRefFromResponse($resp);
    }

    public function setBranding(SetBrandingCommand $command): BrandingResult
    {
        return $this->sshFallback->setBranding($command);
    }

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport
    {
        return $this->sshFallback->probeReadiness($command);
    }

    public function probeClusterHealth(ProbeClusterHealthCommand $command): ClusterHealthReport
    {
        return $this->sshFallback->probeClusterHealth($command);
    }

    public function fetchJobLogs(FetchJobLogsCommand $command): JobLogsResult
    {
        return $this->sshFallback->fetchJobLogs($command);
    }

    public function cancelJob(CancelJobCommand $command): CancelJobResult
    {
        return $this->sshFallback->cancelJob($command);
    }

    public function pollJobStatus(PollJobStatusCommand $command): JobStatusResult
    {
        return $this->sshFallback->pollJobStatus($command);
    }

    public function syncTenant(SyncTenantCommand $command): SyncTenantResult
    {
        return $this->sshFallback->syncTenant($command);
    }

    public function runOccPassthrough(OccPassthroughCommand $command): OccPassthroughResult
    {
        return $this->sshFallback->runOccPassthrough($command);
    }

    public function removeTenant(RemoveTenantCommand $command): AsyncJobRef
    {
        if ($this->transportResolver->shouldUseAgentTransport($command->cluster)) {
            return $this->dispatchManageAsync(new ManageAsyncCommand(
                $command->cluster,
                $command->manageArgs,
                null,
            ));
        }

        return $this->sshFallback->removeTenant($command);
    }

    public function syncWebhookSecret(SyncWebhookSecretCommand $command): void
    {
        $this->sshFallback->syncWebhookSecret($command);
    }

    private function jobRefFromResponse(SshResponse $resp): AsyncJobRef
    {
        $jobId = $resp->parsedJson['job_id'] ?? null;
        if (! is_string($jobId) || $jobId === '') {
            throw new \RuntimeException('Agent transport did not return job_id');
        }

        $this->observability->recordDispatch(TransportObservability::TRANSPORT_AGENT, $jobId);

        return new AsyncJobRef($jobId);
    }
}
