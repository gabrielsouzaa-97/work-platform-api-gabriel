<?php

declare(strict_types=1);

namespace App\Modules\Integration\Adapters;

use App\Modules\Agents\Exceptions\AgentTransportException;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\BrandingResult;
use App\Modules\Integration\Dto\CreateTenantCommand;
use App\Modules\Integration\Dto\EnableAppsCommand;
use App\Modules\Integration\Dto\ProbeReadinessCommand;
use App\Modules\Integration\Dto\ReadinessReport;
use App\Modules\Integration\Dto\SetBrandingCommand;

final class AgentPlatformAdapter implements PlatformPort
{
    public function __construct(
        private readonly AgentUpstreamGateway $agentGateway,
        private readonly SshPlatformAdapter $sshFallback,
    ) {}

    public function createTenant(CreateTenantCommand $command): AsyncJobRef
    {
        if ($command->stagingId !== null) {
            return $this->sshFallback->createTenant($command);
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
        }

        return $this->jobRefFromResponse($resp);
    }

    public function enableApps(EnableAppsCommand $command): AsyncJobRef
    {
        return $this->sshFallback->enableApps($command);
    }

    public function setBranding(SetBrandingCommand $command): BrandingResult
    {
        return $this->sshFallback->setBranding($command);
    }

    public function probeReadiness(ProbeReadinessCommand $command): ReadinessReport
    {
        return $this->sshFallback->probeReadiness($command);
    }

    private function jobRefFromResponse(SshResponse $resp): AsyncJobRef
    {
        $jobId = $resp->parsedJson['job_id'] ?? null;
        if (! is_string($jobId) || $jobId === '') {
            throw new \RuntimeException('Agent transport did not return job_id');
        }

        return new AsyncJobRef($jobId);
    }
}
