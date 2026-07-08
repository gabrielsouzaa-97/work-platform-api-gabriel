<?php

declare(strict_types=1);

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\TenantGroup;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Services\TenantGroupProjector;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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

function groupProjectionCluster(): ClusterServer
{
    return ClusterServer::factory()->create([
        'ssh_host' => '127.0.0.1',
        'webhook_secret_encrypted' => 'group-projection-secret',
    ]);
}

function groupProjectionCustomer(string $clusterId, string $slug = 'acme-grp'): Customer
{
    return Customer::firstOrCreate(['slug' => $slug], [
        'cluster_server_id' => $clusterId,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function groupProjectionJob(
    string $clusterId,
    string $customerSlug,
    string $jobType,
    string $state = 'running',
    array $payloadSanitized = [],
): Job {
    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => "nextcloud-manage {$customerSlug} _ {$jobType}",
        'job_type' => $jobType,
        'state' => $state,
        'payload_sanitized' => $payloadSanitized,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

function groupProjectionFinishedPayload(Job $job, string $state = 'finished'): array
{
    return [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => $state,
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ];
}

function groupProjectionHmacHeader(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

it('webhook groups:create success projeta row em tenant_groups com payload_sanitized', function (): void {
    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id);
    $job = groupProjectionJob($cluster->id, $customer->slug, 'groups:create', payloadSanitized: [
        'args' => ['editors'],
        'name' => 'editors',
        'origin' => 'panel',
    ]);

    app(WebhookHandler::class)->handle($cluster, groupProjectionFinishedPayload($job));

    $row = TenantGroup::where('customer_slug', $customer->slug)
        ->where('name', 'editors')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->origin)->toBe('panel');
});

it('webhook groups:delete success remove row da projeção', function (): void {
    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id, 'acme-grp-del');

    TenantGroup::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'name' => 'editors',
        'origin' => 'api',
    ]);

    $job = groupProjectionJob($cluster->id, $customer->slug, 'groups:delete', payloadSanitized: [
        'args' => ['editors'],
        'name' => 'editors',
    ]);

    app(WebhookHandler::class)->handle($cluster, groupProjectionFinishedPayload($job));

    expect(TenantGroup::where('customer_slug', $customer->slug)->where('name', 'editors')->exists())
        ->toBeFalse();
});

it('webhook re-entregue com mesmo estado terminal não duplica row na projeção de grupos', function (): void {
    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id, 'acme-grp-dup');
    $job = groupProjectionJob($cluster->id, $customer->slug, 'groups:create', payloadSanitized: [
        'args' => ['staff'],
        'name' => 'staff',
    ]);

    $handler = app(WebhookHandler::class);
    $payload = groupProjectionFinishedPayload($job);
    $handler->handle($cluster, $payload);
    $handler->handle($cluster, $payload);

    expect(TenantGroup::where('customer_slug', $customer->slug)->count())->toBe(1);
});

it('projector exception de grupos não impede webhook 204 e registra log warning', function (): void {
    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id, 'acme-grp-err');
    $job = groupProjectionJob($cluster->id, $customer->slug, 'groups:create', payloadSanitized: [
        'args' => ['financeiro'],
        'name' => 'financeiro',
    ]);

    $projector = Mockery::mock(TenantGroupProjector::class);
    $projector->shouldReceive('handleTerminalJob')
        ->once()
        ->andThrow(new RuntimeException('group projection failed'));
    app()->instance(TenantGroupProjector::class, $projector);

    Log::shouldReceive('warning')->atLeast()->once();
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();

    $body = json_encode(groupProjectionFinishedPayload($job));
    $sig = groupProjectionHmacHeader($body, 'group-projection-secret');

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();
});

it('webhook deprovision success purga todas as rows de grupos do customer', function (): void {
    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id, 'acme-grp-deprov');
    Customer::where('slug', $customer->slug)->update(['status' => 'active']);

    TenantGroup::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'name' => 'editors',
        'origin' => 'api',
    ]);
    TenantGroup::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'name' => 'staff',
        'origin' => 'panel',
    ]);

    $job = groupProjectionJob($cluster->id, $customer->slug, 'deprovision');

    app(WebhookHandler::class)->handle($cluster, groupProjectionFinishedPayload($job));

    expect(TenantGroup::where('customer_slug', $customer->slug)->count())->toBe(0);
});

it('payload_sanitized de groups:create inclui group name após enqueue', function (): void {
    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id, 'acme-grp-payload');
    $operator = Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    app(LifecycleAsyncAction::class)->execute(
        $customer,
        'groups:create',
        ['editors'],
        null,
        $operator,
        'panel',
    );

    $job = Job::find($jobId);
    expect($job)->not->toBeNull()
        ->and($job->payload_sanitized['name'] ?? null)->toBe('editors')
        ->and($job->payload_sanitized['args'] ?? null)->toBe(['editors'])
        ->and($job->payload_sanitized['origin'] ?? null)->toBe('panel');
});

it('webhook groups:create success preserva propagação Customer.status inalterada', function (): void {
    Queue::fake();

    $cluster = groupProjectionCluster();
    $customer = groupProjectionCustomer($cluster->id, 'acme-grp-reg');
    Customer::where('slug', $customer->slug)->update(['status' => 'active']);

    $job = groupProjectionJob($cluster->id, $customer->slug, 'groups:create', payloadSanitized: [
        'args' => ['team'],
        'name' => 'team',
    ]);

    app(WebhookHandler::class)->handle($cluster, groupProjectionFinishedPayload($job));

    expect(Customer::where('slug', $customer->slug)->value('status'))->toBe('active');
    Queue::assertNotPushed(ProbeCustomerReadinessJob::class);
});
