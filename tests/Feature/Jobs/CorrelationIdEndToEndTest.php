<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function (): void {
    RateLimiter::clear('webhook:127.0.0.1');

    $noop = Mockery::mock(SshClientInterface::class);
    $noop->shouldReceive('run')->andReturn(new SshResponse(
        stdout: '[]',
        stderr: '',
        exitCode: 0,
        parsedJson: [],
    ));
    $this->app->instance(SshClientInterface::class, $noop);
});

function correlationCluster(): array
{
    $secret = 'correlation-test-secret';
    $cluster = ClusterServer::factory()->create([
        'status' => 'active',
        'ssh_host' => '127.0.0.1',
        'webhook_secret_encrypted' => $secret,
    ]);

    return [$cluster, $secret];
}

function correlationHmac(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

it('propagates the same correlation_id from provision dispatch through webhook to audit_logs', function (): void {
    [$cluster, $secret] = correlationCluster();
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $sshMock = Mockery::mock(SshClientInterface::class);
    $sshMock->shouldReceive('runAsync')->once()->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    $sshMock->shouldReceive('run')->andReturn(new SshResponse(
        stdout: '[]',
        stderr: '',
        exitCode: 0,
        parsedJson: [],
    ));
    $this->app->instance(SshClientInterface::class, $sshMock);

    $this->actingAs($operator)->postJson('/api/customers', [
        'slug' => 'corr-provision',
        'cluster_server_id' => $cluster->id,
        'domain' => 'corr-provision.example.com',
    ])->assertStatus(201);

    $job = Job::find($jobId);
    expect($job)->not->toBeNull()
        ->and($job->correlation_id)->not->toBeNull()
        ->and(Str::isUuid($job->correlation_id))->toBeTrue();

    $correlationId = $job->correlation_id;

    $dispatchAudit = AuditLog::where('action', 'provision_initiated')
        ->where('job_id', $jobId)
        ->first();

    expect($dispatchAudit)->not->toBeNull()
        ->and($dispatchAudit->payload['correlation_id'])->toBe($correlationId);

    $body = json_encode([
        'job_id' => $jobId,
        'state' => 'done',
        'cmd' => 'provision',
        'client' => 'corr-provision',
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
        'ts' => now()->toIso8601String(),
    ]);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => correlationHmac($body, $secret),
    ])->assertNoContent();

    $webhookAudit = AuditLog::where('action', 'webhook_received')
        ->where('job_id', $jobId)
        ->first();

    expect($webhookAudit)->not->toBeNull()
        ->and($webhookAudit->payload['correlation_id'])->toBe($correlationId);
});
