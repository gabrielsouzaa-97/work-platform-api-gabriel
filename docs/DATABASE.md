# Database — mework360-deployer

> Gerado em: 2026-05-07
> Fase: 4 — Arquitetura de Dados
> Baseado em: docs/REQUIREMENTS.md + docs/ARCHITECTURE.md

## 1. Decisões

| Decisão | Escolha | Alternativa descartada | Motivo |
|---------|---------|----------------------|--------|
| BD principal | PostgreSQL 16 | MariaDB 11.8 | O ecossistema em torno de `JSONB` no Postgres é superior e será vastamente utilizado para salvar payloads do audit log e os summaries arbitrários dos webhooks recebidos do upstream. |
| Tier de infra | Tier 1 (Single Node) | Tier 2 (Replicas) | Escala atual (50 customers, 1 servidor scripts no MVP) não justifica HA de banco de dados ou divisões de leitura/escrita. Custo inicial mantido entre $20-50/mês. |
| Composição | Nível 1 | Nível 2 | Relatórios complexos sobre jobs e logs não constam no MVP; não há sentido em adicionar um banco analítico ou Elasticsearch. Redis cumpre o cache e o rate-limit estrito. |

## 2. Schema Inicial

### Entidades

| Entidade | Descrição | Relações principais |
|----------|-----------|-------------------|
| `operators` | Usuários do sistema (painel admin) | `has_many AuditLogs` |
| `cluster_servers` | Gestão dos servidores nextcloud upstream | `has_many Customers`, `has_many Jobs` |
| `customers` | Réplica de customers instalados (sync) | `belongs_to ClusterServer`, `has_many Jobs` |
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
                                           | 1:1
 +--------------------------+              v
 | webhook_secret_history   |     +------------------+
 +--------------------------+     | idempotency_keys |
                                  +------------------+
```

### Convenções aplicadas

Conforme `~/.cursor/skills/capabilities/database-conventions.md`:
- UUID como PK (`id` ou `slug` em alguns casos como customers que já trazem o UID em formato slug). Para `customers`, a PK será string (slug). Todas as PKs numéricas não auto-increment utilizam UUID.
- Timestamps (`created_at`, `updated_at`) obrigatórios.
- Soft delete (`deleted_at`) nas entidades que merecem (como `operators`, `cluster_servers`), embora `audit_logs` e `jobs` sejam append-only ou removidos apenas por políticas longas.
- Índices em todas as FKs e campos de busca pesada (`jobs.state`, `jobs.customer_slug`).

## 3. Infraestrutura de Dados

### Topologia (Tier 1)

```text
    [ Laravel App / Worker / Web ]
                 |
        +-------------------+
        |                   |
        v                   v
 +------------+      +--------------+
 | Redis Node |      | Postgres Node|
 | (Sessão,   |      | (Primary DB) |
 |  Fila ext.)|      +--------------+
 +------------+
```

### Connection Pooling

- Ferramenta: Built-in (Pool próprio do PDO via Laravel Database na porta 5432 direta)
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
