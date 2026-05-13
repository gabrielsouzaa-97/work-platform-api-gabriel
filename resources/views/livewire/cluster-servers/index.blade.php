<div>
    <style>
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; }
        .btn-primary {
            background: #2b6cb0; color: #fff; border: none; border-radius: 6px;
            padding: .5rem 1rem; font-size: .875rem; font-weight: 500;
            cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn-primary:hover { background: #2c5282; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: .75rem; font-weight: 600; color: #718096;
            text-transform: uppercase; letter-spacing: .05em;
            padding: .75rem 1rem; border-bottom: 1px solid #2d3748;
        }
        td { padding: .875rem 1rem; font-size: .875rem; border-bottom: 1px solid #1e2535; vertical-align: middle; }
        tr:hover td { background: #1a1d27; }
        .badge { display: inline-block; padding: .2rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; }
        .badge-active { background: #1c3a2f; color: #68d391; }
        .badge-unreachable { background: #3a2020; color: #fc8181; }
        .badge-inactive { background: #2d3748; color: #a0aec0; }
        .action-btn {
            background: none; border: 1px solid #4a5568; color: #a0aec0;
            border-radius: 4px; padding: .2rem .5rem; font-size: .75rem;
            cursor: pointer; margin-right: .25rem; text-decoration: none; display: inline-block;
        }
        .action-btn:hover { border-color: #718096; color: #e2e8f0; }
        .card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; overflow: hidden; }
        .secret-preview { font-family: monospace; color: #718096; font-size: .8rem; }
    </style>

    <div class="page-header">
        <h1 class="page-title">Cluster Servers</h1>
        <a href="{{ route('cluster-servers.create') }}" class="btn-primary">+ Novo cluster</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Host</th>
                    <th>Porta</th>
                    <th>Usuário</th>
                    <th>Status</th>
                    <th>Webhook Secret</th>
                    <th>Último health</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clusters as $cluster)
                    <tr>
                        <td>{{ $cluster->name }}</td>
                        <td style="color:#a0aec0;font-family:monospace;font-size:.8rem">{{ $cluster->ssh_host }}</td>
                        <td style="color:#718096">{{ $cluster->ssh_port }}</td>
                        <td style="color:#718096">{{ $cluster->ssh_user }}</td>
                        <td>
                            <span class="badge badge-{{ $cluster->status }}">{{ $cluster->status }}</span>
                        </td>
                        <td>
                            <span class="secret-preview">
                                ••••••{{ substr($cluster->webhook_secret_encrypted, -4) }}
                            </span>
                        </td>
                        <td style="color:#718096;font-size:.8rem">
                            {{ $cluster->last_health_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td>
                            <button class="action-btn" wire:click="testConnection('{{ $cluster->id }}')" wire:loading.attr="disabled">
                                Test
                            </button>
                            <button class="action-btn"
                                wire:click="rotateSecret('{{ $cluster->id }}')"
                                wire:confirm="Rotacionar o webhook secret? A versão atual permanece válida por {{ config('services.webhook.grace_period_hours', 24) }}h."
                                wire:loading.attr="disabled">
                                Rotate
                            </button>
                            <a href="{{ route('cluster-servers.edit', $cluster->id) }}" class="action-btn">
                                Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:#718096;padding:2rem">
                            Nenhum cluster server cadastrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $clusters->links() }}
    </div>
</div>
