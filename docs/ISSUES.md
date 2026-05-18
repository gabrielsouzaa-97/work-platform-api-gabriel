# Issues — mework360-deployer

> Fonte de verdade para enhancements, melhorias e change requests. Bugs e findings de segurança → docs/FINDINGS.md.

## Índice

| ID | Tipo | Título | Módulo | Prioridade | Status |
|----|------|--------|--------|------------|--------|
| ISSUE-001 | change_request | Per-job webhook_token na callback URL | Jobs, Core | HIGH | open |

---

## ISSUE-001 — Per-job webhook_token na callback URL

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-05-18
- **Solicitante**: operador (dev)
- **Módulos afetados**: `app/Http/Middleware/`, `app/Modules/Customers/Actions/`, `app/Models/`, `database/migrations/`

### Descrição

A callback URL enviada ao upstream via SSH (`--callback=<url>`) não contém nenhum vínculo com o job específico despachado. Qualquer requisição com HMAC válido de um cluster e um `job_id` legítimo pode alterar o estado do job.

A melhoria proposta: gerar um `webhook_token` aleatório por job, incluí-lo na callback URL (`?cluster=<uuid>&wt=<token>`), armazená-lo em `jobs.webhook_token`, e validá-lo no middleware `VerifyWebhookHmac` quando o upstream faz o callback. Defense in depth além do HMAC-SHA256 existente.

### Critério de aceite

- `wt=<token>` presente em todas as callback URLs geradas pelos 3 Actions (Provision, Remove, LifecycleAsync)
- Middleware rejeita `wt` inválido/ausente (para jobs com token) com `401 invalid_webhook_token`
- Jobs sem token (legacy/null) passam com log de aviso de segurança
- 225+ testes passando; CI verde
