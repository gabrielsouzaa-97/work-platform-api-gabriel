<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

function characterizationPollStuckJob(): array
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    Customer::firstOrCreate(['slug' => 'char-poll-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-poll-co.example.com',
        'status' => 'active',
    ]);

    $job = Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'char-poll-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage char-poll-co _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);

    return [$cluster, $job];
}

function characterizationPollStatusArgs(array $args, string $jobId): bool
{
    return $args === ['job', $jobId, 'status', '--json'];
}

it('characterizes poll argv: job {id} status --json with timeout 30', function (): void {
    [$cluster, $job] = characterizationPollStuckJob();
    $finishedAt = now()->toIso8601String();
    $payload = ['state' => 'done', 'exit_code' => 0, 'finished_at' => $finishedAt];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args, ?string $stdin, int $timeout) use ($job): bool {
            return $cmd === 'nextcloud-manage'
                && characterizationPollStatusArgs($args, $job->job_id)
                && $stdin === null
                && $timeout === 30;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $this->artisan('jobs:poll-stuck')->assertSuccessful();

    $job->refresh();
    expect($job->state)->toBe('success')
        ->and($job->last_poll_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'job_polled')->where('job_id', $job->job_id)->exists())->toBeTrue();
});

it('characterizes inactive cluster skips poll without SSH', function (): void {
    $offlineCluster = ClusterServer::factory()->create(['status' => 'unreachable']);

    Customer::firstOrCreate(['slug' => 'char-poll-offline'], [
        'cluster_server_id' => $offlineCluster->id,
        'domain' => 'char-poll-offline.example.com',
        'status' => 'active',
    ]);

    Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'char-poll-offline',
        'cluster_server_id' => $offlineCluster->id,
        'cmd_canonical' => 'provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    $this->artisan('jobs:poll-stuck')->assertSuccessful();
});

it('characterizes SSH failure logs warning and keeps job running', function (): void {
    [$cluster, $job] = characterizationPollStuckJob();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshConnectionException('timeout'));
    app()->instance(SshClientInterface::class, $ssh);

    $this->artisan('jobs:poll-stuck')->assertSuccessful();

    $job->refresh();
    expect($job->state)->toBe('running');
});
