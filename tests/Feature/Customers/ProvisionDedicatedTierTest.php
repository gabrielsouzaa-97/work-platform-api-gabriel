<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Models\Operator;
use Illuminate\Support\Facades\Http;

function seedDedicatedFarm(string $farmId = 'farm-dedicated'): string
{
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'tier' => 'dedicated']);
    FarmAgent::factory()->create([
        'farm_id' => $farmId,
        'cluster_server_id' => $cluster->id,
    ]);
    FarmInventory::create([
        'farm_id' => $farmId,
        'active_tenants' => 1,
        'max_tenants' => 10,
        'available_slots' => 9,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 15,
        'reported_at' => now(),
    ]);

    return $cluster->id;
}

beforeEach(function (): void {
    config([
        'whmcs.url' => 'https://whmcs.test',
        'whmcs.identifier' => 'test-identifier',
        'whmcs.secret' => 'test-secret',
        'whmcs.dedicated_product_id' => 6,
    ]);
});

it('tier dedicated triggers WHMCS order path and saves customer with tier dedicated', function (): void {
    $expectedClusterId = seedDedicatedFarm();
    seedDedicatedFarm('farm-dedicated-other');

    Http::fake([
        'https://whmcs.test/includes/api.php' => Http::sequence()
            ->push(['result' => 'success', 'orderid' => 99, 'serviceids' => [501]], 200)
            ->push(['result' => 'success', 'orderid' => 99], 200)
            ->push(['result' => 'success', 'serviceid' => 501], 200),
    ]);

    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'dedicated-tenant',
        'tier' => 'dedicated',
        'auto_place' => true,
        'domain' => 'dedicated-tenant.example.com',
    ]);

    $response->assertStatus(201);

    $customer = Customer::find('dedicated-tenant');
    expect($customer)->not->toBeNull()
        ->and($customer->tier)->toBe('dedicated')
        ->and($customer->cluster_server_id)->toBe($expectedClusterId);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request['action'] === 'AddOrder'
        && $request['pid'] === [6]);
    Http::assertSent(fn ($request) => $request['action'] === 'AcceptOrder'
        && $request['orderid'] === 99);
    Http::assertSent(fn ($request) => $request['action'] === 'ModuleCreate'
        && $request['serviceid'] === 501);
});

it('rejects invalid tier values', function (): void {
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'bad-tier',
        'tier' => 'enterprise',
        'domain' => 'bad-tier.example.com',
        'cluster_server_id' => ClusterServer::factory()->create()->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['tier']);
});
