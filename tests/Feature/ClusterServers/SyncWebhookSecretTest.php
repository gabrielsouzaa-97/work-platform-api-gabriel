<?php

declare(strict_types=1);

use App\Http\Livewire\ClusterServers\Create;
use App\Http\Livewire\ClusterServers\Index;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Models\WebhookSecretHistory;
use App\Modules\ClusterServers\Actions\SyncWebhookSecretAction;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshConnectionException;
use App\Modules\Core\Ssh\SshClientInterface;
use Livewire\Livewire;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Returns a minimal RSA PEM accepted by the Create component validator. */
function syncTestPem(): string
{
    return <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIBOQIBAAJBALRiMLAHudeSA1vBr7GvglUMsWq4gyJ8i8N9S0ugRUTTCY7WY8Kb
NcigvTX6fzVJb3MdA19uMWADCpeEnmX9IDcCAwEAAQJAVVkoXoYufY9wz11llgNf
7+JbItfQLpMxTHSJNf6GybdqN8eefIUdAa3UgiF6Mqu1IohlVNKbA7mufGRwxydQ+
FQIhANp6b0w18Fl8b0UW8Y0hqYQZqjEfb0VwV6N5bFFg6h+XAiEAwCol7bAoQK8zyn
SgCDj501n4LGMtYenogSo8bfX60iZu8CID+BgBCZ7DCEcFzqO5LZtD1ZGmmHMdhm
HudW+MPLTqbAiEAqaZdGYv8Hcgt6SGk55/SN8UBfFXB3skew6tZhubeNcCIQCxrn
Y55nNuHvHJGMn0YF275YLo9dE2svP9XY05CJYncfxg==
-----END RSA PRIVATE KEY-----
PEM;
}

function syncSecretHistory(array $attributes, string $secret): WebhookSecretHistory
{
    $history = new WebhookSecretHistory($attributes);
    $history->secret_encrypted = $secret;
    $history->save();

    return $history->fresh();
}
/** Binds a mock SshClientInterface that returns success on run(). */
function mockSshSuccess(): void
{
    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('run')->andReturn(new SshResponse('', '', 0));
    app()->instance(SshClientInterface::class, $mock);
}

/** Binds a mock SshClientInterface that throws SshConnectionException on run(). */
function mockSshFailure(string $message = 'Connection refused'): void
{
    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('run')->andThrow(new SshConnectionException($message));
    app()->instance(SshClientInterface::class, $mock);
}

// ─── a. Create com SSH success ────────────────────────────────────────────────

it('criar cluster com SSH success → status active e redireciona', function () {
    mockSshSuccess();
    $admin = Operator::factory()->admin()->create();

    callCreateSaveWithPem(
        Livewire::actingAs($admin)
            ->test(Create::class)
            ->set('name', 'Cluster SSH OK')
            ->set('ssh_host', '10.0.0.1')
            ->set('ssh_port', 22)
            ->set('ssh_user', 'root'),
        syncTestPem(),
    );

    $cluster = ClusterServer::where('name', 'Cluster SSH OK')->first();
    expect($cluster)->not->toBeNull()
        ->and($cluster->status)->toBe('active');
});

// ─── b. Create com SSH failure ────────────────────────────────────────────────

it('criar cluster com SSH failure → cluster status=error, sem redirect, erro no componente', function () {
    mockSshFailure('Connection refused during sync');
    $admin = Operator::factory()->admin()->create();

    $testable = callCreateSaveWithPem(
        Livewire::actingAs($admin)
            ->test(Create::class)
            ->set('name', 'Cluster SSH Fail')
            ->set('ssh_host', '10.0.0.2')
            ->set('ssh_port', 22)
            ->set('ssh_user', 'root'),
        syncTestPem(),
    );

    expect($testable->instance()->getErrorBag()->has('ssh_private_key'))->toBeTrue();

    $cluster = ClusterServer::where('name', 'Cluster SSH Fail')->first();
    expect($cluster)->not->toBeNull()
        ->and($cluster->status)->toBe('error');
});

it('criar cluster com SSH failure → AuditLog registra cluster_server.secret_sync_failed', function () {
    mockSshFailure('Host unreachable');
    $admin = Operator::factory()->admin()->create();

    callCreateSaveWithPem(
        Livewire::actingAs($admin)
            ->test(Create::class)
            ->set('name', 'Cluster Audit Log Fail')
            ->set('ssh_host', '10.0.0.3')
            ->set('ssh_port', 22)
            ->set('ssh_user', 'root'),
        syncTestPem(),
    );

    $cluster = ClusterServer::where('name', 'Cluster Audit Log Fail')->first();
    expect($cluster)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'cluster_server.secret_sync_failed',
        'resource_type' => 'cluster_server',
        'resource_id' => $cluster->id,
    ]);
});

// ─── c. Rotate com SSH success ────────────────────────────────────────────────

it('rotacionar secret com SSH success → novo secret no DB, SSH chamado', function () {
    mockSshSuccess();
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['webhook_secret_version' => 1]);

    syncSecretHistory([
        'cluster_server_id' => $cluster->id,
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ], 'original-secret');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('rotateSecret', $cluster->id)
        ->assertDispatched('toast', type: 'success');

    $cluster->refresh();
    expect($cluster->webhook_secret_version)->toBe(2);
});

// ─── d. Rotate com SSH failure ────────────────────────────────────────────────

it('rotacionar secret com SSH failure → novo secret no DB, AuditLog registra falha, sem exception', function () {
    mockSshFailure('Timeout during rotate sync');
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['webhook_secret_version' => 1]);

    syncSecretHistory([
        'cluster_server_id' => $cluster->id,
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ], 'secret-before-rotate');

    // Should NOT throw — SSH failure is swallowed on rotate
    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('rotateSecret', $cluster->id)
        ->assertDispatched('toast', type: 'success'); // DB rotation succeeded

    $cluster->refresh();
    expect($cluster->webhook_secret_version)->toBe(2);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'cluster_server.secret_sync_failed',
        'resource_type' => 'cluster_server',
        'resource_id' => $cluster->id,
    ]);
});

// ─── e. SyncWebhookSecretAction: verifica args SSH corretos ──────────────────

it('SyncWebhookSecretAction chama SSH com config set-webhook-secret --payload-stdin e JSON com chave secret', function () {
    $capturedArgs = null;
    $capturedPayload = null;

    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('run')
        ->once()
        ->withArgs(function ($cluster, $cmd, $args, $payload) use (&$capturedArgs, &$capturedPayload) {
            $capturedArgs = $args;
            $capturedPayload = $payload;

            return $cmd === 'nextcloud-manage';
        })
        ->andReturn(new SshResponse('', '', 0));

    $cluster = ClusterServer::factory()->create();
    app()->instance(SshClientInterface::class, $mock);
    $action = app(SyncWebhookSecretAction::class);
    $action->execute($cluster, 'my-plain-secret');

    expect($capturedArgs)->toBe(['config', 'set-webhook-secret', '--payload-stdin']);

    $decoded = json_decode($capturedPayload, true);
    expect($decoded)->toBeArray()
        ->and(array_key_exists('secret', $decoded))->toBeTrue()
        ->and($decoded['secret'])->toBe('my-plain-secret');
});

// ─── f. Secret NUNCA passado como arg CLI ────────────────────────────────────

it('SyncWebhookSecretAction nunca passa o secret como argumento CLI', function () {
    $capturedArgs = null;

    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('run')
        ->once()
        ->withArgs(function ($cluster, $cmd, $args, $payload) use (&$capturedArgs) {
            $capturedArgs = $args;

            return true;
        })
        ->andReturn(new SshResponse('', '', 0));

    $plainSecret = 'super-sensitive-secret-value';
    $cluster = ClusterServer::factory()->create();
    app()->instance(SshClientInterface::class, $mock);
    $action = app(SyncWebhookSecretAction::class);
    $action->execute($cluster, $plainSecret);

    foreach ($capturedArgs as $arg) {
        expect((string) $arg)->not->toContain($plainSecret);
    }
});
