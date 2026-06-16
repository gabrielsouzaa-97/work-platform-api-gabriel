<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Focused tests for the VerifyWebhookHmac middleware. Complements the end-to-end
 * coverage in tests/Feature/Api/WebhookReceiveTest.php by exercising pipeline-
 * specific concerns (rate limit, cluster_id resolution, event enum guard,
 * dedupe key composition) without making assertions on the controller's output.
 */
function middlewareCluster(string $secret = 'mw-secret'): array
{
    $cluster = ClusterServer::factory()->create([
        'ssh_host' => '127.0.0.1',
        'webhook_secret_encrypted' => $secret,
    ]);

    return [$cluster, $secret];
}

function middlewareJob(string $clusterId): Job
{
    Customer::firstOrCreate(['slug' => 'acme-mw'], [
        'cluster_server_id' => $clusterId,
        'domain' => 'acme-mw.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'acme-mw',
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => 'nextcloud-manage acme-mw _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

function middlewareSig(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

beforeEach(function (): void {
    RateLimiter::clear('webhook:127.0.0.1');
    Cache::flush();
});

it('sem cluster id (nem query nem header) → 400 invalid_cluster_id', function (): void {
    $body = json_encode(['job_id' => 'abc', 'state' => 'finished']);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Signature' => 'sha256=irrelevant',
    ]);

    $response->assertStatus(400)->assertJson(['error' => 'invalid_cluster_id']);
});

it('cluster id não-UUID na query → 400 invalid_cluster_id', function (): void {
    $body = json_encode(['job_id' => 'abc', 'state' => 'finished']);

    $response = $this->postJson('/api/jobs/hook?cluster=not-a-uuid', json_decode($body, true), [
        'X-Signature' => 'sha256=irrelevant',
    ]);

    $response->assertStatus(400)->assertJson(['error' => 'invalid_cluster_id']);
});

it('cluster id UUID inexistente → 401 unknown_cluster + AuditLog', function (): void {
    $body = json_encode(['job_id' => 'abc', 'state' => 'finished']);
    $unknownClusterId = (string) Str::uuid();

    $response = $this->postJson('/api/jobs/hook?cluster='.$unknownClusterId, json_decode($body, true), [
        'X-Signature' => 'sha256=irrelevant',
    ]);

    $response->assertStatus(401)->assertJson(['error' => 'unknown_cluster']);

    expect(AuditLog::where('action', 'webhook_unknown_cluster')->where('resource_id', $unknownClusterId)->exists())
        ->toBeTrue();
});

it('rate limit atinge limiar → 429 rate_limit', function (): void {
    config(['services.webhook.rate_limit_per_minute' => 3]);
    [$cluster, $secret] = middlewareCluster();
    $job = middlewareJob($cluster->id);

    $body = json_encode([
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'ts' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);
    $sig = middlewareSig($body, $secret);
    $headers = ['X-Cluster-Server-Id' => $cluster->id, 'X-Signature' => $sig];

    // Esgota o limite (3 requisições válidas).
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/jobs/hook', json_decode($body, true), $headers);
    }

    $blocked = $this->postJson('/api/jobs/hook', json_decode($body, true), $headers);
    $blocked->assertStatus(429)->assertJson(['error' => 'rate_limit']);
});

it('payload sem campos obrigatórios (job_id, state) → 422 invalid_payload', function (): void {
    [$cluster, $secret] = middlewareCluster();
    $body = json_encode(['ts' => now()->toIso8601String()]); // sem job_id nem state
    $sig = middlewareSig($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'invalid_payload']);
});

it('event desconhecido é rejeitado ANTES do dedupe (cache vazio)', function (): void {
    [$cluster, $secret] = middlewareCluster();
    $job = middlewareJob($cluster->id);

    $body = json_encode([
        'event' => 'job.spoof',
        'job_id' => $job->job_id,
        'state' => 'running',
        'ts' => now()->toIso8601String(),
    ]);
    $sig = middlewareSig($body, $secret);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertStatus(422)->assertJson(['error' => 'invalid_event']);

    // Nenhuma chave de dedupe deve ser criada para eventos rejeitados.
    expect(Cache::has("webhook_processed:{$job->job_id}:job.spoof"))->toBeFalse();
    expect(Cache::has("webhook_processed:{$job->job_id}:job.started"))->toBeFalse();
    expect(Cache::has("webhook_processed:{$job->job_id}:job.finished"))->toBeFalse();
});

it('replay window usa ts (não finished_at) como âncora — payload de job.started com ts antigo é rejeitado', function (): void {
    [$cluster, $secret] = middlewareCluster();
    $job = middlewareJob($cluster->id);

    $body = json_encode([
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
        'ts' => now()->subHours(3)->toIso8601String(),
        'started_at' => now()->subHours(3)->toIso8601String(),
    ]);
    $sig = middlewareSig($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'replay_window_exceeded']);
});

it('APP_ENV=local → payload válido é logado em Log::debug com cluster, ip, event e payload', function (): void {
    app()->detectEnvironment(fn () => 'local');
    Log::spy();

    [$cluster, $secret] = middlewareCluster();
    $job = middlewareJob($cluster->id);

    $body = json_encode([
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'ts' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);
    $sig = middlewareSig($body, $secret);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertNoContent();

    Log::shouldHaveReceived('debug')->withArgs(function (string $message, array $context) use ($cluster, $job): bool {
        return $message === 'webhook.payload_received'
            && $context['cluster_server_id'] === $cluster->id
            && $context['event'] === 'job.finished'
            && ($context['payload']['job_id'] ?? null) === $job->job_id
            && ($context['payload']['state'] ?? null) === 'finished'
            && array_key_exists('ip', $context);
    })->once();
});

it('APP_ENV=testing (não-local) → payload válido NÃO é logado em Log::debug', function (): void {
    app()->detectEnvironment(fn () => 'testing');
    Log::spy();

    [$cluster, $secret] = middlewareCluster();
    $job = middlewareJob($cluster->id);

    $body = json_encode([
        'event' => 'job.finished',
        'job_id' => $job->job_id,
        'state' => 'finished',
        'finished_at' => now()->toIso8601String(),
        'ts' => now()->toIso8601String(),
        'exit_code' => 0,
    ]);
    $sig = middlewareSig($body, $secret);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertNoContent();

    Log::shouldNotHaveReceived('debug', ['webhook.payload_received']);
});

it('dedupe key inclui event — segunda entrega de mesmo (job_id, event) responde 204 sem invocar controller', function (): void {
    [$cluster, $secret] = middlewareCluster();
    $job = middlewareJob($cluster->id);

    $body = json_encode([
        'event' => 'job.started',
        'job_id' => $job->job_id,
        'state' => 'running',
        'ts' => now()->toIso8601String(),
        'started_at' => now()->toIso8601String(),
    ]);
    $sig = middlewareSig($body, $secret);
    $headers = ['X-Cluster-Server-Id' => $cluster->id, 'X-Signature' => $sig];

    $this->postJson('/api/jobs/hook', json_decode($body, true), $headers)->assertNoContent();
    RateLimiter::clear('webhook:127.0.0.1');

    // Segundo POST idêntico — middleware bate no cache e responde 204 imediato.
    $this->postJson('/api/jobs/hook', json_decode($body, true), $headers)->assertNoContent();

    expect(AuditLog::where('action', 'webhook_replay_duplicate')
        ->where('resource_id', $cluster->id)->exists())->toBeTrue();
});
