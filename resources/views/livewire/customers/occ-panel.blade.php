<div
    @if ($pendingUserCreateJobId !== '')
        wire:poll.3s="pollPendingUserJob"
    @endif
    class="space-y-gutter"
>
    <div class="flex flex-col gap-sm md:flex-row md:items-center md:justify-between">
        <div>
            <a href="{{ route('customers.show', $customer->slug) }}" class="text-[13px] text-primary hover:underline no-underline">← {{ $customer->slug }}</a>
            <h1 class="text-[1.25rem] font-semibold text-on-surface mt-xs">Painel OCC</h1>
        </div>
        <span class="text-[12px] text-outline">{{ $customer->clusterServer?->name ?? '—' }}</span>
    </div>

    @if ($successMessage)
        <div class="bg-[#6ad191]/10 border border-[#6ad191]/30 rounded-md px-md py-sm text-[13px] text-[#6ad191]" wire:key="success-{{ now() }}">{{ $successMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="bg-error/10 border border-error/30 rounded-md px-md py-sm text-[13px] text-error" wire:key="error-{{ now() }}">{{ $errorMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-xs border-b border-outline-variant">
        @foreach (['quota' => 'Quota', 'branding' => 'Branding', 'maintenance' => 'Manutenção', 'apps' => 'Apps', 'users' => 'Usuários', 'groups' => 'Grupos'] as $key => $label)
            <button
                type="button"
                class="border-b-2 px-md py-sm text-[13px] -mb-px transition-colors {{ $tab === $key ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}"
                wire:click="setTab('{{ $key }}')"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'quota')
    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Definir Quota</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Escopo</label>
                <select class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary cursor-pointer" wire:model="quotaScope">
                    <option value="user">Por usuário</option>
                    <option value="default">Padrão (novos usuários)</option>
                    <option value="all">Todos os usuários</option>
                </select>
            </div>
            <div @class(['hidden' => $quotaScope !== 'user'])>
                <span class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Username</span>
                <x-select-menu
                    model="quotaUsername"
                    :selected="$quotaUsername"
                    :options="$usernameOptions"
                    placeholder="Selecione…"
                />
                @error('quotaUsername')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="mt-md">
            <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Quota</label>
            <select class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary cursor-pointer" wire:model="quotaValue">
                <option value="">— selecione —</option>
                @foreach ($quotaOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
            @error('quotaValue')
                <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
            @enderror
        </div>
        <button type="button" class="mt-md rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary" wire:click="submitQuota">Aplicar Quota</button>
    </div>

    <hr class="border-outline-variant my-lg">

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Files Rescan</div>
        <div>
            <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Username (opcional — deixe vazio para todos)</label>
            <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="rescanUsername" placeholder="johndoe">
        </div>
        <button type="button" class="mt-md rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary" wire:click="submitRescan">Iniciar Rescan</button>
    </div>
    @endif

    @if ($tab === 'branding')
    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Branding Nextcloud</div>
        <p class="mb-md text-[11px] text-outline">Campos em branco não são alterados.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Nome</label>
                <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="brandingName" placeholder="Acme Cloud">
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Cor primária</label>
                <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="brandingColor" placeholder="#0082c9">
                @error('brandingColor')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Slogan</label>
                <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="brandingSlogan" placeholder="A sua nuvem">
            </div>
            <div>
                <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">URL institucional</label>
                <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="brandingUrl" placeholder="https://acme.com">
                @error('brandingUrl')
                    <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <button type="button" class="mt-md rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary" wire:click="submitBranding">Salvar Branding</button>
    </div>
    @endif

    @if ($tab === 'maintenance')
    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Modo Manutenção</div>
        <p class="text-[13px] text-on-surface-variant mb-md leading-relaxed">
            Quando ativado, o Nextcloud exibe uma mensagem de manutenção e bloqueia logins de usuários.
            Administradores ainda conseguem acessar.
        </p>
        <div class="flex items-center gap-md mb-lg">
            <input type="checkbox" id="maintToggle" wire:model="maintenanceOn" class="cursor-pointer">
            <label for="maintToggle" class="text-[14px] text-on-surface cursor-pointer">
                Colocar tenant em modo manutenção
            </label>
        </div>
        <button
            type="button"
            class="rounded-md px-md py-2 text-[13px] {{ $maintenanceOn ? 'border border-error/50 bg-error-container text-error hover:opacity-90' : 'border border-outline-variant bg-surface-container-high text-primary hover:border-primary' }}"
            wire:click="toggleMaintenance"
        >
            {{ $maintenanceOn ? 'Ativar manutenção' : 'Desativar manutenção' }}
        </button>
    </div>
    @endif

    @if ($tab === 'apps')
    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Habilitar App (OCC sync)</div>
        <p class="mb-md text-[11px] text-outline">
            Ativa um app individualmente via OCC síncrono (Feature P). Para operações bulk, use o endpoint de lifecycle.
        </p>
        <div>
            <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">App ID</label>
            <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="appId" placeholder="calendar">
            @error('appId')
                <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-[11px] text-outline">Somente letras minúsculas, números e underscore.</p>
        </div>
        <button type="button" class="mt-md rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary" wire:click="submitApp">Habilitar App</button>
    </div>
    @endif

    @if ($tab === 'users')
    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="flex items-center justify-between mb-md">
            <div class="text-[14px] font-semibold text-on-surface">Usuários ativos</div>
            <button
                type="button"
                class="rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary"
                wire:click="syncUsers"
                wire:loading.attr="disabled"
                wire:target="syncUsers"
            >
                <span wire:loading.remove wire:target="syncUsers">Atualizar</span>
                <span wire:loading wire:target="syncUsers">Carregando…</span>
            </button>
        </div>

        @if ($usersError)
            <div class="bg-error/10 border border-error/30 rounded-md px-md py-sm text-[13px] text-error mb-md">{{ $usersError }}</div>
        @endif

        @if ($usersLoading)
            <p class="text-[11px] text-outline">Carregando usuários…</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="border-b border-outline-variant">
                            <th class="text-left px-md py-sm text-[12px] font-medium text-on-surface-variant">Username</th>
                            <th class="text-left px-md py-sm text-[12px] font-medium text-on-surface-variant">E-mail</th>
                            <th class="text-left px-md py-sm text-[12px] font-medium text-on-surface-variant">Quota</th>
                            <th class="text-left px-md py-sm text-[12px] font-medium text-on-surface-variant">Grupos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse ($tenantUsers as $user)
                            <tr wire:key="tenant-user-{{ $user['username'] }}" class="hover:bg-surface-container-high transition-colors">
                                <td class="px-md py-sm font-mono text-[12px] text-on-surface">{{ $user['username'] }}</td>
                                <td class="px-md py-sm text-on-surface">{{ $user['email'] !== '' ? $user['email'] : '—' }}</td>
                                <td class="px-md py-sm text-on-surface">{{ $user['quota'] }}</td>
                                <td class="px-md py-sm text-on-surface">{{ $user['groups'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-md py-xl text-center text-on-surface-variant">
                                    Nenhum usuário.
                                    <span class="block mt-xs text-[11px] text-outline">Clique em Atualizar para sincronizar com o servidor.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <hr class="border-outline-variant my-lg">

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Criar Usuário (async)</div>
        <form wire:submit.prevent="createUser" class="space-y-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                <div>
                    <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Username *</label>
                    <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="userUsername" placeholder="johndoe">
                    @error('userUsername')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-[11px] text-outline">O username <code>admin</code> é reservado (criado no provisionamento).</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">E-mail</label>
                    <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" type="email" wire:model="userEmail" placeholder="john@acme.com">
                    @error('userEmail')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Senha *</label>
                    <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" type="password" wire:model="userPasswordPlain" autocomplete="new-password">
                    @error('userPassword')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-[11px] text-outline">Nextcloud 33 exige ≥10 caracteres; senhas comuns são rejeitadas.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Template de usuário</label>
                    <select class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="userTemplateSlug">
                        <option value="">— nenhum —</option>
                        @foreach ($userTemplates as $template)
                            <option value="{{ $template->slug }}">{{ $template->name }} ({{ $template->slug }})</option>
                        @endforeach
                    </select>
                    @error('userTemplateSlug')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <span class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Grupos (opcional — vazio herda do template)</span>
                    @if (count($knownGroups) === 0)
                        <p class="text-[13px] text-on-surface-variant mb-sm">Nenhum grupo conhecido ainda. Crie na aba Grupos primeiro.</p>
                        <button
                            type="button"
                            class="rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary"
                            wire:click="setTab('groups')"
                        >
                            Criar grupo →
                        </button>
                    @else
                        <div class="flex flex-wrap gap-md rounded-md border border-outline-variant bg-surface-container-lowest px-md py-sm">
                            @foreach ($knownGroups as $group)
                                <label class="flex items-center gap-xs text-[13px] text-on-surface cursor-pointer" wire:key="group-opt-{{ $group }}">
                                    <input type="checkbox" wire:model="userGroupSelection" value="{{ $group }}" class="cursor-pointer">
                                    {{ $group }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('userGroupSelection')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <button type="submit" class="rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary">Criar Usuário</button>
        </form>
    </div>

    <hr class="border-outline-variant my-lg">

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Remover Usuário (async)</div>
        <div>
            <span class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Username</span>
            <x-select-menu
                model="deleteUsername"
                :selected="$deleteUsername"
                :options="$usernameOptions"
                placeholder="Selecione…"
            />
            @error('deleteUsername')
                <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
            @enderror
        </div>
        <button
            type="button"
            class="mt-md rounded-md border border-error/50 bg-error-container px-md py-2 text-[13px] text-error hover:opacity-90"
            wire:click="deleteUser"
            wire:confirm="Remover o usuário '{{ addslashes($deleteUsername) }}'? Esta ação não pode ser desfeita."
        >Remover Usuário</button>
    </div>
    @endif

    @if ($tab === 'groups')
    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="flex items-center justify-between mb-md">
            <div class="text-[14px] font-semibold text-on-surface">Grupos ativos</div>
            <button
                type="button"
                class="rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary"
                wire:click="syncGroups"
                wire:loading.attr="disabled"
                wire:target="syncGroups"
            >
                <span wire:loading.remove wire:target="syncGroups">Atualizar</span>
                <span wire:loading wire:target="syncGroups">Carregando…</span>
            </button>
        </div>

        @if ($groupsError)
            <div class="bg-error/10 border border-error/30 rounded-md px-md py-sm text-[13px] text-error mb-md">{{ $groupsError }}</div>
        @endif

        @if ($groupsLoading)
            <p class="text-[11px] text-outline">Carregando grupos…</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="border-b border-outline-variant">
                            <th class="text-left px-md py-sm text-[12px] font-medium text-on-surface-variant">Nome</th>
                            <th class="text-left px-md py-sm text-[12px] font-medium text-on-surface-variant">Origem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse ($tenantGroups as $group)
                            <tr wire:key="tenant-group-{{ $group['name'] }}" class="hover:bg-surface-container-high transition-colors">
                                <td class="px-md py-sm font-mono text-[12px] text-on-surface">{{ $group['name'] }}</td>
                                <td class="px-md py-sm text-on-surface">{{ $group['origin'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-md py-xl text-center text-on-surface-variant">
                                    Nenhum grupo.
                                    <span class="block mt-xs text-[11px] text-outline">Clique em Atualizar para sincronizar com o servidor.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <hr class="border-outline-variant my-lg">

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Criar Grupo (async)</div>
        <div>
            <label class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Nome do grupo</label>
            <input class="w-full rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-on-surface outline-none focus:border-primary" wire:model="groupName" placeholder="editors">
            @error('groupName')
                <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
            @enderror
        </div>
        <button type="button" class="mt-md rounded-md border border-outline-variant bg-surface-container-high px-md py-2 text-[13px] text-primary hover:border-primary" wire:click="createGroup">Criar Grupo</button>
    </div>

    <hr class="border-outline-variant my-lg">

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Membership usuário ↔ grupo</div>
        <div class="bg-primary/10 border border-primary/30 rounded-md px-md py-sm text-[13px] text-on-surface-variant">
            Membership usuário↔grupo estará disponível em release futura (aguardando upstream).
        </div>
    </div>

    <hr class="border-outline-variant my-lg">

    <div class="bg-surface-container border border-outline-variant rounded-xl p-lg">
        <div class="text-[14px] font-semibold text-on-surface mb-md">Remover Grupo (async)</div>
        <div>
            <span class="mb-1.5 block text-[12px] font-medium text-on-surface-variant">Nome do grupo</span>
            <x-select-menu
                model="deleteGroupName"
                :selected="$deleteGroupName"
                :options="$groupOptions"
                placeholder="Selecione…"
            />
            @error('deleteGroupName')
                <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
            @enderror
        </div>
        <button
            type="button"
            class="mt-md rounded-md border border-error/50 bg-error-container px-md py-2 text-[13px] text-error hover:opacity-90"
            wire:click="deleteGroup"
            wire:confirm="Remover o grupo '{{ addslashes($deleteGroupName) }}'? Esta ação não pode ser desfeita."
        >Remover Grupo</button>
    </div>
    @endif
</div>
