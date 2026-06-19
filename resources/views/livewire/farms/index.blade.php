<div class="max-w-[1400px] mx-auto space-y-gutter">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Fazendas</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs">
                Capacidade reportada pelos agentes de fazenda via operação farm.inventory.
            </p>
        </div>
    </div>

    <section class="bg-surface-container border border-outline-variant rounded-xl overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant bg-surface-container-high">
            <h3 class="font-semibold text-[16px] text-on-surface">Inventário de capacidade</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="border-b border-outline-variant">
                    <tr class="bg-surface-container">
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Farm ID</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Capacidade</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Versão</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Latência</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Reportado em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($inventories as $inventory)
                        <tr class="hover:bg-surface-container-high transition-colors">
                            <td class="px-lg py-md">
                                <code class="font-mono text-[13px] text-on-surface">{{ $inventory->farm_id }}</code>
                            </td>
                            <td class="px-lg py-md">
                                <span class="text-[13px] text-on-surface">
                                    {{ $inventory->active_tenants }} / {{ $inventory->max_tenants }}
                                </span>
                            </td>
                            <td class="px-lg py-md">
                                <code class="font-mono text-[12px] text-on-surface-variant">{{ $inventory->platform_version }}</code>
                            </td>
                            <td class="px-lg py-md">
                                <span class="text-[13px] text-on-surface-variant">{{ $inventory->latency_ms }} ms</span>
                            </td>
                            <td class="px-lg py-md whitespace-nowrap">
                                <span class="text-[12px] text-on-surface-variant">
                                    {{ $inventory->reported_at?->format('d/m/Y H:i') ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-lg py-xl text-center text-on-surface-variant text-[13px]">
                                Nenhum inventário de fazenda reportado ainda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
