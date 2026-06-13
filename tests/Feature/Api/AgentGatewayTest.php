<?php

declare(strict_types=1);

use App\Models\AgentCommand;
use App\Models\FarmAgent;
use App\Modules\Agents\Services\AgentCommandQueue;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('services.agent.transport_enabled', true);
});

function agentAuthHeaders(FarmAgent $agent, string $rawToken = 'test-agent-token'): array
{
    return [
        'Authorization' => 'Bearer '.$rawToken,
        'X-Farm-Id' => $agent->farm_id,
        'Accept' => 'application/json',
    ];
}

it('returns 503 when agent transport is disabled', function (): void {
    Config::set('services.agent.transport_enabled', false);

    $agent = FarmAgent::factory()->create();

    $this->getJson('/api/agent/v1/commands?farm_id='.$agent->farm_id, agentAuthHeaders($agent))
        ->assertStatus(503)
        ->assertJson(['error' => 'agent_transport_disabled']);
});

it('poll returns pending commands for authenticated agent', function (): void {
    $agent = FarmAgent::factory()->create();
    app(AgentCommandQueue::class)->enqueue($agent, 'agent.ping');

    $response = $this->getJson(
        '/api/agent/v1/commands?farm_id='.$agent->farm_id.'&timeout=1',
        agentAuthHeaders($agent),
    );

    $response->assertOk()
        ->assertJsonPath('schema_version', 1)
        ->assertJsonCount(1, 'commands')
        ->assertJsonPath('commands.0.operation', 'agent.ping');
});

it('rejects invalid bearer token', function (): void {
    $agent = FarmAgent::factory()->create();

    $this->getJson('/api/agent/v1/commands?farm_id='.$agent->farm_id, [
        'Authorization' => 'Bearer wrong-token',
        'X-Farm-Id' => $agent->farm_id,
    ])->assertUnauthorized();
});

it('receiveEvents updates last_seen and acks command', function (): void {
    $agent = FarmAgent::factory()->create(['last_seen_at' => null]);
    $command = AgentCommand::create([
        'farm_agent_id' => $agent->id,
        'operation_id' => '550e8400-e29b-41d4-a716-446655440000',
        'operation' => 'agent.ping',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $agent->farm_id,
        'state' => 'succeeded',
        'step' => 'pong',
        'ts' => now()->toIso8601String(),
    ], agentAuthHeaders($agent))->assertAccepted();

    $agent->refresh();
    expect($agent->last_seen_at)->not->toBeNull();

    $command->refresh();
    expect($command->status)->toBe('acked');
});

it('receiveEvents ignores operation_id owned by another farm agent', function (): void {
    $owner = FarmAgent::factory()->create();
    $intruder = FarmAgent::factory()->create();
    $command = AgentCommand::create([
        'farm_agent_id' => $owner->id,
        'operation_id' => '660e8400-e29b-41d4-a716-446655440001',
        'operation' => 'tenant.create',
        'status' => 'pending',
        'requested_at' => now(),
    ]);
    $injectedJobId = '770e8400-e29b-41d4-a716-446655440002';

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation_id' => $command->operation_id,
        'farm_id' => $intruder->farm_id,
        'state' => 'succeeded',
        'data' => ['job_id' => $injectedJobId],
        'ts' => now()->toIso8601String(),
    ], agentAuthHeaders($intruder))->assertAccepted();

    $command->refresh();
    expect($command->status)->toBe('pending');
    expect(Cache::get(AgentUpstreamGateway::resultCacheKey($command->operation_id)))->toBeNull();
});
