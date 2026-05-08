---
name: webhook-receiver
description: >
  Padrões para implementação e validação de webhooks recebidos do upstream.
  Use when: webhook receiver, HMAC-SHA256, validação de assinatura, IP whitelist,
  replay protection, atualização de estado de jobs.
  Don't use when: chamadas SSH de saída (use ssh-orchestrator).
disable-model-invocation: false
---

# Webhook Receiver

> **Identity**: Especialista em segurança e idempotência para o recebimento de callbacks do `nextcloud-saas-manager`.

## Prerequisites

- `docs/ARCHITECTURE.md` — Decisão técnica ADR-001 (Webhooks).
- `docs/REQUIREMENTS.md` — Feature 8 (Webhook receiver).

## Main Flow

1. **Validação de Assinatura (HMAC-SHA256)** — Validar o header `X-Signature`.
   -> Details: `references/hmac-validation.md`
2. **IP Whitelist** — Garantir que a requisição vem de um `cluster_server` conhecido.
   -> Details: `references/ip-whitelist.md`
3. **Replay Protection** — Rejeitar callbacks muito antigos (ex: > 1h).
   -> Details: `references/replay-protection.md`
4. **Idempotência no Processamento** — Atualizar estado do job apenas se necessário.
   -> Details: `references/idempotency.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- O endpoint deve retornar 200 OK o mais rápido possível (< 500ms) para não bloquear o worker upstream.
- Falhas de assinatura (HMAC inválido) devem retornar 401 e registrar um alerta crítico no audit log.
- O `webhook_secret` deve ser buscado do banco (descriptografado) com base na origem da requisição.
