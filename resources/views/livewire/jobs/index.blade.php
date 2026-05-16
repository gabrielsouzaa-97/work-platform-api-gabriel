<div class="max-w-[1400px] mx-auto space-y-gutter">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Fila de Provisionamento</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs">
                Jobs de provisionamento Nextcloud orquestrados via SSH/webhook com o upstream.
            </p>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-md">
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md flex items-center gap-md">
            <div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary" style="font-size:20px">play_circle</span>
            </div>
            <div>
                <div class="text-[24px] font-bold text-on-surface">{{ $activeOps }}</div>
                <div class="text-[11px] uppercase tracking-wide text-on-surface-variant">Active Ops</div>
            </div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md flex items-center gap-md">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: rgb(from #6ad191 r g b / 0.15)">
                <span class="material-symbols-outlined" style="font-size:20px;color:#6ad191">check_circle</span>
            </div>
            <div>
                <div class="text-[24px] font-bold text-on-surface">{{ $completed24h }}</div>
                <div class="text-[11px] uppercase tracking-wide text-on-surface-variant">Completed 24h</div>
            </div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md flex items-center gap-md">
            <div class="w-10 h-10 rounded-full bg-error/15 flex items-center justify-center">
                <span class="material-symbols-outlined text-error" style="font-size:20px">error</span>
            </div>
            <div>
                <div class="text-[24px] font-bold text-on-surface">{{ $failed24h }}</div>
                <div class="text-[11px] uppercase tracking-wide text-on-surface-variant">Failed 24h</div>
            </div>
        </div>
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md flex items-center gap-md">
            <div class="w-10 h-10 rounded-full bg-on-surface-variant/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-size:20px">timer</span>
            </div>
            <div>
                <div class="text-[24px] font-bold text-on-surface">
                    {{ $avgProvisionTime !== null ? $avgProvisionTime.'s' : '—' }}
                </div>
                <div class="text-[11px] uppercase tracking-wide text-on-surface-variant">Avg Provision Time</div>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
        <div class="flex border-b border-outline-variant">
            @foreach (['all' => 'All', 'running' => 'Running', 'failed' => 'Failed'] as $tab => $label)
                <button type="button" wire:click="$set('activeTab', '{{ $tab }}')"
                        class="px-md py-sm text-[13px] font-semibold border-b-2 transition-colors
                        {{ $activeTab === $tab ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="px-md py-sm flex flex-wrap items-center gap-sm">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:16px">filter_alt</span>
                <input type="text"
                       wire:model.live.debounce.300ms="jobTypeFilter"
                       class="w-full min-w-[140px] bg-surface-container-lowest border border-outline-variant rounded py-sm pl-9 pr-md text-[13px] text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:border-primary transition-colors"
                       placeholder="Tipo (provision...)">
            </div>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:16px">person_search</span>
                <input type="text"
                       wire:model.live.debounce.300ms="customerFilter"
                       class="w-full min-w-[140px] bg-surface-container-lowest border border-outline-variant rounded py-sm pl-9 pr-md text-[13px] text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:border-primary transition-colors"
                       placeholder="Customer slug...">
            </div>
            <label class="inline-flex items-center gap-xs cursor-pointer select-none text-[13px] text-on-surface-variant">
                <input type="checkbox" wire:model.live="autoRefresh" class="rounded border-outline-variant bg-surface-container-lowest text-primary focus:ring-primary/40">
                <span>Auto-refresh</span>
            </label>
            <button type="button" wire:click="exportCsv"
                    class="ml-auto inline-flex items-center gap-xs px-md py-sm border border-outline-variant rounded text-[12px] font-semibold uppercase tracking-wide text-on-surface hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined" style="font-size:16px">download</span>
                Export
            </button>
            <span class="text-[12px] text-on-surface-variant sm:ml-0">
                {{ $jobs->total() }} {{ $jobs->total() === 1 ? 'job' : 'jobs' }}
            </span>
        </div>
    </div>

    @if ($autoRefresh)
        <div wire:poll.10s class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden">
    @else
        <div class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden">
    @endif
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container border-b border-outline-variant">
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap">Job ID</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Customer</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Tipo</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Estado</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap">Saída</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap">Enfileirado</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap">Concluído</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($jobs as $job)
                        <tr class="hover:bg-surface-container transition-colors group">
                            <td class="px-md py-[12px] whitespace-nowrap">
                                <div class="flex items-center gap-sm">
                                    <div class="w-2 h-2 rounded-full shrink-0
                                        {{ $job->state === 'running' ? 'bg-primary animate-pulse' :
                                           ($job->state === 'success' ? 'bg-[#6ad191]' :
                                           ($job->state === 'failed' ? 'bg-error' :
                                           ($job->state === 'cancelled' ? 'bg-tertiary' : 'bg-outline'))) }}">
                                    </div>
                                    <code class="font-mono text-[12px] text-on-surface-variant">
                                        {{ Str::limit($job->job_id, 8, '') }}…
                                    </code>
                                </div>
                            </td>
                            <td class="px-md py-[12px]">
                                <code class="font-mono text-[13px] text-on-surface">{{ $job->customer_slug }}</code>
                            </td>
                            <td class="px-md py-[12px]">
                                <code class="font-mono text-[12px] bg-surface-container-highest px-xs py-0.5 rounded text-secondary">
                                    {{ $job->job_type }}
                                </code>
                            </td>
                            <td class="px-md py-[12px]">
                                <span class="inline-flex flex-col gap-xs items-start w-full max-w-[140px]">
                                    <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide state-{{ $job->state }}">
                                        @if ($job->state === 'running')
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse block"></span>
                                        @endif
                                        {{ $job->state }}
                                    </span>
                                    @if ($job->state === 'running')
                                        <div class="w-full bg-surface-container-highest rounded-full h-1 mt-xs overflow-hidden">
                                            <div class="h-1 w-[60%] bg-primary rounded-full origin-left animate-[progress_2s_ease-in-out_infinite]"></div>
                                        </div>
                                    @endif
                                </span>
                            </td>
                            <td class="px-md py-[12px]">
                                @if ($job->exit_code !== null)
                                    <code class="font-mono text-[12px] {{ $job->exit_code === 0 ? 'text-[#6ad191]' : 'text-error' }}">
                                        {{ $job->exit_code }}
                                    </code>
                                @else
                                    <span class="text-outline text-[12px]">—</span>
                                @endif
                            </td>
                            <td class="px-md py-[12px] whitespace-nowrap">
                                <div class="font-mono text-[12px] text-on-surface-variant">
                                    {{ $job->queued_at?->format('H:i') ?? '—' }}
                                </div>
                                <div class="text-[10px] text-outline">
                                    {{ $job->queued_at?->format('d/m') ?? '' }}
                                </div>
                            </td>
                            <td class="px-md py-[12px] whitespace-nowrap">
                                <div class="font-mono text-[12px] text-on-surface-variant">
                                    {{ $job->finished_at?->format('H:i') ?? '—' }}
                                </div>
                                <div class="text-[10px] text-outline">
                                    {{ $job->finished_at?->format('d/m') ?? '' }}
                                </div>
                            </td>
                            <td class="px-md py-[12px]">
                                <a href="{{ route('queue.show', $job->job_id) }}" wire:navigate
                                   class="text-[12px] text-primary hover:text-primary/80 flex items-center gap-xs">
                                    <span class="material-symbols-outlined" style="font-size:14px">open_in_new</span>
                                    Logs
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-md py-xl text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-outline block mx-auto mb-sm" style="font-size:32px">cloud_off</span>
                                Nenhum job de provisionamento encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-outline-variant bg-surface-container px-md py-sm flex items-center justify-between">
            <span class="text-[12px] text-on-surface-variant">
                Exibindo {{ $jobs->firstItem() ?? 0 }}–{{ $jobs->lastItem() ?? 0 }} de {{ $jobs->total() }}
            </span>
            <div class="text-[13px]">
                {{ $jobs->links() }}
            </div>
        </div>
    </div>

</div>
