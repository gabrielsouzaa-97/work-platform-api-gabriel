<?php

declare(strict_types=1);

/**
 * E2E critical flows — task 8.2
 *
 * Testa os 3 fluxos de persona de ponta a ponta via HTTP, sem SSH real:
 *  - Marina: provisiona customer → webhook → status=active
 *  - Rafael: cancela job em queued → state=cancelled
 *  - Sofia:  reseta quota via OCC sync passthrough → SSH retorna OK
 *
 * Padrão: SshClientInterface mockado via container binding.
 * Webhook simulado com HMAC computado + IP 127.0.0.1 (ssh_host do cluster).
 */

use App\Jobs\ProbeCustomerReadinessJob;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\CustomerReadinessProbe;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

function e2eCluster(string $secret = 'e2e-secret'): ClusterServer
{
    return ClusterServer::factory()->create([
        'ssh_host' => '127.0.0.1',
        'status' => 'active',
        'webhook_secret_encrypted' => $secret,
    ]);
}

function e2eOperator(string $role = 'admin'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function e2eWebhookBody(string $jobId, string $state, string $cmd, string $customerSlug): string
{
    return json_encode([
        'job_id' => $jobId,
        'state' => $state,
        'cmd' => $cmd,
        'client' => $customerSlug,
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
    ]);
}

function e2eHmac(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

beforeEach(function () {
    RateLimiter::clear('webhook:127.0.0.1');
    Cache::flush();
});

// ── Fluxo Marina: provisionar customer → webhook → status=active ──────────────

it('[Marina] provisiona customer via API → webhook done → customer.status=active', function () {
    $marina = e2eOperator('admin');
    $secret = 'marina-secret';
    $cluster = e2eCluster($secret);
    $jobId = Str::uuid()->toString();

    // 1. Mockar SSH: simula resposta assíncrona com job_id + probe user:list
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('create', $args, true))
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    $ssh->shouldReceive('run')
        ->andReturn(new SshResponse(
            stdout: '[]',
            stderr: '',
            exitCode: 0,
            parsedJson: [],
        ));
    $this->app->instance(SshClientInterface::class, $ssh);

    // 2. Marina chama POST /api/customers → provisioning
    $response = $this->actingAs($marina)
        ->postJson('/api/customers', [
            'slug' => 'acme-e2e',
            'cluster_server_id' => $cluster->id,
            'domain' => 'acme-e2e.example.com',
            'admin_email' => 'admin@acme.com',
        ]);

    $response->assertStatus(201);
    $customer = Customer::find('acme-e2e');
    expect($customer)->not->toBeNull();
    expect($customer->status)->toBe('provisioning');

    $job = Job::find($jobId);
    expect($job)->not->toBeNull();
    expect($job->state)->toBe('queued');

    // 3. Upstream envia webhook done → provisioning_finishing + probe enfileirado
    Queue::fake();

    $body = e2eWebhookBody($jobId, 'done', 'provision', 'acme-e2e');
    $sig = e2eHmac($body, $secret);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertNoContent();

    $customer->refresh();
    expect($customer->status)->toBe(CustomerLifecycleStatus::PROVISIONING_FINISHING);

    Queue::assertPushed(ProbeCustomerReadinessJob::class);

    // Sync queue may defer retries via release(); run probe inline to complete the E2E flow.
    (new ProbeCustomerReadinessJob($customer->slug))
        ->handle(app(CustomerReadinessProbe::class));

    $customer->refresh();
    expect($customer->status)->toBe('active');

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->callback_received_at)->not->toBeNull();
});

// ── Fluxo Rafael: cancelar job queued via API ─────────────────────────────────

it('[Rafael] cancela job queued → state=cancelled via API', function () {
    $rafael = e2eOperator('operador');
    $cluster = e2eCluster();

    // Seed: customer + job queued
    Customer::firstOrCreate(['slug' => 'beta-e2e'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'beta-e2e.example.com',
        'status' => 'provisioning',
    ]);

    $jobId = Str::uuid()->toString();
    $job = Job::create([
        'job_id' => $jobId,
        'customer_slug' => 'beta-e2e',
        'cluster_server_id' => $cluster->id,
        'cmd_canonical' => 'provision',
        'job_type' => 'provision',
        'state' => 'queued',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subSeconds(30),
    ]);

    // SSH cancel mockado
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('job', $args, true)
            && in_array('cancel', $args, true))
        ->andReturn(new SshResponse(
            stdout: json_encode(['cancelled' => true]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['cancelled' => true],
        ));
    $this->app->instance(SshClientInterface::class, $ssh);

    // Rafael chama POST /api/queue/{id}/cancel
    $this->actingAs($rafael)
        ->postJson("/api/queue/{$jobId}/cancel")
        ->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('cancelled');
});

// ── Fluxo Sofia: resetar quota via OCC sync passthrough ──────────────────────

it('[Sofia] reseta quota de usuário via OCC passthrough → SSH retorna OK', function () {
    $sofia = e2eOperator('admin');
    $cluster = e2eCluster();

    $customer = Customer::firstOrCreate(['slug' => 'gamma-e2e'], [
        'cluster_server_id' => $cluster->id,
        'domain' => 'gamma-e2e.example.com',
        'status' => 'active',
    ]);

    // SSH sync mockado → retorna OK
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($c, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('user:setting', $args, true)
            && in_array('sofiauser', $args, true)
            && in_array('5GB', $args, true))
        ->andReturn(new SshResponse(
            stdout: json_encode(['result' => 'ok']),
            stderr: '',
            exitCode: 0,
            parsedJson: ['result' => 'ok'],
        ));
    $this->app->instance(SshClientInterface::class, $ssh);

    // Sofia chama PUT /api/customers/{customer}/occ/quota/{username}
    $this->actingAs($sofia)
        ->putJson("/api/customers/{$customer->slug}/occ/quota/sofiauser", ['quota' => '5 GB'])
        ->assertOk();
});
