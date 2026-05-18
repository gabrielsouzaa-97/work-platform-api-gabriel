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

Os jobs assíncronos despachados via SSH não têm vínculo criptográfico entre o dispatch e o callback recebido. Qualquer payload HMAC-válido de um cluster com um `job_id` existente poderia alterar o estado do job.

**Design escolhido (revisão 2026-05-18)**: gerar um `webhook_token` aleatório por job e passá-lo ao upstream como argumento CLI do `nextcloud-manage` (`--webhook-token=<token>`). O upstream inclui esse token no payload JSON que envia ao callback — payload já coberto pelo HMAC-SHA256. O middleware `VerifyWebhookHmac` valida `payload['webhook_token']` contra `jobs.webhook_token` no DB. Callback URL não muda.

Vantagem vs token na URL: o token fica dentro do body HMAC-assinado, não exposto em access logs do servidor.

### Critério de aceite

- `--webhook-token=<token>` presente nos args SSH dos 3 Actions (Provision, Remove, LifecycleAsync)
- `jobs.webhook_token` populado em todos os novos dispatches
- Middleware rejeita token ausente/incorreto no payload com `401 invalid_webhook_token`
- Jobs sem token no DB (legacy/null) passam com log de aviso de segurança
- 225+ testes passando; CI verde
