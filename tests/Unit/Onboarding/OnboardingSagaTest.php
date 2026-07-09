<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Contracts\ProvisionsCustomer;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Modules\Onboarding\Dto\OnboardingSpec;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use App\Modules\Jobs\Services\WebhookHandler;
use App\Modules\Onboarding\Saga\OnboardingSaga;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

function sagaSpec(ClusterServer $cluster): OnboardingSpec
{
    return new OnboardingSpec(
        tenantSlug: 'saga-tenant',
        domain: 'saga-tenant.example.com',
        clusterServerId: $cluster->id,
        apps: ['files'],
        fullApps: false,
        adminUsername: 'saga-admin',
        adminPassword: 'Secret123!',
        adminEmail: 'admin@saga-tenant.example.com',
        adminDisplayName: 'Saga Admin',
    );
}

function mockProvisionReturns(string $jobId, string $slug, ClusterServer $cluster): ProvisionsCustomer
{
    $customer = Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'provisioning',
    ]);

    $job = Job::create([
        'job_id' => $jobId,
        'customer_slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'create',
        'job_type' => 'provision',
        'state' => 'queued',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now(),
    ]);

    $mock = Mockery::mock(ProvisionsCustomer::class);
    $mock->shouldReceive('execute')
        ->once()
        ->with(Mockery::type(ProvisionPayload::class), Mockery::type(Operator::class))
        ->andReturn(['customer' => $customer, 'job' => $job]);

    return $mock;
}

function sagaWithMockedProbe(ProvisionsCustomer $provision): OnboardingSaga
{
    return new OnboardingSaga(
        $provision,
        app(CustomerReadinessProbe::class),
        app(LifecycleAsyncAction::class),
        app(PlatformPortFactory::class),
    );
}

it('starts onboarding pending or running with first step provision_tenant', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $provision = mockProvisionReturns(Str::uuid()->toString(), 'saga-tenant', $cluster);

    $onboarding = sagaWithMockedProbe($provision)->start(sagaSpec($cluster), $operator);

    expect($onboarding)->toBeInstanceOf(Onboarding::class)
        ->and(in_array($onboarding->state, [OnboardingState::Pending, OnboardingState::Running], true))->toBeTrue()
        ->and($onboarding->steps)->toHaveKey('provision_tenant');
});

it('records job_id per step in steps JSON', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $jobId = Str::uuid()->toString();
    $provision = mockProvisionReturns($jobId, 'saga-tenant', $cluster);

    $onboarding = sagaWithMockedProbe($provision)->start(sagaSpec($cluster), $operator);

    expect($onboarding->steps['provision_tenant']['job_id'] ?? null)->toBe($jobId);
});

it('advances to wait_readiness after provision dispatch', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $provision = mockProvisionReturns(Str::uuid()->toString(), 'saga-tenant', $cluster);

    $onboarding = sagaWithMockedProbe($provision)->start(sagaSpec($cluster), $operator);

    expect($onboarding->current_step)->toBe(OnboardingStep::WaitReadiness);
});

it('persists encrypted admin_payload on start', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $provision = mockProvisionReturns(Str::uuid()->toString(), 'saga-tenant', $cluster);

    $onboarding = sagaWithMockedProbe($provision)->start(sagaSpec($cluster), $operator);

    expect($onboarding->admin_payload)->toBeArray()
        ->and($onboarding->admin_payload['username'])->toBe('saga-admin')
        ->and($onboarding->admin_payload['email'])->toBe('admin@saga-tenant.example.com')
        ->and($onboarding->apps_enabled)->toBe(['files']);
});

it('sets correlation_id equal to onboarding uuid', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $jobId = Str::uuid()->toString();
    $provision = mockProvisionReturns($jobId, 'saga-tenant', $cluster);

    $onboarding = sagaWithMockedProbe($provision)->start(sagaSpec($cluster), $operator);

    expect($onboarding->correlation_id)->toBe($onboarding->id)
        ->and(Job::find($jobId)?->correlation_id)->toBe($onboarding->id);
});

it('dispatches users:create after readiness gate passes', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $slug = 'admin-dispatch-'.substr(uniqid(), -6);
    $adminJobId = Str::uuid()->toString();

    $onboarding = Onboarding::factory()->create([
        'tenant_slug' => $slug,
        'state' => OnboardingState::Running,
        'current_step' => OnboardingStep::WaitReadiness,
        'steps' => ['provision_tenant' => ['job_id' => Str::uuid()->toString()]],
        'admin_payload' => [
            'username' => 'admin-user',
            'password' => 'Secret123!',
            'email' => "admin@{$slug}.example.com",
        ],
        'apps_enabled' => ['calendar'],
    ]);

    Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);

    bindReadinessGateMocks(Customer::find($slug));

    Operator::factory()->create();

    $ssh = app(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($c, $cmd, $args, $stdin) use ($slug): bool {
            return $cmd === 'nextcloud-manage'
                && $args[0] === $slug
                && $args[1] === 'user'
                && $args[2] === 'create'
                && in_array('admin-user', $args, true)
                && str_contains((string) $stdin, 'Secret123!');
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $adminJobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $adminJobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    app(OnboardingSaga::class)->advanceAfterProvision($onboarding->fresh());

    $onboarding->refresh();
    expect($onboarding->current_step)->toBe(OnboardingStep::CreateAdmin)
        ->and($onboarding->steps['create_admin']['job_id'] ?? null)->toBe($adminJobId)
        ->and($onboarding->steps['create_admin']['status'] ?? null)->toBe('running')
        ->and(Job::find($adminJobId)?->correlation_id)->toBe($onboarding->correlation_id);
});

it('apps:enable job success with embedded failure marks step failed and onboarding failed', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $slug = 'saga-embed-fail-'.substr(uniqid(), -6);
    $onboardingId = Str::uuid()->toString();
    $enableAppsJobId = Str::uuid()->toString();

    Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);

    Onboarding::factory()->create([
        'id' => $onboardingId,
        'tenant_slug' => $slug,
        'correlation_id' => $onboardingId,
        'state' => OnboardingState::Running,
        'current_step' => OnboardingStep::EnableApps,
        'steps' => [
            'provision_tenant' => ['status' => 'completed'],
            'wait_readiness' => ['status' => 'completed'],
            'create_admin' => ['status' => 'completed'],
            'enable_apps' => ['status' => 'running', 'job_id' => $enableAppsJobId],
        ],
        'apps_enabled' => ['calendar'],
    ]);

    Job::create([
        'job_id' => $enableAppsJobId,
        'customer_slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => "nextcloud-manage {$slug} _ apps:enable",
        'job_type' => 'apps:enable',
        'state' => 'running',
        'correlation_id' => $onboardingId,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinute(),
        'summary' => ['{"error":"occ_command_failed","subcommand":"app:enable","stdout":"calendar app not found"}'],
    ]);

    $noop = Mockery::mock(SshClientInterface::class);
    $noop->shouldReceive('run')->andReturn(new SshResponse(
        stdout: '[]',
        stderr: '',
        exitCode: 0,
        parsedJson: [],
    ));
    app()->instance(SshClientInterface::class, $noop);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $enableAppsJobId,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    $onboarding = Onboarding::query()->findOrFail($onboardingId);

    expect($onboarding->steps['enable_apps']['status'])->toBe('failed')
        ->and($onboarding->state)->toBe(OnboardingState::Failed);
});
