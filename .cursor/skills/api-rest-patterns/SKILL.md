---
name: api-rest-patterns
description: >
  Padrões REST do mework360-deployer-api: controller, FormRequest, resposta JSON, mapeamento
  exceção->HTTP, idempotency, audit log, auth/middleware, e validação.
  Use when: endpoint, controller, FormRequest, response, error, API, REST, rota, validação,
  novo endpoint, adicionar rota, criar controller, mapear exceção, código de erro,
  resposta 202, resposta 503, job_id, auth guard, throttle.
  Don't use when: chamadas SSH de saída (use ssh-orchestrator), webhooks recebidos (use
  webhook-receiver), tradução de vocabulários (use vocabulary-translator), deploy/infra (use me360-deployer).
disable-model-invocation: false
---

# API REST Patterns

> **Identity**: Especialista nos padrões REST deste projeto — controller fino, exceções mapeadas, contrato JSON estável.

## Prerequisites

- `docs/ARCHITECTURE.md` — monolito modular, ADRs
- `docs/openapi.yaml` — contrato formal (código prevalece se divergir)
- Skills irmãs: `ssh-orchestrator`, `vocabulary-translator`, `webhook-receiver`
- Guardrails universais: `capabilities/guardrails.md`

## Main Flow

1. **Camadas e modos** — Route → FormRequest → Controller → DTO → Action; async 202 vs OCC 200 vs webhook 204
   -> Details: `references/layers-and-modes.md`

2. **Formato de resposta** — erros `{ "error": "code" }`; sem envelope `{ success, data }`
   -> Details: `references/response-format.md`

3. **Error codes** — exceção → HTTP + campos extras + `Retry-After`
   -> Details: `references/error-codes.md`

4. **Validação** — FormRequest, roles, regex de path params
   -> Details: `references/validation.md`

5. **Novo endpoint** — checklist rota → teste → OpenAPI
   -> Details: `references/endpoint-checklist.md`

6. **Controller** — templates async lifecycle, OCC sync, provision/remove
   -> Details: `references/controller-patterns.md`

7. **Guardrails** — iron law, red flags, checklist de endpoint
   -> Details: `references/guardrails.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** lógica de domínio no controller — só orquestrar e mapear exceções
- **NUNCA** envelope `{ "success": true }` nem stack trace na resposta
- **NUNCA** 500 para `BlockedOnUpstreamException` — usar **501**
- **SEMPRE** `declare(strict_types=1)` e `final class` em controllers novos
- **SEMPRE** `Retry-After` em 503 (`cluster_unreachable`, `tenant_not_ready`)
- Exit SSH **16** (OCC) → **403** `occ_subcmd_not_allowed`, não 502
- Anti-racionalização: `references/guardrails.md`
