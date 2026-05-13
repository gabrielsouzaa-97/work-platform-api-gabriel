<div>
    <style>
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1.5rem; }
        .form-card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; padding: 1.5rem; max-width: 640px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .8rem; color: #a0aec0; margin-bottom: .35rem; }
        .form-input {
            width: 100%; background: #0f1117; border: 1px solid #2d3748; border-radius: 6px;
            color: #e2e8f0; padding: .45rem .75rem; font-size: .8125rem; box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: #4a90d9; }
        .form-error { color: #fc8181; font-size: .75rem; margin-top: .25rem; }
        .btn-submit {
            background: #2563eb; color: #fff; border: none; border-radius: 6px;
            padding: .5rem 1.5rem; font-size: .875rem; cursor: pointer;
        }
        .btn-submit:disabled { opacity: .5; cursor: not-allowed; }
        .alert-error { background: #3a2020; border: 1px solid #fc8181; border-radius: 6px; color: #fc8181; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .8125rem; }
    </style>

    <h1 class="page-title">Provisionar Customer</h1>

    @if ($errorMessage)
        <div class="alert-error">{{ $errorMessage }}</div>
    @endif

    <div class="form-card">
        <form wire:submit="submit">
            <div class="form-group">
                <label>Slug *</label>
                <input type="text" class="form-input" wire:model.blur="slug" placeholder="acme-prod">
                @error('slug') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Domínio *</label>
                <input type="text" class="form-input" wire:model.blur="domain" placeholder="acme.example.com">
                @error('domain') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label>Cluster Server *</label>
                <select class="form-input" wire:model="clusterServerId">
                    <option value="">Selecione…</option>
                    @foreach ($clusters as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('clusterServerId') <div class="form-error">{{ $message }}</div> @enderror
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

            <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
                <input type="checkbox" wire:model="fullApps" id="fullApps">
                <label for="fullApps" style="margin:0;font-size:.8125rem">Instalar todos os apps (--full-apps)</label>
            </div>

            <div style="margin-top:1.5rem">
                <button type="submit" class="btn-submit" :disabled="$submitting">
                    <span wire:loading.remove wire:target="submit">Provisionar</span>
                    <span wire:loading wire:target="submit">Enviando…</span>
                </button>
                <a href="{{ route('customers.index') }}" style="margin-left:1rem;color:#718096;font-size:.8rem">Cancelar</a>
            </div>
        </form>
    </div>
</div>
