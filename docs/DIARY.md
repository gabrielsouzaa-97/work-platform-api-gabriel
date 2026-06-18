# Sprint Diary — mework360-deployer

<!-- DIARY-INDEX -->
| Sprint | Modulos | Temas | Linhas |
|--------|---------|-------|--------|
| D1 | infra, database | scaffold, docker, migrations, models, pest | 14-80 |
| N30 | Core, Auth, Customers, Jobs | api/v1, DomainError, openapi-external, scopes | 57-98 |
| N32 | Integration, Jobs, Customers, Core | PlatformPort ondas, correlation_id, grep gate, observabilidade | 99-145 |
<!-- /DIARY-INDEX -->

---

## Sprint D1 — Foundation

**Data**: 2026-05-08
**Status**: CONCLUIDA
**Tasks**: 6/6

### Entregas

- Laravel 12.58.0 scaffolded (composer create-project)
- 5 Docker services healthy: app (PHP 8.3-FPM), nginx, postgres16, redis7, mailpit
- 9 migrations: uuid extension + 8 application tables (operators, cluster_servers, customers, jobs, audit_logs, webhook_secret_history, idempotency_keys, api_keys)
- 8 Eloquent models with casts (encrypted for keys/secrets, array for JSONB)
- DatabaseSeeder: admin operator + dev cluster_server
- Pest 3.x installed + smoke test GET /up → 200 PASS

### Decisões Técnicas

1. **predis/predis em vez de phpredis**: PECL network bloqueado no ambiente Docker build. `predis/predis ^3.4` é a alternativa pura PHP suportada pelo Laravel. REDIS_CLIENT=predis no .env.
2. **Xdebug removido do Dockerfile dev**: PECL também inacessível. Comentário inline explica como instalar manualmente quando o ambiente permitir.
3. **vendor_data volume removido**: No Linux, o bind mount (`.:/var/www/html`) é suficiente. O volume nomeado causava `vendor/` vazia no container na primeira subida.
4. **QUEUE_CONNECTION=redis**: Evita conflito de nome com a tabela `jobs` da aplicação (Nextcloud jobs vs Laravel queue jobs). Queue Redis não precisa de migration.
5. **Test DB PostgreSQL**: phpunit.xml configurado para usar `mework360_deployer_test` (PostgreSQL) ao invés de SQLite in-memory, para compatibilidade com uuid-ossp extension e jsonb columns.
6. **DB::raw('uuid_generate_v4()')** como default em PKs UUID: Requer extensão uuid-ossp (habilitada na primeira migration). Alternativa pgcrypto descartada (não disponível em todas as images).

### Problemas Encontrados

- **git commit bloqueado**: Hook `rtk-rewrite.sh` do sistema Cursor retorna JSON inválido para comandos `git commit`. Commits não realizados durante a sprint. Workaround: acumular no branch, push único ao final.
- **nginx health check**: Primeira chamada `/up` retornava 500 antes do app estar totalmente aquecido. Resolvido com start_period=60s (já configurado). Depois de 7 minutos, todos os serviços ficaram healthy.

### Gate da Sprint

- [x] `docker-compose up` → 5 services healthy
- [x] `php artisan migrate` → 9 migrations sem erro
- [x] `php artisan migrate:rollback` → rollback na ordem inversa sem erro
- [x] `php artisan migrate:fresh --seed` → migrations + seeder ok
- [x] Pest smoke test `GET /up` → 200 PASS

### Próximos Passos (D2)

- Implementar SshClient (critica: true — Best-of-N)
- Implementar JobTypeTranslator e StateTranslator (15 verbs + 5 estados)
- Implementar SlugValidator (rejeita underscore com 422)

---

## Sprint N30 — ISSUE-038 Sprint 0: API `/api/v1`

**Data**: 2026-06-17
**Status**: CONCLUÍDA
**Tasks**: 7/7
**PR**: #115 mergeada em `main` (commit `837173c`)

### Entregas

- Contrato externo `/api/v1`: `DomainError` enum + `RenderDomainError` (mapeamento único → HTTP sem vocabulário NC)
- `openapi-external.yaml` como spec público; `openapi.yaml` marcado internal/legacy (DOC-001 / ISSUE-021 parcial)
- Catálogo scopes v1 (`tenants:*`, `apps:write`, `users:write`, `jobs:read`) + grupo rotas com `api.scope` + `api.tenant`
- `routes/api_v1.php` + controllers `Api\V1\*` (aliases finos sobre Actions existentes)
- Envelope v1 `{ data, meta? }` + FormRequests/Resources sanitizados
- ~35 testes Pest em `tests/Feature/Api/V1/` (authz cross-tenant, sanitização DomainError, smoke lifecycle)
- Job CI `redocly lint docs/openapi-external.yaml` no workflow CI

### Decisões Técnicas

1. **Aliases finos, não PlatformPort**: Sprint 0 ADR exige reversibilidade — controllers V1 delegam às Actions legadas sem extrair port ainda (N31).
2. **Reuso F15**: `EnsureApiKeyScope` + `EnsureTenantBinding` aplicados no prefixo `/api/v1` em vez de duplicar authz.
3. **Deny-by-default em rotas com scope declarado**: chaves parceiras com `allowed_tenant_slugs` restrito; `null` = unrestricted (operadores internos).
4. **Job lookup com tenant check in-controller**: `GET /v1/jobs/{id}` não usa `{slug}` na rota — binding feito via `customer_slug` do job vs allowlist da chave.

### Problemas Encontrados (QA R1)

- **CQ-N30-001 (HIGH)**: IDOR em `GET /api/v1/jobs/{id}` — job acessível cross-tenant antes do fix. Corrigido in-sprint: `JobV1Controller::isJobForbiddenForCurrentApiKey()`.
- **SEC-N30-001 (HIGH)**: vazamento `exit_code` em erro de provision `POST /api/v1/tenants`. Corrigido in-sprint via `RenderDomainError` + mapeamento na borda v1.

### Gate da Sprint

- [x] Nenhuma resposta `/api/v1/*` contém `subcmd`/`exit_code`/`cmd_canonical` (DomainErrorSanitizationTest)
- [x] Chave parceiro tenant A → **403** em rotas tenant B (ApiV1AuthorizationTest)
- [x] `redocly lint docs/openapi-external.yaml` — 0 errors (CI)
- [x] CI verde: Lint, Test/Pest, composer audit, OpenAPI Redocly (`837173c`)
- [x] validation_gate_qa: **APROVADA**

### Próximos Passos (N31)

- Extrair `PlatformPort` mínimo (Fase 1 ADR ISSUE-038)
- `PUT /v1/tenants/{slug}/branding` 100% via port (gate D-02 parcial)
- Characterization tests antes de trocar transporte

---

## Sprint N32 — ISSUE-038 Fase 2: Ondas migração + observabilidade transporte

**Data**: 2026-06-18
**Status**: CONCLUÍDA
**Tasks**: 8/8
**PR**: #117 (commits `491f5d9`..`db21720`)

### Entregas

- `PlatformPort` estendido + DTOs tipados (`fetchJobLogs`, `cancelJob`, `pollJobStatus`, `syncTenant`, `runOccPassthrough`, `probeClusterHealth`)
- Ondas (a)(b)(c): migração de `OccPassthroughService`, `CustomerReadinessProbe`, `JobLogFetcher`, `CancelJobAction`, `CustomerSyncService`, `JobsPollStuckCommand`, `ClusterHealthCheckCommand`, Livewire `OccPanel` + `ClusterServers\Index` → `PlatformPort`
- `correlation_id` ponta-a-ponta: migration + propagação em dispatch, webhook, `AuditLog`, eventos Agent
- Observabilidade operacional: `TransportObservability`, `JobsObservabilityCheckCommand`, schedule em `routes/console.php`
- CI grep gate: `scripts/grep-gate-adapters.sh` — transporte direto restrito a `Integration/Adapters/*`
- Characterization tests para todas as ondas + `CorrelationIdEndToEndTest` + `TransportObservabilityTest`
- Fast-track SEC-N30-003/004: erros `DELETE /v1/tenants` legados sanitizados

### Decisões Técnicas

1. **Ondas com characterization first**: tests RED→GREEN antes de cada migração; paridade SSH/Agent preservada via adapters.
2. **`correlation_id` como campo persistido**: migration `add_correlation_id_to_jobs_table`; ligamento dispatch→webhook→audit para rastreio cross-boundary.
3. **Grep gate mecânico no CI**: enforcement de ADR Fase 2 — consumidores de domínio não referenciam `SshClientInterface`/`AgentUpstreamGateway` diretamente (allowlist explícita para testes).
4. **`CQ-N32-003` deferred N33**: exceções de transporte (`SshClientException`) na interface `PlatformPort` exigem refactor arquitetural (`UpstreamUnavailableException`) — parked, não bloqueia gate Fase 2.

### Problemas Encontrados (QA R1 → R2)

- **CQ-N32-001 (HIGH)**: schedule observability sem `use` imports em `routes/console.php` — scheduler não executava checks. Corrigido in-sprint.
- **CQ-N32-002 (HIGH)**: lógica de persistência (`Customer::create/update`) dentro de `SshPlatformAdapter::syncTenant`. Corrigido: réplica movida para `CustomerSyncService`.
- **CQ-N32-004 (HIGH)**: `correlation_id` omitido em remove/cancel/poll. Corrigido in-sprint.
- **CQ-N32-005 (HIGH)**: `CancelJobAction` gravava `previous_state` errado (`getOriginal` pós-update). Corrigido: captura antes do `update()`.
- **CQ-N32-006 (HIGH)**: `dispatchManageAsync` ignorava transporte Agent. Corrigido via `PlatformPort` + factory.
- **CQ-N32-007 (HIGH)**: artefatos críticos não commitados (preflight PROC-025). Corrigido antes de PR/CI.
- **CQ-N32-003 (HIGH, parked N33)**: interface `PlatformPort` ainda declara `@throws SshClientException` — refactor para exceções de port na Fase 3.

### Gate da Sprint

- [x] Grep gate CI verde — transporte somente em `Integration/Adapters/*`
- [x] Characterization tests ondas (a)(b)(c) verdes
- [x] `correlation_id` provado em `CorrelationIdEndToEndTest`
- [x] Observabilidade: `TransportObservabilityTest` + schedule `jobs:observability-check`
- [x] 82 tests passed Docker (Characterization + Jobs + DomainErrorSanitization)
- [x] CI verde: run `27768621255`
- [x] validation_gate_qa: **APROVADA R2** (auditor-senior R2 PASS)

### Próximos Passos (N33)

- Despublicar rotas `/occ/*` do spec externo (`openapi-external.yaml`)
- Capabilities de mutação via port (carry-over grep gate residual: `RemoveCustomerAction`, `SyncWebhookSecretAction`, `AgentEventHandler`)
- Resolver `CQ-N32-003`: exceções de port sem vazamento de transporte SSH/Agent

