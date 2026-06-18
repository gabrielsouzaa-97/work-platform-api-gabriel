# Contratos v1 — Nota de Design (DRAFT)

> **ISSUE-038** · ADR: `.arch-panel/panel/final.md` · Status: **DRAFT — não implementado**
> **Contrato externo (v1):** [`openapi-external.yaml`](openapi-external.yaml) · **Legacy interno (não publicar):** [`openapi.yaml`](openapi.yaml)

---

## 1. Fronteira dos dois contratos

```
┌── CONTRATO EXTERNO (estável, versionado) ──────────────────────────────┐
│  /api/v1/*  ·  VerifyExternalPrincipal (scopes + binding tenant)       │
│  Capabilities: Tenant, Apps, Users, Jobs, Branding*, Onboarding*      │
│  Envelope: { data, meta? } · Erro: DomainError                          │
└────────────────────────────┬───────────────────────────────────────────┘
                             │ aliases finos (Sprint 0) → Actions
                             ▼
┌── TENANT LIFECYCLE (domínio publicado) ────────────────────────────────┐
│  ProvisionCustomerAction · RemoveCustomerAction · LifecycleAsyncAction  │
└────────────────────────────┬───────────────────────────────────────────┘
                             │ comandos tipados (futuro PlatformPort)
                             ▼
┌── INTEGRAÇÃO / PROTOCOLO NC (interno, mutável) ────────────────────────┐
│  SSH nextcloud-manage · occ-exec · webhook HMAC · Farm Agent           │
│  JobTypeTranslator · exit codes · subcmd · staging SFTP                │
│  OccController (/occ/*) — admin only, fora do spec externo             │
└────────────────────────────────────────────────────────────────────────┘
```

O contrato externo **nunca** expõe vocabulário NC. O interno pode trocar transporte (SSH ↔ Agent) sem alterar schema HTTP da v1.

---

## 2. Catálogo DomainError

| `error.code` | HTTP | Significado | Quando |
|---|---|---|---|
| `validation_failed` | 422 | Entrada inválida | FormRequest falhou; slug/username inválido; `confirm_slug` divergente |
| `unauthenticated` | 401 | Sem credencial válida | Bearer ausente/expirado/revogado |
| `forbidden_scope` | 403 | AuthZ negada | Scope insuficiente ou tenant fora do binding do principal (ISSUE-037) |
| `tenant_not_found` | 404 | Tenant inexistente | Slug ausente na réplica local (soft-deleted incluído) |
| `tenant_not_ready` | 503 | Janela de readiness | `POST .../users` com `status` ∈ `{provisioning, provisioning_finishing}` |
| `idempotency_conflict` | 409 | Colisão idempotente | Mesmo hash de args ≠ job existente (24h) |
| `state_conflict` | 409 | Estado incompatible | Remoção em andamento; diff de provisionamento; `already_exists` upstream |
| `cluster_unreachable` | 503 | Cluster indisponível | Cluster inativo ou conexão SSH/Agent falhou na borda |
| `upstream_unavailable` | 502 | Falha upstream genérica | Erro remoto sem expor exit_code/subcmd |
| `capability_not_available` | 404 | Capability bloqueada | Branding/quota/maintenance até D-02 |
| `rate_limited` | 429 | Throttle | >120 req/min (padrão atual) |
| `not_implemented` | 501 | Planned | reservado para capabilities futuras fora do v1 |

Todos os erros usam envelope `{ error: { code, message, retry_after?, details? } }`.

---

## 3. Matriz scope → capability → endpoints

| Scope | Capability | Endpoints |
|---|---|---|
| `tenants:write` | Criar/remover tenant | `POST /v1/tenants`, `DELETE /v1/tenants/{slug}` |
| `tenants:read` | Ler tenant | `GET /v1/tenants/{slug}` |
| `apps:write` | Habilitar apps | `POST /v1/tenants/{slug}/apps` |
| `users:write` | Gestão de usuários | `POST /v1/tenants/{slug}/users`, `DELETE /v1/tenants/{slug}/users/{username}` |
| `jobs:read` | Status de job | `GET /v1/jobs/{id}` |
| `branding:write` | Branding | `PUT /v1/tenants/{slug}/branding` *(404 até D-02)* |
| `onboarding:run` | Saga onboarding | `POST /v1/onboarding`, `GET /v1/onboarding/{id}` |

Middleware: `VerifyExternalPrincipal` em **todo** `/api/v1/*` (ISSUE-037 — gate duro).

---

## 4. Mapa v1 → Action interna (Sprint 0)

| Endpoint v1 | Action / recurso atual | Evolução |
|---|---|---|
| `POST /v1/tenants` | `ProvisionCustomerAction` | → `PlatformPort::createTenant` (Fase 1) |
| `DELETE /v1/tenants/{slug}` | `RemoveCustomerAction` | permanece Action; port encapsula transporte |
| `GET /v1/tenants/{slug}` | `Customer` + `CustomerResource` | leitura direta da réplica (sem port) |
| `POST /v1/tenants/{slug}/apps` | `LifecycleAsyncAction` (`apps:enable`) | → `PlatformPort::enableApps` |
| `POST /v1/tenants/{slug}/users` | `LifecycleAsyncAction` (`users:create`) | permanece Action |
| `DELETE /v1/tenants/{slug}/users/{username}` | `LifecycleAsyncAction` (`users:delete`) | permanece Action |
| `GET /v1/jobs/{id}` | `JobController::show` → `JobResource` | sanitizar campos NC na borda v1 |
| `PUT /v1/tenants/{slug}/branding` | *(bloqueado)* | → `PlatformPort::setBranding` (Fase 1, pós D-02) |
| `POST /v1/onboarding` | `OnboardingSaga::start` | saga assíncrona; step branding pode `skipped` (D-02) |
| `GET /v1/onboarding/{id}` | `OnboardingV1Controller::show` | progresso step-by-step |

Sprint 0 = **aliases finos** sobre Actions existentes, com envelope e erros normalizados na borda.

---

## 5. Exclusões explícitas

| Excluído do spec externo | Motivo |
|---|---|
| `/occ/*` (quota, branding sync, maintenance) | Passthrough cru de subcmd OCC; expõe protocolo NC e falha com `subcmd`/`exit_code` (ISSUE-011, D-02) |
| `subcmd`, `exit_code`, `cmd_canonical` | Termos do protocolo interno; vazam implementação upstream |
| Stack traces, `parsedJson` upstream | Segurança + estabilidade de contrato |
| `GET /queue`, `POST /queue/{id}/cancel` | Operações operacionais/admin — não são capabilities de integrador |
| Groups lifecycle (`groups:*`) | Fora do Sprint 0; avaliar capability futura |

---

## 6. Dependências e gates

| Gate | Papel |
|---|---|
| **ISSUE-037 / SEC-V1-001** | Pré-requisito **DURO** — `VerifyExternalPrincipal`, scopes, binding tenant, audit |
| **D-02 / ISSUE-016** | Allowlist upstream — branding, quota, maintenance, onboarding |
| **ISSUE-021 / DOC-001** | Este spec **corrige** o drift `{success,message,data}` vs código real |
| **ISSUE-038 Sprint 0** | Implementação dos aliases + sanitização DomainError |

---

## 7. Versionamento e deprecação

- Namespace estável: `/api/v1/*`.
- Legado (`/api/customers`, `/api/queue`) permanece durante transição com headers `Deprecation` / `Sunset`.
- Política: suportar **N e N-1**; aviso mínimo **90 dias** antes de desligar legado.
- Breaking changes → `/v2`; nunca alterar semântica de `/v1` in-place.

---

## 8. Envelopes (decisão fixa)

**Sucesso assíncrono (202):**
```json
{ "data": { ... }, "meta": { "job_id": "<uuid>", "status_url": "/v1/jobs/<uuid>" } }
```

**Sucesso síncrono (200):**
```json
{ "data": { ... } }
```

**Erro:**
```json
{ "error": { "code": "<DomainError>", "message": "...", "retry_after": 60, "details": {} } }
```
