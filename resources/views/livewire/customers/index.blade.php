<div>
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; }
        .btn-primary {
            background: #2563eb; color: #fff; border: none; border-radius: 6px;
            padding: .45rem 1rem; font-size: .8125rem; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary {
            background: none; border: 1px solid #4a5568; color: #a0aec0;
            border-radius: 4px; padding: .35rem .75rem; font-size: .8rem; cursor: pointer;
        }
        .btn-secondary:disabled { opacity: .5; cursor: not-allowed; }
        .filter-bar { display: flex; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center; }
        .filter-input {
            background: #1a1d27; border: 1px solid #2d3748; border-radius: 6px;
            color: #a0aec0; padding: .375rem .75rem; font-size: .8125rem; min-width: 160px;
        }
        .filter-input:focus { outline: none; border-color: #4a90d9; }
        .card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: .75rem; font-weight: 600; color: #718096;
            text-transform: uppercase; letter-spacing: .05em;
            padding: .75rem 1rem; border-bottom: 1px solid #2d3748;
        }
        td { padding: .75rem 1rem; font-size: .8rem; border-bottom: 1px solid #1e2535; vertical-align: middle; }
        tr:hover td { background: #0f1117; }
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 4px; font-size: .7rem; font-weight: 600; }
        .badge-active { background: #1c3a2f; color: #68d391; }
        .badge-provisioning { background: #1a2d4a; color: #63b3ed; }
        .badge-removing { background: #3a2d1a; color: #d6b656; }
        .badge-removed { background: #2d2020; color: #fc8181; }
        .badge-error { background: #3a2020; color: #fc8181; }
        .mono { font-family: monospace; font-size: .75rem; color: #a0aec0; }
        .text-link { color: #4a90d9; text-decoration: none; }
        .text-link:hover { text-decoration: underline; }
    </style>

    <div class="page-header">
        <h1 class="page-title">Customers</h1>
        <div style="display:flex;gap:.5rem;align-items:center">
            @can('manage-operators')
            <button class="btn-secondary" wire:click="resync" wire:loading.attr="disabled" :disabled="$syncing">
                <span wire:loading.remove wire:target="resync">↻ Ressincronizar</span>
                <span wire:loading wire:target="resync">Sincronizando…</span>
            </button>
            @endcan
            @can('provision-customers')
            <a href="{{ route('customers.create') }}" class="btn-primary">+ Provisionar</a>
            @endcan
        </div>
    </div>

    <div class="filter-bar">
        <select class="filter-input" wire:model.live="statusFilter">
            <option value="">Todos os status</option>
            <option value="active">active</option>
            <option value="provisioning">provisioning</option>
            <option value="removing">removing</option>
            <option value="removed">removed</option>
            <option value="error">error</option>
        </select>

        <select class="filter-input" wire:model.live="clusterFilter">
            <option value="">Todos os clusters</option>
            @foreach ($clusters as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>

        <input type="text" class="filter-input" wire:model.live.debounce.300ms="searchFilter"
            placeholder="Buscar por slug…">

        @if ($statusFilter || $clusterFilter || $searchFilter)
            <button wire:click="$set('statusFilter', ''); $set('clusterFilter', ''); $set('searchFilter', '')"
                style="background:none;border:1px solid #4a5568;color:#a0aec0;border-radius:4px;padding:.35rem .75rem;font-size:.8rem;cursor:pointer;">
                Limpar filtros
            </button>
        @endif
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Slug</th>
                    <th>Domínio</th>
                    <th>Cluster</th>
                    <th>Status</th>
                    <th>Última sync</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="mono">
                            <a href="{{ route('customers.show', $customer->slug) }}" class="text-link">
                                {{ $customer->slug }}
                            </a>
                        </td>
                        <td style="color:#a0aec0;font-size:.8rem">{{ $customer->domain }}</td>
                        <td style="color:#718096;font-size:.75rem">{{ $customer->clusterServer?->name ?? '—' }}</td>
                        <td>
                            <span class="badge badge-{{ $customer->status }}">{{ $customer->status }}</span>
                        </td>
                        <td style="color:#718096;font-size:.75rem;white-space:nowrap">
                            {{ $customer->last_sync_at?->format('d/m H:i') ?? '—' }}
                        </td>
                        <td>
                            <a href="{{ route('customers.show', $customer->slug) }}"
                                style="color:#4a90d9;font-size:.75rem;">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:#718096;padding:2rem">
                            Nenhum customer encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $customers->links() }}
    </div>
</div>
