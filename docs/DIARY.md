# Sprint Diary — mework360-deployer

<!-- DIARY-INDEX -->
| Sprint | Modulos | Temas | Linhas |
|--------|---------|-------|--------|
| D1 | infra, database | scaffold, docker, migrations, models, pest | 14-80 |
| N30 | Core, Auth, Customers, Jobs | api/v1, DomainError, openapi-external, scopes | 57-98 |
| N32 | Integration, Jobs, Customers, Core | PlatformPort ondas, correlation_id, grep gate, observabilidade | 99-145 |
| N33 | Integration, Customers, Core, ClusterServers | occ spec gate, mutação via port, grep estrito, CQ-N32-003 | 146-192 |
| N34 | TenantLifecycle, Integration | saga onboarding, readiness gate, idempotency | 200-246 |
| N36 | Customers, ClusterServers, Integration, DevOps | image_mode, readiness image-mode, cluster image-pilot, ISSUE-045 | 248+ |
| N37 | Core, Auth, Livewire, docs | Scalar /docs/api, api-key scopes, sidebar link, ISSUE-047 | 294+ |
| N39 | Livewire, Customers, Occ, Jobs, ClusterServers | FQDN normalize, OCC users, async feedback, readiness card, ISSUE-049 | 330+ |
| F23 | Customers, Livewire, Jobs | TenantGroupProjector job_type, OCC groups validation, poll, sync | 349+ |
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

---

## Sprint N33 — ISSUE-038 Fase 3: Despublicar `/occ/*` + capabilities de mutação via port

**Data**: 2026-06-18
**Status**: CONCLUÍDA
**Tasks**: 8/8
**PR**: #117 (branch `campanha/n32-issue038` — campanha N32+N33; commits `b85d4bc`..`ef9547f`)

### Entregas

- Gate spec externo: `/occ/*` ausente de `openapi-external.yaml`; rotas legadas com headers `Deprecation`/`Sunset`; `redocly lint` CI verde
- **CQ-N32-003** resolvido: exceções de domínio no `PlatformPort` (`UpstreamUnavailableException`, `CapabilityBlockedException`, etc.); adapters mapeiam SSH/Agent; interface sem `@throws` de transporte
- Migração mutações via port: `RemoveCustomerAction` → `removeTenant`; `SyncWebhookSecretAction` + `AgentEventHandler` → métodos tipados; SFTP staging/inbox de `ProvisionCustomerAction` → adapter
- Quota v1: `PUT /v1/tenants/{slug}/users/{username}/quota` documentado; D-02 → `capability_not_available` sem vazamento NC
- `OccController` rebaixado a admin-only (passthrough via adapter de Integração direto, fora do spec externo)
- Grep gate **estrito**: WARN residual removido; hits fora de `Integration/Adapters/*` falham CI; characterization suite N33 no CI
- DomainError rendering wired em controllers API e `OccPanel`

### Decisões Técnicas

1. **Transport boundary no port (N33.2)**: consumidores de domínio lançam/capturam exceções de integração (`UpstreamUnavailableException`); mapeamento SSH/Agent isolado nos adapters — fecha ADR Fase 3 fronteira.
2. **D-02 honesto na quota v1**: endpoint v1 existe no spec externo; upstream bloqueado retorna `404 capability_not_available` — não expandir allowlist OCC nesta sprint.
3. **OccController admin-only**: passthrough OCC permanece operacional internamente; spec externo expõe apenas capabilities v1 tipadas.
4. **Grep gate FAIL (não WARN)**: residual N32 (`RemoveCustomerAction`, `SyncWebhookSecretAction`, `AgentEventHandler`, SFTP em `ProvisionCustomerAction`) migrado antes de remover allowlist.

### Problemas Encontrados (QA R1)

- Nenhum HIGH/CRITICAL no delta R1 — carry-over **CQ-N32-003** validado in-sprint (N33.2).

### Gate da Sprint

- [x] `/occ/*` ausente de `openapi-external.yaml`; `redocly lint` CI verde
- [x] Mutações tenant (remove, webhook sync, SFTP staging, agent events) via `PlatformPort` only
- [x] Grep gate estrito sem WARN — CI falha em transporte fora de adapters
- [x] Characterization suite N33 verde
- [x] 563 tests passed Docker (suite completa local)
- [x] validation_gate_qa: **APROVADA R1** (auditor-senior PASS; 0 HIGH/CRITICAL)

### Próximos Passos (N34)

- Saga `POST /v1/onboarding` idempotente + runbook (Fase 4 ADR ISSUE-038)
- Depende D-02 upstream para quota/branding/maintenance em produção

---

## Sprint N34 — ISSUE-038 Fase 4: Saga `POST /v1/onboarding`

**Data**: 2026-06-18
**Status**: CONCLUÍDA
**Tasks**: 8/8
**Branch**: `sprint/N34` (commits `22118d1`..`06c97bf`; follow-up R2 `5bd7456`)

### Entregas

- Modelo `Onboarding` + migration + enums de estado/step (`pending`, `running`, `completed`, `failed`, `partial`)
- `OnboardingSaga` orquestrador: provision → readiness → create_admin → enable_apps → set_branding via `PlatformPort` + Actions existentes
- `POST /api/v1/onboarding`: 202 + idempotência 24h (`IdempotencyKey`), scope `onboarding:run`, PII minimizada
- `GET /api/v1/onboarding/{id}`: status step-by-step + tenant binding
- Readiness gate entre steps (`CustomerReadinessProbe`); `tenant_not_ready` com `retry_after` na borda
- `openapi-external.yaml` + `CONTRACTS-V1.md` alinhados (schemas onboarding; D-02 branding skip documentado)
- Feature + characterization tests: happy path, idempotency replay, step failure parcial, branding `capability_not_available`
- Runbook `docs/runbooks/onboarding-saga.md`: estados terminais parciais, retry seguro, game-day checklist

### Decisões Técnicas

1. **Saga assíncrona por step (N34.2)**: cada step assíncrono persiste `job_id`; `WebhookHandler` avança saga por `correlation_id` após job terminal — fecha ADR Fase 4 signup 1 chamada + polling.
2. **Credenciais admin criptografadas (N34 follow-up)**: `admin_payload` com cast `encrypted:array` + `hidden`; consumido no dispatch de `users:create` — evita perda de credenciais após retorno 202.
3. **Falha terminal explícita (N34 follow-up)**: `markStepFailed()` + roteamento webhook seta `OnboardingState::Failed` — polling não fica em estado zumbi.
4. **D-02 honesto no branding**: step `set_branding` bloqueado → `skipped` + saga `completed`/`partial` sem vocabulário NC.

### Problemas Encontrados (QA R1 → R2)

- **CQ-N34-001** (HIGH): saga incompleta pós-readiness — steps `create_admin`/`enable_apps`/`set_branding` não despachados; corrigido em `5bd7456`.
- **CQ-N34-002** (HIGH): credenciais admin validadas mas descartadas — corrigido com `admin_payload` criptografado em `5bd7456`.
- **CQ-N34-003** (HIGH): falha de job não propagava para `onboarding.state=failed` — corrigido via `WebhookHandler` + `markStepFailed()` em `5bd7456`.

### Gate da Sprint

- [x] POST 202 + GET step-by-step; replay idempotente 24h não duplica tenant
- [x] Saga completa provision→readiness→admin→apps→branding (ou `partial` se branding D-02)
- [x] `correlation_id` propagado (`onboarding_id` → `job_id`)
- [x] Runbook saga parcial publicado
- [x] Spec externo + CONTRACTS-V1 alinhados; redocly lint verde
- [x] 582 tests passed Docker (suite completa local)
- [x] validation_gate_qa: **APROVADA R2** (auditor-senior PASS após `5bd7456`; 0 HIGH/CRITICAL)

### Próximos Passos

- Adoção real por onboarding-api/WHMCS (N22); smoke/staging E2E
- Expandir allowlist upstream D-02 (ISSUE-016) para branding/quota em produção
- PR/merge branch `sprint/N34`

---

## Sprint N36 — ISSUE-043 fase inicial: API → produção image-mode

**Data**: 2026-07-03 — fechamento 2026-07-04
**Status**: CONCLUÍDA (5/5)
**Tasks**: 5/5
**Branch**: `sprint/N36` — PR #128 merge `7a79086`; campanha `campanha/n36-pendencias` (docs + ops)

### Entregas

- **N36.1** (`c22d13d`): flag `image_mode` (request → `ProvisionPayload::imageMode` → argv `--image-mode`); config `platform.image_mode.default_mode` (env `PLATFORM_IMAGE_MODE_DEFAULT`); migration `customers.image_mode`; openapi `CreateTenantRequest.image_mode`; 4 testes `ImageModeProvisionV1Test.php`.
- **N36.5** (`3468695`): `TenantReadinessGateChecker::passesMeMailHttp` ramifica por `customer->image_mode` — image-mode usa `https://<domain>/login` (200); legado inalterado. 3 testes em `CustomerReadinessTest`.
- **N36.2** (`96e8420`): exemplo `image_pilot_production` no openapi; `SUITE-ENV.md` (piloto produção image-mode); `ecosystem-map.md` (cluster image-pilot).
- **N36.3** (ops): cluster `image-pilot` cadastrado no LAB (UUID `978d6dd4-…`); bootstrap SSH/shim/worker no `.120`; R6 PASS; deploy LAB SHA `80f3063`.
- **ISSUE-044** (`80f3063`): 7 testes CI pré-existentes no main corrigidos (fixture `calendar`→`mail`; mocks F3).
- **ISSUE-042** (`d480080`): `command: !override []` no worker de `docker-compose.lab.yml`.

### N36.4 — gate PASS (2026-07-04)

Canário `canario-n36e`: job `9904497b-ad3c-4390-ba61-c5f433cd00c1` success ~5m44s; customer `active` automático; `/login` 200; imagem mw4. **ISSUE-045** resolvida upstream (`ba53ecc`) + deploy `.120`. Ops: symlink `/opt/releases` → bundle `746ecb81`; cleanup `canario-n36b`/`canario-n36c`.

### Aprendizados

1. **Flags booleanas no contrato `create`**: validar e2e do argv até o Redis do worker, não só do audit SSH — bug D3.9b é silencioso (legado sem erro na borda).
2. **Hosts pilotos**: checklist worker antes de canário API (env manifest, `WORKER_REDIS_PASS`, `SUITE_RELEASES_DIR`) — image-pilot nunca tinha worker instalado.
3. **Layout flat** `/opt/nextcloud-customers`: quebra resolução de platform root das libs — padrão recorrente de bug de env.
4. **CI no main**: 7 falhas pré-existentes mascaravam o PR — regra "CI verde no main antes de branch de sprint".
5. **OpenSSH + ForceCommand**: `/usr/sbin/nologin` no host `.120` impedia execução do shim; desvio para `/bin/bash` com acesso restrito ao ForceCommand.

### Gate da Sprint

- [x] N36.1 — `image_mode` no contrato + testes
- [x] N36.2 — docs/spec alinhados ao piloto prod
- [x] N36.3 — cluster cadastrado + R6 PASS
- [x] N36.5 — readiness image-mode
- [x] N36.4 — canário E2E via API (`canario-n36e` gate PASS)
- [x] CI verde (PR #128 merge + deploy LAB validado)

### Próximos Passos

- ISSUE-043: cutover domínio `<tenant>.mework360.com.br` + migração SaaS-02 (fora N36)
- N25.3/N25.4: seed cluster `.108` + canário `qa-platform-lab`

---

## Sprint N37 — ISSUE-047: API Console fase 1 (Scalar + scopes)

**Data**: 2026-07-05
**Status**: CONCLUÍDA (4/4)
**Tasks**: 4/4
**PR**: #136 merge `b43422c`

### Entregas

- Viewer privado `/docs/api` (Scalar via `resources/js/docs-api.js` + Vite); spec `openapi-external.yaml` servido por rota autenticada; gate `manage-operators`.
- Scopes v1 selecionáveis no create de `/api-keys`; default sem seleção = irrestrita (`null`); validação `config/api-scopes.php`; integração `EnsureApiKeyScope`.
- Badges de scopes na listagem; link "Documentação API" na sidebar com `@can('manage-operators')`.

### Aprendizados

1. **Try-it-out Scalar**: spec aponta servidor de produção — desabilitar ou sobrescrever `servers` para `url('/api/v1')` do ambiente (fase 2).
2. **Array vazio de scopes**: `ApiKeyService` colapsa `[]` → `null` (irrestrita) — comportamento documentado no modal.

### Gate da Sprint

- [x] `/docs/api` autenticado renderiza spec externo; anônimo → redirect; sem gate → 403
- [x] Credencial com scopes persiste e é honrada por middleware
- [x] CI verde (PR #136); deploy LAB `8e58fed`; manifest `docs-api.js`; `/up` 200

---

## Sprint N39 — ISSUE-049: UX provisionamento + OCC operacional

**Data**: 2026-07-05
**Status**: CONCLUÍDA (7/7, núcleo + stretch)
**Tasks**: 7/7
**PR**: #135 merge `8e58fed`

### Entregas

- **N39.1:** FQDN normalizado server-side (strip `/`, lowercase, regex); preview no form; API v1 + Livewire + Pest.
- **N39.2–N39.3:** OccPanel lista usuários via `user:list --json`; bloqueio `admin`; feedback async de `users:create` com poll até terminal.
- **N39.4–N39.5:** `customers/show` com `wire:poll`, link job, tail log throttled; readiness visível via AuditLog `customer_readiness_probe`.
- **N39.6–N39.7 (stretch):** retrofit M3 em `customers/*`; remoção de cluster na UI com guarda de tenants ativos.

### Aprendizados

1. **FQDN trailing slash**: normalização só no client não basta — incidente `pacoteste` quebrou Traefik `Host()`; strip server-side é obrigatório.
2. **Poll SSH**: `JobLogFetcher` com throttle 15s evita amplificar carga durante provisioning.

### Gate da Sprint

- [x] Domínio normalizado em Livewire + FormRequest + API v1
- [x] Progresso e readiness visíveis em `customers/show`
- [x] OccPanel operacional (lista + erro inline)
- [x] CI verde (PR #135); deploy LAB `8e58fed`; `/up` 200

---

## Sprint F23 — Fix CQ-N46 projector job_type + OCC groups validation

**Data**: 2026-07-08
**Status**: CONCLUÍDA (5/5)
**Tasks**: F23.1–F23.5
**PR**: [#159](https://github.com/SoftwareBeesy/work-platform-api/pull/159) merge `32bd75a`

### Entregas

- **F23.1**: `TenantGroupProjector` aceita `group_create`/`group_delete` (+ aliases `groups:*`); fixtures Pest com job_types reais; teste integrado Lifecycle → webhook.
- **F23.2–F23.3**: OccPanel membership case-insensitive via `TenantGroupMembership`; regex + bloqueio `admin` alinhados à API.
- **F23.4**: Poll/reload pós create/delete de grupo (`wire:poll` 3s, timeout 120s, `loadGroups()` no terminal).
- **F23.5**: `TenantGroupSyncReport.updated` incrementado; `tenant-groups:sync` retorna FAILURE em falha parcial.

### Decisões / Aprendizados

1. **job_type parity**: projector de grupos deve espelhar `TenantUserProjector` — `JobTypeTranslator` persiste `group_create`, não `groups:create`.
2. **DRY pendente**: regras de nome de grupo ainda duplicadas entre `CreateGroupRequest` e `OccPanel::groupNameRules()` — backlog `CQ-F23-001`.

### Gate da Sprint

- [x] CQ-N46-001..008 validados (8/8)
- [x] Pest F23 suites **103 passed**; CI required checks verde
- [x] Senior review **PASS_WITH_NOTES** (0 HIGH; 3 non-blocking em backlog)

---

## Sprint F24 — OCC groups polish + F17/N40 backlog

**Data**: 2026-07-08
**Status**: CONCLUÍDA (7/7)
**Tasks**: F24.1–F24.7
**PR**: [#160](https://github.com/SoftwareBeesy/work-platform-api/pull/160) merge `5addd2f`
**Deploy LAB**: `5addd2f99794732d4f89e774deb04366882e7490` @ `api.lab.mework360.com.br`

### Entregas

- **F24.1**: `TenantGroupNameRules` DRY compartilhado entre API e OccPanel.
- **F24.2–F24.3**: sync `updated` em cenário misto; poll de grupo sem clobber de mensagem concorrente.
- **F24.4**: `groups: []` no `UserCreateStdinPayload`; `groups: null` herda template na API.
- **F24.5**: unit tests dedicados `SuiteCatalogPathResolver`.
- **F24.6–F24.7**: `deleteUser` recarrega lista; testes webhook out-of-order + poll integrado.

### Decisões / Aprendizados

1. **Tri-state groups**: `null` (herda template) ≠ `[]` (override vazio) — contrato explícito evita regressão silenciosa em novos call sites.
2. **Poll UX residual**: dual-success grupo e poll user×group no mesmo tick ainda podem sobrescrever mensagem — backlog `CQ-F24-001`..`003` + `INT-F24-001`.

### Gate da Sprint

- [x] CQ-F23-001..003 + CQ-F17-001..004 + CQ-N40-003 + QA-N40-003/004 validados (9/9)
- [x] CI PR #160 verde; validation-stamp + auditor-senior APROVADA
- [x] Deploy LAB `5addd2f`; migration `tenant_groups` Ran [7]; smoke `/up`+`/login` 200

---

## Sprint F25 — OccPanel poll messaging + naming polish

**Data**: 2026-07-09
**Status**: CONCLUÍDA (3/3)
**Tasks**: F25.1–F25.3
**PR**: [#161](https://github.com/SoftwareBeesy/work-platform-api/pull/161) merge `8021124`
**Deploy LAB**: não requerido (polish sprint; opcional)

### Entregas

- **F25.1**: `projectUserCreateIntoReadModel` renomeado para `projectUserJobIntoReadModel` (create+delete).
- **F25.2**: `TenantGroupNameRules::forAttribute` usa `$attribute` em regras e mensagens.
- **F25.3**: acumulação de mensagens no poll — dual-success grupo e grupo+usuário no mesmo tick.

### Decisões / Aprendizados

1. **Message accumulation**: helper compartilhado evita clobber entre handlers de poll no mesmo tick.
2. **Polish sem deploy**: sprint LOW-only não exige LAB quando CI + Pest local cobrem regressão.

### Gate da Sprint

- [x] INT-F24-001 + CQ-F24-001..003 validados (4/4)
- [x] CI PR #161 verde; validation-stamp APROVADA
- [x] Local: 14 filter + 50 OccPanelTest passed
