<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Create;
use App\Http\Livewire\Customers\OccPanel;
use App\Http\Livewire\Customers\Show;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use Livewire\Livewire;

function m3ViewsCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function m3ViewsCustomer(ClusterServer $cluster, string $status = 'active'): Customer
{
    return Customer::create([
        'slug' => 'm3-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'm3-test.example.com',
        'status' => $status,
    ]);
}

function m3ViewsOperator(string $role = 'operador'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function assertBladeHasNoInlineStyleBlock(string $html): void
{
    expect($html)->not->toMatch('/<style\b/i');
}

function assertBladeUsesM3Tokens(string $html): void
{
    expect($html)->toContain('bg-surface-container');
    expect($html)->toContain('text-on-surface');
}

it('customers create renders without inline style block and uses M3 tokens (N39.6 scenario 1)', function (): void {
    $cluster = m3ViewsCluster();
    $operator = m3ViewsOperator('admin');

    $html = Livewire::actingAs($operator)
        ->test(Create::class)
        ->assertStatus(200)
        ->html();

    assertBladeHasNoInlineStyleBlock($html);
    assertBladeUsesM3Tokens($html);
    expect($html)->toContain('Provisionar Customer');
});

it('customers show renders without inline style block and active badge uses token classes (N39.6 scenario 2)', function (): void {
    $cluster = m3ViewsCluster();
    $customer = m3ViewsCustomer($cluster, 'active');
    $operator = m3ViewsOperator();

    $html = Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertStatus(200)
        ->html();

    assertBladeHasNoInlineStyleBlock($html);
    assertBladeUsesM3Tokens($html);
    expect($html)->toContain('active');
    expect($html)->toMatch('/text-\[#6ad191\].*active|active.*text-\[#6ad191\]/s');
    expect($html)->not->toMatch('/style\s*=\s*"/');
});

it('customers occ-panel renders without inline style block and uses M3 tokens (N39.6 scenario 1)', function (): void {
    $cluster = m3ViewsCluster();
    $customer = m3ViewsCustomer($cluster);
    $operator = m3ViewsOperator();

    $html = Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->assertStatus(200)
        ->html();

    assertBladeHasNoInlineStyleBlock($html);
    assertBladeUsesM3Tokens($html);
    expect($html)->toContain('Painel OCC');
});

it('customer views mount flows remain intact after M3 retrofit (N39.6 scenario 3)', function (): void {
    $cluster = m3ViewsCluster();
    $provisioningCustomer = m3ViewsCustomer($cluster, 'provisioning');
    $activeCustomer = m3ViewsCustomer($cluster, 'active');
    $operator = m3ViewsOperator('admin');

    Livewire::actingAs($operator)
        ->test(Create::class)
        ->assertSet('submitting', false)
        ->assertSee('Provisionar Customer');

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $provisioningCustomer->slug])
        ->assertSet('customer.slug', $provisioningCustomer->slug)
        ->assertSeeHtml('wire:poll.5s="refreshProgress"');

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $activeCustomer->slug])
        ->assertSet('customer.slug', $activeCustomer->slug)
        ->assertSet('tab', 'quota')
        ->assertSee('Definir Quota');
});
