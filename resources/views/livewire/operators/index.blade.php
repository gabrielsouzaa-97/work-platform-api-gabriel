<div>
    <style>
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; }
        .btn-primary {
            background: #2b6cb0;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: .5rem 1rem;
            font-size: .875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover { background: #2c5282; }
        .filter-bar { display: flex; gap: .75rem; margin-bottom: 1rem; }
        .filter-select {
            background: #1a1d27;
            border: 1px solid #2d3748;
            border-radius: 6px;
            color: #a0aec0;
            padding: .375rem .75rem;
            font-size: .8125rem;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            font-size: .75rem;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: .75rem 1rem;
            border-bottom: 1px solid #2d3748;
        }
        td {
            padding: .875rem 1rem;
            font-size: .875rem;
            border-bottom: 1px solid #1e2535;
            vertical-align: middle;
        }
        tr:hover td { background: #1a1d27; }
        .badge {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 500;
        }
        .badge-active { background: #1c3a2f; color: #68d391; }
        .badge-pending { background: #2d3a1a; color: #c6f135; }
        .badge-inactive { background: #3a2020; color: #fc8181; }
        .badge-admin { background: #1e2d4a; color: #63b3ed; }
        .badge-operador { background: #2a2040; color: #b794f4; }
        .badge-suporte { background: #2a2730; color: #e2e8f0; }
        .action-btn {
            background: none;
            border: 1px solid #4a5568;
            color: #a0aec0;
            border-radius: 4px;
            padding: .2rem .5rem;
            font-size: .75rem;
            cursor: pointer;
            margin-right: .25rem;
        }
        .action-btn:hover { border-color: #718096; color: #e2e8f0; }
        .card {
            background: #1a1d27;
            border: 1px solid #2d3748;
            border-radius: 8px;
            overflow: hidden;
        }
    </style>

    <div class="page-header">
        <h1 class="page-title">Operadores</h1>
        <a href="{{ route('operators.create') }}" class="btn-primary">+ Novo operador</a>
    </div>

    <div class="filter-bar">
        <select class="filter-select" wire:model.live="filterRole">
            <option value="">Todos os perfis</option>
            <option value="admin">Admin</option>
            <option value="operador">Operador</option>
            <option value="suporte">Suporte</option>
        </select>
        <select class="filter-select" wire:model.live="filterStatus">
            <option value="">Todos os status</option>
            <option value="active">Ativo</option>
            <option value="pending">Pendente</option>
            <option value="inactive">Inativo</option>
        </select>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($operators as $op)
                    <tr>
                        <td>{{ $op->name }}</td>
                        <td style="color:#a0aec0">{{ $op->email }}</td>
                        <td>
                            <span class="badge badge-{{ $op->role }}">{{ $op->role }}</span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $op->status }}">{{ $op->status }}</span>
                        </td>
                        <td style="color:#718096;font-size:.8rem">{{ $op->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('operators.edit', $op->id) }}" class="action-btn">
                                Editar
                            </a>
                            @if ($op->status === 'pending')
                                <button class="action-btn" wire:click="resendInvite('{{ $op->id }}')">
                                    Reenviar convite
                                </button>
                            @endif
                            @if ($op->status === 'active' && $op->id !== auth()->id())
                                <button
                                    class="action-btn"
                                    wire:click="deactivate('{{ $op->id }}')"
                                    wire:confirm="Tem certeza que deseja desativar este operador?"
                                >
                                    Desativar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:#718096;padding:2rem">
                            Nenhum operador encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $operators->links() }}
    </div>
</div>
