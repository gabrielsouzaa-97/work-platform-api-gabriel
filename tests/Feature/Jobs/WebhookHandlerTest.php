<?php

declare(strict_types=1);

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Direct service-level tests for WebhookHandler — bypasses the HTTP pipeline so
 * domain branches (started vs finished, out-of-order guards, customer status
 * propagation) can be exercised in isolation from middleware concerns.
 *
 * A no-op SshClientInterface mock is bound globally for this suite so that the
 * JobLogFetcher dependency added in F6.3 does not attempt real SSH connections
 * (which would add 3s of retry-sleep per test).
 */
beforeEach(function () {
    $noop = Mockery::mock(SshClientInterface::class);
    $noop->shouldReceive('run')->andReturn(new SshResponse(
        stdout: '[]',
        stderr: '',
        exitCode: 0,
        parsedJson: [],
    ));
    $this->app->instance(SshClientInterface::class, $noop);
});

function handlerCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['ssh_host' => '127.0.0.1']);
}

function handlerJob(string $clusterId, string $state = 'queued', string $jobType = 'provision'): Job
{
    Customer::firstOrCreate(['slug' => 'acme-handler'], [
        'cluster_server_id' => $clusterId,
        'domain' => 'acme-handler.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'acme-handler',
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => "nextcloud-manage acme-handler _ {$jobType}",
        'job_type' => $jobType,
        'state' => $state,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

it('job.started: muda state para running e preenche started_at, sem mexer em finished_at/exit_code', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'queued');

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
        'started_at' => now()->toIso8601String(),
    ]);

    $job->refresh();
    expect($job->state)->toBe('running')
        ->and($job->started_at)->not->toBeNull()
        ->and($job->finished_at)->toBeNull()
        ->and($job->exit_code)->toBeNull()
        ->and($job->callback_received_at)->not->toBeNull();
});

it('job.started: não propaga customer.status (transição intermediária)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'queued');

    Customer::where('slug', 'acme-handler')->update(['status' => 'provisioning']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
    ]);

    expect(Customer::where('slug', 'acme-handler')->value('status'))->toBe('provisioning');
});

it('job.finished com state=success em provision → propaga customer.status=provisioning_finishing e enfileira probe', function (): void {
    Queue::fake();

    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running');
    Customer::where('slug', 'acme-handler')->update(['status' => 'provisioning']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    $job->refresh();
    expect($job->state)->toBe('success')
        ->and($job->exit_code)->toBe(0)
        ->and($job->finished_at)->not->toBeNull();

    expect(Customer::where('slug', 'acme-handler')->value('status'))->toBe('provisioning_finishing');

    Queue::assertPushed(ProbeCustomerReadinessJob::class, fn (ProbeCustomerReadinessJob $probeJob) => $probeJob->customerSlug === 'acme-handler');
});

it('job.finished duplicado não re-dispatcha ProbeCustomerReadinessJob', function (): void {
    Queue::fake();

    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running');
    Customer::where('slug', 'acme-handler')->update(['status' => 'provisioning']);

    $payload = [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ];

    $handler = app(WebhookHandler::class);
    $handler->handle($cluster, $payload);
    $handler->handle($cluster, $payload);

    Queue::assertPushed(ProbeCustomerReadinessJob::class, 1);
});

it('job.finished com state=failed em deprovision → customer.status permanece (sem mapping)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running', jobType: 'deprovision');
    Customer::where('slug', 'acme-handler')->update(['status' => 'active']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'failed',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 1,
    ]);

    expect(Customer::where('slug', 'acme-handler')->value('status'))->toBe('active');
});

it('job.started após terminal: não regride o estado (out-of-order guard)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'success');
    $job->update(['finished_at' => now(), 'exit_code' => 0]);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
        'started_at' => now()->toIso8601String(),
    ]);

    $job->refresh();
    expect($job->state)->toBe('success')
        ->and($job->exit_code)->toBe(0);
});

it('job.started reaplicado quando started_at já existe é no-op (não duplica AuditLog)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'queued');

    $payload = [
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
        'started_at' => now()->toIso8601String(),
    ];

    app(WebhookHandler::class)->handle($cluster, $payload);
    $firstAuditCount = AuditLog::where('job_id', $job->job_id)->count();

    app(WebhookHandler::class)->handle($cluster, $payload);
    $secondAuditCount = AuditLog::where('job_id', $job->job_id)->count();

    expect($firstAuditCount)->toBe(1)
        ->and($secondAuditCount)->toBe(1);
});

it('job não encontrado lança ModelNotFoundException (controller converte em 404)', function (): void {
    $cluster = handlerCluster();

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => '00000000-0000-0000-0000-000000000000',
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);
})->throws(ModelNotFoundException::class);

it('job pertence a outro cluster lança DomainException (controller converte em 403)', function (): void {
    $clusterA = handlerCluster();
    $clusterB = handlerCluster();
    $job = handlerJob($clusterA->id, state: 'running');

    app(WebhookHandler::class)->handle($clusterB, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);
})->throws(DomainException::class);

it('state desconhecido lança UnknownStateException (controller converte em 422)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running');

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'galactic-state',
        'finished_at' => now()->toIso8601String(),
    ]);
})->throws(UnknownStateException::class);

it('AuditLog.payload registra event + duration_ms para forense', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running');

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
        'duration_ms' => 12345,
    ]);

    $audit = AuditLog::where('job_id', $job->job_id)
        ->where('action', 'webhook_received')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->payload['event'])->toBe('job.finished')
        ->and($audit->payload['duration_ms'])->toBe(12345)
        ->and($audit->payload['exit_code'])->toBe(0);
});

// ── N47 / ISSUE-061: provision failure → failed visível com failure_reason ─────

it('job.finished provision failed → customer failed com failure_reason (não soft-deletado)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running', jobType: 'provision');
    Customer::where('slug', 'acme-handler')->update(['status' => 'provisioning']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'failed',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 1,
        'summary' => 'provision script exited with error',
    ]);

    $customer = Customer::where('slug', 'acme-handler')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->status)->toBe('failed')
        ->and($customer->failure_reason)->not->toBeNull()
        ->and($customer->deleted_at)->toBeNull();

    expect(AuditLog::where('job_id', $job->job_id)->where('action', 'webhook_received')->exists())->toBeTrue();
});

it('job.finished provision cancelled → customer failed com failure_reason', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running', jobType: 'provision');
    Customer::where('slug', 'acme-handler')->update(['status' => 'provisioning']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'cancelled',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 130,
    ]);

    $customer = Customer::where('slug', 'acme-handler')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->status)->toBe('failed')
        ->and($customer->failure_reason)->not->toBeNull()
        ->and($customer->deleted_at)->toBeNull();
});

it('job.finished deprovision success → customer soft-deletado (slug liberado)', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running', jobType: 'deprovision');
    Customer::where('slug', 'acme-handler')->update(['status' => 'active']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    expect(Customer::where('slug', 'acme-handler')->exists())->toBeFalse()
        ->and(Customer::withTrashed()->where('slug', 'acme-handler')->whereNotNull('deleted_at')->exists())->toBeTrue()
        ->and(Customer::withTrashed()->where('slug', 'acme-handler')->value('status'))->toBe('removed');
});

it('job.finished deprovision success em customer failed → soft-deletado e slug reutilizável', function (): void {
    $cluster = handlerCluster();
    $job = handlerJob($cluster->id, state: 'running', jobType: 'deprovision');
    Customer::where('slug', 'acme-handler')->update(['status' => 'failed']);

    app(WebhookHandler::class)->handle($cluster, [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);

    expect(Customer::where('slug', 'acme-handler')->exists())->toBeFalse()
        ->and(Customer::withTrashed()->where('slug', 'acme-handler')->whereNotNull('deleted_at')->exists())->toBeTrue();

    $ghost = Customer::withTrashed()->where('slug', 'acme-handler')->firstOrFail();
    $ghost->restore();
    $ghost->update([
        'cluster_server_id' => $cluster->id,
        'domain' => 'acme-handler.example.com',
        'status' => 'provisioning',
    ]);

    expect($ghost->fresh()->exists)->toBeTrue()
        ->and($ghost->fresh()->deleted_at)->toBeNull();
});
