<?php

declare(strict_types=1);

use App\Http\Livewire\Audit\Index;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('observer registra audit_log ao criar cluster_server', function () {
    $admin = Operator::factory()->admin()->create();
    Auth::login($admin);

    $cluster = ClusterServer::factory()->make([
        'name' => 'Cluster Observer Test',
        'ssh_host' => '1.2.3.4',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'webhook_secret_version' => 1,
        'schema_version' => 1,
        'status' => 'active',
    ]);
    $cluster->ssh_private_key_encrypted = 'pem-content';
    $cluster->webhook_secret_encrypted = 'secret';
    $cluster->save();

    $log = AuditLog::where('resource_type', 'cluster_server')
        ->where('resource_id', $cluster->id)
        ->where('action', 'cluster_server.create')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id);
});

it('observer sanitiza campos sensíveis no payload', function () {
    $admin = Operator::factory()->admin()->create();
    Auth::login($admin);

    $cluster = ClusterServer::factory()->make([
        'name' => 'Sanitize Test',
        'ssh_host' => '5.6.7.8',
        'ssh_port' => 22,
        'ssh_user' => 'root',
        'webhook_secret_version' => 1,
        'schema_version' => 1,
        'status' => 'active',
    ]);
    $cluster->ssh_private_key_encrypted = 'my-super-secret-pem';
    $cluster->webhook_secret_encrypted = 'my-webhook-secret';
    $cluster->save();

    $log = AuditLog::where('action', 'cluster_server.create')
        ->where('resource_type', 'cluster_server')
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull();

    $payload = $log->payload;

    expect($payload['ssh_private_key_encrypted'])->toBe('[REDACTED]');
    expect($payload['webhook_secret_encrypted'])->toBe('[REDACTED]');
    expect($payload['name'])->toBe('Sanitize Test');
});

it('observer registra before/after ao atualizar cluster_server', function () {
    $admin = Operator::factory()->admin()->create();
    Auth::login($admin);

    $cluster = ClusterServer::factory()->create(['name' => 'Nome Original']);
    $cluster->update(['name' => 'Nome Atualizado']);

    $log = AuditLog::where('action', 'cluster_server.update')
        ->where('resource_id', $cluster->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->payload['after']['name'])->toBe('Nome Atualizado');
});

it('admin acessa audit index e vê registros paginados', function () {
    $admin = Operator::factory()->admin()->create();

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'actor_id' => $admin->id,
        'action' => 'cluster_server.create',
        'resource_type' => 'cluster_server',
        'resource_id' => Str::uuid()->toString(),
        'payload' => ['name' => 'Test'],
    ]);

    actingAs($admin)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('cluster_server.create');
});

it('filtro por action filtra registros no Livewire audit index', function () {
    $admin = Operator::factory()->admin()->create();

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'actor_id' => $admin->id,
        'action' => 'cluster_server.create',
        'resource_type' => 'cluster_server',
        'resource_id' => Str::uuid()->toString(),
    ]);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'actor_id' => $admin->id,
        'action' => 'operator.deactivate',
        'resource_type' => 'operator',
        'resource_id' => Str::uuid()->toString(),
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterAction', 'cluster_server')
        ->assertSee('cluster_server.create')
        ->assertDontSee('operator.deactivate');
});

it('suporte não acessa audit index — recebe 403', function () {
    $suporte = Operator::factory()->create(['role' => 'suporte']);

    actingAs($suporte)
        ->get(route('audit.index'))
        ->assertForbidden();
});
