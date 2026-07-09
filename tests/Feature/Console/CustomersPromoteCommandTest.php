<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;

function makePromoteCustomer(string $status = 'provisioning_finishing'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => 'promote-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'promote.example.com',
        'status' => $status,
    ]);
}

it('customers:promote promotes provisioning_finishing to active with audit log', function (): void {
    $customer = makePromoteCustomer('provisioning_finishing');

    $this->artisan('customers:promote', ['slug' => $customer->slug])->assertSuccessful();

    expect($customer->fresh()->status)->toBe('active')
        ->and(AuditLog::where('action', 'customer_promoted_manual')
            ->where('resource_id', $customer->slug)
            ->exists())->toBeTrue();
});

it('customers:promote rejects customers not in provisioning_finishing', function (string $status): void {
    $customer = makePromoteCustomer($status);

    $this->artisan('customers:promote', ['slug' => $customer->slug])->assertFailed();

    expect($customer->fresh()->status)->toBe($status);
})->with(['active', 'provisioning', 'failed', 'removed']);

it('customers:promote records customer_promoted_manual not billing tenant resume', function (): void {
    $customer = makePromoteCustomer('provisioning_finishing');

    $this->artisan('customers:promote', ['slug' => $customer->slug])->assertSuccessful();

    expect(AuditLog::where('resource_id', $customer->slug)
        ->where('action', 'customer_promoted_manual')
        ->exists())->toBeTrue()
        ->and(AuditLog::where('resource_id', $customer->slug)
            ->whereIn('action', ['tenant_resume', 'tenant_resumed', 'billing_resume'])
            ->exists())->toBeFalse();
});
