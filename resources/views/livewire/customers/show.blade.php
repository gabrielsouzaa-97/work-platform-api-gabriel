@php
    $customerStatusColors = [
        'active' => 'text-[#6ad191] bg-[#6ad191]/10 border border-[#6ad191]/20',
        'provisioning' => 'text-primary bg-primary/10 border border-primary/20',
        'provisioning_finishing' => 'text-primary-fixed-dim bg-primary-fixed-dim/10 border border-primary-fixed-dim/20',
        'removing' => 'text-tertiary bg-tertiary/10 border border-tertiary/20',
        'removed' => 'text-error bg-error/10 border border-error/20',
        'error' => 'text-error bg-error/10 border border-error/20',
        'failed' => 'text-error bg-error/10 border border-error/20',
    ];
    $jobStateColors = [
        'success' => 'text-[#6ad191] bg-[#6ad191]/10 border border-[#6ad191]/20',
        'running' => 'text-primary bg-primary/10 border border-primary/20',
        'queued' => 'text-on-surface-variant bg-surface-container-highest border border-outline-variant',
        'failed' => 'text-error bg-error/10 border border-error/20',
        'cancelled' => 'text-error bg-error/10 border border-error/20',
    ];
    $defaultBadgeClass = 'text-on-surface-variant bg-surface-container-highest border border-outline-variant';
@endphp

<div
    @if ($this->shouldPoll())
        wire:poll.5s="refreshProgress"
    @endif
>
    <div class="flex flex-col gap-sm md:flex-row md:items-center md:justify-between mb-lg">
        <div>
            <h1 class="text-[1.25rem] font-semibold text-on-surface flex flex-wrap items-center gap-sm">
                {{ $customer->slug }}
                <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $customerStatusColors[$customer->status] ?? $defaultBadgeClass }}">
                    {{ $customer->status }}
                </span>
            </h1>
            <div class="text-[13px] text-on-surface-variant mt-xs">{{ $customer->domain }}</div>
        </div>
        <div class="flex items-center gap-sm">
            @if ($customer->status === 'active')
                <a
                    href="{{ route('customers.occ', $customer->slug) }}"
                    class="inline-flex items-center rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary no-underline"
                >
                    Painel OCC
                </a>
            @endif
            @can('provision-customers')
                @if (in_array($customer->status, ['active', 'provisioning']))
                    <button
                        type="button"
                        class="rounded-md border border-error/50 bg-error-container px-md py-2 text-[13px] text-error hover:opacity-90"
                        wire:click="$set('showRemoveModal', true)"
                    >
                        Remover
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg mb-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Detalhes</div>
        <div class="flex gap-md mb-sm text-[13px]">
            <span class="text-on-surface-variant min-w-[120px]">Cluster</span>
            <span class="text-on-surface">{{ $customer->clusterServer?->name ?? '—' }}</span>
        </div>
        <div class="flex gap-md mb-sm text-[13px]">
            <span class="text-on-surface-variant min-w-[120px]">Criado em</span>
            <span class="text-on-surface">{{ $customer->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
        </div>
        <div class="flex gap-md text-[13px]">
            <span class="text-on-surface-variant min-w-[120px]">Última sync</span>
            <span class="text-on-surface">{{ $customer->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</span>
        </div>
    </div>

    @if ($customer->status === 'provisioning_finishing')
        <div class="bg-surface-container border border-outline-variant rounded-xl p-lg mb-lg">
            <div class="text-[14px] font-semibold text-on-surface mb-md">Readiness</div>
            @if ($readinessProbe)
                <div class="flex gap-md text-[13px]">
                    <span class="text-on-surface-variant min-w-[120px]">Status</span>
                    <span class="text-on-surface">
                        tentativa {{ $readinessProbe->payload['attempt'] ?? '?' }}/{{ $maxReadinessAttempts }}
                        — último erro: {{ $readinessProbe->payload['error'] ?? '—' }}
                    </span>
                </div>
            @else
                <div class="text-[13px] text-on-surface-variant">Aguardando primeira verificação…</div>
            @endif
        </div>
    @endif

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg mb-lg overflow-x-auto">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Jobs recentes</div>
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="border-b border-outline-variant">
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Job ID</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Tipo</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Estado</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Saída</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Enfileirado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40">
                @forelse ($jobs as $job)
                    <tr class="hover:bg-surface-container-high transition-colors">
                        <td class="px-md py-sm font-mono text-[12px]">
                            <a href="{{ route('queue.show', $job->job_id) }}" class="text-primary hover:underline no-underline">
                                {{ Str::limit($job->job_id, 8, '') }}…
                            </a>
                        </td>
                        <td class="px-md py-sm font-mono text-[12px] text-on-surface">{{ $job->job_type }}</td>
                        <td class="px-md py-sm">
                            <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $jobStateColors[$job->state] ?? $defaultBadgeClass }}">
                                {{ $job->state }}
                            </span>
                        </td>
                        <td class="px-md py-sm font-mono text-[12px] text-on-surface">{{ $job->exit_code !== null ? $job->exit_code : '—' }}</td>
                        <td class="px-md py-sm whitespace-nowrap text-[12px] text-on-surface-variant">{{ $job->queued_at?->format('d/m H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-md py-xl text-center text-on-surface-variant">Nenhum job.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if (! empty($runningJobTail))
            <div class="mt-md rounded-md border border-outline-variant bg-surface-container-lowest p-md">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant mb-sm">Log em execução (últimas linhas)</div>
                @foreach ($runningJobTail as $line)
                    <div class="font-mono text-[11px] text-on-surface leading-relaxed whitespace-pre-wrap break-all">{{ $line }}</div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg overflow-x-auto">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Audit trail</div>
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr class="border-b border-outline-variant">
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Ação</th>
                    <th class="text-left text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant px-md py-sm">Quando</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40">
                @forelse ($auditLogs as $log)
                    <tr class="hover:bg-surface-container-high transition-colors">
                        <td class="px-md py-sm font-mono text-[12px] text-on-surface">{{ $log->action }}</td>
                        <td class="px-md py-sm whitespace-nowrap text-[12px] text-on-surface-variant">{{ $log->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-md py-xl text-center text-on-surface-variant">Sem entradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showRemoveModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" x-data="{ confirmInput: $wire.entangle('confirmInput') }">
        <div class="w-full max-w-[460px] rounded-xl border border-error/50 bg-surface-container p-lg mx-md">
            <div class="text-[16px] font-bold text-error mb-md">⚠ Remover customer</div>
            <div class="text-[13px] text-on-surface-variant leading-relaxed mb-md">
                Esta operação é <strong class="text-on-surface">irreversível</strong>. O Nextcloud do cliente será removido no upstream.
                <br><br>
                Para confirmar, digite exatamente:
                <code class="rounded bg-surface-container-lowest px-xs py-[2px] text-error">{{ $customer->slug }}</code>
                <input
                    type="text"
                    class="mt-sm w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-error"
                    x-model="confirmInput"
                    placeholder="Digite o slug para confirmar"
                    autocomplete="off"
                >
                @if ($removeError)
                    <p class="mt-1 text-[12px] text-error">{{ $removeError }}</p>
                @endif

                <div class="mt-md flex items-center gap-sm">
                    <input type="checkbox" wire:model="backupFirst" id="backupFirst" checked>
                    <label for="backupFirst" class="text-[13px] text-on-surface-variant cursor-pointer">
                        Fazer backup antes de remover (--backup-first)
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-md mt-lg">
                <button
                    type="button"
                    class="rounded border border-outline-variant px-md py-sm text-[13px] text-on-surface-variant hover:border-primary"
                    wire:click="$set('showRemoveModal', false); $set('confirmInput', '')"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="rounded-md border border-error/50 bg-error-container px-md py-sm text-[13px] text-error hover:opacity-90"
                    wire:click="remove"
                    :disabled="confirmInput !== '{{ $customer->slug }}'"
                >
                    Confirmar remoção
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
