<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\FarmAgent;
use App\Models\FarmInventory;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function seedAutoPlaceFarm(string $farmId = 'farm-auto'): string
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    FarmAgent::factory()->create([
        'farm_id' => $farmId,
        'cluster_server_id' => $cluster->id,
    ]);
    FarmInventory::create([
        'farm_id' => $farmId,
        'active_tenants' => 5,
        'max_tenants' => 100,
        'available_slots' => 95,
        'platform_version' => '1.0.0-rc.3',
        'latency_ms' => 20,
        'reported_at' => now(),
    ]);

    return $cluster->id;
}

it('auto_place uses PlacementService to resolve cluster_server_id', function (): void {
    $expectedClusterId = seedAutoPlaceFarm();
    seedAutoPlaceFarm('farm-other');

    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'auto-placed',
        'auto_place' => true,
        'domain' => 'auto-placed.example.com',
    ]);

    $response->assertStatus(201);

    $customer = Customer::find('auto-placed');
    expect($customer)->not->toBeNull()
        ->and($customer->cluster_server_id)->toBe($expectedClusterId)
        ->and(Job::find($jobId))->not->toBeNull();
});

it('requires cluster_server_id when auto_place is false', function (): void {
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $response = test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'missing-cluster',
        'domain' => 'missing-cluster.example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['cluster_server_id']);
});

it('allows explicit cluster_server_id without auto_place', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $sshMock);

    test()->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'explicit-cluster',
        'cluster_server_id' => $cluster->id,
        'domain' => 'explicit-cluster.example.com',
    ])->assertStatus(201);

    expect(Customer::find('explicit-cluster')->cluster_server_id)->toBe($cluster->id);
});
