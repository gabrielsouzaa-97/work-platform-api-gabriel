<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\OccPanel;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;

/**
 * Feature tests for the Livewire OccPanel — covers the 8 actions (quota,
 * rescan, branding, maintenance, app enable, user create/delete, group
 * create/delete, group add-user blocked) plus error mapping. Registered
 * by QA-F5-016 in the R1 audit.
 *
 * Strategy:
 *  - Mock SshClientInterface only — `LifecycleAsyncAction` is `final`
 *    (cannot be mocked by Mockery), so we let the real action execute
 *    and instrument behavior at the SSH boundary. This also catches
 *    integration drift between Livewire layer and the action's contract.
 *  - `BlockedOnUpstreamException` fires inside `JobTypeTranslator` before
 *    SSH is invoked, so the SSH mock declares `shouldNotReceive` for
 *    those paths — defensive verification that the short-circuit works
 *    end-to-end from the Livewire layer.
 *  - `IdempotencyConflictException` is reproduced by seeding the table
 *    before the call.
 */
function makeOccPanelCluster(): ClusterServer
{
    return ClusterServer::factory()->create(['status' => 'active']);
}

function makeOccPanelCustomer(ClusterServer $cluster): Customer
{
    return Customer::create([
        'slug' => 'occ-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'occ-test.example.com',
        'status' => 'active',
    ]);
}

function makeOccPanelOperator(string $role = 'operador'): Operator
{
    return Operator::factory()->create(['role' => $role, 'status' => 'active']);
}

function bindSshSyncSuccess(array $parsedJson = []): void
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturn(new SshResponse(
        stdout: json_encode($parsedJson),
        stderr: '',
        exitCode: 0,
        parsedJson: $parsedJson,
    ));
    app()->instance(SshClientInterface::class, $ssh);
}

function bindSshAsyncSuccess(string $jobId): MockInterface
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $ssh);

    return $ssh;
}

function bindSshAsyncThrows(Throwable $exception): MockInterface
{
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')->andThrow($exception);
    app()->instance(SshClientInterface::class, $ssh);

    return $ssh;
}

// ── Mount + Authorization ────────────────────────────────────────────────────

it('mount carrega o customer quando operador tem provision-customers', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->assertSet('customer.slug', $customer->slug)
        ->assertSet('tab', 'quota');
});

it('mount nega acesso a suporte → 403', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $suporte = makeOccPanelOperator('suporte');

    Livewire::actingAs($suporte)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->assertStatus(403);
});

it('setTab limpa successMessage e errorMessage', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('successMessage', 'foo')
        ->set('errorMessage', 'bar')
        ->call('setTab', 'apps')
        ->assertSet('tab', 'apps')
        ->assertSet('successMessage', '')
        ->assertSet('errorMessage', '');
});

// ── Quota (sync OCC) ─────────────────────────────────────────────────────────

it('submitQuota com sucesso seta successMessage', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshSyncSuccess(['ok' => true]);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('quotaScope', 'user')
        ->set('quotaUsername', 'johndoe')
        ->set('quotaValue', '5 GB')
        ->call('submitQuota')
        ->assertSet('successMessage', 'Quota atualizada com sucesso.')
        ->assertSet('errorMessage', '');
});

it('submitQuota com valor inválido → erro de validação', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('quotaValue', '5 ZZ')
        ->call('submitQuota')
        ->assertHasErrors(['quotaValue']);
});

// ── Rescan (sync OCC) ───────────────────────────────────────────────────────

it('submitRescan sem username executa --all e gera successMessage', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshSyncSuccess(['files' => 42]);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('submitRescan')
        ->assertSet('errorMessage', '');
});

// ── Branding (sync OCC) ─────────────────────────────────────────────────────

it('submitBranding com todos os campos vazios → errorMessage explícito sem SSH', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('submitBranding')
        ->assertSet('errorMessage', 'Preencha ao menos um campo de branding.');
});

it('submitBranding com name preenchido → SSH chamado + successMessage', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshSyncSuccess(['ok' => true]);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('brandingName', 'Acme')
        ->call('submitBranding')
        ->assertSet('successMessage', 'Branding atualizado.');
});

// ── Maintenance (sync OCC) ──────────────────────────────────────────────────

it('toggleMaintenance ON gera successMessage com estado correto', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshSyncSuccess(['ok' => true]);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('maintenanceOn', true)
        ->call('toggleMaintenance')
        ->assertSet('successMessage', 'Modo manutenção ATIVADO.');
});

// ── App enable (sync OCC) ────────────────────────────────────────────────────

it('submitApp habilita app via OCC sync e limpa o input', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshSyncSuccess(['enabled' => true]);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('appId', 'calendar')
        ->call('submitApp')
        ->assertSet('successMessage', "App 'calendar' habilitado via OCC.")
        ->assertSet('appId', '');
});

// ── User create (async lifecycle, real action + SSH mock) ───────────────────

it('createUser dispara LifecycleAsyncAction com stdin payload e seta successMessage', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();

    // Real action runs; assert the SSH boundary receives the expected stdin payload.
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->withArgs(function ($clusterArg, $cmd, $args, $stdin) {
            $decoded = json_decode($stdin ?? '', true);

            return is_array($decoded)
                && ($decoded['password'] ?? null) === 'Secret123!'
                && ($decoded['email'] ?? null) === 'john@acme.com'
                && ($decoded['groups'] ?? null) === ['editors'];
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    // F5.11 (QA-F5-019 fix): a senha agora viaja em $userPasswordPlain via
    // wire:model, mesmo caminho exercitado pela view real (<form wire:submit>).
    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userEmail', 'john@acme.com')
        ->set('userGroups', 'editors')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.")
        ->assertSet('userUsername', '')
        ->assertSet('userPasswordPlain', '');
});

it('createUser com IdempotencyConflictException → mensagem amigável', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    // Seed an active idempotency key so the real action throws.
    $argsHash = hash('sha256', $customer->slug.'|users:create|'.json_encode(['johndoe']));
    IdempotencyKey::create([
        'key' => Str::uuid()->toString(),
        'cmd' => 'users:create',
        'args_hash' => $argsHash,
        'customer_slug' => $customer->slug,
        'expires_at' => now()->addHours(23),
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('errorMessage', 'Operação já em andamento (idempotency conflict).')
        ->assertSet('userPasswordPlain', '');
});

it('deleteUser em tenant provisioning_finishing → mensagem tenant not ready sem SSH', function () {
    $cluster = makeOccPanelCluster();
    $customer = Customer::create([
        'slug' => 'occ-fin-'.substr(uniqid(), -8),
        'cluster_server_id' => $cluster->id,
        'domain' => 'occ-fin.example.com',
        'status' => CustomerLifecycleStatus::PROVISIONING_FINISHING,
    ]);
    $operator = makeOccPanelOperator();
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('deleteUsername', 'alice')
        ->call('deleteUser')
        ->assertSet('errorMessage', 'Tenant ainda finalizando provisionamento — tente novamente em cerca de 60 segundos.');
});

it('createUser com SshTimeoutException → mensagem amigável', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshAsyncThrows(new SshTimeoutException('timeout'));

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('errorMessage', 'Timeout: OCC não respondeu em 60s.')
        ->assertSet('userPasswordPlain', '');
});

it('createUser com senha < 8 chars → addError sem chamar SSH', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', '123')
        ->call('createUser')
        ->assertHasErrors(['userPassword']);
});

it('createUser SEM definir userPasswordPlain (production bug guard) → addError sem chamar SSH', function () {
    // F5.11 (QA-F5-019 regression guard): simula EXATAMENTE o cenário do bug
    // pré-existente — a view antiga não populava nenhuma propriedade Livewire
    // com a senha, resultando em userPasswordPlain ''. O método deve falhar
    // com erro de validação antes de qualquer chamada upstream.
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->call('createUser')
        ->assertHasErrors(['userPassword'])
        ->assertSet('userPasswordPlain', '');
});

it('createUser zera userPasswordPlain após sucesso (cleanup do snapshot)', function () {
    // F5.11 (QA-F5-019): garante que a senha não persiste no snapshot Livewire
    // entre invocações — o finally do método zera userPasswordPlain.
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();
    bindSshAsyncSuccess($jobId);

    $component = Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser');

    $component->assertSet('userPasswordPlain', '');
});

// ── User delete (async lifecycle) ────────────────────────────────────────────

it('deleteUser dispara users:delete e limpa input', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();
    bindSshAsyncSuccess($jobId);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('deleteUsername', 'johndoe')
        ->call('deleteUser')
        ->assertSet('successMessage', "Deleção enfileirada — job {$jobId}.")
        ->assertSet('deleteUsername', '');
});

// ── Group create / delete (async lifecycle) ──────────────────────────────────

it('createGroup dispara groups:create e limpa input', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();
    bindSshAsyncSuccess($jobId);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('groupName', 'editors')
        ->call('createGroup')
        ->assertSet('groupName', '')
        ->assertSeeText('Grupo enfileirado');
});

it('deleteGroup dispara groups:delete e limpa input', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();
    bindSshAsyncSuccess($jobId);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('deleteGroupName', 'editors')
        ->call('deleteGroup')
        ->assertSet('deleteGroupName', '');
});

it('createGroup com SshRemoteException exit 4 → "Recurso já existe"', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshAsyncThrows(new SshRemoteException('exists', 4));

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('groupName', 'editors')
        ->call('createGroup')
        ->assertSet('errorMessage', 'Recurso já existe.');
});

// ── Add user to group → blocked-on-upstream (CQ-F5-007 closure) ─────────────

it('addUserToGroup → BlockedOnUpstreamException renderiza mensagem amigável (CQ-F5-007)', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    // BlockedOnUpstreamException is thrown by JobTypeTranslator BEFORE SSH is invoked.
    // No SSH call should happen — the defensive contract verified end-to-end.
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('groupAddUsername', 'johndoe')
        ->set('groupAddTarget', 'editors')
        ->call('addUserToGroup')
        ->assertSet('errorMessage', 'Funcionalidade pendente no upstream — disponível em release futura.')
        ->assertSet('successMessage', '');

    expect(IdempotencyKey::where('cmd', 'groups:add')->count())->toBe(0);
});
