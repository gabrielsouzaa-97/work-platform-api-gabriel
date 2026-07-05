<div>
    <style>
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1.5rem; }
        .form-card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; padding: 1.5rem; max-width: 640px; overflow: visible; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .8rem; color: #a0aec0; margin-bottom: .35rem; }
        .form-hint { font-size: .7rem; color: #718096; margin-top: .25rem; line-height: 1.4; }
        .form-input {
            width: 100%; background: #0f1117; border: 1px solid #2d3748; border-radius: 6px;
            color: #e2e8f0; padding: .45rem .75rem; font-size: .8125rem; box-sizing: border-box;
            color-scheme: dark;
        }
        .form-input:focus { outline: none; border-color: #4a90d9; }
        select.form-input { cursor: pointer; }
        select.form-input option { background: #1a1d27; color: #e2e8f0; }
        .form-error { color: #fc8181; font-size: .75rem; margin-top: .25rem; }
        .btn-submit {
            background: #2563eb; color: #fff; border: none; border-radius: 6px;
            padding: .5rem 1.5rem; font-size: .875rem; cursor: pointer;
        }
        .btn-submit:disabled { opacity: .5; cursor: not-allowed; }
        .alert-error { background: #3a2020; border: 1px solid #fc8181; border-radius: 6px; color: #fc8181; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .8125rem; }
        .checkbox-row { display: flex; align-items: flex-start; gap: .5rem; margin-bottom: .75rem; }
        .checkbox-row label { margin: 0; font-size: .8125rem; color: #e2e8f0; cursor: pointer; }
        .checkbox-row input { margin-top: .15rem; }
    </style>

    <h1 class="page-title">Provisionar Customer</h1>

    @if ($errorMessage)
        <div class="alert-error">{{ $errorMessage }}</div>
    @endif

    <div class="form-card">
        <form wire:submit="submit">
            <div class="form-group">
                <label for="slug">Slug *</label>
                <input id="slug" type="text" class="form-input" wire:model.live.debounce.300ms="slug" placeholder="minha-empresa" autocomplete="off">
                <p class="form-hint">Somente letras minúsculas, números e hífen (ex.: <code>acme-prod</code>). O placeholder não conta como valor — digite o slug.</p>
                @error('slug') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="domain">Domínio (FQDN) *</label>
                <input id="domain" type="text" class="form-input" wire:model.blur="domain" placeholder="minha-empresa.image-pilot.mework360.com.br" autocomplete="off">
                <p class="form-hint">FQDN completo do tenant — não use só <code>image-pilot.mework360.com.br</code>. Padrão image-pilot: <code>&lt;slug&gt;.image-pilot.mework360.com.br</code>.</p>
                @if ($normalizedDomain !== '')
                    <p class="form-hint">Domínio final: {{ $normalizedDomain }}</p>
                @endif
                @error('domain') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="clusterServerId">Cluster Server *</label>
                <select id="clusterServerId" class="form-input" wire:model.live="clusterServerId">
                    <option value="">Selecione…</option>
                    @foreach ($clusters as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('clusterServerId') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <div class="checkbox-row">
                    <input type="checkbox" wire:model="imageMode" id="imageMode">
                    <label for="imageMode">Image mode (<code>--image-mode</code>) — obrigatório no cluster image-pilot</label>
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" wire:model="suiteCatalog" id="suiteCatalog">
                    <label for="suiteCatalog">Suite catalog (<code>--suite-catalog</code>)</label>
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" wire:model="fullApps" id="fullApps">
                    <label for="fullApps">Instalar todos os apps (<code>--full-apps</code>)</label>
                </div>
            </div>

            <div class="form-group">
                <label>Logo (PNG/JPG, max 5 MB)</label>
                <input type="file" class="form-input" wire:model="logo" accept="image/png,image/jpeg">
                @error('logo') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Background (PNG/JPG, max 5 MB)</label>
                <input type="file" class="form-input" wire:model="background" accept="image/png,image/jpeg">
                @error('background') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div style="margin-top:1.5rem">
                <button type="submit" class="btn-submit" wire:loading.attr="disabled" @disabled($submitting)>
                    <span wire:loading.remove wire:target="submit">Provisionar</span>
                    <span wire:loading wire:target="submit">Enviando…</span>
                </button>
                <a href="{{ route('customers.index') }}" style="margin-left:1rem;color:#718096;font-size:.8rem">Cancelar</a>
            </div>
        </form>
    </div>
</div>
