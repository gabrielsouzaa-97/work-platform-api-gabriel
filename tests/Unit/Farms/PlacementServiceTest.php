<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Modules\Farms\Dto\PlacementCriteria;
use App\Modules\Farms\Exceptions\NoFarmCapacityException;
use App\Modules\Farms\Services\PlacementService;

/**
 * @return array{inventory: FarmInventory, cluster_server_id: int}
 */
function seedFarmInventory(
    string $farmId,
    int $active,
    int $max,
    string $platformVersion = '1.0.0-rc.3',
    int $latencyMs = 50,
): array {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'farm_id' => $farmId,
        'cluster_server_id' => $cluster->id,
    ]);

    $inventory = FarmInventory::create([
        'farm_id' => $farmId,
        'active_tenants' => $active,
        'max_tenants' => $max,
        'available_slots' => $max - $active,
        'platform_version' => $platformVersion,
        'latency_ms' => $latencyMs,
        'reported_at' => now(),
    ]);

    return ['inventory' => $inventory, 'cluster_server_id' => $cluster->id];
}

it('selects farm with available capacity over full farm', function (): void {
    seedFarmInventory('farm-full', active: 100, max: 100);
    $open = seedFarmInventory('farm-open', active: 10, max: 100);

    $result = app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3'),
    );

    expect($result->farmId)->toBe('farm-open')
        ->and($result->clusterServerId)->toBe($open['cluster_server_id']);
});

it('excludes farms at full capacity', function (): void {
    seedFarmInventory('farm-a', active: 100, max: 100);
    seedFarmInventory('farm-b', active: 100, max: 100);

    app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3'),
    );
})->throws(NoFarmCapacityException::class);

it('prefers farm matching required RC version when capacity is equal', function (): void {
    seedFarmInventory('farm-stable', active: 10, max: 100, platformVersion: '1.0.0');
    seedFarmInventory('farm-rc', active: 10, max: 100, platformVersion: '1.0.0-rc.3');

    $result = app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3'),
    );

    expect($result->farmId)->toBe('farm-rc');
});

it('prefers lower latency when capacity and version tie', function (): void {
    seedFarmInventory('farm-slow', active: 10, max: 100, latencyMs: 120);
    seedFarmInventory('farm-fast', active: 10, max: 100, latencyMs: 25);

    $result = app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3'),
    );

    expect($result->farmId)->toBe('farm-fast');
});

it('selects farm with most available slots when multiple farms qualify', function (): void {
    seedFarmInventory('farm-medium', active: 40, max: 100);
    seedFarmInventory('farm-large', active: 5, max: 100);

    $result = app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3'),
    );

    expect($result->farmId)->toBe('farm-large')
        ->and($result->availableSlots)->toBe(95);
});

it('ignores stale inventory older than freshness window', function (): void {
    FarmInventory::create([
        'farm_id' => 'farm-stale',
        'active_tenants' => 1,
        'max_tenants' => 100,
        'available_slots' => 99,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 10,
        'reported_at' => now()->subHours(2),
    ]);

    app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3'),
    );
})->throws(NoFarmCapacityException::class);
