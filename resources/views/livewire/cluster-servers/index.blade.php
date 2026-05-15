<div class="max-w-[1400px] mx-auto space-y-gutter">

    {{-- ===== Page Header ===== --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Configurações</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs">
                Gerencie servidores upstream, secrets de webhook e conexões SSH para orquestração.
            </p>
        </div>
        <a href="{{ route('cluster-servers.create') }}"
           class="shrink-0 bg-primary text-on-primary font-semibold text-[12px] uppercase tracking-wide rounded px-lg py-[10px] hover:bg-primary-fixed transition-colors flex items-center gap-sm">
            <span class="material-symbols-outlined" style="font-size:18px">add</span>
            Novo Cluster
        </a>
    </div>

    {{-- ===== Section: Cluster Servers ===== --}}
    <section class="bg-surface-container border border-outline-variant rounded-xl overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant bg-surface-container-high flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-[16px] text-on-surface">Servidores de Cluster</h3>
                <p class="text-[12px] text-on-surface-variant mt-xs">
                    Conexões SSH com os servidores upstream nextcloud-saas-manager. Webhook HMAC-SHA256.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="border-b border-outline-variant">
                    <tr class="bg-surface-container">
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Nome / Host</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">SSH</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Status</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">Webhook Secret</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px] whitespace-nowrap">Último health</th>
                        <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px] text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($clusters as $cluster)
                        <tr class="hover:bg-surface-container-high transition-colors group">
                            {{-- Name + host --}}
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded bg-surface-container-highest border border-outline-variant flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-on-surface-variant" style="font-size:16px">dns</span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-[13px] text-on-surface">{{ $cluster->name }}</p>
                                        <p class="font-mono text-[11px] text-on-surface-variant">{{ $cluster->ssh_host }}</p>
                                        <div class="flex items-center gap-xs mt-[2px]">
                                            <code class="font-mono text-[10px] text-outline select-all" title="cluster_server_id">{{ $cluster->id }}</code>
                                            <button
                                                type="button"
                                                title="Copiar ID"
                                                onclick="navigator.clipboard.writeText('{{ $cluster->id }}').then(()=>{ this.textContent='check'; setTimeout(()=>this.textContent='content_copy',1500) })"
                                                class="material-symbols-outlined text-outline hover:text-on-surface transition-colors"
                                                style="font-size:12px;cursor:pointer;background:none;border:none;padding:0">content_copy</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            {{-- SSH --}}
                            <td class="px-lg py-md">
                                <span class="font-mono text-[12px] text-on-surface-variant">{{ $cluster->ssh_user }}@:{{ $cluster->ssh_port }}</span>
                            </td>
                            {{-- Status --}}
                            <td class="px-lg py-md">
                                @php
                                    $statusColors = [
                                        'active'      => 'text-[#6ad191] bg-[#6ad191]/10 border border-[#6ad191]/20',
                                        'unreachable' => 'text-error bg-error/10 border border-error/20',
                                        'inactive'    => 'text-on-surface-variant bg-surface-container-highest border border-outline-variant',
                                    ];
                                @endphp
                                <span class="inline-flex items-center gap-xs px-sm py-[3px] rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $statusColors[$cluster->status] ?? $statusColors['inactive'] }}">
                                    @if ($cluster->status === 'active')
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#6ad191] block"></span>
                                    @elseif ($cluster->status === 'unreachable')
                                        <span class="w-1.5 h-1.5 rounded-full bg-error block"></span>
                                    @endif
                                    {{ $cluster->status }}
                                </span>
                            </td>
                            {{-- Webhook secret --}}
                            <td class="px-lg py-md">
                                <code class="font-mono text-[12px] text-on-surface-variant">
                                    ••••••{{ substr($cluster->webhook_secret_encrypted ?? '????', -4) }}
                                </code>
                            </td>
                            {{-- Last health --}}
                            <td class="px-lg py-md whitespace-nowrap">
                                <span class="text-[12px] text-on-surface-variant">
                                    {{ $cluster->last_health_at?->format('d/m/Y H:i') ?? '—' }}
                                </span>
                            </td>
                            {{-- Actions --}}
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-sm">
                                    <button wire:click="testConnection('{{ $cluster->id }}')"
                                            wire:loading.attr="disabled"
                                            class="px-sm py-[4px] border border-outline-variant text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant hover:text-on-surface hover:border-outline rounded transition-colors flex items-center gap-xs">
                                        <span class="material-symbols-outlined" style="font-size:14px">wifi_tethering</span>
                                        Test
                                    </button>
                                    <button wire:click="rotateSecret('{{ $cluster->id }}')"
                                            wire:confirm="Rotacionar o webhook secret? A versão atual permanece válida por {{ config('services.webhook.grace_period_hours', 24) }}h."
                                            wire:loading.attr="disabled"
                                            class="px-sm py-[4px] border border-outline-variant text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant hover:text-tertiary hover:border-tertiary/50 rounded transition-colors flex items-center gap-xs">
                                        <span class="material-symbols-outlined" style="font-size:14px">autorenew</span>
                                        Rotate
                                    </button>
                                    <a href="{{ route('cluster-servers.edit', $cluster->id) }}"
                                       class="px-sm py-[4px] border border-outline-variant text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant hover:text-on-surface hover:border-outline rounded transition-colors flex items-center gap-xs">
                                        <span class="material-symbols-outlined" style="font-size:14px">edit</span>
                                        Editar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-lg py-xl text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-outline block mx-auto mb-sm" style="font-size:32px">dns</span>
                                Nenhum cluster server cadastrado.
                                <p class="text-[12px] text-outline mt-xs">Adicione um servidor para começar a orquestrar provisionamentos.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-lg py-sm border-t border-outline-variant bg-surface-container">
            {{ $clusters->links() }}
        </div>
    </section>

    {{-- ===== Section: Info ===== --}}
    <section class="grid grid-cols-1 md:grid-cols-2 gap-gutter">
        <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
            <h4 class="font-semibold text-[14px] text-on-surface mb-md flex items-center gap-sm">
                <span class="material-symbols-outlined text-primary" style="font-size:18px">security</span>
                Segurança de Webhook
            </h4>
            <div class="space-y-sm text-[13px] text-on-surface-variant">
                <div class="flex justify-between py-sm border-b border-outline-variant/30">
                    <span>Algoritmo</span>
                    <code class="font-mono text-on-surface">HMAC-SHA256</code>
                </div>
                <div class="flex justify-between py-sm border-b border-outline-variant/30">
                    <span>Replay protection</span>
                    <code class="font-mono text-on-surface">1 hora (TTL)</code>
                </div>
                <div class="flex justify-between py-sm border-b border-outline-variant/30">
                    <span>Grace period (rotate)</span>
                    <code class="font-mono text-on-surface">{{ config('services.webhook.grace_period_hours', 24) }}h</code>
                </div>
                <div class="flex justify-between py-sm">
                    <span>IP whitelist</span>
                    <code class="font-mono text-on-surface">Por cluster server</code>
                </div>
            </div>
        </div>

        <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
            <h4 class="font-semibold text-[14px] text-on-surface mb-md flex items-center gap-sm">
                <span class="material-symbols-outlined text-secondary" style="font-size:18px">terminal</span>
                Padrão SSH
            </h4>
            <div class="space-y-sm text-[13px] text-on-surface-variant">
                <div class="flex justify-between py-sm border-b border-outline-variant/30">
                    <span>Chave SSH</span>
                    <code class="font-mono text-on-surface">Por cluster server</code>
                </div>
                <div class="flex justify-between py-sm border-b border-outline-variant/30">
                    <span>Usuário</span>
                    <code class="font-mono text-on-surface">ncsaas-api</code>
                </div>
                <div class="flex justify-between py-sm border-b border-outline-variant/30">
                    <span>Timeout SSH</span>
                    <code class="font-mono text-on-surface">30s</code>
                </div>
                <div class="flex justify-between py-sm">
                    <span>Async (--async)</span>
                    <code class="font-mono text-on-surface">job_id UUID v4</code>
                </div>
            </div>
        </div>
    </section>

</div>
