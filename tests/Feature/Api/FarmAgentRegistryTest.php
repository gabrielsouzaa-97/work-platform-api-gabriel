<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Models\Operator;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('services.agent.transport_enabled', true);
});

it('admin can register farm agent and receive one-time token', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/farm-agents', [
        'farm_id' => 'farm-saas-prod-01',
        'cluster_server_id' => $cluster->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.farm_id', 'farm-saas-prod-01')
        ->assertJsonPath('data.cluster_server_id', $cluster->id)
        ->assertJsonStructure(['agent_token']);

    expect(FarmAgent::where('farm_id', 'farm-saas-prod-01')->exists())->toBeTrue();
});

it('admin can enqueue ping command for farm agent', function (): void {
    $admin = Operator::factory()->admin()->create();
    $agent = FarmAgent::factory()->create();

    $this->actingAs($admin)
        ->postJson('/api/farm-agents/'.$agent->id.'/ping')
        ->assertAccepted()
        ->assertJsonPath('operation', 'agent.ping');
});

it('rejects duplicate cluster_server_id link', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create();
    FarmAgent::factory()->create(['cluster_server_id' => $cluster->id]);

    $this->actingAs($admin)->postJson('/api/farm-agents', [
        'farm_id' => 'farm-other',
        'cluster_server_id' => $cluster->id,
    ])->assertUnprocessable();
});
