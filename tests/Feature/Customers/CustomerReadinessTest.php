<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\Show;
use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Customers\Services\CustomerSyncService;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Services\PlatformPortFactory;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function (): void {
    app()->forgetInstance(CustomerReadinessProbe::class);
    app()->forgetInstance(PlatformPortFactory::class);
    app()->forgetInstance(SshPlatformAdapter::class);
    app()->forgetInstance(AgentPlatformAdapter::class);
    app()->forgetInstance(SshClientInterface::class);
    app()->forgetInstance(AgentTransportResolver::class);
    Http::swap(new Factory);
});

afterEach(fn () => Mockery::close());

it('POST users on provisioning_finishing returns 503 tenant_not_ready', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'ready-gate-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'ready-gate.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
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
        'image_mode' => false,
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
        'image_mode' => false,
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
        'image_mode' => false,
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
        'image_mode' => false,
    ]);

    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 200);

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
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
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
        'image_mode' => false,
    ]);

    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 503);

    $ssh = readinessSshMockWithGatesR1ToR5();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
    $deadline = now()->addHour()->timestamp;
    (new ProbeCustomerReadinessJob($customer->slug, $deadline))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);

    $audit = AuditLog::where('action', 'customer_readiness_probe')
        ->where('resource_id', $customer->slug)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['attempt'])->toBe(1)
        ->and($audit->payload['error'])->toBe('HTTP 503')
        ->and($audit->payload['probe'])->toBe('http:memail');
});

it('ProbeCustomerReadinessJob promotes to active when R6 meMail HTTP returns 200', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-r6-ok-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-r6-ok.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);

    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 200);

    $ssh = readinessSshMockWithGatesR1ToR5();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
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
        'image_mode' => false,
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
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
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
        'image_mode' => false,
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: '',
        stderr: 'not ready',
        exitCode: 1,
        parsedJson: null,
    ));
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
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
        'image_mode' => false,
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
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
        'image_mode' => false,
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
        'image_mode' => false,
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

    fakeReadinessHttp($customer->domain, '/login', 200);

    $ssh = readinessSshMockWithoutMemailApp();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
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

    fakeReadinessHttp($customer->domain, '/login', 500);

    $ssh = readinessSshMockWithGatesR1ToR5();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
    $deadline = now()->addHour()->timestamp;
    (new ProbeCustomerReadinessJob($customer->slug, $deadline))->handle($probe);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'mework360_memail'));
    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);

    expect(AuditLog::where('action', 'customer_readiness_probe')
        ->where('resource_id', $customer->slug)
        ->exists())->toBeTrue();
});

// ── N39.5: readiness probe audit + UI ────────────────────────────────────────

it('ProbeCustomerReadinessJob grava dois audits com attempt incremental em falhas consecutivas', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-inc-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-inc.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);

    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 503);

    $ssh = readinessSshMockWithGatesR1ToR5();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
    $deadline = now()->addHour()->timestamp;

    foreach ([1, 2] as $attempt) {
        $fakeQueueJob = new FakeJob;
        $fakeQueueJob->attempts = $attempt;

        (new ProbeCustomerReadinessJob($customer->slug, $deadline))
            ->setJob($fakeQueueJob)
            ->handle($probe);
    }

    $logs = AuditLog::where('action', 'customer_readiness_probe')
        ->where('resource_id', $customer->slug)
        ->orderBy('created_at')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->payload['attempt'])->toBe(1)
        ->and($logs[1]->payload['attempt'])->toBe(2)
        ->and($logs[0]->payload)->toHaveKeys(['attempt', 'error', 'probe']);
});

it('customers show exibe card readiness com ultimo erro em provisioning_finishing', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'show-ready-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'show-ready.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'actor_id' => null,
        'action' => 'customer_readiness_probe',
        'resource_type' => 'customer',
        'resource_id' => $customer->slug,
        'payload' => [
            'attempt' => 3,
            'error' => 'HTTP 404',
            'probe' => 'http:memail',
        ],
        'cluster_server_id' => $cluster->id,
        'job_id' => null,
        'ip' => null,
    ]);

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertSee('Readiness')
        ->assertSee('tentativa 3/10')
        ->assertSee('HTTP 404');
});

it('customers show nao exibe card readiness fora de provisioning_finishing', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'show-no-ready-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'show-no-ready.example.com',
        'status' => CustomerLifecycleStatus::ACTIVE,
        'image_mode' => false,
    ]);
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);

    Livewire::actingAs($operator)
        ->test(Show::class, ['slug' => $customer->slug])
        ->assertDontSee('Aguardando primeira verificação');
});

// ── occ-exec shim envelope (parsed_result + version strings) ─────────────────

/**
 * @param  array<string, mixed>  $parsedResult
 * @return array<string, mixed>
 */
function readinessOccExecShimEnvelope(string $occCommand, array $parsedResult, ?string $stdout = null): array
{
    return [
        'schema_version' => '1',
        'occ_command' => $occCommand,
        'exit_code' => 0,
        'stdout' => $stdout ?? json_encode($parsedResult),
        'parsed_result' => $parsedResult,
    ];
}

function readinessSshMockWithOccExecShimEnvelopes(): MockInterface
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturnUsing(function (
        ClusterServer $clusterArg,
        string $cmd,
        array $argv,
    ): SshResponse {
        $occ = $argv[2] ?? '';

        if ($occ === 'app:list') {
            $inner = [
                'enabled' => [
                    'mework360_memail' => '2.0.1',
                    'me360_theme' => '1.6.15',
                ],
            ];
            $envelope = readinessOccExecShimEnvelope('app:list', $inner);

            return new SshResponse(
                stdout: json_encode($envelope),
                stderr: '',
                exitCode: 0,
                parsedJson: $envelope,
            );
        }

        if ($occ === 'user:list') {
            return new SshResponse(stdout: '[]', stderr: '', exitCode: 0, parsedJson: []);
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'externalLocation') {
            $value = 'https://cloud.example/roundcube';
            $envelope = readinessOccExecShimEnvelope('config:app:get', ['value' => $value], $value);

            return new SshResponse(
                stdout: json_encode($envelope),
                stderr: '',
                exitCode: 0,
                parsedJson: $envelope,
            );
        }

        if ($occ === 'config:app:get' && ($argv[4] ?? '') === 'forceSSO') {
            $envelope = readinessOccExecShimEnvelope('config:app:get', ['value' => 'yes'], 'yes');

            return new SshResponse(
                stdout: json_encode($envelope),
                stderr: '',
                exitCode: 0,
                parsedJson: $envelope,
            );
        }

        return new SshResponse(stdout: '', stderr: 'unexpected occ', exitCode: 1, parsedJson: null);
    });

    return $ssh;
}

it('ProbeCustomerReadinessJob promotes tenant when occ-exec shim returns envelope with version strings', function () {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-shim-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-shim.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);

    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 200);

    $ssh = readinessSshMockWithOccExecShimEnvelopes();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
    (new ProbeCustomerReadinessJob($customer->slug))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE)
        ->and(AuditLog::where('action', 'customer_readiness_confirmed')->where('resource_id', $customer->slug)->exists())->toBeTrue();
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

    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 200);

    $ssh = readinessSshMockWithGatesR1ToR5();
    $probe = makeCustomerReadinessProbeWithSsh($ssh);
    (new ProbeCustomerReadinessJob($customer->slug))->handle($probe);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/apps/mework360_memail/'));
    Http::assertNotSent(fn ($request) => str_ends_with(rtrim($request->url(), '/'), '/login'));
    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE);
});
