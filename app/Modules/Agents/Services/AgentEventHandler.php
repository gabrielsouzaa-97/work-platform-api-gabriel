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

        if ($state === 'failed') {
            $error = $this->resolveFailureMessage($event, $data);
            if ($error !== null) {
                Cache::put(
                    AgentUpstreamGateway::resultCacheKey($operationId),
                    ['error' => $error],
                    120,
                );

                return;
            }
        }

        if (is_array($data) && $this->storeJobIdIfPresent($operationId, $data)) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeJobIdIfPresent(string $operationId, array $data): bool
    {
        if (! isset($data['job_id']) || ! is_string($data['job_id']) || $data['job_id'] === '') {
            return false;
        }

        Cache::put(
            AgentUpstreamGateway::resultCacheKey($operationId),
            ['job_id' => $data['job_id']],
            120,
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveFailureMessage(array $event, mixed $data): ?string
    {
        if (is_array($data) && array_key_exists('error', $data)) {
            return $this->formatAgentError($data['error']);
        }

        if (isset($event['message']) && is_string($event['message']) && $event['message'] !== '') {
            return $event['message'];
        }

        return null;
    }

    private function formatAgentError(mixed $error): string
    {
        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (is_array($error)) {
            $messages = array_values(array_filter($error, static fn (mixed $item): bool => is_string($item) && $item !== ''));

            if ($messages !== []) {
                return implode('; ', $messages);
            }
        }

        return 'Agent operation failed';
    }
}
