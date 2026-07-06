<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Create;
use App\Models\AppCatalogEntry;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function pickerCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function pickerOperator(): Operator
{
    return Operator::factory()->admin()->create();
}

function pickerCatalogEntry(string $appId, string $label): string
{
    return AppCatalogEntry::create([
        'app_id' => $appId,
        'label' => $label,
        'is_active' => true,
    ])->id;
}

function pickerPlanWithApps(string $slug, array $appsById): Plan
{
    Plan::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'default_quota' => '5 GB',
        'is_default' => false,
        'status' => 'active',
    ]);

    foreach ($appsById as $appId => $label) {
        DB::table('plan_apps')->insert([
            'plan_slug' => $slug,
            'app_catalog_id' => pickerCatalogEntry($appId, $label),
        ]);
    }

    return Plan::findOrFail($slug);
}

it('customers create exposes selectedAppIds for plan-filtered app picker', function (): void {
    $operator = pickerOperator();

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->assertSet('planSlug', null)
        ->assertSet('selectedAppIds', []);
});

it('app picker lists only apps linked to the selected plan', function (): void {
    $operator = pickerOperator();
    pickerPlanWithApps('starter', [
        'mail' => 'Mail App',
        'dashboard' => 'Dashboard App',
    ]);
    pickerPlanWithApps('pro', [
        'deck' => 'Deck App',
    ]);

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->set('planSlug', 'starter')
        ->assertSee('Mail App')
        ->assertSee('Dashboard App')
        ->assertDontSee('Deck App');
});

it('changing planSlug refreshes picker options to the new plan apps', function (): void {
    $operator = pickerOperator();
    pickerPlanWithApps('starter', ['mail' => 'Mail App']);
    pickerPlanWithApps('pro', ['deck' => 'Deck App']);

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->set('planSlug', 'starter')
        ->assertSee('Mail App')
        ->assertDontSee('Deck App')
        ->set('planSlug', 'pro')
        ->assertSee('Deck App')
        ->assertDontSee('Mail App');
});

it('app picker hides catalog apps that are not in the selected plan', function (): void {
    $operator = pickerOperator();
    pickerCatalogEntry('spreed', 'Spreed App');
    pickerPlanWithApps('starter', ['mail' => 'Mail App']);

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->set('planSlug', 'starter')
        ->assertSee('Mail App')
        ->assertDontSee('Spreed App');
});

it('clearing planSlug removes plan-filtered app options from the picker', function (): void {
    $operator = pickerOperator();
    pickerPlanWithApps('starter', ['mail' => 'Mail App']);

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->set('planSlug', 'starter')
        ->assertSee('Mail App')
        ->set('planSlug', null)
        ->assertDontSee('Mail App');
});
