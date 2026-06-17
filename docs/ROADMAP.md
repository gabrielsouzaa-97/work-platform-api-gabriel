# Roadmap Tecnico — mework360-deployer

> Gerado em: 2026-05-07
> Atualizado em: 2026-06-12
> Fase: 9 — Planejamento Tecnico
> Baseado em: docs/REQUIREMENTS.md v0.3 + docs/ARCHITECTURE.md v0.2 + docs/openapi.yaml v2.0 + docs/db-schema.dbml + docs/DATABASE.md
> Status: MVP concluido (199/199 testes; D8 implementado; deploy staging pendente — tarefa humana)
> Modo de execucao: Pipeline / autopilot (`/jarvis pipeline`)

---

## Resumo

| Metrica                                 | Valor                                                                |
| --------------------------------------- | -------------------------------------------------------------------- |
| Total de tarefas                        | 44                                                                   |
| Total de sprints                        | 8 (todas categoria D)                                                |
| Tarefas P (atomicas)                    | 25                                                                   |
| Tarefas M (com executor_prompt)         | 19                                                                   |
| Tarefas G                               | 0 (proibido — decompor)                                              |
| Tarefas com `critica: true` (Best-of-N) | 3 (D2.1 SshClient, D5.1 Webhook receiver, D6.2 Provisionar customer) |
| Caminho critico                         | 6 sprints sequenciais (D1 → D2 → D4 → D5 → D6 → D8)                  |
| Modulos cobertos                        | Core, ClusterServers, Auth, Audit, Jobs, Customers                   |

---

## Errata — SSH API Reference (2026-05-13)

> Documentacao autoritativa `docs/SSH API Reference — Nextcloud SaaS.md` revelou divergencias em relacao ao REQUIREMENTS v0.2. Aplicadas como correcoes inline nas sprints afetadas. Resumo:

| #   | Correcao                                                                                                                                                                                      | Sprints afetadas   |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| E1  | Binario correto: `nextcloud-manage` (nao `manage.sh`). Symlink em `/usr/local/bin/nextcloud-manage`. SSH user: `root` (nao `ncsaas-api`).                                                     | D5, D6, D7         |
| E2  | **StateTranslator corrigido** (D2 bug): upstream usa `queued/running/done/failed/cancelled`; anterior usava `pending/running/done/error/aborted`. Unico rename: `done → success`.             | D2 (corrigido), D5 |
| E3  | `create` exige `<domain>` posicional: `nextcloud-manage <client> <domain> create [flags]`. Outros comandos usam `_` no lugar do dominio.                                                      | D6                 |
| E4  | `remove`: flag correta e `--force` (booleano). `--confirm=<client>` existe como alternativa mas nao e o padrao.                                                                               | D6                 |
| E5  | `list` e `status` **nao suportam `--json`** — retornam texto livre. Parsing por regex/linha obrigatorio.                                                                                      | D6                 |
| E6  | Exit codes adicionados ao SshClient: `5` = validacao (validationFailed) e `99` = nao implementado (notImplemented).                                                                           | D5, D6             |
| E7  | Payload do webhook upstream (secao 7 SSH API Ref): `{job_id, state, cmd, client, exit_code, finished_at}`. Campo `summary` **nao existe** no payload webhook (existe apenas em `job status`). | D5                 |
| E8  | `occ-exec` usa namespace syntax sem domain: `nextcloud-manage <client> occ-exec <subcmd>`.                                                                                                    | D7                 |
| E9  | Callback URL deve ser `https://` (IPs RFC 1918 rejeitados pelo upstream — SSRF defense). Em staging, garantir HTTPS ou usar ngrok/tunnel.                                                     | D6                 |

---

## Errata — Database e Painel Admin (2026-05-14)

> Alteracoes de arquitetura aplicadas durante D8 Polish apos conclusao da implementacao MVP.

| #   | Correcao                                                                                                                                                                                                                                                                                                                               | Impacto |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| E10 | **Database migrado de PostgreSQL 16 para MariaDB 11** — UUIDs via `(UUID())` (nativo, sem extensao), tipo `JSON` (sem `jsonb`), indices `FULLTEXT` para buscas textuais, health-check `healthcheck.sh --connect --innodb_initialized`. Migrations D1 e migration `2026_05_14_000001_add_missing_indexes_d8_polish.php` atualizadas.    | D1, D8  |
| E11 | **Painel admin redesenhado** — layout com sidebar esquerda fixa, topbar, paleta Material Design 3 ("stitch"), Tailwind CSS v4, fontes Inter + Fira Code. Foco em gerenciador de credenciais: painel NAO faz provisionamento, somente a API REST. Rotas atualizadas: `/dashboard`, `/audit`, `/api-keys`, `/cluster-servers`, `/queue`. | D3, D8  |
| E12 | **WebhookHandler**: webhook duplicado (replay) retorna `204` (idempotente) em vez de `409`. Customer status propagado pelo handler no terminal do job (provision→active, deprovision→removed, falha→failed).                                                                                                                           | D5, D6  |
| E13 | **API Keys no MVP**: tela `/api-keys` (Livewire `ApiKeys\Index`) disponivel no MVP — visualizacao, filtragem e revogacao de tokens. Anteriormente classificado como Sprint 2.                                                                                                                                                          | D8      |

---

## Indice de Sprints

> Agentes: leiam ESTE indice primeiro. So facam Read da secao completa se precisarem de notas tecnicas ou detalhes de tasks.

| Sprint | Categoria | Gate (resumo)                                                                                                                    | Status    | Tasks | Modulos               | Resumo                                                     | Linhas    |
| ------ | --------- | -------------------------------------------------------------------------------------------------------------------------------- | --------- | ----- | --------------------- | ---------------------------------------------------------- | --------- |
| D1     | D         | App sobe via docker-compose; migrations das 8 tabelas aplicadas; smoke /health 200                                               | concluida | 6     | infra, database       | Foundation: scaffold Laravel + DB + smoke test             | 90-220    |
| D2     | D         | SshClient executa comando mockado; tradutores cobrem 15 verbs + 5 estados; slug `_` rejeitado 422                                | concluida | 5     | Core                  | Core: SshClient + Tradutores + Slug Validator              | 221-470   |
| D3     | D         | Admin convida operador → email enviado → operador define senha → loga; suporte sem opcoes de provisionar/remover                 | concluida | 5     | Auth                  | Auth: Login + cadastro de operadores (F1)                  | 471-680   |
| D4     | D         | Admin cria cluster_server (encrypted); rotate webhook secret aceita ambos por 24h; audit log registra acoes                      | auditada  | 6     | ClusterServers, Audit | ClusterServers (F9) + Audit (F7 base)                      | 681-960   |
| D5     | D         | Webhook HMAC valido atualiza estado; HMAC invalido 401 + alerta; replay > 1h rejeitado                                           | auditada  | 5     | Jobs                  | Jobs: Webhook receiver (F8) + listagem fila (F5)           | 961-1180  |
| D6     | D         | Marina provisiona customer via UI → SSH → webhook conclui em <5min; slug `_` 422; anexo 800KB via SCP; remove com --backup-first | auditada  | 6     | Customers             | Customers: provisionar + listar + remover (F2+F3+F4+F10)   | 1181-1490 |
| D7     | D         | Operador define quota via UI (sync 60s); cria user via async (job_id retornado, webhook conclui)                                 | auditada  | 5     | Customers, Jobs       | OCC essenciais: sync passthrough + async lifecycle (F6)    | 1491-1700 |
| D8     | D         | CI verde; auditorias sem CRITICAL/HIGH; staging valida fluxo Marina end-to-end; retention 12m ativo                              | concluida | 6     | todos                 | Polish: Audit retention (F7) + Auditorias + Deploy staging | 1701-1900 |
| F1     | F         | Admin gera credencial → token exibido uma vez; revogar seta revoked_at; audit log registra ambas as acoes                        | concluida | 1     | ApiKeys               | Fix MVP incompleto: Gerar + Revogar Bearer tokens no painel | 2650+    |
| F2     | F         | Dashboard chart 7d; clipboard fix; fila redesenhada; log detalhado job; alterar senha; editar perfil; artisan admin; findings   | concluída  | 11    | Auth, Core, Jobs, painel | Sprint 2: UX + fila provisionamento + findings backlog   | 2720+    |
| F3     | F         | 0 findings LOW cobertos; pt-BR; AuditLog rotate semantico; FK sessions; UNIQUE invite_token_hash; $fillable restrito; args SSH mascarados | concluida | 7     | Core, ClusterServers, Auth | Tech Debt LOW: Schema + Security + Observability | 2758+    |
| N1     | N         | criar cluster → SSH `config set-webhook-secret` chamado; rotacionar → SSH com novo secret; secret via stdin; CI verde | concluida | 1 | ClusterServers | Sync Webhook Secret com Upstream via SSH | 2840+    |
| N2     | N         | APP_ENV=local emite Log::debug('webhook.payload_received'); APP_ENV=testing não emite; 46/46 testes da suite de webhook passando | concluida | 1 | Jobs | Observabilidade: log de payload do webhook em ambiente local | 3013+    |
| F5     | F         | Lifecycle async: cmd canônico → argv upstream; apps CSV; OccPanel same-path createUser | **concluída** | 11    | Customers, Core/Ssh, Livewire | ISSUE-006 — F5.11 done; cleanup F5 via F11; **R3 APROVADA** (2026-06-02) | 3047+    |
| F6     | F         | Forgot-password broker nativo Laravel (operadores) + logs de Job populados via SSH `nextcloud-manage job <id> status --json` pós-`job.finished` (corrige queue/{jobId} vazio) | concluida | 6     | Auth, Jobs, Core/Ssh | ISSUE-008 + ISSUE-009 — validado Rock 2026-06-09 (29 testes ForgotPassword+JobLogFetcher) | 3578+    |
| F7     | F         | Create cluster atômico; actor_id no AuditLog de rotate; teste erro "sem secret atual" | concluida | 3     | ClusterServers | CQ-N1-001/002 + QA-N1-001 — Rock 2026-06-09 | 3805+    |
| F8     | F         | Provision success não marca tenant `active` antes de probe; `users:*` retorna 503 até readiness confirmada | **concluída** | 10    | Jobs, Customers, Webhook | ISSUE-010 — validada APROVADA R1 | 3865+    |
| F9     | F         | 404 sob `/api/*` retorna JSON (sem depender de `Accept: application/json`) | **concluída** | 2     | Core (HTTP layer) | ISSUE-012 — validação APROVADA R1 (2026-05-24) | 4003+    |
| F10    | F         | `JobLogFetcher` usa argv introspection `job <id> logs`; `/queue/{jobId}` exibe logs pós-deploy | **ativa** | 3     | Jobs, Core/Ssh | ISSUE-014 — F10.1–F10.2 done; **F10.3 deploy/ISSUE-023 pendente** | 4055+    |
| F11    | F         | Slug reuse pós `provision.failed` + cleanup MEDIUM F5 | **concluída** | 6     | Customers, Core | ISSUE-018 — validação APROVADA R1 (2026-05-24) | 4082+    |
| F12    | F         | `SshClient` normaliza exceções de transporte phpseclib durante `exec()` e reaplica retry | **concluída** | 1 | Core/Ssh, Customers | ISSUE-020 — código done; auditoria formal não registrada | 4227+    |
| F13    | F         | Job `create` inclui branding no contrato upstream: `branding.logo_data_url` via stdin ou `--staging-id` via SFTP | **concluída** | 4 | Customers, Core/Ssh | ISSUE-019 — validação senior+qa APROVADA R1 | 4256+ |
| F14    | F         | CI verde no main: regressão N19 (6 testes) + bump phpseclib >=3.0.54 | **concluída** | 4 | Audit, ClusterServers, Core | ISSUE-039 — validação APROVADA (2026-06-16) | 4372+ |
| F15    | F         | AuthZ ApiKey: scopes aplicados + binding tenant (SEC-V1-001 / ISSUE-037) | **concluída** | 5 | Core, Auth, Customers, Audit | PR #114 mergeada; validation R2 APROVADA | 4420+ |
| N30    | N         | ISSUE-038 Sprint 0: `/api/v1` aliases + DomainError + spec externo | **concluída** | 7 | Core, Auth, Customers, Jobs | PR #115 mergeada; validation R1 APROVADA | 4500+ |
| N31    | N         | ISSUE-038 Fase 1: PlatformPort mínimo + branding via port | planejada | — | Integration, Customers | Depende N30 + D-02 parcial | — |
| N32    | N         | ISSUE-038 Fase 2: ondas migração + observabilidade transporte | planejada | — | Integration, Jobs, Core | Depende N31 | — |
| N33    | N         | ISSUE-038 Fase 3: despublicar `/occ/*` + capabilities mutação | planejada | — | Customers, Core | Depende N32 + D-02 | — |
| N34    | N         | ISSUE-038 Fase 4: `POST /v1/onboarding` saga | planejada | — | TenantLifecycle | Depende N33 + D-02 resolvido | — |

---

## Indice de Sprints — Platform V2

> Plano mestre: `docs/PLATFORM-V2-PLAN.md` (Farm Agent + integrações comerciais).  
> Categoria **N** — execução multi-repo via `/rock`. Detalhes de tasks nas seções §7–§22 do plano.

| Sprint | Repo execução principal | Gate (resumo) | Status | Depende de |
| ------ | ----------------------- | ------------- | ------ | ---------- |
| N14 | `work-rc-kit` | Dockerfile RC pinado + 21 plugins `me360_*` | concluida | — |
| N15 | `work-platform-scripts` | SEC-004 retrofix + CPU/RAM no compose tenant | concluida | — |
| N16 | `work-platform-scripts` | Canary/ring em `custom-apps update` | concluida | N14 |
| N17 | `work-platform-agent` | Daemon outbound mTLS + poll comandos | concluida | — |
| N18 | `work-platform-api` | FarmRegistry + AgentGateway + feature flag | concluida | N17 |
| N19 | agent + api | Cutover SSH → agente (create/remove) | planejada | N17, N18 |
| N20 | agent + scripts | `tenant.create` + `memail.configure` tipados | planejada | N19 |
| N21 | api + `meApiMail` | Integração mail no pipeline create | planejada | N20 |
| N22 | onboarding-api + api + WHMCS | Signup/trial/billing WHMCS+Vindi | planejada | N21; N29 *(opcional)* |
| N23 | `work-platform-api` | Inventário + placement automático | planejada | N19 |
| N24 | multi-repo | Rollout por ring no agente | planejada | N16, N23 |
| N25 | `work-platform` + scripts | LAB greenfield + BOM promote | planejada | N14, N20 |
| N26 | `work-platform-scripts` | Restore drill mensal automatizado | planejada | — |
| N27 | `work-platform-scripts` | Observabilidade default em deploy-server | planejada | — |
| N28 | api + WHMCS + Proxmox | Tier Dedicated (VPS via WHMCS/`IDC-EVEO`) | planejada | N15, N23 |
| N29 | api + `meApiMail` | DNS & deliverability (PowerDNS) | planejada | N21, N23 |

**Ordem recomendada (`/rock`):** F3/F10.3 → N14 → N15 → N16 → [N17 ∥ N18] → N19 → N20 → N21 → N23 → **N29** → N22 → N25 → [N26 ∥ N27] → N24 → N28

---

## Estrategia de Auditoria

> Modo: **Pipeline / autopilot**
> Auditoria e o **unico gate de qualidade** entre sprints (sem revisao humana). Niveis foram escolhidos conservadoramente.

| Sprint | Review          | Motivo                                                                                                                                                     |
| ------ | --------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1     | `skip`          | Fundacao: scaffold + migrations + configs Docker. Testes Pest bastam (sem logica de negocio).                                                              |
| D2     | `senior+qa`     | Core: integracao externa (SSH), traducoes condicionais. Foundation tecnica. Senior + QA Fase 1 (sem triage completa).                                      |
| D3     | `senior+qa`     | Auth + sessoes + email convite. Dados sensiveis (senhas, tokens).                                                                                          |
| D4     | `senior+qa`     | ClusterServers com Encrypted Storage de SSH keys + webhook secrets. Compliance critica.                                                                    |
| D5     | `senior+qa`     | Webhook receiver com HMAC-SHA256 + replay protection. Vetor de ataque #1.                                                                                  |
| D6     | `senior+qa`     | Customers: operacoes destrutivas (remove com backup), SSH + SCP staging, idempotency.                                                                      |
| D7     | `senior+qa`     | OCC essenciais: multiplos endpoints sensiveis (quota, branding, lifecycle).                                                                                |
| D8     | `comprehensive` | Ultima sprint D — pre-deploy. Triage completa + auditores DBA + Security + Performance + Senior. **[concluida — 199/199 testes; deploy staging pendente]** |

**Niveis (referencia):**

- `skip`: testes bastam. Sem subagents de auditoria.
- `senior+qa`: Senior Code Review + QA Fase 1. 1 subagent, sem triage.
- `comprehensive`: triage completa + todos os auditores relevantes (ate 4).

**Modo Pipeline**: metadata seguida sem perguntar — auditoria e o unico gate de qualidade entre sprints.

---

## Grafo de Dependencias

```
D1 [Scaffold + DB] ─┬─► D2 [Core: SSH + Tradutores] ─┬─► D4 [ClusterServers + Audit] ─► D5 [Jobs Webhook] ─► D6 [Customers] ─┬─► D8 [Polish + Deploy]
                    │                                 │                                                                       │
                    └─► D3 [Auth] (paralelo a D2)     └─► (D3 desbloqueia D4 via roles middleware)                              │
                                                                                                                              │
                                                       D6 ────────────────► D7 [OCC sync + async lifecycle] (paralelo a D8)──┘
```

**Caminho critico** (6 sprints sequenciais): D1 → D2 → D4 → D5 → D6 → D8

**Sprints paralelizaveis**:

- D3 (Auth) pode rodar em paralelo a D2 (Core) — sao raizes independentes
- D7 (OCC) pode rodar em paralelo a D8 (Polish) — D7 depende apenas de D6

---

## Sprint D1 — Foundation

> Categoria: D
> Gate: app sobe via `docker-compose up`; `php artisan migrate` aplica todas as 8 tabelas sem erro; Pest smoke test "GET /health retorna 200" passa
> review: skip

| Status | Tamanho | Tarefa                                                                                                                                     | Skill/Command       | Depende de |
| ------ | ------- | ------------------------------------------------------------------------------------------------------------------------------------------ | ------------------- | ---------- |
| [x]    | P       | 1.1 — Scaffold Laravel 12 (`composer create-project`) + `.env.example` aplicado + `config/database.php` apontando para Postgres do compose | `laravel-docker`    | —          |
| [x]    | P       | 1.2 — Validar `docker-compose up -d` sobe os 5 services com healthchecks verdes                                                            | `laravel-docker`    | 1.1        |
| [x]    | M       | 1.3 — Criar migrations das 8 tabelas conforme `docs/db-schema.dbml`                                                                        | `laravel-migration` | 1.2        |
| [x]    | P       | 1.4 — Criar Models Eloquent base com casts (`encrypted` para SSH keys/webhook secrets, `array` para JSONB)                                 | `laravel-migration` | 1.3        |
| [x]    | P       | 1.5 — Criar Seeder `DatabaseSeeder` (admin operator + 1 cluster_server fake para dev)                                                      | `laravel-migration` | 1.4        |
| [x]    | P       | 1.6 — Configurar Pest (`pest --init`) + factories minimas + smoke test `GET /health retorna 200`                                           | `laravel-testing`   | 1.4        |

**Notas tecnicas (tarefas M):**

<details>
<summary>1.3 — Criar migrations das 8 tabelas conforme db-schema.dbml</summary>

#### Mini Design Doc

**Escopo**: criar as 8 migrations do schema MariaDB 11 (originalmente PostgreSQL 16; migrado em 2026-05-14 — ver E10). NAO inclui logica de negocio nos models (apenas casts e relacionamentos diretos).

**Componentes envolvidos**:

- `database/migrations/`: 8 arquivos timestamped
- `app/Models/`: 8 models Eloquent (apenas estrutura, sem queries)

**Decisoes de design**:

1. MariaDB 11 tem UUID nativo: PKs geradas com `DB::raw('(UUID())')` no default — sem extensao necessaria (migration `enable_uuid_extension` e no-op)
2. Usar `Str::uuid()` no PHP para PKs geradas no app-side

**Riscos**:

1. MariaDB nao usa extensoes; primeira migration e no-op (documentado em `enable_uuid_extension.php`).
2. Soft delete em `customers.slug` (PK varchar) → Eloquent SoftDeletes requer coluna `deleted_at` (presente no DBML). Mitigacao: adicionar `$table->softDeletes()` em `customers`, `operators`, `cluster_servers`.

**Plano de rollback**: Cada migration tem `down()` com `Schema::dropIfExists()` na ordem inversa.

- **Arquivo(s)**:
    - `database/migrations/2026_05_08_000001_enable_uuid_extension.php`
    - `database/migrations/2026_05_08_000002_create_operators_table.php`
    - `database/migrations/2026_05_08_000003_create_cluster_servers_table.php`
    - `database/migrations/2026_05_08_000004_create_customers_table.php`
    - `database/migrations/2026_05_08_000005_create_jobs_table.php`
    - `database/migrations/2026_05_08_000006_create_audit_logs_table.php`
    - `database/migrations/2026_05_08_000007_create_webhook_secret_history_table.php`
    - `database/migrations/2026_05_08_000008_create_idempotency_keys_table.php`
    - `database/migrations/2026_05_08_000009_create_api_keys_table.php`
    - `app/Models/Operator.php`, `ClusterServer.php`, `Customer.php`, `Job.php`, `AuditLog.php`, `WebhookSecretHistory.php`, `IdempotencyKey.php`, `ApiKey.php`
- **Abordagem**: traduzir literalmente cada `Table` do DBML em uma migration Laravel. Para PKs UUID, usar `$table->uuid('id')->primary()->default(DB::raw('(UUID())'))`. Para `customers.slug` (PK varchar 64), usar `$table->string('slug', 64)->primary()`. JSON (MariaDB) → `$table->json()`. Soft delete → `$table->softDeletes()` quando o DBML mencionar `deleted_at`.
- **Decisoes**: sem extensao de UUID — MariaDB 11 tem `UUID()` nativo. Casts: `encrypted` para `ssh_private_key_encrypted`, `webhook_secret_encrypted`, `secret_encrypted`. `array` para colunas JSON (`branding_meta`, `payload_sanitized`, `summary`, `payload`, `scopes`).
- **Edge cases**: customer.slug e PK varchar — Eloquent precisa de `protected $primaryKey = 'slug'; public $incrementing = false; protected $keyType = 'string';`. webhook_secret_history sem soft delete (auditavel; rotacao mantem historico). audit_logs sem `updated_at` nem soft delete (append-only por design).
- **Anti-patterns**: NAO usar `bigIncrements()` para nenhum PK — projeto inteiro e UUID. NAO permitir cascade delete em `customers.cluster_server_id` (proibir deletar cluster com customers ativos via FK `restrict`). NAO usar funcoes UUID do Postgres (`uuid_generate_v4()`, `gen_random_uuid()`) — usar `(UUID())` nativo MariaDB.
- **Validacoes**: cada migration tem `down()` com drop na ordem inversa. PKs e FKs declaradas. Indices listados no DBML mapeados via `$table->index([...], 'idx_name')`.
- **Cenarios de teste**:
    1. `php artisan migrate` em DB limpo aplica as 9 migrations sem erro
    2. `php artisan migrate:rollback` reverte na ordem inversa sem erro
    3. Models carregam relacionamentos definidos no DBML (`Customer::find($slug)->clusterServer` retorna instancia)
    4. Insert direto no DB com FK invalida lanca QueryException
- **Budget**: 4 testes (2 schema + 2 model integration)
- **References**: `~/.cursor/skills/laravel-migration/SKILL.md`, `~/.cursor/skills/laravel-migration/references/migration-patterns.md` (se existir)
- **Criterio de aceite**:
    - `php artisan migrate:fresh --seed` em DB limpo aplica todas as 9 migrations + seeder admin
    - `\DB::table('customers')->count()` retorna 0 (esperado em DB recem-criado)
    - `\App\Models\ClusterServer::create([...])` com `webhook_secret_encrypted` retorna valor encriptado em DB e descriptografado via cast
- **executor_prompt**: |
  Criar 9 migrations Laravel em `database/migrations/` traduzindo `docs/db-schema.dbml` 1:1.

        [MariaDB 11 — ver E10] Ordem (timestamps incrementais 000001-000009):
        1. `enable_uuid_extension`: no-op para MariaDB (sem extensao necessaria; UUID nativo via `UUID()`)
        2. `create_operators_table`: PK uuid (default `(UUID())`), email unique, role (default 'operador'), password_hash, status (default 'active'), last_login_at nullable, timestamps + softDeletes. Indices: email, role.
        3. `create_cluster_servers_table`: PK uuid (default `(UUID())`), name, ssh_host, ssh_port (int default 22), ssh_user (default 'ncsaas-api'), ssh_private_key_encrypted (text), webhook_secret_encrypted (text), webhook_secret_version (int default 1), nextcloud_version (nullable), schema_version (int default 1), status (default 'active'), last_health_at nullable, timestamps + softDeletes. Indice: status.
        4. `create_customers_table`: PK varchar(64) `slug`, FK uuid `cluster_server_id` ref cluster_servers.id (onDelete: 'restrict'), domain, status (default 'provisioning'), branding_meta (json nullable), last_sync_at nullable, timestamps + softDeletes. Indices: cluster_server_id, status.
        5. `create_jobs_table`: PK uuid `job_id` (default `(UUID())`), FK varchar `customer_slug` ref customers.slug, FK uuid `cluster_server_id` ref cluster_servers.id, cmd_canonical, job_type, state (default 'queued'), idempotency_key uuid unique, payload_sanitized json, summary json, exit_code int nullable, queued_at/started_at/finished_at/callback_received_at/last_poll_at todos nullable, timestamps. Indices: customer_slug, cluster_server_id, state, job_type.
        6. `create_audit_logs_table`: PK uuid (default `(UUID())`), FK uuid actor_id ref operators.id, action, resource_type, resource_id, payload json, FK uuid cluster_server_id (nullable), FK uuid job_id (nullable), ip varchar(45), user_agent text, created_at SOMENTE (sem updated_at, sem softDeletes — append-only). Indices: actor_id, action, resource_type, cluster_server_id, job_id, created_at.
        7. `create_webhook_secret_history_table`: PK uuid (default `(UUID())`), FK uuid cluster_server_id, secret_encrypted text, version int, valid_from timestamp, valid_until timestamp nullable, timestamps. Indice: cluster_server_id.
        8. `create_idempotency_keys_table`: PK uuid `key`, cmd, args_hash, customer_slug nullable FK ref customers.slug, job_id uuid nullable FK ref jobs.job_id, expires_at timestamp, timestamps. Indices: customer_slug, job_id, expires_at.
        9. `create_api_keys_table`: PK uuid (default `(UUID())`), name, token_hash unique, scopes json nullable, last_used_at nullable, revoked_at nullable, timestamps. Indice: token_hash.

        Para CADA migration, implementar `down()` com `Schema::dropIfExists($tableName)`.

        Em `app/Models/`, criar 8 Models. Para cada Model:
        - Trait `SoftDeletes` quando a tabela tem `deleted_at`
        - `protected $fillable` com colunas editaveis
        - `protected $casts` com:
          - `branding_meta`, `payload_sanitized`, `summary`, `payload`, `scopes` → `'array'`
          - `ssh_private_key_encrypted`, `webhook_secret_encrypted`, `secret_encrypted` → `'encrypted'`
          - timestamps default sao 'datetime' — nao precisa adicionar
        - Para `Customer`: `protected $primaryKey = 'slug'; public $incrementing = false; protected $keyType = 'string';`
        - Para `Job`: `protected $primaryKey = 'job_id';` + UUID nao-incrementando
        - Para `IdempotencyKey`: `protected $primaryKey = 'key';` + UUID nao-incrementando
        - Para `Operator`: extends `Authenticatable` (uses `Notifiable`). Adicionar `getAuthPasswordName(): string { return 'password_hash'; }` (sobrescrever o default 'password').
        - Relationships: `Customer::clusterServer()` → belongsTo, `ClusterServer::customers()` → hasMany, `Customer::jobs()` → hasMany via `customer_slug`/`slug`, `Job::customer()`/`Job::clusterServer()`, `AuditLog::actor()` → belongsTo Operator, `WebhookSecretHistory::clusterServer()` → belongsTo.
        - `AuditLog`: setar `public $timestamps = false; protected $dates = ['created_at'];` e usar `created_at` automatico via `static::creating()` se `created_at` nao foi setado.

        NAO criar nenhum scope ou query method nesta task — Models devem ser estrutura pura. Logica vem em sprints seguintes. NAO esquecer `declare(strict_types=1);` no topo.

    </details>

---

## Sprint D2 — Core: SshClient + Tradutores

> Categoria: D
> Gate: SshClient executa comando contra um stub de SSH server e retorna stdout/stderr/exit*code parseados; JobTypeTranslator cobre os 15 verbs do REQUIREMENTS §3 + StateTranslator cobre os 5 estados (queued/running/success/failed/cancelled); slug com `*` ou maiusculas e rejeitado com 422 antes de qualquer chamada SSH
> review: senior+qa

| Status | Tamanho | Tarefa                                                                                                                                   | Skill/Command     | Depende de                   |
| ------ | ------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ----------------- | ---------------------------- |
| [x]    | M       | 2.1 — Implementar `SshClient` com pool + timeouts + retry exponencial + suporte a `--payload-stdin` e SCP staging                        | `laravel-api`     | 1.4 (Models) `critica: true` |
| [x]    | M       | 2.2 — `JobTypeTranslator` (15 verbs cmd ↔ job_type) com testes unitarios full coverage                                                   | `laravel-api`     | 1.4                          |
| [x]    | M       | 2.3 — `StateTranslator` (5 estados upstream → canonical) com testes unitarios                                                            | `laravel-api`     | 1.4                          |
| [x]    | P       | 2.4 — `Rules\Slug` Form Request rule (`^[a-z0-9-]+$`, max 64) + Form Request `ProvisionCustomerRequest` reutilizando                     | `laravel-api`     | —                            |
| [x]    | P       | 2.5 — Testes Feature do Core: SshClient mockado contra fixtures JSON; tradutores full coverage; Slug rule com 8 inputs validos/invalidos | `laravel-testing` | 2.1, 2.2, 2.3, 2.4           |

**Notas tecnicas (tarefas M):**

<details>
<summary>2.1 — Implementar SshClient com pool + timeouts + retry + payload-stdin + SCP staging</summary>

#### Mini Design Doc

**Escopo**: classe `App\Modules\Core\Ssh\SshClient` — unica porta de saida SSH do sistema. Suporta execucao sincrona (timeout 60s) e batch (executa `manage.sh ... --async` retornando job_id em <2s). Inclui transferencia SCP para anexos > 256KB. NAO inclui parsing de comandos OCC especificos (vai em D6/D7).

**Componentes envolvidos**:

- `SshClient`: porta unica de SSH para upstream — recebe `ClusterServer` model + comando + opcoes
- `SshConnectionPool`: gerencia conexoes reutilizaveis por `cluster_server_id` (TTL 5min)
- `SshClientException`: hierarquia (TimeoutException, ConnectionException, RemoteException com exit_code)

**Fluxo de dados**:

```
Caller (ProvisionCustomerService)
  → SshClient->run($clusterServer, $cmd, $args, $payloadStdin)
    → SshConnectionPool->get($clusterServer) [cria/reusa conexao]
      → phpseclib3 SSH2 + SFTP
        → upstream ncsaas-api@host: bash manage.sh ...
          → parsedResponse {stdout, stderr, exit_code, parsed_json | null}
```

**Decisoes de design**:

1. Lib SSH: `phpseclib3` — suporta key auth via memoria (SSH key vem do `webhook_secret_encrypted` cast `decrypt`ado), nao precisa escrever .pem em disco. Alternativa descartada: `Symfony\Process` chamando `ssh` binary (requer chave em disco).
2. Pool de conexoes com TTL 5min — porque cada open SSH custa ~200ms; sprints D5/D6 fazem rajadas.
3. Retry exponencial (3 tentativas: 1s, 2s, 4s) APENAS para `ConnectionException` (timeout/connection refused). NAO para `RemoteException` (exit_code != 0 e erro de negocio do upstream).
4. `--payload-stdin` via SSH stdin pipe (phpseclib3 `SSH2::write()`); NUNCA por argv.

**Riscos**:

1. Vazamento de chave SSH em logs do PHP → Mitigacao: `SshClient` mascara qualquer string que comece com `-----BEGIN` em logs. Custom log channel sshclient com formatter dedicado.
2. SSH host key changes (man-in-the-middle) → Mitigacao: validar host key fingerprint armazenado em `cluster_servers.ssh_host_fingerprint` (ADICIONAR coluna em sprint futura — issue: cluster_servers nao tem essa coluna no DBML atual; criar finding HIGH no Audit log se primeira conexao detectar).
3. Pool nao libera conexoes em crash → Mitigacao: `register_shutdown_function` fecha todas as conexoes; tambem cap maximo de 5 conexoes simultaneas por cluster.

**Plano de rollback**: feature-flag `services.ssh.driver=phpseclib3` (default). Se quebrar, fallback `services.ssh.driver=symfony_process` (binario ssh do container php-fpm) — nao implementar agora, so deixar abstracao via interface `SshClientInterface`.

- **Arquivo(s)**:
    - `app/Modules/Core/Ssh/SshClientInterface.php`
    - `app/Modules/Core/Ssh/SshClient.php`
    - `app/Modules/Core/Ssh/SshConnectionPool.php`
    - `app/Modules/Core/Ssh/Exceptions/{SshClientException,SshTimeoutException,SshConnectionException,SshRemoteException}.php`
    - `app/Modules/Core/Ssh/Dto/SshResponse.php`
    - `config/services.php` (adicionar bloco `ssh`)
    - `app/Providers/AppServiceProvider.php` (bind interface)
- **Abordagem**: classe `SshClient` injetavel via interface. Metodo principal `run(ClusterServer $cluster, string $cmd, array $args = [], ?string $payloadStdin = null, int $timeoutSec = 60): SshResponse`. Retorna `SshResponse {stdout, stderr, exitCode, parsedJson?}`. Suporte a `runAsync()` que executa `manage.sh ... --async --json` e retorna `parsedJson` com `job_id`. Metodo `scpUpload(ClusterServer $cluster, string $localPath, string $remotePath): void` para staging anexos > 256KB.
- **Decisoes**: phpseclib3 (composer `require phpseclib/phpseclib:^3.0`); pool TTL 5min via static map indexado por `cluster_server_id`; retry 3x (backoff 1s/2s/4s) somente em ConnectionException; chave SSH lida do `cluster_server->ssh_private_key_encrypted` (cast decrypta em memoria).
- **Edge cases**: timeout do upstream (`exit 124`) → SshTimeoutException; exit 2 (queue_unavailable) → SshRemoteException com flag `retryable=true`; exit 3 (idempotency_conflict) → SshRemoteException com flag `idempotency_conflict=true`; exit 4 (state_conflict) → SshRemoteException com flag `state_conflict=true`; conexao SSH cai durante stdin → ConnectionException + retry. JSON parse falhou → SshRemoteException com stdout bruto + exit_code.
- **Anti-patterns**: NAO escrever chave SSH em disco — phpseclib3 aceita string em memoria via `RSA::loadPrivateKey($pemString)`. NAO usar `escapeshellarg` em payload sensivel (sempre stdin). NAO logar `$payloadStdin` (pode conter senha de user Nextcloud). NAO repetir retry em `RemoteException` (exit_code != 0 ja eh resposta valida do upstream).
- **Validacoes**: cluster_server.status != 'active' → `SshConnectionException('cluster unreachable')` antes de tentar; comando obrigatorio (cmd nao-vazio); timeout entre 1-300s; payloadStdin max 256KB (acima disso usar SCP staging — nao expor em `run()`).
- **Cenarios de teste**:
    1. SshClient executa comando contra fixture (mock phpseclib SSH2) e retorna stdout/stderr/exit parseados
    2. Timeout do upstream lanca SshTimeoutException com message clara
    3. exit 3 (idempotency_conflict) lanca SshRemoteException com flag `idempotency_conflict=true` + sem retry
    4. Conexao falha na primeira tentativa, sucesso na segunda — pool registra reuso de conexao
    5. payloadStdin de 100KB e enviado via stdin sem aparecer em logs
    6. SCP upload de arquivo 800KB em /tmp para `/opt/nextcloud-customers/inbox/<staging-id>/file.png` retorna void; falha de permissao lanca SshRemoteException
- **Budget**: 8 testes (Service Layer com integracao externa + edge cases criticos)
- **References**: `~/.cursor/skills/laravel-api/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md` (skill local do projeto)
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md` (decidir Service vs Action para SSH integration), `~/.cursor/skills/capabilities/service-composition/references/orchestration-patterns.md` (multi-step com retry)
- **Seguranca**: chave SSH NUNCA em logs; payloadStdin NUNCA em logs (mascara `password`, `token`, `secret` em SshResponse antes de logar); `SshConnectionException` em cluster reportado como `unreachable` apos 3 falhas consecutivas (registrar no Audit log).
- **Criterio de aceite**:
    - `SshClient::run($cluster, 'echo hello')` retorna `SshResponse(stdout='hello\n', exitCode=0)` em ambiente de teste com SSH server stubado
    - `SshClient::runAsync($cluster, 'manage.sh slug create', [...])` retorna `SshResponse` com `parsedJson['job_id']` UUID v4 em <2s
    - Todos os logs do channel sshclient mascaram strings que casem `/-----BEGIN.*?KEY-----/`
- **executor_prompt**: | ### Quality Brief (Sprint D2)
  Esta task e MARCADA `critica: true` (Best-of-N). 2 implementadores rodam em paralelo; selecionar melhor resultado.

        Criar `App\Modules\Core\Ssh\SshClient` em `app/Modules/Core/Ssh/SshClient.php` que implementa `SshClientInterface`.

        Composer requer: `composer require phpseclib/phpseclib:^3.0`

        Estrutura:
        ```
        app/Modules/Core/Ssh/
          SshClientInterface.php       # contract: run, runAsync, scpUpload
          SshClient.php                 # implementacao com phpseclib3
          SshConnectionPool.php         # static map cluster_id => ['ssh' => SSH2, 'expires_at' => Carbon]
          Dto/SshResponse.php           # readonly class: stdout, stderr, exitCode, parsedJson?
          Exceptions/SshClientException.php
          Exceptions/SshTimeoutException.php       # extends SshClientException
          Exceptions/SshConnectionException.php    # extends SshClientException
          Exceptions/SshRemoteException.php        # extends SshClientException; props: exitCode, retryable, idempotencyConflict, stateConflict
        ```

        SshClientInterface (contrato exato):
        ```php
        public function run(ClusterServer $cluster, string $cmd, array $args = [], ?string $payloadStdin = null, int $timeoutSec = 60): SshResponse;
        public function runAsync(ClusterServer $cluster, string $cmd, array $args = [], ?string $payloadStdin = null): SshResponse;
        public function scpUpload(ClusterServer $cluster, string $localPath, string $remotePath): void;
        ```

        Implementar SshClient::run:
        1. Validar cluster_server.status == 'active'; senao SshConnectionException
        2. Pegar SSH2 do pool (`$pool->get($cluster)`); pool cria nova conexao se nao existe ou TTL expirou
        3. Build comando: `escapeshellarg($cmd) . ' ' . implode(' ', array_map('escapeshellarg', $args))` — JAMAIS interpolar $payloadStdin em comando
        4. Retry loop (3x) com backoff exponencial 1s, 2s, 4s — APENAS se SSH2->exec() lancar exception de conexao (mensagens contendo 'Connection', 'timeout', 'refused')
        5. Se $payloadStdin nao-null, enviar via SSH2::write() apos exec
        6. Capturar stdout, stderr (via SSH2::getStdError()), exitStatus
        7. Tentar json_decode(stdout) — se valid JSON, popular SshResponse->parsedJson
        8. Se exitCode != 0, lancar SshRemoteException com flags conforme exit code:
           - exit 2 → retryable=true (queue_unavailable)
           - exit 3 → idempotencyConflict=true
           - exit 4 → stateConflict=true
           - exit 124 → SshTimeoutException
           - else → SshRemoteException padrao
        9. Logar via channel 'sshclient' (custom processor que mascara `/-----BEGIN.*?-----END.*?KEY-----/s` e fields `password|token|secret`)

        SshConnectionPool::get(ClusterServer $cluster):
        1. Map estatico: `static array $pool = [];` indexado por $cluster->id
        2. Se existe e $pool[$id]['expires_at']->isFuture() → reusar SSH2
        3. Senao: criar new \phpseclib3\Net\SSH2($cluster->ssh_host, $cluster->ssh_port, 30); login com `\phpseclib3\Crypt\PublicKeyLoader::load($cluster->ssh_private_key_encrypted)`; pool TTL 5min
        4. register_shutdown_function fecha todas via SSH2->disconnect()

        runAsync: mesmo que run mas adiciona ao $args: `--async --json --idempotency-key=` e exige $args ter 'idempotency-key'. Timeout 5s (async retorna em <2s).

        scpUpload: phpseclib3 SFTP->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE); validar tamanho local <= 50MB antes (cap pratico).

        config/services.php adicionar:
        ```php
        'ssh' => [
            'driver' => env('SSH_DRIVER', 'phpseclib3'),
            'pool_ttl_seconds' => 300,
            'max_pool_size' => 5,
            'connect_timeout_seconds' => 30,
        ],
        ```

        AppServiceProvider::register: $this->app->bind(SshClientInterface::class, SshClient::class);

        Logging: criar config/logging.php channel 'sshclient' com processor custom em `app/Logging/SshSecretsMasker.php` que aplica regex masking antes do formatter.

        NAO escrever chave SSH em disco. NAO logar $payloadStdin. NAO retry em SshRemoteException. NAO usar `Symfony\Process` ou `shell_exec`.

        Testes (Pest, em tests/Feature/Core/SshClientTest.php) usar mock de phpseclib3 SSH2 via reflection ou Mockery. Nao precisa de servidor SSH real — mocks bastam.

    </details>

<details>
<summary>2.2 — JobTypeTranslator (15 verbs cmd ↔ job_type)</summary>

#### Mini Design Doc (minimo)

**Escopo**: tradutor bidirecional entre `cmd_canonical` (verbo da CLI manage.sh) e `job_type` (vocabulario interno API). 15 verbs definidos em REQUIREMENTS.md §3 + ARCHITECTURE.md secao 10.Core.
**Componentes**: classe `JobTypeTranslator` injetavel; mapping array constante.
**Riscos**: novo verbo upstream nao mapeado → Mitigacao: `translate()` lanca `UnknownVerbException` com mensagem clara + path para atualizar mapping.

- **Arquivo(s)**:
    - `app/Modules/Core/Translators/JobTypeTranslator.php`
    - `app/Modules/Core/Translators/Exceptions/UnknownVerbException.php`
- **Abordagem**: array constante `private const MAP = ['create' => 'provision', 'remove' => 'deprovision', 'occ-exec' => 'occ_passthrough', ...]`. Metodos `cmdToJobType(string $cmd): string` e `jobTypeToCmd(string $jobType): string`. Validacao: `array_key_exists`, senao throw.
- **Decisoes**: classe stateless (constante de classe, nao DB) — performance + simplicidade. Nova entrada exige PR.
- **Edge cases**: cmd unknown → UnknownVerbException; jobType unknown → UnknownVerbException. Case-sensitive (cmd e job_type sao lowercase).
- **Anti-patterns**: NAO armazenar mapping em config (mudanca de mapping e mudanca de codigo, nao de config). NAO usar `?? null` (preferir exception explicita).
- **Validacoes**: cmd nao-vazio; jobType nao-vazio.
- **Cenarios de teste**:
    1. `cmdToJobType('create')` retorna `'provision'`
    2. `jobTypeToCmd('provision')` retorna `'create'`
    3. `cmdToJobType('unknown')` lanca UnknownVerbException com mensagem citando 'unknown'
    4. Roundtrip para os 15 verbs: `cmdToJobType(jobTypeToCmd($x)) === $x` para todo $x da lista
- **Budget**: 5 testes (3 happy + 2 edge)
- **References**: `~/.cursor/skills/vocabulary-translator/SKILL.md` (skill local — padroes de traducao bidirecional)
- **Criterio de aceite**:
    - 15 verbs do REQUIREMENTS §3 mapeados em ambas direcoes
    - Roundtrip estavel para todos os 15
    - Verbo desconhecido lanca UnknownVerbException
- **executor_prompt**: | ### Quality Brief (Sprint D2)

        Criar `App\Modules\Core\Translators\JobTypeTranslator` em `app/Modules/Core/Translators/JobTypeTranslator.php`.

        Lista de 15 verbs (extraida de REQUIREMENTS.md §3 e ARCHITECTURE.md sec 10.Core; confirmar contra CONTRACTS.md do upstream `../nextcloud-saas-manager/docs`):

        cmd → job_type:
        - create → provision
        - remove → deprovision
        - backup → backup
        - restore → restore
        - update → update
        - stop → stop
        - start → start
        - users:create → user_create
        - users:delete → user_delete
        - groups:create → group_create
        - groups:delete → group_delete
        - groups:add → group_add_user
        - groups:remove → group_remove_user
        - apps:enable → apps_enable
        - apps:disable → apps_disable

        Estrutura:
        ```php
        final class JobTypeTranslator {
            private const CMD_TO_JOB_TYPE = [/* ... */];
            private const JOB_TYPE_TO_CMD = [/* flip */];

            public function cmdToJobType(string $cmd): string {
                return self::CMD_TO_JOB_TYPE[$cmd] ?? throw new UnknownVerbException("Unknown cmd: {$cmd}");
            }

            public function jobTypeToCmd(string $jobType): string {
                return self::JOB_TYPE_TO_CMD[$jobType] ?? throw new UnknownVerbException("Unknown job_type: {$jobType}");
            }
        }
        ```

        Calcular `JOB_TYPE_TO_CMD` em const usando `array_flip(self::CMD_TO_JOB_TYPE)` — NAO em runtime (use uma constante derivada via metodo estatico ou manualmente).

        Adicionar binding como singleton no AppServiceProvider:
        ```php
        $this->app->singleton(JobTypeTranslator::class);
        ```

        Testes em `tests/Unit/Core/JobTypeTranslatorTest.php` cobrem todos os 5 cenarios listados.

        NAO armazenar mapping em config. NAO usar trait. NAO permitir mutacao da map.

    </details>

<details>
<summary>2.3 — StateTranslator (5 estados upstream → canonical)</summary>

#### Mini Design Doc (minimo)

**Escopo**: tradutor de `state` recebido do upstream (`pending|running|done|error|aborted`) para canonical da API (`queued|running|success|failed|cancelled`). Unidirectional (upstream → canonical) — webhook payload e SSH polling sempre falam o vocabulario upstream.
**Componentes**: `StateTranslator` injetavel; const map.
**Riscos**: upstream introduz novo state → Mitigacao: throw `UnknownStateException` (nao silenciar com 'unknown' que poluiria o DB).

- **Arquivo(s)**:
    - `app/Modules/Core/Translators/StateTranslator.php`
    - `app/Modules/Core/Translators/Exceptions/UnknownStateException.php`
- **Abordagem**: const map upstream → canonical. Metodo `toCanonical(string $upstreamState): string`. Sem direcao reversa (canonical e o que persistimos no DB; upstream nunca recebe).
- **Decisoes**: states canonical alinhados com `jobs.state` ENUM do DBML (`queued|running|success|failed|cancelled`).
- **Edge cases**: state desconhecido → exception; case-insensitive (normalizar para lowercase antes de lookup).
- **Anti-patterns**: NAO retornar 'unknown' como fallback. NAO criar versao reversa.
- **Validacoes**: input nao-vazio.
- **Cenarios de teste**:
    1. `toCanonical('pending')` → `'queued'`
    2. `toCanonical('done')` → `'success'`
    3. `toCanonical('aborted')` → `'cancelled'`
    4. `toCanonical('UNKNOWN')` lanca UnknownStateException
    5. `toCanonical('DONE')` (uppercase) → `'success'` (case-insensitive)
- **Budget**: 5 testes
- **References**: `~/.cursor/skills/vocabulary-translator/SKILL.md`
- **Criterio de aceite**:
    - 5 estados upstream mapeados para canonical
    - Estado desconhecido lanca exception
    - Case-insensitive
- **executor_prompt**: | ### Quality Brief (Sprint D2)

        Criar `App\Modules\Core\Translators\StateTranslator` em `app/Modules/Core/Translators/StateTranslator.php`.

        Mapping upstream → canonical:
        - pending → queued
        - running → running
        - done → success
        - error → failed
        - aborted → cancelled

        Estrutura:
        ```php
        final class StateTranslator {
            private const MAP = [
                'pending' => 'queued',
                'running' => 'running',
                'done' => 'success',
                'error' => 'failed',
                'aborted' => 'cancelled',
            ];

            public function toCanonical(string $upstreamState): string {
                $key = strtolower(trim($upstreamState));
                return self::MAP[$key] ?? throw new UnknownStateException("Unknown upstream state: {$upstreamState}");
            }
        }
        ```

        AppServiceProvider singleton bind. Testes Unit `tests/Unit/Core/StateTranslatorTest.php` cobrem 5 cenarios.

        NAO permitir versao reversa (canonical → upstream) — esta API nao envia state ao upstream, so consome.

    </details>

---

## Sprint D3 — Auth: Login + Cadastro de Operadores (F1)

> Categoria: D
> Gate: admin (criado via seed) loga via Livewire `/login`; admin cadastra novo operador → email com link de convite → operador define senha → loga; suporte autenticado tenta acessar rota `/customers/create` e recebe 403; tentar login 5x com senha errada bloqueia o IP por 15min
> review: senior+qa

| Status | Tamanho | Tarefa                                                                                                                                 | Skill/Command      | Depende de         |
| ------ | ------- | -------------------------------------------------------------------------------------------------------------------------------------- | ------------------ | ------------------ |
| [x]    | M       | 3.1 — Componente Livewire `Auth\Login` (form + sessao + rate limit + lockout 5/15min)                                                  | `laravel-livewire` | 1.4                |
| [x]    | M       | 3.2 — Componente Livewire `Operators\Index` + `Operators\Create` (admin only) + envio de email convite com signed URL expirando em 48h | `laravel-livewire` | 1.4                |
| [x]    | P       | 3.3 — Middleware `EnsureRole` (admin/operador/suporte) + Gate `manage-operators` em `AuthServiceProvider`                              | `laravel-livewire` | 3.1                |
| [x]    | P       | 3.4 — Componente Livewire `Auth\AcceptInvite` (recebe signed URL, valida nao-expirado, define senha) + Logout                          | `laravel-livewire` | 3.2                |
| [x]    | P       | 3.5 — Testes Feature: login valido/invalido, lockout 5/15min, role enforcement, convite valido/expirado, logout encerra sessao         | `laravel-testing`  | 3.1, 3.2, 3.3, 3.4 |

**Notas tecnicas (tarefas M):**

<details>
<summary>3.1 — Componente Livewire Auth\Login com rate limit</summary>

#### Mini Design Doc (minimo)

**Escopo**: tela de login com email + senha, rate limit por IP (5 tentativas / 15min), lockout temporario, redirect role-aware. NAO inclui 2FA, OAuth nem self-registration (admin convida).
**Componentes**: Livewire `App\Http\Livewire\Auth\Login` + view Blade. Usa `Auth::attempt` + Laravel `RateLimiter`.
**Riscos**: timing attack revela emails validos → Mitigacao: mensagem generica "Credenciais invalidas" + delay artificial 200ms quando email nao existe (alinha com tempo de bcrypt verify).

- **Arquivo(s)**:
    - `app/Http/Livewire/Auth/Login.php`
    - `resources/views/livewire/auth/login.blade.php`
    - `routes/web.php` (rotas /login, /logout, ja em middleware guest/auth)
    - `app/Providers/RouteServiceProvider.php` (HOME constant)
- **Abordagem**: Livewire 3 component com props `$email, $password, $remember`. Method `login()`: rate limit check (`RateLimiter::tooManyAttempts("login:{$ip}", 5)`), `Auth::attempt(['email' => ..., 'password' => ...], $remember)`, redirect role-aware (admin → /admin/dashboard, operador → /customers, suporte → /customers).
- **Decisoes**: usar campo `password_hash` no Operator (nao 'password' default) — sobrescrever `getAuthPasswordName(): string` no Model. Sessao expira apos 8h (`config/session.php` lifetime=480). Rate limit chave `login:{ip}` com decay 15min.
- **Edge cases**: email nao existe → mensagem generica; senha errada → mensagem generica + RateLimiter::hit; email inativo (`status=inactive`) → mensagem "conta desativada"; rate limit excedido → ValidationException com `seconds=` calculado; CSRF protegido pelo Livewire automaticamente.
- **Anti-patterns**: NAO informar "email nao encontrado" vs "senha incorreta" (timing/info leak). NAO armazenar senha em propriedade Livewire que nao seja `wire:model.live`. NAO logar senha mesmo em erro.
- **Validacoes**: email obrigatorio + format email; password obrigatorio min 12 chars (na criacao; aqui no login so checa nao-vazio).
- **Cenarios de teste**:
    1. Email + senha validos com role=admin → redireciona para `/admin/dashboard`
    2. Email + senha validos com role=suporte → redireciona para `/customers`
    3. Senha errada 5x em 15min → tentativa 6 lanca ValidationException com mensagem "Muitas tentativas. Tente em X segundos"
    4. Operator com `status=inactive` → mensagem "Conta desativada"
    5. Logout encerra sessao + invalida CSRF token
- **Budget**: 8 testes Feature (incluindo lockout + roles)
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/laravel-livewire/references/component-form-crud.md` (se existir)
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md`
- **Seguranca**: `password_hash` armazenado com bcrypt (default Laravel cost=12); rate limit por IP; sessao HttpOnly + Secure + SameSite=Lax; logout regenerate session ID
- **UX**: campo email autofocus; senha com toggle show/hide; loading state durante login; mensagem de erro inline (nao toast).
- **Design**: seguir `docs/design/refs/stitch/` (se existir tela login.html); usar tokens.css; tema dark default.
- **Criterio de aceite**:
    - Login com seed admin (`admin@me360.local` / senha do .env.example) funciona
    - 5 tentativas erradas no mesmo IP bloqueiam 15min com mensagem clara
    - Logout retorna para `/login` com mensagem "Sessao encerrada"
- **executor_prompt**: | ### Quality Brief (Sprint D3)

        Criar componente Livewire 3 em `app/Http/Livewire/Auth/Login.php`.

        Antes: garantir Operator Model tem `getAuthPasswordName(): string { return 'password_hash'; }` (sobrescrever default). Atualizar `config/auth.php` provider 'users' apontando para `App\Models\Operator`.

        Componente:
        ```php
        namespace App\Http\Livewire\Auth;

        use Livewire\Component;
        use Illuminate\Support\Facades\Auth;
        use Illuminate\Support\Facades\RateLimiter;
        use Illuminate\Validation\ValidationException;
        use App\Models\Operator;

        #[\Livewire\Attributes\Layout('layouts.guest')]
        class Login extends Component {
            public string $email = '';
            public string $password = '';
            public bool $remember = false;

            protected array $rules = [
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string'],
            ];

            public function login(): void {
                $this->validate();

                $key = 'login:' . request()->ip();
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    $seconds = RateLimiter::availableIn($key);
                    throw ValidationException::withMessages([
                        'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
                    ]);
                }

                $operator = Operator::where('email', $this->email)->first();
                if (! $operator || $operator->status !== 'active' || ! Auth::attempt(
                    ['email' => $this->email, 'password' => $this->password],
                    $this->remember,
                )) {
                    RateLimiter::hit($key, 900); // 15 minutos
                    throw ValidationException::withMessages([
                        'email' => 'Credenciais invalidas ou conta desativada.',
                    ]);
                }

                RateLimiter::clear($key);
                session()->regenerate();
                $operator->update(['last_login_at' => now()]);

                $this->redirect($this->redirectByRole($operator->role), navigate: true);
            }

            private function redirectByRole(string $role): string {
                return match ($role) {
                    'admin' => '/admin/dashboard',
                    default => '/customers',
                };
            }

            public function render() {
                return view('livewire.auth.login');
            }
        }
        ```

        Blade (`resources/views/livewire/auth/login.blade.php`): form com x-data minimo (toggle password visibility), campos email/password/remember, button "Entrar" com `wire:loading` state.

        Routes em `routes/web.php`:
        ```php
        Route::middleware('guest')->group(function () {
            Route::get('/login', \App\Http\Livewire\Auth\Login::class)->name('login');
        });

        Route::middleware('auth')->group(function () {
            Route::post('/logout', function () {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
                return redirect('/login')->with('status', 'Sessao encerrada');
            })->name('logout');
        });
        ```

        config/auth.php: provider users.model = `\App\Models\Operator::class`. config/session.php: lifetime = 480, expire_on_close = false, secure = env('SESSION_SECURE_COOKIE', true), http_only = true, same_site = 'lax'.

        NAO criar rota `/register` publica. NAO armazenar senha em propriedade publica que persista entre requests. NAO mostrar mensagem diferente para email inexistente vs senha errada. Sempre `RateLimiter::hit` mesmo se email nao existe (proteger contra enumeracao).

        Testes em `tests/Feature/Auth/LoginTest.php` cobrem 5 cenarios listados.

    </details>

<details>
<summary>3.2 — Operators Index/Create + email convite signed URL</summary>

#### Mini Design Doc (minimo)

**Escopo**: telas Livewire de listar/criar operadores (admin only) + envio de email com signed URL expirando em 48h. Operador clica → tela definir senha → ativa conta. NAO inclui edicao de email/role apos criacao (admin pode desativar via toggle).
**Componentes**: Livewire `Operators\Index` (listagem), `Operators\Create` (form), Mailable `OperatorInviteMail`, signed URL via `URL::temporarySignedRoute`.
**Riscos**: link de convite vazado por email comprometido → Mitigacao: signed URL valida + expira em 48h + invalidada ao primeiro uso (campo `password_hash` populado).

- **Arquivo(s)**:
    - `app/Http/Livewire/Operators/Index.php`, `Create.php`
    - `app/Mail/OperatorInviteMail.php`
    - `resources/views/livewire/operators/{index,create}.blade.php`
    - `resources/views/emails/operator-invite.blade.php`
    - `app/Providers/AuthServiceProvider.php` (Gate `manage-operators`)
- **Abordagem**: Index lista paginado com filtro por role/status; Create valida unicidade email + role enum + status active. Apos criacao, gera signed URL (`URL::temporarySignedRoute('operators.accept-invite', now()->addHours(48), ['operator' => $op->id])`) e envia email (Mailable + queue).
- **Decisoes**: signed URL via `temporarySignedRoute` (HMAC builtin Laravel + expiracao); operator criado com `password_hash = NULL` ate aceitar convite — ajustar migration para permitir password_hash NULLable, OU criar com placeholder + status 'pending'. Optar por `status=pending` + password_hash com placeholder bcrypt('00000000-0000-0000-0000-000000000000').
- **Edge cases**: email duplicado → 422 inline; admin tenta criar outro admin → permitido (sem limite); link expirado → tela explicativa + botao "Solicitar reenvio" (admin reenvia). Operator com status=pending nao consegue logar (Login.php ja checa status!='active').
- **Anti-patterns**: NAO enviar senha por email (mesmo temporaria). NAO permitir edicao de email pos-criacao. NAO usar email como PK (usa UUID — ja no DBML).
- **Validacoes**: email format + unique; name min 2 chars; role in [admin, operador, suporte].
- **Cenarios de teste**:
    1. Admin cria operator com role=operador → email enviado com signed URL valida 48h
    2. Operator clica link, define senha (min 12 chars), status passa a `active`, logs in OK
    3. Admin nao-admin (role=suporte) tenta GET /operators → 403
    4. Email duplicado → ValidationException 422
    5. Link expirado (>48h) → tela "Link expirado, solicite reenvio"
- **Budget**: 8 testes (mistura Index/Create/AcceptInvite/Mail::fake)
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`
- **UX**: tabela com badge de status (active/pending/inactive); botao "Reenviar convite" para status=pending (admin only); confirmacao "Tem certeza?" ao desativar operator.
- **Criterio de aceite**:
    - Admin via UI cria operator → recebe email com link
    - Operator via link define senha → loga
    - Suporte sem permissao recebe 403 ao acessar /operators
    - Mail::fake() captura email enviado nos testes
- **executor_prompt**: | ### Quality Brief (Sprint D3)

        1. Criar Mailable `app/Mail/OperatorInviteMail.php`:
        ```php
        class OperatorInviteMail extends Mailable implements ShouldQueue {
            use Queueable, SerializesModels;

            public function __construct(public Operator $operator, public string $signedUrl) {}

            public function build() {
                return $this->subject('Convite mework360-deployer')
                    ->view('emails.operator-invite');
            }
        }
        ```

        2. Criar componente Livewire `App\Http\Livewire\Operators\Create`:
        ```php
        class Create extends Component {
            public string $email = '', $name = '', $role = 'operador';

            protected array $rules = [
                'email' => ['required', 'email', 'unique:operators,email'],
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'role' => ['required', 'in:admin,operador,suporte'],
            ];

            public function save() {
                \Illuminate\Support\Facades\Gate::authorize('manage-operators');
                $this->validate();

                $operator = Operator::create([
                    'email' => $this->email,
                    'name' => $this->name,
                    'role' => $this->role,
                    'status' => 'pending',
                    'password_hash' => bcrypt(\Illuminate\Support\Str::random(64)),
                ]);

                $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'operators.accept-invite',
                    now()->addHours(48),
                    ['operator' => $operator->id]
                );

                \Illuminate\Support\Facades\Mail::to($operator->email)
                    ->send(new \App\Mail\OperatorInviteMail($operator, $signedUrl));

                session()->flash('status', "Convite enviado para {$operator->email}");
                return $this->redirect(route('operators.index'), navigate: true);
            }

            public function render() { return view('livewire.operators.create'); }
        }
        ```

        3. Criar `App\Http\Livewire\Operators\Index` com listagem paginada (`Operator::query()->orderBy('created_at', 'desc')->paginate(25)`), filtros por role/status, e botao "Reenviar convite" para status=pending (chama metodo que regenera signed URL e envia email novamente).

        4. AcceptInvite component: recebe parametro `operator` da rota (signed); valida `request()->hasValidSignature()`; mostra form definir senha; apos save: `password_hash = bcrypt($password); status = 'active';` + auto-login.

        5. Routes em `routes/web.php`:
        ```php
        Route::middleware(['auth'])->group(function () {
            Route::get('/operators', \App\Http\Livewire\Operators\Index::class)
                ->middleware('can:manage-operators')
                ->name('operators.index');
            Route::get('/operators/create', \App\Http\Livewire\Operators\Create::class)
                ->middleware('can:manage-operators')
                ->name('operators.create');
        });

        Route::get('/operators/{operator}/accept-invite', \App\Http\Livewire\Auth\AcceptInvite::class)
            ->middleware('signed')
            ->name('operators.accept-invite');
        ```

        6. AppServiceProvider boot: `Gate::define('manage-operators', fn (Operator $u) => $u->role === 'admin');`

        7. Adicionar campo `status='pending'` na enum (atualizar migration operators se necessario adicionar 'pending' como possivel valor).

        Testes em `tests/Feature/Operators/CreateTest.php` cobrem 5 cenarios listados (`Mail::fake`, `Gate::any`, etc.).

        NAO armazenar senha plaintext em qualquer lugar. NAO enviar email com `password_hash` no body.

    </details>

---

## Sprint D4 — ClusterServers (F9) + Audit (F7 base)

> Categoria: D
> Gate: admin (role=admin) cria `cluster_server` via UI com SSH key + webhook secret encriptados; rotate webhook secret aceita ambos por 24h (versao N e N+1); audit log registra criacao/edicao/rotate via observer; tentar criar como operador comum retorna 403
> review: senior+qa

| Status | Tamanho | Tarefa                                                                                                                                           | Skill/Command      | Depende de         |
| ------ | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------ | ------------------ | ------------------------------------------------------------ | ------------------ | -------- |
| [x]    | M       | 4.1 — Module ClusterServers: CRUD via Livewire (admin only) — Index + Create + Edit com encrypted casts ssh_private_key + webhook_secret         | `laravel-livewire` | 1.4, 3.3           |
| [x]    | M       | 4.2 — Test connection (`SshClient::run($cluster, 'echo ok')`) com captura de stdout/stderr/exit_code + UI status badge                           | `laravel-livewire` | 4.1, 2.1           |
| [x]    | M       | 4.3 — Rotate webhook secret com grace period 24h (insere registro em `webhook_secret_history`, `valid_until = now()->addDay()` na versao antiga) | `laravel-livewire` | 4.1, 1.4           |
| [x]    | P       | 4.4 — Cron `php artisan cluster:health-check` (Schedule a cada 5min) atualiza `cluster_servers.status` + `last_health_at`                        | `laravel-api`      | 4.2                |
| [x]    | P       | 4.5 — Module Audit: `AuditLog` observer + sanitizador (mask `password                                                                            | token              | secret             | key`em payload) + Livewire`Audit\Index` paginada com filtros | `laravel-livewire` | 1.4, 3.3 |
| [x]    | P       | 4.6 — Testes Feature ClusterServers (CRUD + test conn + rotate) + Audit (sanitizacao + filtros)                                                  | `laravel-testing`  | 4.1, 4.2, 4.3, 4.5 |

**Notas tecnicas (tarefas M):**

<details>
<summary>4.1 — Module ClusterServers CRUD com encrypted casts</summary>

#### Mini Design Doc (minimo)

**Escopo**: telas Livewire CRUD de cluster_servers, admin-only. Inclui upload de SSH private key (.pem) que e encrypted via cast Eloquent. Webhook secret gerado pelo backend (random_bytes(32) base64).
**Componentes**: Livewire Index/Create/Edit; usa Form Request validators; helper para validar formato PEM.
**Riscos**: SSH key vazada em log → Mitigacao: Pint config + PHPStan rule + custom Log channel masking.

- **Arquivo(s)**:
    - `app/Http/Livewire/ClusterServers/{Index,Create,Edit}.php`
    - `app/Http/Requests/ClusterServer{Store,Update}Request.php`
    - `resources/views/livewire/cluster-servers/{index,create,edit}.blade.php`
    - `app/Modules/ClusterServers/Services/WebhookSecretGenerator.php`
- **Abordagem**: Livewire components com upload `WithFileUploads` para `ssh_private_key.pem`; valida formato PEM via regex `/-----BEGIN.*?KEY-----.+?-----END.*?KEY-----/s`; webhook_secret nao e digitado, e gerado: `base64_encode(random_bytes(32))` (256 bits).
- **Decisoes**: encrypted cast e suficiente (Laravel `Crypt`); `webhook_secret_version` inicial = 1; status default = 'active'.
- **Edge cases**: PEM mal formatado → ValidationException; ssh_host duplicado nao bloqueia (poderia ter dois cluster_servers no mesmo host com users diferentes); rotate webhook secret cria registro em `webhook_secret_history` ANTES de mudar `cluster_servers.webhook_secret_encrypted` (para nao perder grace period).
- **Anti-patterns**: NAO permitir digitar webhook_secret (sempre gerado server-side). NAO armazenar PEM em filesystem. NAO logar evento que contenha `ssh_private_key` (observer Audit ja sanitiza, mas duplo cinto via custom log channel).
- **Validacoes**: name min 3 / max 255, ssh_host valid IP ou hostname, ssh_port 1-65535, ssh_user max 100, ssh_private_key PEM format.
- **Cenarios de teste**:
    1. Admin cria cluster_server com PEM valido → DB armazena encrypted + observer registra audit
    2. Operador comum (role=operador) GET /cluster-servers → 403
    3. PEM invalido (texto plano) → ValidationException 422
    4. Edit cluster_server name → audit log registra antes/depois
    5. Webhook secret e gerado server-side e nao retornado em response (apenas hash em UI)
- **Budget**: 8 testes Feature
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/filament-resource/SKILL.md` (NAO usar Filament por ADR-002, mas comparar padroes)
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md`
- **Seguranca**: encrypted cast obrigatorio em `ssh_private_key_encrypted`/`webhook_secret_encrypted`; UI mostra apenas `last 4 chars` do webhook_secret; PEM nao e mostrado apos save (so botao "substituir chave SSH").
- **Criterio de aceite**:
    - Admin cria cluster_server, recebe redirect ao Index, vê novo registro listado
    - DB armazena `ssh_private_key_encrypted` realmente encriptado (lendo via `\DB::table` mostra string encriptada)
    - PEM invalido rejeita 422 com mensagem clara
- **executor_prompt**: | ### Quality Brief (Sprint D4)

        1. Form Request `app/Http/Requests/ClusterServerStoreRequest.php`:
        ```php
        class ClusterServerStoreRequest extends FormRequest {
            public function authorize(): bool { return $this->user()?->role === 'admin'; }

            public function rules(): array {
                return [
                    'name' => ['required', 'string', 'min:3', 'max:255'],
                    'ssh_host' => ['required', 'string', 'max:255'],
                    'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
                    'ssh_user' => ['required', 'string', 'max:100'],
                    'ssh_private_key' => ['required', 'string', 'regex:/-----BEGIN.*?KEY-----[\s\S]+?-----END.*?KEY-----/'],
                ];
            }
        }
        ```

        2. Livewire `app/Http/Livewire/ClusterServers/Create.php`:
        ```php
        class Create extends Component {
            use WithFileUploads;
            public string $name = '', $ssh_host = '', $ssh_user = 'ncsaas-api';
            public int $ssh_port = 22;
            public ?TemporaryUploadedFile $ssh_private_key_file = null;

            public function save(WebhookSecretGenerator $secretGen) {
                \Gate::authorize('manage-cluster-servers');

                $pem = $this->ssh_private_key_file?->get();
                $validator = \Validator::make([
                    'name' => $this->name, 'ssh_host' => $this->ssh_host,
                    'ssh_port' => $this->ssh_port, 'ssh_user' => $this->ssh_user,
                    'ssh_private_key' => $pem,
                ], (new ClusterServerStoreRequest())->rules());
                $validator->validate();

                $cluster = ClusterServer::create([
                    'name' => $this->name, 'ssh_host' => $this->ssh_host,
                    'ssh_port' => $this->ssh_port, 'ssh_user' => $this->ssh_user,
                    'ssh_private_key_encrypted' => $pem,
                    'webhook_secret_encrypted' => $secretGen->generate(),
                    'webhook_secret_version' => 1,
                    'status' => 'active',
                ]);

                // Inserir primeira versao no historico (para grace period funcionar em rotates futuros)
                \App\Models\WebhookSecretHistory::create([
                    'cluster_server_id' => $cluster->id,
                    'secret_encrypted' => $cluster->webhook_secret_encrypted,
                    'version' => 1,
                    'valid_from' => now(),
                    'valid_until' => null, // null = atual
                ]);

                return $this->redirect(route('cluster-servers.index'), navigate: true);
            }

            public function render() { return view('livewire.cluster-servers.create'); }
        }
        ```

        3. `WebhookSecretGenerator` em `app/Modules/ClusterServers/Services/WebhookSecretGenerator.php`:
        ```php
        final class WebhookSecretGenerator {
            public function generate(): string {
                return base64_encode(random_bytes(32));
            }
        }
        ```

        4. AppServiceProvider boot: `Gate::define('manage-cluster-servers', fn (Operator $u) => $u->role === 'admin');`

        5. Em `app/Models/ClusterServer.php` confirmar casts:
        ```php
        protected $casts = [
            'ssh_private_key_encrypted' => 'encrypted',
            'webhook_secret_encrypted' => 'encrypted',
            'last_health_at' => 'datetime',
        ];
        ```

        6. Index Livewire: paginar 25, mostrar name, ssh_host, status (badge), last_health_at, webhook_secret_preview = "******" + last 4 chars do secret descriptografado, botoes "Test Connection" + "Rotate Secret" + "Edit".

        Edit nao permite trocar `ssh_private_key_encrypted` em mesma tela (botao separado "Substituir chave SSH" abre modal com upload).

        NAO retornar `ssh_private_key_encrypted` em nenhum endpoint API. NAO logar evento de save com payload completo (observer Audit em D4.5 ja sanitiza, mas custom log channel adicional via `Log::channel('cluster_servers')` mascara `ssh_private_key|webhook_secret`).

        Testes Feature `tests/Feature/ClusterServers/StoreTest.php` cobrem 5 cenarios.

    </details>

<details>
<summary>4.2 — Test connection com SshClient e UI badge</summary>

#### Mini Design Doc (minimo)

**Escopo**: botao "Test Connection" no Index/Edit do cluster_server executa `SshClient::run($cluster, 'echo healthcheck-' . $cluster->id)` e atualiza UI com resultado em tempo real.
**Componentes**: Livewire action method + UI feedback; usa Job assincrono se quisermos nao-blocking, mas para MVP roda sync com timeout 10s.
**Riscos**: cluster offline → request hang → Mitigacao: timeout 10s no SshClient + `wire:loading` + tratamento de SshConnectionException com toast.

- **Arquivo(s)**:
    - `app/Http/Livewire/ClusterServers/Index.php` (metodo `testConnection`)
    - eventualmente `app/Modules/ClusterServers/Actions/TestConnectionAction.php` (extracao se logica crescer)
- **Abordagem**: action method recebe `$clusterId`, busca cluster, chama `SshClient::run($cluster, 'echo healthcheck-' . $cluster->id, [], null, 10)`, captura stdout, valida que stdout == "healthcheck-{id}\n" → sucesso; senao registra falha. Atualiza `last_health_at` e `status` (active|unreachable). Dispatcha event Livewire para refresh badge.
- **Decisoes**: timeout curto (10s) — operador clica e espera; nao precisa de queue. Mensagem de exit_code/stderr exibida em modal "Detalhes" se falhar.
- **Edge cases**: SshConnectionException → status = unreachable, registra audit log; SshTimeoutException → status = unreachable + mensagem "Timeout"; sucesso mas exit_code != 0 → status = active mas warning "Comando executou mas retornou exit X".
- **Anti-patterns**: NAO marcar como `disabled` automaticamente (admin decide); NAO esconder erros (mostrar stderr capturado para troubleshooting).
- **Validacoes**: cluster.id pertence a um cluster_servers nao soft-deleted.
- **Cenarios de teste**:
    1. Cluster ativo + SSH stub responde echo → status = active + last_health_at atualizado
    2. SSH connection refused → status = unreachable + audit log entry
    3. SSH timeout → status = unreachable + mensagem clara
- **Budget**: 4 testes
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md`
- **Criterio de aceite**:
    - Botao "Test" trigga loading spinner; em <10s atualiza badge na UI
    - Cluster offline transita para badge "unreachable" com cor coral
- **executor_prompt**: | ### Quality Brief (Sprint D4)

        Em `app/Http/Livewire/ClusterServers/Index.php` adicionar metodo:

        ```php
        public function testConnection(string $clusterId, SshClientInterface $ssh): void {
            \Gate::authorize('manage-cluster-servers');
            $cluster = ClusterServer::findOrFail($clusterId);

            try {
                $expected = "healthcheck-{$cluster->id}";
                $resp = $ssh->run($cluster, 'echo', [$expected], null, 10);

                if (trim($resp->stdout) === $expected && $resp->exitCode === 0) {
                    $cluster->update(['status' => 'active', 'last_health_at' => now()]);
                    $this->dispatch('toast', type: 'success', msg: 'Conexao OK');
                } else {
                    $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
                    $this->dispatch('toast', type: 'warning', msg: "Comando retornou exit {$resp->exitCode}");
                }
            } catch (SshTimeoutException $e) {
                $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
                $this->dispatch('toast', type: 'error', msg: 'Timeout');
            } catch (SshConnectionException $e) {
                $cluster->update(['status' => 'unreachable', 'last_health_at' => now()]);
                $this->dispatch('toast', type: 'error', msg: 'Conexao falhou: ' . $e->getMessage());
            } catch (\Throwable $e) {
                $this->dispatch('toast', type: 'error', msg: 'Erro inesperado');
                report($e);
            }
        }
        ```

        Blade Index: cada linha tem botao `<button wire:click="testConnection('{{ $cluster->id }}')" wire:loading.attr="disabled">Test</button>`.

        Toast handler em layouts/app.blade.php via Alpine.js listener `@toast.window` mostra mensagem.

        Testes em `tests/Feature/ClusterServers/TestConnectionTest.php`. Mockar `SshClientInterface` via container binding para retornar SshResponse fake.

        NAO chamar SshClient em filas/jobs (MVP roda sync). NAO marcar como `disabled` automaticamente — apenas `unreachable`.

    </details>

<details>
<summary>4.3 — Rotate webhook secret com grace period 24h</summary>

#### Mini Design Doc (COMPLETO — dados sensiveis + compliance + lógica condicional)

**Escopo**: rotacionar webhook secret de um cluster_server. Inserir nova versao em `webhook_secret_history` (valid_from=now), atualizar versao antiga (valid_until=now+24h), atualizar `cluster_servers.webhook_secret_encrypted` para a nova. Webhook receiver (D5.1) aceita ambos os secrets durante grace period.

**Componentes envolvidos**:

- `RotateWebhookSecretAction`: action class invocavel, transacional
- `WebhookSecretHistory`: tabela append-only com versionamento
- Livewire button "Rotate Secret" no Index/Edit
- Webhook receiver (D5.1) consulta `webhook_secret_history` para encontrar secret valido

**Fluxo de dados**:

```
Admin click "Rotate"
  → Livewire confirmModal → confirm
    → RotateWebhookSecretAction::execute($cluster)
      → DB transaction:
         1. UPDATE webhook_secret_history SET valid_until = NOW() + 24h WHERE cluster_server_id = X AND valid_until IS NULL
         2. INSERT INTO webhook_secret_history (cluster_server_id, secret_encrypted, version=N+1, valid_from=NOW, valid_until=NULL)
         3. UPDATE cluster_servers SET webhook_secret_encrypted=novo, webhook_secret_version=N+1
      → AuditLog entry (acao=rotate_webhook_secret)
      → Email notif para admin
    → Toast "Secret rotacionado. Versao antiga valida ate {timestamp}"
```

**Decisoes de design**:

1. Grace period 24h fixo (configuravel via `config/services.webhook.grace_period_hours`) — porque permite reconfiguracao do upstream sem downtime.
2. UNIQUE constraint logica: por cluster_server_id, sempre exatamente UM registro com valid_until=NULL (versao corrente). Garantido pela transacao acima.
3. Limpar registros expirados (valid_until < NOW) via cron diario — nao apagar imediatamente para audit trail (manter 30 dias).

**Riscos**:

1. Race condition: dois admins clicam Rotate simultaneamente → poderia gerar duas versoes "atuais" → Mitigacao: `LOCK FOR UPDATE` no SELECT da row valid_until=NULL na transacao.
2. Upstream nao consegue receber novo secret antes do grace expirar → Mitigacao: email para admin com timestamp de expiracao + UI mostra countdown.

**Plano de rollback**: se a rotacao gera incidente (upstream nao reconfigurou), admin clica "Revert Rotation" (botao aparece enquanto grace ativo) → atualiza `cluster_servers.webhook_secret_encrypted` de volta para versao N (lendo do `webhook_secret_history`), apaga registro N+1, remove valid_until da N (volta a NULL).

**Cenarios de teste** (alimentam test-writer):

1. Happy path: admin clica Rotate → transacao commita → webhook receiver aceita ambos secrets por 24h → apos 25h apenas novo aceita
2. Race: dois requests Rotate simultaneos → so um cria nova versao (lock garante)
3. Revert dentro do grace: admin reverte → `cluster_servers.webhook_secret` volta ao N + N+1 e' apagado
4. Cron limpa registros com `valid_until < now()->subDays(30)` mas mantem o ainda em grace
5. Audit log registra acao com payload sanitizado (sem o secret real, so versoes)

- **Arquivo(s)**:
    - `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`
    - `app/Modules/ClusterServers/Actions/RevertWebhookSecretAction.php`
    - `app/Console/Commands/CleanExpiredWebhookSecretsCommand.php`
    - `app/Http/Livewire/ClusterServers/Index.php` (metodo `rotateSecret($clusterId)`)
    - `app/Mail/WebhookSecretRotatedMail.php`
- **Abordagem**: action class single-method `execute(ClusterServer $cluster): WebhookSecretHistory`, `\DB::transaction()` com `LOCK FOR UPDATE`, retorna o novo registro.
- **Decisoes**: grace_period configurable via `config/services.php`; secret gerado pelo `WebhookSecretGenerator`; email enviado pos-commit (queued).
- **Edge cases**: cluster soft-deleted → throw `ClusterServerInactiveException`; webhook_secret_history sem registro valid_until=NULL (estado invalido) → throw + alerta CRITICAL.
- **Anti-patterns**: NAO atualizar `cluster_servers.webhook_secret_encrypted` antes de inserir na history (perde rastreabilidade). NAO permitir rotate sem confirmacao.
- **Validacoes**: cluster_server existe, status != deleted, role admin.
- **Budget**: 8 testes (transacao, race, revert, cleanup, audit)
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/webhook-receiver/SKILL.md` (skill local)
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md`, `~/.cursor/skills/capabilities/service-composition/references/orchestration-patterns.md` (transacao multi-table)
- **Seguranca**: secret nunca em log; UI mostra apenas timestamp de expiracao do antigo + ultimos 4 chars do novo.
- **Criterio de aceite**:
    - Admin clica Rotate → transacao atomica → webhook receiver aceita ambos por 24h
    - Cron diario limpa registros com `valid_until < now()->subDays(30)`
    - Audit log entry tem payload `{ cluster_id, version_old, version_new, grace_until }` (sem secrets)
- **executor_prompt**: | ### Quality Brief (Sprint D4)

        1. `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`:
        ```php
        final class RotateWebhookSecretAction {
            public function __construct(
                private readonly WebhookSecretGenerator $generator,
            ) {}

            public function execute(ClusterServer $cluster): WebhookSecretHistory {
                return \DB::transaction(function () use ($cluster) {
                    $current = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
                        ->whereNull('valid_until')
                        ->lockForUpdate()
                        ->first();

                    if (! $current) {
                        throw new \RuntimeException("ClusterServer {$cluster->id} sem secret atual no historico");
                    }

                    $graceHours = config('services.webhook.grace_period_hours', 24);
                    $current->update(['valid_until' => now()->addHours($graceHours)]);

                    $newSecret = $this->generator->generate();
                    $newVersion = $current->version + 1;

                    $new = WebhookSecretHistory::create([
                        'cluster_server_id' => $cluster->id,
                        'secret_encrypted' => $newSecret,
                        'version' => $newVersion,
                        'valid_from' => now(),
                        'valid_until' => null,
                    ]);

                    $cluster->update([
                        'webhook_secret_encrypted' => $newSecret,
                        'webhook_secret_version' => $newVersion,
                    ]);

                    return $new;
                });
            }
        }
        ```

        2. Action `RevertWebhookSecretAction::execute(ClusterServer $cluster)`:
        - Validar que existe registro com `valid_until > now()` (grace ainda ativo).
        - Em transacao: pegar a `current` (valid_until=NULL) — apaga; pega a anterior (valid_until>now()) — set `valid_until=NULL`; atualiza `cluster_servers.webhook_secret_encrypted` para o secret da anterior.
        - Limpar registros `valid_until` anteriores nao afetados.

        3. `CleanExpiredWebhookSecretsCommand` (`php artisan webhook-secrets:clean`):
        ```php
        WebhookSecretHistory::whereNotNull('valid_until')
            ->where('valid_until', '<', now()->subDays(30))
            ->delete();
        ```
        Schedule diario as 03:00 em `app/Console/Kernel.php`.

        4. `app/Modules/ClusterServers/Services/WebhookSecretValidator.php` (sera usado em D5.1):
        ```php
        final class WebhookSecretValidator {
            public function valid(ClusterServer $cluster, string $signature, string $body): bool {
                $secrets = WebhookSecretHistory::where('cluster_server_id', $cluster->id)
                    ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
                    ->pluck('secret_encrypted'); // already decrypted via cast

                foreach ($secrets as $secret) {
                    $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
                    if (hash_equals($expected, $signature)) return true;
                }
                return false;
            }
        }
        ```

        Casts em WebhookSecretHistory: `secret_encrypted => 'encrypted'`, `valid_from/valid_until => 'datetime'`.

        5. Livewire Index method `rotateSecret($clusterId)`:
        ```php
        public function rotateSecret(string $clusterId, RotateWebhookSecretAction $action): void {
            \Gate::authorize('manage-cluster-servers');
            $cluster = ClusterServer::findOrFail($clusterId);
            $new = $action->execute($cluster);
            \Mail::to(auth()->user()->email)->send(new \App\Mail\WebhookSecretRotatedMail($cluster, $new));
            $this->dispatch('toast', type: 'success', msg: "Secret rotacionado. Versao antiga valida ate {$new->valid_until}");
        }
        ```

        config/services.php:
        ```php
        'webhook' => [
            'grace_period_hours' => env('WEBHOOK_GRACE_PERIOD_HOURS', 24),
        ],
        ```

        Testes em `tests/Feature/ClusterServers/RotateSecretTest.php` cobrem 5 cenarios incluindo race condition (usar `\DB::beginTransaction()` em paralelo simulado).

        NAO permitir rotate sem confirmacao (UI: modal "Tem certeza? Versao N continuara valida por 24h."). NAO logar `secret_encrypted` em qualquer lugar.

    </details>

---

## Sprint D5 — Jobs: Webhook Receiver + Listagem da Fila

> Categoria: D
> Gate: webhook com HMAC-SHA256 valido (secret atual ou em grace) atualiza `jobs.state` + `callback_received_at`; HMAC invalido retorna 401 + audit log entry CRITICAL; payload com `finished_at` > 1h atras retorna 422 (replay protection); `GET /queue?state=running` retorna paginado
> review: senior+qa

| Status | Tamanho | Tarefa                                                                                                                                       | Skill/Command     | Depende de               |
| ------ | ------- | -------------------------------------------------------------------------------------------------------------------------------------------- | ----------------- | ------------------------ |
| [x]    | M       | 5.1 — Webhook receiver `POST /api/jobs/hook` com middleware `VerifyWebhookHmac` (assinatura + IP whitelist + replay 1h + multi-secret grace) | `laravel-api`     | 4.3, 2.3 `critica: true` |
| [x]    | M       | 5.2 — Endpoint `GET /queue` + Livewire `Jobs\Index` (paginacao, filtros state/job_type/customer, deep-link)                                  | `laravel-api`     | 1.4                      |
| [x]    | P       | 5.3 — Endpoints REST `GET /queue/stats` (counts por state) + `GET /queue/{id}` (detalhes do job)                                             | `laravel-api`     | 5.2                      |
| [x]    | P       | 5.4 — Cancel job: action `CancelJobAction` chama `nextcloud-manage job <id> cancel --json` via SshClient                                     | `laravel-api`     | 5.2, 2.1                 |
| [x]    | P       | 5.5 — Testes Feature webhook (HMAC valido/invalido, replay, multi-secret) + queue endpoints + cancel                                         | `laravel-testing` | 5.1-5.4                  |

**Notas tecnicas (tarefas M):**

<details>
<summary>5.1 — Webhook receiver POST /api/jobs/hook</summary>

#### Mini Design Doc (COMPLETO — vetor de ataque #1, integracao externa nova, dados sensiveis, compliance, primeira impl)

**Escopo**: endpoint publico (sem auth Bearer/Session) que recebe callbacks do upstream `nextcloud-saas-manager`. Validacao multi-camada: (1) IP whitelist do `cluster_servers.ssh_host`; (2) header `X-Signature: sha256=<hmac>` validado com `WebhookSecretValidator` (aceita secret atual + secrets em grace); (3) replay protection: rejeitar se `payload.finished_at` for > 1h atras; (4) parsing + traducao de state via `StateTranslator`; (5) atualizacao atomica do Job + AuditLog.

**Componentes envolvidos**:

- `WebhookController::receive()`: recebe request
- `VerifyWebhookHmac` middleware: validacao HMAC + IP + replay
- `WebhookSecretValidator` (criado em D4.3)
- `StateTranslator` (criado em D2.3)
- `Job` Model + observer
- `AuditLog` para logs de seguranca

**Fluxo de dados**:

```
upstream worker
  → POST /api/jobs/hook (X-Signature, X-Cluster-Server-Id, body JSON)
    → middleware VerifyWebhookHmac:
       1. resolve cluster_server por X-Cluster-Server-Id (404 se nao existe)
       2. checa request->ip() in [cluster.ssh_host] (401 se nao bate)
       3. WebhookSecretValidator::valid (401 se HMAC invalido — testa todos secrets validos do cluster)
       4. checa payload.finished_at > now()-1h (422 se replay)
    → controller WebhookController::receive:
       1. parse payload {job_id, state, cmd, client, exit_code, finished_at}  ← [E7] upstream nao envia 'summary' no webhook; extrair em job status separado
       2. busca Job::find($payload['job_id']) (404 se nao existe)
       3. valida que job.cluster_server_id == middleware-resolved cluster_id (403 se mismatch)
       4. transacao: update Job (state=Translator::toCanonical($payload.state), callback_received_at=now, finished_at, exit_code, summary)
       5. AuditLog entry (action=webhook_received, payload sanitized)
    → 204 No Content
```

**Decisoes de design**:

1. Multi-secret support: `WebhookSecretValidator` itera todos secrets validos do cluster (atual + em grace). Permite zero-downtime durante rotacao.
2. IP whitelist baseado em `cluster_servers.ssh_host` (mesmo host SSH e que envia webhooks). Resolver DNS na requisicao? Nao — usar string match exato; se ssh_host e hostname, fazer `gethostbyname` na config + cache 5min.
3. Replay window 1h fixo (configuravel) — alinha com REQUIREMENTS §F8.
4. Endpoint NAO autenticado por Bearer/Session — apenas por HMAC. Sem rate limit de Sanctum, mas RATE LIMIT custom 100 req/min/IP via `RateLimiter`.
5. Idempotencia: se webhook chega DUAS vezes para mesmo job_id+state — segundo update e' no-op (state ja esta correto). Aceitar e logar como info.

**Riscos**:

1. **Tampering**: atacante envia webhook falso → Mitigacao: HMAC + IP whitelist (camadas).
2. **Replay**: atacante intercepta webhook antigo → Mitigacao: rejeitar finished_at > 1h.
3. **DoS**: flood de webhooks → Mitigacao: RateLimiter 100/min/IP + middleware throttle.
4. **Cluster ID guessing**: atacante advinha cluster_server_id e envia para wrong cluster → Mitigacao: HMAC com secret do cluster espessa essa camada (tem que saber secret).
5. **Timing attack no HMAC compare**: revela quanto do HMAC bate → Mitigacao: `hash_equals` (constant-time compare).

**Plano de rollback**: feature flag `services.webhook.enabled` (default true). Se webhook quebrar, polling SSH (D6.4 cron sync) atua como fallback automatico. Polling roda a cada 5min checando jobs em state=running ha mais de 60s sem callback.

**Cenarios de teste** (alimentam test-writer):

1. HMAC valido + IP whitelisted + finished_at recente → 204 + Job atualizado + AuditLog entry
2. HMAC invalido → 401 + AuditLog CRITICAL com IP origem
3. IP fora do whitelist → 401 + AuditLog HIGH
4. finished_at de 2h atras → 422 + AuditLog WARNING (replay attempt)
5. Multi-secret durante rotacao: HMAC com secret antigo (em grace) → 204 + sucesso
6. Webhook chega duas vezes para mesmo job_id+state → 204 idempotente
7. cluster_server_id no header nao bate com job.cluster_server_id → 403 (cluster trying to update foreign job)
8. payload com state desconhecido (`weirdstate`) → 422 + AuditLog WARNING

- **Arquivo(s)**:
    - `app/Http/Controllers/Api/WebhookController.php`
    - `app/Http/Middleware/VerifyWebhookHmac.php`
    - `app/Modules/Jobs/Services/WebhookHandler.php` (orquestrador)
    - `app/Modules/Jobs/Dto/WebhookPayload.php`
    - `routes/api.php` (rota /jobs/hook fora do middleware auth:sanctum)
- **Abordagem**: middleware `VerifyWebhookHmac` faz toda validacao de seguranca + popula `request->attributes->set('cluster_server', $cluster)` para o controller. Controller chama `WebhookHandler::handle($cluster, $payload)` que faz a transacao DB.
- **Decisoes**: usar `Cache::remember("webhook_ip:{cluster_id}", 300, fn () => gethostbyname($cluster->ssh_host))` para DNS resolution; `hash_equals` em compare; `now()->diffInMinutes($finished_at) <= 60` para replay; rate limit `webhook:{ip}` 100 req/min.
- **Edge cases**: payload mal formado JSON → 400; campos obrigatorios ausentes → 422; job nao existe → 404; state desconhecido → UnknownStateException → catch + 422.
- **Anti-patterns**: NAO usar `==` para compare de HMAC (timing attack). NAO logar payload completo (pode conter dados sensiveis). NAO retornar 500 com stack trace em prod. NAO usar middleware `auth:sanctum` (rota e publica protegida por HMAC).
- **Validacoes**: header X-Signature presente; X-Cluster-Server-Id UUID v4 valido; body JSON parseable; payload tem job_id, state, finished_at.
- **Budget**: 12 testes (cobertura completa de seguranca + happy paths + idempotencia)
- **References**: `~/.cursor/skills/webhook-receiver/SKILL.md` (skill local — padroes HMAC), `~/.cursor/skills/laravel-api/SKILL.md`
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md`, `~/.cursor/skills/capabilities/service-composition/references/orchestration-patterns.md` (multi-step com transacao)
- **Seguranca**: 5 camadas: IP whitelist, HMAC valido, replay window 1h, rate limit 100/min, audit log de tentativas suspeitas
- **Criterio de aceite**:
    - Webhook valido com fixture HMAC computado por chave conhecida → 204 + Job atualizado
    - Tentativa com HMAC invalido → 401 + entrada em audit_logs com `action=webhook_invalid_signature` e `payload.ip`
    - Replay com finished_at > 1h → 422
    - Multi-secret: HMAC computado com versao N (em grace) ainda passa
- **executor_prompt**: | ### Quality Brief (Sprint D5)
  Esta task e MARCADA `critica: true` (Best-of-N). 2 implementadores rodam em paralelo; selecionar melhor resultado.

        1. Middleware `app/Http/Middleware/VerifyWebhookHmac.php`:
        ```php
        use App\Models\ClusterServer;
        use App\Modules\ClusterServers\Services\WebhookSecretValidator;
        use Illuminate\Support\Facades\{Cache, Log, RateLimiter};

        class VerifyWebhookHmac {
            public function __construct(
                private WebhookSecretValidator $validator,
            ) {}

            public function handle($request, \Closure $next) {
                $clusterId = $request->header('X-Cluster-Server-Id');
                $signature = $request->header('X-Signature', '');
                $body = $request->getContent();
                $ip = $request->ip();

                // Rate limit
                $rateKey = "webhook:{$ip}";
                if (RateLimiter::tooManyAttempts($rateKey, 100)) {
                    return response()->json(['error' => 'rate_limit'], 429);
                }
                RateLimiter::hit($rateKey, 60);

                if (! $clusterId || ! \Str::isUuid($clusterId)) {
                    return response()->json(['error' => 'invalid_cluster_id'], 400);
                }

                $cluster = ClusterServer::find($clusterId);
                if (! $cluster) {
                    $this->auditFail('webhook_unknown_cluster', $ip, $clusterId);
                    return response()->json(['error' => 'unknown_cluster'], 401);
                }

                $allowedIp = Cache::remember("webhook_ip:{$cluster->id}", 300, fn () => gethostbyname($cluster->ssh_host));
                if ($ip !== $allowedIp) {
                    $this->auditFail('webhook_ip_mismatch', $ip, $cluster->id);
                    return response()->json(['error' => 'ip_not_whitelisted'], 401);
                }

                if (! $this->validator->valid($cluster, $signature, $body)) {
                    $this->auditFail('webhook_invalid_signature', $ip, $cluster->id);
                    return response()->json(['error' => 'invalid_signature'], 401);
                }

                // Replay protection
                $payload = json_decode($body, true);
                if (! $payload || ! isset($payload['finished_at'])) {
                    return response()->json(['error' => 'missing_finished_at'], 422);
                }
                $finishedAt = \Carbon\Carbon::parse($payload['finished_at']);
                if ($finishedAt->diffInMinutes(now()) > 60) {
                    $this->auditFail('webhook_replay', $ip, $cluster->id, $payload);
                    return response()->json(['error' => 'replay_window_exceeded'], 422);
                }

                $request->attributes->set('cluster_server', $cluster);
                $request->attributes->set('webhook_payload', $payload);

                return $next($request);
            }

            private function auditFail(string $action, string $ip, string $resourceId, array $payload = []): void {
                \App\Models\AuditLog::create([
                    'actor_id' => null,
                    'action' => $action,
                    'resource_type' => 'webhook',
                    'resource_id' => $resourceId,
                    'payload' => array_merge(['ip' => $ip], $payload),
                    'ip' => $ip,
                ]);
                Log::channel('security')->warning("webhook.{$action}", compact('ip', 'resourceId'));
            }
        }
        ```

        2. Controller `app/Http/Controllers/Api/WebhookController.php`:
        ```php
        class WebhookController extends Controller {
            public function receive(Request $request, WebhookHandler $handler): \Illuminate\Http\Response {
                $cluster = $request->attributes->get('cluster_server');
                $payload = $request->attributes->get('webhook_payload');

                $handler->handle($cluster, $payload);

                return response()->noContent();
            }
        }
        ```

        3. Service `app/Modules/Jobs/Services/WebhookHandler.php`:
        ```php
        class WebhookHandler {
            public function __construct(private StateTranslator $stateTranslator) {}

            public function handle(ClusterServer $cluster, array $payload): void {
                $jobId = $payload['job_id'] ?? null;
                if (! $jobId) throw new \InvalidArgumentException('job_id ausente');

                $job = \App\Models\Job::find($jobId);
                if (! $job) throw (new ModelNotFoundException())->setModel(Job::class);

                if ($job->cluster_server_id !== $cluster->id) {
                    throw new \DomainException('job pertence a outro cluster');
                }

                $canonical = $this->stateTranslator->toCanonical($payload['state']);

                \DB::transaction(function () use ($job, $canonical, $payload) {
                    $job->update([
                        'state' => $canonical,
                        'callback_received_at' => now(),
                        'finished_at' => $payload['finished_at'] ?? $job->finished_at,
                        'exit_code' => $payload['exit_code'] ?? null,
                        'summary' => $payload['summary'] ?? null,
                    ]);

                    \App\Models\AuditLog::create([
                        'actor_id' => null,
                        'action' => 'webhook_received',
                        'resource_type' => 'job',
                        'resource_id' => $job->job_id,
                        'payload' => ['state' => $canonical, 'exit_code' => $payload['exit_code'] ?? null],
                        'cluster_server_id' => $job->cluster_server_id,
                        'job_id' => $job->job_id,
                    ]);
                });
            }
        }
        ```

        4. Rota em `routes/api.php`:
        ```php
        Route::post('/jobs/hook', [\App\Http\Controllers\Api\WebhookController::class, 'receive'])
            ->middleware([\App\Http\Middleware\VerifyWebhookHmac::class])
            ->name('jobs.hook');
        ```
        Esta rota DEVE ficar fora do grupo `auth:sanctum`.

        5. config/services.php adicionar:
        ```php
        'webhook' => [
            'replay_window_minutes' => env('WEBHOOK_REPLAY_WINDOW_MIN', 60),
            'rate_limit_per_minute' => env('WEBHOOK_RATE_LIMIT', 100),
        ],
        ```

        6. Logging: adicionar channel 'security' em config/logging.php (daily, level=warning).

        Testes em `tests/Feature/Api/WebhookReceiveTest.php`:
        - Helper para gerar HMAC: `hash_hmac('sha256', $body, $secret)`
        - Cobrir os 8 cenarios de teste listados
        - Usar `RateLimiter::clear` em setUp para evitar contamination

        NAO retornar mensagem de erro descritiva em prod (so `error` key generica). NAO logar payload completo do webhook (apenas state + exit_code). SEMPRE usar `hash_equals` para compare HMAC. NAO permitir secret default '' (forcar config explicito).

    </details>

<details>
<summary>5.2 — GET /queue endpoint + Livewire Jobs\Index com filtros</summary>

#### Mini Design Doc (minimo)

**Escopo**: listagem paginada de jobs (~replica local) com filtros state/job_type/customer + deep-link via query string. NAO inclui acoes destrutivas (cancel em D5.4).
**Componentes**: API endpoint `GET /queue`; Livewire `App\Http\Livewire\Jobs\Index` (UI espelhada).
**Riscos**: tabela jobs cresce sem limite → Mitigacao: indices state/job_type/customer_slug ja no DBML; cap de 100 por pagina.

- **Arquivo(s)**:
    - `app/Http/Controllers/Api/JobController.php` (metodos `index`, `show`, `stats`)
    - `app/Http/Livewire/Jobs/Index.php`
    - `app/Http/Resources/JobResource.php`
    - `resources/views/livewire/jobs/index.blade.php`
- **Abordagem**: API endpoint usa `JobResource` + paginacao Laravel (default 25, max 100); query whereLikeAny para customer_slug; filtros via spatie/laravel-query-builder (composer require) ou builder manual com `when()`. Livewire reflete os mesmos filtros via wire:model + URL queryString.
- **Decisoes**: usar query builder manual (sem spatie) para MVP — mais explicito e testavel. Indices ja existem.
- **Edge cases**: per_page > 100 → forcar 100; state invalido (nao no enum) → ignorar filtro silenciosamente (nao 422 — UI nao deveria mandar invalido).
- **Anti-patterns**: NAO retornar campos sensiveis (`payload_sanitized` ja sanitizado pelo upstream — pode retornar). NAO carregar relations N+1 (`with(['customer', 'clusterServer'])`).
- **Validacoes**: per_page 1-100; state in enum; queue_state in enum.
- **Cenarios de teste**:
    1. GET /queue?state=running retorna 200 + paginated com apenas state=running
    2. GET /queue?per_page=200 retorna 200 + cap em 100
    3. GET /queue?customer=acme- (busca like) retorna jobs do customer
    4. Livewire Index aplica filtros + URL atualiza com queryString
- **Budget**: 6 testes
- **References**: `~/.cursor/skills/laravel-api/SKILL.md`, `~/.cursor/skills/laravel-livewire/SKILL.md`
- **Criterio de aceite**:
    - Endpoint paginado retorna shape conforme `docs/openapi.yaml /queue`
    - Filtros combinam (state=running + job_type=provision)
    - Livewire URL deep-link funciona (refresh preserva filtros)
- **executor_prompt**: | ### Quality Brief (Sprint D5)

        1. `app/Http/Controllers/Api/JobController.php`:
        ```php
        class JobController extends Controller {
            public function index(Request $request) {
                $request->validate([
                    'state' => ['nullable', 'in:queued,running,success,failed,cancelled'],
                    'job_type' => ['nullable', 'string', 'max:100'],
                    'customer' => ['nullable', 'string', 'max:64'],
                    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                ]);

                $jobs = Job::query()
                    ->with(['customer', 'clusterServer'])
                    ->when($request->state, fn ($q, $s) => $q->where('state', $s))
                    ->when($request->job_type, fn ($q, $t) => $q->where('job_type', $t))
                    ->when($request->customer, fn ($q, $c) => $q->where('customer_slug', 'like', "%{$c}%"))
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->integer('per_page', 25));

                return JobResource::collection($jobs);
            }

            public function show(string $id) {
                $job = Job::with(['customer', 'clusterServer'])->findOrFail($id);
                return new JobResource($job);
            }

            public function stats() {
                $counts = Job::query()
                    ->selectRaw('state, count(*) as count')
                    ->groupBy('state')
                    ->pluck('count', 'state');

                return response()->json([
                    'queued' => $counts['queued'] ?? 0,
                    'running' => $counts['running'] ?? 0,
                    'success' => $counts['success'] ?? 0,
                    'failed' => $counts['failed'] ?? 0,
                    'cancelled' => $counts['cancelled'] ?? 0,
                ]);
            }
        }
        ```

        2. JobResource em `app/Http/Resources/JobResource.php`: shape conforme openapi.yaml componente `Job` (job_id, customer, cluster_server, cmd_canonical, job_type, state, queued_at, started_at, finished_at, exit_code, summary).

        3. Livewire `Jobs\Index`:
        ```php
        class Index extends Component {
            use WithPagination;

            #[\Livewire\Attributes\Url(as: 'state')]
            public string $stateFilter = '';

            #[\Livewire\Attributes\Url(as: 'job_type')]
            public string $jobTypeFilter = '';

            #[\Livewire\Attributes\Url(as: 'customer')]
            public string $customerFilter = '';

            public function updating(): void { $this->resetPage(); }

            public function render() {
                $jobs = Job::query()
                    ->with(['customer', 'clusterServer'])
                    ->when($this->stateFilter, fn ($q, $s) => $q->where('state', $s))
                    ->when($this->jobTypeFilter, fn ($q, $t) => $q->where('job_type', $t))
                    ->when($this->customerFilter, fn ($q, $c) => $q->where('customer_slug', 'like', "%{$c}%"))
                    ->orderBy('created_at', 'desc')
                    ->paginate(25);

                return view('livewire.jobs.index', compact('jobs'));
            }
        }
        ```

        4. Routes:
        - `routes/api.php`: `Route::middleware('auth:sanctum')->group(...)` com /queue, /queue/stats, /queue/{id}.
        - `routes/web.php`: `Route::middleware('auth')->get('/queue', \App\Http\Livewire\Jobs\Index::class)->name('queue.index');`

        Blade: tabela com badge de state (cores: queued=cinza, running=azul, success=verde, failed=coral, cancelled=cinza), filtros wire:model.live.debounce, paginacao Livewire.

        Testes em `tests/Feature/Api/QueueIndexTest.php` cobrem 4 cenarios.

        NAO retornar campos sensiveis (mas payload_sanitized ja vem sanitizado pelo upstream conforme contract). NAO permitir GET /queue sem auth (sanctum guard).

    </details>

---

## Sprint D6 — Customers: Provisionar + Listar + Remover (F2 + F3 + F4 + F10)

> Categoria: D
> Gate: Marina via UI provisiona customer com slug valido (`acme-prod`) → SSH retorna `job_id` em <2s → webhook conclui em <5min → customer status=`active`. Slug `acme_prod` rejeitado 422 ANTES de SSH. Anexo logo de 800KB e' enviado via SCP staging para `/opt/nextcloud-customers/inbox/<staging-id>/logo.png`. Remove com `--backup-first` exige digitar slug literalmente
> review: senior+qa

| Status | Tamanho | Tarefa                                                                                                                                                                            | Skill/Command      | Depende de                    |
| ------ | ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ | ----------------------------- |
| [x]    | M       | 6.1 — Listar customers (replica local) + sync sob demanda + cron diario `php artisan customers:sync`                                                                              | `laravel-livewire` | 1.4, 2.1                      |
| [x]    | M       | 6.2 — Provisionar customer (Livewire form + endpoint POST /customers + SSH `manage.sh create --async --idempotency-key --callback` + SCP staging > 256KB)                         | `laravel-livewire` | 6.1, 2.1, 5.1 `critica: true` |
| [x]    | M       | 6.3 — Remover customer (modal forte com slug confirm + endpoint DELETE + SSH `nextcloud-manage <client> _ remove --force --backup-first --async --json`)                          | `laravel-livewire` | 6.2                           |
| [x]    | P       | 6.4 — Detalhes do customer (Livewire `Customers\Show`) com aba Jobs, OCC, Branding, Audit timeline                                                                                | `laravel-livewire` | 6.1                           |
| [x]    | M       | 6.5 — Polling fallback: command `php artisan jobs:poll-stuck` (Schedule a cada 5min) busca jobs `running` ha > 60s sem callback e chama `nextcloud-manage job <id> status --json` | `laravel-api`      | 2.1, 1.4                      |
| [x]    | P       | 6.6 — Testes Feature provisionar (slug invalido 422 antes SSH, idempotency 409, anexo SCP staging, webhook conclui) + remove + sync                                               | `laravel-testing`  | 6.1-6.5                       |

**Notas tecnicas (tarefas M):**

<details>
<summary>6.1 — Listar customers + sync sob demanda + cron diario</summary>

#### Mini Design Doc (minimo)

**Escopo**: listagem paginada do espelho local de `customers` + botao "Ressincronizar" (admin only) + cron diario as 03:00 que reconcilia divergencias com upstream `manage.sh list --json`.
**Componentes**: Livewire `Customers\Index`; service `CustomerSyncService::sync($cluster): SyncReport`; command `customers:sync`.
**Riscos**: cluster offline → sync falha → Mitigacao: catch SshConnectionException + audit log entry + retry next cron.

- **Arquivo(s)**:
    - `app/Http/Livewire/Customers/Index.php`
    - `app/Modules/Customers/Services/CustomerSyncService.php`
    - `app/Modules/Customers/Dto/SyncReport.php`
    - `app/Console/Commands/CustomersSyncCommand.php`
- **Abordagem**: Index com filtros status/cluster_server/search-by-slug + paginacao 25; botao "Ressincronizar" chama `CustomerSyncService::sync($clusterId)` para cada cluster ativo; service compara lista upstream com tabela local: insere ausentes, atualiza divergentes, marca soft-delete remotos que sumiram do upstream.
- **Decisoes**: divergencias geram audit log (action=`customer_sync_diverged`, payload com diff). NAO sobrescrever campos editados localmente (branding_meta) — apenas status/domain/last_sync_at vem do upstream.
- **Edge cases**: customer existe local mas nao upstream → marcar local como `removed` + audit; existe upstream mas nao local → INSERT (raro, indica algo fora do fluxo); cluster offline → status do cluster vai para `unreachable` mas sync nao falha o processo todo (pula esse cluster).
- **Anti-patterns**: NAO deletar registros locais (apenas soft-delete). NAO duplicar audit em divergencias triviais (mesmo status).
- **Validacoes**: cluster.status == 'active' antes de tentar sync.
- **Cenarios de teste**:
    1. Cron sync detecta customer no upstream nao presente local → INSERT + audit
    2. Customer presente local nao no upstream → soft-delete + audit
    3. Cluster offline → audit warning, sync prossegue para outros clusters
    4. Filtros combinados (status=active + cluster=X)
- **Budget**: 6 testes
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md`
- **Criterio de aceite**:
    - Cron diario as 03:00 executa para todos clusters active
    - Botao "Ressincronizar" (admin) trigga sync sync (loading state)
    - Audit log tem entrada para divergencias
- **executor_prompt**: | ### Quality Brief (Sprint D6)

        1. Service `app/Modules/Customers/Services/CustomerSyncService.php`:
        ```php
        class CustomerSyncService {
            public function __construct(private SshClientInterface $ssh) {}

            public function sync(ClusterServer $cluster): SyncReport {
                // [E1,E5] nextcloud-manage list nao suporta --json — retorna texto tabulado.
                // Parsear linha a linha: cada linha tem "nome  dominio  status" separado por espacos.
                $resp = $this->ssh->run($cluster, 'nextcloud-manage', ['list'], null, 30);
                $lines = array_filter(explode("\n", trim($resp->stdout)));
                $upstream = array_map(fn ($line) => preg_split('/\s+/', trim($line), 3), $lines);
                // Estrutura normalizada: [['slug' => ..., 'domain' => ..., 'status' => ...], ...]
                $upstream = array_map(fn ($p) => ['slug' => $p[0] ?? '', 'domain' => $p[1] ?? '', 'status' => $p[2] ?? ''], $upstream);
                $upstream = array_filter($upstream, fn ($u) => ! empty($u['slug'])); // remove header/vazio

                $upstreamSlugs = collect($upstream['customers'] ?? [])->pluck('slug');
                $localSlugs = Customer::where('cluster_server_id', $cluster->id)->pluck('slug');

                $report = new SyncReport();

                foreach ($upstream['customers'] ?? [] as $u) {
                    $local = Customer::find($u['slug']);
                    if (! $local) {
                        Customer::create([
                            'slug' => $u['slug'],
                            'cluster_server_id' => $cluster->id,
                            'domain' => $u['domain'],
                            'status' => $u['status'],
                            'last_sync_at' => now(),
                        ]);
                        $report->inserted++;
                        $this->auditDiverged('customer_sync_inserted', $u['slug'], $u);
                    } elseif ($local->status !== $u['status'] || $local->domain !== $u['domain']) {
                        $local->update([
                            'status' => $u['status'],
                            'domain' => $u['domain'],
                            'last_sync_at' => now(),
                        ]);
                        $report->updated++;
                        $this->auditDiverged('customer_sync_updated', $u['slug'], $u);
                    } else {
                        $local->update(['last_sync_at' => now()]);
                    }
                }

                // Customers presentes local mas nao upstream → soft-delete
                Customer::where('cluster_server_id', $cluster->id)
                    ->whereNotIn('slug', $upstreamSlugs)
                    ->whereNull('deleted_at')
                    ->each(function ($c) use ($report) {
                        $c->update(['status' => 'removed']);
                        $c->delete(); // soft-delete
                        $report->deleted++;
                        $this->auditDiverged('customer_sync_removed', $c->slug, ['previous_status' => $c->getOriginal('status')]);
                    });

                return $report;
            }

            private function auditDiverged(string $action, string $slug, array $payload): void {
                \App\Models\AuditLog::create([
                    'actor_id' => null,
                    'action' => $action,
                    'resource_type' => 'customer',
                    'resource_id' => $slug,
                    'payload' => $payload,
                ]);
            }
        }
        ```

        2. Command `app/Console/Commands/CustomersSyncCommand.php`:
        ```php
        class CustomersSyncCommand extends Command {
            protected $signature = 'customers:sync {--cluster=}';
            public function handle(CustomerSyncService $svc) {
                $clusters = $this->option('cluster')
                    ? ClusterServer::where('id', $this->option('cluster'))->get()
                    : ClusterServer::where('status', 'active')->get();

                foreach ($clusters as $c) {
                    try {
                        $report = $svc->sync($c);
                        $this->info("[{$c->name}] inserted={$report->inserted} updated={$report->updated} deleted={$report->deleted}");
                    } catch (\Throwable $e) {
                        $this->error("[{$c->name}] sync falhou: {$e->getMessage()}");
                        Log::channel('security')->warning("customer sync falhou", ['cluster' => $c->id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }
        ```

        Schedule diario as 03:00 em Console/Kernel.php.

        3. Livewire `Customers\Index` similar a Jobs\Index (paginacao + filtros status/cluster/search), botao "Ressincronizar" (admin only) chama `app(CustomerSyncService::class)->sync($cluster)` para o cluster filtrado.

        Testes em `tests/Feature/Customers/SyncTest.php` cobrem 4 cenarios listados, mockando SshClient.

        NAO sobrescrever `branding_meta` (campo local-only).

    </details>

<details>
<summary>6.2 — Provisionar customer (form + endpoint POST + SSH async + SCP staging)</summary>

#### Mini Design Doc (COMPLETO — feature mais critica do MVP)

**Escopo**: feature ponta-a-ponta de provisionamento. Form Livewire + endpoint API + service que orquestra SSH + SCP staging (para anexos > 256KB) + payload-stdin (para inline base64 < 256KB) + idempotency-key + callback URL. NAO inclui retry manual (botao "Tentar novamente" gera nova idempotency-key — feature pos-MVP).

**Componentes envolvidos**:

- `Customers\Create` Livewire (form com upload de logo/background)
- `CustomerController::store` API endpoint
- `ProvisionCustomerAction`: orquestrador
- `Form Request ProvisionCustomerRequest`: validation rigorosa de slug
- `SshClient::scpUpload` para staging
- `SshClient::runAsync` para chamar manage.sh
- `IdempotencyKey` model

**Fluxo de dados**:

```
User submits form (slug, cluster_id, domain, apps[], full_apps, logo, background)
  → ProvisionCustomerRequest valida (slug regex, slug unique, anexos < 5MB, cluster active)
    → ProvisionCustomerAction::execute:
       1. Gerar idempotency_key UUID v4
       2. Persistir IdempotencyKey + Job (state=queued, customer ainda nao existe)
       3. Se anexos > 256KB total: gerar staging_id + scpUpload para /opt/nextcloud-customers/inbox/<staging-id>/
       4. Compor args: [domain, 'create', '--async', "--idempotency-key={$key}", "--callback={$callbackUrl}", '--json', "--apps={$apps}", '--full-apps'?]
          // [E1,E3] comando: nextcloud-manage <client> <domain> create [flags]
          // client (slug) vai como primeiro arg de $cmd ao chamar run(); domain e' OBRIGATORIO posicional
          // [E9] callbackUrl DEVE ser https:// — upstream rejeita IPs RFC 1918
       5. Compor payloadStdin: {logo_data_url?: base64, background_data_url?: base64} se inline (<256KB por anexo)
       6. SshClient::runAsync($cluster, 'nextcloud-manage', array_merge([$customer->slug, $domain], $args), $payloadStdin)
       7. Parse response: job_id (UUID v4) — atualizar Job local com job_id; criar Customer local com status=provisioning
       8. AuditLog (action=provision_initiated)
       9. Retornar Customer + Job para UI
    → UI redireciona para /customers/{slug} mostrando job em andamento
    → (assincrono) Webhook chega → atualiza Job e Customer.status=active
```

**Decisoes de design**:

1. idempotency-key gerada server-side (nunca aceita do cliente HTTP) — alinha com REQUIREMENTS §3 e ARCHITECTURE.
2. Threshold 256KB POR ANEXO (nao total): se logo=200KB + background=200KB → ambos inline; se logo=200KB + background=300KB → apenas background via SCP.
3. SCP staging dir gerado com `staging_id` UUID v4; o upstream e responsavel por limpar apos consumir (contract upstream).
4. Cluster offline durante submit → 503 + retry_after; NAO criar Job local (idempotency-key + Job ficam em estado consistente apenas se SSH retornou com sucesso).
5. exit 3 (idempotency_conflict): NAO criar duplicata — retornar job existente do payload de erro.
6. exit 4 (state_conflict): retornar 409 + diff dos args.

**Riscos**:

1. **SSH retorna job_id mas Job local ja foi criado em transacao** — race entre transacao DB e SSH → Mitigacao: criar Job local DEPOIS do SSH retornar (nao antes); reservar idempotency_key antes para nao duplicar tentativas.
2. **SCP upload sobe arquivo + SSH falha** → arquivo orfao em staging → Mitigacao: upstream limpa staging dirs > 24h via cron proprio (contract upstream §3.9.0).
3. **Anexo malicioso (.exe renomeado .png)** → Mitigacao: validar mime type real via `getimagesize()` server-side; rejeitar se nao image/png ou image/jpeg.
4. **Slug squatting**: dois admins criam mesmo slug simultaneo → unique constraint no DB + idempotency check.
5. **Vazamento de `apps` list**: se appslist vem do request, validar contra whitelist conhecida (apps oficiais Nextcloud + appstore).

**Plano de rollback**: se exit do SSH != 0, NAO persistir Customer local + retornar erro especifico baseado em exit code (2=503, 3=409 idempotency, 4=409 state_conflict, outros=500). IdempotencyKey persiste (TTL 24h) para evitar reentrancia.

**Cenarios de teste** (alimentam test-writer):

1. Slug `acme-prod` valido + anexos pequenos → SSH chamado com payload-stdin → job_id retornado → Customer + Job criados local
2. Slug `acme_prod` (underscore) → 422 ANTES de SSH (Form Request rule)
3. Slug `Acme-Prod` (uppercase) → 422 ANTES de SSH
4. Slug `acme-prod` ja existe local → 409 inline antes de SSH
5. Anexo logo 800KB → SCP staging para /opt/nextcloud-customers/inbox/<staging-id>/logo.png + SSH com `--staging-id`
6. Anexo logo 100KB + background 100KB → ambos inline base64 via payload-stdin
7. SSH retorna exit 3 (idempotency_conflict) → 409 com job_id existente
8. SSH retorna exit 4 (state_conflict) → 409 com diff
9. cluster offline → 503 + retry_after; sem Customer/Job locais
10. Anexo .exe renomeado .png → 422 (mime real)

- **Arquivo(s)**:
    - `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
    - `app/Modules/Customers/Dto/ProvisionPayload.php`
    - `app/Http/Requests/ProvisionCustomerRequest.php`
    - `app/Http/Controllers/Api/CustomerController.php`
    - `app/Http/Livewire/Customers/Create.php`
    - `resources/views/livewire/customers/create.blade.php`
- **Abordagem**: action invocavel singleton; transacao apenas para criar IdempotencyKey + (depois) Job + Customer; SSH fora da transacao (porque pode ser longo); rollback explicito em caso de erro.
- **Decisoes**: 256KB por anexo via `strlen(file_get_contents($file)) <= 256 * 1024`; staging_id gerado por `Str::uuid()`; payload-stdin como JSON com keys `logo_data_url`/`background_data_url`.
- **Edge cases**: cluster_server soft-deleted → 422; cluster status=unreachable → 503; lista de apps vazia + full_apps=false → ok (instalacao base); domain duplicado em outro slug → permitido (mesmo cluster pode ter multiple domains).
- **Anti-patterns**: NAO permitir cliente passar idempotency-key. NAO normalizar slug (rejeitar com 422). NAO permitir anexos sem validar mime real.
- **Validacoes**: slug regex `^[a-z0-9-]+$` max 64; cluster_id UUID + active; domain valid hostname; logo/background image/png ou image/jpeg, max 5MB cada; apps array de strings (validate against whitelist? P task — aceitar qualquer string por ora).
- **Budget**: 12 testes (cobertura completa de paths de erro)
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/laravel-api/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md`, `~/.cursor/skills/vocabulary-translator/SKILL.md`
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md`, `~/.cursor/skills/capabilities/service-composition/references/orchestration-patterns.md`
- **Seguranca**: validar mime real; payload-stdin nunca em log; SCP path com staging_id (proteger contra path traversal — usar `Str::uuid()` do Laravel garante).
- **UX**: form com validacao client-side em Alpine.js + server-side; loading state durante submit; redirect para tela de detalhes do customer com job em andamento.
- **Criterio de aceite**:
    - Marina via UI consegue provisionar customer em <5min (medido via webhook conclusao)
    - Slug invalido (`acme_prod`) rejeitado 422 ANTES de SSH (medivel: SshClient nao chamado)
    - Anexo 800KB sobe via SCP (medivel: scpUpload chamado, payload-stdin sem campo `logo_data_url`)
    - exit 3 do SSH retorna 409 + job_id existente
- **executor_prompt**: | ### Quality Brief (Sprint D6)
  Esta task e MARCADA `critica: true` (Best-of-N). 2 implementadores rodam em paralelo; selecionar melhor resultado.

        1. `app/Http/Requests/ProvisionCustomerRequest.php`:
        ```php
        class ProvisionCustomerRequest extends FormRequest {
            public function authorize(): bool {
                return in_array($this->user()?->role, ['admin', 'operador'], true);
            }

            public function rules(): array {
                return [
                    'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique:customers,slug'],
                    'cluster_server_id' => ['required', 'uuid', 'exists:cluster_servers,id'],
                    'domain' => ['required', 'string', 'max:255'],
                    'apps' => ['nullable', 'array'],
                    'apps.*' => ['string', 'max:100'],
                    'full_apps' => ['nullable', 'boolean'],
                    'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
                    'background' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
                ];
            }

            public function messages(): array {
                return [
                    'slug.regex' => 'Use apenas letras minusculas, numeros e hifen.',
                    'slug.unique' => 'Slug ja em uso.',
                ];
            }
        }
        ```

        2. `app/Modules/Customers/Actions/ProvisionCustomerAction.php`:
        ```php
        class ProvisionCustomerAction {
            public function __construct(
                private readonly SshClientInterface $ssh,
                private readonly StateTranslator $stateTranslator,
            ) {}

            public function execute(ProvisionPayload $payload, Operator $actor): array {
                $cluster = ClusterServer::findOrFail($payload->clusterServerId);

                if ($cluster->status !== 'active') {
                    throw new ClusterUnreachableException();
                }

                $idempotencyKey = (string) Str::uuid();
                $stagingId = null;
                $args = [$payload->slug, $payload->domain, 'create', '--async', '--json',
                    "--idempotency-key={$idempotencyKey}",
                    '--callback=' . config('app.url') . '/api/jobs/hook',
                ];
                if ($payload->fullApps) $args[] = '--full-apps';
                if ($payload->apps) $args[] = '--apps=' . implode(',', $payload->apps);

                // Anexos: > 256KB → SCP; <= 256KB → inline base64 stdin
                $stdin = [];
                $totalAttachments = collect([$payload->logoPath, $payload->backgroundPath])
                    ->filter()
                    ->sum(fn ($p) => filesize($p));

                if ($payload->logoPath || $payload->backgroundPath) {
                    $useScp = ($payload->logoPath && filesize($payload->logoPath) > 256 * 1024)
                        || ($payload->backgroundPath && filesize($payload->backgroundPath) > 256 * 1024);

                    if ($useScp) {
                        $stagingId = (string) Str::uuid();
                        if ($payload->logoPath) {
                            $this->ssh->scpUpload($cluster, $payload->logoPath, "/opt/nextcloud-customers/inbox/{$stagingId}/logo.png");
                        }
                        if ($payload->backgroundPath) {
                            $this->ssh->scpUpload($cluster, $payload->backgroundPath, "/opt/nextcloud-customers/inbox/{$stagingId}/background.png");
                        }
                        $args[] = "--staging-id={$stagingId}";
                    } else {
                        if ($payload->logoPath) {
                            $stdin['logo_data_url'] = 'data:image/png;base64,' . base64_encode(file_get_contents($payload->logoPath));
                        }
                        if ($payload->backgroundPath) {
                            $stdin['background_data_url'] = 'data:image/png;base64,' . base64_encode(file_get_contents($payload->backgroundPath));
                        }
                        $args[] = '--payload-stdin';
                    }
                }

                // Persistir idempotency key ANTES do SSH
                \DB::transaction(function () use ($idempotencyKey, $payload, $args) {
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'cmd' => 'create',
                        'args_hash' => hash('sha256', json_encode($args)),
                        'customer_slug' => $payload->slug,
                        'expires_at' => now()->addHours(24),
                    ]);
                });

                try {
                    $resp = $this->ssh->runAsync($cluster, 'manage.sh', $args, $stdin ? json_encode($stdin) : null);
                } catch (SshRemoteException $e) {
                    if ($e->idempotencyConflict ?? false) {
                        $existingJob = $resp->parsedJson['existing_job_id'] ?? null;
                        throw new IdempotencyConflictException($existingJob);
                    }
                    if ($e->stateConflict ?? false) {
                        throw new StateConflictException($resp->parsedJson['diff'] ?? []);
                    }
                    throw $e;
                }

                $jobId = $resp->parsedJson['job_id'] ?? throw new \RuntimeException('SSH nao retornou job_id');

                return \DB::transaction(function () use ($payload, $cluster, $jobId, $idempotencyKey, $resp, $actor) {
                    IdempotencyKey::where('key', $idempotencyKey)->update(['job_id' => $jobId]);

                    $customer = Customer::create([
                        'slug' => $payload->slug,
                        'cluster_server_id' => $cluster->id,
                        'domain' => $payload->domain,
                        'status' => 'provisioning',
                        'last_sync_at' => now(),
                    ]);

                    $job = Job::create([
                        'job_id' => $jobId,
                        'customer_slug' => $payload->slug,
                        'cluster_server_id' => $cluster->id,
                        'cmd_canonical' => 'create',
                        'job_type' => app(JobTypeTranslator::class)->cmdToJobType('create'),
                        'state' => 'queued',
                        'idempotency_key' => $idempotencyKey,
                        'payload_sanitized' => ['slug' => $payload->slug, 'domain' => $payload->domain, 'apps' => $payload->apps, 'full_apps' => $payload->fullApps],
                        'queued_at' => now(),
                    ]);

                    AuditLog::create([
                        'actor_id' => $actor->id,
                        'action' => 'provision_initiated',
                        'resource_type' => 'customer',
                        'resource_id' => $payload->slug,
                        'payload' => $job->payload_sanitized,
                        'cluster_server_id' => $cluster->id,
                        'job_id' => $jobId,
                    ]);

                    return ['customer' => $customer, 'job' => $job];
                });
            }
        }
        ```

        3. Controller `CustomerController::store`:
        ```php
        public function store(ProvisionCustomerRequest $request, ProvisionCustomerAction $action) {
            $payload = ProvisionPayload::fromRequest($request);

            try {
                $result = $action->execute($payload, $request->user());
            } catch (IdempotencyConflictException $e) {
                return response()->json(['error' => 'idempotency_conflict', 'existing_job_id' => $e->getJobId()], 409);
            } catch (StateConflictException $e) {
                return response()->json(['error' => 'state_conflict', 'diff' => $e->getDiff()], 409);
            } catch (ClusterUnreachableException $e) {
                return response()->json(['error' => 'cluster_unreachable'], 503)
                    ->header('Retry-After', '60');
            }

            return new CustomerResource($result['customer']);
        }
        ```

        4. Livewire `Customers\Create` com `WithFileUploads` para logo/background; metodo `submit()` chama `ProvisionCustomerAction::execute(...)`.

        5. Validacao mime real adicional em Form Request via `after`:
        ```php
        public function withValidator($validator) {
            $validator->after(function ($v) {
                foreach (['logo', 'background'] as $f) {
                    if (! $this->hasFile($f)) continue;
                    $real = $this->file($f)->getMimeType();
                    if (! in_array($real, ['image/png', 'image/jpeg'], true)) {
                        $v->errors()->add($f, 'Tipo de imagem invalido (mime real).');
                    }
                }
            });
        }
        ```

        6. Routes: `Route::middleware('auth:sanctum')->post('/customers', [CustomerController::class, 'store']);`

        Testes em `tests/Feature/Customers/ProvisionTest.php` cobrem 10 cenarios. Mockar SshClientInterface, ClusterServer factory com status=active.

        NAO aceitar idempotency-key do cliente. NAO normalizar slug. NAO logar payload-stdin completo. SshClient mocked para evitar SSH real em testes.

    </details>

<details>
<summary>6.3 — Remover customer com modal forte + backup-first</summary>

#### Mini Design Doc (COMPLETO — operacao destrutiva, dados sensiveis, primeira impl de modal forte)

**Escopo**: feature de remocao de customer com confirmacao forte (digitar slug literalmente, case-sensitive) + opcao `--backup-first` (default ON) + execucao via SSH async + atualizacao de Customer.status=removing → removed.

**Componentes**:

- `Customers\Show` Livewire com botao "Remover" → modal
- `RemoveCustomerAction`
- Endpoint `DELETE /customers/{slug}`
- AuditLog entry de alta visibilidade

**Fluxo de dados**:

```
Operator clica "Remover" em /customers/{slug}
  → Modal forte exige digitar exatamente $slug + checkbox backup
    → DELETE /customers/{slug} (body: {confirm_slug, backup_first})
      → RemoveCustomerAction::execute:
         1. Customer deve existir + status != removing/removed
         2. confirm_slug == customer.slug (case-sensitive)
         3. Gerar idempotency_key
         4. // [E1,E4] nextcloud-manage <client> _ remove --force [--backup-first] --async --json
            SshClient::runAsync($cluster, 'nextcloud-manage', array_filter([$slug, '_', 'remove', '--force', $backup ? '--backup-first' : null, '--async', '--json', "--idempotency-key={$key}"]))
         5. Atualizar Customer.status=removing + criar Job
         6. AuditLog (action=remove_initiated, severity=high)
      → 202 Accepted + job_id
    → Webhook conclui → Customer.status=removed + soft-delete
```

**Decisoes de design**:

1. `--confirm=$slug` literal (alinhado com manage.sh contract) — defesa em profundidade contra remocoes acidentais.
2. `--backup-first` default ON na UI mas opcional (admin sabe o que faz).
3. AuditLog com `severity=high` — destacar em listagem por filtro.
4. Soft-delete local apenas quando webhook confirma success; ate la, customer fica visivel com badge "removing".

**Riscos**:

1. Remove de customer com active jobs → upstream pode rejeitar → Mitigacao: aceitar exit code do upstream + propagar erro; UI explica.
2. Backup falha mas remocao prossegue → Mitigacao: confiar em `--backup-first` do upstream (atomico la); se backup falha, manage.sh retorna error apos backup nao apos remove.
3. Operator com role=suporte tenta remover → Mitigacao: Gate `manage-customers` (apenas admin+operador).

**Plano de rollback**: nao ha — remocao no upstream e' irreversivel apos backup expira (contract). UI deixa isso explicito.

**Cenarios de teste**:

1. Operador clica Remover, digita slug correto + backup ON → 202 + job criado + customer.status=removing + audit
2. Operador digita slug incorreto → 422 (validation client + server)
3. role=suporte → 403
4. customer ja em status=removing → 409 (job em andamento)
5. SSH retorna exit 4 (state_conflict) → 409
6. Webhook conclui success → customer.status=removed + soft-delete
7. AuditLog tem severity=high

- **Arquivo(s)**:
    - `app/Modules/Customers/Actions/RemoveCustomerAction.php`
    - `app/Modules/Customers/Exceptions/{ConfirmationMismatchException,RemoveInProgressException}.php`
    - `app/Http/Requests/RemoveCustomerRequest.php`
    - Atualizar `CustomerController::destroy`
    - Modal Livewire em `Customers\Show`
- **Abordagem**: similar a 6.2 — action separa logica de orquestracao do controller; idempotency_key gerada server-side; transacao pos-SSH para atualizar Customer + criar Job + Audit.
- **Decisoes**: confirm_slug case-sensitive; modal Livewire com Alpine.js para UX (digitar para habilitar botao "Confirmar").
- **Edge cases**: customer ja soft-deleted → 404; status=removing → 409 idempotente.
- **Anti-patterns**: NAO esconder o nome do slug no modal (operador deve ver claramente o que vai apagar). NAO permitir `--no-backup` por default na UI (sempre pedir confirmacao explicita para desligar).
- **Validacoes**: confirm_slug obrigatorio; backup_first booleano.
- **Budget**: 8 testes
- **References**: `~/.cursor/skills/laravel-livewire/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md`
- **Patterns**: `~/.cursor/skills/capabilities/service-composition/references/laravel-decision-trees.md`
- **Criterio de aceite**:
    - Modal exige digitar exatamente o slug
    - SSH chamado com `--confirm=$slug --backup-first` (default)
    - Status local transita provisioning|active → removing → removed
    - Audit log severity=high
- **executor_prompt**: | ### Quality Brief (Sprint D6)

        1. `app/Http/Requests/RemoveCustomerRequest.php`:
        ```php
        class RemoveCustomerRequest extends FormRequest {
            public function authorize(): bool {
                return in_array($this->user()?->role, ['admin', 'operador'], true);
            }
            public function rules(): array {
                return [
                    'confirm_slug' => ['required', 'string'],
                    'backup_first' => ['nullable', 'boolean'],
                ];
            }
        }
        ```

        2. `app/Modules/Customers/Actions/RemoveCustomerAction.php`:
        ```php
        class RemoveCustomerAction {
            public function __construct(private SshClientInterface $ssh) {}

            public function execute(string $slug, string $confirmSlug, bool $backupFirst, Operator $actor): Job {
                $customer = Customer::findOrFail($slug);

                if ($customer->slug !== $confirmSlug) {
                    throw new ConfirmationMismatchException();
                }

                if (in_array($customer->status, ['removing', 'removed'], true)) {
                    throw new RemoveInProgressException();
                }

                $cluster = $customer->clusterServer;
                $idempotencyKey = (string) Str::uuid();
                // [E1,E4] nextcloud-manage <client> _ remove --force [--backup-first] --async --json
                // [E9] callback deve ser https:// — upstream rejeita RFC 1918
                $args = [$slug, '_', 'remove', '--force', '--async', '--json',
                    "--idempotency-key={$idempotencyKey}",
                    '--callback=' . config('app.url') . '/api/jobs/hook',
                ];
                if ($backupFirst) $args[] = '--backup-first';

                try {
                    $resp = $this->ssh->runAsync($cluster, 'manage.sh', $args);
                } catch (SshRemoteException $e) {
                    if ($e->stateConflict ?? false) throw new StateConflictException();
                    throw $e;
                }

                $jobId = $resp->parsedJson['job_id'];

                return \DB::transaction(function () use ($customer, $cluster, $jobId, $idempotencyKey, $actor, $backupFirst) {
                    $customer->update(['status' => 'removing']);
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'cmd' => 'remove',
                        'args_hash' => hash('sha256', $customer->slug . ($backupFirst ? '|backup' : '')),
                        'customer_slug' => $customer->slug,
                        'job_id' => $jobId,
                        'expires_at' => now()->addHours(24),
                    ]);

                    $job = Job::create([
                        'job_id' => $jobId,
                        'customer_slug' => $customer->slug,
                        'cluster_server_id' => $cluster->id,
                        'cmd_canonical' => 'remove',
                        'job_type' => app(JobTypeTranslator::class)->cmdToJobType('remove'),
                        'state' => 'queued',
                        'idempotency_key' => $idempotencyKey,
                        'payload_sanitized' => ['backup_first' => $backupFirst],
                        'queued_at' => now(),
                    ]);

                    AuditLog::create([
                        'actor_id' => $actor->id,
                        'action' => 'remove_initiated',
                        'resource_type' => 'customer',
                        'resource_id' => $customer->slug,
                        'payload' => ['backup_first' => $backupFirst, 'severity' => 'high'],
                        'cluster_server_id' => $cluster->id,
                        'job_id' => $jobId,
                    ]);

                    return $job;
                });
            }
        }
        ```

        3. Controller `CustomerController::destroy`:
        ```php
        public function destroy(string $slug, RemoveCustomerRequest $req, RemoveCustomerAction $action) {
            try {
                $job = $action->execute($slug, $req->string('confirm_slug'), $req->boolean('backup_first', true), $req->user());
            } catch (ConfirmationMismatchException $e) {
                return response()->json(['error' => 'confirm_slug_mismatch'], 422);
            } catch (RemoveInProgressException $e) {
                return response()->json(['error' => 'already_in_progress'], 409);
            } catch (StateConflictException $e) {
                return response()->json(['error' => 'state_conflict'], 409);
            }

            return response()->json(['job_id' => $job->job_id], 202);
        }
        ```

        4. Webhook (D5.1) ja atualiza Job.state quando termina; adicionar listener/observer no Job que, quando state transita para success E cmd_canonical=remove, executa `Customer::find($customer_slug)?->update(['status' => 'removed'])->delete()` (soft).

        5. Modal Livewire em Customers\Show com Alpine.js:
        ```html
        <div x-data="{ confirmInput: '', expected: @js($customer->slug) }">
            <input x-model="confirmInput" placeholder="Digite {{ $customer->slug }} para confirmar" />
            <button :disabled="confirmInput !== expected" wire:click="remove">Remover</button>
        </div>
        ```

        Testes em `tests/Feature/Customers/RemoveTest.php` cobrem 7 cenarios.

        NAO permitir bypass do modal (sempre validar server-side). NAO marcar customer.status=removed antes do webhook confirmar.

    </details>

<details>
<summary>6.5 — Polling fallback: jobs:poll-stuck</summary>

#### Mini Design Doc (minimo)

**Escopo**: cron a cada 5min que busca jobs em state=running ha mais de 60s SEM `callback_received_at` e chama `manage.sh job <id> status --json` via SSH. Atualiza state local conforme retorno upstream. Garante consistencia mesmo se webhook falhar.
**Componentes**: Command `JobsPollStuckCommand`; usa SshClient + StateTranslator.
**Riscos**: cluster offline → polling falha em loop → Mitigacao: cluster.status=unreachable bloqueia polling para esse cluster.

- **Arquivo(s)**: `app/Console/Commands/JobsPollStuckCommand.php`
- **Abordagem**: query `Job::where('state', 'running')->whereNull('callback_received_at')->where('queued_at', '<', now()->subMinute())->get()`; para cada, chamar `manage.sh job <id> status --json`, parsear, atualizar state via StateTranslator.
- **Decisoes**: limite de 50 jobs por execucao (evitar long-running command); intervalo 5min via Schedule.
- **Edge cases**: SSH falha → log warning + skip; upstream retorna state desconhecido → log + skip.
- **Cenarios de teste**:
    1. Job running ha 90s sem callback → polling chama SSH, recebe state=done → atualiza local para success
    2. Cluster unreachable → polling pula esse cluster, processa outros
    3. SSH retorna exit 1 (job nao existe) → marcar Job como failed + audit
- **Budget**: 4 testes
- **References**: `~/.cursor/skills/ssh-orchestrator/SKILL.md`
- **Criterio de aceite**:
    - Schedule a cada 5min em Console/Kernel.php
    - Comando idempotente (rodar duas vezes seguidas nao causa side effect)
- **executor_prompt**: | ### Quality Brief (Sprint D6)

        `app/Console/Commands/JobsPollStuckCommand.php`:
        ```php
        class JobsPollStuckCommand extends Command {
            protected $signature = 'jobs:poll-stuck';

            public function handle(SshClientInterface $ssh, StateTranslator $st) {
                $stuck = Job::query()
                    ->where('state', 'running')
                    ->whereNull('callback_received_at')
                    ->where('queued_at', '<', now()->subMinute())
                    ->limit(50)
                    ->get();

                foreach ($stuck as $job) {
                    $cluster = $job->clusterServer;
                    if ($cluster->status !== 'active') {
                        $this->warn("Skipping {$job->job_id}: cluster {$cluster->name} not active");
                        continue;
                    }

                    try {
                        $resp = $ssh->run($cluster, 'manage.sh', ['job', $job->job_id, 'status', '--json'], null, 30);
                        $data = $resp->parsedJson;
                        if (! $data) { $this->warn("No JSON for {$job->job_id}"); continue; }

                        $canonical = $st->toCanonical($data['state']);
                        $job->update([
                            'state' => $canonical,
                            'last_poll_at' => now(),
                            'finished_at' => $data['finished_at'] ?? $job->finished_at,
                            'exit_code' => $data['exit_code'] ?? null,
                        ]);

                        AuditLog::create([
                            'actor_id' => null,
                            'action' => 'job_polled',
                            'resource_type' => 'job',
                            'resource_id' => $job->job_id,
                            'payload' => ['from_polling' => true, 'canonical' => $canonical],
                            'job_id' => $job->job_id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::channel('security')->warning("polling failed for {$job->job_id}: {$e->getMessage()}");
                    }
                }
            }
        }
        ```

        Schedule: `$schedule->command('jobs:poll-stuck')->everyFiveMinutes()->withoutOverlapping();`

        Testes em `tests/Feature/Console/JobsPollStuckTest.php` cobrem 3 cenarios.

        NAO aumentar limite alem de 50 (long-running command).

    </details>

---

## Sprint D7 — OCC Essenciais: Sync + Async Lifecycle (F6)

> Categoria: D
> Gate: operador via UI define quota de user em <60s (sync passthrough); cria user com 3 grupos via async (job_id retornado em <2s, webhook conclui em <60s); habilita app `calendar` via async; toggle maintenance mode sync 60s
> review: senior+qa

| Status | Tamanho | Tarefa                                                                                                                                                                                                                           | Skill/Command      | Depende de |
| ------ | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ | ---------- |
| [x]    | M       | 7.1 — OCC sync passthrough: endpoints quota (set/audit/options/all/default), branding, maintenance, files:rescan, app:enable individual via `nextcloud-manage <client> occ-exec <subcmd>` (timeout 60s) ← [E1,E8] sem domain `_` | `laravel-api`      | 2.1        |
| [x]    | M       | 7.2 — Lifecycle user/group async: POST /customers/{c}/users, /groups, /apps/enable, /apps/disable via `nextcloud-manage <client> user create --async --json` etc. (Feature O.2) ← [E1]                                           | `laravel-api`      | 2.1, 5.1   |
| [x]    | P       | 7.3 — Endpoints DELETE async: DELETE /users/{username}, /groups/{group}                                                                                                                                                          | `laravel-api`      | 7.2        |
| [x]    | P       | 7.4 — Livewire `Customers\OccPanel` com abas Quota / Branding / Maintenance / Apps / Users / Groups                                                                                                                              | `laravel-livewire` | 7.1, 7.2   |
| [x]    | P       | 7.5 — Testes Feature OCC sync (timeout, mime, validacao) + async lifecycle (idempotency, webhook conclui)                                                                                                                        | `laravel-testing`  | 7.1-7.4    |

**Notas tecnicas (tarefas M):**

<details>
<summary>7.1 — OCC sync passthrough endpoints</summary>

#### Mini Design Doc (minimo)

**Escopo**: passthrough sync para 9 endpoints OCC: quota set/audit/options/all/default por user e por group, branding, maintenance toggle, files:rescan, apps:enable individual. Timeout 60s sync; resposta direta da OCC parseada para JSON.
**Componentes**: `OccController` com 9 metodos; `OccPassthroughService` que invoca SshClient::run com `nextcloud-manage <client> occ-exec <subcmd>` e parseia stdout. ← [E1,E8] namespace syntax: sem domain `_` posicional.
**Riscos**: comando OCC trava (rare) → Mitigacao: timeout 60s no SshClient + 504 Gateway Timeout no API.

- **Arquivo(s)**:
    - `app/Http/Controllers/Api/OccController.php`
    - `app/Modules/Customers/Services/OccPassthroughService.php`
    - `app/Http/Requests/Occ/{SetQuotaRequest,SetBrandingRequest,ToggleMaintenanceRequest,...}.php`
- **Abordagem**: 1 service com metodos enxutos por subcomando; parse stdout/stderr conforme contract upstream (OCC retorna JSON em --output=json).
- **Decisoes**: timeout 60s alinha com REQUIREMENTS §F6. Sem queue local (sync passthrough — bloqueia HTTP request).
- **Edge cases**: customer em maintenance mode → ainda pode chamar maintenance:off (caso especial); user nao existe no Nextcloud → upstream retorna exit 1 → mapear para 404.
- **Anti-patterns**: NAO usar OCC sync para create/delete user (usar async em 7.2 — atomic multi-step).
- **Validacoes**: quotas em formato `(N(GB|MB|KB))|none|default`; branding hex colors; rescan accepts username opcional.
- **Budget por endpoint**: 4 testes (happy + auth + timeout + invalid input)
- **References**: `~/.cursor/skills/laravel-api/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md`
- **Criterio de aceite**: 9 endpoints conforme `docs/openapi.yaml` paths `/customers/{c}/occ/*` retornam shape correto
- **executor_prompt**: | ### Quality Brief (Sprint D7)

        1. `app/Modules/Customers/Services/OccPassthroughService.php`:
        ```php
        class OccPassthroughService {
            public function __construct(private SshClientInterface $ssh) {}

            public function exec(Customer $customer, string $subcmd, array $args = [], int $timeoutSec = 60): array {
                $cluster = $customer->clusterServer;
                if ($cluster->status !== 'active') throw new ClusterUnreachableException();

                $resp = $this->ssh->run(
                    $cluster,
                    'manage.sh',
                    // [E1,E8] nextcloud-manage <client> occ-exec <subcmd> — sem domain posicional
                    array_merge(['occ-exec', $subcmd], $args, ['--json']),
                    null,
                    $timeoutSec
                );

                return $resp->parsedJson ?? throw new \RuntimeException('OCC nao retornou JSON');
            }
        }
        ```

        2. `app/Http/Controllers/Api/OccController.php` com metodos:
        - `setQuota($slug, $username, SetQuotaRequest $req)` → exec('user:setting', [$username, 'files', 'quota', $req->quota])
        - `setQuotaDefault($slug, ...)` → exec('config:app:set', ['files', 'default_quota', '--value', $req->quota])
        - `setQuotaAll($slug, ...)` → ?
        - `setBranding($slug, ...)` → exec('theming:config', ['name', $req->name])
        - `toggleMaintenance($slug, $on)` → exec('maintenance:mode', [$on ? '--on' : '--off'])
        - `filesRescan($slug, ?$username)` → exec('files:scan', $username ? [$username] : ['--all'])
        - `enableApp($slug, $appId)` → exec('app:enable', [$appId])
        - `quotaAudit($slug)` → exec('files:scan', ['--all', '--show-quota'])
        - `quotaOptions($slug)` → static array (nao chama SSH; lista opcoes pre-definidas)

        3. Form Requests com validacao especifica de cada operacao. Ex SetQuotaRequest:
        ```php
        public function rules() {
            return ['quota' => ['required', 'string', 'regex:/^(\d+(GB|MB|KB)|none|default)$/i']];
        }
        ```

        4. Routes em `routes/api.php` agrupadas em `Route::middleware('auth:sanctum')->prefix('customers/{customer}/occ')->group(function () { ... })`.

        Testes em `tests/Feature/Api/OccControllerTest.php` cobrem 4 cenarios por endpoint critico (quota, branding, maintenance, app:enable).

        NAO usar para create/delete user. NAO permitir argumentos arbitrarios via API (cada endpoint tem allow-list explicita de args).

    </details>

<details>
<summary>7.2 — Lifecycle user/group async (Feature O.2)</summary>

#### Mini Design Doc (minimo)

**Escopo**: endpoints async para criar/deletar user, criar/deletar group, add/remove user em group, enable/disable app. Cada endpoint chama `manage.sh <slug> <subcmd> --async --idempotency-key --callback` retorna job_id em <2s.
**Componentes**: `LifecycleAsyncService` similar a `ProvisionCustomerAction` mas para subcomandos lifecycle; reutiliza idempotency_key + Job tracking.
**Riscos**: idempotency conflict (mesma operacao em <24h) → Mitigacao: retornar job existente (alinhado com Customer provision pattern).

- **Arquivo(s)**:
    - `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
    - `app/Http/Controllers/Api/CustomerLifecycleController.php`
    - Form Requests para cada operacao
- **Abordagem**: action invocavel generica que recebe `cmd` + args + customer; constroi args SSH + retorna job_id; create Job local.
- **Decisoes**: payload sensivel (senha de novo user) sempre via stdin; idempotency_key derivada de hash(slug + cmd + args_normalizados).
- **Edge cases**: user/group ja existe no NC → upstream retorna exit 4 → mapear 409; senha fraca → upstream rejeita exit 22 → mapear 422.
- **Anti-patterns**: NAO passar senha em argv. NAO criar Job local antes do SSH retornar.
- **Validacoes**: username regex `^[a-zA-Z0-9._-]+$` max 64; password min 8; group name regex similar.
- **Budget por operacao**: 4 testes
- **References**: `~/.cursor/skills/laravel-api/SKILL.md`, `~/.cursor/skills/ssh-orchestrator/SKILL.md`
- **Criterio de aceite**: 7 endpoints conforme `docs/openapi.yaml` paths `/customers/{c}/users`, `/customers/{c}/groups`, `/customers/{c}/apps/{enable,disable}` retornam 202 + job_id em <2s
- **executor_prompt**: | ### Quality Brief (Sprint D7)

        1. `app/Modules/Customers/Actions/LifecycleAsyncAction.php`:
        ```php
        class LifecycleAsyncAction {
            public function __construct(
                private SshClientInterface $ssh,
                private JobTypeTranslator $translator,
            ) {}

            public function execute(
                Customer $customer,
                string $cmd, // 'users:create', 'users:delete', 'groups:create', 'groups:delete', 'groups:add', 'groups:remove', 'apps:enable', 'apps:disable'
                array $args, // arg posicional + named flags (sem credenciais)
                ?array $stdinPayload, // ex: ['password' => '...']
                Operator $actor,
            ): Job {
                $cluster = $customer->clusterServer;
                if ($cluster->status !== 'active') throw new ClusterUnreachableException();

                $idempotencyKey = (string) Str::uuid();
                $sshArgs = array_merge(
                    [$customer->slug, ...explode(' ', $cmd)],
                    $args,
                    ['--async', '--json', "--idempotency-key={$idempotencyKey}", '--callback=' . config('app.url') . '/api/jobs/hook']
                );
                if ($stdinPayload) $sshArgs[] = '--payload-stdin';

                $resp = $this->ssh->runAsync($cluster, 'manage.sh', $sshArgs, $stdinPayload ? json_encode($stdinPayload) : null);
                $jobId = $resp->parsedJson['job_id'];

                return \DB::transaction(function () use ($customer, $cluster, $jobId, $idempotencyKey, $cmd, $args, $actor) {
                    IdempotencyKey::create([
                        'key' => $idempotencyKey,
                        'cmd' => $cmd,
                        'args_hash' => hash('sha256', $customer->slug . '|' . $cmd . '|' . json_encode($args)),
                        'customer_slug' => $customer->slug,
                        'job_id' => $jobId,
                        'expires_at' => now()->addHours(24),
                    ]);

                    $job = Job::create([
                        'job_id' => $jobId,
                        'customer_slug' => $customer->slug,
                        'cluster_server_id' => $cluster->id,
                        'cmd_canonical' => $cmd,
                        'job_type' => $this->translator->cmdToJobType($cmd),
                        'state' => 'queued',
                        'idempotency_key' => $idempotencyKey,
                        'payload_sanitized' => ['args' => $args], // NAO incluir stdinPayload (senha)
                        'queued_at' => now(),
                    ]);

                    AuditLog::create([
                        'actor_id' => $actor->id,
                        'action' => "{$cmd}_initiated",
                        'resource_type' => 'customer',
                        'resource_id' => $customer->slug,
                        'payload' => ['args' => $args, 'cmd' => $cmd],
                        'cluster_server_id' => $cluster->id,
                        'job_id' => $jobId,
                    ]);

                    return $job;
                });
            }
        }
        ```

        2. Controller `CustomerLifecycleController` com metodos:
        - `createUser(Customer, CreateUserRequest)` → action->execute($c, 'users:create', [$req->username, $req->email, ...$req->groups], ['password' => $req->password], ...)
        - `deleteUser(Customer, string $username)` → action->execute($c, 'users:delete', [$username], null, ...)
        - `createGroup(Customer, CreateGroupRequest)` → action->execute($c, 'groups:create', [$req->name], null, ...)
        - `deleteGroup(Customer, string $group)` → action->execute($c, 'groups:delete', [$group], null, ...)
        - `addUserToGroup(Customer, AddUserToGroupRequest)` → action->execute($c, 'groups:add', [$req->username, $req->group], null, ...)
        - `removeUserFromGroup(Customer, RemoveUserFromGroupRequest)` → action->execute($c, 'groups:remove', [$req->username, $req->group], null, ...)
        - `enableApps(Customer, EnableAppsRequest)` → para cada app, action->execute($c, 'apps:enable', [$app], null, ...)
        - `disableApps(Customer, DisableAppsRequest)` → similar
        - Retornar 202 + ['job_id' => $job->job_id]

        3. Routes:
        ```php
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/customers/{customer}/users', [CustomerLifecycleController::class, 'createUser']);
            Route::delete('/customers/{customer}/users/{username}', [CustomerLifecycleController::class, 'deleteUser']);
            Route::post('/customers/{customer}/groups', [CustomerLifecycleController::class, 'createGroup']);
            Route::delete('/customers/{customer}/groups/{group}', [CustomerLifecycleController::class, 'deleteGroup']);
            Route::post('/customers/{customer}/apps/enable', [CustomerLifecycleController::class, 'enableApps']);
            Route::post('/customers/{customer}/apps/disable', [CustomerLifecycleController::class, 'disableApps']);
        });
        ```

        Testes em `tests/Feature/Api/CustomerLifecycleTest.php` cobrem cenarios criticos (create user com password via stdin, idempotency_conflict 409, exit 22 password fraca → 422, exit 4 user existente → 409).

        NAO passar password em argv. NAO logar payload-stdin. NAO criar Job antes do SSH retornar job_id.

    </details>

---

## Sprint D8 — Polish: Audit Retention (F7) + Auditorias + Deploy Staging

> Categoria: D
> Gate: pipeline CI verde no PR final; auditorias DBA + Security + Performance + Senior sem CRITICAL/HIGH; staging valida fluxo Marina end-to-end (provisionar customer → webhook → ativo); cron `audit:purge` remove logs > 12 meses
> review: comprehensive

| Status | Tamanho | Tarefa                                                                                                             | Skill/Command         | Depende de    |
| ------ | ------- | ------------------------------------------------------------------------------------------------------------------ | --------------------- | ------------- |
| [x]    | M       | 8.1 — Audit log retention 12m: command `audit:purge` (mensal) + indice composto created_at + comparativo de volume | `laravel-migration`   | 4.5           |
| [x]    | M       | 8.2 — Testes E2E críticos via Pest browser (Marina provisiona, Rafael cancela job, Sofia reseta quota)             | `laravel-testing`     | 6.2, 5.4, 7.1 |
| [x]    | P       | 8.3 — Auditoria DBA (revisar indices, queries N+1, plano de explain, eager loading)                                | `auditoria-dba`       | 8.1           |
| [x]    | P       | 8.4 — Auditoria Security (CSRF, mass assignment, raw queries, secrets em log)                                      | `auditoria-seguranca` | 8.1           |
| [x]    | P       | 8.5 — Documentacao operacional: README + runbook (rotate secret, sync, deploy) + atualizar `docs/CI-CD.md`         | `/dev doc`            | 8.1-8.4       |
| [x]    | P       | 8.6 — Deploy staging via INFRASTRUCTURE.md (manual humano), rodar smoke test E2E, registrar resultado              | —                     | 8.5           |

**Notas tecnicas (tarefas M):**

<details>
<summary>8.1 — Audit log retention 12m</summary>

#### Mini Design Doc (minimo)

**Escopo**: cron mensal que apaga audit_logs com `created_at < now()->subMonths(12)`. Tabela e' append-only por design (sem updated_at), entao DELETE e' operacao oposta. Volume estimado: ~10k entries/mes (auditoria de cada acao admin) → ~120k apos 12m.
**Componentes**: Command `AuditPurgeCommand`; usa `chunkById` para processar em lotes.
**Riscos**: lock prolongado em DELETE → Mitigacao: chunkById de 1000 + sleep entre chunks; rodar em horario off-peak (03:30 mensal).

- **Arquivo(s)**: `app/Console/Commands/AuditPurgeCommand.php`
- **Abordagem**: `AuditLog::where('created_at', '<', now()->subMonths(12))->chunkById(1000, fn ($chunk) => $chunk->each->delete())`. Schedule mensal dia 1 as 03:30.
- **Decisoes**: chunkById em vez de truncate (preservar logs recentes); exportar para S3 antes de delete? Para MVP: nao, apenas DELETE (compliance LGPD permite descarte apos retencao).
- **Edge cases**: tabela vazia → no-op; volume excede 1M registros → cap em 100k por execucao + `--dry-run` flag.
- **Anti-patterns**: NAO usar truncate; NAO usar DELETE sem indice (created_at ja indexado).
- **Validacoes**: `--dry-run` exibe count sem deletar; `--retention-months=N` parameter overrides default 12.
- **Cenarios de teste**:
    1. Cron processa logs > 12m, mantem dentro do range
    2. `--dry-run` nao deleta, exibe count
    3. Volume zero → nada acontece
- **Budget**: 3 testes
- **References**: `~/.cursor/skills/laravel-migration/SKILL.md` (chunkById patterns)
- **Criterio de aceite**:
    - Cron mensal dia 1 as 03:30 limpa logs > 12m
    - Indice `idx_audit_logs_created_at` ja existe (DBML)
    - Comando idempotente
- **executor_prompt**: | ### Quality Brief (Sprint D8)

        `app/Console/Commands/AuditPurgeCommand.php`:
        ```php
        class AuditPurgeCommand extends Command {
            protected $signature = 'audit:purge {--retention-months=12} {--dry-run}';

            public function handle() {
                $cutoff = now()->subMonths($this->integer('retention-months', 12));
                $query = AuditLog::where('created_at', '<', $cutoff);

                if ($this->option('dry-run')) {
                    $count = $query->count();
                    $this->info("Would delete {$count} audit_logs older than {$cutoff}");
                    return;
                }

                $deleted = 0;
                $query->chunkById(1000, function ($chunk) use (&$deleted) {
                    foreach ($chunk as $log) {
                        $log->delete();
                        $deleted++;
                    }
                    usleep(100000); // 100ms
                });

                $this->info("Deleted {$deleted} audit_logs older than {$cutoff}");
                Log::info("audit:purge", ['deleted' => $deleted, 'cutoff' => $cutoff->toIso8601String()]);
            }
        }
        ```

        Schedule mensal: `$schedule->command('audit:purge')->monthlyOn(1, '03:30')->withoutOverlapping();`

        Testes em `tests/Feature/Console/AuditPurgeTest.php` cobrem 3 cenarios.

    </details>

<details>
<summary>8.2 — Testes E2E criticos (Pest browser)</summary>

#### Mini Design Doc (minimo)

**Escopo**: 3 cenarios E2E que cobrem os fluxos das 3 personas:

1. Marina provisiona customer com slug valido → SSH (mockado) → webhook simulado → customer.status=active
2. Rafael cancela job em queued → SSH cancel chamado → state=cancelled
3. Sofia reseta quota de user via OCC sync passthrough → SSH retorna OK
   **Componentes**: Pest browser tests (Laravel Dusk ou Pest browser).
   **Riscos**: testes flaky → Mitigacao: aguardar selectors (max 10s) + cleanup explicito.

- **Arquivo(s)**: `tests/Browser/CriticalFlowsTest.php`
- **Abordagem**: usar Laravel Dusk; mockar SshClient via container binding em `setUp` para evitar SSH real; webhook simulado via `$this->postJson('/api/jobs/hook', ...)` com HMAC valido computado.
- **Decisoes**: nao rodar contra ambiente de staging (CI rapido); usar headless Chrome.
- **Edge cases**: timeout de selector → re-tentar com `waitForText`; database state precisa de RefreshDatabase entre testes.
- **Cenarios de teste**: ja descritos no escopo.
- **Budget**: 6 testes (2 por cenario — happy + 1 edge)
- **References**: `~/.cursor/skills/laravel-testing/SKILL.md`, `~/.cursor/skills/e2e-testing-workflow/SKILL.md`
- **Criterio de aceite**: 3 fluxos rodam em <2min total no CI; nenhum teste flaky em 10 execucoes seguidas
- **executor_prompt**: | ### Quality Brief (Sprint D8) — review: comprehensive

        composer require --dev laravel/dusk; php artisan dusk:install

        Em `tests/Browser/CriticalFlowsTest.php`:

        ```php
        class CriticalFlowsTest extends DuskTestCase {
            use DatabaseMigrations;

            public function test_marina_provisiona_customer(): void {
                $admin = Operator::factory()->create(['role' => 'admin']);
                $cluster = ClusterServer::factory()->create(['status' => 'active']);
                $this->mockSshClient(); // helper que faz $this->app->bind(SshClientInterface::class, fn () => $mock)

                $this->browse(function (Browser $browser) use ($admin, $cluster) {
                    $browser->loginAs($admin)
                        ->visit('/customers/create')
                        ->type('slug', 'acme-prod')
                        ->select('cluster_server_id', $cluster->id)
                        ->type('domain', 'acme.example.com')
                        ->press('Provisionar')
                        ->assertPathIs('/customers/acme-prod')
                        ->assertSee('provisioning');
                });

                // Simular webhook conclusao
                $job = Job::where('customer_slug', 'acme-prod')->first();
                $secret = $cluster->webhook_secret_encrypted;
                $body = json_encode(['job_id' => $job->job_id, 'state' => 'done', 'finished_at' => now()->toIso8601String(), 'exit_code' => 0]);
                $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

                $this->withServerVariables(['REMOTE_ADDR' => $cluster->ssh_host]) // ou mock IP
                    ->postJson('/api/jobs/hook', json_decode($body, true), [
                        'X-Signature' => $sig,
                        'X-Cluster-Server-Id' => $cluster->id,
                    ])->assertNoContent();

                $this->assertEquals('active', Customer::find('acme-prod')->status);
            }

            // testes para Rafael cancela job e Sofia reseta quota similares
        }
        ```

        Configurar Dusk no CI (.github/workflows/ci.yml adicionar step de browser tests com Chrome headless).

        NAO chamar SSH real em E2E. NAO depender de fila externa (mock tudo no boundary).

    </details>

---

## Caminho Critico

A sequencia mais longa de dependencias que define o menor caminho em numero de tasks (e ordem obrigatoria):

```
D1.3 (Migrations)
  → D2.1 (SshClient) [critica]
    → D4.1 (ClusterServers CRUD)
      → D4.3 (Rotate Secret)
        → D5.1 (Webhook Receiver) [critica]
          → D6.1 (Listar Customers)
            → D6.2 (Provisionar) [critica]
              → D6.3 (Remover)
                → D8.2 (E2E)
                  → D8.6 (Deploy Staging)
```

10 tarefas sequenciais no caminho critico — atrasos aqui atrasam o projeto inteiro.

**Tarefas paralelizaveis fora do caminho critico**:

- D2.2/D2.3 (Tradutores) podem rodar em paralelo a D2.1
- D3.\* (Auth) pode rodar paralelo a D2 (raiz independente)
- D4.5 (Audit) pode rodar paralelo a D4.1-4.3
- D7._ (OCC) pode rodar paralelo a D8._ (depende apenas de D6 + D2)

---

## Proximos Passos

Apos aprovacao deste roadmap:

1. **Aprovar via `/jarvis pipeline range=D1-D8`** ou comecar manual com `/pmo sprint D1` (mas o usuario escolheu modo Pipeline)
2. Cada sprint tem `executor_prompt` autocontido em todas as tasks M — sub-agente fast (Composer 2) recebe direto
3. Tasks com `critica: true` (D2.1, D5.1, D6.2) lancam Best-of-N (2 implementadores em worktrees, melhor selecionado)
4. Auditorias seguem a "Estrategia de Auditoria" definida acima — pipeline mode = sem AskQuestion entre sprints
5. Cada sprint produz `docs/DIARY.md` com aprendizados; sprints seguintes leem antes de comecar

---

## Sprint F1 — Gerar + Revogar Credencial (MVP Incompleto)

> Categoria: F
> Gate: admin gera credencial → token exibido uma vez → hash armazenado; revogar seta `revoked_at`; audit log registra ambas; 5 testes passing
> review: none (task P isolada, sem dependencias criticas)

| Status | Tamanho | Tarefa                                                                                                             | Skill/Command        | Depende de |
| ------ | ------- | ------------------------------------------------------------------------------------------------------------------ | -------------------- | ---------- |
| [x]    | P       | F1.1 — ApiKeyService (generate + revoke) + modal Livewire + revogar por linha + 5 testes                          | `laravel-livewire`   | —          |

**Contexto**: tela `/api-keys` entregue no D8 tinha botao "Gerar Nova Credencial" com stub `alert('Sprint 2')`. REQUIREMENTS v0.3 §10 e §501 previam geracao no MVP. Triagem inline classificou como MVP incompleto (FEATURE) → Sprint F direta.

**Escopo F1.1**:
- `app/Modules/Core/Services/ApiKeyService.php` — `generate(name, scopes, actor)` + `revoke(id, actor)` + `list(filter)`
- `app/Http/Livewire/ApiKeys/Index.php` — inject service; add `openCreate()`, `create()`, `revoke(id)`, `closeTokenReveal()`
- `resources/views/livewire/api-keys/index.blade.php` — modal criar + revelacao unica de token + botao revogar por linha
- `database/factories/ApiKeyFactory.php` + `tests/Feature/ApiKeys/ApiKeyTest.php` (5 cenarios)

**Token**: `sk_` + `bin2hex(random_bytes(32))` = 67 chars. Armazenar `hash('sha256', $raw)`. Exibir raw UMA vez (modal pos-criacao).
**Revogacao**: `revoked_at = now()`. Token revogado nao altera `token_hash` (apenas marca).
**Audit**: `action=api_key.create` e `action=api_key.revoke` em `audit_logs`.
**NAO incluir**: self-service via API publica, expiracao automatica, escopos avancados (campo `scopes` aceita array livre, nao validado no MVP).

---

## Sprint F2 — Sprint 2: UX Operadores + Chart + Findings Backlog

> Categoria: F
> Gate: operador altera senha com sucesso (sessao mantida); admin edita role de outro operador e a mudanca reflete imediatamente; `artisan operators:create-admin` cria admin funcional; dashboard exibe chart jobs 7d; findings D3-F009 + D4-F004 + D4-F008 corrigidos; 0 CRITICAL/HIGH abertos; testes passando
> review: senior+qa (dados de operadores + autorizacao)
> Auditado em: 2026-05-15 (planejamento via /analista escopo)

| Status | Tamanho | Tarefa                                                                                                                            | Skill/Command       | Depende de |
| ------ | ------- | --------------------------------------------------------------------------------------------------------------------------------- | ------------------- | ---------- |
| [x]    | P       | F2.1 — `artisan operators:create-admin` interativo (nome, email, senha via prompt)                                               | `laravel-migration` | —          |
| [x]    | M       | F2.2 — Dashboard chart jobs 7d (Chart.js, sucesso vs falha por dia, dark M3 theme) + fix FOUC inline CSS vars                    | `laravel-livewire`  | —          |
| [x]    | P       | F2.3 — Fix D3-F009: sentinela de autorizacao cobre remocao de customers (Gate provision-customers)                               | `laravel-api`       | —          |
| [x]    | P       | F2.4 — Fix D4-F004: queue worker no docker-compose dev (servico `queue` com `php artisan queue:work`)                            | `laravel-docker`    | —          |
| [x]    | P       | F2.5 — Fix D4-F008: SSH PEM fora de propriedade Livewire sincrona (mover para metodo de leitura lazy)                           | `laravel-livewire`  | —          |
| [x]    | M       | F2.6 — Operador altera propria senha (`/profile/password` Livewire: senha atual + nova + confirmacao + audit log)                | `laravel-livewire`  | —          |
| [x]    | M       | F2.7 — Admin edita perfil de operador (`/operators/{id}/edit`: nome, role, status, reenviar convite; audit log)                  | `laravel-livewire`  | —          |
| [x]    | M       | F2.8 — Tela `/settings` IP allowlist webhook (admin: gerenciar IPs permitidos por cluster_server; SEC-F016 fix)                  | `laravel-livewire`  | —          |
| [x]    | P       | F2.9 — Bug: clipboard na tela /api-keys — async/await + fallback execCommand para nao-HTTPS; remover icone de copia mascarada    | `laravel-livewire`  | —          |
| [x]    | M       | F2.10 — Log detalhado do job (`/queue/{job_id}` Livewire Show): terminal-style [INFO/TASK/WARN/EXEC] via campo summary; polling wire:poll.5s para jobs running; botoes Export Log + Scroll to Bottom; header com job_id, estado, customer, timestamps | `laravel-livewire` | F2.11 |
| [x]    | M       | F2.11 — Redesign tela `/queue` conforme Stitch provisioning_queue: stats cards (Active Ops, Completed 24h, Failed 24h, Avg Provision Time); tabs All/Running/Failed; progress bar para jobs running; auto-refresh 10s toggle; botao Export; link "View Logs" para F2.10 | `laravel-livewire` | — |

**Contexto F2**: Sprint 2 pos-MVP. Nenhum CRITICAL/HIGH aberto apos D8+F1. Foco em: (a) UX operadores — alterar senha e editar perfil; (b) chart dashboard (SHOULD-HAVE REQUIREMENTS §7); (c) artisan create-admin; (d) fix MEDIUM backlog; (e) /settings IP allowlist; (f) bug clipboard e UX de fila de provisionamento.

**Nota tecnica F2.2**: Chart.js adicionado como dependencia em `package.json`. Entry Vite dedicada em `resources/js/pages/dashboard.js`. Requer `npm install && npm run build` apos merge. FOUC fix (inline CSS vars no layout) implementado junto.

**Nota tecnica F2.9**: `navigator.clipboard.writeText()` e async — sem `.catch()` falha silenciosamente em HTTP (apenas funciona em HTTPS ou localhost). Fix: `async/await` com `.catch(e => fallback execCommand('copy'))`. O icone de copia na linha da tabela nao tem handler nenhum — token mascarado nao pode ser recuperado; substituir por icone `key_off` com tooltip.

**Nota tecnica F2.10**: campo `summary` no model Job e JSON (pode conter linhas de log do upstream). Para jobs running, polling via `wire:poll.5s`. Para jobs concluidos, exibir `summary` estatico. Linhas parseadas por prefixo: `[INFO]` → primary, `[TASK]` → secondary, `[WARN]` → tertiary, `[EXEC]` → on-surface-variant, `[ERROR]` → error.

**Nota tecnica F2.11**: tela atual `/queue` e "Logs de Provisionamento" — renomear titulo para "Fila de Provisionamento". Avg Provision Time = `AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at))` filtrado por success. Progress bar para `running`: placeholder animado (nao ha % real do upstream via webhook).

---

---

## Sprint F3 — Tech Debt LOW: Schema, Security e Observability

> Categoria: F
> Gate: 0 findings LOW desta sprint pendentes; testes passando; mensagens de validacao em pt-BR; AuditLog `cluster_server.rotate_webhook_secret` com acao especifica; `invite_token_hash` com UNIQUE constraint; `role`/`status` fora do $fillable de Operator; args SSH sensiveis mascarados nos logs
> Gerado por `/fix` em 2026-05-15. Fonte: 8 findings LOW (D4-F009, D4-F005, DBA-F010, DBA-F011, DBA-F012, SEC-F013, SEC-F014, SEC-F015).
> review: skip

| Status | Tamanho | Tarefa                                                                                          | Skill/Command         | Depende de |
| ------ | ------- | ----------------------------------------------------------------------------------------------- | --------------------- | ---------- |
| [x]    | P       | F3.1 — [FIX] D4-F009: AuditLog especifico para `rotate_webhook_secret` em RotateWebhookSecretAction | `laravel-api`    | —          |
| [x]    | P       | F3.2 — [FIX] D4-F005: pt-BR — locale + laravel-lang/lang + `config/app.php`                   | `laravel-migration`   | —          |
| [x]    | P       | F3.3 — [FIX] DBA-F010/F011: FK `sessions.user_id` para operators + UNIQUE `invite_token_hash` (migration) | `laravel-migration` | — |
| [x]    | P       | F3.4 — [FIX] DBA-F012: Eager load `clusterServer` em OccController/OccPassthroughService       | `laravel-api`         | —          |
| [x]    | P       | F3.5 — [FIX] SEC-F013: Rate limit login secundario por email (5 tentativas, 300s block)         | `laravel-livewire`    | —          |
| [x]    | P       | F3.6 — [FIX] SEC-F015: Remover `role`/`status`/`invite_token_hash` do $fillable de Operator    | `laravel-migration`   | —          |
| [x]    | M       | F3.7 — [FIX] SEC-F014: Mascarar args SSH sensiveis nos logs (`--idempotency-key`, `--callback`) | `ssh-orchestrator`    | —          |

**Contexto F3**: 8 findings LOW pos-D8 nao cobertos pelo Sprint F2. Nenhum CRITICAL/HIGH. F2 ja cobre os MEDIUM (D3-F009, D4-F008) e SEC-F016. Foco em: (a) integridade de schema (FK sessions, UNIQUE invite token); (b) housekeeping de seguranca ($fillable restrito, rate limit por conta, mascaramento SSH); (c) observabilidade semantica (AuditLog rotate); (d) i18n pt-BR.

---

### Task F3.1 — [FIX] D4-F009 — AuditLog especifico para rotate_webhook_secret

- **Finding:** D4-F009
- **File:** `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`
- **Correction:** Adicionar `AuditLog::create([..., 'action' => 'cluster_server.rotate_webhook_secret', ...])` explicitamente no `execute()` apos `$cluster->save()`. O observer continua registrando `cluster_server.update` (nao remover); o log explicito e adicional para filtragem semantica.
- **Test:** Feature test: `RotateWebhookSecretAction::execute()` cria entry em `audit_logs` com `action = cluster_server.rotate_webhook_secret` e `actor_id` correto.

---

### Task F3.2 — [FIX] D4-F005 — pt-BR: locale + lang package Laravel

- **Finding:** D4-F005
- **Files:** `config/app.php`, `resources/lang/pt_BR/` (criar via artisan)
- **Correction:**
  1. `composer require laravel-lang/lang laravel-lang/publisher --dev`
  2. `php artisan lang:add pt_BR`
  3. Setar `'locale' => 'pt_BR'` e `'fallback_locale' => 'en'` em `config/app.php`
- **Test:** Feature test: submeter form vazio retorna mensagem de erro em pt-BR ("O campo ... e obrigatorio." ou similar).

---

### Task F3.3 — [FIX] DBA-F010/F011 — FK sessions para operators + UNIQUE invite_token_hash

- **Findings:** DBA-F010, DBA-F011
- **Files:** nova migration `2026_05_15_000001_fix_sessions_fk_and_operator_unique.php`
- **Correction:**
  - DBA-F010: `$table->foreign('user_id')->references('id')->on('operators')->onDelete('cascade')` na tabela `sessions`
  - DBA-F011: `$table->unique('invite_token_hash')` na tabela `operators`
- **Test:** Migration sobe e reverte sem erro; FK e UNIQUE verificados via schema inspection no teste.

---

### Task F3.4 — [FIX] DBA-F012 — Eager load clusterServer em OccController

- **Finding:** DBA-F012
- **Files:** `app/Http/Controllers/Api/OccController.php`, `app/Modules/Customers/Services/OccPassthroughService.php`
- **Correction:** Adicionar `$customer->load('clusterServer')` no controller antes de passar para o service, ou ajustar route model binding para eager-load `clusterServer` automaticamente em contextos OCC.
- **Test:** Query count: 1 request OCC deve gerar <= 2 queries (customers + cluster_servers), nao 3+.

---

### Task F3.5 — [FIX] SEC-F013 — Rate limit login secundario por email

- **Finding:** SEC-F013
- **File:** `app/Http/Livewire/Auth/Login.php`
- **Correction:** Adicionar rate limiter secundario por email `login_email:{email}` com max 5 tentativas / 300s alem do existente por IP. Limpar via `RateLimiter::clear()` apos login bem-sucedido.
- **Test:** Feature test: 6 tentativas falhas com mesmo email retorna erro de rate limit na 7a tentativa.

---

### Task F3.6 — [FIX] SEC-F015 — Remover role/status/invite_token_hash do $fillable de Operator

- **Finding:** SEC-F015
- **File:** `app/Models/Operator.php`
- **Correction:** Remover `'role'`, `'status'`, `'invite_token_hash'` do `$fillable`. Verificar todos os `->create()` e `->fill()` no codebase que usem esses campos e substituir por atribuicao direta.
- **Test:** Teste unitario: `Operator::create(['role' => 'admin', ...])` nao persiste `role` via mass assignment.

---

### Task F3.7 — [FIX] SEC-F014 — Mascarar args SSH sensiveis nos logs

- **Finding:** SEC-F014
- **Files:** `app/Modules/Core/Ssh/SshClient.php`, mecanismo de mascaramento SSH existente
- **Correction:** Estender o masker SSH para redactar `--idempotency-key=<valor>` e `--callback=<url>`. Regex: `/--idempotency-key=\S+/` e `/--callback=\S+/` substituidos por `***`.
- **Test:** Teste unitario: comando com `--idempotency-key=abc123 --callback=https://...` e logado como `--idempotency-key=*** --callback=***`.

---

## Sprint N1 — Sync Webhook Secret com Upstream via SSH

> Categoria: N
> Gate: criar ClusterServer → SSH `config set-webhook-secret` chamado; SSH falha → `status='error'` + erro no Livewire; rotacionar secret → SSH chamado com novo secret; SSH falha na rotação → log de segurança + audit (grace period mantém continuidade); secret via `--payload-stdin` (nunca arg CLI); 225+ testes passando; CI verde
> Gerado por `/pmo new` em 2026-05-18. Fonte: ISSUE-001 (change request).
> Revisão final de design em 2026-05-18: sync de secret de cluster (não token por job).
> review: senior+qa

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | N1.1 — SyncWebhookSecretAction + Create.php + RotateWebhookSecretAction: sync via SSH ao criar e rotacionar cluster | `ssh-orchestrator` | — |

### Quality Brief (Sprint N1)

> PATTERN-001 (Decision #187): ao executar a auditoria de Quality Brief, o auditor-senior DEVE criar `docs/.briefs/N1.brief.md` como PRIMEIRO Write, antes de qualquer finding ou resumo. Sem este artefato, `.githooks/pre-commit` bloqueia o commit final da sprint.

---

### Task N1.1 — SyncWebhookSecretAction: sincronizar webhook secret com o upstream via SSH

**Estado atual**: `WebhookSecretGenerator::generate()` gera `base64_encode(random_bytes(32))` e o secret é salvo criptografado em `cluster_servers.webhook_secret_encrypted` (cast `'encrypted'` no model). O upstream **nunca recebe esse secret** — a validação HMAC-SHA256 no `VerifyWebhookHmac` usa o secret do DB, mas o upstream não sabe qual secret usar para assinar seus callbacks. Configuração manual necessária hoje. Dois pontos de update: (1) criação de ClusterServer em `ClusterServers\Create::save()`, (2) rotação em `RotateWebhookSecretAction::execute()`.

**Estado desejado**: Após criar ou rotacionar o secret, a API chama `nextcloud-manage config set-webhook-secret --payload-stdin` via SSH, passando o secret plain via stdin JSON. O upstream armazena o secret e passa a assinar callbacks com ele — o `VerifyWebhookHmac` existente valida sem mudanças. `SyncWebhookSecretAction` encapsula o SSH call e é reutilizado em ambos os contextos.

**Fonte(s)**: ISSUE-001 (2026-05-18)
**Módulo(s) afetado(s)**: `app/Modules/ClusterServers/Actions/` (nova + edit), `app/Http/Livewire/ClusterServers/Create.php`
**Risco**: MEDIUM — erro no Create destrói UX de cadastro; erro na Rotate pode deixar secret desincronizado (mitigado pelo grace period existente).
**Budget**: M (4 arquivos: nova action + Create.php + RotateWebhookSecretAction + testes)

**executor_prompt**:
```
Feature: sincronizar o webhook secret com o upstream nextcloud-saas-manager via SSH sempre que
um ClusterServer for criado ou seu webhook secret for rotacionado.

Contexto do sistema:
- API Laravel. ClusterServer armazena o webhook secret em `cluster_servers.webhook_secret_encrypted`
  com cast 'encrypted' no model (auto-encrypt/decrypt via Laravel Crypt).
- `WebhookSecretGenerator::generate()` retorna `base64_encode(random_bytes(32))` — valor plain.
- `VerifyWebhookHmac` middleware valida HMAC-SHA256 usando `WebhookSecretValidator`, que lê o
  secret do DB (já descriptografado pelo cast). O upstream precisa do mesmo secret para assinar.
- Regra OBRIGATÓRIA (ssh-orchestrator): NUNCA passar senhas/secrets como argv CLI. Usar --payload-stdin.
- `SshClientInterface` já existe em `app/Modules/Core/Ssh/`. Usar `executeCommand()` (síncrono,
  não runAsync) para esta operação de configuração.

Arquivos a tocar:
1. app/Modules/ClusterServers/Actions/SyncWebhookSecretAction.php (NOVA — encapsula SSH call)
2. app/Http/Livewire/ClusterServers/Create.php (injetar SyncWebhookSecretAction, chamar após create)
3. app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php (injetar + chamar após commit)
4. Testes (atualizar ClusterServers Create + Rotate; novos cenários de SSH failure)

Implementação detalhada:

1. SyncWebhookSecretAction (nova):
   Responsabilidade: chamar SSH `nextcloud-manage config set-webhook-secret --payload-stdin`
   passando o secret plain via stdin.

   namespace App\Modules\ClusterServers\Actions;

   final class SyncWebhookSecretAction
   {
       public function __construct(private readonly SshClientInterface $ssh) {}

       public function execute(ClusterServer $cluster, string $plainSecret): void
       {
           $this->ssh->executeCommand(
               $cluster,
               'nextcloud-manage',
               ['config', 'set-webhook-secret', '--payload-stdin'],
               json_encode(['secret' => $plainSecret]),
           );
       }
   }

   Exceções que podem propagar: SshConnectionException, SshRemoteException, SshTimeoutException.
   Não capturar aqui — deixar o caller decidir a estratégia de erro.

2. Create.php — após ClusterServer::create() e WebhookSecretHistory::create():
   - Injetar SyncWebhookSecretAction no método save() (Laravel injeta automaticamente)
   - Chamar APÓS o DB estar salvo (para que $cluster->id exista):

   try {
       $syncAction->execute($cluster, $plainSecret);
   } catch (\Throwable $e) {
       // SSH falhou: marcar cluster como erro, NÃO redirecionar
       $cluster->update(['status' => 'error']);
       AuditLog::create([
           'id' => Str::uuid()->toString(),
           'actor_id' => auth()->id(),
           'action' => 'cluster_server.secret_sync_failed',
           'resource_type' => 'cluster_server',
           'resource_id' => $cluster->id,
           'payload' => ['error' => $e->getMessage()],
       ]);
       $this->addError('ssh_private_key', 'Cluster salvo mas falha ao sincronizar webhook secret: '.$e->getMessage());
       return null; // não redireciona
   }
   // SSH OK — redirecionar normalmente

   IMPORTANTE: guardar $plainSecret = $secretGen->generate() ANTES de ClusterServer::create(),
   pois após create() o cast 'encrypted' não permite recuperar o plain value via $cluster->webhook_secret_encrypted
   sem uma query de leitura (encrypted cast decrypts on read — portanto $cluster->webhook_secret_encrypted
   APÓS create() retorna o plain value via read? Verificar: em Laravel, após ->create(), o model
   refresca os atributos — portanto $cluster->webhook_secret_encrypted DEVE retornar o valor
   descriptografado. Se confirmar, pode usar $cluster->webhook_secret_encrypted diretamente.
   Caso contrário, usar $plainSecret da variável local).

3. RotateWebhookSecretAction — após o DB::transaction():
   - Injetar SyncWebhookSecretAction no construtor
   - Chamar APÓS o commit da transação (fora do DB::transaction()):

   $new = DB::transaction(function () use ($cluster) { ... }); // código existente

   // Após commit: sincronizar novo secret com upstream
   // $cluster->webhook_secret_encrypted retorna o plain value (decrypt automático pelo cast)
   try {
       $this->syncAction->execute($cluster->fresh(), $cluster->fresh()->webhook_secret_encrypted);
   } catch (\Throwable $e) {
       // Grace period garante continuidade — upstream ainda aceita secret anterior por 24h
       AuditLog::create([
           'id' => Str::uuid()->toString(),
           'actor_id' => null,
           'action' => 'cluster_server.secret_sync_failed',
           'resource_type' => 'cluster_server',
           'resource_id' => $cluster->id,
           'payload' => ['error' => $e->getMessage(), 'version' => $new->version],
       ]);
       Log::channel('security')->warning('webhook.secret_sync_failed', [
           'cluster_id' => $cluster->id,
           'version' => $new->version,
           'error' => $e->getMessage(),
       ]);
       // NÃO relançar — rotação de DB foi bem-sucedida; admin pode forçar re-sync manualmente
   }
   return $new;

4. Testes:
   a. Create com SSH success → cluster status='active', redirect
   b. Create com SSH failure → cluster status='error', sem redirect, erro no componente
   c. Rotate com SSH success → novo secret no DB, SSH chamado com novo secret
   d. Rotate com SSH failure → novo secret no DB, AuditLog registra falha, sem exception relançada
   e. SyncWebhookSecretAction: verifica que executeCommand recebe 'config', 'set-webhook-secret',
      '--payload-stdin' e stdin contém JSON com chave 'secret'
   f. SyncWebhookSecretAction: NUNCA passa o secret como arg CLI (assert que args não contêm o secret)

Critério de pronto:
- SyncWebhookSecretAction chama SSH com --payload-stdin (secret no stdin JSON, não no argv)
- Create.php chama SSH após criar cluster; falha SSH → status='error' + erro Livewire
- RotateWebhookSecretAction chama SSH após commit; falha SSH → log + audit (sem exception)
- 225+ testes passando; CI verde

IMPORTANTE — Quality Brief (PATTERN-001 / Decision #187):
Como PRIMEIRO Write tool call, antes de qualquer finding, criar o arquivo:
  docs/.briefs/N1.brief.md

Frontmatter YAML obrigatória:
  ---
  auditor_subagent_id: "<UUID gerado>"
  audited_at: "<ISO-8601 atual>"
  findings_count: <n>
  severity_summary:
    CRITICAL: 0
    HIGH: 0
    MEDIUM: <n>
    LOW: <n>
  audit_evidence_path: ""
  sprint_id: "N1"
  planner: "planejador-melhorias"
  status: "PASS"
  ---
```

---

## Sprint N2 — Observabilidade: log de payload do webhook em ambiente local

> Categoria: N
> Gate: APP_ENV=local emite `Log::debug('webhook.payload_received')` com `cluster_server_id` + `ip` + `event` + `payload`; APP_ENV=testing não emite; 46/46 testes da suite de webhook passando
> Gerado por `/pmo new` em 2026-05-20. Fonte: ISSUE-005 (change request).
> Implementação executada inline (P-size, 1 arquivo + 2 testes); sprint registrada retroativamente para rastreabilidade.
> review: skip

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | N2.1 — VerifyWebhookHmac: `Log::debug` payload válido quando `APP_ENV=local` | `laravel-api` | — |

---

### Task N2.1 — VerifyWebhookHmac: Log::debug payload quando APP_ENV=local

**Estado atual** (pré-2026-05-20): desenvolvedor investiga incidentes de webhook por tcpdump ou inserindo middleware temporário. Rejeições por replay/dedupe não expõem o payload bruto — só `AuditLog` estruturado em `webhook_replay`/`webhook_replay_duplicate`.

**Estado desejado**: bloco `if (app()->environment('local')) { Log::debug('webhook.payload_received', [...]) }` em `VerifyWebhookHmac` posicionado após o guard de `event` enum e ANTES do replay/dedupe. Cobre replays/dedupes rejeitados (casos mais úteis para debug). `try/catch + report` protege HTTP response contra falha de canal de log. Gate por `APP_ENV=local` é mais restritivo que `APP_DEBUG` — staging com debug ativo NÃO loga.

**Fonte(s)**: ISSUE-005 (2026-05-20); postmortems ISSUE-002, ISSUE-003 (observabilidade)
**Módulo(s) afetado(s)**: `app/Http/Middleware/VerifyWebhookHmac.php`, `tests/Feature/Middleware/VerifyWebhookHmacTest.php`
**Risco**: LOW — feature aditiva guarded por env; payload não contém segredos (HMAC signature em header, não body); `APP_ENV=local` somente em dev
**Budget**: P (1 arquivo de produção + 2 testes pareados local/testing)

## Sprint F5 — Tradutor cmd → CLI argv: fix lifecycle async upstream contract

> Categoria: F
> Gate: criar usuário pelo `OccPanel` ou `POST /api/customers/{c}/users` enfileira job upstream com `exit 0 + job_id` (não `cmd_not_allowed`); mesmo para deletar usuário (`['user', 'remove']`), criar/deletar grupo (`['group', 'create|remove']`) e enable/disable apps (`['apps', 'enable|disable']` com CSV consolidado em 1 job); `groups:add`/`groups:remove` retornam HTTP 501 explícito (`not_implemented_yet`) até upstream entregar; `JobTypeTranslator::cmdToCliArgv()` cobre 100% dos pares; argv NUNCA contém `--async --json` duplicado; `cmd_canonical` no DB continua `users:create` (vocabulário interno preservado); 235+ testes passando; CI verde
> Gerado por `/fix` em 2026-05-20. Fonte: ISSUE-006 (postmortem HIGH; SSH probing real contra `mecloud360@MECloud360-NextCloud-SaaS-01` v12.3.0).
> **Status**: **concluída** (11/11 tasks — sync 2026-06-02). Validação formal **`/qa validar R3` — APROVADA** (2026-06-02; `OccPanelTest` 25/25).
> review: senior+qa (severidade HIGH + cross-module Customers/Core/Ssh — obrigatório por `debugging-sistematico` Fase 4a)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F5.1 — [FIX] `JobTypeTranslator::cmdToCliArgv()` + `BlockedOnUpstreamException` | `vocabulary-translator` | — |
| [x] | P | F5.2 — [FIX] `LifecycleAsyncAction`: usar tradutor + remover `--async/--json` manual | `ssh-orchestrator` | F5.1 |
| [x] | M | F5.3 — [FIX] `CustomerLifecycleController`: 501 p/ `groups:add/remove` + `apps enable/disable` consolidado em CSV + email/groups via stdin | `laravel-api` | F5.2 |
| [x] | P | F5.4 — [FIX] `OccPanel.php` (Livewire): espelhar mudanças do controller (501 + CSV apps + stdin schema) | `laravel-livewire` | F5.3 |
| [x] | P | F5.5 — [FIX] Reescrever asserções de `LifecycleTest.php` para argv upstream-correto + adicionar pares cmd→argv em `JobTypeTranslatorTest` | `laravel-testing` | F5.2, F5.3 |
| [x] | P | F5.6 — [FIX] Docs: `docs/DECISION-BRIEF.md` (Decision #ARCH-4, 3º vocabulário) + `.cursor/skills/vocabulary-translator/SKILL.md` | `laravel-api` | F5.1 |
| [x] | M | F5.7 — [FIX] (opt-in) Teste de contrato SSH real contra cluster `homolog` (movido p/ `tests/Contract/`, flag `RUN_UPSTREAM_CONTRACT=1`) | `ssh-orchestrator` | F5.2 |
| [x] | P | F5.8 — [FIX-R1] Assert rollback de `IdempotencyKey` nos 3 testes SSH-failure + novo teste `SshConnectionException` em cluster ativo (QA-F5-017 HIGH + QA-F5-018 MEDIUM) | `laravel-testing` | F5.5 |
| [x] | P | F5.9 — [FIX-R1] Helper `assertNoUpstreamFlagDuplication` aplicado nos 4 endpoints + adicionar `email`/`groups` no stdin do `UpstreamContractTest` (QA-F5-005 ampliado + QA-F5-015 MEDIUM) | `laravel-testing` | F5.5 |
| [x] | M | F5.10 — [FIX-R1] `docs/openapi.yaml`: novo shape `apps/enable\|disable` + response 501 `groups/{g}/users` + criar `tests/Feature/Livewire/Customers/OccPanelTest.php` (CQ-F5-001 HIGH + QA-F5-016 MEDIUM) | `laravel-livewire` + docs | F5.3, F5.4 |
| [x] | M | F5.11 — [FIX-R2] Eliminar test/production divergence em `OccPanel::createUser`: refatorar blade para `<form wire:submit.prevent>` + `wire:model="userPasswordPlain"` em propriedade pública (sem `#[Locked]`), remover escape-hatch `?string $password` e fallback `request()->input()`; atualizar 4 testes Livewire para o same-path real; registrar ISSUE backlog para E2E Dusk/Playwright (QA-F5-019 HIGH) | `laravel-livewire` | F5.4, F5.10 |

> **R1 follow-up (2026-05-20T17:30Z)**: `/qa validar R1` resultou REPROVADA — 2 HIGH pendentes em sprint aberta (`CQ-F5-001` + `QA-F5-017`). Per PROC-012 + Hard Rule 5, F5.8–F5.10 corrigidos in-sprint (mesma branch `uds/fix/lifecycle-async-cmd-argv`).
> **R2 follow-up (2026-05-20T19:30Z)**: `/qa validar R2` resultou REPROVADA — 1 HIGH convergente (`QA-F5-019` OccPanel::createUser quebrado em produção; test/production divergence via escape-hatch). Decisão usuário: continuar in-sprint via task F5.11 (estratégia same-path, opção A do finding).
> **F5.11 implementado (2026-05-20T20:30Z)**: QA-F5-019 corrigido in-code; 323+ testes. Cleanup MEDIUM F5 (CQ-F5-002/003, QA-F5-006/008/010, CQ-F5-007) entregue na **Sprint F11** (2026-05-24).
> **Sync 2026-06-02**: Sprint **concluída** (11/11 tasks). **`/qa validar R3` formal concluída** — **APROVADA** (`QA-F5-019` validado; `OccPanelTest` 25/25). Backlog F5: 7 findings LOW/MEDIUM (test hygiene). E2E browser: **ISSUE-007**.

### Quality Brief (Sprint F5)

> PATTERN-001 (Decision #187): ao executar a auditoria de Quality Brief, o auditor-senior DEVE criar `docs/.briefs/F5.brief.md` como PRIMEIRO Write, antes de qualquer finding ou resumo. Sem este artefato, `.githooks/pre-commit` bloqueia o commit final da sprint.

### Contexto F5

Em 2026-05-20 06:00 UTC log de produção (`local.DEBUG: SSH command executed ... exit_code: 101 stdout: {"error":"cmd_not_allowed","cmd":"joao.silva"}`) revelou que a feature **lifecycle async de users/groups/apps** (`OccPanel` Livewire + `POST/DELETE /api/customers/{c}/users|groups|apps/*`) está completamente quebrada. Análise via `/qa debug` identificou dois bugs interagindo:

1. **Bug arquitetural** — vocabulário canônico-API (`users:create`) vazando direto no argv do `nextcloud-manage`. O upstream usa namespace hierárquico `user create` (per `SSH API Reference §3.3`), não `users:create`. Falta a camada de tradução **cmd canônico → CLI argv** (terceiro vocabulário do sistema; os outros dois — `cmd_canonical` ↔ `job_type` — têm tradutor: `JobTypeTranslator`).
2. **Bug mecânico** — `--async --json` duplicado: `LifecycleAsyncAction::execute()` adiciona manualmente e `SshClient::runAsync()` adiciona de novo. Os outros chamadores (`ProvisionCustomerAction`, `RemoveCustomerAction`) seguem o contrato correto.

**Por que os testes não pegaram**: `tests/Feature/Customers/LifecycleTest.php` valida `in_array('users:create', $args)` — asserta exatamente o vocabulário canônico-API no argv, ou seja, o comportamento bugado.

**Mapping fechado** (via SSH probing real contra `mecloud360@MECloud360-NextCloud-SaaS-01` v12.3.0):

| API canônica | CLI argv upstream | Status |
|---|---|---|
| `users:create` | `['user', 'create']` + `<username>` + `--payload-stdin {password, email?, groups?}` | ✅ |
| `users:delete` | `['user', 'remove']` + `<username>` (NÃO `user delete`) | ✅ |
| `groups:create` | `['group', 'create']` + `<groupname>` | ✅ |
| `groups:delete` | `['group', 'remove']` + `<groupname>` (NÃO `group delete`) | ✅ |
| `groups:add` | **blocked-on-upstream** — `group modify` upstream é rename, não membership; verbs `add-user`/`remove-user` retornam `not_implemented_yet ... D3/D4` | ❌ |
| `groups:remove` | **blocked-on-upstream** (idem) | ❌ |
| `apps:enable` | `['apps', 'enable']` + `<apps_csv>` (CSV nativo — substituir loop `dispatchMulti` por 1 job) | ✅ |
| `apps:disable` | `['apps', 'disable']` + `<apps_csv>` (idem) | ✅ |

### Riscos da Sprint F5

- **`group modify` schema desconhecido**: o upstream aceitou strings arbitrárias como `action` no probing (criou jobs queued que iriam falhar na execução). F5.3 retorna 501 explícito para `groups:add/remove` até `mework360-deployer-scripts` D3/D4 entregar o verb correto.
- **Schema do stdin de `user create`**: probing confirmou `--payload-stdin` obrigatório com `password`, mas não validamos se `email`/`groups` são aceitos como keys adicionais. F5.3 inicia com `{password, email?, groups?}` e ajusta após teste de integração F5.7 (se rodado).
- **Documentação upstream inconsistente**: `SSH API Reference §14` lista `user-create` (com hífen) que NÃO existe. O real é `user create` (espaço). Abrir issue no repo `mework360-deployer-scripts` como follow-up (fora desta sprint).

---

### Task F5.1 — [FIX] JobTypeTranslator::cmdToCliArgv() + BlockedOnUpstreamException

**Estado atual**: `JobTypeTranslator` mapeia `cmd_canonical` (ex.: `users:create`) ↔ `job_type` (ex.: `user_create`). Não há mapeamento para CLI argv upstream. `LifecycleAsyncAction::execute()` (linha 67-76) injeta `$cmd` cru no argv.

**Estado desejado**: `JobTypeTranslator` ganha método `cmdToCliArgv(string $cmd): array<string>` retornando tokens upstream (ex.: `['user', 'create']`). Para `groups:add`/`groups:remove` lança nova `BlockedOnUpstreamException` que controllers mapeiam para HTTP 501.

**Fonte(s)**: ISSUE-006 §"Causa raiz" + descobertas SSH probing
**Módulo(s) afetado(s)**: `app/Modules/Core/Translators/`, `app/Modules/Core/Translators/Exceptions/`
**Risco**: LOW — adição pura (não muda mapping existente)
**Budget**: P (2 arquivos: `JobTypeTranslator.php` edit + nova exception class)

**Correction (ANTES/DEPOIS)**:

```php
// ANTES — JobTypeTranslator.php
final class JobTypeTranslator
{
    private const CMD_TO_JOB_TYPE = [/* ... 15 verbs ... */];
    private const JOB_TYPE_TO_CMD = [/* flip ... */];

    public function cmdToJobType(string $cmd): string { /* ... */ }
    public function jobTypeToCmd(string $jobType): string { /* ... */ }
}

// DEPOIS — adicionar constante + método
private const CMD_TO_CLI_ARGV = [
    'create' => ['create'],
    'remove' => ['remove'],
    'backup' => ['backup'],
    'restore' => ['restore'],
    'update' => ['update'],
    'stop' => ['stop'],
    'start' => ['start'],
    'users:create' => ['user', 'create'],
    'users:delete' => ['user', 'remove'],   // NÃO 'user delete'
    'groups:create' => ['group', 'create'],
    'groups:delete' => ['group', 'remove'], // NÃO 'group delete'
    // 'groups:add' e 'groups:remove' INTENCIONALMENTE ausentes:
    //   upstream group modify atual é rename, não membership.
    //   Verbs add-user/remove-user retornam not_implemented_yet em v12.3.0.
    //   cmdToCliArgv() lança BlockedOnUpstreamException para esses cmds.
    'apps:enable' => ['apps', 'enable'],
    'apps:disable' => ['apps', 'disable'],
];

private const BLOCKED_ON_UPSTREAM = [
    'groups:add' => 'group_membership_add not implemented upstream (mework360-deployer-scripts D3/D4 pending)',
    'groups:remove' => 'group_membership_remove not implemented upstream (mework360-deployer-scripts D3/D4 pending)',
];

public function cmdToCliArgv(string $cmd): array
{
    if ($cmd === '') {
        throw new UnknownVerbException('Command cannot be empty');
    }
    if (isset(self::BLOCKED_ON_UPSTREAM[$cmd])) {
        throw new BlockedOnUpstreamException(self::BLOCKED_ON_UPSTREAM[$cmd], cmd: $cmd);
    }
    return self::CMD_TO_CLI_ARGV[$cmd]
        ?? throw new UnknownVerbException(
            "Unknown cmd: '{$cmd}'. Update CMD_TO_CLI_ARGV mapping to register new verbs."
        );
}
```

```php
// NOVO — app/Modules/Core/Translators/Exceptions/BlockedOnUpstreamException.php
namespace App\Modules\Core\Translators\Exceptions;

final class BlockedOnUpstreamException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $cmd)
    {
        parent::__construct($message);
    }
}
```

**Test**:
- `JobTypeTranslatorTest::cmdToCliArgv_maps_all_canonical_verbs()` — dataset com 12 pares (todos exceto os 2 blocked).
- `JobTypeTranslatorTest::cmdToCliArgv_throws_BlockedOnUpstream_for_groups_membership()` — `groups:add` e `groups:remove`.
- `JobTypeTranslatorTest::cmdToCliArgv_throws_UnknownVerb_for_unmapped()` — `'inexistente:x'`.

---

### Task F5.2 — [FIX] LifecycleAsyncAction: usar tradutor + remover --async/--json manual

**Estado atual**: `LifecycleAsyncAction::execute()` (linha 67-76) faz `array_merge([$customer->slug, $cmd], $args, ['--async', '--json', ...])`. `SshClient::runAsync()` (linha 69) também faz `array_merge($args, ['--async', '--json'])` → flags duplicadas no argv final.

**Estado desejado**: `array_merge([$customer->slug, ...$translator->cmdToCliArgv($cmd)], $args, ['--idempotency-key=...', '--callback=...'])`. As flags `--async --json` ficam por conta do `SshClient::runAsync` (delegação correta, consistente com `ProvisionCustomerAction` e `RemoveCustomerAction`).

**Fonte(s)**: ISSUE-006 §"Bug A" + §"Bug B"
**Módulo(s) afetado(s)**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
**Risco**: LOW — mudança cirúrgica em 1 arquivo; coberta por testes existentes (após F5.5 reescrever asserções)
**Budget**: P (1 arquivo + branch handling de `BlockedOnUpstreamException`)

**Correction (ANTES/DEPOIS)**:

```php
// ANTES — LifecycleAsyncAction.php linhas 67-76
$sshArgs = array_merge(
    [$customer->slug, $cmd],
    $args,
    [
        '--async',
        '--json',
        "--idempotency-key={$idempotencyKey}",
        "--callback={$callbackUrl}",
    ],
);

// DEPOIS
$sshArgs = array_merge(
    [$customer->slug, ...$this->translator->cmdToCliArgv($cmd)],
    $args,
    [
        "--idempotency-key={$idempotencyKey}",
        "--callback={$callbackUrl}",
    ],
);
```

`BlockedOnUpstreamException` propaga naturalmente (controllers a tratam — ver F5.3).

**Test**:
- `LifecycleAsyncActionTest::execute_sends_user_create_argv_not_users_colon_create()` — mock recebe `args` contendo `'user'` e `'create'` consecutivos; NÃO contém `users:create`.
- `LifecycleAsyncActionTest::execute_does_not_duplicate_async_json_flags()` — count de `'--async'` em `$args` recebido pelo mock = 0 (SshClient adiciona, action não).
- `LifecycleAsyncActionTest::execute_propagates_BlockedOnUpstreamException()` — chamada com `cmd='groups:add'` lança exception sem disparar SSH.

---

### Task F5.3 — [FIX] CustomerLifecycleController: 501 + CSV consolidado + email/groups via stdin

> **Esta é a única task M (Moderate) da sprint.** Decisões de design: schema do stdin payload, contrato HTTP 501, consolidação de apps.

**Estado atual**:
1. `createUser()` passa email como segundo positional e `--group={g}` como flag CLI — falha silenciosamente (upstream `user create` aceita só `<username>` positional + `--payload-stdin`).
2. `dispatchMulti()` (enable/disable apps) faz loop disparando N jobs (1 por app) — ineficiente; upstream aceita CSV em 1 chamada.
3. `addUserToGroup()`/`removeUserFromGroup()` chamam `LifecycleAsyncAction` que vai bater com `BlockedOnUpstreamException` (F5.1).

**Estado desejado**:
1. `createUser()` passa **só `<username>` como positional**; email/groups vão no stdin payload JSON `{password, email?, groups?}`.
2. `enableApps()`/`disableApps()` consolidam em **1 chamada com CSV**: `LifecycleAsyncAction::execute($customer, 'apps:enable', [implode(',', $apps)], null, $actor)`.
3. `addUserToGroup()`/`removeUserFromGroup()` capturam `BlockedOnUpstreamException` → retornam **HTTP 501** com `{"error":"not_implemented_yet","reason":"upstream group membership pending D3/D4","cmd":"groups:add"}`.

**Fonte(s)**: ISSUE-006 §"Design points" DP1, DP2, DP3
**Módulo(s) afetado(s)**: `app/Http/Controllers/Api/CustomerLifecycleController.php`
**Risco**: MEDIUM — 4 endpoints mudam comportamento; testes existentes em `LifecycleTest.php` precisam adaptação (F5.5)
**Budget**: M (1 arquivo de produção + decisões de design + 3 endpoints com mudança de contrato)

**Decisões pendentes a confirmar dentro da sprint** (gather inicial obrigatório):
- Schema final do stdin de `user create`: confirmar se upstream aceita `{password, email, groups}` num único `--payload-stdin` ou se precisa multistep (criar + setar email + add em groups). Confirmar com 1 chamada SSH real antes de codificar.
- Formato exato do 501 response (alinhar com padrão de errors do projeto: ver `cluster_unreachable`, `lifecycle_timeout`, etc).

**executor_prompt**:
```
Feature: corrigir 3 contratos do CustomerLifecycleController após mapping cmd → CLI argv upstream
ficar disponível em F5.1/F5.2 (JobTypeTranslator::cmdToCliArgv + BlockedOnUpstreamException +
LifecycleAsyncAction refatorado).

Contexto do sistema:
- API Laravel orquestradora. Controller em app/Http/Controllers/Api/CustomerLifecycleController.php.
- 8 verbs lifecycle: users:create/delete, groups:create/delete/add/remove, apps:enable/disable.
- Upstream nextcloud-saas-manager v12.3.0 confirmou via SSH probing (cluster homolog, host
  dev.mework360.com.br) que:
  * user create exige --payload-stdin com password; positional apenas <username>.
  * apps enable/disable aceita <apps_csv> em 1 job único (não N jobs).
  * group modify atual é rename, não membership; group add-user/remove-user retornam
    not_implemented_yet (D3/D4 pending).
- Tradutor cmdToCliArgv lança BlockedOnUpstreamException para groups:add/groups:remove (F5.1).

Tasks deste arquivo (1 controller):

1. createUser(Customer, CreateUserRequest) — ANTES passa email como positional + --group=X.
   DEPOIS: positional apenas [$username]; stdin payload = ['password' => ..., 'email' => ...?, 'groups' => [...]?].
   PRÉ-REQUISITO: confirmar com 1 chamada SSH real (sudo nextcloud-manage <client> user create
   joao.silva --async --json --payload-stdin com payload {"password":"...","email":"...","groups":["editors"]})
   se upstream aceita keys extras no payload. Se aceitar → schema final {password, email?, groups?}.
   Se rejeitar → implementar como multistep follow-up (criar TODO; F5 entrega só com password).

2. enableApps(Customer, EnableAppsRequest) e disableApps(...):
   ANTES: dispatchMulti() faz loop chamando action->execute() por app.
   DEPOIS: 1 chamada única com CSV — action->execute($customer, 'apps:enable', [implode(',', $apps)], null, $actor)
   retornando 1 job_id. Endpoint retorna 202 + {"job_id": "...", "apps_csv": "a,b,c"}.
   dispatchMulti() pode ser removido (não usado em outro lugar).

3. addUserToGroup(Customer, AddUserToGroupRequest), removeUserFromGroup(...):
   ANTES: chama dispatch() com 'groups:add'/'groups:remove' — vai propagar exceção via LifecycleAsyncAction.
   DEPOIS: try/catch BlockedOnUpstreamException → response()->json([
     'error' => 'not_implemented_yet',
     'reason' => 'upstream group membership pending mework360-deployer-scripts D3/D4',
     'cmd' => $e->cmd,
   ], 501);
   Manter no dispatch() do helper genérico (não inline) — ele já chama action->execute que vai
   propagar a exceção. Capturar no dispatch() junto com as outras (ClusterUnreachable, etc).

Critério de pronto:
- createUser usa stdin payload schema (esquema confirmado por SSH real)
- enableApps/disableApps consolidam em 1 job com CSV (testes refletem)
- addUserToGroup/removeUserFromGroup retornam 501 com payload estruturado
- Sem regressão em deleteUser, createGroup, deleteGroup (continuam funcionando após F5.2)
- IDE lint clean, php-cs-fixer aplicado

IMPORTANTE — Quality Brief (PATTERN-001 / Decision #187):
auditor-senior cria docs/.briefs/F5.brief.md como PRIMEIRO Write.
```

**Test**:
- `CustomerLifecycleControllerTest::create_user_passes_email_groups_via_stdin()` — `$stdin` recebido pelo mock é JSON com keys `password` + (opcional `email`, `groups`).
- `CustomerLifecycleControllerTest::enable_apps_consolidates_into_single_csv_job()` — 3 apps → mock `runAsync` chamado **1x** com arg contendo `'app1,app2,app3'`.
- `CustomerLifecycleControllerTest::add_user_to_group_returns_501_blocked_on_upstream()` — `POST /api/customers/{c}/groups/editors/users` → status 501, `error: not_implemented_yet`.
- Mesmo para `remove_user_from_group`.

---

### Task F5.4 — [FIX] OccPanel.php (Livewire): espelhar mudanças do controller

**Estado atual**: `OccPanel::createUser()`, `addUserToGroup()` etc. usam o mesmo `LifecycleAsyncAction` e têm os mesmos bugs do controller. UX hoje: form OCC parece submeter mas job falha silenciosamente upstream.

**Estado desejado**: espelhar contratos de F5.3 — `createUser()` move email/groups para stdin payload; `addUserToGroup()`/`removeUserFromGroup()` exibem mensagem amigável "Funcionalidade pendente no upstream — disponível em release futura" (em vez de 500/exception); `submitApp()` (sync individual via `app:enable` OCC) NÃO MUDA — esse é o caminho síncrono via `OccPassthroughService`, fora do lifecycle async corrigido.

**Fonte(s)**: ISSUE-006 §"Design points"
**Módulo(s) afetado(s)**: `app/Http/Livewire/Customers/OccPanel.php`
**Risco**: LOW — espelho do controller; mudança LIVRE de Livewire (sem mudança de view/blade)
**Budget**: P (1 arquivo; ~3 métodos tocados)

**Correction**:

```php
// createUser() — args agora puramente username; resto vai no stdin
$args = [$this->userUsername];
$stdin = array_filter([
    'password' => $password,
    'email' => $this->userEmail ?: null,
    'groups' => array_values(array_filter(array_map('trim', explode(',', $this->userGroups)))) ?: null,
]);
$job = $action->execute($this->customer, 'users:create', $args, $stdin, $actor);
```

```php
// addUserToGroup() — capturar BlockedOnUpstreamException
try {
    $job = $action->execute($this->customer, 'groups:add', [...], null, $actor);
} catch (BlockedOnUpstreamException) {
    $this->errorMessage = 'Funcionalidade pendente no upstream — disponível em release futura.';
    return;
}
```

**Test**:
- `OccPanelTest::create_user_via_livewire_uses_stdin_payload()` — chamada via `Livewire::test()->call('createUser')` → mock recebe stdin com keys corretos; argv positional só com username.
- `OccPanelTest::add_user_to_group_shows_blocked_message()` — chamada → `errorMessage === 'Funcionalidade pendente no upstream...'`.

---

### Task F5.5 — [FIX] Reescrever asserções de teste

**Estado atual**: `tests/Feature/Customers/LifecycleTest.php` valida `in_array('users:create', $args, true)` em 6+ lugares (linhas 59, 155, 179, 337, 373, 410) — assertion simétrica ao bug. `JobTypeTranslatorTest.php` cobre só cmd↔job_type, não cmd→argv.

**Estado desejado**:
- `LifecycleTest.php`: asserções comparam contra **argv upstream-correto** (`['user', 'create']`, `['group', 'remove']`, etc.) usando o helper de match consecutivo (pares de tokens).
- Asserir ausência de `--async --json` duplicado: `count(array_filter($args, fn($a) => $a === '--async')) === 0` (caller não duplica; SshClient adiciona).
- `JobTypeTranslatorTest.php`: novo grupo `cmdToCliArgv` cobrindo 12 pares válidos + 2 blocked + 1 unknown.

**Fonte(s)**: ISSUE-006 §"Por que os testes não pegaram"
**Módulo(s) afetado(s)**: `tests/Feature/Customers/LifecycleTest.php`, `tests/Unit/Core/JobTypeTranslatorTest.php`
**Risco**: LOW — testes; verde se F5.1+F5.2+F5.3 corretas
**Budget**: P (2 arquivos de teste; ~12 asserções para revisar)

**Correction (helper sugerido para LifecycleTest)**:

```php
function argsContainConsecutive(array $args, array $sequence): bool
{
    $n = count($sequence);
    for ($i = 0; $i <= count($args) - $n; $i++) {
        if (array_slice($args, $i, $n) === $sequence) return true;
    }
    return false;
}

// ANTES:
// ->withArgs(fn ($c, $cmd, $args, $stdin) => in_array('users:create', $args, true) && ...)

// DEPOIS:
// ->withArgs(fn ($c, $cmd, $args, $stdin) =>
//     argsContainConsecutive($args, ['user', 'create'])
//     && !in_array('--async', $args, true)  // SshClient adiciona, action não
//     && str_contains($stdin ?? '', '"password"')
// )
```

**Test** (esta task PRODUZ testes; gate = todos os 230+ testes passando após F5.1-F5.4 + estes):
- Suite completa passa: `php artisan test` ou `pest`
- Nenhum teste mock asserting `in_array('users:create', $args)` (grep ZERO matches).

---

### Task F5.6 — [FIX] Docs: SETUP-DECISIONS + skill vocabulary-translator

**Estado atual**: `docs/SETUP-DECISIONS.md` não documenta o terceiro vocabulário. `.cursor/skills/vocabulary-translator/SKILL.md` lista 2 tradutores (state, jobtype) mas não menciona cmd→argv. Skill tem `references/` inexistente (vide `ls` durante o debug).

**Estado desejado**:
- `SETUP-DECISIONS.md`: nova decisão (próximo número livre) — "3 vocabulários: cmd_canonical (interno), job_type (webhook), CLI argv (upstream); JobTypeTranslator agrega 2 dos 3 mappings; cmd↔argv é o gap fechado em F5".
- `vocabulary-translator/SKILL.md`: adicionar 4º item em Main Flow ("Tradução cmd → CLI argv") + atualizar Rules ("3 vocabulários, não 2").

**Fonte(s)**: ISSUE-006 §"Próximo passo" item docs
**Módulo(s) afetado(s)**: `docs/SETUP-DECISIONS.md`, `.cursor/skills/vocabulary-translator/SKILL.md`
**Risco**: LOW — docs
**Budget**: P (2 arquivos markdown)

**Test**: revisão manual no PR (auditor-senior valida que docs refletem F5.1).

---

### Task F5.7 — [FIX] (opt-in) Teste de contrato SSH real contra cluster homolog

**Estado atual**: nenhum teste de contrato real — toda integração SSH é mockada. Bug A passou despercebido porque mock validava argv canônico-API ao invés do upstream-correto.

**Estado desejado**: 1 teste por categoria de verb (criar usuário, deletar usuário, criar grupo, deletar grupo, enable app, disable app) que dispara SSH real contra cluster `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`, host `dev.mework360.com.br`) e valida:
- `exit_code === 0`
- `parsedJson['job_id']` UUID v4 válido
- `parsedJson['state'] === 'queued'`

Atrás de env flag (`RUN_UPSTREAM_CONTRACT=1`) — não roda em CI default, só em validação manual pré-merge.

**Fonte(s)**: ISSUE-006 §"Riscos descobertos" item 3
**Módulo(s) afetado(s)**: `tests/Feature/Customers/UpstreamContractTest.php` (NOVO)
**Risco**: MEDIUM — cria jobs reais no cluster homolog (pode poluir `jobs` table upstream; usar slug de teste descartável, ex.: `slug='qa-f5-contract'`)
**Budget**: M (1 arquivo de teste + env flag setup + cleanup de jobs criados)

**executor_prompt**:
```
Feature: teste de contrato SSH real contra cluster homolog para validar mapping cmd → CLI argv
após F5.1-F5.6. Previne regressão do bug F5 (vocabulário canônico-API vazando no argv upstream).

Contexto:
- Atrás de flag de env: RUN_UPSTREAM_CONTRACT=1. Default: skip.
- Cluster homolog em DB: 119d74df-9011-4c0f-a6bf-ad03f84af10d.
- Slug de teste descartável (não usar slug real): 'qa-f5-contract' (criar setup/teardown).
- LifecycleAsyncAction usa SshClient real (NÃO mockar).
- Após cada teste, opcionalmente cancelar o job criado upstream via job cancel (cleanup).

Tasks:
1. tests/Feature/Customers/UpstreamContractTest.php:
   - beforeAll: criar Customer com slug='qa-f5-contract', cluster=homolog
   - it('user create dispara job upstream com exit 0', função): chama LifecycleAsyncAction->execute(
     $customer, 'users:create', ['joao-test'], ['password' => '...']); asserta $job->job_id é UUID,
     $job->state === 'queued'.
   - Mesmo para user remove, group create, group remove, apps enable, apps disable.
   - skip(!env('RUN_UPSTREAM_CONTRACT')) em todos.
   - afterAll: cleanup do customer + tentar cancelar jobs criados.

2. .github/workflows/upstream-contract.yml (NOVO, opcional):
   - Workflow manual (workflow_dispatch) que roda RUN_UPSTREAM_CONTRACT=1.
   - Configura secrets SSH para cluster homolog.

Critério de pronto:
- Suite normal (sem flag) → testes pulam.
- RUN_UPSTREAM_CONTRACT=1 php artisan test --filter=UpstreamContractTest → todos passam contra cluster homolog real.
- Cleanup deixa cluster sem rastro de testes (slug qa-f5-contract removido).
```

**Test**: o próprio. Validação manual pré-merge da F5.

---

### Task F5.8 — [FIX-R1] Rollback de IdempotencyKey + path negativo SshConnectionException

> **Re-validação R1 follow-up.** Resolve `QA-F5-017` (HIGH — weak invariant em 3 testes SSH-failure) e `QA-F5-018` (MEDIUM — path negativo SshConnectionException em cluster ativo).

**Estado atual**: `LifecycleAsyncAction::execute()` linhas 108-117 têm 3 catch blocks que deletam explicitamente a `IdempotencyKey` antes de re-throw (contrato defensivo deliberado para permitir retry). Testes em `LifecycleTest.php:415-446, 584-599` (SSH exit 4 → 409, exit 22 → 422, timeout → 504) asseram apenas HTTP status + JSON path, **sem verificar estado final do banco**. Adicionalmente, o teste `cluster offline → 503` (linha 392-413) usa cluster `status=unreachable` que dispara guard preemptiva — **nunca exercita** a catch block `SshConnectionException → ClusterUnreachableException` (linha 108-110) em cluster ativo.

**Estado desejado**:
- 3 testes existentes ganham `expect(IdempotencyKey::where('cmd', $cmd)->count())->toBe(0)` após `assertStatus()`.
- Teste de timeout adicionalmente verifica `Job::count() === 0` + `AuditLog::where('action', $cmd.'_initiated')->count() === 0` (padrão estabelecido em QA-F5-004).
- Novo teste `SshConnectionException em cluster ativo → 503 cluster_unreachable` em `LifecycleTest.php`, espelhando o cenário real de produção (cluster active + SSH cai em runtime).

**Fonte(s)**: FINDINGS QA-F5-017 (HIGH), QA-F5-018 (MEDIUM)
**Módulo(s) afetado(s)**: `tests/Feature/Customers/LifecycleTest.php` (3 testes alterados + 1 teste novo)
**Risco**: LOW — só testes; reforça invariantes já implementadas
**Budget**: P (~25min, 3 expect chains + 1 teste novo de ~20 LoC)

**Test**: a própria task. Gate = `php artisan test --filter=LifecycleTest` verde + grep para confirmar que cada um dos 4 cenários (exit 4, exit 22, timeout, SshConnectionException) tem `IdempotencyKey` assertion.

---

### Task F5.9 — [FIX-R1] Helper anti-duplicação de flags upstream + stdin estendido no Contract test

> **Re-validação R1 follow-up.** Resolve `QA-F5-005` ampliado (MEDIUM — bug-B guards incompletos em 4 endpoints) e `QA-F5-015` (MEDIUM — Contract test não exercita `email`/`groups`).

**Estado atual**:
1. `LifecycleTest.php` tem guard completo `! in_array('--async') && ! in_array('--json')` apenas em 3 testes (`POST users`, `POST apps/disable` single, `POST apps/disable` 3 apps). Os 4 demais endpoints têm guard parcial ou ausente:
   - `POST groups` (linha 235-251): tem `! --async`, **falta `! --json`**
   - `DELETE users/{username}` (linha 255-277): **falta ambos**
   - `DELETE groups/{group}` (linha 473-493): **falta ambos**
   - `POST apps/enable` (linha 295-319): **falta ambos**
2. `tests/Contract/Customers/UpstreamContractTest.php:90-96` cenário `users:create` injeta apenas `['password' => '...']` no stdin — não valida que upstream `nextcloud-manage` aceita o JSON estendido `{password, email?, groups?}` introduzido em F5.3.

**Estado desejado**:
- Helper reusável `assertNoUpstreamFlagDuplication(array $args, string $cmd): void` em `tests/Pest.php` (ou em concern dedicado): verifica `! in_array('--async')`, `! in_array('--json')` e `! in_array($cmd, ...)` para bug-symmetry.
- Helper chamado em todos os 7 testes de `LifecycleTest.php` que validam `withArgs` (users:create, users:delete, groups:create, groups:delete, apps:enable, apps:disable single, apps:disable 3 apps).
- `UpstreamContractTest.php` cenário `user create`: adicionar `'email' => 'qa-contract@example.com', 'groups' => ['editors']` ao stdin payload; assertar `$job->state === 'queued'` (não falhou no parse upstream).

**Fonte(s)**: FINDINGS QA-F5-005 (MEDIUM, ampliado em R1), QA-F5-015 (MEDIUM)
**Módulo(s) afetado(s)**: `tests/Pest.php`, `tests/Feature/Customers/LifecycleTest.php`, `tests/Contract/Customers/UpstreamContractTest.php`
**Risco**: LOW — testes; helper centralizado evita drift futuro
**Budget**: P (~30min, 1 helper + aplicação em 7 testes + extensão do Contract test)

**Test**: a própria task. Gate = `rg "in_array\('--async'" tests/` retorna apenas chamadas dentro do helper + `php artisan test --filter=LifecycleTest` verde + grep mostra `assertNoUpstreamFlagDuplication` em 7 lugares.

---

### Task F5.10 — [FIX-R1] OpenAPI yaml apps/* shape + 501 groups/{g}/users + OccPanelTest

> **Esta é a única task M (Moderate) da rodada R1.** Re-validação R1 follow-up. Resolve `CQ-F5-001` (HIGH — OpenAPI drift bloqueando aprovação) e `QA-F5-016` (MEDIUM — OccPanel sem nenhum teste).

**Estado atual**:
1. `docs/openapi.yaml` descreve `POST /customers/{customer}/apps/enable|disable` retornando `$ref: '#/components/responses/JobAccepted'`. A implementação F5.3 retorna shape flat `{job_id, apps_csv}` em 202 e `{error, exit_code, apps_csv}` em 502. A Sprint F5.3 também introduziu HTTP 501 (`{error: not_implemented_yet, reason, cmd}`) para `POST/DELETE /customers/{customer}/groups/{group}/users` — não documentado em OpenAPI.
2. `app/Http/Livewire/Customers/OccPanel.php` (385 LoC, 8 actions) não tem **nenhum teste de feature**. F5.4 alterou stdin payload em `createUser` e `formatError(BlockedOnUpstreamException)` em `addUserToGroup`/`removeUserFromGroup` — sem regressão guard.

**Estado desejado**:
1. `docs/openapi.yaml`:
   - Substituir `$ref: JobAccepted` nas rotas `apps/enable` e `apps/disable` por inline schema `{job_id: uuid, apps_csv: string}` (202) + ErrorResponse com `exit_code` e `apps_csv` (502).
   - Adicionar response 501 `{error: not_implemented_yet, reason: string, cmd: string}` em `POST /customers/{customer}/groups/{group}/users` e `DELETE /customers/{customer}/groups/{group}/users/{username}`.
   - Bump `info.version` (0.5.0 → 0.6.0) + entrada em `docs/CHANGELOG.md`.
2. Novo `tests/Feature/Livewire/Customers/OccPanelTest.php` cobrindo:
   - **Happy path** para cada action: `submitQuota`, `submitBranding`, `submitMaintenance`, `createUser`, `deleteUser`, `submitApp`, `submitAppsBulk`.
   - **Blocked-on-upstream** para `addUserToGroup`/`removeUserFromGroup` → `assertSet('errorMessage', 'Funcionalidade pendente no upstream — disponível em release futura.')` (resolve `CQ-F5-007` LOW de quebra).
   - **Error mapping** para `BlockedOnUpstreamException`, `IdempotencyConflictException`, `SshTimeoutException` → `errorMessage` amigável.
   - **Autorização**: operador sem `provision-customers` → 403.

**Fonte(s)**: FINDINGS CQ-F5-001 (HIGH), QA-F5-016 (MEDIUM)
**Módulo(s) afetado(s)**: `docs/openapi.yaml`, `docs/CHANGELOG.md`, `tests/Feature/Livewire/Customers/OccPanelTest.php` (novo arquivo)
**Risco**: MEDIUM — OpenAPI tem consumidores potenciais (clientes externos); OccPanelTest cobre 8 actions com 10-15 testes
**Budget**: M (~2-3h: OpenAPI ~30min + OccPanelTest ~2h)

**Test**: a própria task. Gate = `redocly lint docs/openapi.yaml` 0 errors + `php artisan test --filter=OccPanelTest` verde + grep confirma que `OccPanel.php` tem coverage path-por-path (8 actions × pelo menos 1 happy + 1 error).

---

### Task F5.11 — [FIX-R2] Eliminar test/production divergence em OccPanel::createUser

> **Re-validação R2 follow-up.** Resolve `QA-F5-019` (HIGH — `OccPanel::createUser` quebrado em produção; cobertura R1 falso-positiva via escape-hatch).

**Estado atual**:
- View (`resources/views/livewire/customers/occ-panel.blade.php:178-204`) usa `<input type="password" name="password">` (sem `wire:model`) e botão `wire:click="createUser"` (sem `wire:submit`). O payload Livewire JSON enviado a `/livewire/update` **não inclui** inputs sem `wire:model` — apenas o snapshot do componente. Resultado: senha digitada nunca chega ao back-end.
- Componente (`app/Http/Livewire/Customers/OccPanel.php:214-268`) tem `#[Locked] public string $userPassword = '';` (nunca usado) + parâmetro opcional `?string $password = null` + fallback `request()->input('password', '')`. O fallback é inútil porque o payload não traz `password`. O método sempre falha com `strlen('') < 8` em produção real.
- Testes (`tests/Feature/Livewire/Customers/OccPanelTest.php:266,293,306,321`) usam `->call('createUser', 'Secret123!')` — exercitam apenas o ramo do parâmetro (escape-hatch), **nunca** o ramo `request()->input` que é o único que produção tenta usar. False-positive coverage; bug pré-existente em main desde F2.5, mas mascarado pelos 19 testes R1 follow-up.

**Estado desejado** (estratégia same-path):
- Blade `occ-panel.blade.php` (seção "Criar Usuário"): envolver os 4 form-groups em `<form wire:submit.prevent="createUser">`, trocar `<input type="password" name="password">` por `<input type="password" wire:model="userPasswordPlain">`, trocar `<button wire:click="createUser">` por `<button type="submit">`. As demais ações (`deleteUser`, `createGroup`, etc.) ficam intactas — escopo cirúrgico.
- `OccPanel.php`: substituir `#[Locked] public string $userPassword = '';` por `public string $userPasswordPlain = '';` (sem `#[Locked]` — o snapshot pode carregar a senha enquanto o usuário digita; é o mesmo modelo de qualquer formulário HTML, protegido por HTTPS + CSRF). Remover parâmetro `?string $password = null` e fallback `request()->input(...)`. Ler senha diretamente de `$this->userPasswordPlain`. Em `finally`, zerar `$this->userPasswordPlain = ''` para limpar o snapshot pós-uso.
- Testes (`OccPanelTest.php`): trocar `->call('createUser', '...')` por `->set('userPasswordPlain', '...')->call('createUser')` nos 4 testes existentes — agora exercitam o **mesmo path** que produção (set propriedade pública = simulação fiel do `wire:model`). Adicionar 2 novos testes:
  - **Production scenario** (regressão guard): `createUser` sem `set('userPasswordPlain')` → `assertHasErrors(['userPassword'])` (replica o cenário do bug original).
  - **Cleanup**: após `createUser` bem-sucedido, `$userPasswordPlain` é `''` (verifica `assertSet('userPasswordPlain', '')`).
- ISSUE backlog (`docs/ISSUES.md`): registrar "E2E real coverage via Dusk/Playwright" para uma sprint N-UI dedicada (cobre o wire:submit/click via navegador real; fora do budget M desta task).

**Fonte(s)**: FINDINGS `QA-F5-019` (HIGH, convergente entre auditor-senior R2 claude-4.6-sonnet + auditor-qa R2 gemini-3.1-pro)
**Módulo(s) afetado(s)**: `app/Http/Livewire/Customers/OccPanel.php`, `resources/views/livewire/customers/occ-panel.blade.php`, `tests/Feature/Livewire/Customers/OccPanelTest.php`, `docs/ISSUES.md`
**Risco**: LOW — wire:model é o padrão Livewire; refactor de teste é mecânico; backlog cobre o gap de browser real
**Budget**: M (~30-60min: blade + componente + 6 testes + ISSUE + FINDINGS update)

**Test**: a própria task. Gate = `docker compose exec app php artisan test --filter=OccPanelTest` verde + grep `rg "->call\('createUser'" tests/Feature/Livewire/Customers/OccPanelTest.php` mostra 0 ocorrências de escape-hatch (todos os calls passam pelo set-prop pattern) + suite global ≥ 321 passed (mantida ou aumentada com 2 novos testes).


---

## Sprint F6 — Forgot Password (operadores) + Logs de Job populados via SSH

> Categoria: F
> Gate: (1) operador clica "Esqueci minha senha" em `/login` → recebe email com URL assinada → define nova senha → loga; (2) job `success`/`failed`/`cancelled` recebido via webhook resulta em `jobs.summary` populado com linhas do log upstream (`nextcloud-manage <client> job <id> logs --json`, fallback `job <id> status --json`); (3) `/queue/{jobId}` exibe linhas coloridas em vez de "Nenhum log disponível"; (4) suite ≥ 325 testes verde (323 atual + ≥2 novos por feature).
> Gerado por `/fix` em 2026-05-21. Fonte: ISSUE-008 (MEDIUM) + ISSUE-009 (HIGH) — ambas originadas de `/qa debug` mesma data.
> review: senior+qa (severidade HIGH presente — obrigatório por `debugging-sistematico` Fase 4a)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F6.1 — [ISSUE-008] Migration `password_reset_tokens` + binding broker `operators` em `config/auth.php` | `laravel-migration` | — |
| [x] | M | F6.2 — [ISSUE-008] Livewire `Auth/ForgotPassword` + `Auth/ResetPassword` + rotas + mailable + view de email + link em `login.blade.php` + rate-limit + audit | `laravel-livewire` | F6.1 |
| [x] | P | F6.3 — [ISSUE-009] `WebhookPayload`: tolerar campo opcional `log_tail` (vem do contrato futuro do upstream; aceitar como hint); refatorar `WebhookHandler` para chamar `JobLogFetcher` pós-`applyFinishedEvent()` | `webhook-receiver` | — |
| [x] | M | F6.4 — [ISSUE-009] Service `App\Modules\Jobs\Services\JobLogFetcher` injetando `SshClientInterface` (timeout, idempotência, sanitização de secrets, fallback `job status --json` se `job logs --json` retornar 99=`not_implemented`) | `ssh-orchestrator` | F6.3 |
| [x] | P | F6.5 — [ISSUE-009] Pest Feature tests: webhook `job.finished` mockado → `summary` populada; idempotência (`summary` já cheia → skip); falha SSH → `Log::warning` + `summary` permanece null (não quebra webhook) | `laravel-testing` | F6.4 |
| [x] | P | F6.6 — [ISSUE-009] Pest Contract test (opt-in `RUN_UPSTREAM_CONTRACT=1`): SSH real contra cluster `homolog` valida formato do output de `job <id> logs --json` ou `job <id> status --json` | `ssh-orchestrator` + `laravel-testing` | F6.4 |

> **Quality Brief (Sprint F6)**: PATTERN-001 (Decision #187) — auditor-senior DEVE criar `docs/.briefs/F6.brief.md` como PRIMEIRO Write na auditoria final, antes de qualquer finding.

### Contexto F6

Em 2026-05-21, `/qa debug` levantou 5 itens. 3 foram resolvidos via Fast-Track (sidebar sem `@can`, menu Clientes ausente, link Webhook IPs ausente) na branch `fix/sidebar-permissions-and-missing-links` — não entram nesta sprint. Os 2 que exigem TDD + design entram aqui:

**ISSUE-008 (Forgot Password)** — Tela `/login` (`resources/views/livewire/auth/login.blade.php`) não tem link para recuperar senha. Verificado: 0 rotas/components/mailables de password reset existem (`grep password.request|forgot|recuperar` → só docs/config padrão). Não é só adicionar um `<a>` — é construir o fluxo completo.

**ISSUE-009 (Logs de Job)** — `/queue/{jobId}` (`resources/views/livewire/jobs/show.blade.php`) renderiza `$logLines` de `Job::$summary` (JSON cast). Confirmado:

- `app/Modules/Jobs/Services/WebhookHandler::applyFinishedEvent()` nunca toca `summary`.
- `app/Modules/Jobs/Dto/WebhookPayload::fromArray()` não lê `summary`/`log_tail`/`stdout`.
- **Errata E7** da ROADMAP confirma: "Campo `summary` não existe no payload webhook (existe apenas em `job status`)".

Resultado: 100% dos jobs exibem "Nenhum log disponível" em produção.

### Riscos da Sprint F6

- **Comando SSH `nextcloud-manage <client> job <id> logs --json` pode não existir no upstream** — `SSH API Reference §7` documenta `job status` como o único endpoint que carrega `summary`. F6.4 deve probar contra `homolog` ANTES de codar (fast-fail), com fallback automático para `job <id> status --json` se exit_code=99. Documentar mapping em `JobLogFetcher` similar a `JobTypeTranslator`.
- **Vazamento de secrets em logs** — output upstream pode conter `--password-stdin`, `--token=*`, `password=*`. Sanitizar via regex similar a `Job::$payload_sanitized` ANTES de persistir. Test obrigatório com fixture contendo `password=segredo123` → asserta `[REDACTED]` no `summary` final.
- **Latência do callback** — fetch SSH adiciona ~200-800ms ao processamento do webhook `job.finished`. Atualmente o handler retorna `204` em <50ms. Decisão: aceitar o overhead (webhook é fire-and-forget assíncrono do lado upstream); medir via `Log::info('jobs.log_fetch.duration_ms', [...])`.
- **Enumeração de e-mail no forgot-password** — `Password::sendResetLink()` retorna `INVALID_USER` quando o e-mail não existe. Front-end DEVE retornar mensagem genérica ("se o e-mail existir, enviaremos instruções") em todos os outcomes. Test obrigatório: 3 cenários (email válido + ativo, email válido + inactive, email inexistente) → todos exibem a mesma string.
- **Operador `status != active`** — bloquear silenciosamente o envio de email, logar audit `password_reset_blocked` com `actor_id=null` + `payload.email=<hash>`.

### Tarefas detalhadas

---

### Task F6.1 — [ISSUE-008] Migration password_reset_tokens + broker config

**Estado atual**: `config/auth.php` tem provider `operators` mas seção `passwords` está padrão (`provider: 'users'`). Tabela `password_reset_tokens` não existe nas migrations.

**Estado desejado**: migration cria tabela padrão do Laravel + `config/auth.php` ganha broker `operators` (provider=`operators`, table=`password_reset_tokens`, expire=60min, throttle=60s).

**Fonte(s)**: ISSUE-008
**Módulo(s) afetado(s)**: `database/migrations/`, `config/auth.php`
**Risco**: LOW — adição pura
**Budget**: P

**Correction (ANTES/DEPOIS)**:

```php
// ANTES — config/auth.php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
        'expire' => 60,
        'throttle' => 60,
    ],
],

// DEPOIS
'passwords' => [
    'operators' => [
        'provider' => 'operators',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],

'defaults' => [
    // ...
    'passwords' => 'operators',
],
```

Migration: `database/migrations/2026_05_21_000001_create_password_reset_tokens_table.php` — schema canônico do Laravel (`email PK`, `token`, `created_at`).

**Test**: `php artisan migrate:fresh` verde + `Password::broker('operators')` resolve sem erro (smoke).

---

### Task F6.2 — [ISSUE-008] Forgot/Reset Livewire + rotas + mailable + link + rate-limit + audit

**Estado atual**: 0 código de password reset.

**Estado desejado**:

1. Rotas em `routes/web.php` (grupo `guest`):
   - `GET /forgot-password` → `Auth\ForgotPassword` (Livewire) → `password.request`
   - `POST /forgot-password` (form submit do Livewire) → `password.email`
   - `GET /reset-password/{token}` → `Auth\ResetPassword` (Livewire) → `password.reset`
   - `POST /reset-password` (form submit) → `password.update`
2. Livewire `App\Http\Livewire\Auth\ForgotPassword` — campo `email`, validação `required|email`, chama `Password::broker('operators')->sendResetLink([...])`, sempre retorna mensagem genérica.
3. Livewire `App\Http\Livewire\Auth\ResetPassword` — campos `email`, `password`, `password_confirmation`, `token` (vem da URL via `mount`). Validação: `password: required|confirmed|min:8`. Chama `Password::broker('operators')->reset([...])`.
4. Mailable `App\Mail\OperatorPasswordResetMail` com template em `resources/views/emails/operator-password-reset.blade.php` (URL assinada via `URL::temporarySignedRoute('password.reset', now()->addMinutes(60), [...])`).
5. Override `Operator::sendPasswordResetNotification($token)` para usar o mailable customizado.
6. Link em `resources/views/livewire/auth/login.blade.php` — `<a href="{{ route('password.request') }}">Esqueci minha senha</a>` abaixo do botão Entrar.
7. Rate-limit em `POST /forgot-password`: middleware `throttle:3,15` por IP+email.
8. Audit: registrar `password_reset_requested` (no submit do ForgotPassword) e `password_reset_completed` (no sucesso do ResetPassword). Para operador `status != active`: `password_reset_blocked`.

**Fonte(s)**: ISSUE-008
**Módulo(s) afetado(s)**: `routes/web.php`, `app/Http/Livewire/Auth/`, `app/Mail/`, `app/Models/Operator.php`, `resources/views/livewire/auth/`, `resources/views/emails/`
**Risco**: MEDIUM — surface de auth ampliada; cuidado com enumeração de e-mail e replay
**Budget**: M

**Test**: Pest Feature tests obrigatórios:
- `it sends reset link for active operator`
- `it returns generic message for unknown email` (anti-enumeração)
- `it returns generic message + logs audit blocked for inactive operator`
- `it rate-limits 4th request within 15min`
- `it accepts valid token and updates password`
- `it rejects expired token (>60min)`
- `it rejects mismatched email/token pair`

Gate: `docker compose exec app php artisan test --filter=ForgotPassword --filter=ResetPassword` verde + suite global ≥ 323+7 = 330 testes.

---

### Task F6.3 — [ISSUE-009] WebhookPayload + WebhookHandler hook para JobLogFetcher

**Estado atual**: `WebhookHandler::applyFinishedEvent()` (linhas 112-167) faz `$job->update([...])` e cria `AuditLog`. Nunca chama serviço de fetch de log.

**Estado desejado**: após o `update()` do estado terminal, dentro da mesma transação, chamar `$this->jobLogFetcher->fetch($job, $cluster)` e mesclar `summary` no `$updates` quando o retorno for não-vazio. Falha do fetcher NÃO aborta a transação (try/catch + `Log::warning`).

**Fonte(s)**: ISSUE-009
**Módulo(s) afetado(s)**: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Modules/Jobs/Dto/WebhookPayload.php` (opcional — só se quiser aceitar `log_tail` hint do upstream futuro)
**Risco**: MEDIUM — toca o caminho crítico de finalização de job
**Budget**: P

**Correction (DEPOIS)**:

```php
// app/Modules/Jobs/Services/WebhookHandler.php
public function __construct(
    private readonly StateTranslator $stateTranslator,
    private readonly JobLogFetcher $jobLogFetcher,
) {}

// dentro de applyFinishedEvent(), após $updates ser montado:
DB::transaction(function () use ($job, $canonical, $payload, $cluster): void {
    $updates = [/* ... existente ... */];

    if ($payload->finishedAt !== null) {
        $updates['finished_at'] = Carbon::parse($payload->finishedAt);
    }

    if (empty($job->summary)) {
        try {
            $lines = $this->jobLogFetcher->fetch($job, $cluster);
            if ($lines !== []) {
                $updates['summary'] = $lines;
            }
        } catch (\Throwable $e) {
            \Log::warning('jobs.log_fetch.failed', [
                'job_id' => $job->job_id,
                'cluster_id' => $cluster->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    $job->update($updates);

    // ... resto (customer status, audit log) ...
});
```

**Test**: F6.5 (Feature tests com fetcher mockado cobrem o hook).

---

### Task F6.4 — [ISSUE-009] JobLogFetcher service

**Estado atual**: serviço inexistente.

**Estado desejado**: `App\Modules\Jobs\Services\JobLogFetcher` injetando `SshClientInterface`. Método `fetch(Job $job, ClusterServer $cluster): array<string>` retorna lista de linhas (vazias filtradas, secrets sanitizados). Idempotência: se `$job->summary` já populada, retorna `[]` sem fazer SSH (caller já filtra, mas double-check).

**Algoritmo**:
1. Comando primário: `nextcloud-manage <client> job <job_id> logs --json` — passa `$job->customer_slug` como `<client>` (ou `_` se for job sem cliente). Timeout 15s (config `services.ssh.log_fetch_timeout_seconds`).
2. Se `exit_code === 99` (notImplemented per E6) → fallback: `nextcloud-manage <client> job <job_id> status --json` e extrair `data.summary` ou `data.logs`.
3. Parsear JSON. Esperar `array<string>` ou `{lines: array<string>}` ou `{summary: array<string>}` — tolerante.
4. Sanitizar cada linha: regex `(password|token|secret|pwd)\s*[:=]\s*\S+` → `$1=[REDACTED]`. Também remover linhas com `--payload-stdin` content.
5. Retornar array. Em qualquer erro de parse/exec, lançar `JobLogFetchException` (handler captura).

**Fonte(s)**: ISSUE-009, Errata E7 (`summary` em `job status`), Errata E6 (exit code 99)
**Módulo(s) afetado(s)**: `app/Modules/Jobs/Services/JobLogFetcher.php`, `app/Modules/Jobs/Exceptions/JobLogFetchException.php`, `config/services.php` (ssh.log_fetch_timeout_seconds)
**Risco**: MEDIUM — novo SSH path; precisa de cuidado com timeout e parsing tolerante
**Budget**: M

**Test**: F6.5 (unit/feature com mock) + F6.6 (contract opt-in).

---

### Task F6.5 — [ISSUE-009] Pest Feature tests do hook + JobLogFetcher (mock)

Cenários obrigatórios:
- `webhook job.finished populates summary from fetcher result`
- `webhook job.finished idempotency: summary already populated → fetcher not called`
- `webhook job.finished tolerates fetcher exception (logs warning, persists state)`
- `webhook job.started does NOT call fetcher`
- `webhook job.finished with empty fetcher result keeps summary null`
- `JobLogFetcher sanitizes password=foo to password=[REDACTED]`
- `JobLogFetcher falls back to job status when exit_code=99`

Mock `SshClientInterface` via `Mockery` (padrão estabelecido em `tests/Feature/Customers/LifecycleTest.php`).

**Budget**: P (testes, sem produção)
**Gate**: suite ≥ 330 testes verde.

---

### Task F6.6 — [ISSUE-009] Contract test opt-in (RUN_UPSTREAM_CONTRACT=1)

Espelhando o padrão de `tests/Contract/Customers/UpstreamContractTest.php`:

```php
test('nextcloud-manage job logs --json returns parseable array')
    ->skipUnless(env('RUN_UPSTREAM_CONTRACT') === '1');
```

Provisiona um job de teste (ou usa job_id conhecido do `homolog`), executa SSH real e asserta formato esperado. Falha pega divergência upstream antes de produção.

**Budget**: P
**Gate**: rodar manual; documentar resultado em `docs/.briefs/F6.brief.md`.

---

## Sprint F7 — High findings N1: transação + rastreabilidade + teste de erro

> Categoria: F
> Gate: (1) `Create::save()` persiste `cluster_servers` + `webhook_secret_history` atomicamente; (2) rotação de secret registra `actor_id` correto no `AuditLog`; (3) existe teste cobrindo explicitamente o erro "sem secret atual no histórico". Suite permanece verde.
> Gerado por `/fix` em 2026-05-21. Fonte: findings HIGH pendentes `CQ-N1-001`, `CQ-N1-002`, `QA-N1-001` (Sprint N1).
> review: senior+qa (HIGH severity)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | F7.1 — [CQ-N1-001] Tornar atômico o fluxo de criação de cluster + history (`DB::transaction`) | `laravel-livewire` + `50-database.mdc` | — |
| [x] | P | F7.2 — [CQ-N1-002] Propagar `actor_id` no `RotateWebhookSecretAction` (AuditLog de falha) | `laravel-livewire` | — |
| [x] | P | F7.3 — [QA-N1-001] Cobrir path "sem secret atual no histórico" com teste unitário + integração Livewire | `laravel-testing` | F7.1 |

### Task F7.1 — [CQ-N1-001] Transaction em `Create::save()`

**Estado atual**: `ClusterServer::create()` e `WebhookSecretHistory::create()` são escritas separadas em `Create::save()`, sem `DB::transaction`.

**Estado desejado**: envolver as duas inserções numa transação única para preservar o invariante `cluster_server` ↔ `webhook_secret_history`. O sync SSH continua fora da transação.

**Fonte(s)**: `CQ-N1-001` (HIGH)
**Módulo(s) afetado(s)**: `app/Http/Livewire/ClusterServers/Create.php`
**Risco**: MEDIUM (consistência de dados)
**Budget**: M

**Test**: falha simulada no insert de history deve efetuar rollback total (nenhum cluster órfão persistido).

---

### Task F7.2 — [CQ-N1-002] `actor_id` consistente no AuditLog de rotação

**Estado atual**: `RotateWebhookSecretAction` registra `cluster_server.secret_sync_failed` com `actor_id => null` no caminho de erro.

**Estado desejado**: `execute()` recebe `?string $actorId = null` e grava esse valor no AuditLog; caller de UI (`Index::rotateSecret`) passa `auth()->id()`.

**Fonte(s)**: `CQ-N1-002` (HIGH)
**Módulo(s) afetado(s)**: `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`, `app/Http/Livewire/ClusterServers/Index.php`
**Risco**: LOW (ajuste de rastreabilidade)
**Budget**: P

**Test**: caminho de falha de sync deve manter `actor_id` do operador autenticado no `AuditLog`.

---

### Task F7.3 — [QA-N1-001] Teste de erro "sem secret atual no histórico"

**Estado atual**: não há teste cobrindo o `RuntimeException` em `RotateWebhookSecretAction` quando não existe secret ativo em `webhook_secret_history`.

**Estado desejado**: adicionar:
- teste unitário da Action validando `toThrow(RuntimeException, 'sem secret atual no histórico')`;
- teste de integração Livewire em `Index::rotateSecret` validando erro amigável para admin (sem 500).

**Fonte(s)**: `QA-N1-001` (HIGH)
**Módulo(s) afetado(s)**: `tests/Feature/ClusterServers/RotateSecretTest.php` (ou arquivo equivalente), `app/Http/Livewire/ClusterServers/Index.php` (se necessário)
**Risco**: LOW
**Budget**: P

**Test**: a própria task (novos testes devem falhar antes e passar depois da implementação).

---

## Sprint F8 — Readiness gate pós-provision (ISSUE-010)

> Categoria: F
> Gate: (1) webhook `provision` + `success` deixa tenant em `provisioning_finishing` (não `active`); (2) probe SSH (`occ-exec user:list` exit 0) promove para `active`; (3) `POST/DELETE .../users` em tenant não-ready retorna **503** `{error: tenant_not_ready}` + header `Retry-After: 60` (não enfileira job que falha silenciosamente); (4) `groups:*` e `apps:*` continuam aceitos na janela; (5) suite verde + testes novos cobrindo webhook + lifecycle gate.
> Gerado por `/fix` em 2026-05-23. Fonte: **ISSUE-010** (postmortem CRITICAL) + finding **QA-DYN-021** (triagem P-21).
> review: **senior+qa** (CRITICAL — obrigatório por `debugging-sistematico` Fase 4a)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F8.1 — [ISSUE-010] TDD: webhook `provision success` → `provisioning_finishing`; atualizar `WebhookHandlerTest` | `webhook-receiver` + `laravel-testing` | — |
| [x] | M | F8.2 — [ISSUE-010] `CustomerReadinessProbe` + `ProbeCustomerReadinessJob` (probe `occ-exec user:list`, backoff, timeout 20min) | `ssh-orchestrator` | F8.1 |
| [x] | M | F8.3 — [ISSUE-010] Gate `users:create|users:delete` em `LifecycleAsyncAction` + `TenantNotReadyException` → 503 no controller | `laravel-api` | F8.1 |
| [x] | P | F8.4 — [ISSUE-010] Pest Feature: tenant `provisioning_finishing` + `users:create` → 503; probe mock → `active` + dispatch OK | `laravel-testing` | F8.2, F8.3 |
| [x] | P | F8.5 — [ISSUE-010] `CustomerSyncService`: não sobrescrever `provisioning_finishing` com `running→active` do upstream | `laravel-api` | F8.1 |
| [x] | P | F8.6 — [ISSUE-010] OpenAPI 503 `tenant_not_ready` + Decision em `SETUP-DECISIONS.md` + badge UI `provisioning_finishing` | `laravel-api` | F8.3 |

> **Validação `/qa validar F8`** (2026-05-23): senior+qa → **REPROVADA**. Core fix ISSUE-010 validado (17/17 testes F8). 2 HIGH pendentes: `QA-F8-001` (timeout probe ~83 min vs ~20 min), `QA-F8-002` (probe failure paths sem teste). Brief: `docs/.briefs/F8.brief.md`. PROC-012: follow-up F8.7+ antes de merge.

| [x] | P | F8.7 — [QA-F8-001] Ajustar timeout probe ~20 min + teste boundary | `laravel-api` | F8.2 |
| [x] | P | F8.8 — [QA-F8-002] Testes probe: SSH fail, exit≠0, exhaustion → failed | `laravel-testing` | F8.2 |
| [x] | P | F8.9 — [QA-F8-003/004] Testes gate DELETE + provisioning status | `laravel-testing` | F8.3 |
| [x] | M | F8.10 — [QA-F8-005/006] Sync guard `provisioning` + OccPanel UX | `laravel-api` | F8.5, F8.3 |

> **Re-validação F8 R1** (2026-05-23): follow-up F8.7–F8.10 implementado. Testes F8: **46 passed**. Full suite: **364 passed** (2 falhas env: permissão `storage/logs`). **Resultado: APROVADA** — HIGH blockers resolvidos; remanescentes LOW/MEDIUM (`QA-F8-008`, `QA-F8-011`).

> **Quality Brief (Sprint F8)**: PATTERN-001 (Decision #187) — auditor-senior DEVE criar `docs/.briefs/F8.brief.md` como PRIMEIRO Write na auditoria final.

### Contexto F8

Em testes dinâmicos (2026-05-21), o upstream emite callback `provision success` após o passo 6 da `SSH API Reference §4.1` (core install), enquanto passos 7–9 (Redis, Collabora, 14 apps) ainda executam (~5–15 min). A API marca `Customer.status=active` imediatamente (`WebhookHandler:164`). Operações `users:create`/`users:delete` falham silenciosamente se disparadas nos primeiros ~10 min (5/5 failed); após ~30 min funcionam (8/8 success). `groups:*` e `apps:*` toleram a janela.

**Fix Brief aprovado (opção B)**: readiness gate defensivo na API — não depende de deploy upstream imediato. Issue upstream (opção A) pode ser aberta em paralelo.

### Riscos da Sprint F8

- **`CustomerSyncService` pode promover `active` cedo** — cron `customers:sync` mapeia upstream `running` → `active`. F8.5 deve preservar `provisioning_finishing` até probe local confirmar.
- **Probe `user:list` pode falhar por motivos não-readiness** — tratar exit ≠ 0 como "ainda não pronto" com retry; timeout 20min → `failed` + audit `customer_readiness_timeout`.
- **UI/Livewire** — badges em `customers/show` e `customers/index` precisam exibir `provisioning_finishing` (distinto de `provisioning`).
- **OpenAPI contrato** — novo 503 não quebra clientes existentes (fail-closed explícito é melhor que 202→failed).

### Task F8.1 — WebhookHandler: provision success → `provisioning_finishing`

**Estado atual**: `WebhookHandler::applyFinishedEvent()` linha 164: `provision + success` → `Customer.status = 'active'`.

**Estado desejado**: mesmo match → `'provisioning_finishing'`. Disparar `ProbeCustomerReadinessJob::dispatch($customerSlug)` após commit (fora da transação DB).

**Fonte(s)**: ISSUE-010, QA-DYN-021
**Módulo(s)**: `app/Modules/Jobs/Services/WebhookHandler.php`, `tests/Feature/Jobs/WebhookHandlerTest.php`
**Budget**: P

**Correction (ANTES/DEPOIS)**:

```php
// ANTES
$job->job_type === 'provision' && $canonical === 'success' => 'active',

// DEPOIS
$job->job_type === 'provision' && $canonical === 'success' => 'provisioning_finishing',
```

**Test (TDD primeiro)**: alterar teste linha 96-115 para expect `'provisioning_finishing'`; novo teste asserta que job de probe é dispatched (Bus::fake).

---

### Task F8.2 — CustomerReadinessProbe + Job assíncrono

**Estado atual**: nenhum probe pós-provision.

**Estado desejado**:
- `App\Modules\Customers\Services\CustomerReadinessProbe` com método `isReady(Customer $customer): bool`
- Executa via `OccPassthroughService::exec($customer, 'user:list', [])` — exit 0 = ready
- `App\Jobs\ProbeCustomerReadinessJob` implementa `ShouldQueue`, `$tries` alto, backoff `[30, 60, 120, 300]` segundos, `$timeout = 120`
- Sucesso → `Customer::where('slug', ...)->update(['status' => 'active'])` + audit `customer_readiness_confirmed`
- Timeout (~20 min desde webhook) → `status = 'failed'` + audit `customer_readiness_timeout`

**Fonte(s)**: ISSUE-010 §Fix Brief opção B
**Módulo(s)**: `app/Modules/Customers/Services/`, `app/Jobs/`, `config/services.php` (readiness.max_wait_seconds, probe_interval)
**Budget**: M

**Test**: unit test com `OccPassthroughService` mockado (exit 0 → true, exit 1 → false); feature test com Bus fake + job handle mock.

---

### Task F8.3 — Gate lifecycle `users:*` + HTTP 503

**Estado atual**: `LifecycleAsyncAction::execute()` não inspeciona `customer.status`.

**Estado desejado**:
- Nova exception `TenantNotReadyException` (carrega `$customer->status`, `$retryAfterSeconds = 60`)
- Em `execute()`, antes do SSH, se `$cmd` ∈ `{users:create, users:delete}` e status ∈ `{provisioning, provisioning_finishing}` → throw
- `CustomerLifecycleController::dispatch()` catch → `503` + `Retry-After: 60` + `{error: tenant_not_ready, status: ...}`

**Fonte(s)**: ISSUE-010
**Módulo(s)**: `LifecycleAsyncAction.php`, `CustomerLifecycleController.php`, nova exception
**Budget**: M

**Test**: F8.4

---

### Task F8.4 — Pest Feature tests (webhook + lifecycle gate)

Cenários obrigatórios:
- `webhook provision finished sets provisioning_finishing not active`
- `ProbeCustomerReadinessJob promotes to active when probe succeeds`
- `POST users on provisioning_finishing returns 503 tenant_not_ready`
- `POST users on active customer still returns 202` (regressão)
- `POST groups:create on provisioning_finishing still returns 202` (não gated)
- `CustomerSyncService does not overwrite provisioning_finishing with active`

**Budget**: P

---

### Task F8.5 — CustomerSyncService: precedência local

**Estado atual**: `translateInstanceStatus('running')` → `'active'` sempre sobrescreve local.

**Estado desejado**: se `$local->status === 'provisioning_finishing'`, pular update de status (só atualizar `last_sync_at`). Probe local é fonte de verdade para transição → `active`.

**Fonte(s)**: ISSUE-010 §Riscos
**Módulo(s)**: `app/Modules/Customers/Services/CustomerSyncService.php`
**Budget**: P

---

### Task F8.6 — Contrato + Decision + UI badge

**Estado desejado**:
- `docs/openapi.yaml`: response component `TenantNotReady` (503) em `POST/DELETE .../users`
- `docs/SETUP-DECISIONS.md`: nova Decision `#ARCH-5` (readiness gate pós-provision, opção B)
- `resources/views/livewire/customers/show.blade.php` + `index.blade.php`: badge para `provisioning_finishing` ("Finalizando configuração")
- `docs/db-schema.dbml`: nota em `customers.status` incluindo `provisioning_finishing`

**Budget**: P

---

## Sprint F9 — API 404 JSON contract (ISSUE-012)

> Categoria: F
> Gate: (1) `GET /api/<inexistente>` **sem** `Accept: application/json` retorna `404` + `Content-Type: application/json` + body `{"error":"route_not_found",...}`; (2) `MethodNotAllowedHttpException` sob `/api/*` idem com `method_not_allowed`; (3) rotas web (não-API) continuam retornando HTML; (4) teste `ApiNotFoundJsonTest` verde.
> Gerado por `/fix` em 2026-05-24. Fonte: **ISSUE-012** (bug HIGH — info leak + DX).
> review: senior+qa (HIGH severity)
>
> **Nota**: os 3 findings HIGH N1 (`CQ-N1-001`, `CQ-N1-002`, `QA-N1-001`) já estão planejados na **Sprint F7** — executar `/pmo sprint F7` separadamente.

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F9.1 — [ISSUE-012] `shouldRenderJsonWhen` em `bootstrap/app.php` + payload 404/405 JSON sob `/api/*` | `laravel-api` | — |
| [x] | P | F9.2 — [ISSUE-012] Pest Feature `ApiNotFoundJsonTest` (404/405 JSON com e sem `Accept`; HTML preservado fora de `/api/*`) | `laravel-testing` | F9.1 |

### Task F9.1 — [ISSUE-012] Handler JSON forçado para rotas `/api/*`

**Estado atual**: `NotFoundHttpException` sob `/api/*` retorna HTML (~30 KB) quando o cliente não envia `Accept: application/json`. Outros erros da API (409/422/502/503) já retornam JSON.

**Estado desejado**: em `bootstrap/app.php`, registrar `shouldRenderJsonWhen` para `$request->is('api/*') || $request->expectsJson()`. Customizar payload de 404/405:

```json
{ "error": "route_not_found", "path": "/api/...", "method": "GET" }
```

**Fonte(s)**: ISSUE-012 (HIGH)
**Módulo(s) afetado(s)**: `bootstrap/app.php`
**Risco**: LOW — escopo isolado; UI web inalterada
**Budget**: P

**Test**: `curl -s -o /dev/null -w '%{content_type}' http://localhost/api/rota-inexistente` → `application/json`.

---

### Task F9.2 — [ISSUE-012] Testes de regressão ApiNotFoundJsonTest

**Estado desejado**: `tests/Feature/ApiNotFoundJsonTest.php` cobrindo:
- (a) 404 JSON sob `/api/*` **sem** `Accept: application/json`
- (b) 404 JSON sob `/api/*` **com** `Accept: application/json` (sem regressão)
- (c) HTML preservado fora de `/api/*` (ex.: `GET /rota-inexistente`)
- (d) `POST` em rota só-GET → `405` JSON com `error: method_not_allowed`

**Fonte(s)**: ISSUE-012 critério de aceite
**Módulo(s) afetado(s)**: `tests/Feature/ApiNotFoundJsonTest.php`
**Risco**: LOW
**Budget**: P

**Test**: a própria task.

---

## Sprint F10 — JobLogFetcher argv fix (ISSUE-014)

> Categoria: F
> Gate: (1) `JobLogFetcher` invoca `nextcloud-manage job <id> logs --json` (sem client slug); (2) fallback `status --json` + `SshRemoteException(notImplemented)`; (3) após deploy, job novo popula `jobs.summary` e `/queue/{jobId}` exibe linhas; (4) 12 testes `JobLogFetcherTest` verde.
> Gerado por `/pmo sprint` em 2026-05-24. Fonte: **ISSUE-014** (bug — exit 101 cmd_not_allowed) + sintoma **ISSUE-009** (logs vazios).
> review: fast-track (diff isolado, sem auditoria formal obrigatória)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F10.1 — [ISSUE-014] Corrigir argv em `JobLogFetcher` (`['job', $id, 'logs', '--json']`) + `fetchViaStatus` idem + catch `SshRemoteException(notImplemented)` | `ssh-orchestrator` | — |
| [x] | P | F10.2 — [ISSUE-014] Testes: assert argv sem client slug; fallback via `SshRemoteException(99)`; contract comment fix | `laravel-testing` | F10.1 |
| [ ] | P | F10.3 — Push `main` + deploy remoto + validar job novo em produção (`summary` populado, UI com logs) — **bloqueado: execução ops**; runbook [F10.3-prod-validation.md](runbooks/F10.3-prod-validation.md) | `70-devops` | F10.2 |

### Contexto F10

Durante triagem de `/queue/{jobId}` vazio (2026-05-24), confirmado em produção:

- Webhook `job.finished` chega sem `log_tail` → `WebhookHandler` invoca `JobLogFetcher`
- Fetch falha 100% com exit 101 — argv incorreto incluía `<client>` antes de `job`
- Job `e6dec946-b91a-4112-ab84-916c8be5c3c7`: SUCCESS + exit_code 0, mas `summary` null

Fix implementado em `197ff46` (merged local em `main`). Sprint F10 formaliza gate de deploy.

**F10.3 (2026-06-12):** código F10.1–F10.2 concluído; deploy + smoke prod **pendente execução humana** (ISSUE-023 `ready_for_ops`). Runbook: `docs/runbooks/F10.3-prod-validation.md`. Agente não faz SSH em `deployer.mework360.com.br`.

---

## Sprint F11 — Slug reuse pós-falha + limpeza MEDIUM F5

> Categoria: F
> Gate: (1) re-provisionar o mesmo slug após job `provision.failed` retorna 202 sem erro; (2) `unique:customers,slug` ignora soft-deleted; (3) `Customer::create` não colide em PK com registro fantasma; (4) CMD_TO_CLI_ARGV dead-code removido; (5) `mapLifecycleException` extraído; (6) phpunit.xml força `RUN_UPSTREAM_CONTRACT=0`; (7) suite permanece verde.
> Gerado por `/fix` em 2026-05-24. Fonte: ISSUE-018 (HIGH) + CQ-F5-002/003, QA-F5-006, QA-F5-008, QA-F5-010 (MEDIUM — entregues F11).
> **Status**: **concluída** — validação APROVADA R1 (2026-05-24).
> review: senior+qa (HIGH em ISSUE-018; delta isolado)
> **Nota**: `CQ-N1-001`, `CQ-N1-002`, `QA-N1-001` (HIGH) já estão em Sprint F7 (não executada). Recomenda-se executar F11 e F7 em sequência ou combinados via `/pmo sprint F11` + `/pmo sprint F7`.

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F11.1 — [ISSUE-018] Corrigir lifecycle de Customer após falha de provisioning (soft-delete webhook + unique fix + restore/update ghost) | `laravel-migration` + `webhook-receiver` | — |
| [x] | P | F11.2 — [CQ-F5-002] Remover 7 entradas YAGNI customer-level de `CMD_TO_CLI_ARGV` em `JobTypeTranslator` | `vocabulary-translator` | — |
| [x] | P | F11.3 — [CQ-F5-003] Extrair `mapLifecycleException()` privado em `CustomerLifecycleController` | `laravel-api` | — |
| [x] | P | F11.4 — [QA-F5-010] Adicionar `<env name="RUN_UPSTREAM_CONTRACT" value="0" force="true"/>` ao `phpunit.xml` | `60-testing.mdc` | — |
| [x] | M | F11.5 — [QA-F5-006] Adicionar assertions `--idempotency-key=` e `--callback=` em `LifecycleTest` (1 teste representativo por path) | `laravel-testing` | — |
| [x] | M | F11.6 — [QA-F5-008] Decidir + documentar política de hash para CSV apps; adicionar teste assertindo a política | `vocabulary-translator` + `laravel-testing` | — |

> **Validação F11 R1** (2026-05-24, `/qa validar R1`): senior → **REPROVADA** (CRITICAL CQ-F11-001 + HIGH CQ-F11-002). QA → **REPROVADA** (HIGH QA-F11-001 + 3 MEDIUM). **Todos os 7 findings corrigidos in-sprint** (R1 follow-up: `restore()+update()` em vez de `forceDelete`). **Resultado: APROVADA**. Suite 394+ passed, 7 skipped. Ver `docs/FINDINGS.md` seção Sprint F11.

### Task F11.1 — [ISSUE-018] Lifecycle de Customer após falha de provisioning

**Estado atual**: Quando `WebhookHandler` recebe `job.finished` com `state=failed` para um job de `provision`, apenas atualiza `customer.status = 'failed'`. O registro `Customer` persiste na tabela. Como `slug` é PK, re-provisioning com mesmo slug falha em dois pontos:
- `ProvisionCustomerRequest` → 422 "Slug já em uso" (regra `unique:customers,slug` não exclui soft-deleted)
- `ProvisionCustomerAction` → PK duplicate error (mesmo com soft-delete, constraint do banco persiste)

**Estado desejado**: Falha de provisioning resulta em registro fantasma removido → re-provisioning funciona transparentemente.

**Fix em 3 arquivos cirúrgicos**:

1. `app/Modules/Jobs/Services/WebhookHandler.php` (~linha 169) — após `Customer::where(...)->update(['status' => $customerStatus])`:
   ```php
   // Soft-delete: provisioning failure = customer was never created upstream
   if (in_array($canonical, ['failed', 'cancelled'], true) && $job->job_type === 'provision') {
       Customer::where('slug', $job->customer_slug)->delete();
   }
   ```

2. `app/Http/Requests/ProvisionCustomerRequest.php` (linha 21) — trocar a regra `unique`:
   ```php
   // ANTES: 'unique:customers,slug'
   // DEPOIS:
   use Illuminate\Validation\Rule;
   Rule::unique('customers', 'slug')->whereNull('deleted_at'),
   ```

3. `app/Modules/Customers/Actions/ProvisionCustomerAction.php` — dentro do `DB::transaction`, antes de `Customer::create(...)`:
   ```php
   // Cleanup any soft-deleted ghost from a previous failed provisioning attempt
   Customer::withTrashed()->where('slug', $payload->slug)->whereNotNull('deleted_at')->forceDelete();
   ```

**Fonte(s)**: ISSUE-018 (HIGH)
**Módulo(s) afetado(s)**: `WebhookHandler`, `ProvisionCustomerRequest`, `ProvisionCustomerAction`
**Risco**: MEDIUM — clientes `failed` deixam de aparecer em listagens (se houver alguma que filtre por status). Confirmar que nenhuma UI/API lista `status=failed` antes de executar.
**Budget**: P

**Tests** (TDD):
- webhook `provision.failed` → customer soft-deletado (não persiste com `status=failed`)
- re-provisioning do mesmo slug após webhook `failed` → 202 + novo `job_id`
- `unique` validation passa para slug com registro soft-deleted
- `unique` validation falha para slug com customer ativo (`status=provisioning/active/...`)

---

### Task F11.2 — [CQ-F5-002] Remover dead-code `CMD_TO_CLI_ARGV` customer-level

**Estado atual**: `JobTypeTranslator::CMD_TO_CLI_ARGV` tem 7 entradas customer-level (`create`, `remove`, `backup`, `restore`, `update`, `stop`, `start`) que nenhum caller de `cmdToCliArgv()` consome. `ProvisionCustomerAction` e `RemoveCustomerAction` constroem argv à mão.

**Estado desejado**: Entrads YAGNI removidas. Comentário TODO de forward-compat removido ou substituído por comentário claro de escopo.

**Fonte(s)**: CQ-F5-002 (MEDIUM)
**Módulo(s) afetado(s)**: `app/Modules/Core/Translators/JobTypeTranslator.php`
**Risco**: LOW (remoção de dead-code não usado por nenhum caller produtivo)
**Budget**: P

**Test**: `JobTypeTranslatorTest` asserta que `cmdToCliArgv('create')` lança `UnknownCmdException` (ou equivalente) após remoção. OU: verificar que nenhum teste quebra com a remoção.

---

### Task F11.3 — [CQ-F5-003] Extrair `mapLifecycleException()` em `CustomerLifecycleController`

**Estado atual**: `dispatch()` (linhas ~144-185) e `dispatchAppsCsv()` (linhas ~199-230) em `CustomerLifecycleController` capturam as mesmas 4-5 exceções com corpos quase idênticos (~30 LoC duplicados).

**Estado desejado**: Método privado `mapLifecycleException(\Throwable $e, array $extraPayload = []): JsonResponse` extraído. Ambos delegam para ele.

**Fonte(s)**: CQ-F5-003 (MEDIUM)
**Módulo(s) afetado(s)**: `app/Http/Controllers/Api/CustomerLifecycleController.php`
**Risco**: LOW (pure refactor, comportamento idêntico)
**Budget**: P

**Test**: testes existentes de `dispatch()` e `dispatchAppsCsv()` devem continuar verdes sem alteração.

---

### Task F11.4 — [QA-F5-010] `phpunit.xml` força `RUN_UPSTREAM_CONTRACT=0`

**Estado atual**: a testsuite `Contract` está isolada em diretório separado (não roda por default), mas não há proteção explícita no `phpunit.xml` via `force="true"`.

**Estado desejado**: Adicionar no bloco `<php>` do `phpunit.xml`:
```xml
<env name="RUN_UPSTREAM_CONTRACT" value="0" force="true"/>
```

**Fonte(s)**: QA-F5-010 (MEDIUM)
**Módulo(s) afetado(s)**: `phpunit.xml`
**Risco**: BAIXO (defense-in-depth; não altera comportamento atual da CI)
**Budget**: P

**Test**: confirmar que `UpstreamContractTest` é pulado quando `force="true"` (inspeção manual do output).

---

### Task F11.5 — [QA-F5-006] Assertions `--idempotency-key` e `--callback` em `LifecycleTest`

**Estado atual**: `LifecycleAsyncAction::execute()` anexa `--idempotency-key={UUID}` e `--callback={url}` ao argv SSH. Nenhum teste asserta presença dessas flags. Regressão silenciosa → jobs zombie.

**Estado desejado**: Adicionar a 1 teste representativo por categoria (users, groups, apps):
```php
expect($args)->toContain(fn ($a) => str_starts_with($a, '--idempotency-key='))
expect($args)->toContain(fn ($a) => str_contains($a, '/api/jobs/hook?cluster='))
```

**Fonte(s)**: QA-F5-006 (MEDIUM)
**Módulo(s) afetado(s)**: `tests/Feature/Customers/LifecycleTest.php`
**Risco**: LOW (adição de testes apenas)
**Budget**: M (requer análise de quais testes são representativos por path)

**Test**: os novos asserts devem falhar se a flag for removida de `execute()`.

---

### Task F11.6 — [QA-F5-008] Política de hash CSV apps: decidir + documentar + testar

**Estado atual**: `implode(',', $apps)` em `LifecycleAsyncAction` e `CustomerLifecycleController` preserva ordem de input. `'calendar,mail'` ≠ `'mail,calendar'` em deduplicação. Política não documentada nem testada.

**Estado desejado**: 
- Decisão explícita: (A) preservar ordem (atual — sem change de comportamento) OU (B) canonicalizar via `sort($apps)` antes do `implode`.
- Adicionar comentário no código documentando a política.
- Adicionar 1 teste assertindo o comportamento escolhido.

**Fonte(s)**: QA-F5-008 (MEDIUM)
**Módulo(s) afetado(s)**: `app/Http/Controllers/Api/CustomerLifecycleController.php`, `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
**Risco**: LOW para A (preservar ordem); MEDIUM para B (canonicalizar muda comportamento de deduplicação — breaking change para callers que já se apoiavam na ordem)
**Budget**: M

**Test**: 1 teste asserta que `['calendar', 'mail']` e `['mail', 'calendar']` geram hashes **iguais** (se B) ou **diferentes** (se A).

---

## Sprint F12 — SSH readiness transport exception normalization

> Categoria: F
> Gate: `SshClient` converte exceções de transporte do phpseclib durante `exec()` em `SshConnectionException`, remove conexão stale do pool e preserva retry; readiness probe não gera `local.ERROR` não tratado para queda transitória de canal.
> Gerado por `/fix` em 2026-05-27. Fonte: ISSUE-020 (MEDIUM).
> review: senior+qa (Core/Ssh; integração externa)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F12.1 — [ISSUE-020] Normalizar `ConnectionClosedException` em `SshClient::executeCommand()` | `ssh-orchestrator` + `laravel-testing` | — |

### Task F12.1 — [ISSUE-020] Normalizar `ConnectionClosedException` em `SshClient::executeCommand()`

**Estado atual**: `SshClient::executeCommand()` trata `exec() === false`, mas não captura exceções lançadas por `phpseclib` durante `SSH2::exec()`. Quando uma conexão reaproveitada pelo `SshConnectionPool` fecha antes da abertura de canal, `ConnectionClosedException` escapa crua, o retry de `SshClient::run()` não acontece naquela tentativa e `ProbeCustomerReadinessJob` registra `local.ERROR`.

**Estado desejado**: exceções de transporte durante `exec()` e `execWithStdin()` devem virar `SshConnectionException`, com a conexão removida do pool e `previous` preservado. O retry existente em `SshClient::run()` deve reaproveitar esse contrato sem alterar callers.

**Fonte(s)**: ISSUE-020 (MEDIUM)
**Módulo(s) afetado(s)**: `app/Modules/Core/Ssh/SshClient.php`, `tests/Feature/Core/SshClientTest.php`
**Risco**: LOW — alteração restrita ao adapter SSH, alinhada ao contrato já esperado pelos callers (`SshConnectionException`).
**Budget**: P

**Tests** (TDD):
- `SshClientTest` simula `SSH2::exec()` lançando `phpseclib3\Exception\ConnectionClosedException` na primeira tentativa e sucesso na segunda.
- Assertar que `SshConnectionPool::remove($clusterId)` é chamado ao normalizar a exceção.
- Rodar `php artisan test --filter SshClientTest`.

---

## Sprint F13 — Branding payload no job create (ISSUE-019)

> Categoria: F
> Gate: (1) create inline envia `--payload-stdin` com JSON `branding.logo_data_url`/`branding.background_data_url`; (2) upload inicial persiste logo/background em `branding_meta`; (3) re-provision de ghost reutiliza logo cadastrado sem novo upload; (4) arquivo >256 KB usa `inboxInit` + `sftpUpload` + `--staging-id`, sem payload inline; (5) `ProvisionTest` filtrado verde.
> Gerado por `/fix` em 2026-05-28. Fonte: ISSUE-019 (MEDIUM) + triagem 2026-05-28 sobre contrato `branding.*_data_url`.
> review: senior+qa (Customers + contrato SSH payload/staging)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F13.1 — [ISSUE-019] TDD: cobrir payload inline aninhado em `branding` e branch SFTP sem stdin | `laravel-testing` + `ssh-orchestrator` | — |
| [x] | M | F13.2 — [ISSUE-019] `ProvisionPayload` + `CustomerController`: resolver logo/background salvos em `branding_meta` quando request não trouxer arquivo | `laravel-api` | F13.1 |
| [x] | M | F13.3 — [ISSUE-019] `ProvisionCustomerAction`: montar `branding.*_data_url`, persistir uploads em storage local e atualizar `branding_meta` após create/restore | `ssh-orchestrator` + `laravel-api` | F13.2 |
| [x] | P | F13.4 — [ISSUE-019] Regressão final: testes de provisionamento + atualizar status do issue após validação | `laravel-testing` | F13.3 |

### Task F13.1 — [ISSUE-019] TDD do contrato de branding

**Estado atual**: `ProvisionCustomerAction` monta `logo_data_url` e `background_data_url` no topo do JSON enviado por `--payload-stdin`. O contrato upstream espera essas chaves dentro de `branding`. A branch SFTP já existe, mas precisa de regressão explícita garantindo que não mistura `--payload-stdin` com `--staging-id`.

**Estado desejado**: testes falham antes do fix e comprovam:
- inline ≤ 256 KB envia `--payload-stdin` e stdin JSON com `branding.logo_data_url`
- inline com background envia `branding.background_data_url`
- SFTP > 256 KB chama `inboxInit`/`sftpUpload`, envia `--staging-id` e não envia stdin inline

**Fonte(s)**: ISSUE-019 (MEDIUM)
**Módulo(s) afetado(s)**: `tests/Feature/Customers/ProvisionTest.php`
**Risco**: LOW — testes sobre comportamento já centralizado em `ProvisionCustomerAction`.
**Budget**: P

---

### Task F13.2 — [ISSUE-019] Resolver branding salvo no payload de provisionamento

**Estado atual**: `ProvisionPayload::fromRequest()` só olha arquivos do request atual. Em re-provisioning de ghost, o cliente pode ter `branding_meta.logo_path`, mas `logoPath` fica null e o job `create` sai sem branding.

**Estado desejado**: `CustomerController::store()` consulta ghost soft-deleted antes de construir o payload e `ProvisionPayload` resolve `logoPath`/`backgroundPath` a partir de `branding_meta` quando nenhum arquivo novo vier no request.

**Fonte(s)**: ISSUE-019 (MEDIUM)
**Módulo(s) afetado(s)**: `app/Modules/Customers/Dto/ProvisionPayload.php`, `app/Http/Controllers/Api/CustomerController.php`
**Risco**: MEDIUM — altera contrato de montagem de payload em re-provisioning; manter fallback apenas para ghost e arquivos existentes.
**Budget**: M

---

### Task F13.3 — [ISSUE-019] Persistir uploads e montar `branding.*_data_url`

**Estado atual**: uploads temporários são usados para dispatch SSH e descartados ao fim do request. Além disso, o stdin inline não segue o envelope `branding`.

**Estado desejado**: `ProvisionCustomerAction` monta o stdin como `['branding' => [...]]`, preserva o limiar 256 KB por arquivo e, após criar/restaurar o `Customer`, persiste uploads em `Storage::disk('local')` e atualiza `branding_meta` com caminhos reaproveitáveis.

**Fonte(s)**: ISSUE-019 (MEDIUM)
**Módulo(s) afetado(s)**: `app/Modules/Customers/Actions/ProvisionCustomerAction.php`, `app/Models/Customer.php`
**Risco**: MEDIUM — envolve storage local e contrato SSH; não logar payload base64 e não alterar `payload_sanitized` com dados sensíveis.
**Budget**: M

---

### Task F13.4 — [ISSUE-019] Validação final e status

**Estado desejado**: `ProvisionTest` cobre os cenários críticos e o `ISSUE-019` pode ser marcado como corrigido após suíte filtrada verde.

**Tests**:
- `php artisan test tests/Feature/Customers/ProvisionTest.php`

**Budget**: P

---

### Validação F13 R1

> Resultado: APROVADA — auditor-senior e auditor-qa sem findings após follow-up.

- Testes: `php artisan test tests/Feature/Customers/ProvisionTest.php` → 16 passed, 63 assertions.
- Senior R1: 2 HIGH + 1 MEDIUM detectados inicialmente; corrigidos antes do fechamento (limite base64/JSON, tratamento de staging, `Storage::put`).
- QA R1: gap de teste HTTP multipart coberto; follow-up sem findings.

---

## Sprint F14 — CI verde no main (regressão N19 + phpseclib)

> Categoria: F
> Status: concluída — CI verde no main (PR #112 merge e350caba); validação `/qa validar F14` APROVADA
> Gate: workflow CI verde no `main` (jobs Lint + Test + Security composer audit); findings QA-F14-001, QA-F14-002, SEC-F14-001 em status `corrigido` ou `validado`
> Gerado via `/pmo fix` em 2026-06-16. Fonte: ISSUE-039 + 3 findings (investigação CI run `27646529336`).
> review: senior+qa (regressão de testes + dependência security)

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | F14.1 — [FIX] Bump `phpseclib/phpseclib` → `>=3.0.54` (GHSA-m557-wrgg-6rp4) | `composer update` | — |
| [x] | M | F14.2 — [FIX] `AuditLogTest`: assign direto de secrets (fillable removido no N19) | `laravel-testing` | — |
| [x] | M | F14.3 — [FIX] `RotateSecretTest`: alinhar 4 testes ao factory auto-history (`withoutWebhookHistory()` ou remover seed redundante) | `laravel-testing` | — |
| [x] | P | F14.4 — [ISSUE-039] Atualizar `CI-FAIL-*` + validar CI verde no main | `/git` | F14.1–F14.3 |

### Task F14.1 — [FIX] Bump phpseclib

**Finding(s)**: SEC-F14-001 (MEDIUM)
**Arquivo(s)**: `composer.lock` (+ `composer.json` se constraint pinada)
**ANTES**: `phpseclib/phpseclib` 3.0.52 — `composer audit` exit 1 (SSRF via X.509 AIA).
**DEPOIS**: `>=3.0.54`; `composer audit --no-dev --locked` exit 0.
**Validação**: `composer audit --no-dev --locked`

### Task F14.2 — [FIX] AuditLogTest mass assignment

**Finding(s)**: QA-F14-001 (HIGH)
**Arquivo(s)**: `tests/Feature/Audit/AuditLogTest.php`
**ANTES**: `ClusterServer::create(['ssh_private_key_encrypted' => ...])` — campos fora do `$fillable` desde N19 → NOT NULL violation.
**DEPOIS**: `factory()->create()` + `$cluster->ssh_private_key_encrypted = '...'; $cluster->webhook_secret_encrypted = '...'; $cluster->save();` (padrão `Create.php`/`Edit.php`). Não reabrir `$fillable`.
**Validação**: `php artisan test tests/Feature/Audit/AuditLogTest.php`

### Task F14.3 — [FIX] RotateSecretTest factory contract

**Finding(s)**: QA-F14-002 (HIGH)
**Arquivo(s)**: `tests/Feature/ClusterServers/RotateSecretTest.php`, opcional `database/factories/ClusterServerFactory.php` (state `withoutWebhookHistory()`)
**ANTES**: Factory `afterCreating` já cria `WebhookSecretHistory` v1; testes fazem seed manual ou esperam 0 registros.
**DEPOIS**: Remover seed redundante nos testes de rotação feliz; cenários “sem histórico” usam state `withoutWebhookHistory()` na factory.
**Validação**: `php artisan test tests/Feature/ClusterServers/RotateSecretTest.php`

### Task F14.4 — [ISSUE-039] Fechar CI findings

**Arquivo(s)**: `docs/sistema/ci-issues/CI-FAIL-*.md`
**DEPOIS**: Marcar findings CI como corrigidos; confirmar run verde pós-push.
**Validação**: `gh run list --branch main --limit 1` → success

---

## Sprint F15 — AuthZ ApiKey (scopes + binding tenant)

> Categoria: F
> Status: **concluída** (PR #114 mergeada 2026-06-17; validation R2 APROVADA)
> Gate: `ApiKey.scopes` enforced; tenant binding em `/api/customers/{customer}/*`; teste negativo 403 cross-tenant; findings SEC-V1-001 corrigido
> Gerado via `/rock` + `/pmo fix` em 2026-06-16. Fonte: ISSUE-037 + SEC-V1-001 + ADR `.arch-panel/panel/final.md` §2.1
> review: senior+security (IDOR latente / pré-requisito API v1)
> Desbloqueia: ISSUE-038 Sprint 0 → Sprint **N30**

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | F15.1 — [FIX] Expor `ApiKey` resolvida no request após auth `api-key` (request attribute + helper) | `laravel-api` | — |
| [x] | M | F15.2 — [FIX] Middleware `EnsureApiKeyScope` + catálogo de scopes por rota (`customers:read`, `customers:write`, `lifecycle:write`, etc.) | `laravel-api` | F15.1 |
| [x] | M | F15.3 — [FIX] Migration `allowed_tenant_slugs` (json nullable) + middleware `EnsureTenantBinding` em rotas `customers/{customer}/*` | `laravel-migration` | F15.1 |
| [x] | M | F15.4 — [FIX] Testes negativos: chave sem scope → 403; chave tenant A em slug B → 403 | `laravel-testing` | F15.2–F15.3 |
| [x] | P | F15.5 — [ISSUE-037] AuditLog com `api_key_id` em ações via Bearer; atualizar FINDINGS/ISSUES | `/git` | F15.1–F15.4 |

### Task F15.1 — Bind ApiKey no request

**Finding(s)**: SEC-V1-001 (HIGH)
**Arquivo(s)**: `app/Providers/AppServiceProvider.php`, novo `app/Http/Middleware/AttachApiKeyToRequest.php` ou equivalente
**ANTES**: Guard `api-key` retorna só `Operator`; `ApiKey.scopes` nunca consultado.
**DEPOIS**: Após resolver chave, `request()->attributes->set('api_key', $apiKey)`; helper `currentApiKey(): ?ApiKey`.
**Validação**: unit test do helper + smoke auth Bearer

### Task F15.2 — Scope enforcement

**Finding(s)**: SEC-V1-001, SEC-F004
**Arquivo(s)**: `routes/api.php`, middleware `EnsureApiKeyScope`, `app/Modules/Core/Enums/ApiKeyScope.php` (ou const array)
**ANTES**: Qualquer chave válida acessa todas as rotas autenticadas.
**DEPOIS**: Rotas customer/lifecycle exigem scopes declarados; `null` scopes em chave interna = full access (backward compat operadores) OU deny-by-default — **decisão: deny-by-default para rotas com scope declarado; chaves UI admin com scopes explícitos `*` ou lista completa**.
**Validação**: `php artisan test` scope tests

### Task F15.3 — Tenant binding

**Finding(s)**: SEC-V1-001
**Arquivo(s)**: migration, `app/Models/ApiKey.php`, middleware `EnsureTenantBinding`
**ANTES**: IDOR — qualquer chave acessa qualquer `{customer}` slug.
**DEPOIS**: `allowed_tenant_slugs` json; `null` = unrestricted (chaves internas); array não-vazio = allowlist; 403 `forbidden_tenant` se slug fora.
**Validação**: feature test cross-tenant 403

### Task F15.4 — Testes negativos obrigatórios (gate ADR)

**Finding(s)**: SEC-V1-001
**Arquivo(s)**: `tests/Feature/Auth/ApiKeyAuthorizationTest.php`
**DEPOIS**: Parceiro A → tenant B = 403; scope insuficiente = 403; chave interna unrestricted = 200 nos cenários permitidos.
**Validação**: `php artisan test tests/Feature/Auth/ApiKeyAuthorizationTest.php`

### Task F15.5 — Fechar ISSUE-037

**Arquivo(s)**: `docs/FINDINGS.md`, `docs/ISSUES.md`
**DEPOIS**: SEC-V1-001 `corrigido`; ISSUE-037 encaminhada para validação.
**Validação**: `/qa validar F15`

---

## Sprint N30 — ISSUE-038 Sprint 0: API `/api/v1` (aliases + DomainError + spec externo)

> Categoria: N
> Status: **concluída** (PR #115 mergeada 2026-06-17; validation R1 APROVADA; follow-up HIGH em `837173c`)
> Gate: nenhuma resposta `/api/v1/*` contém `subcmd`/`exit_code`/stack trace; chave parceiro A → **403** em tenant B; `redocly lint docs/openapi-external.yaml` 0 errors; endpoints v1 delegam às Actions existentes sem alterar semântica upstream
> Fonte: **ISSUE-038** + ADR `.arch-panel/panel/final.md` Sprint 0 + `docs/CONTRACTS-V1.md` + `docs/openapi-external.yaml`
> review: **senior+security** (superfície externa + IDOR + vazamento protocolo NC)
> Pré-requisito: **F15/ISSUE-037** ✓ (merge PR #114, validation R2 APROVADA)
> Bloqueia: Sprint **N31** (PlatformPort mínimo)
> Fora de escopo N30: extrair `PlatformPort`, saga `/v1/onboarding`, `PUT /v1/tenants/{slug}/branding` (gate D-02), grep gate adapters

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | N30.1 — `DomainError` enum + `RenderDomainError` handler (mapeamento único → HTTP; sem vazamento NC) | `laravel-api` | — |
| [x] | P | N30.2 — Congelar specs: `openapi.yaml` → internal/legacy; `openapi-external.yaml` como contrato externo; nota DOC-001 | `/dev doc` | — |
| [x] | M | N30.3 — Catálogo scopes v1 (`tenants:*`, `apps:write`, `users:write`, `jobs:read`) + grupo rotas `/api/v1` com `api.scope` + `api.tenant` | `laravel-api` | — |
| [x] | M | N30.4 — `routes/api_v1.php` + controllers `Api\V1\*` (aliases finos → Actions existentes, mapa CONTRACTS-V1 §4) | `api-rest-patterns` | N30.1, N30.3 |
| [x] | M | N30.5 — Envelope v1 (`{ data, meta? }`) + FormRequests + Resources sanitizados | `laravel-api` | N30.4 |
| [x] | M | N30.6 — Testes gate ADR: cross-tenant 403 em `/v1/*` + `DomainErrorSanitizationTest` (sem subcmd/exit_code) | `laravel-testing` | N30.4, N30.5 |
| [x] | P | N30.7 — `redocly lint` de `openapi-external.yaml` no CI | `ci-automations` | N30.2 |

### Task N30.1 — DomainError + exception handler

**Estado atual**: Erros API expõem vocabulário NC (`occ_subcmd_not_allowed`, `exit_code`, `subcmd`) e formatos heterogêneos (`{ error: 'not_found' }` vs `JsonResource`). DOC-001 documenta drift com envelope `{ success, message, data }`.
**Estado desejado**: Hierarquia `DomainError` com códigos estáveis (`tenant_not_ready`, `forbidden_scope`, `upstream_unavailable`, etc.) conforme `docs/CONTRACTS-V1.md` §2; handler único para `api/*` e especialmente `api/v1/*`; adapters/actions traduzem exceções upstream **antes** da borda HTTP.
**Fonte(s)**: ISSUE-038, DOC-001, ADR `final.md` Sprint 0, ISSUE-011
**Módulo(s)**: `app/Modules/Core/Domain/`, `bootstrap/app.php`, controllers API existentes (mapeamento mínimo)
**Risco**: MEDIUM — mudança observável em respostas de erro; legado `/api/customers` deve manter compat ou migrar gradualmente com testes de regressão
**Task size**: M (3–5 arquivos)

**executor_prompt**:
Melhoria: Introduzir `DomainError` enum/class + `RenderDomainError` no exception handler.
Contexto: Clientes externos recebem `subcmd`/`exit_code` quando OCC falha (ISSUE-011). ADR Sprint 0 exige sanitização na borda.
Objetivo: Respostas de erro usam `{ error: { code, message, retry_after?, details? } }` com códigos de `CONTRACTS-V1.md` §2; nenhum campo `subcmd`, `exit_code`, `cmd_canonical` na resposta.
Arquivos: `app/Modules/Core/Domain/DomainError.php`, `app/Http/Exceptions/RenderDomainError.php`, `bootstrap/app.php`, mapeamentos em `OccController`/`CustomerLifecycleController` (mínimo para paths v1).
Critério de pronto: `DomainErrorSanitizationTest` prova ausência de termos NC em JSON de erro; códigos HTTP alinhados à tabela CONTRACTS-V1.
reuse_targets:
  - component: `bootstrap/app.php` (render 404/405 JSON existente)
    reuse_as: extend
    convergence_check: rg "shouldRenderJsonWhen" bootstrap/app.php
Cenários de teste:
  1. OCC bloqueado → 403 `capability_not_available` ou `forbidden_scope` sem `subcmd`
  2. Tenant em provisioning → 503 `tenant_not_ready` com `retry_after`
  3. Regressão: 404 JSON em `/api/v1/tenants/inexistente` mantém formato DomainError

### Task N30.3 — Scopes v1 + grupo de rotas `/api/v1`

**Estado atual**: F15 implementou `EnsureApiKeyScope` + `EnsureTenantBinding` em rotas legado (`customers:write`, `lifecycle:write`, etc.). Scopes v1 (`tenants:read`, `tenants:write`, …) ainda não existem.
**Estado desejado**: Matriz scope→rota de `CONTRACTS-V1.md` §3 aplicada em todo `/api/v1/*`; binding tenant em rotas `{slug}`; sessão web de operador interno continua funcionando onde aplicável.
**Fonte(s)**: ISSUE-038, CONTRACTS-V1 §3, F15 (SEC-V1-001)
**Módulo(s)**: `app/Http/Middleware/EnsureApiKeyScope.php`, `routes/api_v1.php`, `bootstrap/app.php`
**Risco**: MEDIUM — authz incorreta = IDOR; testes negativos obrigatórios
**Task size**: M

**executor_prompt**:
Melhoria: Registrar scopes v1 e grupo middleware para prefixo `/api/v1`.
Contexto: ADR exige `VerifyExternalPrincipal` — reutilizar F15 (`api.scope`, `api.tenant`) em vez de duplicar lógica.
Objetivo: Cada rota v1 declara scope conforme CONTRACTS-V1 §3; `DELETE /v1/tenants/{slug}` usa `api.tenant` com parâmetro `slug` (mesmo padrão F15.6 fix).
Arquivos: `EnsureApiKeyScope.php` (ou `config/api-scopes.php`), `routes/api_v1.php`, registro em `bootstrap/app.php` ou `routes/api.php`.
Critério de pronto: chave com `allowed_tenant_slugs: ['a']` → 403 em `/v1/tenants/b/*`; chave sem `tenants:write` → 403 em POST tenants.
reuse_targets:
  - component: `app/Http/Middleware/EnsureApiKeyScope.php`
    reuse_as: extend
    convergence_check: rg "api.scope" routes/api.php
  - component: `app/Http/Middleware/EnsureTenantBinding.php`
    reuse_as: extend
    convergence_check: rg "api.tenant" routes/api.php
Cenários de teste:
  1. Cross-tenant 403 (gate ADR)
  2. Scope insuficiente 403 `forbidden_scope`
  3. Chave unrestricted interna → 200 nos cenários permitidos

### Task N30.4 — Rotas e controllers V1 (aliases finos)

**Estado atual**: Integração externa usa `/api/customers/*`, `/api/queue/*`, `/occ/*`. Não existe `/api/v1/*` implementado (spec em `openapi-external.yaml` é DRAFT).
**Estado desejado**: `routes/api_v1.php` com endpoints do mapa CONTRACTS-V1 §4 delegando a `ProvisionCustomerAction`, `RemoveCustomerAction`, `LifecycleAsyncAction`, `JobController::show` — **sem** duplicar lógica de negócio.
**Fonte(s)**: ISSUE-038, CONTRACTS-V1 §4, `openapi-external.yaml`
**Módulo(s)**: `routes/api_v1.php`, `app/Http/Controllers/Api/V1/`
**Risco**: MEDIUM — divergência v1 vs legado se aliases não forem finos
**Task size**: M (4–6 arquivos)

**executor_prompt**:
Melhoria: Implementar controllers V1 como aliases HTTP sobre Actions existentes.
Contexto: Sprint 0 ADR proíbe extrair PlatformPort; valor = namespace estável `/api/v1` sem big-bang.
Objetivo: Endpoints listados em CONTRACTS-V1 §4 funcionais; `POST /v1/tenants` → mesmo outcome que `POST /api/customers`; `GET /v1/jobs/{id}` sanitiza campos NC no Resource.
Arquivos: `routes/api_v1.php`, `TenantController.php`, `TenantUserController.php`, `TenantAppsController.php`, `JobV1Controller.php` (nomes conforme convenção do projeto).
Critério de pronto: smoke Pest por endpoint v1; nenhuma chamada SSH nova nos controllers (só Actions).
reuse_targets:
  - component: `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
    reuse_as: call
    convergence_check: rg "ProvisionCustomerAction" app/Http/Controllers
  - component: `routes/api.php` (padrão middleware/throttle)
    reuse_as: copy_pattern
    convergence_check: rg "throttle:120" routes/api.php
Cenários de teste:
  1. POST tenant → 202 + `job_id` (async)
  2. POST users em tenant provisioning → 503 `tenant_not_ready`
  3. GET job → 200 sem campos `cmd`/`exit_code` no JSON v1

### Task N30.5 — Envelope v1 + FormRequests

**Estado atual**: Respostas misturam `{ job_id }` cru e `JsonResource` sem `meta.status_url`.
**Estado desejado**: Sucesso async `{ data, meta: { job_id, status_url } }`; sucesso sync `{ data }`; alinhado a `openapi-external.yaml` e CONTRACTS-V1 §8.
**Fonte(s)**: DOC-001, ISSUE-038, openapi-external.yaml
**Módulo(s)**: `app/Http/Resources/V1/`, `app/Http/Requests/V1/`
**Risco**: LOW–MEDIUM — breaking para quem já consumisse draft (nenhum em prod ainda)
**Task size**: M

**executor_prompt**:
Melhoria: Padronizar envelope de sucesso/erro na borda v1.
Objetivo: Resources V1 encapsulam `Customer`, `Job`; FormRequests validam slug/username conforme regras existentes (reuso de `ProvisionCustomerRequest` rules onde possível).
Arquivos: `app/Http/Resources/V1/*`, `app/Http/Requests/V1/*`, trait `RespondsWithV1Envelope` (se necessário).
Critério de pronto: responses batem exemplos do spec externo; erros passam por DomainError handler (N30.1).

### Task N30.6 — Testes gate ADR

**Estado atual**: `ApiKeyAuthorizationTest` cobre rotas legado F15; sem cobertura `/v1/*`.
**Estado desejado**: Gate ADR Sprint 0 verificável em CI: 403 cross-tenant + sanitização erro + smoke v1.
**Fonte(s)**: ADR `final.md` critérios Sprint 0, F15.4
**Módulo(s)**: `tests/Feature/Api/V1/`, `tests/Feature/Auth/ApiKeyAuthorizationTest.php`
**Risco**: LOW
**Task size**: M

**executor_prompt**:
Melhoria: Suite de testes que fecha o gate da Sprint N30.
Objetivo: `ApiV1AuthorizationTest` (cross-tenant + scopes v1); `DomainErrorSanitizationTest` (regex negativa para subcmd/exit_code); smoke por endpoint do mapa §4.
Arquivos: `tests/Feature/Api/V1/ApiV1AuthorizationTest.php`, `tests/Feature/Api/V1/DomainErrorSanitizationTest.php`, `tests/Feature/Api/V1/TenantLifecycleV1Test.php`.
Critério de pronto: `php artisan test tests/Feature/Api/V1` verde; gate ADR reproduzível localmente.

### Quality Brief (Sprint N30)

> PATTERN-001 (Decision #187): auditoria R1 senior+security; artefato de brief conforme gate pre-commit quando aplicável.

- **Review**: senior+security (superfície externa, IDOR, vazamento protocolo NC)
- **PR**: #115 mergeada em `main`; follow-up segurança `837173c`
- **Gate ADR Sprint 0**: ✓ respostas `/api/v1/*` sem `subcmd`/`exit_code`; cross-tenant 403; `redocly lint` 0 errors; aliases delegam às Actions existentes
- **Findings R1**: 2 HIGH detectados e corrigidos in-sprint — `CQ-N30-001` (IDOR jobs), `SEC-N30-001` (exit_code leak provision)
- **CI** (`837173c`): Lint, Test/Pest, composer audit, OpenAPI Redocly — verde
- **Testes**: ~35 Pest em `tests/Feature/Api/V1/`
- **Resultado**: **APROVADA** (validation_gate_qa)

---

## Roadmap ISSUE-038 — Fases posteriores (stubs)

> Detalhamento completo via `/pmo plan` quando N30 estiver validada. Fonte: ADR `final.md` §4.

| Sprint | Fase ADR | Gate resumido | Bloqueio |
|--------|----------|---------------|----------|
| **N31** | Fase 1 — PlatformPort mínimo | `PUT /v1/tenants/{slug}/branding` 100% via port; characterization tests | N30 + D-02 (branding) |
| **N32** | Fase 2 — Ondas + observabilidade | grep gate adapters; `correlation_id`; alertas job preso | N31 |
| **N33** | Fase 3 — Despublicar `/occ/*` | `/occ/*` fora do spec externo; capabilities mutação via port | N32 + D-02 |
| **N34** | Fase 4 — Saga onboarding | `POST /v1/onboarding` idempotente + runbook parcial | N33 + D-02 resolvido |

---

| Data       | Versao | Alteracao                                                                                        | Autor                                                        |
| ---------- | ------ | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------ |
| 2026-06-17 | 0.27   | Sprint N30 concluída — ISSUE-038 Sprint 0 (`/api/v1` + DomainError + openapi-external); PR #115; 2 HIGH R1 corrigidos (`CQ-N30-001`, `SEC-N30-001`). | sprint-finalizer |
| 2026-06-17 | 0.26   | Sprint N30 planejada — ISSUE-038 Sprint 0 (`/api/v1` aliases + DomainError + spec externo); stubs N31–N34; F15 índice → concluída. | `/pmo plan` |
| 2026-06-02 | 0.24   | Sync FINDINGS + ROADMAP: F5.11 `[x]`, F5/F11 no índice; F9 APROVADA R1; F10/F12 notas; F5 `/qa validar R3` pendente. | /pmo sync |
| 2026-05-28 | 0.23   | Sprint F13 CONCLUÍDA — branding no `create` corrigido; ProvisionTest 16 passed; validação senior+qa APROVADA R1. | /fix F13 |
| 2026-05-28 | 0.22   | Sprint F13 adicionada — ISSUE-019 (`create` deve enviar branding logo/background via `branding.*_data_url` ou `--staging-id`). | /fix (interativo) |
| 2026-05-27 | 0.21   | Sprint F12 CONCLUÍDA — `SshClient` normaliza `ConnectionClosedException` do phpseclib durante `exec()`/stdin; `SshClientTest` 13 passed. | /pmo sprint F12 |
| 2026-05-27 | 0.20   | Sprint F12 adicionada — ISSUE-020 (`SshClient` normaliza `ConnectionClosedException` do phpseclib durante readiness probe). | /fix (interativo) |
| 2026-05-24 | 0.19   | Sprint F11 adicionada — ISSUE-018 HIGH (slug reuse) + 5 MEDIUM F5 (CQ-F5-002/003, QA-F5-006/008/010). N1 HIGH já em F7. | /fix (interativo) |
| 2026-05-24 | 0.18   | Sprint F10 adicionada — ISSUE-014 (JobLogFetcher argv introspection; corrige logs vazios ISSUE-009) | /pmo sprint |
| 2026-05-24 | 0.17   | Sprint F9 adicionada — ISSUE-012 (404 `/api/*` retorna JSON sem depender de Accept header); filtro HIGH-only | /fix (interativo)                              |
| 2026-05-15 | 0.5    | Sprint F3 adicionada — 8 findings LOW pos-D8 (D4-F009, D4-F005, DBA-F010/F011/F012, SEC-F013/F014/F015) | /fix (interativo)                               |
| 2026-05-18 | 0.6    | Sprint N1 adicionada — ISSUE-001 (sync webhook secret com upstream via SSH ao criar/rotacionar cluster) | /pmo new (interativo, 2 revisões de design)            |
| 2026-05-20 | 0.7    | Sprint N2 adicionada (retroativa) — ISSUE-005 (log debug do payload do webhook em APP_ENV=local)       | /pmo new (interativo)                                  |
| 2026-05-20 | 0.8    | Sprint F5 adicionada — ISSUE-006 (tradutor cmd → CLI argv; fix lifecycle async; bug postmortem HIGH)   | /fix (interativo)                                              |
| 2026-05-20 | 0.9    | Sprint F5 CONCLUÍDA — 7/7 tasks; brief `docs/.briefs/F5.brief.md`; 301/307 testes; PASS_WITH_FINDINGS    | /pmo sprint F5                                                 |
| 2026-05-20 | 0.10   | Sprint F5 expandida com F5.8/F5.9/F5.10 (R1 follow-up — PROC-012 corrige in-sprint após /qa validar R1 REPROVADA) | /pmo sprint F5                                                 |
| 2026-05-20 | 0.11   | Sprint F5 expandida com F5.11 (R2 follow-up — same-path fix para QA-F5-019; PROC-012 corrige in-sprint após /qa validar R2 REPROVADA) | /pmo sprint F5                                                 |
| 2026-05-21 | 0.12   | Sprint F6 adicionada — ISSUE-008 (forgot-password broker nativo Laravel) + ISSUE-009 (logs de job via SSH pull pós job.finished); originada de `/qa debug` | /fix (interativo)                                              |
| 2026-05-21 | 0.13   | Sprint F7 adicionada — 3 findings HIGH pendentes da N1 (`CQ-N1-001`, `CQ-N1-002`, `QA-N1-001`) | /fix (interativo)                                              |
| 2026-05-23 | 0.14   | Sprint F8 adicionada — ISSUE-010 / QA-DYN-021 (readiness gate pós-provision; callback prematuro CRITICAL) | /fix — ISSUE-010                                              |
| 2026-05-23 | 0.16   | Sprint F8 R1 follow-up (F8.7–F8.10) — timeout wall-clock 20min, testes probe/gate/sync, OccPanel UX | /qa validar F8 R1                                           |
