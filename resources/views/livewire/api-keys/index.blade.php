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
                wire:click="openCreate"
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
                                        <span class="material-symbols-outlined text-outline-variant" style="font-size:14px" title="Token mascarado — não pode ser recuperado">key_off</span>
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
                                @if (!$revoked)
                                    <button
                                        wire:click="revoke('{{ $key->id }}')"
                                        wire:confirm="Revogar a credencial '{{ addslashes($key->name) }}'? Esta ação não pode ser desfeita."
                                        wire:loading.attr="disabled"
                                        wire:target="revoke('{{ $key->id }}')"
                                        class="text-on-surface-variant hover:text-error opacity-0 group-hover:opacity-100 transition-all disabled:opacity-40"
                                        title="Revogar credencial"
                                        aria-label="Revogar credencial {{ $key->name }}">
                                        <span class="material-symbols-outlined" style="font-size:18px">block</span>
                                    </button>
                                @else
                                    <span class="text-outline-variant opacity-0 group-hover:opacity-40 transition-opacity" title="Credencial já revogada">
                                        <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-lg py-xl text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-outline block mx-auto mb-sm" style="font-size:32px">vpn_key_off</span>
                                Nenhuma credencial cadastrada.
                                <p class="text-[12px] text-outline mt-xs">Clique em "Gerar Nova Credencial" para criar a primeira.</p>
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

    {{-- ===== Modal: Gerar Nova Credencial ===== --}}
    @if ($showCreateModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-data
            x-on:keydown.escape.window="$wire.set('showCreateModal', false)">
            {{-- Backdrop --}}
            <div
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                wire:click="$set('showCreateModal', false)">
            </div>

            {{-- Modal panel --}}
            <div class="relative w-full max-w-lg mx-4 rounded-xl border border-outline-variant bg-surface-container-low p-lg shadow-2xl">
                <div class="flex items-start justify-between mb-md">
                    <div>
                        <h3 class="font-semibold text-[18px] text-on-surface">Gerar Nova Credencial</h3>
                        <p class="text-[12px] text-on-surface-variant mt-xs">
                            O token será exibido <strong>uma única vez</strong> após a criação. Guarde-o em local seguro.
                        </p>
                    </div>
                    <button
                        wire:click="$set('showCreateModal', false)"
                        class="text-on-surface-variant hover:text-on-surface transition-colors ml-md shrink-0"
                        aria-label="Fechar modal">
                        <span class="material-symbols-outlined" style="font-size:22px">close</span>
                    </button>
                </div>

                @error('createName')
                    <div class="mb-md rounded-lg bg-error/10 border border-error/20 px-md py-sm text-[13px] text-error" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                <form wire:submit="create">
                    <div class="space-y-md">
                        <div>
                            <label for="createName" class="block text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant mb-xs">
                                Nome da credencial <span class="text-error">*</span>
                            </label>
                            <input
                                id="createName"
                                type="text"
                                wire:model.blur="createName"
                                placeholder="Ex: Integração ERP, Sistema de Billing…"
                                autofocus
                                class="w-full bg-surface border border-outline-variant rounded-lg px-md py-sm text-[13px] text-on-surface placeholder:text-outline
                                       focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors"
                                aria-describedby="createName-hint"
                            />
                            <p id="createName-hint" class="mt-xs text-[11px] text-outline">Mínimo 2 caracteres. Máximo 120.</p>
                        </div>
                    </div>

                    <div class="mt-lg flex items-center justify-end gap-sm">
                        <button
                            type="button"
                            wire:click="$set('showCreateModal', false)"
                            class="px-lg py-[9px] text-[12px] font-semibold uppercase tracking-wide text-on-surface-variant
                                   hover:text-on-surface rounded border border-outline-variant hover:bg-surface-variant transition-colors">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="create"
                            class="bg-primary text-on-primary font-semibold text-[12px] uppercase tracking-wide rounded px-lg py-[9px]
                                   hover:bg-primary-fixed transition-all flex items-center gap-sm disabled:opacity-50">
                            <span wire:loading.remove wire:target="create" class="material-symbols-outlined" style="font-size:16px">key</span>
                            <span wire:loading wire:target="create" class="material-symbols-outlined animate-spin" style="font-size:16px">progress_activity</span>
                            <span wire:loading.remove wire:target="create">Gerar Credencial</span>
                            <span wire:loading wire:target="create">Gerando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal: Token Reveal (exibido UMA vez) ===== --}}
    @if ($showTokenReveal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center"
            x-data
            x-on:keydown.escape.window="$wire.closeTokenReveal()">
            {{-- Backdrop — intencional: nao fechar ao clicar fora para evitar perda do token --}}
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

            {{-- Token panel --}}
            <div class="relative w-full max-w-lg mx-4 rounded-xl border border-primary/30 bg-surface-container-low p-lg shadow-2xl">
                {{-- Warning header --}}
                <div class="flex items-start gap-md mb-md">
                    <div class="w-10 h-10 rounded-full bg-primary/15 border border-primary/30 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary" style="font-size:22px">shield_lock</span>
                    </div>
                    <div>
                        <h3 class="font-semibold text-[18px] text-on-surface">Token gerado com sucesso</h3>
                        <p class="text-[12px] text-on-surface-variant mt-xs">
                            Copie agora — este token <strong class="text-error">não será exibido novamente</strong> após fechar esta janela.
                        </p>
                    </div>
                </div>

                {{-- Token display --}}
                <div
                    class="bg-black border border-outline-variant/60 rounded-lg px-md py-md mb-lg"
                    x-data="{ copied: false }"
                    x-on:click="
                        navigator.clipboard.writeText({{ Js::from($createdToken) }})
                            .then(() => {
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            })
                            .catch(() => {
                                const el = document.createElement('textarea');
                                el.value = {{ Js::from($createdToken) }};
                                el.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
                                document.body.appendChild(el);
                                el.focus(); el.select();
                                document.execCommand('copy');
                                document.body.removeChild(el);
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            });
                    "
                    title="Clique para copiar"
                    role="button"
                    tabindex="0"
                    aria-label="Copiar token de API">
                    <div class="flex items-center justify-between gap-md">
                        <code class="font-mono text-[13px] text-secondary break-all leading-relaxed select-all">
                            {{ $createdToken }}
                        </code>
                        <span
                            x-show="!copied"
                            class="material-symbols-outlined text-on-surface-variant hover:text-primary transition-colors shrink-0 cursor-pointer"
                            style="font-size:20px">
                            content_copy
                        </span>
                        <span
                            x-show="copied"
                            class="material-symbols-outlined text-primary shrink-0"
                            style="font-size:20px">
                            check_circle
                        </span>
                    </div>
                </div>

                <div class="bg-tertiary/8 border border-tertiary/20 rounded-lg px-md py-sm mb-lg flex items-start gap-sm">
                    <span class="material-symbols-outlined text-tertiary shrink-0" style="font-size:16px">info</span>
                    <p class="text-[12px] text-on-surface-variant">
                        Use este token no cabeçalho <code class="font-mono text-secondary">Authorization: Bearer &lt;token&gt;</code> em chamadas à API.
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        wire:click="closeTokenReveal"
                        class="bg-primary text-on-primary font-semibold text-[12px] uppercase tracking-wide rounded px-lg py-[9px]
                               hover:bg-primary-fixed transition-all flex items-center gap-sm">
                        <span class="material-symbols-outlined" style="font-size:16px">check</span>
                        Já copiei, fechar
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
