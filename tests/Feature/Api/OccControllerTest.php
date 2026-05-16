<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;

function makeOccCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeOccCustomer(ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => 'occ-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'occ-test.example.com',
        'status' => 'active',
    ]);
}

function makeOccOperator(string $role = 'operador'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function sshOccSuccess(array $data = []): SshResponse
{
    return new SshResponse(
        stdout: json_encode($data ?: ['result' => 'ok']),
        stderr: '',
        exitCode: 0,
        parsedJson: $data ?: ['result' => 'ok'],
    );
}

// ── 7.1 Quota ─────────────────────────────────────────────────────────────────

it('PUT quota/{username} com quota válida → 200 + audit log', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('user:setting', $args, true)
            && in_array('5 GB', $args, true))
        ->andReturn(sshOccSuccess());
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/quota/johndoe", ['quota' => '5 GB']);

    $response->assertOk();
    expect(AuditLog::where('action', 'occ_set_quota')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('PUT quota/{username} com formato inválido → 422 sem SSH', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/quota/johndoe", ['quota' => 'muita coisa']);

    $response->assertStatus(422);
});

it('GET quota/options → retorna lista estática sem SSH', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->getJson("/api/customers/{$customer->slug}/occ/quota/options");

    $response->assertOk();
    $response->assertJsonStructure(['options']);
    expect($response->json('options'))->toContain('1 GB');
});

// ── 7.1 Quota Audit ───────────────────────────────────────────────────────────

it('GET quota/audit → SSH chama files:scan --all sem --show-quota → 200', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('files:scan', $args, true)
            && in_array('--all', $args, true)
            && ! in_array('--show-quota', $args, true))
        ->andReturn(sshOccSuccess(['files' => 42]));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->getJson("/api/customers/{$customer->slug}/occ/quota/audit")
        ->assertOk()
        ->assertJsonPath('files', 42);
});

// ── 7.1 Maintenance ───────────────────────────────────────────────────────────

it('POST maintenance on=true → SSH chama --on → 200', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('--on', $args, true))
        ->andReturn(sshOccSuccess());
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/occ/maintenance", ['on' => true]);

    $response->assertOk();
    expect(AuditLog::where('action', 'occ_maintenance_toggle')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('POST maintenance sem campo on → 422', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/occ/maintenance", [])
        ->assertStatus(422);
});

// ── 7.1 App enable (sync) ─────────────────────────────────────────────────────

it('POST occ/apps/{appId}/enable → SSH chama app:enable → 200', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('app:enable', $args, true) && in_array('calendar', $args, true))
        ->andReturn(sshOccSuccess());
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/occ/apps/calendar/enable");

    $response->assertOk();
});

it('POST occ/apps com App ID inválido → 422 sem SSH', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/occ/apps/Bad%20App/enable")
        ->assertStatus(422);
});

// ── 7.1 Error mapping ──────────────────────────────────────────────────────────

it('SSH timeout → 504', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshTimeoutException('Timeout'));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/occ/maintenance", ['on' => false])
        ->assertStatus(504)
        ->assertJsonPath('error', 'occ_timeout');
});

it('cluster offline → 503 sem SSH', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'unreachable']);
    $customer = Customer::create([
        'slug' => 'occ-off-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'occ-offline.example.com',
        'status' => 'active',
    ]);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->postJson("/api/customers/{$customer->slug}/occ/maintenance", ['on' => false])
        ->assertStatus(503)
        ->assertJsonPath('error', 'cluster_unreachable');
});

it('SSH exit 1 → 404', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshRemoteException('User not found', 1));
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/quota/ghost", ['quota' => '1 GB'])
        ->assertStatus(404)
        ->assertJsonPath('error', 'not_found');
});

it('suporte não autorizado nos endpoints OCC → 403', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $suporte = Operator::factory()->create(['role' => 'suporte', 'status' => 'active']);

    $this->actingAs($suporte)
        ->postJson("/api/customers/{$customer->slug}/occ/maintenance", ['on' => false])
        ->assertStatus(403);
});

it('sem autenticação → 401', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);

    $this->postJson("/api/customers/{$customer->slug}/occ/maintenance", ['on' => false])
        ->assertStatus(401);
});

// ── D7-F006: setBranding ───────────────────────────────────────────────────────

it('PUT branding com name e color → SSH chama theming:config → 200 + audit log', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator('admin');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('theming:config', $args, true)
            && in_array('name', $args, true)
            && in_array('Acme Corp', $args, true))
        ->andReturn(sshOccSuccess());
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/branding", ['name' => 'Acme Corp', 'color' => '#123456']);

    $response->assertOk();
    expect(AuditLog::where('action', 'occ_set_branding')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('PUT branding sem campos → SSH chama theming:config com args vazios → 200', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator('admin');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => in_array('theming:config', $args, true))
        ->andReturn(sshOccSuccess());
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/branding", [])
        ->assertOk();
});

// ── D7-F006: setQuotaAll ───────────────────────────────────────────────────────

it('PUT quota/all com quota válida → SSH chama user:setting --all → 200 + audit log', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator('admin');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('user:setting', $args, true)
            && in_array('--all', $args, true)
            && in_array('10 GB', $args, true))
        ->andReturn(sshOccSuccess());
    $this->app->instance(SshClientInterface::class, $ssh);

    $response = $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/quota/all", ['quota' => '10 GB']);

    $response->assertOk();
    expect(AuditLog::where('action', 'occ_set_quota_all')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('PUT quota/all com formato inválido → 422 sem SSH', function () {
    $cluster = makeOccCluster();
    $customer = makeOccCustomer($cluster);
    $operator = makeOccOperator('admin');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)
        ->putJson("/api/customers/{$customer->slug}/occ/quota/all", ['quota' => 'muito'])
        ->assertStatus(422);
});
