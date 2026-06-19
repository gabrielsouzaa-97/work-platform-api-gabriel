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

function validFarmInventoryData(): array
{
    return [
        'tenant_count' => 12,
        'tenant_capacity' => 50,
        'available_slots' => 38,
        'platform_version' => '1.0.0-rc2',
        'nextcloud_version' => '32.0.1',
        'latency_ms' => 18,
        'tenants' => [
            ['slug' => 'acme-corp', 'status' => 'active'],
            ['slug' => 'beta-co', 'status' => 'provisioning'],
        ],
    ];
}

it('persists farm.inventory snapshot from authenticated agent event', function (): void {
    $agent = FarmAgent::factory()->create(['farm_id' => 'farm-saas-prod-01']);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation' => 'farm.inventory',
        'farm_id' => $agent->farm_id,
        'state' => 'succeeded',
        'data' => validFarmInventoryData(),
        'ts' => now()->toIso8601String(),
    ], farmInventoryAuthHeaders($agent))->assertAccepted();

    $this->assertDatabaseHas('farm_inventories', [
        'farm_agent_id' => $agent->id,
        'tenant_count' => 12,
        'tenant_capacity' => 50,
        'available_slots' => 38,
        'platform_version' => '1.0.0-rc2',
        'latency_ms' => 18,
    ]);

    expect(FarmInventory::where('farm_agent_id', $agent->id)->count())->toBe(1);
});

it('upserts farm.inventory when the same farm reports again', function (): void {
    $agent = FarmAgent::factory()->create(['farm_id' => 'farm-saas-prod-02']);
    $headers = farmInventoryAuthHeaders($agent);
    $payload = [
        'schema_version' => 1,
        'operation' => 'farm.inventory',
        'farm_id' => $agent->farm_id,
        'state' => 'succeeded',
        'ts' => now()->toIso8601String(),
    ];

    $this->postJson('/api/agent/v1/events', [
        ...$payload,
        'data' => array_merge(validFarmInventoryData(), ['tenant_count' => 5]),
    ], $headers)->assertAccepted();

    $this->postJson('/api/agent/v1/events', [
        ...$payload,
        'data' => array_merge(validFarmInventoryData(), [
            'tenant_count' => 20,
            'available_slots' => 30,
            'latency_ms' => 9,
        ]),
    ], $headers)->assertAccepted();

    expect(FarmInventory::where('farm_agent_id', $agent->id)->count())->toBe(1);

    $this->assertDatabaseHas('farm_inventories', [
        'farm_agent_id' => $agent->id,
        'tenant_count' => 20,
        'available_slots' => 30,
        'latency_ms' => 9,
    ]);
});

it('rejects farm.inventory event without agent authentication', function (): void {
    FarmAgent::factory()->create(['farm_id' => 'farm-saas-prod-03']);

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation' => 'farm.inventory',
        'farm_id' => 'farm-saas-prod-03',
        'state' => 'succeeded',
        'data' => validFarmInventoryData(),
        'ts' => now()->toIso8601String(),
    ])->assertUnauthorized();

    expect(FarmInventory::count())->toBe(0);
});

it('rejects farm.inventory payload missing capacity fields', function (): void {
    $agent = FarmAgent::factory()->create();

    $this->postJson('/api/agent/v1/events', [
        'schema_version' => 1,
        'operation' => 'farm.inventory',
        'farm_id' => $agent->farm_id,
        'state' => 'succeeded',
        'data' => [
            'platform_version' => '1.0.0-rc2',
            'latency_ms' => 10,
        ],
        'ts' => now()->toIso8601String(),
    ], farmInventoryAuthHeaders($agent))->assertUnprocessable();

    expect(FarmInventory::count())->toBe(0);
});
