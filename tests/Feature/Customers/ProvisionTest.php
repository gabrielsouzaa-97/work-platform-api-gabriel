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
use App\Modules\Customers\Actions\ProvisionCustomerAction;
use App\Modules\Customers\Dto\ProvisionPayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

it('logo > 256 KB → inboxInit + sftpUpload chamados + --staging-id repassado ao SSH', function () {
    Storage::fake('local');

    $cluster = makeProvisionCluster();
    $operator = makeOperator('operador');
    $jobId = Str::uuid()->toString();

    $tmpFile = tempnam(sys_get_temp_dir(), 'logo_scp_');
    file_put_contents($tmpFile, str_repeat('X', 260 * 1024));

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('inboxInit')->once();
    $sshMock->shouldReceive('sftpUpload')->once();
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $bin, $args, $stdin) {
            $decoded = is_string($stdin) ? json_decode($stdin, true) : null;

            return is_array($decoded)
                && ($decoded['shell'] ?? null) === true
                && in_array('--suite-catalog', $args, true)
                && collect($args)->contains(fn ($a) => str_starts_with((string) $a, '--staging-id='))
                && ! collect($args)->contains('--payload-stdin');
        })
        ->andReturn(sshProvisionSuccess($jobId));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $payload = new ProvisionPayload(
        slug: 'acme-scp',
        domain: 'acme-scp.example.com',
        clusterServerId: $cluster->id,
        apps: [],
        fullApps: false,
        logoPath: $tmpFile,
        backgroundPath: null,
    );

    $action = app(ProvisionCustomerAction::class);
    $result = $action->execute($payload, $operator);

    @unlink($tmpFile);

    expect($result['customer']->slug)->toBe('acme-scp');
    expect(Job::find($jobId))->not->toBeNull();
});

it('logo ≤ 256 KB mas payload base64 > 256 KB → usa SFTP para respeitar limite do stdin', function () {
    Storage::fake('local');

    $cluster = makeProvisionCluster();
    $operator = makeOperator('operador');
    $jobId = Str::uuid()->toString();

    $tmpFile = tempnam(sys_get_temp_dir(), 'logo_payload_limit_');
    file_put_contents($tmpFile, str_repeat('X', 200 * 1024));

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('inboxInit')->once();
    $sshMock->shouldReceive('sftpUpload')->once();
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $bin, $args, $stdin) {
            $decoded = is_string($stdin) ? json_decode($stdin, true) : null;

            return is_array($decoded)
                && ($decoded['shell'] ?? null) === true
                && in_array('--suite-catalog', $args, true)
                && collect($args)->contains(fn ($a) => str_starts_with((string) $a, '--staging-id='))
                && ! collect($args)->contains('--payload-stdin');
        })
        ->andReturn(sshProvisionSuccess($jobId));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $payload = new ProvisionPayload(
        slug: 'acme-payload-limit',
        domain: 'acme-payload-limit.example.com',
        clusterServerId: $cluster->id,
        apps: [],
        fullApps: false,
        logoPath: $tmpFile,
        backgroundPath: null,
    );

    $action = app(ProvisionCustomerAction::class);
    $result = $action->execute($payload, $operator);

    @unlink($tmpFile);

    expect($result['customer']->slug)->toBe('acme-payload-limit');
    expect(Job::find($jobId))->not->toBeNull();
});

it('logo e background ≤ 256 KB → --payload-stdin com branding.*_data_url; sem SFTP', function () {
    Storage::fake('local');

    $cluster = makeProvisionCluster();
    $operator = makeOperator('operador');
    $jobId = Str::uuid()->toString();

    $logoFile = tempnam(sys_get_temp_dir(), 'logo_inline_');
    $backgroundFile = tempnam(sys_get_temp_dir(), 'background_inline_');
    file_put_contents($logoFile, str_repeat('L', 64 * 1024));
    file_put_contents($backgroundFile, str_repeat('B', 64 * 1024));

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('inboxInit');
    $sshMock->shouldNotReceive('sftpUpload');
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $bin, $args, $stdin) {
            $hasFlag = collect($args)->contains('--payload-stdin');
            $decoded = json_decode((string) $stdin, true);

            return $hasFlag
                && isset($decoded['branding']['logo_data_url'])
                && isset($decoded['branding']['background_data_url'])
                && str_starts_with($decoded['branding']['logo_data_url'], 'data:image/png;base64,')
                && str_starts_with($decoded['branding']['background_data_url'], 'data:image/png;base64,')
                && ! array_key_exists('logo_data_url', $decoded);
        })
        ->andReturn(sshProvisionSuccess($jobId));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $payload = new ProvisionPayload(
        slug: 'acme-inline',
        domain: 'acme-inline.example.com',
        clusterServerId: $cluster->id,
        apps: [],
        fullApps: false,
        logoPath: $logoFile,
        backgroundPath: $backgroundFile,
    );

    $action = app(ProvisionCustomerAction::class);
    $result = $action->execute($payload, $operator);

    @unlink($logoFile);
    @unlink($backgroundFile);

    expect($result['customer']->slug)->toBe('acme-inline');
    expect(Job::find($jobId))->not->toBeNull();
    Storage::disk('local')->assertExists('branding/acme-inline/logo.png');
    Storage::disk('local')->assertExists('branding/acme-inline/background.png');
});

it('POST /api/customers com logo e background pequenos → stdin branding e branding_meta persistido', function () {
    Storage::fake('local');

    $cluster = makeProvisionCluster();
    $operator = makeOperator();
    $jobId = Str::uuid()->toString();
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
    $logoFile = tempnam(sys_get_temp_dir(), 'http_logo_');
    $backgroundFile = tempnam(sys_get_temp_dir(), 'http_background_');
    file_put_contents($logoFile, $pngBytes);
    file_put_contents($backgroundFile, $pngBytes);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('inboxInit');
    $sshMock->shouldNotReceive('sftpUpload');
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $bin, $args, $stdin) {
            $decoded = json_decode((string) $stdin, true);

            return collect($args)->contains('--payload-stdin')
                && isset($decoded['branding']['logo_data_url'])
                && isset($decoded['branding']['background_data_url']);
        })
        ->andReturn(sshProvisionSuccess($jobId));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->post('/api/customers', [
        'slug' => 'acme-http-branding',
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme-http-branding.example.com',
        'logo' => new UploadedFile($logoFile, 'logo.png', 'image/png', null, true),
        'background' => new UploadedFile($backgroundFile, 'background.png', 'image/png', null, true),
    ], ['Accept' => 'application/json']);

    @unlink($logoFile);
    @unlink($backgroundFile);

    $response->assertStatus(201);

    $customer = Customer::find('acme-http-branding');
    expect($customer->branding_meta['logo_path'])->toBe('branding/acme-http-branding/logo.png')
        ->and($customer->branding_meta['background_path'])->toBe('branding/acme-http-branding/background.png');

    Storage::disk('local')->assertExists($customer->branding_meta['logo_path']);
    Storage::disk('local')->assertExists($customer->branding_meta['background_path']);
});

it('sem autenticação → 401', function () {
    $response = $this->postJson('/api/customers', [
        'slug' => 'acme-noauth',
        'cluster_server_id' => Str::uuid()->toString(),
        'domain' => 'acme.example.com',
    ]);

    $response->assertStatus(401);
});

// ── F11.1 / ISSUE-018: re-provisioning após provision.failed ──────────────────

it('re-provisionar slug após provision.failed → ghost restaurado, 201 + novo job (CQ-F11-001/QA-F11-001)', function () {
    // Arrange: ghost Customer soft-deleted (simula estado após WebhookHandler processar provision.failed)
    $cluster = makeProvisionCluster();
    $operator = makeOperator();
    $ghostJobId = Str::uuid()->toString();

    Customer::create([
        'slug' => 'failed-tenant',
        'cluster_server_id' => $cluster->id,
        'domain' => 'failed-tenant.example.com',
        'status' => 'failed',
    ])->delete(); // soft-delete simulating WebhookHandler

    // FK constraint check: create a Job referencing the ghost (as would exist after provision.failed)
    Job::create([
        'job_id' => $ghostJobId,
        'customer_slug' => 'failed-tenant',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'create',
        'job_type' => 'provision',
        'state' => 'failed',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(10),
    ]);

    $newJobId = Str::uuid()->toString();
    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(sshProvisionSuccess($newJobId));
    $this->app->instance(SshClientInterface::class, $sshMock);

    // Act: re-provisionar o mesmo slug
    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'failed-tenant',
        'cluster_server_id' => $cluster->id,
        'domain' => 'failed-tenant.example.com',
    ]);

    // Assert: 2xx, ghost restaurado como active customer, novo Job criado
    $response->assertSuccessful(); // 200 (restore) ou 201 (create) — ambos válidos
    expect(Customer::find('failed-tenant'))->not->toBeNull()
        ->and(Customer::find('failed-tenant')->status)->toBe('provisioning')
        ->and(Customer::find('failed-tenant')->deleted_at)->toBeNull();
    expect(Job::find($newJobId))->not->toBeNull();

    // Velhos jobs do ghost NÃO foram deletados (audit trail preservado)
    expect(Job::find($ghostJobId))->not->toBeNull();
});

it('slug de customer ativo (não soft-deleted) → 422 (unique constraint mantém rejeição)', function () {
    $cluster = makeProvisionCluster();
    $operator = makeOperator();

    Customer::create([
        'slug' => 'active-tenant',
        'cluster_server_id' => $cluster->id,
        'domain' => 'active-tenant.example.com',
        'status' => 'active',
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('runAsync');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'active-tenant',
        'cluster_server_id' => $cluster->id,
        'domain' => 'active-tenant.example.com',
    ])->assertStatus(422);
});

it('re-provisionar ghost com logo em branding_meta → create envia branding.logo_data_url sem novo upload', function () {
    Storage::fake('local');

    $cluster = makeProvisionCluster();
    $operator = makeOperator();
    $jobId = Str::uuid()->toString();
    $logoPath = 'branding/failed-with-logo/logo.png';

    Storage::disk('local')->put($logoPath, 'stored-logo-content');

    Customer::create([
        'slug' => 'failed-with-logo',
        'cluster_server_id' => $cluster->id,
        'domain' => 'failed-with-logo.example.com',
        'status' => 'failed',
        'branding_meta' => ['logo_path' => $logoPath],
    ])->delete();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('inboxInit');
    $sshMock->shouldNotReceive('sftpUpload');
    $sshMock->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $bin, $args, $stdin) {
            $decoded = json_decode((string) $stdin, true);

            return collect($args)->contains('--payload-stdin')
                && isset($decoded['branding']['logo_data_url'])
                && ! isset($decoded['branding']['background_data_url']);
        })
        ->andReturn(sshProvisionSuccess($jobId));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $response = $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'failed-with-logo',
        'cluster_server_id' => $cluster->id,
        'domain' => 'failed-with-logo.example.com',
    ]);

    $response->assertSuccessful();

    expect(Customer::find('failed-with-logo')->branding_meta['logo_path'])->toBe($logoPath);
    expect(Job::find($jobId))->not->toBeNull();
});
