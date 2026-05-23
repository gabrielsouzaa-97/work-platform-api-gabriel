<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
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
 * Returns true when $sequence appears as consecutive elements in $args.
 *
 * Used to assert upstream argv tokens like ['user', 'create'] without coupling to
 * the exact position (slug always precedes the verb pair, idempotency/callback
 * flags follow).
 *
 * @param  array<int, string>  $args
 * @param  array<int, string>  $sequence
 */
function argsContainConsecutive(array $args, array $sequence): bool
{
    $args = array_values($args);
    $n = count($sequence);
    if ($n === 0) {
        return true;
    }
    $max = count($args) - $n;
    for ($i = 0; $i <= $max; $i++) {
        if (array_slice($args, $i, $n) === $sequence) {
            return true;
        }
    }

    return false;
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
        ->withArgs(function ($c, $cmd, $args, $stdin) {
            // Upstream argv tokens (NOT 'users:create')
            if (! argsContainConsecutive($args, ['user', 'create'])) {
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
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) {
            if (! argsContainConsecutive($args, ['user', 'create'])) {
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
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) {
            if (! argsContainConsecutive($args, ['user', 'create'])) {
                return false;
            }
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['password'] ?? null) === 'Secret123!'
                && ($decoded['display_name'] ?? null) === 'Ricardo Ramos'
                && ($decoded['email'] ?? null) === 'ricardo.ramos@me360.com.br'
                && ($decoded['quota'] ?? null) === '5GB'
                && ($decoded['groups'] ?? null) === ['admin']
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
            'groups' => ['admin'],
            'subadmin_groups' => ['financeiro'],
        ])
        ->assertStatus(202);
});

it('POST users aceita aliases displayname e subadmin do OpenAPI legado', function () {
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
                && ($decoded['display_name'] ?? null) === 'João Silva'
                && ($decoded['subadmin_groups'] ?? null) === ['admin'];
        })
        ->andReturn(sshLifecycleSuccess($jobId));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/users", [
            'username' => 'joao.silva',
            'password' => 'Secret123!',
            'displayname' => 'João Silva',
            'subadmin' => ['admin'],
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
        ->withArgs(fn ($c, $cmd, $args) => argsContainConsecutive($args, ['group', 'create'])
            && noUpstreamFlagDuplication($args, 'groups:create')
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

it('DELETE users/{username} → 202 + job_id + argv ["user","remove"]', function () {
    $cluster = makeLifecycleCluster();
    $customer = makeLifecycleCustomer($cluster);
    $operator = makeLifecycleOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => argsContainConsecutive($args, ['user', 'remove'])
            && noUpstreamFlagDuplication($args, 'users:delete')
            && ! argsContainConsecutive($args, ['user', 'delete'])
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
        ->withArgs(fn ($c, $cmd, $args) => argsContainConsecutive($args, ['apps', 'enable'])
            && noUpstreamFlagDuplication($args, 'apps:enable')
            && in_array('calendar,contacts,mail', $args, true)
        )
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
        ->withArgs(fn ($c, $cmd, $args) => argsContainConsecutive($args, ['apps', 'disable'])
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
        ->withArgs(fn ($c, $cmd, $args) => argsContainConsecutive($args, ['apps', 'disable'])
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
        ->withArgs(fn ($c, $cmd, $args) => argsContainConsecutive($args, ['group', 'remove'])
            && noUpstreamFlagDuplication($args, 'groups:delete')
            && ! argsContainConsecutive($args, ['group', 'delete'])
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
