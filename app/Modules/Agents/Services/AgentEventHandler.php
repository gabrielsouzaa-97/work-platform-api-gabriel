<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

use App\Models\AgentCommand;
use App\Models\AuditLog;
use App\Models\FarmAgent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AgentEventHandler
{
    public function __construct(
        private readonly AgentCommandQueue $commandQueue,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(FarmAgent $agent, array $event): void
    {
        $agent->update(['last_seen_at' => now()]);

        $operationId = isset($event['operation_id']) ? (string) $event['operation_id'] : '';
        $state = isset($event['state']) ? (string) $event['state'] : '';

        if ($operationId !== '' && $state !== '' && $this->commandBelongsToAgent($operationId, $agent)) {
            $this->commandQueue->ack($agent, $operationId, $state);
            $this->storeOperationResult($operationId, $event, $state);
        }

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => null,
            'action' => 'farm_agent.event',
            'resource_type' => 'farm_agent',
            'resource_id' => $agent->id,
            'payload' => [
                'farm_id' => $agent->farm_id,
                'event_type' => $event['event_type'] ?? 'progress',
                'state' => $state,
                'step' => $event['step'] ?? null,
                'operation_id' => $operationId !== '' ? $operationId : null,
            ],
        ]);
    }

    private function commandBelongsToAgent(string $operationId, FarmAgent $agent): bool
    {
        return AgentCommand::query()
            ->where('operation_id', $operationId)
            ->where('farm_agent_id', $agent->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function storeOperationResult(string $operationId, array $event, string $state): void
    {
        $data = $event['data'] ?? null;
        if (! is_array($data)) {
            if ($state === 'failed' && isset($event['message']) && is_string($event['message'])) {
                Cache::put(
                    AgentUpstreamGateway::resultCacheKey($operationId),
                    ['error' => $event['message']],
                    120,
                );
            }

            return;
        }

        if (isset($data['job_id']) && is_string($data['job_id']) && $data['job_id'] !== '') {
            Cache::put(
                AgentUpstreamGateway::resultCacheKey($operationId),
                ['job_id' => $data['job_id']],
                120,
            );
        }
    }
}
