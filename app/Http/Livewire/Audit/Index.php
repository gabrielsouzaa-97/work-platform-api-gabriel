<?php

declare(strict_types=1);

namespace App\Http\Livewire\Audit;

use App\Models\AuditLog;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $filterAction = '';

    public string $filterResource = '';

    public string $filterActor = '';

    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatingFilterResource(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActor(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = AuditLog::with('actor')
            ->orderByDesc('created_at');

        if ($this->filterAction !== '') {
            $query->where('action', 'like', "%{$this->filterAction}%");
        }

        if ($this->filterResource !== '') {
            $query->where('resource_type', $this->filterResource);
        }

        if ($this->filterActor !== '') {
            $query->where('actor_id', $this->filterActor);
        }

        return view('livewire.audit.index', [
            'logs' => $query->paginate(50),
        ]);
    }
}
