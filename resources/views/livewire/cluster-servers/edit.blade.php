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
        .form-control:disabled { opacity: .5; cursor: not-allowed; }
        .error-msg { color: #fc8181; font-size: .8rem; margin-top: .25rem; }
        .form-hint { color: #718096; font-size: .75rem; margin-top: .25rem; }
        .btn-submit {
            background: #2b6cb0; color: #fff; border: none; border-radius: 6px;
            padding: .5rem 1.25rem; font-size: .875rem; font-weight: 500; cursor: pointer;
        }
        .btn-submit:hover { background: #2c5282; }
        .btn-cancel { color: #a0aec0; font-size: .875rem; margin-left: 1rem; text-decoration: none; }
        .btn-cancel:hover { color: #e2e8f0; }
        .readonly-field {
            background: #0f1117; border: 1px solid #1e2535; border-radius: 6px;
            color: #4a5568; padding: .5rem .75rem; font-size: .875rem;
            font-family: monospace; width: 100%;
        }
        .section-label { font-size: .8125rem; font-weight: 600; color: #63b3ed; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: .05em; }
        .section-label-sftp { color: #68d391; }
        .section-divider { border-top: 1px solid #2d3748; margin: 1.5rem 0 1.25rem; }
        .badge-optional {
            font-size: .7rem; font-weight: 400; color: #718096;
            border: 1px solid #2d3748; border-radius: 4px; padding: .1rem .4rem;
            margin-left: .5rem; text-transform: none; letter-spacing: 0;
        }
        .pubkey-box {
            background: #0f1117; border: 1px solid #2d3748; border-radius: 6px;
            color: #68d391; padding: .5rem .75rem; font-size: .75rem;
            font-family: monospace; width: 100%; white-space: pre-wrap; word-break: break-all;
        }
        .btn-copy {
            margin-top: .5rem; background: transparent; border: 1px solid #2d3748;
            border-radius: 6px; color: #68d391; font-size: .8125rem;
            padding: .375rem .75rem; cursor: pointer;
        }
        .btn-copy:hover { background: #1a2535; }
    </style>

    <h1 class="page-title">Editar Cluster Server</h1>

    <div class="form-card">
        <form wire:submit="save">
            <div class="form-group">
                <label for="name">Nome *</label>
                <input id="name" type="text" class="form-control" wire:model="name">
                @error('name') <p class="error-msg">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label for="ssh_host">SSH Host *</label>
                <input id="ssh_host" type="text" class="form-control" wire:model="ssh_host">
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
                <p class="section-label">SSH Private Key</p>

                @if($replacingKey)
                    <textarea
                        id="ssh_private_key"
                        class="form-control"
                        wire:model="ssh_private_key"
                        rows="8"
                        placeholder="Cole aqui a chave privada SSH (-----BEGIN ... KEY-----)"
                        style="font-family:monospace;font-size:.8125rem;resize:vertical;"
                        autofocus
                    ></textarea>
                    @error('ssh_private_key') <p class="error-msg">{{ $message }}</p> @enderror
                    <p class="form-hint">Cole a chave privada completa, incluindo os cabeçalhos BEGIN/END.</p>
                    <button type="button" class="btn-cancel" wire:click="toggleReplaceKey" style="margin-left:0;margin-top:.5rem;display:inline-block;">
                        Cancelar substituição
                    </button>
                @else
                    <div class="readonly-field">••••••••••••••••  (não exibida)</div>
                    <button type="button" style="margin-top:.5rem;background:transparent;border:1px solid #2d3748;border-radius:6px;color:#63b3ed;font-size:.8125rem;padding:.375rem .75rem;cursor:pointer;" wire:click="toggleReplaceKey">
                        Substituir chave SSH
                    </button>
                @endif
            </div>

            {{-- ── Canal B — SFTP Branding ────────────────────────────── --}}
            <div class="section-divider"></div>
            <p class="section-label section-label-sftp">
                Canal B — SFTP Branding
                <span class="badge-optional">opcional</span>
            </p>

            <div class="form-group">
                <label for="sftp_user">Usuário SFTP</label>
                <input id="sftp_user" type="text" class="form-control" wire:model="sftp_user">
                @error('sftp_user') <p class="error-msg">{{ $message }}</p> @enderror
                <p class="form-hint">Canal B chrooteado para upload de branding (ncsaas-sftp).</p>
            </div>

            <div class="form-group">
                <p class="section-label" style="font-size:.75rem;margin-bottom:.5rem;color:#68d391;">Chave Privada SFTP</p>

                @if($replacingSftpKey)
                    <textarea
                        id="sftp_private_key"
                        class="form-control"
                        wire:model="sftp_private_key"
                        rows="8"
                        placeholder="Cole aqui a chave privada Ed25519 do Canal B em formato PEM"
                        style="font-family:monospace;font-size:.8125rem;resize:vertical;"
                    ></textarea>
                    @error('sftp_private_key') <p class="error-msg">{{ $message }}</p> @enderror
                    <p class="form-hint">
                        Gere com <code>ssh-keygen -t ed25519 -f ncsaas-sftp-key</code>.
                        Ao salvar, a chave pública derivada será exibida abaixo para instalação no servidor.
                    </p>
                    <button type="button" class="btn-cancel" wire:click="toggleReplaceSftpKey" style="margin-left:0;margin-top:.5rem;display:inline-block;">
                        Cancelar substituição
                    </button>
                @else
                    @if($clusterServer->sftp_private_key_encrypted)
                        <div class="readonly-field">••••••••••••••••  (não exibida)</div>
                    @else
                        <div class="readonly-field" style="color:#718096;">Não configurada</div>
                    @endif
                    <button type="button" style="margin-top:.5rem;background:transparent;border:1px solid #2d3748;border-radius:6px;color:#68d391;font-size:.8125rem;padding:.375rem .75rem;cursor:pointer;" wire:click="toggleReplaceSftpKey">
                        {{ $clusterServer->sftp_private_key_encrypted ? 'Substituir chave SFTP' : 'Configurar chave SFTP' }}
                    </button>
                @endif
            </div>

            @if($sftp_public_key)
            <div class="form-group">
                <label>Chave Pública SFTP — instale no servidor</label>
                <div class="pubkey-box" id="sftp-pubkey-box">{{ $sftp_public_key }}</div>
                <button
                    type="button"
                    class="btn-copy"
                    onclick="navigator.clipboard.writeText(document.getElementById('sftp-pubkey-box').innerText).then(()=>{ this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar chave pública',2000) })"
                >Copiar chave pública</button>
                <p class="form-hint" style="margin-top:.5rem;">
                    Adicione no servidor: <code>echo "&lt;chave&gt;" >> /etc/ssh/ncsaas-sftp-authorized_keys</code>
                </p>
            </div>
            @endif

            <div>
                <button type="submit" class="btn-submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Salvar alterações</span>
                    <span wire:loading>Salvando…</span>
                </button>
                <a href="{{ route('cluster-servers.index') }}" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</div>
