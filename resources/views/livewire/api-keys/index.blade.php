<div class="max-w-[1400px] mx-auto space-y-gutter">

    {{-- ===== Page Header ===== --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Credenciais de API</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs max-w-2xl">
                Gerencie as chaves de autenticação Bearer utilizadas por sistemas externos para consumir esta API.
                Mantenha-as seguras e rotacione periodicamente.
            </p>
        </div>
        <div class="shrink-0">
            <button
                onclick="alert('Geração de credenciais disponível na Sprint 2.')"
                class="bg-primary text-on-primary font-semibold text-[12px] uppercase tracking-wide rounded px-lg py-[10px] hover:bg-primary-fixed transition-all flex items-center gap-sm shadow-[0_0_15px_-3px_rgba(173,198,255,0.25)]">
                <span class="material-symbols-outlined" style="font-size:18px">key</span>
                Gerar Nova Credencial
            </button>
        </div>
    </div>

    {{-- ===== Filters ===== --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg px-md py-sm flex items-center gap-sm">
        <span class="text-[12px] uppercase tracking-wide text-on-surface-variant">Filtrar:</span>
        <div class="flex gap-xs">
            @foreach (['all' => 'Todas', 'active' => 'Ativas', 'revoked' => 'Revogadas'] as $val => $label)
                <button wire:click="$set('filterStatus', '{{ $val === 'all' ? '' : $val }}')"
                        class="px-md py-xs text-[12px] font-semibold uppercase tracking-wide rounded transition-colors
                               {{ ($filterStatus === '' && $val === 'all') || $filterStatus === $val
                                  ? 'bg-secondary-container text-on-secondary-container'
                                  : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-variant' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="ml-auto text-[12px] text-on-surface-variant">
            {{ $keys->total() }} {{ $keys->total() === 1 ? 'chave' : 'chaves' }}
        </div>
    </div>

    {{-- ===== Table ===== --}}
    <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden">
        {{-- Table Header --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-surface-container border-b border-outline-variant sticky top-0 z-10">
                    <tr>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Nome</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Chave (mascarada)</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Criada em</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Último uso</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md text-right">Status</th>
                        <th class="px-lg py-md w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($keys as $key)
                        @php $revoked = $key->revoked_at !== null; @endphp
                        <tr class="hover:bg-surface-container-low/80 transition-colors group {{ $revoked ? 'opacity-60' : '' }}">
                            {{-- Name --}}
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-on-surface-variant" style="font-size:18px">
                                        {{ $revoked ? 'key_off' : 'vpn_key' }}
                                    </span>
                                    <span class="font-semibold text-[14px] text-on-surface {{ $revoked ? 'line-through decoration-outline-variant' : '' }}">
                                        {{ $key->name }}
                                    </span>
                                </div>
                            </td>
                            {{-- Masked key --}}
                            <td class="px-lg py-md">
                                <div class="inline-flex items-center gap-sm bg-black border {{ $revoked ? 'border-outline-variant/30 opacity-60 cursor-not-allowed' : 'border-outline-variant/60 hover:border-primary/50 cursor-pointer' }} rounded px-sm py-xs transition-colors group/code">
                                    <code class="font-mono text-[12px] text-secondary tracking-wider">
                                        sk_••••••••••••••••••••{{ strtoupper(substr(str_replace('-', '', $key->id), -4)) }}
                                    </code>
                                    @if (!$revoked)
                                        <span class="material-symbols-outlined text-on-surface-variant group-hover/code:text-primary transition-colors" style="font-size:14px">content_copy</span>
                                    @else
                                        <span class="material-symbols-outlined text-outline-variant" style="font-size:14px">block</span>
                                    @endif
                                </div>
                            </td>
                            {{-- Created --}}
                            <td class="px-lg py-md text-[12px] text-on-surface-variant">
                                {{ $key->created_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            {{-- Last used --}}
                            <td class="px-lg py-md">
                                @if ($key->last_used_at)
                                    @php $stale = $key->last_used_at->diffInDays() > 90; @endphp
                                    <span class="text-[12px] {{ $stale ? 'text-tertiary' : 'text-on-surface-variant' }} flex items-center gap-xs">
                                        @if ($stale)
                                            <span class="material-symbols-outlined" style="font-size:14px">warning</span>
                                        @endif
                                        {{ $key->last_used_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-[12px] text-outline">Nunca usada</span>
                                @endif
                            </td>
                            {{-- Status --}}
                            <td class="px-lg py-md text-right">
                                <span class="inline-flex items-center px-sm py-[3px] rounded text-[11px] font-semibold uppercase tracking-wide
                                    {{ $revoked
                                       ? 'bg-error/10 text-error border border-error/20'
                                       : 'bg-primary/10 text-primary border border-primary/20' }}">
                                    {{ $revoked ? 'Revogada' : 'Ativa' }}
                                </span>
                            </td>
                            {{-- Actions --}}
                            <td class="px-lg py-md text-right">
                                <button class="text-on-surface-variant hover:text-on-surface opacity-0 group-hover:opacity-100 transition-opacity"
                                        title="Mais ações">
                                    <span class="material-symbols-outlined" style="font-size:20px">more_vert</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-lg py-xl text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-outline block mx-auto mb-sm" style="font-size:32px">vpn_key_off</span>
                                Nenhuma credencial cadastrada.
                                <p class="text-[12px] text-outline mt-xs">Use a API para criar a primeira credencial.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-lg py-sm border-t border-outline-variant bg-surface flex items-center justify-between">
            <span class="text-[12px] text-on-surface-variant">
                Página {{ $keys->currentPage() }} de {{ $keys->lastPage() }}
            </span>
            <div class="text-[13px]">
                {{ $keys->links() }}
            </div>
        </div>
    </div>

</div>
