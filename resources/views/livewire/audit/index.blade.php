<div class="max-w-[1400px] mx-auto space-y-gutter">

    {{-- ===== Page Header ===== --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Logs de Requisição</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs">
                Histórico de operações registradas na API. Retenção: 12 meses (LGPD).
            </p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <a href="{{ route('dashboard') }}"
               class="px-md py-sm bg-surface-container border border-outline-variant text-on-surface text-[12px] font-semibold uppercase tracking-wide hover:bg-surface-variant rounded transition-colors flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:16px">arrow_back</span>
                Dashboard
            </a>
        </div>
    </div>

    {{-- ===== Filters ===== --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg px-md py-sm flex flex-wrap items-center gap-sm">
        <div class="flex-1 min-w-[180px] relative">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant" style="font-size:16px">filter_alt</span>
            <input type="text"
                   wire:model.live.debounce.300ms="filterAction"
                   class="w-full bg-surface-container-lowest border border-outline-variant rounded py-sm pl-9 pr-md text-[13px] text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                   placeholder="Filtrar por ação...">
        </div>
        <div class="relative">
            <select wire:model.live="filterResource"
                    class="appearance-none bg-surface-container-lowest border border-outline-variant rounded py-sm pl-md pr-9 text-[13px] text-on-surface focus:outline-none focus:border-primary cursor-pointer">
                <option value="">Todos os recursos</option>
                <option value="cluster_server">cluster_server</option>
                <option value="operator">operator</option>
                <option value="customer">customer</option>
                <option value="job">job</option>
                <option value="api_key">api_key</option>
            </select>
            <span class="material-symbols-outlined absolute right-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none" style="font-size:16px">expand_more</span>
        </div>
        @if ($filterAction || $filterResource)
            <button wire:click="$set('filterAction', ''); $set('filterResource', '')"
                    class="px-md py-sm border border-outline-variant text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant hover:text-on-surface hover:border-outline rounded transition-colors flex items-center gap-xs">
                <span class="material-symbols-outlined" style="font-size:14px">close</span>
                Limpar
            </button>
        @endif
        <div class="ml-auto text-[12px] text-on-surface-variant">
            {{ $logs->total() }} {{ $logs->total() === 1 ? 'registro' : 'registros' }}
        </div>
    </div>

    {{-- ===== Data Table ===== --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container border-b border-outline-variant">
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap">Data / Hora</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Ação</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Recurso</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px]">Operador</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] whitespace-nowrap">IP</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-md py-[10px] text-center">Detalhes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-surface-container transition-colors group" x-data="{ open: false }">
                            <td class="px-md py-[10px] whitespace-nowrap">
                                <div class="font-mono text-[12px] text-on-surface-variant">
                                    {{ $log->created_at?->format('H:i:s') ?? '—' }}
                                </div>
                                <div class="text-[10px] text-outline mt-0.5">
                                    {{ $log->created_at?->format('d/m/Y') ?? '' }}
                                </div>
                            </td>
                            <td class="px-md py-[10px]">
                                <code class="font-mono text-[12px] bg-surface-container-highest/80 border border-outline-variant/30 px-sm py-0.5 rounded text-primary">
                                    {{ $log->action }}
                                </code>
                            </td>
                            <td class="px-md py-[10px]">
                                <span class="text-[11px] uppercase tracking-wide text-on-surface-variant/60">{{ $log->resource_type }}</span>
                                <div class="font-mono text-[11px] text-on-surface-variant truncate max-w-[140px]">
                                    {{ Str::limit($log->resource_id, 16) }}
                                </div>
                            </td>
                            <td class="px-md py-[10px] text-[13px] text-on-surface-variant">
                                {{ $log->actor?->name ?? ($log->actor_id ? Str::limit($log->actor_id, 12) : '—') }}
                            </td>
                            <td class="px-md py-[10px] whitespace-nowrap">
                                <span class="font-mono text-[11px] text-on-surface-variant">
                                    {{ $log->ip ?? '—' }}
                                </span>
                            </td>
                            <td class="px-md py-[10px] text-center">
                                @if ($log->payload)
                                    <button @click="open = !open"
                                            class="p-[4px] text-on-surface-variant hover:text-primary hover:bg-surface-variant rounded transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100">
                                        <span class="material-symbols-outlined" style="font-size:18px">data_object</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($log->payload)
                            <tr x-show="open" x-collapse class="bg-surface-container-lowest">
                                <td colspan="6" class="px-md pb-sm">
                                    <pre class="font-mono text-[11px] text-on-surface-variant bg-surface-dim rounded p-sm overflow-x-auto max-h-[160px] leading-relaxed">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-md py-xl text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-outline block mx-auto mb-sm" style="font-size:32px">receipt_long</span>
                                Nenhum registro de auditoria encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="border-t border-outline-variant bg-surface-container px-md py-sm flex items-center justify-between">
            <span class="text-[12px] text-on-surface-variant">
                Exibindo {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} de {{ $logs->total() }}
            </span>
            <div class="text-[13px]">
                {{ $logs->links() }}
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script src="//cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
@endpush
