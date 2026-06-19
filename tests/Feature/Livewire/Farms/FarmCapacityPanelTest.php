<?php

declare(strict_types=1);

use App\Http\Livewire\Farms\Index as FarmCapacityIndex;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Models\Operator;
use Livewire\Livewire;

function seedFarmCapacityPanelRow(
    string $farmId,
    int $active,
    int $max,
): FarmInventory {
    FarmAgent::factory()->create(['farm_id' => $farmId]);

    return FarmInventory::create([
        'farm_id' => $farmId,
        'active_tenants' => $active,
        'max_tenants' => $max,
        'available_slots' => $max - $active,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 35,
        'reported_at' => now(),
    ]);
}

it('admin sees farms with capacity metrics on inventory panel', function (): void {
    $admin = Operator::factory()->admin()->create();
    seedFarmCapacityPanelRow('farm-saas-prod-01', active: 20, max: 100);
    seedFarmCapacityPanelRow('farm-saas-prod-02', active: 80, max: 100);

    Livewire::actingAs($admin)
        ->test(FarmCapacityIndex::class)
        ->assertSee('farm-saas-prod-01')
        ->assertSee('farm-saas-prod-02')
        ->assertSee('20 / 100')
        ->assertSee('80 / 100')
        ->assertSee('35 ms');
});

it('denies farm capacity panel to operador without admin role', function (): void {
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    seedFarmCapacityPanelRow('farm-hidden', active: 1, max: 50);

    Livewire::actingAs($operator)
        ->test(FarmCapacityIndex::class)
        ->assertStatus(403);
});
