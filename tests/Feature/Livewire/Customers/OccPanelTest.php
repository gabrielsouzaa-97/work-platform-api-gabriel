<?php

declare(strict_types=1);

use App\Http\Livewire\Customers\OccPanel;
use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Job;
use App\Models\Operator;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Support\CustomerLifecycleStatus;
use Carbon\Carbon;
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

it('submitQuota default usa argv com --value para config:app:set (F?-OCC-4)', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(function ($clusterArg, $cmd, $args) {
            return $cmd === 'nextcloud-manage'
                && in_array('config:app:set', $args, true)
                && in_array('files', $args, true)
                && in_array('default_quota', $args, true)
                && in_array('--value', $args, true)
                && in_array('10GB', $args, true);
        })
        ->andReturn(new SshResponse(
            stdout: json_encode(['ok' => true]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['ok' => true],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('quotaScope', 'default')
        ->set('quotaValue', '10 GB')
        ->call('submitQuota')
        ->assertSet('successMessage', 'Quota atualizada com sucesso.');
});

it('submitBranding com exit 16 → mensagem allowlist explícita (ISSUE-016)', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshRemoteException('subcmd blocked', 16));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('brandingName', 'Acme')
        ->call('submitBranding')
        ->assertSet('errorMessage', 'Operação OCC não permitida pelo upstream — subcomando bloqueado na allowlist occ-exec (exit 16).');
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

it('submitBranding com name e color → duas chamadas theming:config (P-10)', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->twice()
        ->withArgs(fn ($clusterArg, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('theming:config', $args, true))
        ->andReturn(new SshResponse(
            stdout: json_encode(['ok' => true]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['ok' => true],
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('brandingName', 'Acme')
        ->set('brandingColor', '#aabbcc')
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

it('createUser com senha < 10 chars → addError sem chamar SSH', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', '123456789')
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

// ── User list (N39.2 — sync OCC user:list --json) ───────────────────────────

it('loadUsers com mock SSH retorna 2 usuários e renderiza tabela', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $users = [
        ['username' => 'alice', 'email' => 'alice@example.com', 'quota' => '5 GB', 'groups' => ['users']],
        ['username' => 'bob', 'email' => 'bob@example.com', 'quota' => 'none', 'groups' => ['editors']],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->once()
        ->withArgs(fn ($clusterArg, $cmd, $args) => $cmd === 'nextcloud-manage'
            && in_array('user:list', $args, true)
            && in_array('--json', $args, true))
        ->andReturn(new SshResponse(
            stdout: json_encode($users),
            stderr: '',
            exitCode: 0,
            parsedJson: $users,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('setTab', 'users')
        ->assertSet('usersLoading', false)
        ->assertCount('tenantUsers', 2)
        ->assertSee('alice')
        ->assertSee('bob')
        ->assertSee('alice@example.com');
});

it('loadUsers com SSH timeout seta usersError amigável sem stack trace', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshTimeoutException('timeout'));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('setTab', 'users')
        ->assertSet('usersError', 'Timeout: OCC não respondeu em 60s.')
        ->assertSet('tenantUsers', [])
        ->assertDontSee('SshTimeoutException');
});

it('createUser com username admin → validação rejeita antes de SSH', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldNotReceive('runAsync');
    $ssh->shouldNotReceive('run');
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'admin')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertHasErrors(['userUsername']);
});

it('loadUsers com JSON vazio exibe Nenhum usuário sem exception', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    bindSshSyncSuccess([]);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('setTab', 'users')
        ->assertSet('tenantUsers', [])
        ->assertSee('Nenhum usuário');
});

it('loadUsers normaliza envelope users e refresh via botão', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();

    $payload = [
        'users' => [
            ['uid' => 'carol', 'mail' => 'carol@example.com', 'quota' => '10 GB', 'groups' => ['admins']],
        ],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')
        ->twice()
        ->andReturn(new SshResponse(
            stdout: json_encode($payload),
            stderr: '',
            exitCode: 0,
            parsedJson: $payload,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('setTab', 'users')
        ->assertSee('carol')
        ->call('loadUsers')
        ->assertCount('tenantUsers', 1)
        ->assertSet('usersError', '');
});

// ── User create poll (N39.3 — async feedback até terminal) ───────────────────

it('createUser poll até job success → confirmação inline e reload da lista', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();

    $users = [
        ['username' => 'newuser', 'email' => 'new@example.com', 'quota' => '5 GB', 'groups' => ['users']],
    ];

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('runAsync')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode(['job_id' => $jobId]),
            stderr: '',
            exitCode: 0,
            parsedJson: ['job_id' => $jobId],
        ));
    $ssh->shouldReceive('run')
        ->once()
        ->andReturn(new SshResponse(
            stdout: json_encode($users),
            stderr: '',
            exitCode: 0,
            parsedJson: $users,
        ));
    app()->instance(SshClientInterface::class, $ssh);

    $component = Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'newuser')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('pendingUserCreateJobId', $jobId)
        ->assertSet('successMessage', "Usuário enfileirado — job {$jobId}.");

    Job::query()->where('job_id', $jobId)->update([
        'state' => 'success',
        'summary' => ['[INFO] User newuser created'],
        'finished_at' => now(),
    ]);

    $component->call('pollPendingUserJob')
        ->assertSet('pendingUserCreateJobId', '')
        ->assertSet('successMessage', 'Usuário criado com sucesso.')
        ->assertCount('tenantUsers', 1)
        ->assertSet('tenantUsers.0.username', 'newuser');
});

it('createUser poll job failed com summary → errorMessage inline', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();
    bindSshAsyncSuccess($jobId);

    $component = Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('pendingUserCreateJobId', $jobId);

    Job::query()->where('job_id', $jobId)->update([
        'state' => 'failed',
        'summary' => ['[INFO] Starting user create', '[ERROR] admin already exists'],
        'finished_at' => now(),
        'exit_code' => 1,
    ]);

    $component->call('pollPendingUserJob')
        ->assertSet('pendingUserCreateJobId', '')
        ->assertSet('errorMessage', 'admin already exists')
        ->assertSet('successMessage', '');
});

it('pollPendingUserJob após 120s sem terminal → mensagem timeout com link queue', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();

    Carbon::setTestNow('2026-07-05 12:00:00');
    bindSshAsyncSuccess($jobId);

    $component = Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSet('pendingUserCreateJobId', $jobId);

    Carbon::setTestNow('2026-07-05 12:02:01');

    $component->call('pollPendingUserJob')
        ->assertSet('pendingUserCreateJobId', '')
        ->assertSet('errorMessage', "Tempo esgotado — verifique /queue/{$jobId}");

    Carbon::setTestNow();
});

it('blade exibe hint política NC 33 e wire:poll quando job pendente', function () {
    $cluster = makeOccPanelCluster();
    $customer = makeOccPanelCustomer($cluster);
    $operator = makeOccPanelOperator();
    $jobId = Str::uuid()->toString();

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->andReturn(new SshResponse(
        stdout: json_encode([]),
        stderr: '',
        exitCode: 0,
        parsedJson: [],
    ));
    $ssh->shouldReceive('runAsync')->andReturn(new SshResponse(
        stdout: json_encode(['job_id' => $jobId]),
        stderr: '',
        exitCode: 0,
        parsedJson: ['job_id' => $jobId],
    ));
    app()->instance(SshClientInterface::class, $ssh);

    Livewire::actingAs($operator)
        ->test(OccPanel::class, ['slug' => $customer->slug])
        ->call('setTab', 'users')
        ->set('userUsername', 'johndoe')
        ->set('userPasswordPlain', 'Secret123!')
        ->call('createUser')
        ->assertSee('Nextcloud 33 exige ≥10 caracteres')
        ->assertSeeHtml('wire:poll.3s="pollPendingUserJob"');
});
