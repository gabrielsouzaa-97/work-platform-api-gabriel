<div class="space-y-gutter">
    <h1 class="text-[1.25rem] font-semibold text-on-surface">Provisionar Customer</h1>

    @if ($errorMessage)
        <div class="bg-error/10 border border-error/30 rounded-md px-md py-sm text-[13px] text-error">{{ $errorMessage }}</div>
    @endif

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg max-w-[640px] overflow-visible">
        <form wire:submit="submit" class="space-y-md">
            <div>
                <label for="slug" class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Slug *</label>
                <input
                    id="slug"
                    type="text"
                    class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary"
                    wire:model.live.debounce.300ms="slug"
                    placeholder="minha-empresa"
                    autocomplete="off"
                >
                <p class="mt-1 text-[11px] text-outline leading-snug">
                    Somente letras minúsculas, números e hífen (ex.: <code>acme-prod</code>). O placeholder não conta como valor — digite o slug.
                </p>
                @error('slug')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="domain" class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Domínio (FQDN) *</label>
                <input
                    id="domain"
                    type="text"
                    class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary"
                    wire:model.blur="domain"
                    placeholder="minha-empresa.image-pilot.mework360.com.br"
                    autocomplete="off"
                >
                <p class="mt-1 text-[11px] text-outline leading-snug">
                    FQDN completo do tenant — não use só <code>image-pilot.mework360.com.br</code>. Padrão image-pilot: <code>&lt;slug&gt;.image-pilot.mework360.com.br</code>.
                </p>
                @if ($normalizedDomain !== '')
                    <p class="mt-1 text-[11px] text-outline">Domínio final: {{ $normalizedDomain }}</p>
                @endif
                @error('domain')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="clusterServerId" class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Cluster Server *</label>
                <select
                    id="clusterServerId"
                    class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary cursor-pointer"
                    wire:model.live="clusterServerId"
                >
                    <option value="">Selecione…</option>
                    @foreach ($clusters as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('clusterServerId')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="planSlug" class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Plano</label>
                <select
                    id="planSlug"
                    class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary cursor-pointer"
                    wire:model.live="planSlug"
                >
                    <option value="">Selecione…</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->slug }}">{{ $plan->name }}</option>
                    @endforeach
                </select>
                @error('planSlug')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            @if ($planSlug)
                <div>
                    <span class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Apps do plano</span>
                    <div class="rounded-md border border-outline-variant bg-surface-container-low p-md space-y-sm">
                        @foreach ($this->availableApps as $app)
                            <div class="flex items-start gap-sm">
                                <input
                                    type="checkbox"
                                    wire:model="selectedAppIds"
                                    value="{{ $app->app_id }}"
                                    id="app-{{ $app->app_id }}"
                                    class="mt-[2px]"
                                >
                                <label for="app-{{ $app->app_id }}" class="text-[13px] text-on-surface cursor-pointer">
                                    {{ $app->label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('selectedAppIds')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="space-y-sm">
                <div class="flex items-start gap-sm">
                    <input type="checkbox" wire:model="imageMode" id="imageMode" class="mt-[2px]">
                    <label for="imageMode" class="text-[13px] text-on-surface cursor-pointer">
                        Image mode (<code>--image-mode</code>) — obrigatório no cluster image-pilot
                    </label>
                </div>
                <div class="flex items-start gap-sm">
                    <input type="checkbox" wire:model="suiteCatalog" id="suiteCatalog" class="mt-[2px]">
                    <label for="suiteCatalog" class="text-[13px] text-on-surface cursor-pointer">
                        Suite catalog (<code>--suite-catalog</code>)
                    </label>
                </div>
                <div class="flex items-start gap-sm">
                    <input type="checkbox" wire:model="fullApps" id="fullApps" class="mt-[2px]">
                    <label for="fullApps" class="text-[13px] text-on-surface cursor-pointer">
                        Instalar todos os apps (<code>--full-apps</code>)
                    </label>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Logo (PNG/JPG, max 5 MB)</label>
                <input type="file" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface" wire:model="logo" accept="image/png,image/jpeg">
                @error('logo')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Background (PNG/JPG, max 5 MB)</label>
                <input type="file" class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface" wire:model="background" accept="image/png,image/jpeg">
                @error('background')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-md pt-sm">
                <button
                    type="submit"
                    class="rounded-md bg-primary px-lg py-2.5 text-[13px] font-semibold text-on-primary hover:opacity-95 disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                    @disabled($submitting)
                >
                    <span wire:loading.remove wire:target="submit">Provisionar</span>
                    <span wire:loading wire:target="submit">Enviando…</span>
                </button>
                <a href="{{ route('customers.index') }}" class="text-[12px] text-on-surface-variant hover:text-on-surface">Cancelar</a>
            </div>
        </form>
    </div>
</div>
