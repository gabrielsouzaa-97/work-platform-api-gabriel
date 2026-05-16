<div class="space-y-gutter">
    <nav class="text-[13px] text-on-surface-variant">
        <a href="{{ route('operators.index') }}" class="text-primary hover:underline">Operadores</a>
        <span class="mx-1 text-outline-variant">/</span>
        <span class="text-on-surface">Editar</span>
    </nav>

    <header>
        <h1 class="text-[1.25rem] font-semibold text-on-surface">
            Editar Operador — {{ $operator->name }}
        </h1>
    </header>

    @if (auth()->id() === $operator->id)
        <div
            class="rounded-lg border border-outline-variant bg-surface-container-low px-md py-sm text-[13px] text-on-surface-variant"
            role="status"
        >
            Você está editando seu próprio perfil.
        </div>
    @endif

    <div class="max-w-[480px] rounded-lg border border-outline-variant bg-surface-container-low p-lg">
        <form wire:submit="save" class="space-y-[1.25rem]">
            <div>
                <label for="edit-operator-name" class="mb-1.5 block text-[13px] font-medium text-on-surface-variant">Nome</label>
                <input
                    id="edit-operator-name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    class="w-full rounded-md border bg-surface-container px-3 py-2.5 text-[14px] text-on-surface outline-none focus:border-primary border-outline-variant {{ $errors->has('name') ? 'border-error' : '' }}"
                >
                @error('name')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="edit-operator-role" class="mb-1.5 block text-[13px] font-medium text-on-surface-variant">Perfil</label>
                <select
                    id="edit-operator-role"
                    wire:model="role"
                    class="w-full rounded-md border border-outline-variant bg-surface-container px-3 py-2.5 text-[14px] text-on-surface outline-none focus:border-primary {{ $errors->has('role') ? 'border-error' : '' }}"
                >
                    <option value="operador">Operador</option>
                    <option value="suporte">Suporte</option>
                    <option value="admin">Admin</option>
                </select>
                @error('role')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="edit-operator-status" class="mb-1.5 block text-[13px] font-medium text-on-surface-variant">Status</label>
                <select
                    id="edit-operator-status"
                    wire:model="status"
                    class="w-full rounded-md border border-outline-variant bg-surface-container px-3 py-2.5 text-[14px] text-on-surface outline-none focus:border-primary {{ $errors->has('status') ? 'border-error' : '' }}"
                >
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
                </select>
                @error('status')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-md bg-primary-container px-5 py-2.5 text-[13px] font-semibold text-on-primary-container hover:opacity-95 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">Salvar alterações</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                </button>
                <a
                    href="{{ route('operators.index') }}"
                    class="inline-flex items-center rounded-md border border-outline-variant px-5 py-2.5 text-[13px] text-on-surface-variant hover:border-primary hover:text-on-surface"
                >
                    Cancelar
                </a>
            </div>
        </form>

        @if ($showResendInvite)
            <div class="mt-lg border-t border-outline-variant pt-lg">
                <p class="mb-3 text-[13px] text-on-surface-variant">Convite pendente</p>
                <button
                    type="button"
                    wire:click="resendInvite"
                    wire:loading.attr="disabled"
                    class="rounded-md border border-outline-variant px-5 py-2.5 text-[13px] font-medium text-on-surface hover:border-primary"
                >
                    <span wire:loading.remove wire:target="resendInvite">Reenviar convite</span>
                    <span wire:loading wire:target="resendInvite">Enviando...</span>
                </button>
            </div>
        @endif
    </div>
</div>
