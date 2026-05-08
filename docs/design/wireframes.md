# Wireframes: Telas Faltantes (meWork360 Deployer)

> Baseado no REQUIREMENTS v0.2 e no design system extraído do Stitch.
> Estilo: High-Tech Professional (Dark mode default, densidade afrouxada, pt-BR).

---

## 1. Login

**Rota**: `/login`
**Tipo**: página (layout centrado)
**Autenticação**: pública

### Layout
```text
+--------------------------------------------------+
|                                                  |
|                                                  |
|            [ Logo meWork360 Deployer ]           |
|                                                  |
|            ┌───────────────────────┐             |
|            │ Acesso ao Sistema     │             |
|            │                       │             |
|            │ E-mail                │             |
|            │ [ marina@allta...   ] │             |
|            │                       │             |
|            │ Senha                 │             |
|            │ [ ••••••••••••••••• ] │             |
|            │                       │             |
|            │ [x] Lembrar-me        │             |
|            │                       │             |
|            │ [    ENTRAR (CTA)   ] │             |
|            └───────────────────────┘             |
|                                                  |
+--------------------------------------------------+
```

### Componentes
| Componente | Tipo | Dados | Interação |
|-----------|------|-------|-----------|
| Card | Surface Container | - | Fundo elevado |
| Input Email | Text Input | E-mail do operador | Focus ring primary |
| Input Senha | Password Input | Senha | Focus ring primary |
| Checkbox | Checkbox | Lembrar-me | Toggle state |
| Botão Entrar | Primary Button | Label "ENTRAR" | Submit form |

### Estados
| Estado | Comportamento |
|--------|--------------|
| Loading | Botão Entrar com spinner (ícone `sync` animado), inputs disabled |
| Empty | Inputs vazios |
| Error | Toast/Alert "Credenciais inválidas" ou "Conta bloqueada (rate limit)" |
| Success | Redireciona para `/` (Dashboard) |

### Ações do usuário
1. Preencher form + Enter → Tenta login
2. Errar 5x → Bloqueio de IP por 15min (Rate limit)

---

## 2. Lista de Customers

**Rota**: `/customers`
**Tipo**: página
**Autenticação**: autenticada (todas as roles)

### Layout
```text
+--------------------------------------------------+
| TOPBAR: Logo   Busca (⌘K)             Avatar  🔔 |
+--------------------------------------------------+
| SIDEBAR    | Clientes                            |
| Dashboard  |                                     |
| ● Clientes | [Busca slug...] [Status ▼] [Server ▼]|
| Fila       |                                     |
| Logs       | ┌─────────────────────────────────┐ |
| Settings   | │ Slug   Domínio   Status  Criado │ |
|            | │ acme   acme.com  [Ativo] 10/05  │ |
|            | │ beta   beta.com  [Erro]  11/05  │ |
|            | └─────────────────────────────────┘ |
|            | [< 1 2 3 ... 10 >]                  |
+--------------------------------------------------+
```

### Componentes
| Componente | Tipo | Dados | Interação |
|-----------|------|-------|-----------|
| Tabela | DataTable | Slug, Domínio, Status (Badge), Criado em | Click na linha abre `/customers/{slug}` |
| Filtros | Input + Selects | Busca por slug, dropdown Status, dropdown Server | Atualiza tabela via Livewire |
| Paginação | Pagination | 25 por página | Navega páginas |
| Status Badge | Chip | Ativo (Verde), Provisionando (Azul spin), Erro (Coral) | - |

### Estados
| Estado | Comportamento |
|--------|--------------|
| Loading | Skeleton rows na tabela |
| Empty | "Nenhum cliente encontrado." + CTA "Provisionar Cliente" (se admin/operador) |
| Error | "Erro ao carregar clientes. A réplica pode estar desatualizada." + Botão "Tentar Novamente" |
| Success | Tabela populada |

### Ações do usuário
1. Digitar na busca → Filtra tabela em tempo real (debounce)
2. Clicar na linha → Navega para detalhes do cliente

---

## 3. Provisionar Customer

**Rota**: `/customers/new`
**Tipo**: página
**Autenticação**: autenticada (admin, operador)

### Layout
```text
+--------------------------------------------------+
| TOPBAR: Logo   Busca (⌘K)             Avatar  🔔 |
+--------------------------------------------------+
| SIDEBAR    | Provisionar Novo Cliente            |
| Dashboard  |                                     |
| ● Clientes | ┌─────────────────────────────────┐ |
| Fila       | │ Slug (identificador único)      │ |
| Logs       | │ [ acme-corp                   ] │ |
| Settings   | │ * Apenas minúsculas e hífen     │ |
|            | │                                 │ |
|            | │ Cluster Server                  │ |
|            | │ [ Cluster Defensys SP ▼       ] │ |
|            | │                                 │ |
|            | │ Domínio                         │ |
|            | │ [ acme.mework360.com          ] │ |
|            | │                                 │ |
|            | │ [x] Instalar todos os apps      │ |
|            | │                                 │ |
|            | │ Logo (opcional, max 5MB)        │ |
|            | │ [ Escolher arquivo... ]         │ |
|            | │                                 │ |
|            | │ [ CANCELAR ]  [ PROVISIONAR ]   │ |
|            | └─────────────────────────────────┘ |
+--------------------------------------------------+
```

### Componentes
| Componente | Tipo | Dados | Interação |
|-----------|------|-------|-----------|
| Form | Form Container | Slug, Server, Domínio, Apps, Logo | Submit |
| Input Slug | Text Input | Slug do cliente | Validação regex tempo real (rejeita `_`) |
| Select Server | Dropdown | Lista de cluster_servers ativos | Seleciona destino |
| File Upload | File Input | Logo/Background | Preview da imagem se < 5MB |
| Botões | Ghost + Primary | Cancelar, Provisionar | Navega back / Submit |

### Estados
| Estado | Comportamento |
|--------|--------------|
| Loading | Botão "Provisionar" com spinner, form disabled |
| Empty | Form limpo |
| Error | Validação inline (ex: "Slug inválido", "Arquivo muito grande") |
| Success | Redireciona para `/queue/{job_id}` |

### Ações do usuário
1. Preencher form com slug inválido (ex: `acme_corp`) → Erro 422 imediato (UI bloqueia)
2. Submit válido → Gera idempotency_key, inicia job SSH, vai para fila

---

## 4. Detalhe do Customer (Overview)

**Rota**: `/customers/{slug}`
**Tipo**: página (com tabs de navegação interna)
**Autenticação**: autenticada

### Layout
```text
+--------------------------------------------------+
| TOPBAR: Logo   Busca (⌘K)             Avatar  🔔 |
+--------------------------------------------------+
| SIDEBAR    | Cliente: acme-corp          [Ativo] |
| Dashboard  | ----------------------------------- |
| ● Clientes | [Visão Geral] [Usuários] [Grupos]   |
| Fila       | [Apps] [Branding] [Manutenção]      |
| Logs       |                                     |
| Settings   | ┌──────────────┐ ┌────────────────┐ |
|            | │ Info         │ │ Jobs Recentes  │ |
|            | │ Domínio: ... │ │ • Provision... │ |
|            | │ Servidor: .. │ │ • Add User ... │ |
|            | │ Criado: .... │ │                │ |
|            | └──────────────┘ └────────────────┘ |
+--------------------------------------------------+
```

### Componentes
| Componente | Tipo | Dados | Interação |
|-----------|------|-------|-----------|
| Header | Page Header | Slug, Status Badge | - |
| Tabs | Sub-nav | Links para sub-rotas | Navega sem recarregar página (Livewire navigate) |
| Info Card | Card | Metadados do cliente | - |
| Jobs Card | List Card | Últimos 5 jobs do cliente | Click no job vai para `/queue/{id}` |

### Estados
| Estado | Comportamento |
|--------|--------------|
| Loading | Skeletons nos cards |
| Empty | N/A (se chegou aqui, cliente existe) |
| Error | "Cliente não encontrado" (404) |
| Success | Dados exibidos |

### Ações do usuário
1. Clicar em uma Tab → Muda o conteúdo principal (sub-rotas)

---

## 5. Gerir Usuários do Customer

**Rota**: `/customers/{slug}/users`
**Tipo**: página (sub-rota do detalhe)
**Autenticação**: autenticada

### Layout
```text
(Header e Tabs iguais à tela 4)
|            | [ + Novo Usuário ]                  |
|            | ┌─────────────────────────────────┐ |
|            | │ ID   Nome   Email   Quota  Ações│ |
|            | │ u1   João   j@...   5GB    [⋮]  │ |
|            | └─────────────────────────────────┘ |
```

### Componentes
| Componente | Tipo | Dados | Interação |
|-----------|------|-------|-----------|
| Tabela | DataTable | Usuários do Nextcloud (via OCC sync) | Menu de ações (Editar, Reset Senha, Deletar) |
| Botão Novo | Primary Button | - | Abre Modal de Criação |
| Modal Form | Dialog | Nome, Email, Quota, Grupos, Senha | Submit (Feature O.2 async) |

### Estados
| Estado | Comportamento |
|--------|--------------|
| Loading | Skeleton tabela (OCC sync pode levar ~2-5s) |
| Empty | "Nenhum usuário encontrado." |
| Error | "Falha ao conectar com o servidor (SSH timeout)." |
| Success | Tabela populada |

### Ações do usuário
1. Clicar "+ Novo Usuário" → Preenche modal → Submit → Inicia Job Async → Mostra Toast "Job enfileirado"
2. Editar Quota → OCC Sync (rápido) → Atualiza linha

---

## 6. Gerir Grupos do Customer

**Rota**: `/customers/{slug}/groups`
**Tipo**: página (sub-rota do detalhe)
**Autenticação**: autenticada

### Layout
*(Similar à tela 5, mas listando Grupos. Ações: Renomear, Deletar)*

---

## 7. Gerir Apps do Customer

**Rota**: `/customers/{slug}/apps`
**Tipo**: página (sub-rota do detalhe)
**Autenticação**: autenticada

### Layout
*(Similar à tela 5. Tabela de Apps instalados. Ações: Enable/Disable. Botão "Instalar Novo App" abre modal com lista de apps permitidos)*

---

## 8. Branding do Customer

**Rota**: `/customers/{slug}/branding`
**Tipo**: página (sub-rota do detalhe)
**Autenticação**: autenticada

### Layout
```text
(Header e Tabs iguais à tela 4)
|            | ┌─────────────────────────────────┐ |
|            | │ Cores da Marca                  │ |
|            | │ Cor Principal: [ #005ac2 ]      │ |
|            | │                                 │ |
|            | │ Imagens                         │ |
|            | │ Logo: [ Upload... ] (Atual: ✓)  │ |
|            | │ Fundo: [ Upload... ] (Atual: ✗) │ |
|            | │                                 │ |
|            | │ [ SALVAR ALTERAÇÕES ]           │ |
|            | └─────────────────────────────────┘ |
```
*(Usa SCP staging para anexos > 256KB, OCC sync para aplicar)*

---

## 9. Manutenção e Ações Destrutivas

**Rota**: `/customers/{slug}/maintenance`
**Tipo**: página (sub-rota do detalhe)
**Autenticação**: autenticada (admin)

### Layout
```text
(Header e Tabs iguais à tela 4)
|            | ┌─────────────────────────────────┐ |
|            | │ Modo de Manutenção              │ |
|            | │ [ Toggle: OFF ]                 │ |
|            | │ * Bloqueia acesso de usuários   │ |
|            | └─────────────────────────────────┘ |
|            | ┌─────────────────────────────────┐ |
|            | │ Zona de Perigo                  │ |
|            | │ Remover Cliente                 │ |
|            | │ Apaga a instância e os dados.   │ |
|            | │ [ REMOVER CLIENTE (Destructive)]│ |
|            | └─────────────────────────────────┘ |
```

### Modal de Remoção (Crítico)
```text
| ┌───────────────────────────────────────────┐ |
| │ Remover Cliente: acme-corp                │ |
| │ Esta ação não pode ser desfeita.          │ |
| │                                           │ |
| │ Digite o slug para confirmar:             │ |
| │ [ acme-corp                             ] │ |
| │                                           │ |
| │ [x] Fazer backup antes de remover         │ |
| │ [ ] Forçar remoção (ignorar erros)        │ |
| │                                           │ |
| │ [ CANCELAR ]  [ REMOVER (Desabilitado) ]  │ |
| └───────────────────────────────────────────┘ |
```
*(Botão Remover só habilita se o input bater exatamente com o slug)*

---

## 10. Operadores (Admin)

**Rota**: `/operators`
**Tipo**: página
**Autenticação**: autenticada (apenas admin)

### Layout
```text
+--------------------------------------------------+
| TOPBAR: Logo   Busca (⌘K)             Avatar  🔔 |
+--------------------------------------------------+
| SIDEBAR    | Operadores do Sistema               |
| Dashboard  |                                     |
| Clientes   | [ + Novo Operador ]                 |
| Fila       | ┌─────────────────────────────────┐ |
| Logs       | │ Nome   Email   Role    Últ.Login│ |
| Settings   | │ Admin  a@...   Admin   Hoje     │ |
| ● Operador | │ Sofia  s@...   Suporte Ontem    │ |
|            | └─────────────────────────────────┘ |
+--------------------------------------------------+
```

### Componentes
| Componente | Tipo | Dados | Interação |
|-----------|------|-------|-----------|
| Tabela | DataTable | Operadores locais | Editar Role, Desativar |
| Modal Form | Dialog | Nome, Email, Role | Dispara email de convite |

---

## 11. Perfil do Operador

**Rota**: `/profile`
**Tipo**: página
**Autenticação**: autenticada

### Layout
*(Formulário simples para alterar Nome, Senha atual, Nova senha. Lista de sessões ativas com botão "Encerrar outras sessões")*
