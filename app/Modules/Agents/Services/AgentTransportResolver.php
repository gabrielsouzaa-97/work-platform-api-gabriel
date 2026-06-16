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

        return $this->resolveActiveAgent($cluster) !== null;
    }

    public function findAgentForCluster(ClusterServer $cluster): ?FarmAgent
    {
        return $this->resolveActiveAgent($cluster);
    }

    private function resolveActiveAgent(ClusterServer $cluster): ?FarmAgent
    {
        $agent = FarmAgent::query()
            ->where('cluster_server_id', $cluster->id)
            ->where('status', 'active')
            ->first();

        if ($agent === null || ! $agent->isOnline()) {
            return null;
        }

        return $agent;
    }
}
