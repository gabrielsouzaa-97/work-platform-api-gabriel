<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Str;

function makeCancellableJob(string $state = 'running'): array
{
    $cluster = ClusterServer::factory()->create(['status' => 'active']);

    Customer::firstOrCreate(['slug' => 'cancel-co'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'cancel-co.example.com',
        'status' => 'active',
    ]);

    $job = Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'cancel-co',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'nextcloud-manage cancel-co _ provision',
        'job_type' => 'provision',
        'state' => $state,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(5),
    ]);

    return [$cluster, $job];
}

it('POST /api/queue/{id}/cancel cancela job running → 204 + state=cancelled + AuditLog', function () {
    [$cluster, $job] = makeCancellableJob('running');
    $admin = Operator::factory()->admin()->create();

    $this->mock(SshClientInterface::class, function ($mock) {
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(new SshResponse(
                stdout: '{"status":"cancelled"}',
                stderr: '',
                exitCode: 0,
                parsedJson: ['status' => 'cancelled'],
            ));
    });

    $this->actingAs($admin);

    $response = $this->postJson("/api/queue/{$job->job_id}/cancel");

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('cancelled');

    expect(AuditLog::where('action', 'job.cancel')->where('job_id', $job->job_id)->exists())->toBeTrue();
});

it('POST /api/queue/{id}/cancel em job já finalizado → 422 invalid_state', function () {
    [, $job] = makeCancellableJob('success');
    $admin = Operator::factory()->admin()->create();

    $this->actingAs($admin);

    $response = $this->postJson("/api/queue/{$job->job_id}/cancel");

    $response->assertStatus(422);
    $response->assertJson(['error' => 'invalid_state']);
});

it('POST /api/queue/{id}/cancel com falha SSH → 502 upstream_error', function () {
    [, $job] = makeCancellableJob('running');
    $admin = Operator::factory()->admin()->create();

    $this->mock(SshClientInterface::class, function ($mock) {
        $mock->shouldReceive('run')
            ->once()
            ->andThrow(new SshConnectionException('upstream unreachable'));
    });

    $this->actingAs($admin);

    $response = $this->postJson("/api/queue/{$job->job_id}/cancel");

    $response->assertStatus(502);
    $response->assertJson(['error' => 'upstream_error']);
});

it('POST /api/queue/{id}/cancel sem autenticação → 401', function () {
    [, $job] = makeCancellableJob('running');

    $response = $this->postJson("/api/queue/{$job->job_id}/cancel");

    $response->assertUnauthorized();
});
