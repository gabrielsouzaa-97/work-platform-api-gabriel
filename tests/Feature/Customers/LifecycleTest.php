<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function makeLifecycleCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeLifecycleCustomer(ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => 'lc-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'lc-test.example.com',
        'status' => 'active',
    ]);
}

function makeLifecycleOperator(string $role = 'operador'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function sshLifecycleSuccess(string $jobId): SshResponse
{
    return new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    );
}

// ── 7.2 Create User ───────────────────────────────────────────────────────────

it('POST users → 202 + job_id + audit log criado', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args, $stdin) => $cmd === 'nextcloud-manage'
            && in_array('users:create', $args, true)
            && str_contains($stdin ?? '', 'password')
        )
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'email' => 'john@acme.com',
        ]);

    $response->assertStatus(202);
    $response->assertJsonPath('job_id', $jobId);
    expect(Job::find($jobId))->not->toBeNull();
    expect(AuditLog::where('action', 'users_create_initiated')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('POST users com username inválido → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'john doe',
            'password' => 'Secret123!',
        ])
        ->assertStatus(422);
});

it('POST users senha fraca → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => '123',
        ])
        ->assertStatus(422);
});

// ── 7.2 Idempotency ────────────────────────────────────────────────────────────

it('POST users duplicado dentro de 24h → 409 idempotency_conflict', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    // Seed key without job_id — represents a persisted key before SSH returned (job_id is nullable)
    $argsHash = hash('sha256', $customer->slug.'|users:create|'.json_encode(['johndoe', 'john@acme.com']));

    IdempotencyKey::create([
        'key' => Str::uuid()->toString(),
        'cmd' => 'users:create',
        'args_hash' => $argsHash,
        'customer_slug' => $customer->slug,
        'expires_at' => now()->addHours(23),
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'email' => 'john@acme.com',
        ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_conflict');
});

// ── 7.2 Create Group ───────────────────────────────────────────────────────────

it('POST groups → 202 + job_id', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('groups:create', $args, true)
            && in_array('editors', $args, true)
        )
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => 'editors']);

    $response->assertStatus(202);
    $response->assertJsonPath('job_id', $jobId);
});

// ── 7.3 Delete User ────────────────────────────────────────────────────────────

it('DELETE users/{username} → 202 + job_id', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('users:delete', $args, true)
            && in_array('johndoe', $args, true)
        )
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->deleteJson("/api/customers/{$customer->slug}/users/johndoe");

    $response->assertStatus(202);
    $response->assertJsonPath('job_id', $jobId);
});

it('DELETE users/{username} inválido → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->deleteJson("/api/customers/{$customer->slug}/users/".str_repeat('a', 65))
        ->assertStatus(422);
});

// ── 7.2 Enable Apps bulk ───────────────────────────────────────────────────────

it('POST apps/enable com lista de apps → 202 + job_ids array', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->twice()
        ->andReturnUsing(fn () => sshLifecycleSuccess(Str::uuid()->toString()));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", [
            'apps' => ['calendar', 'contacts'],
        ]);

    $response->assertStatus(202);
    $response->assertJsonStructure(['job_ids']);
    expect($response->json('job_ids'))->toHaveCount(2);
});

it('POST apps/enable com App ID inválido → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", [
            'apps' => ['Bad App!'],
        ])
        ->assertStatus(422);
});

// ── 7.2 Error mapping ──────────────────────────────────────────────────────────

it('cluster offline → 503', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable']);
    $customer = Customer::create([
        'slug' => 'lc-off-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'lc-offline.example.com',
        'status' => 'active',
    ]);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
        ])
        ->assertStatus(503)
        ->assertJsonPath('error', 'cluster_unreachable');
});

it('SSH exit 4 (recurso já existe) → 409 already_exists', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andThrow(new SshRemoteException('Already exists', 4));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => 'editors'])
        ->assertStatus(409)
        ->assertJsonPath('error', 'already_exists');
});

it('SSH exit 22 (senha fraca no upstream) → 422', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andThrow(new SshRemoteException('Weak password', 22));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'validation_failed');
});

it('suporte não pode criar usuários → 403', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $suporte = Operator::factory()->create(['role' => 'suporte', 'status' => 'active']);

    $this->actingAs($suporte)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
        ])
        ->assertStatus(403);
});

it('sem autenticação → 401', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);

    $this->postJson("/api/customers/{$customer->slug}/users", [
        'username' => 'johndoe',
        'password' => 'Secret123!',
    ])->assertStatus(401);
});

// ── D7-F006: deleteGroup ────────────────────────────────────────────────────────

it('DELETE groups/{group} → 202 + job_id', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('groups:delete', $args, true)
            && in_array('editors', $args, true))
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->deleteJson("/api/customers/{$customer->slug}/groups/editors")
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('DELETE groups/{group} com nome inválido → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->deleteJson('/api/customers/'.$customer->slug.'/groups/'.str_repeat('x', 257))
        ->assertStatus(422);
});

// ── D7-F006: addUserToGroup ────────────────────────────────────────────────────

it('POST groups/{group}/users → 202 + job_id', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('groups:add', $args, true)
            && in_array('editors', $args, true)
            && in_array('johndoe', $args, true))
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups/editors/users", ['username' => 'johndoe'])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('POST groups/{group}/users com group inválido → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson('/api/customers/'.$customer->slug.'/groups/'.str_repeat('x', 257).'/users', ['username' => 'johndoe'])
        ->assertStatus(422);
});

// ── D7-F006: removeUserFromGroup ───────────────────────────────────────────────

it('DELETE groups/{group}/users/{username} → 202 + job_id', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('groups:remove', $args, true)
            && in_array('editors', $args, true)
            && in_array('johndoe', $args, true))
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->deleteJson("/api/customers/{$customer->slug}/groups/editors/users/johndoe")
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('DELETE groups/{group}/users/{username} com group inválido → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->deleteJson('/api/customers/'.$customer->slug.'/groups/'.str_repeat('x', 257).'/users/johndoe')
        ->assertStatus(422);
});

// ── D7-F006: lifecycle_timeout (D7-F003 coverage) ─────────────────────────────

it('SSH timeout em lifecycle → 504 lifecycle_timeout', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshTimeoutException('Timeout'));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => 'editors'])
        ->assertStatus(504)
        ->assertJsonPath('error', 'lifecycle_timeout');
});
