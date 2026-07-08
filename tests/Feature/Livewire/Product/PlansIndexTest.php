<?php

declare(strict_types=1);

use App\Http\Livewire\Product\Plans\Index;
use App\Models\Operator;
use App\Models\Plan;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows plans sidebar link only for manage-operators', function (): void {
    $admin = Operator::factory()->admin()->create();
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Planos', false)
        ->assertSee(route('plans.index'), false);

    actingAs($operador)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('Planos', false);
});

it('admin can access plans index page', function (): void {
    $admin = Operator::factory()->admin()->create();

    actingAs($admin)
        ->get(route('plans.index'))
        ->assertOk();
});

it('operador cannot access plans index and gets 403', function (): void {
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($operador)
        ->get(route('plans.index'))
        ->assertForbidden();
});

it('admin can create plan via Livewire modal', function (): void {
    $admin = Operator::factory()->admin()->create();
    $slug = 'lw-plan-'.substr(uniqid(), -6);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openCreate')
        ->assertSet('showCreateModal', true)
        ->set('createSlug', $slug)
        ->set('createName', 'Livewire Plan')
        ->set('createDefaultQuota', '5 GB')
        ->set('createStatus', 'active')
        ->call('create')
        ->assertSet('showCreateModal', false);

    $this->assertDatabaseHas('plans', [
        'slug' => $slug,
        'name' => 'Livewire Plan',
        'default_quota' => '5 GB',
    ]);
});

it('admin can edit plan via Livewire', function (): void {
    $admin = Operator::factory()->admin()->create();

    Plan::create([
        'slug' => 'editable',
        'name' => 'Before',
        'default_quota' => '5 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openEdit', 'editable')
        ->set('editName', 'After')
        ->set('editDefaultQuota', '12 GB')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('plans', [
        'slug' => 'editable',
        'name' => 'After',
        'default_quota' => '12 GB',
    ]);
});

it('plans index lists existing plans', function (): void {
    $admin = Operator::factory()->admin()->create();

    Plan::create([
        'slug' => 'listed-plan',
        'name' => 'Listed Plan',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Listed Plan')
        ->assertSee('listed-plan');
});

it('setting is_default on create clears previous default in Livewire', function (): void {
    $admin = Operator::factory()->admin()->create();

    Plan::create([
        'slug' => 'old-default',
        'name' => 'Old Default',
        'default_quota' => '5 GB',
        'is_default' => true,
        'status' => 'active',
    ]);

    $slug = 'new-default-lw-'.substr(uniqid(), -6);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('createSlug', $slug)
        ->set('createName', 'New Default')
        ->set('createDefaultQuota', '5 GB')
        ->set('createIsDefault', true)
        ->call('create');

    expect(Plan::where('is_default', true)->count())->toBe(1);
    expect(Plan::find('old-default')?->is_default)->toBeFalse();
    expect(Plan::find($slug)?->is_default)->toBeTrue();
});
