<?php

declare(strict_types=1);

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Customers\Services\CustomerSyncService;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('POST users on provisioning_finishing returns 503 tenant_not_ready', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'ready-gate-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'ready-gate.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $response = $this->actingAs($operator)->postJson("/api/customers/{$customer->slug}/users", [
        'username' => 'alice',
        'password' => 'Secret123!',
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

it('DELETE users on provisioning_finishing returns 503 tenant_not_ready', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'del-gate-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'del-gate.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $this->actingAs($operator)->deleteJson("/api/customers/{$customer->slug}/users/alice")
        ->assertStatus(503)
        ->assertHeader('Retry-After', '60')
        ->assertJson([
            'error' => 'tenant_not_ready',
            'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        ]);

    expect(Job::count())->toBe(0)
        ->and(IdempotencyKey::count())->toBe(0);
});

it('POST users on provisioning returns 503 tenant_not_ready', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'prov-gate-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'prov-gate.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    $this->actingAs($operator)->postJson("/api/customers/{$customer->slug}/users", [
        'username' => 'alice',
        'password' => 'Secret123!',
    ])->assertStatus(503)
        ->assertJson([
            'error' => 'tenant_not_ready',
            'status' => CustomerLifecycleStatus::PROVISIONING,
        ]);
});

it('POST groups:create on provisioning_finishing still returns 202', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'grp-gate-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'grp-gate.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $ssh);

    $this->actingAs($operator)->postJson("/api/customers/{$customer->slug}/groups", [
        'name' => 'marketing',
    ])->assertStatus(202)->assertJson(['job_id' => $jobId]);
});

it('ProbeCustomerReadinessJob promotes tenant to active when probe succeeds', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-ok-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-ok.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    Http::fake([
        'https://probe-ok.example.com/apps/mework360_memail/*' => Http::response('OK', 200),
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->times(4)->andReturnUsing(function (
        ClusterServer $clusterArg,
        string $cmd,
        array $argv,
    ): SshResponse {
        $occ = $argv[2] ?? '';

        if ($occ === 'app:list') {
            return new SshResponse(
                stdout: json_encode(['enabled' => ['mework360_memail' => true, 'me360_theme' => true]]),
                stderr: '',
                exitCode: 0,
                parsedJson: ['enabled' => ['mework360_memail' => true, 'me360_theme' => true]],
            );
        }

        if ($occ === 'user:list') {
            return new SshResponse(stdout: '[]', stderr: '', exitCode: 0, parsedJson: []);
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'externalLocation') {
            return new SshResponse(
                stdout: 'https://cloud.example/roundcube',
                stderr: '',
                exitCode: 0,
                parsedJson: ['value' => 'https://cloud.example/roundcube'],
            );
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'forceSSO') {
            return new SshResponse(
                stdout: 'yes',
                stderr: '',
                exitCode: 0,
                parsedJson: ['value' => 'yes'],
            );
        }

        return new SshResponse(stdout: '', stderr: 'unexpected occ', exitCode: 1, parsedJson: null);
    });
    app()->instance(SshClientInterface::class, $ssh);
    $probe = app(CustomerReadinessProbe::class);
    (new ProbeCustomerReadinessJob($customer->slug))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE)
        ->and(AuditLog::where('action', 'customer_readiness_confirmed')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('ProbeCustomerReadinessJob keeps finishing when R6 meMail HTTP gate fails', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-r6-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-r6.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    Http::fake([
        'https://probe-r6.example.com/apps/mework360_memail/*' => Http::response('Service Unavailable', 503),
    ]);

    app()->instance(SshClientInterface::class, readinessSshMockWithGatesR1ToR5());
    $probe = app(CustomerReadinessProbe::class);
    $deadline = now()->addHour()->timestamp;
    (new ProbeCustomerReadinessJob($customer->slug, $deadline))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);
});

it('ProbeCustomerReadinessJob promotes to active when R6 meMail HTTP returns 200', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-r6-ok-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-r6-ok.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    Http::fake([
        'https://probe-r6-ok.example.com/apps/mework360_memail/*' => Http::response('OK', 200),
    ]);

    app()->instance(SshClientInterface::class, readinessSshMockWithGatesR1ToR5());
    $probe = app(CustomerReadinessProbe::class);
    (new ProbeCustomerReadinessJob($customer->slug))->handle($probe);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/apps/mework360_memail/'));
    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE);
});

it('ProbeCustomerReadinessJob keeps finishing when memail externalLocation gate fails', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-r4-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-r4.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturnUsing(function (
        ClusterServer $clusterArg,
        string $cmd,
        array $argv,
    ): SshResponse {
        $occ = $argv[2] ?? '';

        if ($occ === 'app:list') {
            return new SshResponse(
                stdout: json_encode(['enabled' => ['mework360_memail' => true, 'me360_theme' => true]]),
                stderr: '',
                exitCode: 0,
                parsedJson: ['enabled' => ['mework360_memail' => true, 'me360_theme' => true]],
            );
        }

        if ($occ === 'user:list') {
            return new SshResponse(stdout: '[]', stderr: '', exitCode: 0, parsedJson: []);
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'externalLocation') {
            return new SshResponse(stdout: '', stderr: '', exitCode: 0, parsedJson: ['value' => '']);
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'forceSSO') {
            return new SshResponse(stdout: 'yes', stderr: '', exitCode: 0, parsedJson: ['value' => 'yes']);
        }

        return new SshResponse(stdout: '', stderr: 'unexpected', exitCode: 1, parsedJson: null);
    });
    app()->instance(SshClientInterface::class, $ssh);
    $probe = app(CustomerReadinessProbe::class);
    $deadline = now()->addHour()->timestamp;
    (new ProbeCustomerReadinessJob($customer->slug, $deadline))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);
});

it('ProbeCustomerReadinessJob keeps finishing when probe returns non-zero exit', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-nz-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-nz.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: '',
        stderr: 'not ready',
        exitCode: 1,
        parsedJson: null,
    ));
    app()->instance(SshClientInterface::class, $ssh);
    $probe = app(CustomerReadinessProbe::class);
    $deadline = now()->addHour()->timestamp;
    (new ProbeCustomerReadinessJob($customer->slug, $deadline))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);
});

it('ProbeCustomerReadinessJob marks failed when deadline exceeded', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-dead-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-dead.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);
    $probe = app(CustomerReadinessProbe::class);
    (new ProbeCustomerReadinessJob($customer->slug, now()->subSecond()->timestamp))->handle($probe);

    expect($customer->fresh()->status)->toBe('failed')
        ->and(AuditLog::where('action', 'customer_readiness_timeout')->where('resource_id', $customer->slug)->exists())->toBeTrue();
});

it('ProbeCustomerReadinessJob deadline defaults to max_wait_seconds from config', function () {
    config(['services.customer_readiness.max_wait_seconds' => 1200]);

    $before = now()->timestamp;
    $deadline = ProbeCustomerReadinessJob::deadlineTimestamp();
    $after = now()->addSeconds(1200)->timestamp;

    expect($deadline)->toBeGreaterThanOrEqual($before + 1200)
        ->and($deadline)->toBeLessThanOrEqual($after + 1);
});

it('CustomerSyncService does not overwrite provisioning_finishing with active from upstream', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'name' => 'sync-finishing']);

    Customer::create([
        'slug' => 'finishing-co',
        'cluster_server_id' => $cluster->id,
        'domain' => 'finishing.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);

    $payload = [
        'schema_version' => '1',
        'instances' => [
            ['name' => 'finishing-co', 'domain' => 'finishing.example.com', 'status' => 'running'],
        ],
        'shared_services' => [],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: json_encode($payload),
        stderr: '',
        exitCode: 0,
        parsedJson: $payload,
    ));

    app()->instance(SshClientInterface::class, $ssh);

    $report = app(CustomerSyncService::class)->sync($cluster);

    expect($report->updated)->toBe(1)
        ->and(Customer::find('finishing-co')->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);
});

it('CustomerSyncService does not overwrite provisioning with active from upstream', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active', 'name' => 'sync-provisioning']);

    Customer::create([
        'slug' => 'still-provisioning',
        'cluster_server_id' => $cluster->id,
        'domain' => 'still.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING,
    ]);

    $payload = [
        'schema_version' => '1',
        'instances' => [
            ['name' => 'still-provisioning', 'domain' => 'still.example.com', 'status' => 'running'],
        ],
        'shared_services' => [],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: json_encode($payload),
        stderr: '',
        exitCode: 0,
        parsedJson: $payload,
    ));

    app()->instance(SshClientInterface::class, $ssh);

    app(CustomerSyncService::class)->sync($cluster);

    expect(Customer::find('still-provisioning')->status)->toBe(CustomerLifecycleStatus::PROVISIONING);
});

// ── N36.5: readiness gate compatible with image-mode tenants ─────────────────

it('ProbeCustomerReadinessJob promotes image-mode tenant when login HTTP gate returns 200', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-img-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-img.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => true,
    ]);

    Http::fake([
        'https://probe-img.example.com/login' => Http::response('OK', 200),
    ]);

    app()->instance(SshClientInterface::class, readinessSshMockWithGatesR1ToR5());
    $probe = app(CustomerReadinessProbe::class);
    (new ProbeCustomerReadinessJob($customer->slug))->handle($probe);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/login'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'mework360_memail'));
    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE);
});

it('ProbeCustomerReadinessJob keeps finishing when image-mode login HTTP gate fails', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-img-fail-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-img-fail.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => true,
    ]);

    Http::fake([
        'https://probe-img-fail.example.com/login' => Http::response('Service Unavailable', 500),
    ]);

    app()->instance(SshClientInterface::class, readinessSshMockWithGatesR1ToR5());
    $probe = app(CustomerReadinessProbe::class);
    $deadline = now()->addHour()->timestamp;
    (new ProbeCustomerReadinessJob($customer->slug, $deadline))->handle($probe);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'mework360_memail'));
    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);
});

it('ProbeCustomerReadinessJob legacy tenant still probes mework360_memail HTTP gate', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-legacy-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-legacy.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);

    Http::fake([
        'https://probe-legacy.example.com/apps/mework360_memail/*' => Http::response('OK', 200),
    ]);

    app()->instance(SshClientInterface::class, readinessSshMockWithGatesR1ToR5());
    $probe = app(CustomerReadinessProbe::class);
    (new ProbeCustomerReadinessJob($customer->slug))->handle($probe);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/apps/mework360_memail/'));
    Http::assertNotSent(fn ($request) => str_ends_with(rtrim($request->url(), '/'), '/login'));
    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE);
});
