<div>
    <style>
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1.5rem; }
        .filter-bar { display: flex; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-input {
            background: #1a1d27; border: 1px solid #2d3748; border-radius: 6px;
            color: #a0aec0; padding: .375rem .75rem; font-size: .8125rem; min-width: 180px;
        }
        .card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: .75rem; font-weight: 600; color: #718096;
            text-transform: uppercase; letter-spacing: .05em;
            padding: .75rem 1rem; border-bottom: 1px solid #2d3748;
        }
        td { padding: .75rem 1rem; font-size: .8rem; border-bottom: 1px solid #1e2535; vertical-align: top; }
        tr:hover td { background: #0f1117; }
        .action-tag {
            display: inline-block; padding: .15rem .5rem; border-radius: 4px;
            font-family: monospace; font-size: .75rem;
            background: #1e2d4a; color: #63b3ed;
        }
        .payload-pre {
            background: #0f1117; border-radius: 4px; padding: .5rem;
            font-size: .7rem; font-family: monospace; color: #a0aec0;
            max-height: 120px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;
        }
    </style>

    <h1 class="page-title">Audit Log</h1>

    <div class="filter-bar">
        <input type="text" class="filter-input" wire:model.live.debounce.300ms="filterAction"
            placeholder="Filtrar por ação (ex: cluster_server.create)">
        <select class="filter-input" wire:model.live="filterResource">
            <option value="">Todos os recursos</option>
            <option value="cluster_server">cluster_server</option>
            <option value="operator">operator</option>
            <option value="customer">customer</option>
            <option value="job">job</option>
        </select>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Ação</th>
                    <th>Recurso</th>
                    <th>Ator</th>
                    <th>IP</th>
                    <th>Payload</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td style="color:#718096;white-space:nowrap;font-size:.75rem">
                            {{ $log->created_at?->format('d/m/Y H:i:s') ?? '—' }}
                        </td>
                        <td><span class="action-tag">{{ $log->action }}</span></td>
                        <td style="color:#a0aec0">
                            <span style="font-size:.7rem;color:#718096">{{ $log->resource_type }}</span><br>
                            <span style="font-family:monospace;font-size:.75rem">{{ Str::limit($log->resource_id, 16) }}</span>
                        </td>
                        <td style="color:#a0aec0">{{ $log->actor?->name ?? $log->actor_id }}</td>
                        <td style="color:#718096;font-family:monospace;font-size:.75rem">{{ $log->ip ?? '—' }}</td>
                        <td>
                            @if ($log->payload)
                                <div class="payload-pre">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
                            @else
                                <span style="color:#4a5568">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:#718096;padding:2rem">
                            Nenhum registro de auditoria encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $logs->links() }}
    </div>
</div>
