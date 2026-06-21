<?php

declare(strict_types=1);

use App\Models\FarmAgent;
use App\Models\FarmInventory;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('services.agent.transport_enabled', true);
});

function farmInventoryAuthHeaders(FarmAgent $agent): array
{
    return [
        'Authorization' => 'Bearer test-agent-token',
        'X-Farm-Id' => $agent->farm_id,
        'Accept' => 'application/json',
    ];
}

function farmInventoryPayload(string $farmId, int $active = 10, int $max = 100): array
{
    return [
        'schema_version' => 1,
        'operation' => 'farm.inventory',
        'farm_id' => $farmId,
        'reported_at' => now()->toIso8601String(),
        'capacity' => [
            'max_tenants' => $max,
            'active_tenants' => $active,
            'available_slots' => $max - $active,
        ],
        'versions' => [
            'nextcloud' => '31.0.0',
            'platform' => '1.0.0-rc.3',
            'roundcube' => '1.6.8',
        ],
        'latency_ms' => 42,
        'tenants' => [
            ['slug' => 'tenant-a', 'status' => 'active'],
        ],
    ];
}

it('persists farm.inventory from authenticated agent', function (): void {
    $agent = FarmAgent::factory()->create(['farm_id' => 'farm-saas-prod-01']);

    $this->postJson(
        '/api/agent/v1/inventory',
        farmInventoryPayload($agent->farm_id),
        farmInventoryAuthHeaders($agent),
    )->assertAccepted()
        ->assertJsonPath('data.farm_id', 'farm-saas-prod-01');

    $inventory = FarmInventory::where('farm_id', $agent->farm_id)->first();
    expect($inventory)->not->toBeNull()
        ->and($inventory->active_tenants)->toBe(10)
        ->and($inventory->max_tenants)->toBe(100)
        ->and($inventory->platform_version)->toBe('1.0.0-rc.3')
        ->and($inventory->latency_ms)->toBe(42);
});

it('updates existing inventory on subsequent farm.inventory report', function (): void {
    $agent = FarmAgent::factory()->create(['farm_id' => 'farm-saas-prod-02']);

    $this->postJson(
        '/api/agent/v1/inventory',
        farmInventoryPayload($agent->farm_id, active: 5, max: 50),
        farmInventoryAuthHeaders($agent),
    )->assertAccepted();

    $this->postJson(
        '/api/agent/v1/inventory',
        farmInventoryPayload($agent->farm_id, active: 40, max: 50),
        farmInventoryAuthHeaders($agent),
    )->assertAccepted();

    expect(FarmInventory::where('farm_id', $agent->farm_id)->count())->toBe(1);

    $inventory = FarmInventory::where('farm_id', $agent->farm_id)->first();
    expect($inventory->active_tenants)->toBe(40)
        ->and($inventory->available_slots)->toBe(10);
});

it('rejects farm.inventory ingest without valid agent bearer token', function (): void {
    $agent = FarmAgent::factory()->create();

    $this->postJson(
        '/api/agent/v1/inventory',
        farmInventoryPayload($agent->farm_id),
        ['Authorization' => 'Bearer wrong-token', 'X-Farm-Id' => $agent->farm_id],
    )->assertUnauthorized();

    expect(FarmInventory::count())->toBe(0);
});

it('rejects farm.inventory payload missing required capacity fields', function (): void {
    $agent = FarmAgent::factory()->create();
    $payload = farmInventoryPayload($agent->farm_id);
    unset($payload['capacity']['max_tenants']);

    $this->postJson('/api/agent/v1/inventory', $payload, farmInventoryAuthHeaders($agent))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['capacity.max_tenants']);

    expect(FarmInventory::count())->toBe(0);
});
