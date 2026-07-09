<?php

declare(strict_types=1);

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Support\Str;

uses()->group('n47-failure-reason');

beforeEach(function (): void {
    $noop = Mockery::mock(SshClientInterface::class);
    $noop->shouldReceive('run')->andReturn(new SshResponse(
        stdout: '[]',
        stderr: '',
        exitCode: 0,
        parsedJson: [],
    ));
    app()->instance(SshClientInterface::class, $noop);
});

function failureReasonCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['ssh_host' => '127.0.0.1']);
}

function failureReasonProvisionJob(ClusterServer $cluster, string $slug, string $state = 'running'): Job
{
    Customer::firstOrCreate(['slug' => $slug], [
        'cluster_server_id' => $cluster->id,
        'domain' => "{$slug}.example.com",
        'status' => CustomerLifecycleStatus::PROVISIONING,
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $slug,
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => "nextcloud-manage {$slug} _ provision",
        'job_type' => 'provision',
        'state' => $state,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

it('provision webhook failed persists sanitized failure_reason on customer', function (): void {
    $cluster = failureReasonCluster();
    $slug = 'prov-fail-'.substr(uniqid(), -6);
    $job = failureReasonProvisionJob($cluster, $slug);
    $summary = ['[INFO] Starting provision', '[ERROR] DNS validation failed for tenant'];

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'failed',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 1,
        'summary' => $summary,
    ]);

    $customer = Customer::query()->where('slug', $slug)->first();

    expect($customer)->not->toBeNull()
        ->and($customer->status)->toBe(CustomerLifecycleStatus::FAILED)
        ->and($customer->failure_reason)->toBe('DNS validation failed for tenant');
});

it('ProbeCustomerReadinessJob timeout persists failure_reason customer_readiness_timeout', function (): void {
    config(['services.customer_readiness.probe_timeout_seconds' => 25]);
    beginReadinessIsolatedTest();

    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'probe-fr-'.substr(uniqid(), -6),
        'cluster_server_id' => $cluster->id,
        'domain' => 'probe-fr.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'image_mode' => false,
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    $probe = makeCustomerReadinessProbeWithSsh($ssh);

    (new ProbeCustomerReadinessJob($customer->slug, now()->subSecond()->timestamp))->handle($probe);

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::FAILED)
        ->and($customer->fresh()->failure_reason)->toBe('customer_readiness_timeout');
});

it('customers:promote clears failure_reason when promoting to active', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'promote-fr-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'promote-fr.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
        'failure_reason' => 'customer_readiness_timeout',
    ]);
    $operator = Operator::factory()->create(['role' => 'admin', 'status' => 'active']);

    expect($customer->fresh()->failure_reason)->toBe('customer_readiness_timeout');

    $this->actingAs($operator)
        ->artisan('customers:promote', ['slug' => $customer->slug])
        ->assertSuccessful();

    expect($customer->fresh()->status)->toBe(CustomerLifecycleStatus::ACTIVE)
        ->and($customer->fresh()->failure_reason)->toBeNull();
});
