<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Http\Exceptions\RenderDomainError;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\TenantUser;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use App\Modules\Customers\Services\OccPassthroughService;
use App\Modules\Customers\Services\TenantUserProjector;
use App\Modules\Customers\Services\TenantUserSyncService;
use App\Modules\Customers\Support\OccQuotaValue;
use App\Modules\Customers\Support\TenantUserListParser;
use App\Modules\Customers\Support\UserCreateStdinPayload;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Jobs\Support\JobSummaryParser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class OccPanel extends Component
{
    private const USER_CREATE_POLL_TIMEOUT_SECONDS = 120;

    /** @var list<string> */
    private const JOB_TERMINAL_STATES = ['success', 'failed', 'cancelled'];

    public Customer $customer;

    public string $tab = 'quota';

    // Quota tab
    public string $quotaUsername = '';

    public string $quotaValue = '';

    public string $quotaScope = 'user'; // user | default | all

    // Branding tab
    public string $brandingName = '';

    public string $brandingColor = '';

    public string $brandingSlogan = '';

    public string $brandingUrl = '';

    // Maintenance tab
    public bool $maintenanceOn = false;

    // Files rescan tab (within quota)
    public string $rescanUsername = '';

    // Apps tab
    public string $appId = '';

    public string $appAction = 'enable'; // enable | disable_bulk

    public string $appsBulk = ''; // comma-separated for bulk disable

    // Users tab
    public string $userUsername = '';

    /**
     * Senha bound via wire:model="userPasswordPlain" no input do formulário.
     * O snapshot do componente carrega a senha apenas enquanto o usuário digita —
     * mesmo modelo de qualquer formulário HTML (proteção via HTTPS + CSRF do
     * endpoint /livewire/update). Após criação bem-sucedida (ou erro), o método
     * createUser() zera a propriedade no finally para evitar persistência no
     * snapshot entre invocações.
     *
     * Chave da bag de erros permanece "userPassword" (mantém @error('userPassword')
     * legado e contratos de teste com assertHasErrors(['userPassword'])).
     */
    public string $userPasswordPlain = '';

    public string $userEmail = '';

    public string $userGroups = ''; // comma-separated

    public string $deleteUsername = '';

    /** @var array<int, array{username: string, email: string, quota: string, groups: string}> */
    public array $tenantUsers = [];

    public bool $usersLoading = false;

    public string $usersError = '';

    public string $pendingUserCreateJobId = '';

    public ?int $pendingUserCreateJobStartedAt = null;

    // Groups tab
    public string $groupName = '';

    public string $groupAddUsername = '';

    public string $groupAddTarget = '';

    public string $deleteGroupName = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function mount(string $slug): void
    {
        Gate::authorize('provision-customers');
        $this->customer = Customer::with('clusterServer')->findOrFail($slug);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->clearMessages();

        if ($tab === 'users') {
            $this->loadUsers();
        }
    }

    public function updatedTab(): void
    {
        if ($this->tab !== 'users') {
            return;
        }

        $this->loadUsers();
    }

    // ── Quota ────────────────────────────────────────────────────────────────

    public function submitQuota(OccPassthroughService $occ): void
    {
        $this->validate([
            'quotaValue' => ['required', 'string', 'regex:/^(\d+(\.\d+)?\s*(GB|MB|KB)|none|default)$/i'],
            'quotaUsername' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._@-]*$/'],
        ]);

        $this->clearMessages();

        $quotaForSsh = OccQuotaValue::forSshArgv($this->quotaValue);

        try {
            match ($this->quotaScope) {
                'user' => $occ->exec($this->customer, 'user:setting', [$this->quotaUsername, 'files', 'quota', $quotaForSsh]),
                'default' => $occ->exec($this->customer, 'config:app:set', ['files', 'default_quota', '--value', $quotaForSsh]),
                'all' => $occ->exec($this->customer, 'user:setting', ['--all', 'files', 'quota', $quotaForSsh]),
                default => throw new \InvalidArgumentException("Scope inválido: {$this->quotaScope}"),
            };
            $this->successMessage = 'Quota atualizada com sucesso.';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    public function submitRescan(OccPassthroughService $occ): void
    {
        $this->validate([
            'rescanUsername' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._@-]*$/'],
        ]);

        $this->clearMessages();
        try {
            $result = $occ->exec(
                $this->customer,
                'files:scan',
                $this->rescanUsername !== '' ? [$this->rescanUsername] : ['--all'],
            );
            $this->successMessage = 'Rescan concluído. '.(isset($result['files']) ? $result['files'].' arquivos.' : '');
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    // ── Branding ─────────────────────────────────────────────────────────────

    public function submitBranding(OccPassthroughService $occ): void
    {
        $this->validate([
            'brandingColor' => ['nullable', 'regex:/^(#[0-9a-fA-F]{6})?$/'],
            'brandingUrl' => ['nullable', 'url'],
        ]);

        $this->clearMessages();
        $fields = [];
        foreach ([
            'name' => $this->brandingName,
            'color' => $this->brandingColor,
            'slogan' => $this->brandingSlogan,
            'url' => $this->brandingUrl,
        ] as $key => $val) {
            if ($val !== '') {
                $fields[$key] = $val;
            }
        }

        if ($fields === []) {
            $this->errorMessage = 'Preencha ao menos um campo de branding.';

            return;
        }

        try {
            $occ->execThemingConfig($this->customer, $fields);
            $this->successMessage = 'Branding atualizado.';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    // ── Maintenance ───────────────────────────────────────────────────────────

    public function toggleMaintenance(OccPassthroughService $occ): void
    {
        $this->clearMessages();
        try {
            $occ->exec($this->customer, 'maintenance:mode', [$this->maintenanceOn ? '--on' : '--off']);
            $state = $this->maintenanceOn ? 'ATIVADO' : 'DESATIVADO';
            $this->successMessage = "Modo manutenção {$state}.";
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    // ── Apps (sync individual enable via OCC) ─────────────────────────────────

    public function submitApp(OccPassthroughService $occ): void
    {
        $this->validate([
            'appId' => ['required', 'string', 'regex:/^[a-z0-9_]+$/', 'max:100'],
        ]);

        $this->clearMessages();
        try {
            $occ->exec($this->customer, 'app:enable', [$this->appId]);
            $this->successMessage = "App '{$this->appId}' habilitado via OCC.";
            $this->appId = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    // ── Users (list sync + async lifecycle) ───────────────────────────────────

    public function loadUsers(): void
    {
        $this->usersLoading = true;
        $this->usersError = '';

        try {
            $rows = TenantUser::query()
                ->where('customer_slug', $this->customer->slug)
                ->orderBy('username')
                ->get();

            $this->tenantUsers = $rows
                ->map(static fn (TenantUser $user): array => TenantUserListParser::toDisplayRow($user))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $this->tenantUsers = [];
            $this->usersError = $this->formatError($e);
        } finally {
            $this->usersLoading = false;
        }
    }

    public function syncUsers(TenantUserSyncService $sync): void
    {
        $this->usersLoading = true;
        $this->usersError = '';

        try {
            $sync->sync($this->customer);
            $this->loadUsers();
        } catch (\Throwable $e) {
            $this->usersError = $this->formatError($e);
        } finally {
            $this->usersLoading = false;
        }
    }

    public function createUser(LifecycleAsyncAction $action): void
    {
        // Senha viaja em $this->userPasswordPlain (bound via wire:model na view).
        // O método valida >=10 chars (política NC 33) antes de qualquer chamada
        // upstream e zera a propriedade no finally — produção e testes percorrem
        // o MESMO caminho (F5.11 elimina test/production divergence QA-F5-019).
        $this->validate([
            'userUsername' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9._-]+$/',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (strtolower((string) $value) === 'admin') {
                        $fail('Username reservado (criado no provisionamento).');
                    }
                },
            ],
            'userEmail' => ['nullable', 'email'],
            'userGroups' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $groups = array_map('trim', explode(',', (string) $value));
                    foreach ($groups as $group) {
                        if ($group !== '' && strtolower($group) === 'admin') {
                            $fail('Grupo admin é reservado da plataforma.');
                        }
                    }
                },
            ],
        ]);

        if (strlen($this->userPasswordPlain) < 10) {
            $this->addError('userPassword', 'Senha deve ter ao menos 10 caracteres.');

            return;
        }

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        // Upstream `user create` accepts only <username> as positional; other fields
        // travel via JSON --payload-stdin. See ISSUE-006 §DP3.
        $groups = array_values(array_filter(
            array_map('trim', explode(',', $this->userGroups)),
            static fn (string $g): bool => $g !== '',
        ));
        $stdinPayload = UserCreateStdinPayload::build(
            password: $this->userPasswordPlain,
            email: $this->userEmail !== '' ? $this->userEmail : null,
            groups: $groups,
        );

        try {
            $job = $action->execute(
                $this->customer,
                'users:create',
                [$this->userUsername],
                $stdinPayload,
                $actor,
                'panel',
            );
            $this->pendingUserCreateJobId = $job->job_id;
            $this->pendingUserCreateJobStartedAt = now()->timestamp;
            $this->successMessage = "Usuário enfileirado — job {$job->job_id}.";
            $this->userUsername = $this->userEmail = $this->userGroups = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        } finally {
            $this->userPasswordPlain = '';
            unset($stdinPayload);
        }
    }

    public function pollPendingUserJob(): void
    {
        if ($this->pendingUserCreateJobId === '') {
            return;
        }

        $jobId = $this->pendingUserCreateJobId;

        if ($this->isUserCreatePollTimedOut()) {
            $this->finalizeUserCreatePollTimeout($jobId);

            return;
        }

        $job = Job::query()->where('job_id', $jobId)->first();
        if ($job === null || ! in_array($job->state, self::JOB_TERMINAL_STATES, true)) {
            return;
        }

        $this->handleUserCreateJobTerminal($job);
    }

    public function deleteUser(LifecycleAsyncAction $action): void
    {
        $this->validate([
            'deleteUsername' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
        ]);

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        try {
            $job = $action->execute($this->customer, 'users:delete', [$this->deleteUsername], null, $actor);
            $this->successMessage = "Deleção enfileirada — job {$job->job_id}.";
            $this->deleteUsername = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    // ── Groups (async lifecycle) ──────────────────────────────────────────────

    public function createGroup(LifecycleAsyncAction $action): void
    {
        $this->validate([
            'groupName' => ['required', 'string', 'max:256'],
        ]);

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        try {
            $job = $action->execute($this->customer, 'groups:create', [$this->groupName], null, $actor);
            $this->successMessage = "Grupo enfileirado — job {$job->job_id}.";
            $this->groupName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    public function deleteGroup(LifecycleAsyncAction $action): void
    {
        $this->validate([
            'deleteGroupName' => ['required', 'string', 'max:256'],
        ]);

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        try {
            $job = $action->execute($this->customer, 'groups:delete', [$this->deleteGroupName], null, $actor);
            $this->successMessage = "Deleção de grupo enfileirada — job {$job->job_id}.";
            $this->deleteGroupName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    public function addUserToGroup(LifecycleAsyncAction $_action): void
    {
        $this->validate([
            'groupAddUsername' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
            'groupAddTarget' => ['required', 'string', 'max:256'],
        ]);

        $this->clearMessages();

        // groups:add blocked upstream until D3/D4 — short-circuit avoids unreachable success branch.
        $this->errorMessage = 'Funcionalidade pendente no upstream — disponível em release futura.';
    }

    public function render(): View
    {
        return view('livewire.customers.occ-panel', [
            'quotaOptions' => OccPassthroughService::quotaOptions(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    private function clearPendingUserCreateJob(): void
    {
        $this->pendingUserCreateJobId = '';
        $this->pendingUserCreateJobStartedAt = null;
    }

    private function isUserCreatePollTimedOut(): bool
    {
        $startedAt = $this->pendingUserCreateJobStartedAt ?? now()->timestamp;

        return now()->timestamp - $startedAt >= self::USER_CREATE_POLL_TIMEOUT_SECONDS;
    }

    private function finalizeUserCreatePollTimeout(string $jobId): void
    {
        $this->clearPendingUserCreateJob();
        $this->clearMessages();
        $this->errorMessage = "Tempo esgotado — verifique /queue/{$jobId}";
    }

    private function handleUserCreateJobTerminal(Job $job): void
    {
        $this->clearPendingUserCreateJob();
        $this->clearMessages();

        if ($job->state === 'success') {
            $this->projectUserCreateIntoReadModel($job);
            $this->successMessage = 'Usuário criado com sucesso.';
            $this->loadUsers();

            return;
        }

        $this->errorMessage = JobSummaryParser::failureMessage($job);
    }

    private function projectUserCreateIntoReadModel(Job $job): void
    {
        try {
            app(TenantUserProjector::class)->handleTerminalJob($job, 'success');
        } catch (\Throwable $e) {
            Log::warning('tenant_users.projection.poll_failed', [
                'job_id' => $job->job_id,
                'customer_slug' => $job->customer_slug,
                'job_type' => $job->job_type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatError(\Throwable $e): string
    {
        if ($e instanceof CapabilityBlockedException) {
            return 'Operação OCC não permitida pelo upstream — subcomando bloqueado na allowlist occ-exec (exit 16).';
        }

        $e = RenderDomainError::unwrapTransport($e);

        return match (true) {
            $e instanceof BlockedOnUpstreamException => 'Funcionalidade pendente no upstream — disponível em release futura.',
            $e instanceof TenantNotReadyException => 'Tenant ainda finalizando provisionamento — tente novamente em cerca de 60 segundos.',
            $e instanceof ClusterUnreachableException => 'Cluster indisponível. Tente novamente em instantes.',
            $e instanceof SshTimeoutException => 'Timeout: OCC não respondeu em 60s.',
            $e instanceof IdempotencyConflictException => 'Operação já em andamento (idempotency conflict).',
            $e instanceof SshRemoteException && $e->remoteExitCode === 1 => 'Recurso não encontrado no Nextcloud.',
            $e instanceof SshRemoteException && $e->remoteExitCode === 4 => 'Recurso já existe.',
            $e instanceof SshRemoteException && $e->remoteExitCode === 22 => 'Senha não atende aos requisitos mínimos.',
            $e instanceof SshRemoteException && $e->remoteExitCode === 16 => 'Operação OCC não permitida pelo upstream — subcomando bloqueado na allowlist occ-exec (exit 16).',
            $e instanceof SshRemoteException => "Erro upstream (exit {$e->remoteExitCode}).",
            default => 'Erro inesperado: '.$e->getMessage(),
        };
    }
}
