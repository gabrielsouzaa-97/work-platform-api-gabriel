<div class="max-w-[1200px] mx-auto space-y-gutter">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h2 class="font-bold text-[28px] leading-tight text-on-surface">Planos</h2>
            <p class="text-[13px] text-on-surface-variant mt-xs max-w-2xl">
                Gerencie planos comerciais, quotas padrão e limites por tenant.
            </p>
        </div>
        <button
            wire:click="openCreate"
            type="button"
            class="bg-primary text-on-primary font-semibold text-[12px] uppercase tracking-wide rounded px-lg py-[10px] hover:bg-primary-fixed transition-all flex items-center gap-sm"
        >
            <span class="material-symbols-outlined" style="font-size:18px">add</span>
            Novo plano
        </button>
    </div>

    <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-surface-container border-b border-outline-variant">
                <tr>
                    <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Slug</th>
                    <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Nome</th>
                    <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Quota padrão</th>
                    <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Status</th>
                    <th class="text-[11px] uppercase tracking-wide text-on-surface-variant px-lg py-md">Default</th>
                    <th class="px-lg py-md"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40 text-[13px] text-on-surface">
                @forelse ($plans as $plan)
                    <tr class="hover:bg-surface-container-low/80 transition-colors">
                        <td class="px-lg py-md font-mono text-[13px] text-on-surface">{{ $plan->slug }}</td>
                        <td class="px-lg py-md text-[14px] text-on-surface">{{ $plan->name }}</td>
                        <td class="px-lg py-md text-[13px] text-on-surface-variant">{{ $plan->default_quota }}</td>
                        <td class="px-lg py-md text-[13px] text-on-surface-variant">{{ $plan->status }}</td>
                        <td class="px-lg py-md text-[13px] text-on-surface-variant">
                            {{ $plan->is_default ? 'Sim' : 'Não' }}
                        </td>
                        <td class="px-lg py-md text-right">
                            <button
                                type="button"
                                wire:click="openEdit('{{ $plan->slug }}')"
                                class="text-primary text-[12px] font-semibold uppercase tracking-wide hover:underline"
                            >
                                Editar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-lg py-xl text-center text-on-surface-variant text-[13px]">
                            Nenhum plano cadastrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-md">
            <div class="bg-surface border border-outline-variant rounded-xl p-lg w-full max-w-[32rem] space-y-md">
                <h3 class="text-[18px] font-semibold text-on-surface">Novo plano</h3>
                <div class="space-y-sm">
                    <input wire:model="createSlug" type="text" placeholder="slug" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface placeholder:text-on-surface-variant outline-none focus:border-primary">
                    <input wire:model="createName" type="text" placeholder="Nome" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface placeholder:text-on-surface-variant outline-none focus:border-primary">
                    <input wire:model="createDefaultQuota" type="text" placeholder="Quota padrão" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface placeholder:text-on-surface-variant outline-none focus:border-primary">
                    <x-select-menu
                        model="createStatus"
                        :selected="$createStatus"
                        :options="['active' => 'active', 'inactive' => 'inactive']"
                    />
                    <label class="flex items-center gap-sm text-[13px] text-on-surface">
                        <input type="checkbox" wire:model="createIsDefault">
                        Plano padrão da plataforma
                    </label>
                </div>
                <div class="flex justify-end gap-sm">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="px-md py-sm text-[12px] uppercase text-on-surface-variant">Cancelar</button>
                    <button type="button" wire:click="create" class="bg-primary text-on-primary px-md py-sm text-[12px] uppercase rounded">Salvar</button>
                </div>
            </div>
        </div>
    @endif

    @if ($editSlug)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-md">
            <div class="bg-surface border border-outline-variant rounded-xl p-lg w-full max-w-[32rem] space-y-md">
                <h3 class="text-[18px] font-semibold text-on-surface">Editar plano</h3>
                <p class="text-[12px] text-on-surface-variant font-mono">{{ $editSlug }}</p>
                <div class="space-y-sm">
                    <input wire:model="editName" type="text" placeholder="Nome" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface placeholder:text-on-surface-variant outline-none focus:border-primary">
                    <input wire:model="editDefaultQuota" type="text" placeholder="Quota padrão" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface placeholder:text-on-surface-variant outline-none focus:border-primary">
                    <x-select-menu
                        model="editStatus"
                        :selected="$editStatus"
                        :options="['active' => 'active', 'inactive' => 'inactive']"
                    />
                    <label class="flex items-center gap-sm text-[13px] text-on-surface">
                        <input type="checkbox" wire:model="editIsDefault">
                        Plano padrão da plataforma
                    </label>
                </div>
                <div class="flex justify-end gap-sm">
                    <button type="button" wire:click="$set('editSlug', null)" class="px-md py-sm text-[12px] uppercase text-on-surface-variant">Cancelar</button>
                    <button type="button" wire:click="save" class="bg-primary text-on-primary px-md py-sm text-[12px] uppercase rounded">Salvar</button>
                </div>
            </div>
        </div>
    @endif
</div>
