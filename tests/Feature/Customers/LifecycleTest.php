<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Models\TenantGroup;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
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

/**
 * @param  list<string>  $names
 */
function seedLifecycleTenantGroups(Customer $customer, array $names): void
{
    foreach ($names as $name) {
        TenantGroup::create([
            'id' => Str::uuid()->toString(),
            'customer_slug' => $customer->slug,
            'name' => $name,
            'origin' => 'api',
        ]);
    }
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

/**
 * Returns true when $args starts with $sequence (slug + upstream verb tokens first).
 *
 * @param  array<int, string>  $args
 * @param  array<int, string>  $sequence
 */
function argsStartWithSequence(array $args, array $sequence): bool
{
    $args = array_values($args);
    $n = count($sequence);
    if ($n === 0) {
        return true;
    }
    if (count($args) < $n) {
        return false;
    }

    return array_slice($args, 0, $n) === array_values($sequence);
}

// ── 7.2 Create User ───────────────────────────────────────────────────────────

it('POST users → 202 + job_id + audit log criado + email/groups via stdin (não positional)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) use ($customer) {
            // Upstream argv: slug + verb tokens (NOT 'users:create')
            if (! argsStartWithSequence($args, [$customer->slug, 'user', 'create'])) {
                return false;
            }
            // Bug-A + Bug-B regression guards (helper centralised in tests/Pest.php — QA-F5-005)
            if (! noUpstreamFlagDuplication($args, 'users:create')) {
                return false;
            }
            // Username flows as positional; email is in stdin payload, NOT a positional
            if (! in_array('johndoe', $args, true) || in_array('john@acme.com', $args, true)) {
                return false;
            }
            // QA-F5-006: idempotency-key and callback flags must be present in argv
            $hasIdempotencyKey = (bool) array_filter($args, fn ($a) => is_string($a) && str_starts_with($a, '--idempotency-key='));
            $hasCallback = (bool) array_filter($args, fn ($a) => is_string($a) && str_contains($a, '/api/jobs/hook?cluster='));
            if (! $hasIdempotencyKey || ! $hasCallback) {
                return false;
            }
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['password'] ?? null) === 'Secret123!'
                && ($decoded['email'] ?? null) === 'john@acme.com';
        })
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

it('POST users com groups → stdin payload contém groups[]', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    seedLifecycleTenantGroups($customer, ['editors', 'admins']);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) use ($customer) {
            if (! argsStartWithSequence($args, [$customer->slug, 'user', 'create'])) {
                return false;
            }
            if (! noUpstreamFlagDuplication($args, 'users:create')) {
                return false;
            }
            // --group= flags MUST NOT be in argv (legacy bug)
            foreach ($args as $a) {
                if (is_string($a) && str_starts_with($a, '--group=')) {
                    return false;
                }
            }
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['groups'] ?? null) === ['editors', 'admins'];
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'groups' => ['editors', 'admins'],
        ])
        ->assertStatus(202);
});

it('POST users com display_name, quota e subadmin_groups → stdin payload upstream completo', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    seedLifecycleTenantGroups($customer, ['staff', 'financeiro']);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) use ($customer) {
            if (! argsStartWithSequence($args, [$customer->slug, 'user', 'create'])) {
                return false;
            }
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['password'] ?? null) === 'Secret123!'
                && ($decoded['display_name'] ?? null) === 'Ricardo Ramos'
                && ($decoded['email'] ?? null) === 'ricardo.ramos@me360.com.br'
                && ($decoded['quota'] ?? null) === '5GB'
                && ($decoded['groups'] ?? null) === ['staff']
                && ($decoded['subadmin_groups'] ?? null) === ['financeiro'];
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'ricardo.ramos2',
            'password' => 'Secret123!',
            'display_name' => 'Ricardo Ramos',
            'email' => 'ricardo.ramos@me360.com.br',
            'quota' => '5 GB',
            'groups' => ['staff'],
            'subadmin_groups' => ['financeiro'],
        ])
        ->assertStatus(202);
});

it('POST users com grupo inexistente na projeção → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'groups' => ['unknown-group'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['groups.0']);
});

it('POST users aceita aliases displayname e subadmin do OpenAPI legado', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    seedLifecycleTenantGroups($customer, ['staff']);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) {
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['display_name'] ?? null) === 'João Silva'
                && ($decoded['subadmin_groups'] ?? null) === ['staff'];
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'joao.silva',
            'password' => 'Secret123!',
            'displayname' => 'João Silva',
            'subadmin' => ['staff'],
        ])
        ->assertStatus(202);
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

    // Seed key without job_id — represents a persisted key before SSH returned (job_id is nullable).
    // Args hash now reflects the new contract: username is the ONLY positional, no email.
    $argsHash = hash('sha256', $customer->slug.'|users:create|'.json_encode(['johndoe']));

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

it('POST groups → 202 + job_id + argv ["group","create"]', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args) use ($customer) {
            // QA-F5-006: idempotency-key and callback must be in argv
            $hasIdempotencyKey = (bool) array_filter($args, fn ($a) => is_string($a) && str_starts_with($a, '--idempotency-key='));
            $hasCallback = (bool) array_filter($args, fn ($a) => is_string($a) && str_contains($a, '/api/jobs/hook?cluster='));

            return argsStartWithSequence($args, [$customer->slug, 'group', 'create'])
                && noUpstreamFlagDuplication($args, 'groups:create')
                && in_array('editors', $args, true)
                && $hasIdempotencyKey
                && $hasCallback;
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => 'editors']);

    $response->assertStatus(202);
    $response->assertJsonPath('job_id', $jobId);
});

// ── 7.3 Delete User ────────────────────────────────────────────────────────────

it('DELETE users/{username} → 202 + job_id + argv ["user","remove"]', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => argsStartWithSequence($args, [$customer->slug, 'user', 'remove'])
            && noUpstreamFlagDuplication($args, 'users:delete')
            && ! argsStartWithSequence($args, [$customer->slug, 'user', 'delete'])
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

// ── 7.2 Enable Apps bulk (CSV consolidado em 1 job) ───────────────────────────

it('POST apps/enable consolida lista em 1 job único com CSV positional', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once() // single call — not one-per-app
        ->withArgs(function ($c, $cmd, $args) use ($customer) {
            // QA-F5-006: idempotency-key and callback must be in argv
            $hasIdempotencyKey = (bool) array_filter($args, fn ($a) => is_string($a) && str_starts_with($a, '--idempotency-key='));
            $hasCallback = (bool) array_filter($args, fn ($a) => is_string($a) && str_contains($a, '/api/jobs/hook?cluster='));

            return argsStartWithSequence($args, [$customer->slug, 'apps', 'enable'])
                && noUpstreamFlagDuplication($args, 'apps:enable')
                && in_array('calendar,contacts,mail', $args, true)
                && $hasIdempotencyKey
                && $hasCallback;
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", [
            'apps' => ['calendar', 'contacts', 'mail'],
        ]);

    $response->assertStatus(202);
    $response->assertJsonPath('job_id', $jobId);
    $response->assertJsonPath('apps_csv', 'calendar,contacts,mail');
});

it('POST apps/disable consolida lista em 1 job único com CSV positional', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => argsStartWithSequence($args, [$customer->slug, 'apps', 'disable'])
            // Bug-A + Bug-B regression guards via centralised helper (QA-F5-005)
            && noUpstreamFlagDuplication($args, 'apps:disable')
            // Multi-app CSV is asserted in the dedicated test below; here we verify single-app CSV
            && in_array('contacts', $args, true)
        )
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/disable", [
            'apps' => ['contacts'],
        ])
        ->assertStatus(202)
        ->assertJsonPath('apps_csv', 'contacts');
});

it('POST apps/disable com 3 apps consolida no CSV "a,b,c" em 1 job único (QA-F5-007)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once() // single SSH call — NOT one-per-app
        ->withArgs(fn ($c, $cmd, $args) => argsStartWithSequence($args, [$customer->slug, 'apps', 'disable'])
            && noUpstreamFlagDuplication($args, 'apps:disable')
            && in_array('calendar,contacts,mail', $args, true)
        )
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/disable", [
            'apps' => ['calendar', 'contacts', 'mail'],
        ])
        ->assertStatus(202)
        ->assertJsonPath('apps_csv', 'calendar,contacts,mail');
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

it('SSH exit 4 (recurso já existe) → 409 already_exists + IdempotencyKey rolled back', function () {
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

    // QA-F5-017: SshRemoteException catch in LifecycleAsyncAction MUST delete the key
    // so the operator can retry after fixing the upstream conflict (within the 24h TTL).
    expect(IdempotencyKey::where('cmd', 'groups:create')->count())->toBe(0);
});

it('SSH exit 22 (senha fraca no upstream) → 422 + IdempotencyKey rolled back', function () {
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

    // QA-F5-017: same defensive contract for the exit-22 branch.
    expect(IdempotencyKey::where('cmd', 'users:create')->count())->toBe(0);
});

it('SshConnectionException em cluster ativo → 503 cluster_unreachable + nada persistido', function () {
    // QA-F5-018: the existing "cluster offline → 503" test uses status='unreachable',
    // which hits the preemptive guard BEFORE SSH is invoked. This test exercises the
    // catch block in LifecycleAsyncAction (SshConnectionException → ClusterUnreachableException)
    // — the path that actually fires when a runtime SSH connection drops in an active cluster.
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andThrow(new SshConnectionException('Connection refused'));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => 'editors'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'cluster_unreachable');

    expect(IdempotencyKey::where('cmd', 'groups:create')->count())->toBe(0)
        ->and(Job::where('cmd_canonical', 'groups:create')->count())->toBe(0)
        ->and(AuditLog::where('action', 'groups_create_initiated')->count())->toBe(0);
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

it('DELETE groups/{group} → 202 + job_id + argv ["group","remove"]', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => argsStartWithSequence($args, [$customer->slug, 'group', 'remove'])
            && noUpstreamFlagDuplication($args, 'groups:delete')
            && ! argsStartWithSequence($args, [$customer->slug, 'group', 'delete'])
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

// ── ISSUE-006: groups:add e groups:remove → 501 (blocked on upstream D3/D4) ──

it('POST groups/{group}/users → 501 not_implemented_yet (groups:add blocked on upstream)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync'); // tradutor falha antes de qualquer SSH
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups/editors/users", ['username' => 'johndoe'])
        ->assertStatus(501)
        ->assertJsonPath('error', 'not_implemented_yet')
        ->assertJsonPath('cmd', 'groups:add');

    // Blocked verbs MUST short-circuit BEFORE any DB write — defensive contract
    // verified by inspecting all three side-effect tables (QA-F5-004).
    expect(IdempotencyKey::where('cmd', 'groups:add')->count())->toBe(0)
        ->and(Job::where('cmd_canonical', 'groups:add')->count())->toBe(0)
        ->and(AuditLog::where('action', 'groups_add_initiated')->count())->toBe(0);
});

it('POST groups/{group}/users com group inválido → 422 sem SSH (validação precede tradutor)', function () {
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

it('DELETE groups/{group}/users/{username} → 501 not_implemented_yet (groups:remove blocked)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->deleteJson("/api/customers/{$customer->slug}/groups/editors/users/johndoe")
        ->assertStatus(501)
        ->assertJsonPath('error', 'not_implemented_yet')
        ->assertJsonPath('cmd', 'groups:remove');

    // Symmetric hygiene with the groups:add test — QA-F5-003 + QA-F5-004.
    expect(IdempotencyKey::where('cmd', 'groups:remove')->count())->toBe(0)
        ->and(Job::where('cmd_canonical', 'groups:remove')->count())->toBe(0)
        ->and(AuditLog::where('action', 'groups_remove_initiated')->count())->toBe(0);
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

it('SSH timeout em lifecycle → 504 lifecycle_timeout + nada persistido', function () {
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

    // QA-F5-017: timeout branch also deletes the key and must not leak Job/AuditLog rows
    // (the persist DB::transaction runs only AFTER the SSH future returns successfully).
    expect(IdempotencyKey::where('cmd', 'groups:create')->count())->toBe(0)
        ->and(Job::where('cmd_canonical', 'groups:create')->count())->toBe(0)
        ->and(AuditLog::where('action', 'groups_create_initiated')->count())->toBe(0);
});

// ── QA-F11-003/004: mapLifecycleException coverage for dispatchAppsCsv ─────────

it('apps/enable: cluster offline → 503 cluster_unreachable (mapLifecycleException via dispatchAppsCsv)', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable']);
    $customer = Customer::create([
        'slug' => 'lc-apps-off-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'lc-apps-offline.example.com',
        'status' => 'active',
    ]);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", ['apps' => ['calendar']])
        ->assertStatus(503)
        ->assertJsonPath('error', 'cluster_unreachable');
});

it('apps/enable: SSH timeout → 504 lifecycle_timeout (mapLifecycleException via dispatchAppsCsv)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andThrow(new SshTimeoutException('Timeout'));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", ['apps' => ['calendar']])
        ->assertStatus(504)
        ->assertJsonPath('error', 'lifecycle_timeout');
});

it('apps/disable: SSH error → 502 com apps_csv no payload (SshRemoteException via dispatchAppsCsv, QA-F11-004)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andThrow(new SshRemoteException('Remote error', 99));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/disable", ['apps' => ['calendar', 'mail']])
        ->assertStatus(502)
        ->assertJsonPath('error', 'upstream_error')
        ->assertJsonPath('apps_csv', 'calendar,mail');
});

// ── QA-F5-008: CSV apps order policy ──────────────────────────────────────────

it('apps/enable: ordem dos apps é preservada (policy A — order-sensitive CSV, QA-F5-008)', function () {
    // Policy A: implode preserves input order. Two requests with the same apps
    // in different order produce DIFFERENT CSV strings (= different idempotency hashes).
    // This is intentional — callers are responsible for canonicalizing if they need dedup.
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId1 = Str::uuid()->toString();
    $jobId2 = Str::uuid()->toString();

    $capturedArgs = [];
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->twice()
        ->withArgs(function ($c, $cmd, $args) use (&$capturedArgs) {
            $capturedArgs[] = $args;

            return true;
        })
        ->andReturn(sshLifecycleSuccess($jobId1), sshLifecycleSuccess($jobId2));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", ['apps' => ['calendar', 'mail']])
        ->assertStatus(202);

    // Second request with same apps in reversed order — must NOT be treated as duplicate
    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", ['apps' => ['mail', 'calendar']])
        ->assertStatus(202);

    // The two argv sets must carry DIFFERENT CSV strings
    $csvFromFirst = collect($capturedArgs[0])->first(fn ($a) => str_contains($a, 'calendar') && str_contains($a, 'mail'));
    $csvFromSecond = collect($capturedArgs[1])->first(fn ($a) => str_contains($a, 'calendar') && str_contains($a, 'mail'));
    expect($csvFromFirst)->toBe('calendar,mail')
        ->and($csvFromSecond)->toBe('mail,calendar')
        ->and($csvFromFirst)->not->toBe($csvFromSecond);
});

// ── QA-F5-009: boundary value tests ───────────────────────────────────────────

it('POST users aceita username com exatamente 64 caracteres (boundary válido)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();
    $username = str_repeat('a', 64);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => $username,
            'password' => 'Secret123!',
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('POST users rejeita username com 65 caracteres (off-by-one)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => str_repeat('a', 65),
            'password' => 'Secret123!',
        ])
        ->assertStatus(422);
});

it('POST groups aceita name com exatamente 256 caracteres (boundary válido)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();
    $groupName = str_repeat('a', 256);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => $groupName])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('POST groups rejeita name com 257 caracteres (off-by-one)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/groups", ['name' => str_repeat('a', 257)])
        ->assertStatus(422);
});

it('POST apps/enable com apps vazio → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", ['apps' => []])
        ->assertStatus(422);
});

it('POST users aceita email com plus addressing (boundary válido)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) {
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['email'] ?? null) === 'user+tag@example.com';
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'Secret123!',
            'email' => 'user+tag@example.com',
        ])
        ->assertStatus(202);
});

it('POST users aceita password com exatamente 10 caracteres (boundary válido)', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'johndoe',
            'password' => 'secret1234',
        ])
        ->assertStatus(202)
        ->assertJsonPath('job_id', $jobId);
});

it('POST apps/enable rejeita App ID apenas uppercase → 422 sem SSH', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/apps/enable", ['apps' => ['CALENDAR']])
        ->assertStatus(422);
});
