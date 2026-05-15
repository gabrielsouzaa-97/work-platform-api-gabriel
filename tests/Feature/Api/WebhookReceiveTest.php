<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\WebhookSecretHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

function makeCluster(string $secret = 'test-secret-plain'): array
{
    $cluster = ClusterServer::factory()->create(['ssh_host' => '127.0.0.1']);

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $secret,
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ]);

    return [$cluster, $secret];
}

function makeJob(string $clusterId): Job
{
    Customer::firstOrCreate(['slug' => 'acme-test'], [
        'cluster_server_id' => $clusterId,
        'domain' => 'acme-test.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'acme-test',
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => 'nextcloud-manage acme-test _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

function webhookBody(string $jobId, string $state = 'done'): string
{
    return json_encode([
        'job_id' => $jobId,
        'state' => $state,
        'cmd' => 'provision',
        'client' => 'acme-test',
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
    ]);
}

function hmacHeader(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

beforeEach(function () {
    RateLimiter::clear('webhook:127.0.0.1');
    Cache::flush();
});

it('HMAC válido + IP whitelisted + finished_at recente → 204 + Job atualizado + AuditLog', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = webhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->callback_received_at)->not->toBeNull();

    expect(AuditLog::where('action', 'webhook_received')->where('job_id', $job->job_id)->exists())->toBeTrue();
});

it('HMAC inválido → 401 + AuditLog com ação webhook_invalid_signature', function () {
    [$cluster] = makeCluster();
    $job = makeJob($cluster->id);

    $body = webhookBody($job->job_id);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => 'sha256=invalidsignature',
    ]);

    $response->assertStatus(401);
    $response->assertJson(['error' => 'invalid_signature']);

    expect(AuditLog::where('action', 'webhook_invalid_signature')
        ->where('resource_id', $cluster->id)
        ->exists())->toBeTrue();
});

it('IP fora do whitelist → 401 + AuditLog com ação webhook_ip_mismatch', function () {
    $cluster = ClusterServer::factory()->create(['ssh_host' => '10.0.0.99']);

    $secret = 'test-secret';
    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $secret,
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ]);

    $job = makeJob($cluster->id);
    $body = webhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertStatus(401);
    $response->assertJson(['error' => 'ip_not_whitelisted']);

    expect(AuditLog::where('action', 'webhook_ip_mismatch')
        ->where('resource_id', $cluster->id)
        ->exists())->toBeTrue();
});

it('finished_at com 2h de atraso → 422 + AuditLog com ação webhook_replay', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $stalePayload = json_encode([
        'job_id' => $job->job_id,
        'state' => 'done',
        'cmd' => 'provision',
        'client' => 'acme-test',
        'exit_code' => 0,
        'finished_at' => now()->subHours(2)->toIso8601String(),
    ]);

    $sig = hmacHeader($stalePayload, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($stalePayload, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertStatus(422);
    $response->assertJson(['error' => 'replay_window_exceeded']);

    expect(AuditLog::where('action', 'webhook_replay')->exists())->toBeTrue();
});

it('multi-secret: HMAC com secret antigo em grace period → 204 aceito', function () {
    $cluster = ClusterServer::factory()->create(['ssh_host' => '127.0.0.1', 'webhook_secret_version' => 2]);

    $oldSecret = 'old-secret-grace';
    $newSecret = 'new-secret-active';

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $oldSecret,
        'version' => 1,
        'valid_from' => now()->subHours(2),
        'valid_until' => now()->addHours(22),
    ]);

    WebhookSecretHistory::create([
        'cluster_server_id' => $cluster->id,
        'secret_encrypted' => $newSecret,
        'version' => 2,
        'valid_from' => now(),
        'valid_until' => null,
    ]);

    $job = makeJob($cluster->id);
    $body = webhookBody($job->job_id);
    $sig = hmacHeader($body, $oldSecret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
});

it('webhook chega duas vezes para o mesmo job_id+state → 204 idempotente', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = webhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $headers = [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ];

    $this->postJson('/api/jobs/hook', json_decode($body, true), $headers)->assertNoContent();

    RateLimiter::clear('webhook:127.0.0.1');

    $body2 = webhookBody($job->job_id);
    $sig2 = hmacHeader($body2, $secret);
    $headers['X-Signature'] = $sig2;

    $this->postJson('/api/jobs/hook', json_decode($body2, true), $headers)->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
});

it('cluster_server_id no header não bate com job.cluster_server_id → 403', function () {
    [$cluster, $secret] = makeCluster();
    [$otherCluster] = makeCluster('other-secret');

    $job = makeJob($otherCluster->id);

    $body = webhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertStatus(403);
});

it('cluster_id via query param (?cluster=) → 204 aceito (caminho real do upstream)', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = webhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    // Upstream sends cluster_id in URL, not as a header
    $response = $this->postJson(
        '/api/jobs/hook?cluster='.$cluster->id,
        json_decode($body, true),
        ['X-Signature' => $sig]
    );

    $response->assertNoContent();
    $job->refresh();
    expect($job->state)->toBe('success');
});

it('payload com state desconhecido → 422', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = json_encode([
        'job_id' => $job->job_id,
        'state' => 'weirdstate',
        'cmd' => 'provision',
        'client' => 'acme-test',
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
    ]);

    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertStatus(422);
});
