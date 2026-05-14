<div class="max-w-[1400px] mx-auto space-y-gutter">

    {{-- ===== Page Header ===== --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Logs de Provisionamento</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs">
                Jobs de provisionamento Nextcloud orquestrados via SSH/webhook com o upstream.
            </p>
        </div>
    </div>

    {{-- ===== Filters ===== --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg px-md py-sm flex flex-wrap items-center gap-sm">
        {{-- State filter --}}
        <div class="relative">
            <select wire:model.live="stateFilter"
                    class="appearance-none bg-surface-container-lowest border border-outline-variant rounded py-sm pl-md pr-9 text-[13px] text-on-surface focus:outline-none focus:border-primary cursor-pointer">
                <option value="">Todos os estados</option>
                <option value="queued">queued</option>
                <option value="running">running</option>
                <option value="success">success</option>
                <option value="failed">failed</option>
                <option value="cancelled">cancelled</option>
            </select>
            <span class="material-symbols-outlined absolute right-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none" style="font-size:16px">expand_more</span>
        </div>
        {{-- Job type --}}
        <div class="relative">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:16px">filter_alt</span>
            <input type="text"
                   wire:model.live.debounce.300ms="jobTypeFilter"
                   class="w-full min-w-[140px] bg-surface-container-lowest border border-outline-variant rounded py-sm pl-9 pr-md text-[13px] text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:border-primary transition-colors"
                   placeholder="Tipo (provision...)">
        </div>
        {{-- Customer --}}
        <div class="relative">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:16px">person_search</span>
            <input type="text"
                   wire:model.live.debounce.300ms="customerFilter"
                   class="w-full min-w-[140px] bg-surface-container-lowest border border-outline-variant rounded py-sm pl-9 pr-md text-[13px] text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:border-primary transition-colors"
                   placeholder="Customer slug...">
        </div>
        @if ($stateFilter || $jobTypeFilter || $customerFilter)
            <button wire:click="$set('stateFilter', ''); $set('jobTypeFilter', ''); $set('customerFilter', '')"
                    class="px-md py-sm border border-outline-variant text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant hover:text-on-surface rounded transition-colors flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:14px">close</span>
                Limpar
            </button>
        @endif
        <div class="ml-auto text-[12px] text-on-surface-variant">
            {{ $jobs->total() }} {{ $jobs->total() === 1 ? 'job' : 'jobs' }}
        </div>
    </div>

    {{-- ===== Data Table ===== --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden">
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($jobs as $job)
                        <tr class="hover:bg-surface-container transition-colors group">
                            {{-- Job ID --}}
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
                            {{-- Customer --}}
                            <td class="px-md py-[12px]">
                                <code class="font-mono text-[13px] text-on-surface">{{ $job->customer_slug }}</code>
                            </td>
                            {{-- Type --}}
                            <td class="px-md py-[12px]">
                                <code class="font-mono text-[12px] bg-surface-container-highest px-xs py-0.5 rounded text-secondary">
                                    {{ $job->job_type }}
                                </code>
                            </td>
                            {{-- State --}}
                            <td class="px-md py-[12px]">
                                <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide state-{{ $job->state }}">
                                    @if ($job->state === 'running')
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse block"></span>
                                    @endif
                                    {{ $job->state }}
                                </span>
                            </td>
                            {{-- Exit code --}}
                            <td class="px-md py-[12px]">
                                @if ($job->exit_code !== null)
                                    <code class="font-mono text-[12px] {{ $job->exit_code === 0 ? 'text-[#6ad191]' : 'text-error' }}">
                                        {{ $job->exit_code }}
                                    </code>
                                @else
                                    <span class="text-outline text-[12px]">—</span>
                                @endif
                            </td>
                            {{-- Queued at --}}
                            <td class="px-md py-[12px] whitespace-nowrap">
                                <div class="font-mono text-[12px] text-on-surface-variant">
                                    {{ $job->queued_at?->format('H:i') ?? '—' }}
                                </div>
                                <div class="text-[10px] text-outline">
                                    {{ $job->queued_at?->format('d/m') ?? '' }}
                                </div>
                            </td>
                            {{-- Finished at --}}
                            <td class="px-md py-[12px] whitespace-nowrap">
                                <div class="font-mono text-[12px] text-on-surface-variant">
                                    {{ $job->finished_at?->format('H:i') ?? '—' }}
                                </div>
                                <div class="text-[10px] text-outline">
                                    {{ $job->finished_at?->format('d/m') ?? '' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-md py-xl text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-outline block mx-auto mb-sm" style="font-size:32px">cloud_off</span>
                                Nenhum job de provisionamento encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
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
