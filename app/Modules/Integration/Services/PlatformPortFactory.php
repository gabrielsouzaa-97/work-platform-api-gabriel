<?php

declare(strict_types=1);

namespace App\Modules\Integration\Services;

use App\Models\ClusterServer;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Contracts\PlatformPort;
use App\Modules\Integration\Dto\AsyncJobRef;
use App\Modules\Integration\Dto\ManageAsyncCommand;

final class PlatformPortFactory
{
    public function __construct(
        private readonly AgentTransportResolver $transportResolver,
        private readonly SshPlatformAdapter $sshAdapter,
        private readonly AgentPlatformAdapter $agentAdapter,
    ) {}

    public function for(ClusterServer $cluster, ?string $stagingId = null): PlatformPort
    {
        if ($this->shouldUseAgentTransport($cluster, $stagingId)) {
            return $this->agentAdapter;
        }

        return $this->sshAdapter;
    }

    public function dispatchManageAsync(
        ClusterServer $cluster,
        array $manageArgs,
        ?string $stdinJson,
    ): AsyncJobRef {
        return $this->sshAdapter->dispatchManageAsync(
            new ManageAsyncCommand($cluster, $manageArgs, $stdinJson),
        );
    }

    public function shouldUseAgentTransport(ClusterServer $cluster, ?string $stagingId): bool
    {
        return $stagingId === null && $this->transportResolver->shouldUseAgentTransport($cluster);
    }
}
