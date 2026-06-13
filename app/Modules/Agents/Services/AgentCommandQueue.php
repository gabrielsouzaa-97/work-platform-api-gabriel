<?php

declare(strict_types=1);

namespace App\Modules\Agents\Services;

use App\Models\AgentCommand;
use App\Models\FarmAgent;
use Illuminate\Support\Str;

final class AgentCommandQueue
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function enqueue(
        FarmAgent $agent,
        string $operation,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): AgentCommand {
        return AgentCommand::create([
            'farm_agent_id' => $agent->id,
            'operation_id' => Str::uuid()->toString(),
            'operation' => $operation,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function poll(FarmAgent $agent, int $timeoutSeconds): array
    {
        $timeoutSeconds = max(1, min($timeoutSeconds, (int) config('services.agent.poll_timeout_seconds', 55)));
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $commands = AgentCommand::query()
                ->where('farm_agent_id', $agent->id)
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(10)
                ->get();

            if ($commands->isNotEmpty()) {
                return $commands->map(fn (AgentCommand $cmd): array => [
                    'schema_version' => 1,
                    'operation_id' => $cmd->operation_id,
                    'operation' => $cmd->operation,
                    'farm_id' => $agent->farm_id,
                    'idempotency_key' => $cmd->idempotency_key,
                    'payload' => $cmd->payload ?? [],
                    'requested_at' => $cmd->requested_at?->toIso8601String(),
                ])->all();
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        return [];
    }

    public function ack(FarmAgent $agent, string $operationId, string $terminalState): void
    {
        $status = in_array($terminalState, ['succeeded', 'failed', 'cancelled'], true)
            ? 'acked'
            : 'pending';

        if ($status !== 'acked') {
            return;
        }

        AgentCommand::query()
            ->where('operation_id', $operationId)
            ->where('farm_agent_id', $agent->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'acked',
                'acked_at' => now(),
            ]);
    }
}
