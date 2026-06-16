<?php

declare(strict_types=1);

use App\Http\Livewire\Settings\WebhookIpAllowlist;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\WebhookSecretHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

function webhookAllowlist_makeCluster(?string $webhookAllowedIp = null, string $sshHost = 'upstream.internal'): ClusterServer
{
    return ClusterServer::factory()->create([
        'ssh_host' => $sshHost,
        'webhook_allowed_ip' => $webhookAllowedIp,
    ]);
}

function webhookAllowlist_setupSecret(ClusterServer $cluster, string $secret = 'test-secret-plain'): void
{
    WebhookSecretHistory::where('cluster_server_id', $cluster->id)->delete();
    $cluster->webhook_secret_encrypted = $secret;
    $cluster->save();

    WebhookSecretHistory::createWithSecret([
        'cluster_server_id' => $cluster->id,
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ], $secret);
}

function webhookAllowlist_makeJob(string $clusterId): Job
{
    Customer::firstOrCreate(['slug' => 'acme-whitelist-test'], [
        'cluster_server_id' => $clusterId,
        'domain' => 'acme-whitelist-test.example.com',
        'status' => 'active',
    ]);

    return Job::create([
        'job_id' => Str::uuid()->toString(),
        'customer_slug' => 'acme-whitelist-test',
        'cluster_server_id' => $clusterId,
        'cmd_canonical' => 'nextcloud-manage acme-whitelist-test _ provision',
        'job_type' => 'provision',
        'state' => 'running',
        'idempotency_key' => Str::uuid()->toString(),
        'queued_at' => now()->subMinutes(2),
    ]);
}

function webhookAllowlist_body(string $jobId): string
{
    return json_encode([
        'job_id' => $jobId,
        'state' => 'done',
        'cmd' => 'provision',
        'client' => 'acme-whitelist-test',
        'exit_code' => 0,
        'finished_at' => now()->toIso8601String(),
    ]);
}

function webhookAllowlist_hmac(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

beforeEach(function () {
    RateLimiter::clear('webhook:127.0.0.1');
    Cache::flush();
});

it('admin salva IP válido para cluster → persiste e registra AuditLog', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = webhookAllowlist_makeCluster(null);

    Livewire::actingAs($admin)
        ->test(WebhookIpAllowlist::class)
        ->set(sprintf('allowedIps.%s', $cluster->id), '203.0.113.42')
        ->call('save')
        ->assertHasNoErrors();

    $cluster->refresh();
    expect($cluster->webhook_allowed_ip)->toBe('203.0.113.42');

    expect(AuditLog::query()
        ->where('action', 'cluster_server.webhook_ip_updated')
        ->where('resource_id', $cluster->id)
        ->exists())->toBeTrue();
});

it('webhook com webhook_allowed_ip configurado mas IP incorreto → 403 ip_not_allowed', function (): void {
    $secret = 'shared-secret-ip-test';
    $cluster = webhookAllowlist_makeCluster('198.51.100.99');
    webhookAllowlist_setupSecret($cluster, $secret);
    $job = webhookAllowlist_makeJob($cluster->id);
    $body = webhookAllowlist_body($job->job_id);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => webhookAllowlist_hmac($body, $secret),
    ]);

    $response->assertForbidden();
    $response->assertJson(['error' => 'ip_not_allowed']);
});

it('webhook com IP correto segundo webhook_allowed_ip → 204', function (): void {
    $secret = 'shared-secret-ip-ok';
    $cluster = webhookAllowlist_makeCluster('127.0.0.1');
    webhookAllowlist_setupSecret($cluster, $secret);
    $job = webhookAllowlist_makeJob($cluster->id);
    $body = webhookAllowlist_body($job->job_id);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => webhookAllowlist_hmac($body, $secret),
    ]);

    $response->assertNoContent();
});

it('cluster sem webhook_allowed_ip aceita requisição de qualquer IP com HMAC válido', function (): void {
    $secret = 'open-ip-secret';
    $cluster = webhookAllowlist_makeCluster(null, '10.254.254.254');
    webhookAllowlist_setupSecret($cluster, $secret);
    $job = webhookAllowlist_makeJob($cluster->id);
    $body = webhookAllowlist_body($job->job_id);

    $response = $this->postJson('/api/jobs/hook', json_decode($body, true), [
        'X-Cluster-Server-Id' => $cluster->id,
        'X-Signature' => webhookAllowlist_hmac($body, $secret),
    ]);

    $response->assertNoContent();
});

it('admin tenta salvar IP inválido → assertHasErrors e não persiste (QA-001)', function (): void {
    $admin = Operator::factory()->admin()->create();
    $cluster = webhookAllowlist_makeCluster(null);

    Livewire::actingAs($admin)
        ->test(WebhookIpAllowlist::class)
        ->set(sprintf('allowedIps.%s', $cluster->id), '999.999.999.999')
        ->call('save')
        ->assertHasErrors([sprintf('allowedIps.%s', $cluster->id)]);

    $cluster->refresh();
    expect($cluster->webhook_allowed_ip)->toBeNull();
});
