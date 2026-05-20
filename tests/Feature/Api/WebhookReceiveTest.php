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

function webhookBody(string $jobId, string $state = 'done', ?string $event = null): string
{
    $payload = [
        'job_id' => $jobId,
        'state' => $state,
        'cmd' => 'provision',
        'client' => 'acme-test',
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
        'ts' => now()->toIso8601String(),
    ];

    if ($event !== null) {
        $payload['event'] = $event;
    }

    return json_encode($payload);
}

function startedWebhookBody(string $jobId): string
{
    // job.started callbacks intentionally omit finished_at/exit_code/duration_ms
    // — the upstream worker emits them only on job.finished. See additive schema
    // expansion in mework360-deployer-scripts (schema_version remains "1").
    return json_encode([
        'event' => 'job.started',
        'schema_version' => '1',
        'job_id' => $jobId,
        'state' => 'running',
        'cmd' => 'provision',
        'client' => 'acme-test',
        'ts' => now()->toIso8601String(),
        'started_at' => now()->toIso8601String(),
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

it('webhook_allowed_ip definido mas request IP diferente → 403 + webhook_ip_not_allowed', function () {
    $cluster = ClusterServer::factory()->create([
        'ssh_host' => 'upstream.example.internal',
        'webhook_allowed_ip' => '10.0.0.1',
    ]);

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

    $response->assertForbidden();
    $response->assertJson(['error' => 'ip_not_allowed']);

    expect(AuditLog::where('action', 'webhook_ip_not_allowed')
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

it('state=finished (worker.sh real impl) → 204 + Job atualizado para success', function () {
    // Regression: before this fix, worker.sh emitting "finished" on exit_code=0 was
    // unknown to StateTranslator and produced 422 → upstream retry fake-204 from
    // dedupe → job stuck in original state. See ISSUE-003 postmortem.
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = webhookBody($job->job_id, state: 'finished');
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->callback_received_at)->not->toBeNull();
});

it('controller falha com 422 NÃO seta dedupe — retry com payload corrigido sucede', function () {
    // Regression: previous middleware persisted the dedupe key BEFORE running the
    // controller, so any 422/4xx from the handler caused the upstream's retry to be
    // silenced with a fake 204, leaving the job permanently stuck. The dedupe must
    // only persist on a fully-processed (status < 300) response.
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $headers = [
        'X-Cluster-Server-Id' => $cluster->id,
    ];

    $badBody = json_encode([
        'job_id' => $job->job_id,
        'state' => 'unknown_state',
        'cmd' => 'provision',
        'client' => 'acme-test',
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
    ]);
    $headers['X-Signature'] = hmacHeader($badBody, $secret);

    $this->postJson('/api/jobs/hook', json_decode($badBody, true), $headers)
        ->assertStatus(422);

    // Dedupe key is scoped per (job_id, event); legacy payloads default to job.finished.
    expect(Cache::has("webhook_processed:{$job->job_id}:job.finished"))->toBeFalse();

    RateLimiter::clear('webhook:127.0.0.1');

    $goodBody = webhookBody($job->job_id, state: 'finished');
    $headers['X-Signature'] = hmacHeader($goodBody, $secret);

    $this->postJson('/api/jobs/hook', json_decode($goodBody, true), $headers)
        ->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect(Cache::has("webhook_processed:{$job->job_id}:job.finished"))->toBeTrue();
});

it('event=job.started → 204 + state=running + started_at preenchido (sem finished_at/exit_code)', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);
    $job->update(['state' => 'queued', 'started_at' => null]);

    $body = startedWebhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('running');
    expect($job->started_at)->not->toBeNull();
    expect($job->finished_at)->toBeNull();
    expect($job->exit_code)->toBeNull();
    expect($job->callback_received_at)->not->toBeNull();

    $audit = AuditLog::where('action', 'webhook_received')->where('job_id', $job->job_id)->first();
    expect($audit)->not->toBeNull();
    expect($audit->payload['event'])->toBe('job.started');
});

it('event=job.started seguido de event=job.finished → ambos 204 (dedupe per evento)', function () {
    // Regression: o dedupe per-job inviabilizaria a sequência started→finished do
    // mesmo job_id porque o segundo callback bateria no cache do primeiro. A
    // chave deve ser composta por (job_id, event) — ver ISSUE-004.
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);
    $job->update(['state' => 'queued', 'started_at' => null]);

    $startedBody = startedWebhookBody($job->job_id);
    $startedSig = hmacHeader($startedBody, $secret);

    $this->postJson('/api/jobs/hook', json_decode($startedBody, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $startedSig,
    ])->assertNoContent();

    RateLimiter::clear('webhook:127.0.0.1');

    $finishedBody = webhookBody($job->job_id, state: 'finished', event: 'job.finished');
    $finishedSig = hmacHeader($finishedBody, $secret);

    $this->postJson('/api/jobs/hook', json_decode($finishedBody, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $finishedSig,
    ])->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->started_at)->not->toBeNull();
    expect($job->finished_at)->not->toBeNull();
    expect($job->exit_code)->toBe(0);

    expect(Cache::has("webhook_processed:{$job->job_id}:job.started"))->toBeTrue();
    expect(Cache::has("webhook_processed:{$job->job_id}:job.finished"))->toBeTrue();
});

it('event=job.started reenviado após restart do worker → 204 idempotente sem regredir started_at', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);
    $job->update(['state' => 'queued', 'started_at' => null]);

    $body = startedWebhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertNoContent();

    $job->refresh();
    $firstStartedAt = $job->started_at;

    RateLimiter::clear('webhook:127.0.0.1');

    // Worker reinicia e reenvia o MESMO evento job.started — o dedupe deve absorver.
    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertNoContent();

    $job->refresh();
    expect($job->started_at->equalTo($firstStartedAt))->toBeTrue();
    expect($job->state)->toBe('running');
});

it('event=job.started chega após job ter terminado (out-of-order) → 204 mas mantém estado terminal', function () {
    // Cenário: webhook job.finished chegou e converteu o job para success; um
    // job.started atrasado (retry tardio do upstream) não pode reverter para running.
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);
    $job->update(['state' => 'success', 'finished_at' => now(), 'exit_code' => 0]);

    $body = startedWebhookBody($job->job_id);
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->exit_code)->toBe(0);
});

it('payload com event desconhecido → 422 invalid_event (sem dedupe persistido)', function () {
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = json_encode([
        'event' => 'job.weird_unknown',
        'schema_version' => '1',
        'job_id' => $job->job_id,
        'state' => 'running',
        'ts' => now()->toIso8601String(),
    ]);
    $sig = hmacHeader($body, $secret);

    $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ])->assertStatus(422)->assertJson(['error' => 'invalid_event']);

    expect(Cache::has("webhook_processed:{$job->job_id}:job.weird_unknown"))->toBeFalse();
    expect(Cache::has("webhook_processed:{$job->job_id}:job.finished"))->toBeFalse();
});

it('payload finished SEM campo event (legacy) → 204 + tratado como job.finished', function () {
    // Backwards compatibility: callbacks vindos de workers antigos (pré expansão
    // aditiva do schema) continuam funcionando. Sem o campo `event`, a API
    // assume `job.finished` e executa o fluxo terminal completo.
    [$cluster, $secret] = makeCluster();
    $job = makeJob($cluster->id);

    $body = webhookBody($job->job_id, state: 'finished'); // sem `event`
    $sig = hmacHeader($body, $secret);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => $sig,
    ]);

    $response->assertNoContent();

    $job->refresh();
    expect($job->state)->toBe('success');
    expect($job->finished_at)->not->toBeNull();
    expect(Cache::has("webhook_processed:{$job->job_id}:job.finished"))->toBeTrue();
});
