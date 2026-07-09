<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;

it('POST groups:create on provisioning_finishing returns 503 tenant_not_ready without SSH', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'mtx-grp-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'mtx-grp.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)->postJson("/api/customers/{$customer->slug}/groups", [
        'name' => 'marketing',
    ]);

    $response->assertStatus(503)
        ->assertHeader('Retry-After', '60')
        ->assertJson([
            'error' => 'tenant_not_ready',
            'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        ]);

    expect(Job::count())->toBe(0)
        ->and(IdempotencyKey::count())->toBe(0);
});

it('POST groups:create on provisioning returns 503 tenant_not_ready without SSH', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'mtx-prov-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'mtx-prov.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING,
        'image_mode' => false,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)->postJson("/api/customers/{$customer->slug}/groups", [
        'name' => 'marketing',
    ])->assertStatus(503)
        ->assertJson([
            'error' => 'tenant_not_ready',
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
});
