<div>
    <style>
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1.5rem; }
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
        .badge {
            display: inline-block; padding: .2rem .55rem; border-radius: 4px;
            font-size: .7rem; font-weight: 600; letter-spacing: .03em;
        }
        .badge-queued    { background: #1e2535; color: #718096; }
        .badge-running   { background: #1a2d4a; color: #63b3ed; }
        .badge-success   { background: #1c3a2f; color: #68d391; }
        .badge-failed    { background: #3a2020; color: #fc8181; }
        .badge-cancelled { background: #2d2a1a; color: #d6b656; }
        .mono { font-family: monospace; font-size: .75rem; color: #a0aec0; }
        .text-muted { color: #4a5568; }
    </style>

    <h1 class="page-title">Fila de Jobs</h1>

    <div class="filter-bar">
        <select class="filter-input" wire:model.live="stateFilter">
            <option value="">Todos os estados</option>
            <option value="queued">queued</option>
            <option value="running">running</option>
            <option value="success">success</option>
            <option value="failed">failed</option>
            <option value="cancelled">cancelled</option>
        </select>

        <input type="text" class="filter-input" wire:model.live.debounce.300ms="jobTypeFilter"
            placeholder="Tipo (ex: provision)">

        <input type="text" class="filter-input" wire:model.live.debounce.300ms="customerFilter"
            placeholder="Customer (ex: acme)">

        @if ($stateFilter || $jobTypeFilter || $customerFilter)
            <button wire:click="$set('stateFilter', ''); $set('jobTypeFilter', ''); $set('customerFilter', '')"
                style="background:none;border:1px solid #4a5568;color:#a0aec0;border-radius:4px;padding:.35rem .75rem;font-size:.8rem;cursor:pointer;">
                Limpar filtros
            </button>
        @endif
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Customer</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Saída</th>
                    <th>Enfileirado</th>
                    <th>Concluído</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($jobs as $job)
                    <tr>
                        <td class="mono">{{ Str::limit($job->job_id, 8, '') }}…</td>
                        <td>
                            <span class="mono">{{ $job->customer_slug }}</span>
                        </td>
                        <td class="mono">{{ $job->job_type }}</td>
                        <td>
                            <span class="badge badge-{{ $job->state }}">{{ $job->state }}</span>
                        </td>
                        <td class="mono text-muted">
                            {{ $job->exit_code !== null ? $job->exit_code : '—' }}
                        </td>
                        <td style="color:#718096;font-size:.75rem;white-space:nowrap">
                            {{ $job->queued_at?->format('d/m H:i') ?? '—' }}
                        </td>
                        <td style="color:#718096;font-size:.75rem;white-space:nowrap">
                            {{ $job->finished_at?->format('d/m H:i') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;color:#718096;padding:2rem">
                            Nenhum job encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $jobs->links() }}
    </div>
</div>
