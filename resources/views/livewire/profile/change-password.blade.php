<div class="max-w-[1400px] mx-auto space-y-gutter">
    <div>
        <h2 class="font-bold text-[28px] leading-tight text-on-surface">Alterar Senha</h2>
        <p class="text-[13px] text-on-surface-variant mt-xs">
            Atualize sua senha de acesso ao painel.
        </p>
    </div>

    <section class="max-w-lg bg-surface-container border border-outline-variant rounded-xl overflow-hidden">
        <div class="px-lg py-md border-b border-outline-variant bg-surface-container-high">
            <h3 class="font-semibold text-[16px] text-on-surface">Senha de acesso</h3>
            <p class="text-[12px] text-on-surface-variant mt-xs">Use uma senha forte com pelo menos 8 caracteres.</p>
        </div>

        <form wire:submit="save" class="px-lg py-md space-y-md">
            <div class="flex flex-col gap-sm">
                <label for="currentPassword" class="text-[13px] font-semibold text-on-surface">Senha atual</label>
                <input
                    id="currentPassword"
                    type="password"
                    autocomplete="current-password"
                    wire:model.blur="currentPassword"
                    class="w-full bg-surface-container-highest border rounded-xl px-md py-sm text-[13px] text-on-surface border-outline-variant placeholder:text-on-surface-variant focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors @error('currentPassword') border-error @enderror"
                >
                @error('currentPassword')
                    <p class="text-[13px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-sm">
                <label for="newPassword" class="text-[13px] font-semibold text-on-surface">Nova senha</label>
                <input
                    id="newPassword"
                    type="password"
                    autocomplete="new-password"
                    wire:model.blur="newPassword"
                    class="w-full bg-surface-container-highest border rounded-xl px-md py-sm text-[13px] text-on-surface border-outline-variant placeholder:text-on-surface-variant focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors @error('newPassword') border-error @enderror"
                >
                @error('newPassword')
                    <p class="text-[13px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-sm">
                <label for="newPasswordConfirmation" class="text-[13px] font-semibold text-on-surface">Confirmar nova senha</label>
                <input
                    id="newPasswordConfirmation"
                    type="password"
                    autocomplete="new-password"
                    wire:model.blur="newPasswordConfirmation"
                    class="w-full bg-surface-container-highest border rounded-xl px-md py-sm text-[13px] text-on-surface border-outline-variant placeholder:text-on-surface-variant focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors @error('newPasswordConfirmation') border-error @enderror"
                >
                @error('newPasswordConfirmation')
                    <p class="text-[13px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-sm">
                <button
                    type="submit"
                    class="bg-primary text-on-primary font-semibold text-[13px] rounded-xl px-lg py-sm hover:bg-primary-fixed transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">Salvar nova senha</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                </button>
            </div>
        </form>
    </section>
</div>
