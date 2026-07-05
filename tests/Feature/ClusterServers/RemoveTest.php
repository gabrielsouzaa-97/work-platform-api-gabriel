<?php

declare(strict_types=1);

use App\Http\Livewire\ClusterServers\Index;
use App\Mail\WebhookSecretRotatedMail;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\WebhookSecretHistory;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('remove cluster sem customers faz soft-delete e exibe toast de sucesso', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['name' => 'lab-legacy']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openRemoveModal', $cluster->id)
        ->set('confirmInput', 'lab-legacy')
        ->call('removeCluster')
        ->assertDispatched('toast', type: 'success', msg: 'Cluster removido com sucesso.');

    expect(ClusterServer::find($cluster->id))->toBeNull();
    expect(ClusterServer::withTrashed()->find($cluster->id))->not->toBeNull();

    $audit = AuditLog::where('action', 'cluster_server.delete')
        ->where('resource_id', $cluster->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($admin->id);
});

it('remove cluster com customer ativo bloqueia com toast de erro e não deleta', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['name' => 'prod-upstream']);
    Customer::create([
        'slug' => 'tenant-active',
        'cluster_server_id' => $cluster->id,
        'domain' => 'tenant-active.example.com',
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openRemoveModal', $cluster->id)
        ->set('confirmInput', 'prod-upstream')
        ->call('removeCluster')
        ->assertDispatched('toast', type: 'error', msg: 'Não é possível remover: existem customers ativos vinculados.');

    expect(ClusterServer::find($cluster->id))->not->toBeNull();
    expect(AuditLog::where('action', 'cluster_server.delete')->where('resource_id', $cluster->id)->exists())->toBeFalse();
});

it('remove cluster rejeita confirmação com nome incorreto', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['name' => 'exact-name']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openRemoveModal', $cluster->id)
        ->set('confirmInput', 'wrong-name')
        ->call('removeCluster')
        ->assertSet('removeError', 'Nome digitado não confere.');

    expect(ClusterServer::find($cluster->id))->not->toBeNull();
});

it('operador sem manage-cluster-servers recebe 403 ao remover cluster', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);
    $cluster = ClusterServer::factory()->create(['name' => 'forbidden-remove']);

    Livewire::actingAs($operador)
        ->test(Index::class)
        ->call('openRemoveModal', $cluster->id)
        ->assertForbidden();
});

it('testConnection e rotateSecret permanecem funcionais após adicionar remove', function () {
    Mail::fake();

    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('ping')
        ->once()
        ->andReturn(new SshResponse(stdout: 'ok', stderr: '', exitCode: 0));
    $mock->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(stdout: '', stderr: '', exitCode: 0));
    app()->instance(SshClientInterface::class, $mock);

    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['status' => 'inactive', 'webhook_secret_version' => 1]);

    WebhookSecretHistory::createWithSecret([
        'cluster_server_id' => $cluster->id,
        'version' => 1,
        'valid_from' => now()->subHour(),
        'valid_until' => null,
    ], 'rotate-secret');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('testConnection', $cluster->id)
        ->assertDispatched('toast', type: 'success')
        ->call('rotateSecret', $cluster->id)
        ->assertDispatched('toast', type: 'success');

    Mail::assertQueued(WebhookSecretRotatedMail::class);
});
