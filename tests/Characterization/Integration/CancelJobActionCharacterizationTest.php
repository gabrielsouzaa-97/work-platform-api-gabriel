<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Agents\Services\AgentUpstreamGateway;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;
use App\Modules\Jobs\Actions\CancelJobAction;
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

function characterizationCancellableJob(string $state = 'running'): Job
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    Customer::firstOrCreate(['slug' => 'char-cancel-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'char-cancel-co.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'char-cancel-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage char-cancel-co _ provision',
        'job_type' => 'provision',
        'state' => $state,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);
}

it('characterizes cancel argv: job {id} cancel --json via nextcloud-manage', function (): void {
    $job = characterizationCancellableJob('queued');
    $operator = Operator::factory()->create();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function (ClusterServer $c, string $cmd, array $args) use ($job): bool {
            return $cmd === 'nextcloud-manage'
                && $args === ['job', $job->job_id, 'cancel', '--json'];
        })
        ->andReturn(new SshResponse(
            stdout: '{"status":"cancelled"}',
            stderr: '',
            exitCode: 0,
            parsedJson: ['status' => 'cancelled'],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    app(CancelJobAction::class)->execute($job, $operator->id);

    expect($job->fresh()->state)->toBe('cancelled');
});

it('characterizes successful cancel persists state cancelled and audit log', function (): void {
    $job = characterizationCancellableJob('running');
    $operator = Operator::factory()->create();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andReturn(new SshResponse(
        stdout: '{"status":"cancelled"}',
        stderr: '',
        exitCode: 0,
        parsedJson: ['status' => 'cancelled'],
    ));

    app()->instance(SshClientInterface::class, $ssh);

    app(CancelJobAction::class)->execute($job, $operator->id);

    $job->refresh();
    expect($job->state)->toBe('cancelled');

    $audit = AuditLog::where('action', 'job.cancel')->where('job_id', $job->job_id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($operator->id)
        ->and($audit->payload['previous_state'])->toBe('running');
});

it('characterizes non-cancellable state throws DomainException without SSH', function (): void {
    $job = characterizationCancellableJob('success');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');

    expect(fn () => app(CancelJobAction::class)->execute($job))
        ->toThrow(DomainException::class, 'cannot be cancelled from state [success]');
});

it('characterizes UpstreamUnavailableException propagates without mutating job state', function (): void {
    $job = characterizationCancellableJob('running');

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshConnectionException('upstream down'));
    app()->instance(SshClientInterface::class, $ssh);

    expect(fn () => app(CancelJobAction::class)->execute($job))
        ->toThrow(UpstreamUnavailableException::class);

    expect($job->fresh()->state)->toBe('running');
    expect(AuditLog::where('action', 'job.cancel')->where('job_id', $job->job_id)->exists())->toBeFalse();
});
