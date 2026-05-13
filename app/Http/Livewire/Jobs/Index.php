<?php

declare(strict_types=1);

namespace App\Http\Livewire\Jobs;

use App\Models\Job;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'state')]
    public string $stateFilter = '';

    #[Url(as: 'job_type')]
    public string $jobTypeFilter = '';

    #[Url(as: 'customer')]
    public string $customerFilter = '';

    public function updatingStateFilter(): void
    {
        $this->resetPage();
    }

    public function updatingJobTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCustomerFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $jobs = Job::query()
            ->with(['customer', 'clusterServer'])
            ->when($this->stateFilter !== '', fn ($q) => $q->where('state', $this->stateFilter))
            ->when($this->jobTypeFilter !== '', fn ($q) => $q->where('job_type', $this->jobTypeFilter))
            ->when($this->customerFilter !== '', fn ($q) => $q->where('customer_slug', 'like', '%'.addcslashes($this->customerFilter, '%_').'%'))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.jobs.index', compact('jobs'));
    }
}
