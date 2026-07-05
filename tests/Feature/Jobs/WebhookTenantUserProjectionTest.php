<?php

declare(strict_types=1);

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\TenantUser;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Services\TenantUserProjector;
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

function projectionCluster(): ClusterServer
{
    return ClusterServer::factory()->create([
        'ssh_host' => '127.0.0.1',
        'webhook_secret_encrypted' => 'projection-secret',
    ]);
}

function projectionCustomer(string $clusterId, string $slug = 'acme-proj'): Customer
{
    return Customer::firstOrCreate(['slug' => $slug], [
        'cluster_server_id' => $clusterId,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function projectionJob(
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

function projectionFinishedPayload(Job $job, string $state = 'finished'): array
{
    return [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => $state,
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ];
}

function projectionHmacHeader(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

it('webhook users:create success projeta row em tenant_users com payload_sanitized', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id);
    $job = projectionJob($cluster->id, $customer->slug, 'users:create', payloadSanitized: [
        'args' => ['johndoe'],
        'email' => 'john@acme.com',
        'groups' => ['editors'],
        'quota' => '5 GB',
    ]);

    app(WebhookHandler::class)->handle($cluster, projectionFinishedPayload($job));

    $row = TenantUser::where('customer_slug', $customer->slug)
        ->where('username', 'johndoe')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->email)->toBe('john@acme.com')
        ->and($row->groups)->toBe(['editors'])
        ->and($row->quota)->toBe('5 GB')
        ->and($row->origin)->toBeIn(['api', 'panel']);
});

it('webhook users:delete success remove row da projeção', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-del');

    TenantUser::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'username' => 'alice',
        'origin' => 'api',
    ]);

    $job = projectionJob($cluster->id, $customer->slug, 'users:delete', payloadSanitized: [
        'args' => ['alice'],
    ]);

    app(WebhookHandler::class)->handle($cluster, projectionFinishedPayload($job));

    expect(TenantUser::where('customer_slug', $customer->slug)->where('username', 'alice')->exists())
        ->toBeFalse();
});

it('webhook provision success projeta admin com origin provision', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-prov');
    Customer::where('slug', $customer->slug)->update(['status' => 'provisioning']);

    $job = projectionJob($cluster->id, $customer->slug, 'provision');

    app(WebhookHandler::class)->handle($cluster, projectionFinishedPayload($job));

    $row = TenantUser::where('customer_slug', $customer->slug)
        ->where('username', 'admin')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->origin)->toBe('provision');
});

it('webhook re-entregue com mesmo estado terminal não duplica row na projeção', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-dup');
    $job = projectionJob($cluster->id, $customer->slug, 'users:create', payloadSanitized: [
        'args' => ['alice'],
        'email' => 'alice@example.com',
    ]);

    $handler = app(WebhookHandler::class);
    $payload = projectionFinishedPayload($job);
    $handler->handle($cluster, $payload);
    $handler->handle($cluster, $payload);

    expect(TenantUser::where('customer_slug', $customer->slug)->count())->toBe(1);
});

it('projector exception não impede webhook 204 e registra log warning', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-proj-err');
    $job = projectionJob($cluster->id, $customer->slug, 'users:create', payloadSanitized: [
        'args' => ['bob'],
    ]);

    if (class_exists(TenantUserProjector::class)) {
        $projector = Mockery::mock(TenantUserProjector::class);
        $projector->shouldReceive('handleTerminalJob')
            ->once()
            ->andThrow(new RuntimeException('projection failed'));
        app()->instance(TenantUserProjector::class, $projector);
    }

    Log::shouldReceive('warning')->once();
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();

    $body = json_encode(projectionFinishedPayload($job));
    $sig = projectionHmacHeader($body, 'projection-secret');

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();
});

it('webhook deprovision success purga todas as rows do customer na projeção', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-deprov');
    Customer::where('slug', $customer->slug)->update(['status' => 'active']);

    TenantUser::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'username' => 'alice',
        'origin' => 'api',
    ]);
    TenantUser::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'username' => 'bob',
        'origin' => 'panel',
    ]);

    $job = projectionJob($cluster->id, $customer->slug, 'deprovision');

    app(WebhookHandler::class)->handle($cluster, projectionFinishedPayload($job));

    expect(TenantUser::where('customer_slug', $customer->slug)->count())->toBe(0);
});

it('webhook users:create success preserva propagação Customer.status e ProbeCustomerReadinessJob', function (): void {
    Queue::fake();

    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-reg');
    Customer::where('slug', $customer->slug)->update(['status' => 'provisioning']);

    $job = projectionJob($cluster->id, $customer->slug, 'provision');

    app(WebhookHandler::class)->handle($cluster, projectionFinishedPayload($job));

    expect(Customer::where('slug', $customer->slug)->value('status'))
        ->toBe('provisioning_finishing');

    Queue::assertPushed(ProbeCustomerReadinessJob::class, fn (ProbeCustomerReadinessJob $probe) => $probe->customerSlug === $customer->slug);
});

it('payload_sanitized de users:create não contém password após enqueue', function (): void {
    $cluster = projectionCluster();
    $customer = projectionCustomer($cluster->id, 'acme-pwd');
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
        'users:create',
        ['secureuser'],
        [
            'password' => 'Secret123!',
            'email' => 'secure@example.com',
            'groups' => ['editors'],
            'quota' => '10 GB',
        ],
        $operator,
    );

    $job = Job::find($jobId);
    expect($job)->not->toBeNull()
        ->and($job->payload_sanitized)->not->toHaveKey('password')
        ->and(collect($job->payload_sanitized)->flatten()->doesntContain('Secret123!'))->toBeTrue()
        ->and($job->payload_sanitized['email'] ?? null)->toBe('secure@example.com')
        ->and($job->payload_sanitized['groups'] ?? null)->toBe(['editors'])
        ->and($job->payload_sanitized['quota'] ?? null)->toBe('10 GB');
});
