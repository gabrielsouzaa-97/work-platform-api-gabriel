<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

use App\Models\ClusterServer;
use App\Models\FarmAgent;

final class AgentTransportResolver
{
    public function isTransportEnabled(): bool
    {
        return (bool) config('services.agent.transport_enabled', false);
    }

    public function shouldUseAgentTransport(ClusterServer $cluster): bool
    {
        if (! $this->isTransportEnabled()) {
            return false;
        }

        $agent = FarmAgent::query()
            ->where('cluster_server_id', $cluster->id)
            ->where('status', 'active')
            ->first();

        return $agent !== null && $agent->isOnline();
    }

    public function findAgentForCluster(ClusterServer $cluster): ?FarmAgent
    {
        return FarmAgent::query()
            ->where('cluster_server_id', $cluster->id)
            ->where('status', 'active')
            ->first();
    }
}
