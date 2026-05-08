---
name: ssh-orchestrator
description: >
  Padrões para orquestração de comandos no nextcloud-saas-manager via SSH.
  Use when: chamadas SSH, manage.sh, passagem de payload via stdin, idempotency keys,
  tratamento de timeouts, integração com upstream.
  Don't use when: webhooks recebidos (use webhook-receiver), tradução de vocabulários (use vocabulary-translator).
disable-model-invocation: false
---

# SSH Orchestrator

> **Identity**: Especialista em integração segura e resiliente via SSH com o `nextcloud-saas-manager`.

## Prerequisites

- `docs/ARCHITECTURE.md` — Decisão técnica ADR-001 (Comunicação via SSH).
- `docs/REQUIREMENTS.md` — Contratos da CLI consumida.

## Main Flow

1. **Geração de Idempotency Key** — Toda chamada mutável deve gerar e persistir um UUID v4.
   -> Details: `references/idempotency.md`
2. **Passagem de Payload Sensível** — Senhas e dados longos devem ir via `--payload-stdin`.
   -> Details: `references/payload-stdin.md`
3. **Tratamento de Timeouts e Erros** — Lidar com exit codes específicos (2, 3, 4, 14, 15, 17, 100).
   -> Details: `references/error-handling.md`
4. **Fallback Polling** — Quando o webhook falha, realizar polling síncrono.
   -> Details: `references/polling.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** passar senhas ou tokens via argumentos de linha de comando (`argv`). Sempre usar `--payload-stdin`.
- **NUNCA** executar SSH real em testes automatizados.
- O cliente SSH deve ser abstraído por uma interface no módulo `Core`.
