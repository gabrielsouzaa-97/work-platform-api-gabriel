<?php

declare(strict_types=1);

namespace App\Http\Livewire\Customers;

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Core\Ssh\Exceptions\SshClientException;
use App\Modules\Customers\Services\CustomerSyncService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'cluster')]
    public string $clusterFilter = '';

    #[Url(as: 'search')]
    public string $searchFilter = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingClusterFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearchFilter(): void
    {
        $this->resetPage();
    }

    public function resync(CustomerSyncService $svc): void
    {
        Gate::authorize('manage-operators');

        $clusters = $this->clusterFilter
            ? ClusterServer::where('id', $this->clusterFilter)->where('status', 'active')->get()
            : ClusterServer::where('status', 'active')->get();

        foreach ($clusters as $cluster) {
            try {
                $svc->sync($cluster);
            } catch (SshClientException $e) {
                $this->dispatch('toast', type: 'error', msg: "Sync falhou para {$cluster->name}: {$e->getMessage()}");
            }
        }

        $this->dispatch('toast', type: 'success', msg: 'Sincronização concluída.');
    }

    public function render(): View
    {
        $customers = Customer::query()
            ->with('clusterServer')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->clusterFilter !== '', fn ($q) => $q->where('cluster_server_id', $this->clusterFilter))
            ->when($this->searchFilter !== '', fn ($q) => $q->where('slug', 'like', '%'.addcslashes($this->searchFilter, '%_').'%'))
            ->orderByDesc('created_at')
            ->paginate(25);

        $clusters = ClusterServer::orderBy('name')->get(['id', 'name']);

        return view('livewire.customers.index', compact('customers', 'clusters'));
    }
}
