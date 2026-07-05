<div>
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.25rem; font-weight: 600; color: #e2e8f0; }
        .back-link { color: #63b3ed; font-size: .8rem; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .tabs { display: flex; gap: .25rem; margin-bottom: 1.5rem; border-bottom: 1px solid #2d3748; padding-bottom: 0; flex-wrap: wrap; }
        .tab-btn {
            background: none; border: none; color: #718096; font-size: .8125rem;
            padding: .5rem 1rem; cursor: pointer; border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        .tab-btn:hover { color: #e2e8f0; }
        .tab-btn.active { color: #63b3ed; border-bottom-color: #63b3ed; font-weight: 600; }
        .section-card { background: #1a1d27; border: 1px solid #2d3748; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; }
        .section-title { font-size: .875rem; font-weight: 600; color: #e2e8f0; margin-bottom: 1rem; }
        .form-group { margin-bottom: .875rem; }
        label { display: block; font-size: .75rem; color: #718096; margin-bottom: .3rem; font-weight: 500; }
        .form-input {
            width: 100%; background: #0f1117; border: 1px solid #2d3748; border-radius: 6px;
            color: #e2e8f0; padding: .45rem .75rem; font-size: .8125rem; box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: #63b3ed; }
        select.form-input { cursor: pointer; }
        .form-error { color: #fc8181; font-size: .75rem; margin-top: .25rem; }
        .btn-primary {
            background: #1a365d; color: #63b3ed; border: 1px solid #2b4c7e;
            border-radius: 6px; padding: .45rem 1rem; font-size: .8125rem; cursor: pointer;
        }
        .btn-primary:hover { background: #2b4c7e; }
        .btn-danger { background: #7f1d1d; color: #fc8181; border: 1px solid #991b1b; border-radius: 6px; padding: .45rem 1rem; font-size: .8125rem; cursor: pointer; }
        .btn-danger:hover { background: #991b1b; }
        .alert-success { background: #1c3a2f; border: 1px solid #276749; color: #68d391; border-radius: 6px; padding: .6rem .9rem; font-size: .8rem; margin-bottom: 1rem; }
        .alert-error { background: #3a2020; border: 1px solid #742a2a; color: #fc8181; border-radius: 6px; padding: .6rem .9rem; font-size: .8rem; margin-bottom: 1rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
        .hint { font-size: .7rem; color: #4a5568; margin-top: .2rem; }
        .toggle-row { display: flex; align-items: center; gap: .75rem; }
        .divider { border: none; border-top: 1px solid #2d3748; margin: 1.25rem 0; }
        .users-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .users-table th { text-align: left; color: #718096; font-weight: 500; padding: .5rem .75rem; border-bottom: 1px solid #2d3748; }
        .users-table td { padding: .5rem .75rem; border-bottom: 1px solid #2d3748; color: #e2e8f0; }
        .users-table .mono { font-family: ui-monospace, monospace; font-size: .75rem; }
    </style>

    {{-- Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('customers.show', $customer->slug) }}" class="back-link">← {{ $customer->slug }}</a>
            <h1 class="page-title" style="margin-top:.25rem">Painel OCC</h1>
        </div>
        <span style="font-size:.75rem;color:#4a5568">{{ $customer->clusterServer?->name ?? '—' }}</span>
    </div>

    {{-- Alerts --}}
    @if ($successMessage)
        <div class="alert-success" wire:key="success-{{ now() }}">{{ $successMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="alert-error" wire:key="error-{{ now() }}">{{ $errorMessage }}</div>
    @endif

    {{-- Tabs --}}
    <div class="tabs">
        @foreach (['quota' => 'Quota', 'branding' => 'Branding', 'maintenance' => 'Manutenção', 'apps' => 'Apps', 'users' => 'Usuários', 'groups' => 'Grupos'] as $key => $label)
            <button class="tab-btn {{ $tab === $key ? 'active' : '' }}" wire:click="setTab('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ─── QUOTA TAB ──────────────────────────────────────────────────────── --}}
    @if ($tab === 'quota')
    <div class="section-card">
        <div class="section-title">Definir Quota</div>
        <div class="grid-2">
            <div class="form-group">
                <label>Escopo</label>
                <select class="form-input" wire:model="quotaScope">
                    <option value="user">Por usuário</option>
                    <option value="default">Padrão (novos usuários)</option>
                    <option value="all">Todos os usuários</option>
                </select>
            </div>
            <div class="form-group" @if ($quotaScope !== 'user') style="display:none" @endif>
                <label>Username</label>
                <input class="form-input" wire:model="quotaUsername" placeholder="johndoe">
                @error('quotaUsername') <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="form-group">
            <label>Quota</label>
            <select class="form-input" wire:model="quotaValue">
                <option value="">— selecione —</option>
                @foreach ($quotaOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
            @error('quotaValue') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <button class="btn-primary" wire:click="submitQuota">Aplicar Quota</button>
    </div>

    <hr class="divider">

    <div class="section-card">
        <div class="section-title">Files Rescan</div>
        <div class="form-group">
            <label>Username (opcional — deixe vazio para todos)</label>
            <input class="form-input" wire:model="rescanUsername" placeholder="johndoe">
        </div>
        <button class="btn-primary" wire:click="submitRescan">Iniciar Rescan</button>
    </div>
    @endif

    {{-- ─── BRANDING TAB ───────────────────────────────────────────────────── --}}
    @if ($tab === 'branding')
    <div class="section-card">
        <div class="section-title">Branding Nextcloud</div>
        <p class="hint" style="margin-bottom:.875rem">Campos em branco não são alterados.</p>
        <div class="grid-2">
            <div class="form-group">
                <label>Nome</label>
                <input class="form-input" wire:model="brandingName" placeholder="Acme Cloud">
            </div>
            <div class="form-group">
                <label>Cor primária</label>
                <input class="form-input" wire:model="brandingColor" placeholder="#0082c9">
                @error('brandingColor') <div class="form-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label>Slogan</label>
                <input class="form-input" wire:model="brandingSlogan" placeholder="A sua nuvem">
            </div>
            <div class="form-group">
                <label>URL institucional</label>
                <input class="form-input" wire:model="brandingUrl" placeholder="https://acme.com">
                @error('brandingUrl') <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <button class="btn-primary" wire:click="submitBranding">Salvar Branding</button>
    </div>
    @endif

    {{-- ─── MAINTENANCE TAB ─────────────────────────────────────────────────── --}}
    @if ($tab === 'maintenance')
    <div class="section-card">
        <div class="section-title">Modo Manutenção</div>
        <p style="color:#a0aec0;font-size:.8125rem;margin-bottom:1rem">
            Quando ativado, o Nextcloud exibe uma mensagem de manutenção e bloqueia logins de usuários.
            Administradores ainda conseguem acessar.
        </p>
        <div class="toggle-row" style="margin-bottom:1.25rem">
            <input type="checkbox" id="maintToggle" wire:model="maintenanceOn" style="cursor:pointer">
            <label for="maintToggle" style="color:#e2e8f0;font-size:.875rem;cursor:pointer;margin-bottom:0">
                {{ $maintenanceOn ? 'Ativar manutenção' : 'Desativar manutenção' }}
            </label>
        </div>
        <button
            class="{{ $maintenanceOn ? 'btn-danger' : 'btn-primary' }}"
            wire:click="toggleMaintenance">
            {{ $maintenanceOn ? 'Ativar manutenção' : 'Desativar manutenção' }}
        </button>
    </div>
    @endif

    {{-- ─── APPS TAB ────────────────────────────────────────────────────────── --}}
    @if ($tab === 'apps')
    <div class="section-card">
        <div class="section-title">Habilitar App (OCC sync)</div>
        <p class="hint" style="margin-bottom:.875rem">
            Ativa um app individualmente via OCC síncrono (Feature P). Para operações bulk, use o endpoint de lifecycle.
        </p>
        <div class="form-group">
            <label>App ID</label>
            <input class="form-input" wire:model="appId" placeholder="calendar">
            @error('appId') <div class="form-error">{{ $message }}</div> @enderror
            <p class="hint">Somente letras minúsculas, números e underscore.</p>
        </div>
        <button class="btn-primary" wire:click="submitApp">Habilitar App</button>
    </div>
    @endif

    {{-- ─── USERS TAB ───────────────────────────────────────────────────────── --}}
    @if ($tab === 'users')
    <div class="section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <div class="section-title" style="margin-bottom:0">Usuários ativos</div>
            <button
                class="btn-primary"
                type="button"
                wire:click="loadUsers"
                wire:loading.attr="disabled"
                wire:target="loadUsers"
            >
                <span wire:loading.remove wire:target="loadUsers">Atualizar</span>
                <span wire:loading wire:target="loadUsers">Carregando…</span>
            </button>
        </div>

        @if ($usersError)
            <div class="alert-error" style="margin-bottom:1rem">{{ $usersError }}</div>
        @endif

        @if ($usersLoading)
            <p class="hint">Carregando usuários…</p>
        @else
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>E-mail</th>
                        <th>Quota</th>
                        <th>Grupos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenantUsers as $user)
                        <tr wire:key="tenant-user-{{ $user['username'] }}">
                            <td class="mono">{{ $user['username'] }}</td>
                            <td>{{ $user['email'] !== '' ? $user['email'] : '—' }}</td>
                            <td>{{ $user['quota'] }}</td>
                            <td>{{ $user['groups'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:#718096;padding:1.5rem">Nenhum usuário.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <hr class="divider">

    <div class="section-card">
        <div class="section-title">Criar Usuário (async)</div>
        {{-- F5.11 (QA-F5-019): <form wire:submit> + wire:model na senha eliminam
             test/production divergence. createUser() lê de $userPasswordPlain
             e zera no finally. --}}
        <form wire:submit.prevent="createUser">
            <div class="grid-2">
                <div class="form-group">
                    <label>Username *</label>
                    <input class="form-input" wire:model="userUsername" placeholder="johndoe">
                    @error('userUsername') <div class="form-error">{{ $message }}</div> @enderror
                    <p class="hint">O username <code>admin</code> é reservado (criado no provisionamento).</p>
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input class="form-input" type="email" wire:model="userEmail" placeholder="john@acme.com">
                    @error('userEmail') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Senha *</label>
                    <input class="form-input" type="password" wire:model="userPasswordPlain" autocomplete="new-password">
                    @error('userPassword') <div class="form-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label>Grupos (separados por vírgula)</label>
                    <input class="form-input" wire:model="userGroups" placeholder="admins, editors">
                </div>
            </div>
            <button class="btn-primary" type="submit">Criar Usuário</button>
        </form>
    </div>

    <hr class="divider">

    <div class="section-card">
        <div class="section-title">Remover Usuário (async)</div>
        <div class="form-group">
            <label>Username</label>
            <input class="form-input" wire:model="deleteUsername" placeholder="johndoe">
            @error('deleteUsername') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <button class="btn-danger" wire:click="deleteUser">Remover Usuário</button>
    </div>
    @endif

    {{-- ─── GROUPS TAB ──────────────────────────────────────────────────────── --}}
    @if ($tab === 'groups')
    <div class="section-card">
        <div class="section-title">Criar Grupo (async)</div>
        <div class="form-group">
            <label>Nome do grupo</label>
            <input class="form-input" wire:model="groupName" placeholder="editors">
            @error('groupName') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <button class="btn-primary" wire:click="createGroup">Criar Grupo</button>
    </div>

    <hr class="divider">

    <div class="section-card">
        <div class="section-title">Adicionar usuário a grupo (async)</div>
        <div class="grid-2">
            <div class="form-group">
                <label>Username</label>
                <input class="form-input" wire:model="groupAddUsername" placeholder="johndoe">
                @error('groupAddUsername') <div class="form-error">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label>Grupo</label>
                <input class="form-input" wire:model="groupAddTarget" placeholder="editors">
                @error('groupAddTarget') <div class="form-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <button class="btn-primary" wire:click="addUserToGroup">Adicionar ao Grupo</button>
    </div>

    <hr class="divider">

    <div class="section-card">
        <div class="section-title">Remover Grupo (async)</div>
        <div class="form-group">
            <label>Nome do grupo</label>
            <input class="form-input" wire:model="deleteGroupName" placeholder="editors">
            @error('deleteGroupName') <div class="form-error">{{ $message }}</div> @enderror
        </div>
        <button class="btn-danger" wire:click="deleteGroup">Remover Grupo</button>
    </div>
    @endif
</div>
