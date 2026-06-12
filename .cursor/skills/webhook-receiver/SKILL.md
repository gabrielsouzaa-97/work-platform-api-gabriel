---
name: webhook-receiver
scope: project
created-by: manual
description: >
  Padrões para implementação e validação de webhooks recebidos do upstream.
  Use when: webhook receiver, HMAC-SHA256, validação de assinatura, IP whitelist,
  replay protection, atualização de estado de jobs, job.started, job.finished.
  Don't use when: chamadas SSH de saída (use ssh-orchestrator), deploy/infra (use me360-deployer).
disable-model-invocation: false
---

# Webhook Receiver

> **Identity**: Especialista em segurança e idempotência de callbacks upstream — HMAC primeiro, resposta rápida, estado consistente.

## Prerequisites

- `docs/ARCHITECTURE.md` — ADR-001 (webhooks)
- `docs/REQUIREMENTS.md` — Feature 8 (webhook receiver)
- `VerifyWebhookHmac`, `WebhookHandler`, `WebhookPayload` no código
- Skill irmã: `vocabulary-translator` (`StateTranslator`)
- Guardrails universais: `capabilities/guardrails.md`

## Main Flow

1. **Assinatura HMAC-SHA256** — header `X-Signature` vs secret ativo + grace history
   -> Details: `references/hmac-validation.md` (quando existir; ver middleware)

2. **IP whitelist** — origem do `cluster_server` conhecido
   -> Details: `references/ip-whitelist.md` (quando existir; ver middleware)

3. **Replay protection** — janela `WEBHOOK_REPLAY_WINDOW_SECONDS`
   -> Details: `references/replay-protection.md` (quando existir; ver middleware)

4. **Idempotência** — dedupe por `(job_id, event)`; persistir só em 2xx
   -> Details: `references/idempotency.md` (quando existir; ver handler)

5. **Guardrails** — iron law, red flags, checklist de mudança
   -> Details: `references/guardrails.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- Resposta **< 500ms** — não bloquear worker upstream
- HMAC inválido → **401** + audit crítico (não 500)
- Secret do banco descriptografado por cluster (`webhook_secret_history`)
- Aceitar `event=job.started` e `job.finished` (payload aditivo schema v1)
- Anti-racionalização: `references/guardrails.md`
