<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\TenantGroup;
use App\Modules\Customers\Support\TenantKnownGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeKnownGroupsCustomer(): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => 'kg-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'kg.example.com',
        'status' => 'active',
    ]);
}

function seedKnownGroupProjection(Customer $customer, string $name): void
{
    TenantGroup::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'name' => $name,
        'origin' => 'api',
    ]);
}

it('forCustomer returns distinct groups sorted alphabetically', function (): void {
    $customer = makeKnownGroupsCustomer();

    seedKnownGroupProjection($customer, 'users');
    seedKnownGroupProjection($customer, 'editors');
    seedKnownGroupProjection($customer, 'financeiro');

    expect(TenantKnownGroups::forCustomer($customer->slug))
        ->toBe(['editors', 'financeiro', 'users']);
});

it('forCustomer excludes admin group names', function (): void {
    $customer = makeKnownGroupsCustomer();

    seedKnownGroupProjection($customer, 'admin');
    seedKnownGroupProjection($customer, 'staff');

    expect(TenantKnownGroups::forCustomer($customer->slug))
        ->toBe(['staff']);
});

it('forCustomer scopes results to the requested customer slug', function (): void {
    $customerA = makeKnownGroupsCustomer();
    $customerB = makeKnownGroupsCustomer();

    seedKnownGroupProjection($customerA, 'team-a');
    seedKnownGroupProjection($customerB, 'team-b');

    expect(TenantKnownGroups::forCustomer($customerA->slug))->toBe(['team-a'])
        ->and(TenantKnownGroups::forCustomer($customerB->slug))->toBe(['team-b']);
});

it('forCustomer returns empty list when no tenant groups exist', function (): void {
    $customer = makeKnownGroupsCustomer();

    expect(TenantKnownGroups::forCustomer($customer->slug))->toBe([]);
});
