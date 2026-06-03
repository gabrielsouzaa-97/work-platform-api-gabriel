# Camadas e Modos de Operação

## Arquitetura de camadas

```
Route (routes/api.php)
  └─ Middleware (auth:web,api-key | active.operator | throttle:120,1)
       └─ FormRequest (authorize + rules + prepareForValidation)
            └─ Controller (final class, magro — só orquestra)
                 └─ DTO (readonly, fromRequest / fromArray)
                      └─ Action (async) | Service (sync OCC)
                           └─ SshClientInterface / Translators / Models
```

**Regra de ouro**: o controller nunca contém lógica de domínio. Chama Action ou Service, captura exceções de domínio e retorna HTTP correto.

## Modos de operação

| Modo | Exemplo | Resposta | Timeout |
|------|---------|----------|---------|
| **Async lifecycle** | `POST /customers/{slug}/users` | `202 { job_id }` | < 2s |
| **Sync OCC passthrough** | `PUT .../occ/quota/{username}` | `200` JSON upstream | 60s |
| **Provision/Remove** | `POST /customers` | `201 CustomerResource` | 30s |
| **Webhook receiver** | `POST /jobs/hook` | `204` (body vazio) | < 500ms |
| **Read-only** | `GET /queue` | `200 JobResource` | — |

## Middleware por tipo

| Tipo | Middleware |
|------|------------|
| Webhook | `VerifyWebhookHmac::class` (sem auth de operador) |
| Demais | `['auth:web,api-key', 'active.operator', 'throttle:120,1']` |

- **`auth:web,api-key`** — sessão web ou Bearer (SHA-256 em `api_keys`)
- **`active.operator`** — bloqueia operadores inativos
- **`throttle:120,1`** — 120 req/min

Webhook: ver skill `webhook-receiver`.

## Idempotency

- Dedupe por `args_hash` (SHA-256 de `[customer_slug, cmd, args]`) em `LifecycleAsyncAction`
- Conflito → `IdempotencyConflictException` → `409 { "error": "idempotency_conflict", "existing_job_id": "uuid" }`

## Audit log

| Onde | Quando registrar |
|------|------------------|
| OCC sync | `AuditLog::create()` em `runOccExec()` **após** sucesso |
| Lifecycle/Provision async | Na Action, não no controller |

**NUNCA** incluir senhas ou tokens em `payload`. Campos: `id`, `actor_id`, `action` (snake_case), `resource_type`, `resource_id`, `payload` sanitizado, `cluster_server_id`.
