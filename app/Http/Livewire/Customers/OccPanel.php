<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Http\Exceptions\RenderDomainError;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Operator;
use App\Models\TenantGroup;
use App\Models\TenantUser;
use App\Models\UserTemplate;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Translators\Exceptions\BlockedOnUpstreamException;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Exceptions\TenantNotReadyException;
use App\Modules\Customers\Services\OccPassthroughService;
use App\Modules\Customers\Services\TenantGroupProjector;
use App\Modules\Customers\Services\TenantGroupSyncService;
use App\Modules\Customers\Services\TenantUserProjector;
use App\Modules\Customers\Services\TenantUserSyncService;
use App\Modules\Customers\Support\OccQuotaValue;
use App\Modules\Customers\Support\TenantGroupListParser;
use App\Modules\Customers\Support\TenantGroupNameRules;
use App\Modules\Customers\Support\TenantKnownGroups;
use App\Modules\Customers\Support\TenantUserListParser;
use App\Modules\Customers\Support\UserCreateStdinPayload;
use App\Modules\Customers\Validation\TenantGroupMembership;
use App\Modules\Integration\Exceptions\CapabilityBlockedException;
use App\Modules\Jobs\Support\JobSummaryParser;
use App\Modules\Product\Exceptions\PlanLimitExceededException;
use App\Modules\Product\Services\PolicyResolver;
use App\Modules\Product\Services\UserCreateTemplateResolver;
use App\Modules\Product\Validation\ActiveUserTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class OccPanel extends Component
{
    private const USER_CREATE_POLL_TIMEOUT_SECONDS = 120;

    private const GROUP_JOB_POLL_TIMEOUT_SECONDS = 120;

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

    /** @var list<string> */
    public array $userGroupSelection = [];

    public bool $userGroupSelectionTouched = false;

    public string $userTemplateSlug = '';

    public string $deleteUsername = '';

    /** @var array<int, array{username: string, email: string, quota: string, groups: string}> */
    public array $tenantUsers = [];

    public bool $usersLoading = false;

    public string $usersError = '';

    public string $pendingUserCreateJobId = '';

    public ?int $pendingUserCreateJobStartedAt = null;

    public string $pendingUserDeleteJobId = '';

    public ?int $pendingUserDeleteJobStartedAt = null;

    public string $pendingGroupCreateJobId = '';

    public ?int $pendingGroupCreateJobStartedAt = null;

    public string $pendingGroupDeleteJobId = '';

    public ?int $pendingGroupDeleteJobStartedAt = null;

    // Groups tab
    public string $groupName = '';

    public string $groupAddUsername = '';

    public string $groupAddTarget = '';

    public string $deleteGroupName = '';

    /** @var array<int, array{name: string, origin: string}> */
    public array $tenantGroups = [];

    public bool $groupsLoading = false;

    public string $groupsError = '';

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

        if ($tab === 'groups') {
            $this->loadGroups();
        }
    }

    public function updatedTab(): void
    {
        if ($this->tab === 'users') {
            $this->loadUsers();
        }

        if ($this->tab === 'groups') {
            $this->loadGroups();
        }
    }

    public function updatedUserTemplateSlug(?string $value): void
    {
        $this->userGroupSelectionTouched = false;
    }

    public function updatedUserGroupSelection(): void
    {
        $this->userGroupSelectionTouched = true;
    }

    // ── Quota ────────────────────────────────────────────────────────────────

    public function submitQuota(OccPassthroughService $occ): void
    {
        $this->validate([
            'quotaValue' => ['required', 'string', 'regex:/^(\d+(\.\d+)?\s*(GB|MB|KB)|none|default)$/i'],
            'quotaUsername' => [
                Rule::requiredIf(fn (): bool => $this->quotaScope === 'user'),
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9._@-]*$/',
            ],
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

    public function loadGroups(): void
    {
        $this->groupsLoading = true;
        $this->groupsError = '';

        try {
            $rows = TenantGroup::query()
                ->where('customer_slug', $this->customer->slug)
                ->orderBy('name')
                ->get();

            $this->tenantGroups = $rows
                ->map(static fn (TenantGroup $group): array => TenantGroupListParser::toDisplayRow($group))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $this->tenantGroups = [];
            $this->groupsError = $this->formatError($e);
        } finally {
            $this->groupsLoading = false;
        }
    }

    public function syncGroups(TenantGroupSyncService $sync): void
    {
        $this->groupsLoading = true;
        $this->groupsError = '';

        try {
            $sync->sync($this->customer);
            $this->loadGroups();
        } catch (\Throwable $e) {
            $this->groupsError = $this->formatError($e);
        } finally {
            $this->groupsLoading = false;
        }
    }

    public function createUser(
        LifecycleAsyncAction $action,
        UserCreateTemplateResolver $templateResolver,
        PolicyResolver $policyResolver,
    ): void {
        try {
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
                'userGroupSelection' => [
                    'array',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! is_array($value)) {
                            return;
                        }

                        $membership = new TenantGroupMembership($this->customer->slug);
                        foreach ($value as $group) {
                            $membership->validate($attribute, $group, $fail);
                        }
                    },
                ],
                'userTemplateSlug' => ['nullable', 'string', 'max:64', new ActiveUserTemplate],
            ]);

            if (strlen($this->userPasswordPlain) < 10) {
                $this->addError('userPassword', 'Senha deve ter ao menos 10 caracteres.');

                return;
            }

            $this->clearMessages();

            /** @var Operator $actor */
            $actor = auth()->user();
            $policyResolver->assertCanCreateUser($this->customer, $actor);

            $templateSlug = $this->userTemplateSlug !== '' ? $this->userTemplateSlug : null;
            $explicitGroups = $this->userGroupSelection !== []
                ? $this->resolveCanonicalGroupNames($this->userGroupSelection)
                : ($templateSlug !== null && $this->userGroupSelectionTouched ? [] : null);
            $resolved = $templateResolver->resolve($templateSlug, $explicitGroups, null);

            $groupsForPayload = $explicitGroups !== null
                ? $explicitGroups
                : ($resolved->groups !== [] ? $resolved->groups : null);

            $stdinPayload = UserCreateStdinPayload::build(
                password: $this->userPasswordPlain,
                email: $this->userEmail !== '' ? $this->userEmail : null,
                groups: $groupsForPayload,
            );

            if ($resolved->quota !== null && $resolved->quota !== '') {
                $stdinPayload['quota'] = $resolved->quota;
            }

            if ($resolved->userTemplateSlug !== null) {
                $stdinPayload['user_template_slug'] = $resolved->userTemplateSlug;
            }

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
            $this->userUsername = $this->userEmail = $this->userTemplateSlug = '';
            $this->userGroupSelection = [];
            $this->userGroupSelectionTouched = false;
        } catch (ValidationException $e) {
            throw $e;
        } catch (PlanLimitExceededException) {
            $this->errorMessage = 'Limite do plano excedido para criação de usuários.';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        } finally {
            $this->userPasswordPlain = '';
        }
    }

    public function pollPendingUserJob(): void
    {
        $successBefore = $this->successMessage;
        $errorBefore = $this->errorMessage;
        $this->pollPendingGroupJob();
        $preserveMessages = $this->successMessage !== $successBefore
            || $this->errorMessage !== $errorBefore;

        $this->pollPendingUserCreateJob($preserveMessages);
        $this->pollPendingUserDeleteJob($preserveMessages);
    }

    private function pollPendingUserCreateJob(bool $preserveMessages = false): void
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

        $this->handleUserCreateJobTerminal($job, $preserveMessages);
    }

    private function pollPendingUserDeleteJob(bool $preserveMessages = false): void
    {
        if ($this->pendingUserDeleteJobId === '') {
            return;
        }

        $jobId = $this->pendingUserDeleteJobId;

        if ($this->isUserDeletePollTimedOut()) {
            $this->finalizeUserDeletePollTimeout($jobId);

            return;
        }

        $job = Job::query()->where('job_id', $jobId)->first();
        if ($job === null || ! in_array($job->state, self::JOB_TERMINAL_STATES, true)) {
            return;
        }

        $this->handleUserDeleteJobTerminal($job, $preserveMessages);
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
            $this->pendingUserDeleteJobId = $job->job_id;
            $this->pendingUserDeleteJobStartedAt = now()->timestamp;
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
            'groupName' => $this->groupNameRules(),
        ]);

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        try {
            $job = $action->execute($this->customer, 'groups:create', [$this->groupName], null, $actor, 'panel');
            $this->pendingGroupCreateJobId = $job->job_id;
            $this->pendingGroupCreateJobStartedAt = now()->timestamp;
            $this->successMessage = "Grupo enfileirado — job {$job->job_id}.";
            $this->groupName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    public function deleteGroup(LifecycleAsyncAction $action): void
    {
        $this->validate([
            'deleteGroupName' => $this->groupNameRules(),
        ]);

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        try {
            $job = $action->execute($this->customer, 'groups:delete', [$this->deleteGroupName], null, $actor, 'panel');
            $this->pendingGroupDeleteJobId = $job->job_id;
            $this->pendingGroupDeleteJobStartedAt = now()->timestamp;
            $this->successMessage = "Deleção de grupo enfileirada — job {$job->job_id}.";
            $this->deleteGroupName = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
    }

    public function pollPendingGroupJob(): void
    {
        $preserveMessages = $this->pendingGroupCreateJobId !== ''
            && $this->pendingGroupDeleteJobId !== '';

        if ($this->pendingGroupCreateJobId !== '') {
            $this->pollSingleGroupJob(
                $this->pendingGroupCreateJobId,
                $this->pendingGroupCreateJobStartedAt,
                function (): void {
                    $this->clearPendingGroupCreateJob();
                },
                'Grupo criado com sucesso.',
                $preserveMessages,
            );
        }

        if ($this->pendingGroupDeleteJobId !== '') {
            $this->pollSingleGroupJob(
                $this->pendingGroupDeleteJobId,
                $this->pendingGroupDeleteJobStartedAt,
                function (): void {
                    $this->clearPendingGroupDeleteJob();
                },
                'Grupo removido com sucesso.',
                $preserveMessages,
            );
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
        $customerSlug = $this->customer->slug;

        return view('livewire.customers.occ-panel', [
            'quotaOptions' => OccPassthroughService::quotaOptions(),
            'userTemplates' => UserTemplate::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['slug', 'name']),
            'knownGroups' => TenantKnownGroups::forCustomer($customerSlug),
            'usernameOptions' => TenantUser::query()
                ->where('customer_slug', $customerSlug)
                ->orderBy('username')
                ->pluck('username', 'username')
                ->all(),
            'groupOptions' => TenantGroup::query()
                ->where('customer_slug', $customerSlug)
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resolveCanonicalGroupNames(array $selection): array
    {
        return array_values(array_map(
            fn (string $group): string => $this->canonicalGroupName($group) ?? $group,
            $selection,
        ));
    }

    private function canonicalGroupName(string $group): ?string
    {
        return TenantGroup::query()
            ->where('customer_slug', $this->customer->slug)
            ->whereRaw('LOWER(name) = ?', [strtolower($group)])
            ->value('name');
    }

    private function clearMessages(): void
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    private function appendSuccess(string $text): void
    {
        if ($this->successMessage === '') {
            $this->successMessage = $text;

            return;
        }

        if (! str_contains($this->successMessage, $text)) {
            $this->successMessage .= ' '.$text;
        }
    }

    private function appendError(string $text): void
    {
        if ($this->errorMessage === '') {
            $this->errorMessage = $text;

            return;
        }

        if (! str_contains($this->errorMessage, $text)) {
            $this->errorMessage .= ' '.$text;
        }
    }

    private function clearPendingUserCreateJob(): void
    {
        $this->pendingUserCreateJobId = '';
        $this->pendingUserCreateJobStartedAt = null;
    }

    private function clearPendingUserDeleteJob(): void
    {
        $this->pendingUserDeleteJobId = '';
        $this->pendingUserDeleteJobStartedAt = null;
    }

    private function clearPendingGroupCreateJob(): void
    {
        $this->pendingGroupCreateJobId = '';
        $this->pendingGroupCreateJobStartedAt = null;
    }

    private function clearPendingGroupDeleteJob(): void
    {
        $this->pendingGroupDeleteJobId = '';
        $this->pendingGroupDeleteJobStartedAt = null;
    }

    /**
     * @return list<string|\Closure>
     */
    private function groupNameRules(): array
    {
        return TenantGroupNameRules::forAttribute('groupName');
    }

    private function pollSingleGroupJob(
        string $jobId,
        ?int $startedAt,
        \Closure $clearPending,
        string $successText,
        bool $preserveMessages = false,
    ): void {
        if ($this->isGroupJobPollTimedOut($startedAt)) {
            $clearPending();
            if (! $preserveMessages) {
                $this->clearMessages();
            }
            $this->errorMessage = "Tempo esgotado — verifique /queue/{$jobId}";

            return;
        }

        $job = Job::query()->where('job_id', $jobId)->first();
        if ($job === null || ! in_array($job->state, self::JOB_TERMINAL_STATES, true)) {
            return;
        }

        $clearPending();
        if (! $preserveMessages) {
            $this->clearMessages();
        }

        if ($job->state === 'success') {
            $this->projectGroupJobIntoReadModel($job);
            if ($preserveMessages) {
                $this->appendSuccess($successText);
            } else {
                $this->successMessage = $successText;
            }
            $this->loadGroups();

            return;
        }

        if ($preserveMessages) {
            $this->appendError(JobSummaryParser::failureMessage($job));
        } else {
            $this->errorMessage = JobSummaryParser::failureMessage($job);
        }
    }

    private function isGroupJobPollTimedOut(?int $startedAt): bool
    {
        $anchor = $startedAt ?? now()->timestamp;

        return now()->timestamp - $anchor >= self::GROUP_JOB_POLL_TIMEOUT_SECONDS;
    }

    private function projectGroupJobIntoReadModel(Job $job): void
    {
        try {
            app(TenantGroupProjector::class)->handleTerminalJob($job, 'success');
        } catch (\Throwable $e) {
            Log::warning('tenant_groups.projection.poll_failed', [
                'job_id' => $job->job_id,
                'customer_slug' => $job->customer_slug,
                'job_type' => $job->job_type,
                'error' => $e->getMessage(),
            ]);
        }
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

    private function handleUserCreateJobTerminal(Job $job, bool $preserveMessages = false): void
    {
        $this->clearPendingUserCreateJob();
        if (! $preserveMessages) {
            $this->clearMessages();
        }

        if ($job->state === 'success') {
            $this->projectUserJobIntoReadModel($job);
            if ($preserveMessages) {
                $this->appendSuccess('Usuário criado com sucesso.');
            } else {
                $this->successMessage = 'Usuário criado com sucesso.';
            }
            $this->loadUsers();

            return;
        }

        if ($preserveMessages) {
            $this->appendError(JobSummaryParser::failureMessage($job));
        } else {
            $this->errorMessage = JobSummaryParser::failureMessage($job);
        }
    }

    private function isUserDeletePollTimedOut(): bool
    {
        $startedAt = $this->pendingUserDeleteJobStartedAt ?? now()->timestamp;

        return now()->timestamp - $startedAt >= self::USER_CREATE_POLL_TIMEOUT_SECONDS;
    }

    private function finalizeUserDeletePollTimeout(string $jobId): void
    {
        $this->clearPendingUserDeleteJob();
        $this->clearMessages();
        $this->errorMessage = "Tempo esgotado — verifique /queue/{$jobId}";
    }

    private function handleUserDeleteJobTerminal(Job $job, bool $preserveMessages = false): void
    {
        $this->clearPendingUserDeleteJob();
        if (! $preserveMessages) {
            $this->clearMessages();
        }

        if ($job->state === 'success') {
            $this->projectUserJobIntoReadModel($job);
            if ($preserveMessages) {
                $this->appendSuccess('Usuário removido com sucesso.');
            } else {
                $this->successMessage = 'Usuário removido com sucesso.';
            }
            $this->loadUsers();

            return;
        }

        if ($preserveMessages) {
            $this->appendError(JobSummaryParser::failureMessage($job));
        } else {
            $this->errorMessage = JobSummaryParser::failureMessage($job);
        }
    }

    private function projectUserJobIntoReadModel(Job $job): void
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
