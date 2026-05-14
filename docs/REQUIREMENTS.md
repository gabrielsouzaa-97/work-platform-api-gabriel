# Requisitos — mework360-deployer (API REST orquestradora + Painel Gestor)

> Gerado em: 2026-05-07
> Atualizado em: 2026-05-14
> Status: Revisado — MVP implementado
> Versão: 0.3
> Autor: Analista de Requisitos (IA, via `/analista escopo`)

---

## 1. Visão Geral

**Descrição**: API Laravel **orquestradora** + painel administrativo interno meWork360 que expõe a Nextcloud Deployer API (REST) e delega o provisionamento e a gestão das instâncias Nextcloud para o sistema upstream `nextcloud-saas-manager` (Bash scripts + Redis worker em servidor dedicado), via SSH + webhooks HMAC.

**Problema que resolve**: hoje o provisionamento de Nextcloud é manual (SSH + scripts), demorado, sem rastro auditável e dependente de conhecimento tribal sobre comandos OCC. Operações de suporte cotidianas (resetar quota, habilitar app, criar usuário) exigem terminal e abrem brecha para erro humano. O `nextcloud-saas-manager` v12.0+ fornece a CLI assíncrona necessária; falta uma camada REST + UI segura, auditada e amigável que orquestre essa CLI sem bloquear chamadas HTTP.

**Para quem**: operadores internos meWork360 — DevOps, SRE e Suporte N2 — que provisionam e mantêm instâncias Nextcloud para os customers da empresa.

**Objetivo de negócio**: reduzir tempo de provisionamento de novos customers para <5min por clique, eliminar SSH para 80% das tarefas operacionais comuns, ter audit log completo (12 meses, LGPD), e expor um contrato REST estável para futuros consumidores externos (sprint 2 com Bearer auth).

**Cenário**: PROTÓTIPO + ANÁLISE de **dois sistemas externos**:

1. Contrato OpenAPI 3.0.3 próprio (`docs/openapi.yaml`, 33 ops em 31 paths) — define a interface REST que esta API expõe
2. `nextcloud-saas-manager` (`/home/usuario/Work/beesy/me360/nextcloud-saas-manager`, REQUIREMENTS.md v0.3) — sistema upstream que esta API consome via SSH (CLI `manage.sh`) + Webhook HMAC-SHA256

**Posicionamento arquitetural**: esta API é a "**API REST consumidora**" descrita na §3 do `nextcloud-saas-manager` (persona não-humana). Toda mutação estrutural (provisionar, remover, backup, restore, update, stop, start, lifecycle de user/group/apps) é delegada via SSH ao servidor scripts; a API somente valida, orquestra, aguarda callback HMAC, atualiza réplica local, audita e expõe estado.

---

## 2. Stakeholders

| Papel                             | Tipo                          | Prioridade | O que importa para ele                                                                           |
| --------------------------------- | ----------------------------- | ---------- | ------------------------------------------------------------------------------------------------ |
| Engenheiro líder meWork360        | Decisor                       | Alta       | Padronização da operação, redução de incidentes, audit log auditável                             |
| DevOps / SRE                      | Influenciador + Usuário final | Alta       | Confiabilidade do provisionamento, visibilidade da fila, controle granular                       |
| Suporte N2                        | Usuário final                 | Alta       | Resolver chamados sem SSH, com confirmação clara em ações destrutivas                            |
| Compliance/Segurança              | Influenciador                 | Média      | LGPD, retention de logs, rastro de quem fez o quê                                                |
| Patrocinador (gestão meWork360)   | Patrocinador                  | Alta       | Tempo até produção, custo do MVP, ROI em redução de toil                                         |
| **Time `nextcloud-saas-manager`** | **Dependência crítica**       | **Alta**   | **Esta API consome contratos CLI/JSON/Webhook estáveis; mudanças exigem coordenação cross-repo** |

---

## 3. Usuários e Personas

| Persona                                | Contexto                                                               | Frustração principal (hoje)                                                                | Objetivo                                                                                | Nível técnico |
| -------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------- | ------------- |
| **Marina** — Operadora de Provisioning | DevOps júnior/pleno; faz deploys diários de novos customers            | Provisionamento manual via SSH/scripts é demorado e sujeito a erro humano, sem rastro      | Provisionar 1 customer em <5min com 1 clique e acompanhar o job inteiro                 | Técnico       |
| **Rafael** — Admin Sênior / SRE        | Cuida da infra cluster, segurança, fila e incidentes                   | Não tem visibilidade unificada da fila, dos jobs falhados, nem de quem chamou o quê na API | Dashboard único com KPIs, fila, audit log e controle granular                           | Técnico       |
| **Sofia** — Suporte N2                 | Atende customer reclamando de quota, app desabilitado, login Nextcloud | Precisa abrir terminal, decorar comandos OCC, sem GUI para tarefas comuns                  | Resolver 80% dos chamados sem SSH, com confirmação clara antes de operações destrutivas | Semi-técnico  |

---

## 4. Features (MVP)

> Estratégia adotada:
>
> - **Provisionamento estrutural** (create/remove customer, lifecycle de user/group/apps): **delegado via SSH** ao `manage.sh ... --async` do `nextcloud-saas-manager`
> - **OCC sync passthrough** (quota, branding, maintenance, files:rescan, app:enable individual): **delegado via SSH** ao `manage.sh <slug> occ-exec <subcmd>` (Feature P do scripts)
> - **User/Group lifecycle no MVP**: SEMPRE async via Feature O.2 do scripts (multi-step atômico — criar + atribuir grupos + setar quota numa única transação async com callback)
> - **Estado dos jobs**: a API mantém réplica local sincronizada via webhooks HMAC-SHA256 + polling SSH como fallback

### Feature 1: Login + cadastro manual de operadores

- **Persona**: todas
- **Prioridade**: Must-have

**Fluxo principal:**

1. Admin cadastra operador via tela admin (email, nome, role: `admin` | `operador` | `suporte`)
2. Operador recebe convite por email com link de definição de senha (tempo limitado)
3. Operador define senha, faz login (email + senha), obtém sessão Laravel
4. Sessão valida role nas telas conforme permissões

**Fluxo de exceção:**

- Email já cadastrado → erro "operador já existe"
- Token de convite expirado → operador pede reenvio
- Senha fraca → validação cliente + servidor (mínimo 12 caracteres, complexidade)

**Regras de negócio:**

- Apenas role `admin` pode cadastrar/desativar outros operadores
- Senhas armazenadas com hash bcrypt/argon2 (Laravel default)
- Sessão expira após 8h de inatividade
- Bloqueio temporário após 5 tentativas falhas em 15min (rate limit)

**Critérios de aceite:**

- [ ] Admin cadastra um operador novo e ele recebe email com link de convite
- [ ] Operador define senha pelo link e consegue logar
- [ ] Operador com role `suporte` não vê opções de provisionar/remover customer
- [ ] Tentar logar com senha errada 5x bloqueia temporariamente o IP
- [ ] Logout encerra a sessão imediatamente

---

### Feature 2: Listar customers (réplica local sincronizada)

- **Persona**: Marina, Rafael, Sofia
- **Prioridade**: Must-have

**Fluxo principal:**

1. Operador acessa `/customers`
2. API consulta tabela local `customers` (espelho de instâncias do servidor scripts; sincronizada via callbacks de jobs `create`/`remove` e por sync periódico via `manage.sh list --json`)
3. Lista paginada exibe: slug, server (cluster), domínio, status (provisioning/active/error/removing), criado em, última atividade
4. Operador filtra por status, busca por slug, ordena por data
5. Clicar em uma linha abre detalhes do customer

**Fluxo de exceção:**

- Nenhum customer cadastrado → empty state com CTA "Provisionar primeiro customer"
- Erro ao sync com servidor scripts → toast "réplica local pode estar desatualizada" + botão "ressincronizar agora"

**Regras de negócio:**

- Paginação obrigatória (default 25, max 100)
- Filtros: status (multi-select), server, range de data
- Sync automático: cron diário às 3h chama `manage.sh list --json` para reconciliar; gaps detectados (customer nos scripts mas não na API, ou vice-versa) geram alerta no audit log
- Sync sob demanda: botão "Ressincronizar agora" disponível para `admin`

**Critérios de aceite:**

- [ ] Lista carrega em <2s para até 200 customers
- [ ] Filtros funcionam combinados (ex: status=active + server=cluster-A)
- [ ] Paginação navegável; URL preserva filtros (deep-link)
- [ ] Sync diário detecta divergências e registra no audit log

---

### Feature 3: Provisionar customer (delegado ao `nextcloud-saas-manager`)

- **Persona**: Marina, Rafael
- **Prioridade**: Must-have

**Fluxo principal:**

1. Operador clica "Provisionar customer"
2. Formulário: slug (regex `^[a-z0-9-]+$`, **sem underscore**, max 64 chars), server (dropdown de `cluster_servers` ativos), domínio, apps iniciais (multi-select), `--full-apps` (toggle), branding opcional (logo + background PNG/JPEG)
3. Validação cliente + servidor:
   - Slug com `_` ou maiúsculas → **rejeitar com 422** + mensagem "Use apenas letras minúsculas, números e hífen"
   - Slug já existe na réplica local → 409 inline
   - Cluster server selecionado offline → erro com sugestão de servers ativos
   - Anexo > 5MB → 413 Payload Too Large
4. API gera `idempotency_key` (UUID v4) e armazena pendente em tabela local `customer_jobs`
5. **Se anexos > 256KB**: API faz SCP para `ncsaas-api@<server>:/opt/nextcloud-customers/inbox/<staging-id>/{logo.png,background.png}` antes do SSH
6. **Se anexos ≤ 256KB**: anexos vão inline via `--payload-stdin` (`logo_data_url` / `background_data_url`)
7. API executa via SSH: `manage.sh <slug> <dominio> create --async --idempotency-key=<uuid> --callback=https://<api>/api/jobs/hook --json [--apps=...] [--full-apps] [--staging-id=<uuid>] [--payload-stdin]` no `cluster_server` escolhido
8. Resposta esperada em <2s: `{job_id, state: queued, queued_at}` (UUID v4)
9. API armazena `job_id` no `customer_jobs`, marca customer com status `provisioning`
10. UI redireciona para tela do job e aguarda
11. **Caminho preferido**: webhook chega em `POST /api/jobs/hook` com payload + `X-Signature: HMAC-SHA256(secret, body)`; API valida assinatura, atualiza estado, dispara notificação por email ao operador
12. **Caminho fallback** (se webhook não chega em 60s e job ainda em `running`): polling SSH `manage.sh job <id> status --json` a cada 30s até concluir
13. Sucesso (`state: success`): customer marcado como `active`, URL da instância exibida, email enviado ao operador
14. Falha (`state: failed`): mensagem com `error_msg` + `exit_code` + link para `manage.sh job <id> logs` + botão "Tentar novamente" (gera nova idempotency-key)

**Fluxo alternativo:**

- Cancelar job ainda em `queued`: `manage.sh job <id> cancel` via SSH

**Fluxo de exceção:**

- SSH não conecta → 503 Service Unavailable + retry com backoff; após 3 falhas marca cluster_server como `unreachable` e alerta admin
- `manage.sh` retorna exit 2 (`queue_unavailable`) → 503 + `retry_after`
- `idempotency_conflict` (exit 3): mostra job antigo no painel, sem criar duplicata
- `state_conflict` (exit 4): customer já existe com args diferentes; UI mostra diff + opção "ver job existente" ou "ajustar args"
- Webhook chega com signature inválida → log de segurança crítico + 401 + alerta

**Regras de negócio:**

- Slug é imutável após criação
- Apenas roles `admin` e `operador` podem provisionar
- Toda criação registra entrada no audit log: ator, timestamp, payload (sanitizado), `idempotency_key`, `job_id`, cluster_server destino
- Notificação por email é enviada apenas para o ator do provisionamento
- Idempotency-key é **gerada pela API** (UUID v4), nunca aceita do cliente HTTP — garante que retries naturais não dupliquem jobs
- Threshold base64 inline ↔ SCP staging: **256KB por anexo** (alinhado com `nextcloud-saas-manager` CONTRACTS.md §3.9.0)

**Critérios de aceite:**

- [ ] Slug com `_`, maiúscula ou char especial é rejeitado com 422 e mensagem clara antes do SSH
- [ ] Slug válido + server ativo provisiona com sucesso; em <5min instância acessível via URL
- [ ] Anexo de 100KB chega via inline base64; anexo de 800KB chega via SCP staging para `/opt/nextcloud-customers/inbox/<staging-id>/`
- [ ] Webhook HMAC-SHA256 inválido é rejeitado com 401 e gera alerta
- [ ] Quando webhook não chega em 60s, polling SSH ativa automaticamente e detecta conclusão
- [ ] Reenviar mesmo formulário (idempotency-key gerada na primeira tentativa) retorna mesmo `job_id` sem duplicar
- [ ] Falha com `state_conflict` (exit 4) mostra diff e não tenta sobrescrever
- [ ] Audit log registra ator + payload sanitizado + `job_id` + cluster_server

---

### Feature 4: Remover customer (delegado ao `nextcloud-saas-manager`)

- **Persona**: Rafael
- **Prioridade**: Must-have

**Fluxo principal:**

1. Operador acessa detalhes do customer e clica "Remover"
2. Modal de confirmação **forte** exige:
   - Digitar o slug do customer literalmente (case-sensitive)
   - Checkbox "fazer backup antes" (`--backup-first`, pré-marcado por default)
   - Checkbox "force" (`--force`, não pré-marcado; usar quando instância está com erro)
3. Botão "Remover" só habilita após slug correto digitado
4. API gera nova `idempotency_key`, audita, e executa via SSH: `manage.sh <slug> _ remove --async --idempotency-key=<uuid> --callback=... --confirm=<slug> [--force] [--backup-first] --json`
5. Recebe `job_id`; UI vai para tela do job e aguarda webhook
6. Sucesso: customer marca como `removed` na réplica local; backup (se solicitado) fica disponível na seção "Backups disponíveis" (lido via `manage.sh <slug> _ credentials` ou link do `summary` do callback)
7. Falha: mantém customer com status `error` + permite retry com flags ajustadas

**Fluxo de exceção:**

- Customer não existe na réplica local → 404 + redireciona para lista
- Job falha → status `error` + permite retry
- `--backup-first` falha → o `remove` não é executado (atomicidade do scripts); job marcado como `failed` com `error_msg=backup_failed`

**Regras de negócio:**

- Apenas role `admin` pode remover (operador comum vê o botão desabilitado com tooltip "requer admin")
- Backup armazenado pelo `nextcloud-saas-manager` (responsabilidade do upstream); API só lista/linka
- Toda remoção registra audit log com flags usadas e `idempotency_key`
- Sem janela de undo / sem dupla aprovação no MVP (decisão consciente para simplificar)

**Critérios de aceite:**

- [ ] Modal exige digitar slug correto antes de habilitar remoção
- [ ] Por default `--backup-first=true`; operador pode desmarcar conscientemente
- [ ] Após sucesso, customer some da lista e link de backup aparece (se `--backup-first` ativo)
- [ ] Audit log registra ator, timestamp, slug removido, flags `--force`/`--backup-first`, `idempotency_key`
- [ ] Tentar remover via API direta sem `--confirm=<slug>` (cliente externo na sprint 2) é rejeitado pelo scripts

---

### Feature 5: Fila de jobs (réplica local sincronizada)

- **Persona**: Marina, Rafael
- **Prioridade**: Must-have

**Fluxo principal:**

1. Operador acessa `/queue`
2. API consulta tabela local `jobs` (réplica espelhada). Stats topo: pending, running, done, failed, cancelled, total
3. Stats agregadas pesadas pegam do servidor: `manage.sh worker stats --by-cmd --by-client --json` (cache 30s)
4. Tabela com filtros: state (multi), customer (busca local), cmd / job_type (dropdown), data range, cluster_server
5. Auto-refresh toggle (5s) para acompanhar fila ao vivo (consulta réplica local; refresh real do upstream a cada 30s)
6. Clicar em uma linha abre detalhe do job: payload sanitizado, callback recebido, summary, started_at, finished_at, state atual, exit_code, link "Baixar log" (puxa via `manage.sh job <id> logs` sob demanda)
7. Para jobs `queued`: botão "Cancelar" → `manage.sh job <id> cancel` via SSH
8. Para jobs `failed`: link "Ver no audit log"

**Fluxo de exceção:**

- Cancelar job que já saiu de `queued` → toast "job já em execução, não pode cancelar"
- Polling SSH falha → mostra última atualização recebida + indicador "réplica desatualizada"

**Regras de negócio:**

- Apenas `queued` jobs são canceláveis
- Paginação 50 por página (max 200; alinhado com upstream)
- Cancelamento registra audit log
- TTL upstream do job: 7d após `finished_at` (Redis `nc:jobs:<id>` expira). API mantém réplica **indefinidamente** para histórico de audit; ao tentar `manage.sh job <id> logs` em job antigo, log pode não existir (TTL 30d) → mostrar "log expirado, consulte journald do servidor scripts"
- Reconciliação: cron horário pega `manage.sh job list --state=running --json` para detectar jobs zumbis (running na API mas done/failed nos scripts)

**Critérios de aceite:**

- [ ] Stats cards refletem réplica local instantaneamente; stats do worker upstream atualizam a cada 30s
- [ ] Filtros combinados funcionam (state=failed + customer=acme + cmd=create)
- [ ] Cancelar job pending o move para `cancelled` e some da view "Pending"
- [ ] Detalhe do job mostra `summary` formatado + `output` cru (se disponível)
- [ ] Auto-refresh consome <50KB/s do upstream (polling stats only)
- [ ] Job antigo (>7d) com log indisponível mostra mensagem clara

---

### Feature 6: Operações OCC e lifecycle por customer

- **Persona**: Sofia (suporte), Marina (operadora)
- **Prioridade**: Must-have

> Cobre 80% dos chamados de suporte sem SSH. Mapeia para 2 superfícies do `nextcloud-saas-manager`:
>
> - **Feature P (sync OCC passthrough)**: para operações idempotentes leves (<5s) — `manage.sh <slug> occ-exec <subcmd>`
> - **Feature O (async via fila)**: para operações multi-step compostas — `manage.sh <slug> user/group/apps <verb> --async`

**Sub-features e mapeamento de superfície:**

| Sub-feature                                                                                | Superfície scripts                                                              | Por quê                                                                        |
| ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| 6.1 Listar users do customer                                                               | `occ-exec user:list --output=json` (sync)                                       | Read-only, rápido                                                              |
| 6.1 Criar user (com grupos + quota + template inicial)                                     | `user create --async` (Feature O.2)                                             | Multi-step atômico (user:add + group:adduser + user:setting quota); decisão N2 |
| 6.1 Editar user (display, email, quota, grupos add/remove, enable/disable, resend_welcome) | `user modify --async` (Feature O.2)                                             | Multi-step; senha via `--payload-stdin`                                        |
| 6.1 Deletar user                                                                           | `user remove --async` (Feature O.2)                                             | Por consistência com create/modify (mesmo contrato)                            |
| 6.1 Ver quota                                                                              | `occ-exec user:info <u> --output=json` (sync)                                   | Read-only                                                                      |
| 6.2 Listar groups                                                                          | `occ-exec group:list --output=json` (sync)                                      | Read-only                                                                      |
| 6.2 Criar group                                                                            | `group create --async` (Feature O.3)                                            | Por consistência                                                               |
| 6.2 Editar group (rename, display_name)                                                    | `group modify --async` (Feature O.3)                                            | **Requer Nextcloud ≥ 31** para `group:rename`                                  |
| 6.2 Deletar group                                                                          | `group remove --async` (Feature O.3)                                            | Por consistência                                                               |
| 6.2 Quota do grupo (batch)                                                                 | `occ-exec` em loop OU `apps enable nextcloud_files_scan` para batch grande      | Decisão tática — começar com loop sync via occ-exec                            |
| 6.3 Listar apps instalados                                                                 | `occ-exec app:list --output=json` (sync)                                        | Read-only                                                                      |
| 6.3 Habilitar app individual                                                               | `occ-exec app:enable <appId>` (sync)                                            | Single-app sync                                                                |
| 6.3 Desabilitar app                                                                        | `apps disable --async` (Feature O.4)                                            | Suporta `--remove` opcional; segue padrão de batch                             |
| 6.4 Quota default                                                                          | `occ-exec config:app:get/set files default_quota` (sync)                        | —                                                                              |
| 6.4 Opções de quota                                                                        | `occ-exec config:app:get/set files quota_preset` (sync)                         | —                                                                              |
| 6.5 Branding (logo, background, color, name, url, slogan)                                  | `occ-exec theming:config` em multi-call (sync) + SCP staging para anexos >256KB | Allowlist OCC + anexos com mesma regra de threshold                            |
| 6.6 Maintenance mode toggle                                                                | `occ-exec maintenance:mode --on/--off` (sync)                                   | Apenas `admin`; bloqueia se houver job async no cliente (exit 17)              |
| 6.7 files:rescan (ressalva: timeout 60s sync)                                              | `occ-exec files:scan` (sync) com confirmação                                    | Operações longas devem ir por `apps enable` (Feature O.4) — UI orienta         |

**Regras de negócio:**

- Operações estruturais requerem role `operador` ou `admin`; suporte só faz read-only + reset de senha + habilitar app comum
- Toda mutação registra audit log (ator, customer, operação, payload sanitizado)
- **Senha NUNCA em argv**: criação/modificação de user passa senha via `--payload-stdin` (JSON via stdin do SSH)
- Confirmação dupla apenas para deletar user/group (digitar nome) — outras operações são reversíveis
- `occ-exec` que altera estado é bloqueado se houver job async em `running` no mesmo cliente (exit 17 dos scripts) → UI mostra "operação ocorreu na fila do cliente, aguarde"
- Validação de versão Nextcloud: API mantém `cluster_servers.nextcloud_version` (atualizada no sync); operações que exigem versão mínima (ex: `group:rename` exige Nextcloud ≥ 31) são pré-validadas e rejeitadas com 422 antes do SSH

**Critérios de aceite:**

- [ ] Sofia consegue resetar a quota de um user em <30s sem SSH (occ-exec síncrono)
- [ ] Operações OCC sync respondem em <5s p90; timeout 60s
- [ ] Operações async (user/group/apps lifecycle) retornam `job_id` em <2s e UI espera webhook
- [ ] Erros do OCC (ex: usuário já existe, app inexistente) viram mensagens humanas em pt-BR no painel, não JSON cru
- [ ] Audit log captura todas as mutações por user/group/app/quota/branding/maintenance
- [ ] Tentativa de `group:rename` em cluster com Nextcloud < 31 é rejeitada com 422 e mensagem orientando atualização
- [ ] `occ-exec` em cliente com job async em `running` no mesmo customer mostra erro claro

---

### Feature 7: Audit log básico

- **Persona**: Rafael, Compliance
- **Prioridade**: Must-have

**Fluxo principal:**

1. Toda mutação no sistema (login, provisionar, remover, OCC, cancel job, gestão de operadores) escreve uma entrada de audit log local na API
2. Operador acessa `/audit-log`
3. Lista paginada com colunas: timestamp, ator (operador), ação, recurso afetado (customer/user/group/app), payload (truncado, com expand), `job_id` correlacionado (quando aplicável), cluster_server destino
4. Filtros: operador, ação (multi), customer, range de data, cluster_server
5. Export CSV das entradas filtradas

**Regras de negócio:**

- Audit log da API é **append-only** — nunca editado nem deletado pelo painel
- Retenção mínima: 12 meses (LGPD compliance)
- Payloads são sanitizados antes de gravar (sem senhas, sem tokens, sem HMAC secrets, sem chaves SSH)
- Apenas `admin` pode exportar audit log
- Audit log do upstream (`journald` do `nextcloud-saas-worker`, retenção 30d) **não é replicado** para a API — quando precisar correlacionar, painel mostra link "ver log do job upstream" que chama `manage.sh job <id> logs` sob demanda enquanto disponível

**Critérios de aceite:**

- [ ] Toda mutação no MVP gera 1 entrada de audit log com ator + ação + recurso + (quando aplicável) job_id
- [ ] Export CSV de até 10k linhas funciona em <10s
- [ ] Tentar editar/deletar audit log via painel é impossível (sem endpoints expostos)
- [ ] Senhas, tokens, HMAC secrets e chaves SSH nunca aparecem em payloads exportados
- [ ] Detalhe da entrada permite expandir payload e baixar log do job upstream (se ainda disponível)

---

### Feature 8 (NOVA): Webhook receiver HMAC-SHA256

- **Persona**: API REST consumidora (sistema), API REST consumidora externa (sprint 2)
- **Prioridade**: Must-have (P0 — caminho preferido para detectar conclusão de jobs)

**Contexto**: o `nextcloud-saas-manager` envia callback HTTPS para `https://<api>/api/jobs/hook` ao concluir cada job async, com payload `{job_id, state, exit_code, finished_at, summary, log_url}` e header `X-Signature: HMAC-SHA256(webhook_secret, body)` (formato `sha256=<hex>`).

**Fluxo principal:**

1. Worker do `nextcloud-saas-manager` faz `POST` em `/api/jobs/hook` com body JSON e header `X-Signature`
2. API valida `X-Signature` usando o `webhook_secret` do `cluster_server` correspondente (lookup pela origem do request — IP whitelist + signature)
3. Localiza job na réplica local pelo `job_id`
4. Atualiza estado: `state`, `exit_code`, `finished_at`, `summary`
5. Dispara side-effects baseados no `cmd`:
   - `create` success: marca customer como `active`, envia email ao operador
   - `create` failed: marca customer como `error`, envia email com causa
   - `remove` success: marca customer como `removed`, link backup se disponível
   - `user create/modify/remove` success: invalida cache local de users do customer
   - `apps enable` parcial (`summary.failed_apps[]` não vazio): notifica com WARN
6. Responde 200 OK em <500ms (worker não bloqueia)

**Fluxo de exceção:**

- `X-Signature` inválida → 401 + log de segurança crítico + alerta admin (potencial tampering)
- `job_id` desconhecido → 404 + log (pode ser race condition; worker faz retry 3x backoff exponencial)
- API indisponível → worker retenta 3x; se falhar, `callback_failed=true` no Redis upstream; API detecta via reconciliação horária (Feature 5 regra)
- Body malformado → 400

**Regras de negócio:**

- Endpoint **público** (sem auth Laravel) mas protegido por:
  - HMAC-SHA256 obrigatório (rejeita sem header ou com signature inválida)
  - IP whitelist por `cluster_server.ssh_host` (apenas IPs conhecidos)
  - Rate limit: 100 req/min por IP (caso webhook seja explorado para DoS)
- Replay protection: cada callback tem `finished_at`; rejeitar se `finished_at` < `now - 1h` (callback antigo demais)
- Idempotência: mesmo `job_id` recebendo callback duplicado é processado apenas uma vez (estado já atualizado → 200 OK sem side-effects)
- Webhook secret é **por cluster_server**, armazenado em Laravel encrypted storage; rotacionável por admin

**Critérios de aceite:**

- [ ] Endpoint `POST /api/jobs/hook` valida `X-Signature` com `webhook_secret` correto
- [ ] Signature inválida retorna 401 e gera entrada de audit log de segurança
- [ ] IP fora da whitelist é rejeitado antes mesmo de validar signature
- [ ] Callback duplicado para mesmo job_id é processado idempotentemente (sem disparar email duas vezes)
- [ ] Tempo de resposta p99 < 500ms (worker não fica bloqueado)
- [ ] Replay attack (callback com `finished_at` há mais de 1h) é rejeitado com 410 Gone
- [ ] Webhook recebido enquanto polling SSH está em andamento sincroniza corretamente (race condition tratada via lock optimista por `job_id`)

---

### Feature 9 (NOVA): Gestão de servidores upstream + secrets

- **Persona**: Rafael (admin), Engenheiro líder
- **Prioridade**: Must-have (P0 — sem isso, F3/F4/F5/F6/F8 não funcionam)

**Contexto**: a API precisa armazenar e gerenciar a configuração dos servidores `nextcloud-saas-manager` que ela orquestra. No MVP, **um único servidor**, mas a tabela já é estruturada para multi-server (sprint futuro).

**Fluxo principal:**

1. Admin acessa `/settings/cluster-servers`
2. Cadastra um cluster server com:
   - `name` (label amigável, ex: "Cluster Defensys SP")
   - `ssh_host` (IP ou FQDN)
   - `ssh_port` (default 22)
   - `ssh_user` (default `ncsaas-api`)
   - `ssh_private_key` (upload ou paste; armazenado encriptado)
   - `webhook_secret` (gerar aleatório ou paste; armazenado encriptado)
   - `webhook_secret_version` (int, incrementa em rotação)
   - `nextcloud_version` (preenchido automaticamente no primeiro health check)
   - `schema_version` (preenchido via `manage.sh --json` na primeira chamada; trava em `1` no MVP)
   - `status` (active / unreachable / disabled)
3. Botão "Testar conexão" executa `ssh ncsaas-api@<host> manage.sh health --json` e mostra resultado
4. Botão "Rotacionar webhook secret" gera novo, atualiza no servidor scripts (instrução para admin executar lá), bumpa `webhook_secret_version`
5. UI exibe último health check, fila depth, jobs do dia

**Regras de negócio:**

- **Apenas `admin`** pode CRUD cluster_servers
- SSH private key e webhook secret armazenados via Laravel encrypted storage (encrypt com `APP_KEY`)
- Health check automático a cada 5min (cron); marca `unreachable` após 3 falhas consecutivas
- No MVP: **1 cluster_server único**; se `count() > 1`, UI exibe warning "multi-server não testado no MVP"
- Validação de versão Nextcloud: ao adicionar/atualizar cluster, verificar `nextcloud_version >= 31` se há features dependentes (group:rename) — alerta se inferior
- Secrets nunca expostos no audit log nem no JSON da API

**Critérios de aceite:**

- [ ] Admin cadastra cluster server e botão "Testar conexão" funciona retornando saída do `health`
- [ ] SSH private key e webhook secret são gravados encriptados no DB e não retornam no GET (apenas indicador "✓ configurado")
- [ ] Health check automático detecta servidor offline em <15min e marca `unreachable`
- [ ] Operadores não-admin não veem a tela de cluster_servers
- [ ] Tentativa de cadastrar cluster com Nextcloud < 31 mostra warning sobre features incompatíveis (mas permite cadastrar)
- [ ] Rotação de webhook_secret bumpa `webhook_secret_version` e mantém histórico para grace period (24h aceita ambas as versões)

---

### Feature 10 (NOVA): Tradução de vocabulários (state, cmd) — slug é validação

- **Persona**: API REST consumidora (sistema), Engenheiro líder
- **Prioridade**: Must-have (P0)

**Contexto**: API REST e `nextcloud-saas-manager` usam vocabulários distintos em alguns campos. A API mantém a camada de tradução isolada e versionada para evitar drift. **Importante**: slug NÃO é traduzido — `_` é rejeitado na entrada da API (validação 422), mantendo o contrato consistente desde o input.

**Mapeamentos (versão schema 1):**

| Conceito                                               | API REST (interno + cliente HTTP)                                                                                                                                                                                                                                                         | `nextcloud-saas-manager` (CLI)                                                                                                                                                                             | Tradução                                                                                                |
| ------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| **Customer slug**                                      | `^[a-z0-9-]+$`, max 64 chars (`_` proibido, validado com 422)                                                                                                                                                                                                                             | `^[a-z0-9-]+$`, max 64 chars (C-1)                                                                                                                                                                         | **Identidade** — sem tradução; `_` rejeitado na API                                                     |
| **Job ID**                                             | UUID v4 (string)                                                                                                                                                                                                                                                                          | UUID v4 (string)                                                                                                                                                                                           | **Identidade**                                                                                          |
| **Estado do job**                                      | `state` enum: `queued`, `running`, `success`, `failed`, `cancelled`                                                                                                                                                                                                                       | `state` enum idêntico (atenção: ortografia `cancelled` com 2 'l', NÃO `canceled`)                                                                                                                          | **Identidade** com guard de ortografia                                                                  |
| **Tipo de operação**                                   | `job_type` enum: `customer.create`, `customer.remove`, `customer.update`, `customer.backup`, `customer.restore`, `customer.stop`, `customer.start`, `user.create`, `user.modify`, `user.remove`, `group.create`, `group.modify`, `group.remove`, `apps.enable`, `apps.disable` (15 verbs) | `cmd` enum: `create`, `remove`, `update`, `backup`, `restore`, `stop`, `start`, `user create`, `user modify`, `user remove`, `group create`, `group modify`, `group remove`, `apps enable`, `apps disable` | **Tabela bidirecional** mantida em `app/Services/JobTypeTranslator.php` (15 verbs, validada por testes) |
| **Customer→Server routing**                            | `cluster_server_id` (FK)                                                                                                                                                                                                                                                                  | argumentos SSH `<ssh_host>`, `<ssh_user>`, `<ssh_private_key>`                                                                                                                                             | Lookup em `cluster_servers` antes do SSH                                                                |
| **Servidor (`server` field do CreateCustomerRequest)** | string livre (label do cluster)                                                                                                                                                                                                                                                           | scripts não usa esse campo — é meta da API                                                                                                                                                                 | API armazena em `customers.cluster_server_id`; scripts não veem                                         |
| **Webhook signature**                                  | header `X-Signature: sha256=<hex>`                                                                                                                                                                                                                                                        | igual                                                                                                                                                                                                      | **Identidade**                                                                                          |
| **Schema version**                                     | constante `1` no MVP                                                                                                                                                                                                                                                                      | flag `--schema-version=1` (ou implícito)                                                                                                                                                                   | Validar antes de cada SSH                                                                               |

**Implementação obrigatória:**

- Validador de slug (regex + tamanho) executa **antes** de qualquer SSH ou query — retorna 422 com mensagem "Use apenas letras minúsculas, números e hífen (sem underscore); até 64 caracteres" para input com `_`, maiúscula, especial ou >64 chars
- `JobTypeTranslator` com tabela 1:1 entre 15 verbs; teste unitário cobre cada par
- Guard de ortografia: assertion compile-time / teste de smoke garante que `cancelled` está com 2 'l'
- Versionamento: API trava em `schema_version=1`; ao detectar `schema_version` diferente em response do scripts, gera alerta crítico e bloqueia operações até admin atualizar a API

**Critérios de aceite:**

- [ ] Slug `acme_corp` é rejeitado pela API com 422 antes do SSH; mensagem orienta uso de hífen
- [ ] Slug `acme-corp` (válido) é aceito e enviado **sem alteração** ao scripts
- [ ] `JobTypeTranslator` traduz os 15 verbs nos dois sentidos sem perda
- [ ] Receber callback com `state=canceled` (1 'l') gera erro de validação e log de incompatibilidade
- [ ] Receber response com `schema_version=2` (futuro) bloqueia operação e alerta admin
- [ ] Server label é meta da API; nunca trafega para o scripts via SSH

---

## 5. Integrações

| Sistema                                                                                                | Tipo                                                  | Direção                | Autenticação                                                                                                            | Fallback                                                                                                                                                  |
| ------------------------------------------------------------------------------------------------------ | ----------------------------------------------------- | ---------------------- | ----------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`nextcloud-saas-manager`** (CLI Bash em servidor remoto)                                             | SSH (saída) + Webhook HTTPS (entrada)                 | Bidirecional           | SSH key par dedicada (`mework360-deployer` → `ncsaas-api@<host>`); HMAC-SHA256 nos webhooks (secret por cluster_server) | Polling SSH `manage.sh job <id> status --json` quando webhook não chega em 60s; 3 retries com backoff antes de marcar cluster como `unreachable`          |
| **SCP staging** (mesmo servidor scripts, path restrito `/opt/nextcloud-customers/inbox/<staging-id>/`) | SFTP via SSH                                          | Saída (anexos > 256KB) | Mesma chave SSH; jail por `Match User` + `internal-sftp` no servidor scripts                                            | Cair para inline base64 se SCP falha (mas só para anexos ≤ 256KB)                                                                                         |
| **Email service** (notificações de job para operador)                                                  | SMTP/API                                              | Saída                  | API key/credentials                                                                                                     | Tentar 3x; se falhar, marcar notificação como `pending` no DB para retry assíncrono via Laravel Queue local (apenas para emails, não para jobs Nextcloud) |
| **Storage de backups** (read-only, backups vivem no servidor scripts)                                  | Read via `manage.sh <slug> _ credentials` (link/path) | Saída                  | SSH (mesma chave)                                                                                                       | Mostrar "backup indisponível" se servidor offline                                                                                                         |

**Autenticação do painel para usuários internos**: email + senha + sessão Laravel (Fortify ou Breeze). Sem SSO no MVP.

**Autenticação da API mework360-deployer para clientes externos** (consumidores Bearer): a API expõe Bearer tokens via tabela `api_keys`. A tela de gerenciamento (`/api-keys`) faz parte do MVP — exibe, filtra e revoga tokens existentes. Geração de novos tokens é realizada pelo admin via painel (Livewire `ApiKeys\Index`). Geração por auto-serviço via API pública fica para sprint futura.

**Autenticação SSH (mework360-deployer → nextcloud-saas-manager)**:

- Usuário não-privilegiado `ncsaas-api` no servidor scripts (gerenciado por DevOps Defensys)
- Chave SSH dedicada e rotacionável; uma chave por cluster_server
- `sudoers` no servidor scripts permite **apenas** `manage.sh` com NOPASSWD (responsabilidade do servidor scripts)
- Logs de invocação em `journald` do servidor (tag `ncsaas-api-ssh`); API mantém audit local independente

**Autenticação Webhook (nextcloud-saas-manager → mework360-deployer)**:

- Endpoint público `/api/jobs/hook`
- HMAC-SHA256 obrigatório com secret por cluster_server
- IP whitelist (apenas `cluster_servers.ssh_host`)
- Rate limit 100 req/min por IP
- Replay protection: rejeitar callbacks com `finished_at` > 1h atrás

---

## 6. Requisitos Não-Funcionais

| Categoria                       | Requisito                                                             | Meta                                                                                                                   |
| ------------------------------- | --------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Performance**                 | Latência API → SSH → response JSON do `manage.sh ... --async`         | < 3s (SSH adiciona ~100-500ms; alvo upstream <2s)                                                                      |
| Performance                     | Webhook receiver `POST /api/jobs/hook` p99                            | < 500ms                                                                                                                |
| Performance                     | Listagem de customers (até 200)                                       | < 2s                                                                                                                   |
| Performance                     | Operações OCC sync via `occ-exec`                                     | < 5s p90; timeout 60s                                                                                                  |
| Performance                     | Provisionamento customer ponta a ponta (do clique ao webhook success) | < 5min (alvo); até 15min aceitável dependendo de carga do servidor                                                     |
| **Escalabilidade**              | Customers ativos suportados no MVP                                    | até ~50 (alinhado com limite atual do servidor scripts)                                                                |
| Escalabilidade                  | Jobs simultâneos                                                      | 1 por vez por cluster_server (worker é sequencial nos scripts)                                                         |
| Escalabilidade                  | Servidores upstream no MVP                                            | **1 cluster_server único**                                                                                             |
| Escalabilidade                  | Operadores cadastrados                                                | até 50                                                                                                                 |
| **Disponibilidade**             | Uptime alvo da API + painel                                           | 99%                                                                                                                    |
| Disponibilidade                 | Tolerância a indisponibilidade do `nextcloud-saas-manager`            | API continua operacional para read-only e audit; mutações ficam em fila local de retry com backoff até servidor voltar |
| **Segurança**                   | Compliance                                                            | LGPD aplicável (dados de operadores meWork360)                                                                         |
| Segurança                       | Audit log                                                             | Obrigatório para toda mutação; retenção 12 meses                                                                       |
| Segurança                       | Secrets (SSH key, webhook secret)                                     | Laravel encrypted storage (criptografados em DB com `APP_KEY`)                                                         |
| Segurança                       | Senha em mutações de user                                             | NUNCA em argv; sempre via `--payload-stdin` para SSH                                                                   |
| Segurança                       | Webhook                                                               | HMAC-SHA256 obrigatório + IP whitelist + replay protection 1h                                                          |
| Segurança                       | Rate limit login painel                                               | 5 tentativas / 15min por IP                                                                                            |
| Segurança                       | Rate limit webhook receiver                                           | 100 req/min por IP                                                                                                     |
| **Compatibilidade upstream**    | Versão mínima do `nextcloud-saas-manager`                             | v12.0+ (com Features N, O, P, idempotency, callback HMAC)                                                              |
| Compatibilidade upstream        | Versão mínima Nextcloud nos cluster_servers                           | **≥ 31** (necessário para `group:rename` na Feature 6.2)                                                               |
| Compatibilidade upstream        | Schema version do contrato CLI                                        | Trava em `1`; bloqueio automático se servidor scripts retornar `schema_version` diferente                              |
| **Disponibilidade do upstream** | Uptime do `nextcloud-saas-manager` (dependência)                      | ≥ 99.9% (definido pelo time scripts; nossa SLA depende disso)                                                          |
| **Acessibilidade**              | Padrão                                                                | WCAG 2.1 AA (boa intenção; sem auditoria formal no MVP)                                                                |
| **Plataforma**                  | Suporte                                                               | Desktop only (Chrome, Firefox, Edge — versões recentes)                                                                |
| **Idioma**                      | Suporte                                                               | pt-BR apenas                                                                                                           |

---

## 7. Fora de Escopo

> Decidido junto com o usuário; reflete o pivot arquitetural para orquestrador.

**Continua fora (já estava na v0.1):**

- **Self-service para customers** — painel é interno meWork360 apenas
- **Mobile / responsivo completo** — desktop only no MVP
- **Internacionalização** — só pt-BR no MVP
- **Logs de provisioning em streaming "terminal-like"** — operador vê `output` do job após conclusão, sem live tail
- **SSO corporativo** — login tradicional Laravel apenas
- **Self-onboarding de operadores** — todos cadastrados manualmente por admin
- **Métricas time-series no dashboard (charts de requests/latência)** — sprint futuro
- **Dashboard agregado com KPIs visuais** — entra como SHOULD-HAVE em sprint 2

**Sai do escopo da API com o pivot (passa a ser responsabilidade do `nextcloud-saas-manager`):**

- **Implementar fila Laravel + Horizon + Redis local para jobs Nextcloud** — fila vive no servidor scripts (Redis lá; AOF habilitado pelo time scripts)
- **Implementar worker que executa provisionamento, OCC, Docker** — é o `nextcloud-saas-worker.service` do servidor scripts
- **Lógica direta de OCC, Docker, SSH para Nextcloud** — vive nos scripts Bash
- **Decisão de versão Nextcloud, política de backup local, off-site backup** — domínio dos scripts
- **Implementar `--dry-run`, idempotency-key generation, retry de job no worker** — já existe nos scripts
- **Construir painel web de DevOps puro do servidor** — Feature I do scripts (P3) era pra ser este painel; agora é o `mework360-deployer`

**Multi-server no MVP**: tabela `cluster_servers` é multi-row capable, mas operação **assume 1 servidor único**; multi-server real fica para v2.

---

## 8. Premissas

- **A API é orquestradora**: não implementa Nextcloud nativamente; depende criticamente de `nextcloud-saas-manager` v12.0+ em produção
- **`nextcloud-saas-manager` v12.0+ está implantado** ou será implantado em paralelo, fornecendo: Features N (async + worker + callback), O (lifecycle user/group/apps), P (occ-exec sync passthrough), D (idempotency + dry-run + confirm), e contratos CLI/JSON/Webhook documentados em `CONTRACTS.md`
- **Stack do servidor scripts é fixa** (Bash + Docker Compose + Redis + systemd worker); reescrita não está sobre a mesa
- **Schema version do contrato CLI** trava em `1` no MVP; mudanças exigem PR coordenado entre repos
- **MVP usa 1 cluster_server único**; multi-server fica para v2 (mas tabela já estruturada)
- **Cluster servers rodam Nextcloud ≥ 31**; versões inferiores bloqueiam features que dependem de `group:rename` (validado pela Feature 9)
- **Time `nextcloud-saas-manager` está em comunicação direta** com o time desta API (cross-repo coordination); mudanças no contrato exigem PR + bump de schema_version + janela de migração
- **Livewire 3** é o framework do painel admin (decisão implementada); layout com sidebar esquerda fixa, topbar, paleta Material Design 3 ("stitch"), fontes Inter + Fira Code, Tailwind CSS v4
- **Database desta API**: MariaDB 11 (migrada de PostgreSQL 16 em 2026-05-14); UUIDs nativos via `UUID()`, tipo `JSON` (sem `jsonb`), índices `FULLTEXT` para buscas textuais
- **LGPD**: operadores são funcionários meWork360 com termo de uso interno; não tratamos dados pessoais de end-users dos Nextcloud (esses são responsabilidade dos customers, hospedados nas instâncias)
- **Webhook secret** é gerenciado por cluster_server; rotação manual no MVP, com grace period de 24h aceitando versão antiga + nova
- **SSH key** é gerenciada manualmente no MVP; rotação automática fica para sprint futura (alinhada com Dúvida 4 do scripts)
- **Threshold base64 inline ↔ SCP staging**: 256KB por anexo, alinhado com `nextcloud-saas-manager` CONTRACTS.md §3.9.0

---

## 9. Dúvidas em Aberto

| #   | Dúvida                                                                                                                                                    | Impacto                            | Status                                                          |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------- | --------------------------------------------------------------- |
| 1   | Confirmação de coordenação cross-repo: time `nextcloud-saas-manager` está alinhado com escopo da v12.0 e prazo do MVP da API?                             | Alto (bloqueador se descoordenado) | Aberta — gestão precisa confirmar                               |
| 2   | Webhook secret: rotação manual com janela de 24h é suficiente, ou precisamos de 7d para alinhamento operacional?                                          | Médio                              | Aberta — discutir com time scripts                              |
| 3   | Política de retry da API quando `cluster_server` está `unreachable`: backoff exponencial até quanto tempo? Quando alertar admin?                          | Médio                              | Aberta — sugestão: 1min, 5min, 15min, 1h, depois alerta         |
| 4   | Job antigo (>30d) com log expirado: API mostra "log indisponível" e ponto, ou permite admin acessar journald upstream via SSH ao vivo?                    | Baixo                              | Aberta — sugestão: link "ver journald" para admin               |
| 5   | Sync diário de customers (via `manage.sh list --json`): horário fixo (03h) ou dinâmico baseado em carga?                                                  | Baixo                              | Aberta — sugestão: 03h fixo + sob demanda                       |
| 6   | Webhook IP whitelist: aceita apenas IP exato do `cluster_server.ssh_host`, ou range? Considerar NAT?                                                      | Médio                              | Aberta — sugestão: IP exato; NAT exige nova feature             |
| 7   | OCC `files:scan` longo (>60s): UI orienta usar Feature O.4 mas como? Botão "executar em batch" cria job custom?                                           | Médio                              | Aberta — definir UX                                             |
| 8   | Estratégia para operações compostas no painel (criar user + adicionar a 3 grupos): UI faz 1 chamada Feature O.2 com tudo, ou 1+3 chamadas separadas?      | Baixo                              | Aberta — sugestão: 1 chamada O.2 atômica (decisão N2 do escopo) |
| 9   | Reconciliação de customers (gap entre réplica local e upstream): se detectar customer no upstream que não está na API, criar automático ou alertar admin? | Médio                              | Aberta — sugestão: alertar e exigir aprovação manual            |

**Dúvidas resolvidas pelo pivot (v0.1 → v0.2):**

| # antiga | Dúvida                                                   | Resolução                                                                          |
| -------- | -------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| 2 (v0.1) | Backups de remoção ficam onde?                           | Responsabilidade dos scripts; API só linka backup disponível                       |
| 3 (v0.1) | "Email service" — qual provider?                         | Independente do pivot; ainda aberta — sugestão: SES ou SMTP interno                |
| 4 (v0.1) | Há limite de versão Nextcloud suportado?                 | **Resolvida**: ≥ 31 obrigatório (Feature 9 valida)                                 |
| 7 (v0.1) | Disable de apps via fila — usar mesmo padrão de polling? | **Resolvida**: usa Feature O.4 do scripts (async batch) com callback               |
| 8 (v0.1) | Fila Laravel: Horizon + Redis confirmados?               | **Resolvida**: fila vive nos scripts; API não tem fila própria para jobs Nextcloud |

---

## 10. Mapa de Telas

> 8 telas Stitch identificadas + telas adicionais necessárias. **Decisão:** painel único unificado (CloudAdmin + DevPortal consolidados).

| #   | Tela                            | Rota MVP                        | Componentes principais                                     | Features       | Status                       |
| --- | ------------------------------- | ------------------------------- | ---------------------------------------------------------- | -------------- | ---------------------------- |
| 1   | Login                           | `/login`                        | Email + senha + lembrar-me                                 | F1             | **MVP** ✓                    |
| 2   | Dashboard                       | `/dashboard`                    | Cards: count customers, jobs do dia, status cluster_server | F2/F5 (resumo) | **MVP** ✓ parcial (sem charts) |
| 3   | Lista de customers              | `/customers`                    | Tabela com filtros, paginação, busca                       | F2             | **MVP** ✓                    |
| 4   | Provisionar customer            | `/customers/create`             | Formulário + upload de anexos + preview                    | F3             | **MVP** ✓                    |
| 5   | Detalhe do customer             | `/customers/{slug}`             | Overview + ações + status atual + jobs recentes            | Várias         | **MVP** ✓                    |
| 6   | OCC do customer                 | `/customers/{slug}/occ`         | Quota, branding, manutenção, apps, users, groups           | F6             | **MVP** ✓                    |
| 7   | Fila de jobs                    | `/queue`                        | Stats cards + tabela + filtros + auto-refresh              | F5             | **MVP** ✓                    |
| 8   | Logs de Requisições             | `/audit`                        | Tabela com filtros, paginação, retenção 12m                | F7             | **MVP** ✓                    |
| 9   | Logs de Provisionamento         | `/queue` (filtro job_type)      | Filtros por tipo + estado; link para detalhe do job        | F5, F7         | **MVP** ✓                    |
| 10  | API Keys                        | `/api-keys`                     | Tabela + gerar/revogar Bearer tokens                       | —              | **MVP** ✓                    |
| 11  | Operadores                      | `/operators`                    | CRUD de operadores (admin only)                            | F1             | **MVP** ✓                    |
| 12  | Cluster servers                 | `/cluster-servers`              | CRUD + testar conexão + rotacionar secret (admin only)     | F9             | **MVP** ✓                    |
| 13  | Settings security               | `/settings`                     | IP allowlist, webhooks externos, rate limit                | —              | Sprint futuro                |

> **Layout implementado (2026-05-14)**: sidebar esquerda fixa com navegação agrupada (Dashboard, Credenciais Provisionadas, Log de Requisições, Log de Provisionamentos, Configurações), topbar com avatar/logout, paleta Material Design 3 "stitch". Painel é somente gerenciador de credenciais — provisionamento ocorre exclusivamente via API REST.

**Mapeamento original do protótipo Stitch:**

| Tela do protótipo                                  | Mapeada para                                                         |
| -------------------------------------------------- | -------------------------------------------------------------------- |
| `dashboard_overview` + `dashboard_api_management`  | Tela #2 — `/dashboard` ✓                                            |
| `provisioning_queue`                               | Tela #7 — `/queue` ✓                                                |
| `provisioning_logs`                                | Tela #9 — `/queue` com filtro job_type (sem streaming live) ✓        |
| `logs_de_requisi_es`                               | Tela #8 — `/audit` ✓                                                |
| `api_credentials` + `gerenciamento_de_credenciais` | Tela #10 — `/api-keys` ✓ (implementado no MVP)                      |
| `configura_es_e_seguran_a`                         | Tela #12 — `/cluster-servers` ✓ + Tela #13 `/settings` (sprint futura) |

---

## 11. Mapa do Sistema Externo (`nextcloud-saas-manager`)

### Stack do upstream

- **Linguagem**: Bash (~2.451 linhas)
- **Versão alvo**: v12.0+ (`development` branch consolidada em produção)
- **Orquestração local do servidor**: Docker Engine 29.x + Docker Compose v2
- **Reverse proxy**: Traefik v3.x + Let's Encrypt
- **Database compartilhado**: MariaDB 10.11
- **Cache + fila**: Redis (alpine) compartilhado, AOF habilitado, dbindex dedicado para fila (`nc:jobs:queue`, `nc:jobs:<id>`, `nc:idem:<key>`)
- **Worker**: `nextcloud-saas-worker.service` (systemd, sequencial 1 job por vez)
- **OS**: Ubuntu 24.04 LTS
- **Auth**: SSH key dedicada `ncsaas-api` + sudoers restrito a `manage.sh`

### Contratos consumidos pela API

**1. CLI invocada via SSH:**

```text
INPUT:  ssh ncsaas-api@<host> manage.sh <slug> <dom|_> <cmd> [--async] [--idempotency-key=K] [--callback=URL] [--dry-run] [--json] [--apps=...] [--full-apps] [--staging-id=<uuid>] [--payload-stdin] [--confirm=<slug>] [--force] [--backup-first] [--strict] [--state=...] [--cmd=...] [--client=...] [--limit=N] [--offset=N | --after=<job_id>]
OUTPUT (stdout):
  {"job_id":"<uuid-v4>","state":"queued","queued_at":"<ISO8601>"}                    # async
  {"state":"success","exit_code":0,"summary":{...}}                                   # sync
  {"checks":[...],"summary":{"ok":N,"warn":N,"fail":N}}                               # health
  {"error":"<code>","message":"...","retry_after":N}                                  # erros
EXIT CODES: 0 sucesso/enfileirado | 1 warning | 2 fail/queue_unavailable | 3 idempotency_conflict | 4 state_conflict | 10+ erros técnicos | 14 instance_not_running | 15 occ_timeout | 17 client_busy_async_job_running | 100 occ_command_not_allowed
```

**2. Comandos relevantes para a API (subset do CONTRACTS.md):**

| Comando                                                                                        | Uso na API                     | Sync/Async          |
| ---------------------------------------------------------------------------------------------- | ------------------------------ | ------------------- |
| `manage.sh <slug> <dom> create [...]`                                                          | F3                             | Async               |
| `manage.sh <slug> _ remove [--confirm] [--backup-first] [--force]`                             | F4                             | Async               |
| `manage.sh <slug> _ backup`                                                                    | F4 (encadeado)                 | Async               |
| `manage.sh <slug> _ status`                                                                    | F2/F5 (status pontual)         | Sync                |
| `manage.sh <slug> _ credentials`                                                               | F4 (link backup)               | Sync                |
| `manage.sh list [--json]`                                                                      | F2 (sync diário + sob demanda) | Sync                |
| `manage.sh job <id> status [--json]`                                                           | F5 (polling fallback)          | Sync                |
| `manage.sh job <id> logs`                                                                      | F5 (sob demanda)               | Sync                |
| `manage.sh job <id> cancel`                                                                    | F5                             | Sync                |
| `manage.sh job list [--state=...] [--cmd=...] [--client=...] [--limit=N] [--after=<id>]`       | F5 (reconciliação)             | Sync                |
| `manage.sh worker status [--json]`                                                             | F5/F9                          | Sync                |
| `manage.sh worker stats [--by-cmd] [--by-client] [--json]`                                     | F5 (stats agregadas)           | Sync                |
| `manage.sh health [--json]`                                                                    | F9 (health check)              | Sync                |
| `manage.sh <slug> user create/modify/remove [--payload-stdin]`                                 | F6.1                           | Async (Feature O.2) |
| `manage.sh <slug> group create/modify/remove [--payload-stdin]`                                | F6.2                           | Async (Feature O.3) |
| `manage.sh <slug> apps enable <a,b,c> [--strict]`                                              | F6.3                           | Async (Feature O.4) |
| `manage.sh <slug> apps disable <a,b,c> [--strict] [--payload-stdin]`                           | F6.3                           | Async               |
| `manage.sh <slug> occ-exec <subcmd> [args] [--json] [--payload-stdin] [--staging-id=<uuid>]`   | F6 (sync OCC)                  | Sync (timeout 60s)  |
| `scp -i <key> <file> ncsaas-api@<host>:/opt/nextcloud-customers/inbox/<staging-id>/<filename>` | F3 (anexos > 256KB)            | Sync                |

**3. Webhook recebido pela API:**

```http
POST /api/jobs/hook HTTP/1.1
Host: <api>
Content-Type: application/json
X-Signature: sha256=<hex-hmac-sha256(secret, body)>

{
  "job_id": "<uuid-v4>",
  "state": "success" | "failed",
  "exit_code": 0,
  "finished_at": "<ISO8601>",
  "summary": { ... },
  "log_url": "/var/log/nextcloud-saas/jobs/<uuid>.log"
}
```

### Modelo de dados local da API (espelho + nativo)

| Tabela                   | Tipo                               | Origem                                                            | Campos principais                                                                                                                                                                                                                                             |
| ------------------------ | ---------------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `operators`              | Nativa                             | API                                                               | `id`, `email`, `name`, `role`, `password_hash`, `last_login_at`, `status`                                                                                                                                                                                     |
| `cluster_servers`        | Nativa                             | API                                                               | `id`, `name`, `ssh_host`, `ssh_port`, `ssh_user`, `ssh_private_key_encrypted`, `webhook_secret_encrypted`, `webhook_secret_version`, `nextcloud_version`, `schema_version`, `status`, `last_health_at`                                                        |
| `customers`              | **Réplica espelhada**              | Sync via callbacks de `create`/`remove` + `manage.sh list` diário | `slug` (PK, `^[a-z0-9-]+$`), `cluster_server_id` (FK), `domain`, `status`, `created_at`, `last_sync_at`, `branding_meta`                                                                                                                                      |
| `jobs`                   | **Réplica espelhada + indefinida** | Sync via webhook + polling SSH                                    | `job_id` (UUID v4 PK), `customer_slug` (FK), `cluster_server_id` (FK), `cmd_canonical`, `job_type`, `state`, `idempotency_key`, `payload_sanitized`, `summary`, `exit_code`, `queued_at`, `started_at`, `finished_at`, `callback_received_at`, `last_poll_at` |
| `audit_logs`             | Nativa                             | API                                                               | `id`, `actor_id` (FK operator), `action`, `resource_type`, `resource_id`, `payload` (jsonb sanitizado), `cluster_server_id` (nullable), `job_id` (nullable), `ip`, `user_agent`, `created_at`                                                                 |
| `webhook_secret_history` | Nativa                             | API                                                               | `id`, `cluster_server_id` (FK), `secret_encrypted`, `version`, `valid_from`, `valid_until` (grace period 24h)                                                                                                                                                 |
| `idempotency_keys`       | Nativa                             | API                                                               | `key` (UUID v4 PK), `cmd`, `args_hash`, `customer_slug`, `job_id` (nullable), `created_at`, `expires_at` (24h)                                                                                                                                                |
| `api_keys`               | Nativa                             | API                                                               | `id`, `name`, `token_hash`, `scopes`, `last_used_at`, `revoked_at`, `created_at` — gerenciado via painel `/api-keys` (MVP ✓)                                                                                                                                  |

---

## 12. Checklist de Integração com `nextcloud-saas-manager`

> Itens que o time da API deve implementar/decidir para a integração funcionar. Validados com o time scripts em coordenação cross-repo.

### Implementação obrigatória (Feature 10 + outras)

- [x] **Validador de slug**: rejeitar `_`, maiúsculas, especiais e >64 chars com 422 antes de qualquer SSH ou query (Feature 3, 10)
  - Regex: `^[a-z0-9-]+$`, max 64 chars
  - Mensagem em pt-BR: "Use apenas letras minúsculas, números e hífen (sem underscore); até 64 caracteres"
- [x] **Tradução bidirecional `state ↔ status`**: tabela 1:1 com guard ortográfico para `cancelled` (2 'l') vs `canceled` (incorreto). Implementar em `app/Services/StateTranslator.php` com testes (Feature 10)
- [x] **Tradução `cmd ↔ job_type`**: 15 verbs mapeados em `app/Services/JobTypeTranslator.php` com teste unitário cobrindo cada par (Feature 10)
- [x] **Threshold base64 inline vs SCP staging**: **256KB por anexo** (alinhado com `nextcloud-saas-manager` CONTRACTS.md §3.9.0). Documentado em Feature 3 e na premissa
- [x] **Tabela `cluster_servers`** com `server_ip`/`ssh_host` para roteamento (Feature 9). Incluir `nextcloud_version` validado a cada sync; bloquear `group:rename` em clusters com Nextcloud < 31 (Feature 6.2)

### Decisões pendentes (se tornam Dúvidas em Aberto §9)

- [ ] Webhook secret rotation grace period: 24h ou 7d? (Dúvida #2)
- [ ] Backoff de retry quando cluster_server `unreachable`: progressão e ponto de alerta (Dúvida #3)
- [ ] Política para job antigo com log expirado >30d (Dúvida #4)
- [ ] Reconciliação de gaps entre réplica e upstream: automática ou manual? (Dúvida #9)

### Coordenação cross-repo

- [ ] PR coordenado entre `mework360-deployer` e `nextcloud-saas-manager` para atualizar `OpenAPI.yaml` removendo underscore do pattern de slug e ajustando max length para 64 chars (atualmente o OpenAPI permite `_`)
- [ ] PR para registrar `schema_version=1` formal no contrato (Q-8 do scripts)
- [ ] Comunicação operacional: time scripts confirma janela de deploy v12.0 antes do MVP da API ir para staging

---

## Histórico de Revisões

| Data       | Versão | Alteração                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | Autor                                              |
| ---------- | ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| 2026-05-07 | 0.1    | Versão inicial gerada via `/analista planejar` (PROTÓTIPO + ANÁLISE OpenAPI)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | Analista de Requisitos (IA)                        |
| 2026-05-07 | 0.2    | **Pivot arquitetural**: descoberta de `nextcloud-saas-manager` como sistema upstream. API passa a ser orquestradora (SSH + Webhook HMAC), não implementadora. Features F3/F4/F5/F6 reescritas; F8 (webhook receiver), F9 (cluster*servers + secrets), F10 (tradução de vocabulários) adicionadas. Decisões: 1 cluster_server único MVP, sempre Feature O.2 async para user/group lifecycle, Laravel encrypted storage, threshold 256KB, slug com `*` rejeitado com 422 (sem normalização), Nextcloud ≥ 31 obrigatório. Seção 12 adicionada com checklist de integração. | Analista de Requisitos (IA) via `/analista escopo` |
