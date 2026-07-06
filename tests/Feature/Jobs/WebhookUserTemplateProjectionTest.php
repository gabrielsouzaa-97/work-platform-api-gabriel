<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\TenantUser;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    RateLimiter::clear('webhook:127.0.0.1');
});

function templateProjectionCluster(): ClusterServer
{
    return ClusterServer::factory()->create([
        'ssh_host' => '127.0.0.1',
        'webhook_secret_encrypted' => 'template-projection-secret',
    ]);
}

function templateProjectionCustomer(string $clusterId, string $slug = 'acme-tpl'): Customer
{
    return Customer::firstOrCreate(['slug' => $slug], [
        'cluster_server_id' => $clusterId,
        'domain' => "{$slug}.example.com",
        'status' => 'active',
    ]);
}

function templateProjectionJob(
    string $clusterId,
    string $customerSlug,
    array $payloadSanitized = [],
): Job {
    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => $customerSlug,
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => "nextcloud-manage {$customerSlug} _ users:create",
        'job_type' => 'users:create',
        'state' => 'running',
        'payload_sanitized' => $payloadSanitized,
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

function templateProjectionFinishedPayload(Job $job, string $state = 'finished'): array
{
    return [
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => $state,
        'finished_at' => now()->toIso8601String(),
        'exit_code' => 0,
    ];
}

it('webhook users:create success projeta user_template_slug de payload_sanitized', function (): void {
    $cluster = templateProjectionCluster();
    $customer = templateProjectionCustomer($cluster->id);
    $job = templateProjectionJob($cluster->id, $customer->slug, payloadSanitized: [
        'args' => ['johndoe'],
        'email' => 'john@acme.com',
        'groups' => ['supervisors'],
        'quota' => '10 GB',
        'user_template_slug' => 'supervisor',
    ]);

    app(WebhookHandler::class)->handle($cluster, templateProjectionFinishedPayload($job));

    $row = TenantUser::where('customer_slug', $customer->slug)
        ->where('username', 'johndoe')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->user_template_slug)->toBe('supervisor')
        ->and($row->email)->toBe('john@acme.com');
});

it('tenant_users projection table exposes user_template_slug column (N43.1)', function (): void {
    expect(Schema::hasColumn('tenant_users', 'user_template_slug'))->toBeTrue();
});

it('webhook users:create re-entregue preserva user_template_slug na projeção', function (): void {
    $cluster = templateProjectionCluster();
    $customer = templateProjectionCustomer($cluster->id, 'acme-tpl-dup');
    $job = templateProjectionJob($cluster->id, $customer->slug, payloadSanitized: [
        'args' => ['bob'],
        'user_template_slug' => 'collaborator',
    ]);

    $handler = app(WebhookHandler::class);
    $payload = templateProjectionFinishedPayload($job);
    $handler->handle($cluster, $payload);
    $handler->handle($cluster, $payload);

    expect(TenantUser::where('customer_slug', $customer->slug)->count())->toBe(1);

    $row = TenantUser::where('customer_slug', $customer->slug)
        ->where('username', 'bob')
        ->first();

    expect($row?->user_template_slug)->toBe('collaborator');
});

it('webhook users:create atualiza user_template_slug em row existente quando payload muda', function (): void {
    $cluster = templateProjectionCluster();
    $customer = templateProjectionCustomer($cluster->id, 'acme-tpl-upd');

    TenantUser::create([
        'id' => Str::uuid()->toString(),
        'customer_slug' => $customer->slug,
        'username' => 'carol',
        'origin' => 'api',
        'user_template_slug' => null,
    ]);

    $job = templateProjectionJob($cluster->id, $customer->slug, payloadSanitized: [
        'args' => ['carol'],
        'email' => 'carol@example.com',
        'user_template_slug' => 'supervisor',
    ]);

    app(WebhookHandler::class)->handle($cluster, templateProjectionFinishedPayload($job));

    $row = TenantUser::where('customer_slug', $customer->slug)
        ->where('username', 'carol')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->user_template_slug)->toBe('supervisor');
});
