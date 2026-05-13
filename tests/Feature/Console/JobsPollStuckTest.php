<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function makePollCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeStuckJob(ClusterServer $cluster): Job
{
    Customer::firstOrCreate(['slug' => 'poll-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'poll-co.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'poll-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage poll-co _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

it('job running há 90s sem callback → polling chama SSH e atualiza para success', function () {
    $cluster = makePollCluster();
    $job = makeStuckJob($cluster);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['state' => 'done', 'exit_code' => 0, 'finished_at' => now()->toIso8601String()]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['state' => 'done', 'exit_code' => 0, 'finished_at' => now()->toIso8601String()],
        ));

    $this->app->instance(SshClientInterface::class, $sshMock);

    $this->artisan('jobs:poll-stuck')->assertSuccessful();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->last_poll_at)->not->toBeNull();
    expect(AuditLog::where('action', 'job_polled')->where('job_id', $job->job_id)->exists())->toBeTrue();
});

it('cluster unreachable → polling pula esse cluster', function () {
    $offlineCluster = ClusterServer::factory()->create(['status' => 'unreachable']);
    $activeCluster = makePollCluster();

    Customer::firstOrCreate(['slug' => 'poll-offline'], [
        'cluster_server_id' => $offlineCluster->id,
        'domain' => 'poll-offline.example.com',
        'status' => 'active',
    ]);

    Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'poll-offline',
        'cluster_server_id' => $offlineCluster->id,
        'cmd_canonical' => 'provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldNotReceive('run');
    $this->app->instance(SshClientInterface::class, $sshMock);

    $this->artisan('jobs:poll-stuck')->assertSuccessful();
});

it('SSH falha no polling → log warning + continua sem exception', function () {
    $cluster = makePollCluster();
    $job = makeStuckJob($cluster);

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('run')->once()->andThrow(new SshConnectionException('timeout'));
    $this->app->instance(SshClientInterface::class, $sshMock);

    $this->artisan('jobs:poll-stuck')->assertSuccessful();

    $job->refresh();
    expect($job->state)->toBe('running');
});
