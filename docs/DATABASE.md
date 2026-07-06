# Database — mework360-deployer

> Gerado em: 2026-05-07
> Fase: 4 — Arquitetura de Dados
> Baseado em: docs/REQUIREMENTS.md + docs/ARCHITECTURE.md

## 1. Decisões

| Decisão | Escolha | Alternativa descartada | Motivo |
|---------|---------|----------------------|--------|
| BD principal | MariaDB 11 | PostgreSQL 16 | Decisão E10 (ARCHITECTURE.md v0.3, 2026-05-14): migração para MariaDB 11 — alinhamento com a infra já operada no work (docker-compose `mariadb:11`, driver `mysql`). Colunas `json` (não `jsonb`) para payloads de audit log e summaries de webhook. Sem partial unique index — unicidades condicionais são enforcement app-level. |
| Tier de infra | Tier 1 (Single Node) | Tier 2 (Replicas) | Escala atual (50 customers, 1 servidor scripts no MVP) não justifica HA de banco de dados ou divisões de leitura/escrita. Custo inicial mantido entre $20-50/mês. |
| Composição | Nível 1 | Nível 2 | Relatórios complexos sobre jobs e logs não constam no MVP; não há sentido em adicionar um banco analítico ou Elasticsearch. Redis cumpre o cache e o rate-limit estrito. |

## 2. Schema Inicial

### Entidades

| Entidade | Descrição | Relações principais |
|----------|-----------|-------------------|
| `operators` | Usuários do sistema (painel admin) | `has_many AuditLogs` |
| `cluster_servers` | Gestão dos servidores nextcloud upstream | `has_many Customers`, `has_many Jobs` |
| `customers` | Réplica de customers instalados (sync) | `belongs_to ClusterServer`, `has_many Jobs`, `has_many TenantUsers` |
| `tenant_users` | Read model local de usuários de tenant (projeção webhook + sync) | `belongs_to Customer` (FK `customer_slug`) |
| `plans` | Planos comerciais/técnicos — quota default e limites | `has_many Customers`, `has_many PlanApps` |
| `app_catalog_entries` | Catálogo de apps habilitáveis (app_id upstream) | `has_many PlanApps`, `has_many UserTemplateApps` |
| `user_templates` | Perfis reutilizáveis (Admin, Supervisor, Colaborador) | `has_many UserTemplateApps` |
| `jobs` | Réplica de fila e execuções no upstream | `belongs_to Customer`, `belongs_to ClusterServer` |
| `audit_logs` | Logs do painel de administração e API | `belongs_to Operator`, `belongs_to ClusterServer`, `belongs_to Job` |
| `webhook_secret_history` | Histórico de secrets para grace period 24h | `belongs_to ClusterServer` |
| `idempotency_keys` | Previne replay e duplicatas nos requests API | `belongs_to Job` |
| `api_keys` | (Sprint 2) Tokens para acesso REST externo | - |

### Diagrama ER (ASCII)

```text
 +---------------+       1:N      +------------------+
 |   operators   |--------------->|    audit_logs    |
 +---------------+                +------------------+
                                           ^
 +---------------+                         | 1:N
 |cluster_servers|-------------------------+
 +---------------+                         |
         | 1:N                             |
         v                                 | 1:N
 +---------------+       1:N      +------------------+
 |   customers   |--------------->|       jobs       |
 +---------------+                +------------------+
         | 1:N                            | 1:1
         v                                v
 +---------------+                +------------------+
 | tenant_users  |                | idempotency_keys |
 +---------------+                +------------------+
         ^
         | optional user_template_slug
 +---------------+       N:M      +----------------------+
 | user_templates|<---------------->| user_template_apps   |
 +---------------+                +----------------------+
         |                                  ^
 +---------------+       N:M                |
 |    plans      |<------+ plan_apps --------+----> app_catalog_entries
 +---------------+
         | 1:N
         v
 +---------------+  (customers.plan_slug FK nullable)
 |   customers   |
 +---------------+
```

### Convenções aplicadas

Conforme `~/.cursor/skills/capabilities/database-conventions.md`:
- UUID como PK (`id` ou `slug` em alguns casos como customers que já trazem o UID em formato slug). Para `customers`, a PK será string (slug). Todas as PKs numéricas não auto-increment utilizam UUID.
- Timestamps (`created_at`, `updated_at`) obrigatórios.
- Soft delete (`deleted_at`) nas entidades que merecem (como `operators`, `cluster_servers`), embora `audit_logs` e `jobs` sejam append-only ou removidos apenas por políticas longas.
- Índices em todas as FKs e campos de busca pesada (`jobs.state`, `jobs.customer_slug`).

### `tenant_users` (Sprint N40)

Read model local de usuários de tenant — alimentado por projeção webhook (`users:create`/`users:delete`/`provision`/`deprovision`) e reconciliação periódica/on-demand via `tenant-users:sync`.

| Coluna | Tipo | Notas |
|--------|------|-------|
| `id` | uuid PK | Gerado na aplicação |
| `customer_slug` | varchar(64) FK → `customers.slug` | ON DELETE CASCADE |
| `username` | varchar(64) | UNIQUE composto com `customer_slug` |
| `email` | varchar(255) nullable | |
| `quota` | varchar(64) nullable | |
| `groups` | json nullable | Lista de grupos NC |
| `user_template_slug` | varchar(64) nullable | ISSUE-051 — audit trail (N43); sem FK constraint |
| `origin` | varchar(20) | `api`, `panel`, `sync`, `provision` |
| `synced_at` | timestamp nullable | Preenchido em paths de sync/reconciliação |
| `created_at` / `updated_at` | timestamps | |

Índices: `uniq_tenant_users_customer_username`, `idx_tenant_users_customer_slug`.

### Product Governance (ISSUE-051 — Sprint N41–N43)

Camada de produto no control plane — **API-first**; apps Nextcloud customizados consomem metadados em fase posterior. Ver `docs/ARCHITECTURE.md` §10.1.

#### `plans`

| Coluna | Tipo | Notas |
|--------|------|-------|
| `slug` | varchar(64) PK | `^[a-z0-9-]+$` — ex. `basic`, `pro` |
| `name` | varchar(255) | Label operador |
| `description` | text nullable | |
| `default_quota` | varchar(64) | Ex. `5 GB`, `default` — aplicada a novos usuários sem override |
| `max_users` | int unsigned nullable | Limite opcional de usuários por tenant |
| `is_default` | boolean | Plano fallback para tenants legados (único `true`) |
| `status` | varchar(20) | `active` \| `inactive` |
| `created_at` / `updated_at` | timestamps | Sem soft delete — desativação via `status: inactive` (slug PK reutilizável não se aplica; slug é permanente) |

Unicidade de `is_default`: MariaDB 11 não suporta partial unique index — **enforcement app-level** no `PlanService`, dentro de transação (desmarcar o default anterior + marcar o novo atomicamente), com cenário de teste dedicado (N41).

#### `app_catalog_entries`

| Coluna | Tipo | Notas |
|--------|------|-------|
| `id` | uuid PK | |
| `app_id` | varchar(100) UNIQUE | Alinhado a `suite_catalog.json` / `occ app:enable` |
| `label` | varchar(255) | |
| `description` | text nullable | |
| `category` | varchar(64) nullable | Ex. `collaboration`, `integration` |
| `cluster_server_id` | uuid nullable FK | `null` = global; senão restrito ao cluster |
| `is_active` | boolean | |
| `created_at` / `updated_at` | timestamps | |

Índices: `uniq_app_catalog_app_id`, `idx_app_catalog_cluster`.

#### `plan_apps` (junction)

| Coluna | Tipo | Notas |
|--------|------|-------|
| `plan_slug` | varchar(64) FK → `plans.slug` | ON DELETE CASCADE |
| `app_catalog_id` | uuid FK → `app_catalog_entries.id` | ON DELETE CASCADE |

PK composta `(plan_slug, app_catalog_id)`.

#### `user_templates`

| Coluna | Tipo | Notas |
|--------|------|-------|
| `slug` | varchar(64) PK | Ex. `supervisor`, `collaborator` |
| `name` | varchar(255) | |
| `description` | text nullable | |
| `default_quota` | varchar(64) nullable | Override do plano; null = herdar plano |
| `groups` | json | Grupos NC aplicados no `users:create` |
| `permissions` | json | Permissões lógicas (control plane only) — ver schema abaixo |
| `status` | varchar(20) | `active` \| `inactive` |
| `created_at` / `updated_at` | timestamps | Sem soft delete — desativação via `status: inactive` |

#### `user_template_apps` (junction)

| Coluna | Tipo | Notas |
|--------|------|-------|
| `user_template_slug` | varchar(64) FK → `user_templates.slug` | |
| `app_catalog_id` | uuid FK → `app_catalog_entries.id` | Apps que o perfil pode usar |

PK composta `(user_template_slug, app_catalog_id)`.

#### Alterações em entidades existentes

| Tabela | Coluna | Notas |
|--------|--------|-------|
| `customers` | `plan_slug` | varchar(64) nullable FK → `plans.slug` (Sprint N41) |
| `tenant_users` | `user_template_slug` | varchar(64) nullable **sem FK constraint** — trilha de auditoria de qual template originou o create; índice simples, não bloqueia gestão de templates (Sprint N43) |

Limite `max_users`: enforcement no `PolicyResolver` (Sprint N43) — 422 `plan_limit_exceeded` + AuditLog `policy_denied`. Apps do plano: designação via junction `plan_apps`; enable/provision valida ⊆ plano via `PlanAppResolver` (422 validation). Fora de N41/N42.

#### Schema `permissions` (JSON, versionado)

```json
{
  "schema_version": 1,
  "users": {
    "hire": false,
    "block": false,
    "activate": false
  },
  "apps": {
    "install_from_store": false,
    "create_integration": false
  },
  "audit": {
    "read": false
  }
}
```

Enforcement na API (`PolicyResolver`) antes de SSH; apps NC leem via `GET /v1/tenants/{slug}/policy` (fase posterior).

## 3. Infraestrutura de Dados

### Topologia (Tier 1)

```text
    [ Laravel App / Worker / Web ]
                 |
        +-------------------+
        |                   |
        v                   v
 +------------+      +--------------+
 | Redis Node |      | MariaDB Node |
 | (Sessão,   |      | (Primary DB) |
 |  Fila ext.)|      +--------------+
 +------------+
```

### Connection Pooling

- Ferramenta: Built-in (Pool próprio do PDO via Laravel Database na porta 3306 direta)
- Modo: session (conexões persistentes do FPM)
- Pool size: base nos workers (ex: `max_connections = 100`)

### Read/Write Splitting

- Framework: N/A (Tier 1 possui apenas um nó de banco)

### Cache Strategy

- Engine: Redis 7.x
- HA: Standalone (Redis Node único para o MVP)
- Uso: Sessões do painel, rate limiting de webhook do upstream, queue local leve para tentativa de emails
- TTL padrão: Webhook replay protection = 1 hora; Rate-limit = janelas de minuto; Sessões = 8h.

## 4. Segurança

| Item | Config |
|------|--------|
| Usuário BD | `mework360_deployer_user`, GRANT SELECT/INSERT/UPDATE/DELETE no schema public. Negado acesso de drop database. |
| Senha | Gerada (32+ chars), rotação 90 dias manual |
| TLS app-BD | Obrigatório (inclusive em rede local, via self-signed se AWS/DigitalOcean internal network não for cifrada) |
| Encryption at rest | Habilitado via Laravel Encrypted Storage nos models para colunas sensíveis (`ssh_private_key_encrypted`, `webhook_secret_encrypted`). No storage engine do PG, sem TDE nativo no MVP. |
| Backup | `pg_dump` diário exportado para cloud storage independente com lifecycle rules. |
| PITR | Desabilitado para reduzir custos (tolerado pela arquitetura assíncrona baseada na "fonte de verdade" no upstream) |
| Teste de restore | Mensal |

## 5. Migration Inicial

A Sprint 1 DEVE começar com "criar migrations do schema inicial". Tarefa:
- Criar todas as tabelas listadas na seção 2.
- Para `customers`, a PK será o campo `slug` (string primária).
- Para a maioria das outras, a PK é UUID (`jobs`, `idempotency_keys`, `api_keys`).
- Aplicar convenções de `database-conventions.md`.
- Criar seeders de teste simulando a estrutura e estados para as 18 telas mapeadas no DESIGN.md, facilitando o Mock do upstream.
