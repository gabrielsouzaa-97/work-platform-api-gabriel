<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Ssh\Exceptions\SshRemoteException;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Customers\Actions\LifecycleAsyncAction;
use App\Modules\Customers\Exceptions\ClusterUnreachableException;
use App\Modules\Customers\Exceptions\IdempotencyConflictException;
use App\Modules\Customers\Services\OccPassthroughService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class OccPanel extends Component
{
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
     * Senha NÃO é wire:model — não entra no snapshot do componente.
     * Lida via HttpRequest::input() dentro de createUser() e apagada imediatamente.
     */
    #[Locked]
    public string $userPassword = '';

    public string $userEmail = '';

    public string $userGroups = ''; // comma-separated

    public string $deleteUsername = '';

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
    }

    // ── Quota ────────────────────────────────────────────────────────────────

    public function submitQuota(OccPassthroughService $occ): void
    {
        $this->validate([
            'quotaValue' => ['required', 'string', 'regex:/^(\d+(\.\d+)?\s*(GB|MB|KB)|none|default)$/i'],
            'quotaUsername' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._@-]*$/'],
        ]);

        $this->clearMessages();

        try {
            match ($this->quotaScope) {
                'user' => $occ->exec($this->customer, 'user:setting', [$this->quotaUsername, 'files', 'quota', $this->quotaValue]),
                'default' => $occ->exec($this->customer, 'config:app:set', ['files', 'default_quota', '--value', $this->quotaValue]),
                'all' => $occ->exec($this->customer, 'user:setting', ['--all', 'files', 'quota', $this->quotaValue]),
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
        $args = [];
        $fields = [
            'name' => $this->brandingName,
            'color' => $this->brandingColor,
            'slogan' => $this->brandingSlogan,
            'url' => $this->brandingUrl,
        ];
        foreach ($fields as $key => $val) {
            if ($val !== '') {
                $args[] = $key;
                $args[] = $val;
            }
        }

        if (empty($args)) {
            $this->errorMessage = 'Preencha ao menos um campo de branding.';

            return;
        }

        try {
            $occ->exec($this->customer, 'theming:config', $args);
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

    // ── Users (async lifecycle) ───────────────────────────────────────────────

    public function createUser(LifecycleAsyncAction $action): void
    {
        // Senha lida diretamente do request HTTP — nunca transita como propriedade Livewire.
        $password = request()->input('password', '');

        $this->validate([
            'userUsername' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
            'userEmail' => ['nullable', 'email'],
        ]);

        if (strlen($password) < 8) {
            $this->addError('userPassword', 'Senha deve ter ao menos 8 caracteres.');

            return;
        }

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();
        $args = array_filter([$this->userUsername, $this->userEmail]);
        foreach (array_filter(array_map('trim', explode(',', $this->userGroups))) as $group) {
            $args[] = "--group={$group}";
        }

        try {
            $job = $action->execute(
                $this->customer,
                'users:create',
                array_values($args),
                ['password' => $password],
                $actor,
            );
            $this->successMessage = "Usuário enfileirado — job {$job->job_id}.";
            $this->userUsername = $this->userEmail = $this->userGroups = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        } finally {
            unset($password);
        }
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

    public function addUserToGroup(LifecycleAsyncAction $action): void
    {
        $this->validate([
            'groupAddUsername' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:64'],
            'groupAddTarget' => ['required', 'string', 'max:256'],
        ]);

        $this->clearMessages();

        /** @var Operator $actor */
        $actor = auth()->user();

        try {
            $job = $action->execute(
                $this->customer,
                'groups:add',
                [$this->groupAddUsername, $this->groupAddTarget],
                null,
                $actor,
            );
            $this->successMessage = "Adição ao grupo enfileirada — job {$job->job_id}.";
            $this->groupAddUsername = $this->groupAddTarget = '';
        } catch (\Throwable $e) {
            $this->errorMessage = $this->formatError($e);
        }
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

    private function formatError(\Throwable $e): string
    {
        return match (true) {
            $e instanceof ClusterUnreachableException => 'Cluster indisponível. Tente novamente em instantes.',
            $e instanceof SshTimeoutException => 'Timeout: OCC não respondeu em 60s.',
            $e instanceof IdempotencyConflictException => 'Operação já em andamento (idempotency conflict).',
            $e instanceof SshRemoteException && $e->remoteExitCode === 1 => 'Recurso não encontrado no Nextcloud.',
            $e instanceof SshRemoteException && $e->remoteExitCode === 4 => 'Recurso já existe.',
            $e instanceof SshRemoteException && $e->remoteExitCode === 22 => 'Senha não atende aos requisitos mínimos.',
            $e instanceof SshRemoteException => "Erro upstream (exit {$e->remoteExitCode}).",
            default => 'Erro inesperado: '.$e->getMessage(),
        };
    }
}
