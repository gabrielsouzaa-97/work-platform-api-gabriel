# Roadmap Tecnico — mework360-deployer

> Gerado em: 2026-05-07
> Fase: 9 — Planejamento Tecnico
> Baseado em: docs/REQUIREMENTS.md v0.2 + docs/ARCHITECTURE.md v0.2 + docs/openapi.yaml v2.0 + docs/db-schema.dbml + docs/DATABASE.md
> Status: Proposta
> Modo de execucao: Pipeline / autopilot (`/jarvis pipeline`)

---

## Resumo

| Metrica | Valor |
|---------|-------|
| Total de tarefas | 44 |
| Total de sprints | 8 (todas categoria D) |
| Tarefas P (atomicas) | 25 |
| Tarefas M (com executor_prompt) | 19 |
| Tarefas G | 0 (proibido — decompor) |
| Tarefas com `critica: true` (Best-of-N) | 3 (D2.1 SshClient, D5.1 Webhook receiver, D6.2 Provisionar customer) |
| Caminho critico | 6 sprints sequenciais (D1 → D2 → D4 → D5 → D6 → D8) |
| Modulos cobertos | Core, ClusterServers, Auth, Audit, Jobs, Customers |

---

## Errata — SSH API Reference (2026-05-13)

> Documentacao autoritativa `docs/SSH API Reference — Nextcloud SaaS.md` revelou divergencias em relacao ao REQUIREMENTS v0.2. Aplicadas como correcoes inline nas sprints afetadas. Resumo:

| # | Correcao | Sprints afetadas |
|---|---------|-----------------|
| E1 | Binario correto: `nextcloud-manage` (nao `manage.sh`). Symlink em `/usr/local/bin/nextcloud-manage`. SSH user: `root` (nao `ncsaas-api`). | D5, D6, D7 |
| E2 | **StateTranslator corrigido** (D2 bug): upstream usa `queued/running/done/failed/cancelled`; anterior usava `pending/running/done/error/aborted`. Unico rename: `done → success`. | D2 (corrigido), D5 |
| E3 | `create` exige `<domain>` posicional: `nextcloud-manage <client> <domain> create [flags]`. Outros comandos usam `_` no lugar do dominio. | D6 |
| E4 | `remove`: flag correta e `--force` (booleano). `--confirm=<client>` existe como alternativa mas nao e o padrao. | D6 |
| E5 | `list` e `status` **nao suportam `--json`** — retornam texto livre. Parsing por regex/linha obrigatorio. | D6 |
| E6 | Exit codes adicionados ao SshClient: `5` = validacao (validationFailed) e `99` = nao implementado (notImplemented). | D5, D6 |
| E7 | Payload do webhook upstream (secao 7 SSH API Ref): `{job_id, state, cmd, client, exit_code, finished_at}`. Campo `summary` **nao existe** no payload webhook (existe apenas em `job status`). | D5 |
| E8 | `occ-exec` usa namespace syntax sem domain: `nextcloud-manage <client> occ-exec <subcmd>`. | D7 |
| E9 | Callback URL deve ser `https://` (IPs RFC 1918 rejeitados pelo upstream — SSRF defense). Em staging, garantir HTTPS ou usar ngrok/tunnel. | D6 |

---

## Indice de Sprints

> Agentes: leiam ESTE indice primeiro. So facam Read da secao completa se precisarem de notas tecnicas ou detalhes de tasks.

| Sprint | Categoria | Gate (resumo) | Status | Tasks | Modulos | Resumo | Linhas |
|--------|-----------|---------------|--------|-------|---------|--------|--------|
| D1 | D | App sobe via docker-compose; migrations das 8 tabelas aplicadas; smoke /health 200 | concluida | 6 | infra, database | Foundation: scaffold Laravel + DB + smoke test | 90-220 |
| D2 | D | SshClient executa comando mockado; tradutores cobrem 15 verbs + 5 estados; slug `_` rejeitado 422 | concluida | 5 | Core | Core: SshClient + Tradutores + Slug Validator | 221-470 |
| D3 | D | Admin convida operador → email enviado → operador define senha → loga; suporte sem opcoes de provisionar/remover | concluida | 5 | Auth | Auth: Login + cadastro de operadores (F1) | 471-680 |
| D4 | D | Admin cria cluster_server (encrypted); rotate webhook secret aceita ambos por 24h; audit log registra acoes | pendente | 6 | ClusterServers, Audit | ClusterServers (F9) + Audit (F7 base) | 681-960 |
| D5 | D | Webhook HMAC valido atualiza estado; HMAC invalido 401 + alerta; replay > 1h rejeitado | pendente | 5 | Jobs | Jobs: Webhook receiver (F8) + listagem fila (F5) | 961-1180 |
| D6 | D | Marina provisiona customer via UI → SSH → webhook conclui em <5min; slug `_` 422; anexo 800KB via SCP; remove com --backup-first | pendente | 6 | Customers | Customers: provisionar + listar + remover (F2+F3+F4+F10) | 1181-1490 |
| D7 | D | Operador define quota via UI (sync 60s); cria user via async (job_id retornado, webhook conclui) | pendente | 5 | Customers, Jobs | OCC essenciais: sync passthrough + async lifecycle (F6) | 1491-1700 |
| D8 | D | CI verde; auditorias sem CRITICAL/HIGH; staging valida fluxo Marina end-to-end; retention 12m ativo | pendente | 6 | todos | Polish: Audit retention (F7) + Auditorias + Deploy staging | 1701-1900 |

---

## Estrategia de Auditoria

> Modo: **Pipeline / autopilot**
> Auditoria e o **unico gate de qualidade** entre sprints (sem revisao humana). Niveis foram escolhidos conservadoramente.

| Sprint | Review | Motivo |
|--------|--------|--------|
| D1 | `skip` | Fundacao: scaffold + migrations + configs Docker. Testes Pest bastam (sem logica de negocio). |
| D2 | `senior+qa` | Core: integracao externa (SSH), traducoes condicionais. Foundation tecnica. Senior + QA Fase 1 (sem triage completa). |
| D3 | `senior+qa` | Auth + sessoes + email convite. Dados sensiveis (senhas, tokens). |
| D4 | `senior+qa` | ClusterServers com Encrypted Storage de SSH keys + webhook secrets. Compliance critica. |
| D5 | `senior+qa` | Webhook receiver com HMAC-SHA256 + replay protection. Vetor de ataque #1. |
| D6 | `senior+qa` | Customers: operacoes destrutivas (remove com backup), SSH + SCP staging, idempotency. |
| D7 | `senior+qa` | OCC essenciais: multiplos endpoints sensiveis (quota, branding, lifecycle). |
| D8 | `comprehensive` | Ultima sprint D — pre-deploy. Triage completa + auditores DBA + Security + Performance + Senior. |

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | P | 1.1 — Scaffold Laravel 12 (`composer create-project`) + `.env.example` aplicado + `config/database.php` apontando para Postgres do compose | `laravel-docker` | — |
| [x] | P | 1.2 — Validar `docker-compose up -d` sobe os 5 services com healthchecks verdes | `laravel-docker` | 1.1 |
| [x] | M | 1.3 — Criar migrations das 8 tabelas conforme `docs/db-schema.dbml` | `laravel-migration` | 1.2 |
| [x] | P | 1.4 — Criar Models Eloquent base com casts (`encrypted` para SSH keys/webhook secrets, `array` para JSONB) | `laravel-migration` | 1.3 |
| [x] | P | 1.5 — Criar Seeder `DatabaseSeeder` (admin operator + 1 cluster_server fake para dev) | `laravel-migration` | 1.4 |
| [x] | P | 1.6 — Configurar Pest (`pest --init`) + factories minimas + smoke test `GET /health retorna 200` | `laravel-testing` | 1.4 |

**Notas tecnicas (tarefas M):**

<details>
<summary>1.3 — Criar migrations das 8 tabelas conforme db-schema.dbml</summary>

#### Mini Design Doc

**Escopo**: criar as 8 migrations do schema PostgreSQL. NAO inclui logica de negocio nos models (apenas casts e relacionamentos diretos).

**Componentes envolvidos**:
- `database/migrations/`: 8 arquivos timestamped
- `app/Models/`: 8 models Eloquent (apenas estrutura, sem queries)

**Decisoes de design**:
1. Habilitar extensao `uuid-ossp` (`uuid_generate_v4()`) na primeira migration — porque PKs sao UUID v4
2. Usar `ulid()` ou `Str::uuid()` no PHP para PKs — porque Postgres `gen_random_uuid()` requer pgcrypto e a extensao uuid-ossp ja vem habilitada na imagem oficial Postgres 16

**Riscos**:
1. Esquecer de rodar `CREATE EXTENSION` antes do uso → migration falha. Mitigacao: primeira migration habilita extensao em `Schema::getConnection()->statement()`.
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
- **Abordagem**: traduzir literalmente cada `Table` do DBML em uma migration Laravel. Para PKs UUID, usar `$table->uuid('id')->primary()`. Para `customers.slug` (PK varchar 64), usar `$table->string('slug', 64)->primary()`. JSONB → `$table->jsonb()`. Soft delete → `$table->softDeletes()` quando o DBML mencionar `deleted_at`.
- **Decisoes**: extensao `uuid-ossp` habilitada na primeira migration (ja documentado em DATABASE.md secao 8.4). Casts: `encrypted` para `ssh_private_key_encrypted`, `webhook_secret_encrypted`, `secret_encrypted`. `array` para colunas `jsonb` (`branding_meta`, `payload_sanitized`, `summary`, `payload`, `scopes`).
- **Edge cases**: customer.slug e PK varchar — Eloquent precisa de `protected $primaryKey = 'slug'; public $incrementing = false; protected $keyType = 'string';`. webhook_secret_history sem soft delete (auditavel; rotacao mantem historico). audit_logs sem `updated_at` nem soft delete (append-only por design).
- **Anti-patterns**: NAO usar `bigIncrements()` para nenhum PK — projeto inteiro e UUID. NAO permitir cascade delete em `customers.cluster_server_id` (proibir deletar cluster com customers ativos via FK `restrict`). NAO usar `gen_random_uuid()` no Postgres (preferir `uuid_generate_v4()` do uuid-ossp).
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

    Ordem (timestamps incrementais 000001-000009):
    1. `enable_uuid_extension`: `Schema::getConnection()->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"')`
    2. `create_operators_table`: PK uuid (default `uuid_generate_v4()`), email unique, role (default 'operador'), password_hash, status (default 'active'), last_login_at nullable, timestamps + softDeletes. Indices: email, role.
    3. `create_cluster_servers_table`: PK uuid, name, ssh_host, ssh_port (int default 22), ssh_user (default 'ncsaas-api'), ssh_private_key_encrypted (text), webhook_secret_encrypted (text), webhook_secret_version (int default 1), nextcloud_version (nullable), schema_version (int default 1), status (default 'active'), last_health_at nullable, timestamps + softDeletes. Indice: status.
    4. `create_customers_table`: PK varchar(64) `slug`, FK uuid `cluster_server_id` ref cluster_servers.id (onDelete: 'restrict'), domain, status (default 'provisioning'), branding_meta (jsonb nullable), last_sync_at nullable, timestamps + softDeletes. Indices: cluster_server_id, status.
    5. `create_jobs_table`: PK uuid `job_id`, FK varchar `customer_slug` ref customers.slug, FK uuid `cluster_server_id` ref cluster_servers.id, cmd_canonical, job_type, state (default 'queued'), idempotency_key uuid unique, payload_sanitized jsonb, summary jsonb, exit_code int nullable, queued_at/started_at/finished_at/callback_received_at/last_poll_at todos nullable, timestamps. Indices: customer_slug, cluster_server_id, state, job_type.
    6. `create_audit_logs_table`: PK uuid, FK uuid actor_id ref operators.id, action, resource_type, resource_id, payload jsonb, FK uuid cluster_server_id (nullable), FK uuid job_id (nullable), ip varchar(45), user_agent text, created_at SOMENTE (sem updated_at, sem softDeletes — append-only). Indices: actor_id, action, resource_type, cluster_server_id, job_id, created_at.
    7. `create_webhook_secret_history_table`: PK uuid, FK uuid cluster_server_id, secret_encrypted text, version int, valid_from timestamp, valid_until timestamp nullable, timestamps. Indice: cluster_server_id.
    8. `create_idempotency_keys_table`: PK uuid `key`, cmd, args_hash, customer_slug nullable FK ref customers.slug, job_id uuid nullable FK ref jobs.job_id, expires_at timestamp, timestamps. Indices: customer_slug, job_id, expires_at.
    9. `create_api_keys_table`: PK uuid, name, token_hash unique, scopes jsonb nullable, last_used_at nullable, revoked_at nullable, timestamps. Indice: token_hash.

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
> Gate: SshClient executa comando contra um stub de SSH server e retorna stdout/stderr/exit_code parseados; JobTypeTranslator cobre os 15 verbs do REQUIREMENTS §3 + StateTranslator cobre os 5 estados (queued/running/success/failed/cancelled); slug com `_` ou maiusculas e rejeitado com 422 antes de qualquer chamada SSH
> review: senior+qa

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | 2.1 — Implementar `SshClient` com pool + timeouts + retry exponencial + suporte a `--payload-stdin` e SCP staging | `laravel-api` | 1.4 (Models) `critica: true` |
| [x] | M | 2.2 — `JobTypeTranslator` (15 verbs cmd ↔ job_type) com testes unitarios full coverage | `laravel-api` | 1.4 |
| [x] | M | 2.3 — `StateTranslator` (5 estados upstream → canonical) com testes unitarios | `laravel-api` | 1.4 |
| [x] | P | 2.4 — `Rules\Slug` Form Request rule (`^[a-z0-9-]+$`, max 64) + Form Request `ProvisionCustomerRequest` reutilizando | `laravel-api` | — |
| [x] | P | 2.5 — Testes Feature do Core: SshClient mockado contra fixtures JSON; tradutores full coverage; Slug rule com 8 inputs validos/invalidos | `laravel-testing` | 2.1, 2.2, 2.3, 2.4 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D2)
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
- **executor_prompt**: |
    ### Quality Brief (Sprint D2)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D2)

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | 3.1 — Componente Livewire `Auth\Login` (form + sessao + rate limit + lockout 5/15min) | `laravel-livewire` | 1.4 |
| [x] | M | 3.2 — Componente Livewire `Operators\Index` + `Operators\Create` (admin only) + envio de email convite com signed URL expirando em 48h | `laravel-livewire` | 1.4 |
| [x] | P | 3.3 — Middleware `EnsureRole` (admin/operador/suporte) + Gate `manage-operators` em `AuthServiceProvider` | `laravel-livewire` | 3.1 |
| [x] | P | 3.4 — Componente Livewire `Auth\AcceptInvite` (recebe signed URL, valida nao-expirado, define senha) + Logout | `laravel-livewire` | 3.2 |
| [x] | P | 3.5 — Testes Feature: login valido/invalido, lockout 5/15min, role enforcement, convite valido/expirado, logout encerra sessao | `laravel-testing` | 3.1, 3.2, 3.3, 3.4 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D3)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D3)

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [x] | M | 4.1 — Module ClusterServers: CRUD via Livewire (admin only) — Index + Create + Edit com encrypted casts ssh_private_key + webhook_secret | `laravel-livewire` | 1.4, 3.3 |
| [ ] | M | 4.2 — Test connection (`SshClient::run($cluster, 'echo ok')`) com captura de stdout/stderr/exit_code + UI status badge | `laravel-livewire` | 4.1, 2.1 |
| [ ] | M | 4.3 — Rotate webhook secret com grace period 24h (insere registro em `webhook_secret_history`, `valid_until = now()->addDay()` na versao antiga) | `laravel-livewire` | 4.1, 1.4 |
| [ ] | P | 4.4 — Cron `php artisan cluster:health-check` (Schedule a cada 5min) atualiza `cluster_servers.status` + `last_health_at` | `laravel-api` | 4.2 |
| [ ] | P | 4.5 — Module Audit: `AuditLog` observer + sanitizador (mask `password|token|secret|key` em payload) + Livewire `Audit\Index` paginada com filtros | `laravel-livewire` | 1.4, 3.3 |
| [ ] | P | 4.6 — Testes Feature ClusterServers (CRUD + test conn + rotate) + Audit (sanitizacao + filtros) | `laravel-testing` | 4.1, 4.2, 4.3, 4.5 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D4)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D4)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D4)

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [ ] | M | 5.1 — Webhook receiver `POST /api/jobs/hook` com middleware `VerifyWebhookHmac` (assinatura + IP whitelist + replay 1h + multi-secret grace) | `laravel-api` | 4.3, 2.3 `critica: true` |
| [ ] | M | 5.2 — Endpoint `GET /queue` + Livewire `Jobs\Index` (paginacao, filtros state/job_type/customer, deep-link) | `laravel-api` | 1.4 |
| [ ] | P | 5.3 — Endpoints REST `GET /queue/stats` (counts por state) + `GET /queue/{id}` (detalhes do job) | `laravel-api` | 5.2 |
| [ ] | P | 5.4 — Cancel job: action `CancelJobAction` chama `nextcloud-manage job <id> cancel --json` via SshClient | `laravel-api` | 5.2, 2.1 |
| [ ] | P | 5.5 — Testes Feature webhook (HMAC valido/invalido, replay, multi-secret) + queue endpoints + cancel | `laravel-testing` | 5.1-5.4 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D5)
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
- **executor_prompt**: |
    ### Quality Brief (Sprint D5)

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [ ] | M | 6.1 — Listar customers (replica local) + sync sob demanda + cron diario `php artisan customers:sync` | `laravel-livewire` | 1.4, 2.1 |
| [ ] | M | 6.2 — Provisionar customer (Livewire form + endpoint POST /customers + SSH `manage.sh create --async --idempotency-key --callback` + SCP staging > 256KB) | `laravel-livewire` | 6.1, 2.1, 5.1 `critica: true` |
| [ ] | M | 6.3 — Remover customer (modal forte com slug confirm + endpoint DELETE + SSH `nextcloud-manage <client> _ remove --force --backup-first --async --json`) | `laravel-livewire` | 6.2 |
| [ ] | P | 6.4 — Detalhes do customer (Livewire `Customers\Show`) com aba Jobs, OCC, Branding, Audit timeline | `laravel-livewire` | 6.1 |
| [ ] | M | 6.5 — Polling fallback: command `php artisan jobs:poll-stuck` (Schedule a cada 5min) busca jobs `running` ha > 60s sem callback e chama `nextcloud-manage job <id> status --json` | `laravel-api` | 2.1, 1.4 |
| [ ] | P | 6.6 — Testes Feature provisionar (slug invalido 422 antes SSH, idempotency 409, anexo SCP staging, webhook conclui) + remove + sync | `laravel-testing` | 6.1-6.5 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D6)

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
- **Validacoes**: slug regex `^[a-z0-9-]+$` max 64; cluster_id UUID + active; domain valid hostname; logo/background image/png ou image/jpeg, max 5MB cada; apps array de strings (validate against whitelist?  P task — aceitar qualquer string por ora).
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
- **executor_prompt**: |
    ### Quality Brief (Sprint D6)
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
- **executor_prompt**: |
    ### Quality Brief (Sprint D6)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D6)

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [ ] | M | 7.1 — OCC sync passthrough: endpoints quota (set/audit/options/all/default), branding, maintenance, files:rescan, app:enable individual via `nextcloud-manage <client> occ-exec <subcmd>` (timeout 60s) ← [E1,E8] sem domain `_` | `laravel-api` | 2.1 |
| [ ] | M | 7.2 — Lifecycle user/group async: POST /customers/{c}/users, /groups, /apps/enable, /apps/disable via `nextcloud-manage <client> user create --async --json` etc. (Feature O.2) ← [E1] | `laravel-api` | 2.1, 5.1 |
| [ ] | P | 7.3 — Endpoints DELETE async: DELETE /users/{username}, /groups/{group} | `laravel-api` | 7.2 |
| [ ] | P | 7.4 — Livewire `Customers\OccPanel` com abas Quota / Branding / Maintenance / Apps / Users / Groups | `laravel-livewire` | 7.1, 7.2 |
| [ ] | P | 7.5 — Testes Feature OCC sync (timeout, mime, validacao) + async lifecycle (idempotency, webhook conclui) | `laravel-testing` | 7.1-7.4 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D7)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D7)

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

| Status | Tamanho | Tarefa | Skill/Command | Depende de |
|--------|---------|--------|---------------|------------|
| [ ] | M | 8.1 — Audit log retention 12m: command `audit:purge` (mensal) + indice composto created_at + comparativo de volume | `laravel-migration` | 4.5 |
| [ ] | M | 8.2 — Testes E2E críticos via Pest browser (Marina provisiona, Rafael cancela job, Sofia reseta quota) | `laravel-testing` | 6.2, 5.4, 7.1 |
| [ ] | P | 8.3 — Auditoria DBA (revisar indices, queries N+1, plano de explain, eager loading) | `auditoria-dba` | 8.1 |
| [ ] | P | 8.4 — Auditoria Security (CSRF, mass assignment, raw queries, secrets em log) | `auditoria-seguranca` | 8.1 |
| [ ] | P | 8.5 — Documentacao operacional: README + runbook (rotate secret, sync, deploy) + atualizar `docs/CI-CD.md` | `/dev doc` | 8.1-8.4 |
| [ ] | P | 8.6 — Deploy staging via INFRASTRUCTURE.md (manual humano), rodar smoke test E2E, registrar resultado | — | 8.5 |

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D8)

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
- **executor_prompt**: |
    ### Quality Brief (Sprint D8) — review: comprehensive

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
- D3.* (Auth) pode rodar paralelo a D2 (raiz independente)
- D4.5 (Audit) pode rodar paralelo a D4.1-4.3
- D7.* (OCC) pode rodar paralelo a D8.* (depende apenas de D6 + D2)

---

## Proximos Passos

Apos aprovacao deste roadmap:
1. **Aprovar via `/jarvis pipeline range=D1-D8`** ou comecar manual com `/pmo sprint D1` (mas o usuario escolheu modo Pipeline)
2. Cada sprint tem `executor_prompt` autocontido em todas as tasks M — sub-agente fast (Composer 2) recebe direto
3. Tasks com `critica: true` (D2.1, D5.1, D6.2) lancam Best-of-N (2 implementadores em worktrees, melhor selecionado)
4. Auditorias seguem a "Estrategia de Auditoria" definida acima — pipeline mode = sem AskQuestion entre sprints
5. Cada sprint produz `docs/DIARY.md` com aprendizados; sprints seguintes leem antes de comecar

---

## Historico

| Data | Versao | Alteracao | Autor |
|------|--------|-----------|-------|
| 2026-05-07 | 0.1 | Versao inicial — 8 sprints D, 44 tasks (25P / 19M / 0G), 3 tasks `critica:true` | Planejador de Tarefas (IA via /jarvis CONCIERGE → /pmo plan) |
