<?php

declare(strict_types=1);

namespace App\Http\Livewire\ApiKeys;

use App\Models\Operator;
use App\Modules\Core\Services\ApiKeyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public bool $showCreateModal = false;

    public string $createName = '';

    /** @var array<int, string> */
    public array $createScopes = [];

    protected function rules(): array
    {
        return [
            'createName' => ['required', 'string', 'min:2', 'max:120'],
            'createScopes' => ['array'],
            'createScopes.*' => [Rule::in(config('api-scopes.v1'))],
        ];
    }

    public string $createdToken = '';

    public bool $showTokenReveal = false;

    private ApiKeyService $apiKeyService;

    public function mount(ApiKeyService $apiKeyService): void
    {
        $this->apiKeyService = $apiKeyService;
    }

    public function hydrate(ApiKeyService $apiKeyService): void
    {
        $this->apiKeyService = $apiKeyService;
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('manage-operators');

        $this->createName = '';
        $this->createScopes = [];
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        Gate::authorize('manage-operators');

        $this->validate();

        try {
            /** @var Operator $actor */
            $actor = Auth::user();
            $result = $this->apiKeyService->generate(
                name: trim($this->createName),
                scopes: $this->createScopes ?: null,
                actor: $actor,
            );

            $this->createdToken = $result['rawToken'];
            $this->showCreateModal = false;
            $this->showTokenReveal = true;
            $this->createName = '';
            $this->createScopes = [];
            $this->resetErrorBag();
        } catch (\Throwable $e) {
            Log::warning('ApiKeys\Index: create failed', ['error' => $e->getMessage()]);
            $this->addError('createName', 'Falha ao gerar credencial. Tente novamente.');
        }
    }

    public function closeTokenReveal(): void
    {
        $this->createdToken = '';
        $this->showTokenReveal = false;
    }

    public function revoke(string $id): void
    {
        Gate::authorize('manage-operators');

        if (! Str::isUuid($id)) {
            $this->addError('revoke', 'ID de credencial inválido.');

            return;
        }

        try {
            /** @var Operator $actor */
            $actor = Auth::user();
            $this->apiKeyService->revoke($id, $actor);
            $this->dispatch('toast', type: 'success', msg: 'Credencial revogada.');
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'warning', msg: $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('ApiKeys\Index: revoke failed', ['id' => $id, 'error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', msg: 'Falha ao revogar credencial.');
        }
    }

    public function render(): View
    {
        Gate::authorize('manage-operators');

        $keys = $this->apiKeyService->list($this->filterStatus);

        return view('livewire.api-keys.index', compact('keys'));
    }
}
