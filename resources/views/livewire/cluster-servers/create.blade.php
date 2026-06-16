<div>
    <style>
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1.5rem; }
        .form-card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; padding: 1.5rem; max-width: 640px; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: .8125rem; font-weight: 500; color: #a0aec0; margin-bottom: .375rem; }
        .form-control {
            width: 100%; background: #0f1117; border: 1px solid #2d3748;
            border-radius: 6px; color: #e2e8f0; padding: .5rem .75rem;
            font-size: .875rem; outline: none;
        }
        .form-control:focus { border-color: #63b3ed; }
        textarea.form-control { font-family: monospace; font-size: .8rem; resize: vertical; min-height: 160px; }
        .error-msg { color: #fc8181; font-size: .8rem; margin-top: .25rem; }
        .form-hint { color: #718096; font-size: .75rem; margin-top: .25rem; }
        .btn-submit {
            background: #2b6cb0; color: #fff; border: none; border-radius: 6px;
            padding: .5rem 1.25rem; font-size: .875rem; font-weight: 500; cursor: pointer;
        }
        .btn-submit:hover { background: #2c5282; }
        .btn-cancel { color: #a0aec0; font-size: .875rem; margin-left: 1rem; text-decoration: none; }
        .btn-cancel:hover { color: #e2e8f0; }
        .info-box { background: #1e2535; border: 1px solid #2d3748; border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .8125rem; color: #a0aec0; }
    </style>

    <h1 class="page-title">Novo Cluster Server</h1>

    <div class="form-card">
        <div class="info-box">
            O webhook secret é gerado automaticamente pelo sistema. A SSH private key é armazenada de forma encriptada.
        </div>

        <form wire:submit="save">
            <div class="form-group">
                <label for="name">Nome *</label>
                <input id="name" type="text" class="form-control" wire:model="name" placeholder="Ex.: Produção Brasil">
                @error('name') <p class="error-msg">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="ssh_host">SSH Host *</label>
                <input id="ssh_host" type="text" class="form-control" wire:model="ssh_host" placeholder="IP ou hostname">
                @error('ssh_host') <p class="error-msg">{{ $message }}</p> @enderror
            </div>

            <div style="display:flex;gap:1rem">
                <div class="form-group" style="flex:1">
                    <label for="ssh_port">Porta *</label>
                    <input id="ssh_port" type="number" class="form-control" wire:model="ssh_port" min="1" max="65535">
                    @error('ssh_port') <p class="error-msg">{{ $message }}</p> @enderror
                </div>
                <div class="form-group" style="flex:2">
                    <label for="ssh_user">Usuário SSH *</label>
                    <input id="ssh_user" type="text" class="form-control" wire:model="ssh_user">
                    @error('ssh_user') <p class="error-msg">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="ssh_private_key">SSH Private Key (PEM) *</label>
                <textarea id="ssh_private_key" class="form-control" name="ssh_private_key"
                    placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"></textarea>
                <p class="form-hint">Cole o conteúdo do arquivo .pem. Nunca exibido após salvo.</p>
                @error('ssh_private_key') <p class="error-msg">{{ $message }}</p> @enderror
            </div>

            <div>
                <button type="submit" class="btn-submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Criar cluster</span>
                    <span wire:loading>Salvando…</span>
                </button>
                <a href="{{ route('cluster-servers.index') }}" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</div>
