---
name: api-rest-patterns
description: >
  Padrões REST do mework360-deployer-api: controller, FormRequest, resposta JSON, mapeamento
  exceção->HTTP, idempotency, audit log, auth/middleware, e validação.
  Use when: endpoint, controller, FormRequest, response, error, API, REST, rota, validação,
  novo endpoint, adicionar rota, criar controller, mapear exceção, código de erro,
  resposta 202, resposta 503, job_id, auth guard, throttle.
  Don't use when: chamadas SSH de saída (use ssh-orchestrator), webhooks recebidos (use
  webhook-receiver), tradução de vocabulários (use vocabulary-translator).
disable-model-invocation: false
---

# API REST Patterns

> **Identity**: Especialista nos padrões REST do mework360-deployer-api — criar, estender ou debugar endpoints.

## Prerequisites

- `docs/ARCHITECTURE.md` — Monolito modular, ADRs
- `docs/openapi.yaml` — Contrato formal (código é a verdade se divergir)
- Skills irmãs: `ssh-orchestrator`, `vocabulary-translator`, `webhook-receiver`

## Main Flow

1. **Camadas e modos** — Route → FormRequest → Controller → DTO → Action/Service; async 202 vs OCC 200 vs webhook 204.
   -> Details: `references/layers-and-modes.md`
2. **Formato de resposta** — Erros `{ "error": "code" }`; sucesso Resource ou JSON manual; sem `{ success, data }`.
   -> Details: `references/response-format.md`
3. **Error codes** — Tabela canônica exceção → HTTP + campos extras + `Retry-After`.
   -> Details: `references/error-codes.md`
4. **Validação** — FormRequest, roles, regex de path params, `prepareForValidation`.
   -> Details: `references/validation.md`
5. **Novo endpoint** — Checklist rota → teste → OpenAPI.
   -> Details: `references/endpoint-checklist.md`
6. **Controller** — Templates async lifecycle, OCC sync, provision/remove.
   -> Details: `references/controller-patterns.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** lógica de domínio no controller — só orquestrar e mapear exceções
- **NUNCA** envelope `{ "success": true, "data": {} }` nem stack trace na resposta
- **NUNCA** 500 para `BlockedOnUpstreamException` — usar **501** `not_implemented_yet`
- **NUNCA** 500 para erros de negócio mapeáveis — usar status semântico (409, 422, 502, 503, 504)
- **SEMPRE** `declare(strict_types=1)` e `final class` em controllers
- **SEMPRE** `Retry-After` em 503 (`cluster_unreachable`, `tenant_not_ready`)
- **SEMPRE** path params sem FormRequest validados inline (regex + length) antes do dispatch
- **SEMPRE** `$request->string('field')->toString()` em código novo
- Exit SSH **16** (OCC) → **403** `occ_subcmd_not_allowed`, não 502
- Audit OCC em `runOccExec()` após sucesso; audit lifecycle/provision na Action
- Payload de audit **sem** senhas nem tokens
