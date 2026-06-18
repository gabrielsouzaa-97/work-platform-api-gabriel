<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Onboarding;
use App\Models\Operator;
use App\Modules\Customers\Contracts\ProvisionsCustomer;
use App\Modules\Customers\Dto\ProvisionPayload;
use App\Modules\Onboarding\Dto\OnboardingSpec;
use App\Modules\Onboarding\Enums\OnboardingState;
use App\Modules\Onboarding\Enums\OnboardingStep;
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

it('starts onboarding pending or running with first step provision_tenant', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $provision = mockProvisionReturns(Str::uuid()->toString(), 'saga-tenant', $cluster);

    $onboarding = (new OnboardingSaga($provision))->start(sagaSpec($cluster), $operator);

    expect($onboarding)->toBeInstanceOf(Onboarding::class)
        ->and(in_array($onboarding->state, [OnboardingState::Pending, OnboardingState::Running], true))->toBeTrue()
        ->and($onboarding->steps)->toHaveKey('provision_tenant');
});

it('records job_id per step in steps JSON', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $jobId = Str::uuid()->toString();
    $provision = mockProvisionReturns($jobId, 'saga-tenant', $cluster);

    $onboarding = (new OnboardingSaga($provision))->start(sagaSpec($cluster), $operator);

    expect($onboarding->steps['provision_tenant']['job_id'] ?? null)->toBe($jobId);
});

it('advances to wait_readiness after provision dispatch', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $provision = mockProvisionReturns(Str::uuid()->toString(), 'saga-tenant', $cluster);

    $onboarding = (new OnboardingSaga($provision))->start(sagaSpec($cluster), $operator);

    expect($onboarding->current_step)->toBe(OnboardingStep::WaitReadiness);
});

it('sets correlation_id equal to onboarding uuid', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $operator = Operator::factory()->create();
    $jobId = Str::uuid()->toString();
    $provision = mockProvisionReturns($jobId, 'saga-tenant', $cluster);

    $onboarding = (new OnboardingSaga($provision))->start(sagaSpec($cluster), $operator);

    expect($onboarding->correlation_id)->toBe($onboarding->id)
        ->and(Job::find($jobId)?->correlation_id)->toBe($onboarding->id);
});
