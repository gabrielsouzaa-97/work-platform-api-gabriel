<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Modules\Farms\Dto\PlacementCriteria;
use App\Modules\Farms\Exceptions\NoFarmCapacityException;
use App\Modules\Farms\Services\PlacementService;

/**
 * @return array{inventory: FarmInventory, cluster_server_id: string}
 */
function seedTierFarm(
    string $farmId,
    string $tier,
    int $active = 10,
    int $max = 100,
): array {
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'tier' => $tier]);
    FarmAgent::factory()->create([
        'farm_id' => $farmId,
        'cluster_server_id' => $cluster->id,
    ]);

    $inventory = FarmInventory::create([
        'farm_id' => $farmId,
        'active_tenants' => $active,
        'max_tenants' => $max,
        'available_slots' => $max - $active,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 50,
        'reported_at' => now(),
    ]);

    return ['inventory' => $inventory, 'cluster_server_id' => $cluster->id];
}

it('dedicated tier only picks dedicated cluster servers', function (): void {
    $shared = seedTierFarm('farm-shared', tier: 'shared');
    $dedicated = seedTierFarm('farm-dedicated', tier: 'dedicated');

    $result = app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3', tier: 'dedicated'),
    );

    expect($result->farmId)->toBe('farm-dedicated')
        ->and($result->clusterServerId)->toBe($dedicated['cluster_server_id'])
        ->not->toBe($shared['cluster_server_id']);
});

it('shared tier excludes dedicated cluster servers', function (): void {
    seedTierFarm('farm-dedicated-only', tier: 'dedicated');

    app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3', tier: 'shared'),
    );
})->throws(NoFarmCapacityException::class);

it('shared tier picks shared cluster servers when both exist', function (): void {
    seedTierFarm('farm-dedicated', tier: 'dedicated');
    $shared = seedTierFarm('farm-shared-open', tier: 'shared', active: 5, max: 100);

    $result = app(PlacementService::class)->select(
        new PlacementCriteria(requiredPlatformVersion: '1.0.0-rc.3', tier: 'shared'),
    );

    expect($result->farmId)->toBe('farm-shared-open')
        ->and($result->clusterServerId)->toBe($shared['cluster_server_id']);
});
