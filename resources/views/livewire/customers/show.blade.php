<div>
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; }
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 4px; font-size: .7rem; font-weight: 600; }
        .badge-active { background: #1c3a2f; color: #68d391; }
        .badge-provisioning { background: #1a2d4a; color: #63b3ed; }
        .badge-removing { background: #3a2d1a; color: #d6b656; }
        .badge-removed { background: #2d2020; color: #fc8181; }
        .badge-error { background: #3a2020; color: #fc8181; }
        .section-card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; }
        .section-title { font-size: .875rem; font-weight: 600; color: #e2e8f0; margin-bottom: .75rem; }
        .info-row { display: flex; gap: 1rem; margin-bottom: .5rem; font-size: .8rem; }
        .info-label { color: #718096; min-width: 120px; }
        .info-value { color: #a0aec0; }
        .btn-danger {
            background: #7f1d1d; color: #fc8181; border: 1px solid #991b1b;
            border-radius: 6px; padding: .45rem 1rem; font-size: .8125rem; cursor: pointer;
        }
        .btn-danger:hover { background: #991b1b; }
        .btn-cancel { background: none; border: 1px solid #4a5568; color: #a0aec0; border-radius: 4px; padding: .4rem .85rem; font-size: .8rem; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        th { text-align: left; color: #718096; font-size: .75rem; font-weight: 600; padding: .5rem .75rem; border-bottom: 1px solid #2d3748; text-transform: uppercase; }
        td { padding: .5rem .75rem; border-bottom: 1px solid #1e2535; color: #a0aec0; }
        .mono { font-family: monospace; font-size: .75rem; }
        /* Modal overlay */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.7);
            display: flex; align-items: center; justify-content: center; z-index: 50;
        }
        .modal-box { background: #1a1d27; border: 1px solid #991b1b; border-radius: 10px; padding: 1.75rem; max-width: 460px; width: 100%; }
        .modal-title { font-size: 1rem; font-weight: 700; color: #fc8181; margin-bottom: .75rem; }
        .modal-body { font-size: .8125rem; color: #a0aec0; margin-bottom: 1rem; line-height: 1.6; }
        .modal-input { width: 100%; background: #0f1117; border: 1px solid #2d3748; border-radius: 6px; color: #e2e8f0; padding: .45rem .75rem; font-size: .8125rem; box-sizing: border-box; margin-top: .5rem; }
        .modal-input:focus { outline: none; border-color: #991b1b; }
        .form-error { color: #fc8181; font-size: .75rem; margin-top: .25rem; }
        .modal-actions { display: flex; gap: .75rem; margin-top: 1.25rem; justify-content: flex-end; }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                {{ $customer->slug }}
                <span class="badge badge-{{ $customer->status }}" style="margin-left:.5rem">{{ $customer->status }}</span>
            </h1>
            <div style="color:#718096;font-size:.8rem;margin-top:.25rem">{{ $customer->domain }}</div>
        </div>
        <div style="display:flex;gap:.5rem;align-items:center">
            @if ($customer->status === 'active')
                <a href="{{ route('customers.occ', $customer->slug) }}"
                   style="background:#1a365d;color:#63b3ed;border:1px solid #2b4c7e;border-radius:6px;padding:.45rem 1rem;font-size:.8125rem;text-decoration:none">
                    Painel OCC
                </a>
            @endif
            @if (in_array($customer->status, ['active', 'provisioning']) && auth()->user()?->role !== 'suporte')
                <button class="btn-danger" wire:click="$set('showRemoveModal', true)">Remover</button>
            @endif
        </div>
    </div>

    <div class="section-card">
        <div class="section-title">Detalhes</div>
        <div class="info-row">
            <span class="info-label">Cluster</span>
            <span class="info-value">{{ $customer->clusterServer?->name ?? '—' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Criado em</span>
            <span class="info-value">{{ $customer->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Última sync</span>
            <span class="info-value">{{ $customer->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</span>
        </div>
    </div>

    <div class="section-card">
        <div class="section-title">Jobs recentes</div>
        <table>
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Saída</th>
                    <th>Enfileirado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($jobs as $job)
                    <tr>
                        <td class="mono">{{ Str::limit($job->job_id, 8, '') }}…</td>
                        <td class="mono">{{ $job->job_type }}</td>
                        <td><span class="badge badge-{{ $job->state }}">{{ $job->state }}</span></td>
                        <td class="mono">{{ $job->exit_code !== null ? $job->exit_code : '—' }}</td>
                        <td style="white-space:nowrap;font-size:.75rem">{{ $job->queued_at?->format('d/m H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#718096;padding:1.5rem">Nenhum job.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section-card">
        <div class="section-title">Audit trail</div>
        <table>
            <thead>
                <tr>
                    <th>Ação</th>
                    <th>Quando</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $log)
                    <tr>
                        <td class="mono">{{ $log->action }}</td>
                        <td style="white-space:nowrap;font-size:.75rem">{{ $log->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="text-align:center;color:#718096;padding:1.5rem">Sem entradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de remoção forte --}}
    @if ($showRemoveModal)
    <div class="modal-overlay" x-data="{ confirmInput: $wire.entangle('confirmInput') }">
        <div class="modal-box">
            <div class="modal-title">⚠ Remover customer</div>
            <div class="modal-body">
                Esta operação é <strong>irreversível</strong>. O Nextcloud do cliente será removido no upstream.
                <br><br>
                Para confirmar, digite exatamente: <code style="background:#0f1117;padding:.1rem .35rem;border-radius:3px;color:#fc8181">{{ $customer->slug }}</code>
                <input
                    type="text"
                    class="modal-input"
                    x-model="confirmInput"
                    placeholder="Digite o slug para confirmar"
                    autocomplete="off">
                @if ($removeError)
                    <div class="form-error">{{ $removeError }}</div>
                @endif

                <div style="display:flex;align-items:center;gap:.5rem;margin-top:.75rem">
                    <input type="checkbox" wire:model="backupFirst" id="backupFirst" checked>
                    <label for="backupFirst" style="color:#a0aec0;font-size:.8rem;cursor:pointer">
                        Fazer backup antes de remover (--backup-first)
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" wire:click="$set('showRemoveModal', false); $set('confirmInput', '')">
                    Cancelar
                </button>
                <button
                    class="btn-danger"
                    wire:click="remove"
                    :disabled="confirmInput !== '{{ $customer->slug }}'">
                    Confirmar remoção
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
