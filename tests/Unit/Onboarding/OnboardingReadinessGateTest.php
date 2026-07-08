<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Contracts\ProvisionsCustomer;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Services\PlatformPortFactory;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
use App\Modules\Onboarding\Saga\OnboardingSaga;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses()->group('readiness-isolated');

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    Config::set('services.agent.transport_enabled', false);
    config([
        'cache.default' => 'array',
        'platform.image_mode.default_mode' => false,
        'services.customer_readiness.retry_after_seconds' => 60,
        'services.customer_readiness.probe_timeout_seconds' => 25,
    ]);
    resetCustomerReadinessProbeContainer();
    Http::swap(new Factory);
});

afterEach(fn () => Mockery::close());

function readinessGateOnboarding(string $slug = 'readiness-gate-acme'): Onboarding
{
    return Onboarding::factory()->create([
        'tenant_slug' => $slug,
        'state' => OnboardingState::Running,
        'current_step' => OnboardingStep::WaitReadiness,
        'steps' => [
            'provision_tenant' => [
                'job_id' => 'job-provision-1',
            ],
        ],
        'admin_payload' => [
            'username' => 'admin-user',
            'password' => 'Secret123!',
            'email' => "admin@{$slug}.example.com",
        ],
        'apps_enabled' => ['calendar'],
    ]);
}

function readinessGateCustomer(string $slug, string $status = 'provisioning_finishing'): Customer
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    return Customer::create([
        'slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => $status,
        'image_mode' => false,
    ]);
}

function readinessGateSaga(?CustomerReadinessProbe $probe = null): OnboardingSaga
{
    $provision = Mockery::mock(ProvisionsCustomer::class);
    $provision->shouldIgnoreMissing();

    return new OnboardingSaga(
        $provision,
        $probe ?? app(CustomerReadinessProbe::class),
        app(LifecycleAsyncAction::class),
        app(PlatformPortFactory::class),
    );
}

function mockReadinessProbeSsh(int $exitCode): CustomerReadinessProbe
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturnUsing(function (
        ClusterServer $clusterArg,
        string $cmd,
        array $argv,
    ) use ($exitCode): SshResponse {
        return new SshResponse(
            stdout: $exitCode === 0 ? '[]' : '',
            stderr: $exitCode === 0 ? '' : 'not ready',
            exitCode: $exitCode,
            parsedJson: $exitCode === 0 ? [] : null,
        );
    });

    return makeCustomerReadinessProbeWithSsh($ssh);
}

it('advanceAfterProvision marks wait_readiness pending when tenant is not ready', function (): void {
    $slug = 'readiness-not-ready';
    $onboarding = readinessGateOnboarding($slug);
    readinessGateCustomer($slug);
    $probe = mockReadinessProbeSsh(1);

    readinessGateSaga($probe)->advanceAfterProvision($onboarding->fresh());

    $onboarding->refresh();
    expect($onboarding->current_step)->toBe(OnboardingStep::WaitReadiness)
        ->and($onboarding->steps['wait_readiness']['status'])->toBe('pending')
        ->and($onboarding->steps['wait_readiness']['reason'])->toBe('tenant_not_ready')
        ->and($onboarding->steps['wait_readiness']['retry_after'])->toBe(60);
});

it('advanceAfterProvision advances to create_admin when tenant is ready', function (): void {
    $slug = 'readiness-ready';
    $onboarding = readinessGateOnboarding($slug);
    $customer = readinessGateCustomer($slug, 'active');
    Operator::factory()->create();
    $adminJobId = Str::uuid()->toString();

    $ssh = readinessGateSshMockWithGatesR1ToR5();
    fakeReadinessHttp($customer->domain, '/apps/mework360_memail/', 200);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $adminJobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $adminJobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    readinessGateSaga(makeCustomerReadinessProbeWithSsh($ssh))->advanceAfterProvision($onboarding->fresh());

    $onboarding->refresh();
    expect($onboarding->current_step)->toBe(OnboardingStep::CreateAdmin)
        ->and($onboarding->steps['wait_readiness']['status'])->toBe('completed')
        ->and($onboarding->steps['create_admin']['status'])->toBe('running');
});

it('advanceAfterProvision ignores onboarding not at wait_readiness step', function (): void {
    $onboarding = Onboarding::factory()->create([
        'current_step' => OnboardingStep::CreateAdmin,
        'steps' => ['create_admin' => ['status' => 'pending']],
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    readinessGateSaga()->advanceAfterProvision($onboarding->fresh());

    $onboarding->refresh();
    expect($onboarding->current_step)->toBe(OnboardingStep::CreateAdmin);
});
