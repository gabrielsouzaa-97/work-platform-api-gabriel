<?php

declare(strict_types=1);

namespace App\Http\Livewire\ApiKeys;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('manage-operators');

        $keys = ApiKey::query()
            ->when($this->filterStatus === 'active', fn ($q) => $q->whereNull('revoked_at'))
            ->when($this->filterStatus === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.api-keys.index', compact('keys'));
    }
}
