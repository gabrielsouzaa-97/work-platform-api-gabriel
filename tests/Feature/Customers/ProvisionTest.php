<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function makeProvisionCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeOperator(string $role = 'operador'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function sshProvisionSuccess(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

it('slug válido + anexos pequenos → SSH chamado → customer + job criados', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(sshProvisionSuccess($jobId));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'acme-provision',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme-provision.example.com',
    ]);

    $response->assertStatus(201);
    expect(Customer::find('acme-provision'))->not->toBeNull();
    expect(Job::find($jobId))->not->toBeNull();
    expect(AuditLog::where('action', 'provision_initiated')->where('resource_id', 'acme-provision')->exists())->toBeTrue();
});

it('slug com underscore → 422 ANTES de SSH', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'acme_prod',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(422);
});

it('slug com uppercase → 422 ANTES de SSH', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'Acme-Prod',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(422);
});

it('slug já existente local → 409 inline antes de SSH', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();

    Customer::create([
        'slug' => 'existing-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'existing-co.example.com',
        'status' => 'active',
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'existing-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'existing-co.example.com',
    ]);

    $response->assertStatus(422);
});

it('SSH retorna exit 3 (idempotency_conflict) → 409', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();
    $existingJobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andThrow(
        new SshRemoteException('Idempotency conflict', 3, idempotencyConflict: true, parsedJson: ['existing_job_id' => $existingJobId])
    );
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'acme-conflict',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'idempotency_conflict');
    $response->assertJsonPath('existing_job_id', $existingJobId);
});

it('SSH retorna exit 4 (state_conflict) → 409', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andThrow(
        new SshRemoteException('State conflict', 4, stateConflict: true, parsedJson: ['diff' => ['status' => 'running']])
    );
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'acme-state',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'state_conflict');
});

it('cluster offline → 503 + Retry-After; sem Customer/Job locais', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable']);
    $operator = makeOperator();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'acme-offline',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(503);
    expect(Customer::find('acme-offline'))->toBeNull();
});

it('suporte não pode provisionar → 422 (authorization)', function () {
    $cluster = makeProvisionCluster();
    $suporte = Operator::factory()->create(['role' => 'suporte', 'status' => 'active']);

    $response = $this->actingAs($suporte)->postJson('/api/customers', [
        'slug' => 'acme-suporte',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(403);
});

it('sem autenticação → 401', function () {
    $response = $this->postJson('/api/customers', [
        'slug' => 'acme-noauth',
        'cluster_server_id' => Str::uuid()->toString(),
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(401);
});
