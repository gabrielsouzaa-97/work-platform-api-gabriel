<div class="max-w-[1400px] mx-auto space-y-gutter">

    <div class="flex flex-col gap-xs">
        <h2 class="font-bold text-[28px] leading-tight text-on-surface">
            Configurações — IP Allowlist Webhook
        </h2>
        <p class="text-[13px] text-on-surface-variant max-w-[720px]">
            Configure o IP de origem permitido para recebimento de webhooks do upstream.
            Deixe em branco para aceitar qualquer IP autenticado.
        </p>
    </div>

    <section class="bg-surface-container border border-outline-variant rounded-xl overflow-hidden">
        <form wire:submit="save">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="border-b border-outline-variant bg-surface-container-high">
                        <tr>
                            <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">
                                Cluster
                            </th>
                            <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px]">
                                Host SSH
                            </th>
                            <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-[10px] min-w-[220px]">
                                IP permitido (webhook)
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse ($clusters as $cluster)
                            @php
                                $fieldName = 'allowedIps.' . $cluster->id;
                                $ipRaw = $allowedIps[$cluster->id] ?? '';
                                $ipStr = is_string($ipRaw) ? trim($ipRaw) : '';
                                $ipInvalidFilled = $ipStr !== ''
                                    && filter_var($ipStr, FILTER_VALIDATE_IP) === false;
                            @endphp
                            <tr class="hover:bg-surface-container-high transition-colors">
                                <td class="px-lg py-md align-top">
                                    <p class="font-semibold text-[13px] text-on-surface">{{ $cluster->name }}</p>
                                    <code class="font-mono text-[10px] text-outline select-all">{{ $cluster->id }}</code>
                                </td>
                                <td class="px-lg py-md align-top">
                                    <span class="font-mono text-[12px] text-on-surface-variant">{{ $cluster->ssh_host }}</span>
                                </td>
                                <td class="px-lg py-md align-top">
                                    <input
                                        type="text"
                                        inputmode="text"
                                        autocomplete="off"
                                        wire:model.live="allowedIps.{{ $cluster->id }}"
                                        class="w-full rounded-lg bg-surface-container-low border px-md py-[10px] text-[13px] text-on-surface border-outline-variant focus:outline-none focus:ring-2 focus:ring-primary/40 placeholder:text-on-surface-variant/60 font-mono"
                                        placeholder="ex.: 203.0.113.42"
                                        aria-invalid="{{ $errors->has($fieldName) ? 'true' : 'false' }}"
                                    />
                                    @error($fieldName)
                                        <p class="mt-xs text-[12px] text-error">{{ $message }}</p>
                                    @enderror
                                    @if (! $errors->has($fieldName) && $ipInvalidFilled)
                                        <p class="mt-xs text-[12px] text-error">
                                            Digite um endereço IPv4 ou IPv6 válido.
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-lg py-xl text-[13px] text-on-surface-variant text-center">
                                    Nenhum cluster ativo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-lg py-md border-t border-outline-variant bg-surface-container-low flex justify-end gap-md">
                <button
                    type="submit"
                    class="bg-primary text-on-primary font-semibold text-[12px] uppercase tracking-wide rounded px-lg py-[10px] hover:bg-primary-fixed transition-colors"
                >
                    Salvar configuração
                </button>
            </div>
        </form>
    </section>
</div>
