<?php

declare(strict_types=1);

namespace App\Http\Livewire\Product\Plans;

use App\Models\Plan;
use App\Modules\Product\Services\PlanService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showCreateModal = false;

    public string $createSlug = '';

    public string $createName = '';

    public string $createDefaultQuota = '5 GB';

    public string $createStatus = 'active';

    public bool $createIsDefault = false;

    public ?string $editSlug = null;

    public string $editName = '';

    public string $editDefaultQuota = '';

    public string $editStatus = 'active';

    public bool $editIsDefault = false;

    private PlanService $planService;

    public function mount(PlanService $planService): void
    {
        Gate::authorize('manage-operators');
        $this->planService = $planService;
    }

    public function hydrate(PlanService $planService): void
    {
        $this->planService = $planService;
    }

    public function openCreate(): void
    {
        Gate::authorize('manage-operators');
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        Gate::authorize('manage-operators');
        $this->validate($this->createRules());

        $this->planService->create([
            'slug' => $this->createSlug,
            'name' => $this->createName,
            'default_quota' => $this->createDefaultQuota,
            'status' => $this->createStatus,
            'is_default' => $this->createIsDefault,
            'app_ids' => [],
        ]);

        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    public function openEdit(string $slug): void
    {
        Gate::authorize('manage-operators');

        $plan = Plan::query()->findOrFail($slug);
        $this->editSlug = $plan->slug;
        $this->editName = $plan->name;
        $this->editDefaultQuota = $plan->default_quota;
        $this->editStatus = $plan->status;
        $this->editIsDefault = $plan->is_default;
    }

    public function save(): void
    {
        Gate::authorize('manage-operators');

        if ($this->editSlug === null) {
            return;
        }

        $this->validate($this->editRules());

        $this->planService->update($this->editSlug, [
            'name' => $this->editName,
            'default_quota' => $this->editDefaultQuota,
            'status' => $this->editStatus,
            'is_default' => $this->editIsDefault,
        ]);

        $this->editSlug = null;
    }

    public function render(): View
    {
        Gate::authorize('manage-operators');

        return view('livewire.product.plans.index', [
            'plans' => Plan::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        return [
            'createSlug' => ['required', 'string', 'regex:/^[a-z0-9-]+$/', 'max:64', Rule::unique('plans', 'slug')],
            'createName' => ['required', 'string', 'max:255'],
            'createDefaultQuota' => ['required', 'string', 'max:64'],
            'createStatus' => ['required', Rule::in(['active', 'inactive'])],
            'createIsDefault' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editRules(): array
    {
        return [
            'editName' => ['required', 'string', 'max:255'],
            'editDefaultQuota' => ['required', 'string', 'max:64'],
            'editStatus' => ['required', Rule::in(['active', 'inactive'])],
            'editIsDefault' => ['boolean'],
        ];
    }

    private function resetCreateForm(): void
    {
        $this->createSlug = '';
        $this->createName = '';
        $this->createDefaultQuota = '5 GB';
        $this->createStatus = 'active';
        $this->createIsDefault = false;
        $this->resetErrorBag();
    }
}
