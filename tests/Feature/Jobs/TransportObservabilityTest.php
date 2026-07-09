<?php

declare(strict_types=1);

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Agents\Services\AgentTransportResolver;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Integration\Adapters\AgentPlatformAdapter;
use App\Modules\Integration\Adapters\SshPlatformAdapter;
use App\Modules\Integration\Dto\CreateTenantCommand;
use App\Modules\Integration\Dto\ManageAsyncCommand;
use App\Modules\Jobs\Services\TransportObservability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function makeObsCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeObsJob(
    ClusterServer $cluster,
    array $overrides = [],
): Job {
    Customer::firstOrCreate(['slug' => 'obs-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'obs-co.example.com',
        'status' => 'active',
    ]);

    return Job::create(array_merge([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'obs-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'create',
        'job_type' => 'provision',
        'state' => 'queued',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subSeconds(120),
    ], $overrides));
}

it('emits transport.webhook_missing when queued job exceeds missing webhook SLA', function (): void {
    Config::set('observability.missing_webhook_sla_seconds', 60);

    $cluster = makeObsCluster();
    makeObsJob($cluster, [
        'state' => 'queued',
        'callback_received_at' => null,
        'queued_at' => now()->subSeconds(90),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('transport.webhook_missing', Mockery::on(
            fn (array $context): bool => $context['sla_seconds'] === 60
                && $context['elapsed_seconds'] >= 90
                && $context['job_type'] === 'provision',
        ));

    $this->artisan('jobs:observability-check')->assertSuccessful();
});

it('emits transport.job_stuck_sla when poll-stuck finds running job beyond SLA', function (): void {
    Config::set('observability.stuck_job_sla_seconds', 60);
    Queue::fake();

    $cluster = makeObsCluster();
    $job = makeObsJob($cluster, [
        'state' => 'running',
        'callback_received_at' => null,
        'queued_at' => now()->subSeconds(90),
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: json_encode(['state' => 'done', 'exit_code' => 0]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['state' => 'done', 'exit_code' => 0],
    ));
    $this->app->instance(SshClientInterface::class, $sshMock);

    $securityLog = Mockery::mock();
    $securityLog->shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('channel')->with('security')->andReturn($securityLog);

    Log::shouldReceive('warning')
        ->once()
        ->with('transport.job_stuck_sla', Mockery::on(
            fn (array $context): bool => $context['job_id'] === $job->job_id
                && $context['sla_seconds'] === 60,
        ));

    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('info')->zeroOrMoreTimes();

    $this->artisan('jobs:poll-stuck')->assertSuccessful();

    Queue::assertPushed(ProbeCustomerReadinessJob::class, 1);
});

it('emits transport.parity_divergence when SSH and Agent success rates diverge by job_type', function (): void {
    Config::set('observability.parity.min_samples_per_transport', 5);
    Config::set('observability.parity.success_rate_delta_threshold', 0.15);

    $cluster = makeObsCluster();
    $finishedAt = now()->subHour();

    for ($i = 0; $i < 5; $i++) {
        makeObsJob($cluster, [
            'job_id' => Str::uuid()->toString(),
            'state' => 'success',
            'finished_at' => $finishedAt,
            'payload_sanitized' => ['transport' => TransportObservability::TRANSPORT_SSH],
        ]);
    }

    for ($i = 0; $i < 5; $i++) {
        makeObsJob($cluster, [
            'job_id' => Str::uuid()->toString(),
            'state' => 'failed',
            'finished_at' => $finishedAt,
            'payload_sanitized' => ['transport' => TransportObservability::TRANSPORT_AGENT],
        ]);
    }

    Log::shouldReceive('warning')
        ->once()
        ->with('transport.parity_divergence', Mockery::on(
            fn (array $context): bool => $context['job_type'] === 'provision'
                && $context['ssh_success_rate'] === 1.0
                && $context['agent_success_rate'] === 0.0,
        ));

    $this->artisan('jobs:observability-check')->assertSuccessful();
});

it('records dispatch transport in cache and attaches to job on webhook', function (): void {
    $cluster = makeObsCluster();
    $job = makeObsJob($cluster, [
        'queued_at' => now(),
        'payload_sanitized' => [],
    ]);

    /** @var TransportObservability $observability */
    $observability = app(TransportObservability::class);
    $observability->recordDispatch(TransportObservability::TRANSPORT_AGENT, $job->job_id);

    expect(Cache::get('transport_obs:dispatch:'.$job->job_id))
        ->toBe(TransportObservability::TRANSPORT_AGENT);

    $observability->attachTransportToJob($job);
    $job->refresh();

    expect($job->payload_sanitized['transport'] ?? null)
        ->toBe(TransportObservability::TRANSPORT_AGENT);
});

it('does not emit alerts when observability is disabled', function (): void {
    Config::set('observability.enabled', false);

    $cluster = makeObsCluster();
    makeObsJob($cluster, [
        'state' => 'queued',
        'callback_received_at' => null,
        'queued_at' => now()->subMinutes(5),
    ]);

    Log::shouldReceive('warning')->never();

    $this->artisan('jobs:observability-check')->assertSuccessful();
});

it('ssh adapter records ssh transport on async dispatch', function (): void {
    $jobId = (string) Str::uuid();
    $cluster = makeObsCluster();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));

    $adapter = new SshPlatformAdapter(
        $ssh,
        app(TransportObservability::class),
    );

    $ref = $adapter->dispatchManageAsync(new ManageAsyncCommand(
        cluster: $cluster,
        manageArgs: ['tenant', 'create'],
        stdinJson: null,
    ));

    expect($ref->jobId)->toBe($jobId)
        ->and(Cache::get('transport_obs:dispatch:'.$jobId))
        ->toBe(TransportObservability::TRANSPORT_SSH);
});

it('agent adapter records agent transport on createTenant dispatch', function (): void {
    $jobId = (string) Str::uuid();
    $cluster = makeObsCluster();

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));

    $sshFallback = app(SshPlatformAdapter::class);
    $adapter = new AgentPlatformAdapter(
        $gateway,
        app(AgentTransportResolver::class),
        $sshFallback,
        app(TransportObservability::class),
    );

    $ref = $adapter->createTenant(new CreateTenantCommand(
        cluster: $cluster,
        manageArgs: ['tenant', 'create'],
        stdinJson: null,
        stagingId: null,
    ));

    expect($ref->jobId)->toBe($jobId)
        ->and(Cache::get('transport_obs:dispatch:'.$jobId))
        ->toBe(TransportObservability::TRANSPORT_AGENT);
});
