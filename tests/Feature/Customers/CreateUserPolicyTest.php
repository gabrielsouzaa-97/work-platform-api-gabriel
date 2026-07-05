<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function policyCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function policyCustomer(ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => 'policy-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'policy.example.com',
        'status' => 'active',
    ]);
}

function policyOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

it('POST users com username admin → 422 sem SSH', function (): void {
    $cluster = policyCluster();
    $customer = policyCustomer($cluster);
    $operator = policyOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'admin',
            'password' => 'Secret123!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username']);
});

it('POST users com groups contendo admin → 422 sem SSH', function (): void {
    $cluster = policyCluster();
    $customer = policyCustomer($cluster);
    $operator = policyOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'groups' => ['editors', 'admin'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['groups.1']);
});

it('POST users com subadmin_groups contendo admin → 422 sem SSH', function (): void {
    $cluster = policyCluster();
    $customer = policyCustomer($cluster);
    $operator = policyOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'subadmin_groups' => ['admin'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subadmin_groups.0']);
});

it('POST users válido sem admin passa validação e enfileira job', function (): void {
    $cluster = policyCluster();
    $customer = policyCustomer($cluster);
    $operator = policyOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'validuser',
            'password' => 'Secret123!',
            'email' => 'valid@example.com',
            'groups' => ['editors'],
            'subadmin_groups' => ['financeiro'],
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});
