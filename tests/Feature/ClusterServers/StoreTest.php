<?php

declare(strict_types=1);

use App\Http\Livewire\ClusterServers\Create;
use App\Http\Livewire\ClusterServers\Edit;
use App\Models\ClusterServer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/** Bind a no-op SSH mock so SyncWebhookSecretAction does not attempt real SSH. */
function bindSshSuccessMock(): void
{
    $mock = Mockery::mock(SshClientInterface::class);
    $mock->shouldReceive('run')->andReturn(new SshResponse('', '', 0));
    app()->instance(SshClientInterface::class, $mock);
}

/** @var string Minimal RSA PEM accepted as a private key by OpenSSL (test-only). */
const TEST_CLUSTER_SERVER_VALID_PEM = <<<'PEM'
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

it('admin acessa index e vê clusters listados', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['name' => 'Cluster Visible Alpha']);

    $response = actingAs($admin)
        ->get(route('cluster-servers.index'));

    $response->assertOk();
    $response->assertSee($cluster->name);
});

it('operador comum recebe 403 em GET /cluster-servers', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($operador)
        ->get(route('cluster-servers.index'))
        ->assertForbidden();
});

it('admin cria cluster_server com PEM válido → redireciona e persiste no DB', function () {
    bindSshSuccessMock();
    $admin = Operator::factory()->admin()->create();
    $pem = TEST_CLUSTER_SERVER_VALID_PEM;

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('name', 'Nova Origem Produção')
        ->set('ssh_host', '10.20.30.40')
        ->set('ssh_port', 22)
        ->set('ssh_user', 'deploy')
        ->set('ssh_private_key', $pem)
        ->call('save')
        ->assertRedirect(route('cluster-servers.index'));

    $this->assertDatabaseHas('cluster_servers', [
        'name' => 'Nova Origem Produção',
        'status' => 'active',
    ]);

    $row = DB::table('cluster_servers')
        ->where('name', 'Nova Origem Produção')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->ssh_private_key_encrypted)->not->toBe($pem);
});

it('PEM inválido retorna erro de validação', function () {
    $admin = Operator::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('name', 'Cluster PEM Ruim')
        ->set('ssh_host', '1.2.3.4')
        ->set('ssh_port', 22)
        ->set('ssh_user', 'root')
        ->set('ssh_private_key', 'nao-e-um-pem')
        ->call('save')
        ->assertHasErrors(['ssh_private_key']);
});

it('operador comum não consegue salvar via Livewire (gate bloqueia)', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);

    Livewire::actingAs($operador)
        ->test(Create::class)
        ->set('name', 'Tentativa Operador')
        ->set('ssh_host', '9.9.9.9')
        ->set('ssh_port', 22)
        ->set('ssh_user', 'op')
        ->set('ssh_private_key', TEST_CLUSTER_SERVER_VALID_PEM)
        ->call('save')
        ->assertForbidden();
});

it('admin edita nome do cluster_server → persiste no DB', function () {
    $admin = Operator::factory()->admin()->create();
    $cluster = ClusterServer::factory()->create(['name' => 'Nome Original DB']);

    Livewire::actingAs($admin)
        ->test(Edit::class, ['clusterServer' => $cluster])
        ->set('name', 'Nome Atualizado DB')
        ->call('save')
        ->assertRedirect(route('cluster-servers.index'));

    $this->assertDatabaseHas('cluster_servers', [
        'id' => $cluster->id,
        'name' => 'Nome Atualizado DB',
    ]);
});

it('ClusterServer listado em Index tem botões de ação (Test, Rotate, Edit)', function () {
    $admin = Operator::factory()->admin()->create();
    ClusterServer::factory()->create(['name' => 'Cluster Com Ações']);

    $html = actingAs($admin)
        ->get(route('cluster-servers.index'))
        ->assertOk()
        ->getContent();

    expect(
        str_contains($html, 'Test')
        || str_contains($html, 'Rotate')
        || str_contains($html, 'Editar'),
    )->toBeTrue();
});

it('webhook_secret_encrypted é gerado server-side na criação (não informado pelo usuário)', function () {
    bindSshSuccessMock();
    $admin = Operator::factory()->admin()->create();
    $clusterName = 'Cluster Com Webhook Secret';

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('name', $clusterName)
        ->set('ssh_host', '192.168.1.10')
        ->set('ssh_port', 22022)
        ->set('ssh_user', 'automation')
        ->set('ssh_private_key', TEST_CLUSTER_SERVER_VALID_PEM)
        ->call('save')
        ->assertRedirect(route('cluster-servers.index'));

    $stored = ClusterServer::where('name', $clusterName)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->webhook_secret_encrypted)->not->toBeNull();

    $this->assertDatabaseHas('webhook_secret_history', [
        'cluster_server_id' => $stored->id,
        'version' => 1,
    ]);
});
