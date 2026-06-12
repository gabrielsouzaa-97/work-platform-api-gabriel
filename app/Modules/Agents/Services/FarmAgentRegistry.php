<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

use App\Models\AuditLog;
use App\Models\FarmAgent;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FarmAgentRegistry
{
    /**
     * @return array{farmAgent: FarmAgent, rawToken: string}
     */
    public function register(
        string $farmId,
        ?string $clusterServerId,
        ?string $mtlsFingerprint,
        Operator $actor,
    ): array {
        if (FarmAgent::withTrashed()->where('farm_id', $farmId)->exists()) {
            throw ValidationException::withMessages([
                'farm_id' => ['farm_id already registered'],
            ]);
        }

        if ($clusterServerId !== null
            && FarmAgent::where('cluster_server_id', $clusterServerId)->exists()) {
            throw ValidationException::withMessages([
                'cluster_server_id' => ['cluster already linked to a farm agent'],
            ]);
        }

        $rawToken = 'ag_'.bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $agent = DB::transaction(function () use ($farmId, $clusterServerId, $mtlsFingerprint, $tokenHash, $actor): FarmAgent {
            $agent = FarmAgent::create([
                'farm_id' => $farmId,
                'cluster_server_id' => $clusterServerId,
                'agent_token_hash' => $tokenHash,
                'mtls_cert_fingerprint' => $mtlsFingerprint,
                'status' => 'active',
            ]);

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => $actor->id,
                'action' => 'farm_agent.create',
                'resource_type' => 'farm_agent',
                'resource_id' => $agent->id,
                'payload' => [
                    'farm_id' => $farmId,
                    'cluster_server_id' => $clusterServerId,
                ],
            ]);

            return $agent;
        });

        return ['farmAgent' => $agent, 'rawToken' => $rawToken];
    }

    public function revoke(FarmAgent $agent, Operator $actor): FarmAgent
    {
        $agent->update(['status' => 'revoked']);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actor->id,
            'action' => 'farm_agent.revoke',
            'resource_type' => 'farm_agent',
            'resource_id' => $agent->id,
            'payload' => ['farm_id' => $agent->farm_id],
        ]);

        return $agent->fresh();
    }
}
