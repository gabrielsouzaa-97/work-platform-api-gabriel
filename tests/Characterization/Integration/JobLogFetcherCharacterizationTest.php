<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Jobs\Exceptions\JobLogFetchException;
use App\Modules\Jobs\Services\JobLogFetcher;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    config(['services.ssh.log_fetch_timeout_seconds' => 20]);

    $gateway = Mockery::mock(AgentUpstreamGateway::class);
    $gateway->shouldNotReceive('run');
    $gateway->shouldNotReceive('runAsync');
    app()->instance(AgentUpstreamGateway::class, $gateway);
});

function characterizationFetcherJob(): array
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    Customer::firstOrCreate(['slug' => 'char-fetcher-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-fetcher-co.example.com',
        'status' => 'active',
    ]);

    $job = Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'char-fetcher-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage char-fetcher-co _ user_create',
        'job_type' => 'user_create',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);

    return [$cluster, $job];
}

function characterizationJobIntrospectionArgs(array $args, string $jobId, string $subcommand): bool
{
    return $args === ['job', $jobId, $subcommand, '--json'];
}

it('characterizes primary fetch argv: job {id} logs --json with log_fetch timeout', function (): void {
    [$cluster, $job] = characterizationFetcherJob();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args, ?string $stdin, int $timeout) use ($job): bool {
            return $cmd === 'nextcloud-manage'
                && characterizationJobIntrospectionArgs($args, $job->job_id, 'logs')
                && $stdin === null
                && $timeout === 20;
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['Line A']),
            stderr: '',
            exitCode: 0,
            parsedJson: ['Line A'],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe(['Line A']);
});

it('characterizes exit 99 triggers fallback argv: job {id} status --json', function (): void {
    [$cluster, $job] = characterizationFetcherJob();

    $statusPayload = ['data' => ['summary' => ['Fallback line']]];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => characterizationJobIntrospectionArgs($args, $job->job_id, 'logs'))
        ->andReturn(new SshResponse(stdout: '', stderr: 'not implemented', exitCode: 99));
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => characterizationJobIntrospectionArgs($args, $job->job_id, 'status'))
        ->andReturn(new SshResponse(
            stdout: json_encode($statusPayload),
            stderr: '',
            exitCode: 0,
            parsedJson: $statusPayload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $lines = app(JobLogFetcher::class)->fetch($job, $cluster);

    expect($lines)->toBe(['Fallback line']);
});

it('characterizes upstream transport failure wraps as JobLogFetchException', function (): void {
    [$cluster, $job] = characterizationFetcherJob();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshConnectionException('SSH unreachable'));
    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(JobLogFetcher::class)->fetch($job, $cluster))
        ->toThrow(JobLogFetchException::class, 'SSH unreachable');
});

it('characterizes upstream remote failure wraps as JobLogFetchException', function (): void {
    [$cluster, $job] = characterizationFetcherJob();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->andThrow(new SshRemoteException('remote fail', remoteExitCode: 5));
    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(JobLogFetcher::class)->fetch($job, $cluster))
        ->toThrow(JobLogFetchException::class);
});

it('characterizes idempotent fetch returns empty array when summary already set', function (): void {
    [$cluster, $job] = characterizationFetcherJob();
    $job->update(['summary' => ['existing']]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    expect(app(JobLogFetcher::class)->fetch($job, $cluster))->toBe([]);
});
