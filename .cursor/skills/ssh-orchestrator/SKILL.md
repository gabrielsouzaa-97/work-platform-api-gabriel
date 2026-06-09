---
name: ssh-orchestrator
description: >
  Padrões para orquestração de comandos no nextcloud-saas-manager via SSH.
  Use when: chamadas SSH, manage.sh, passagem de payload via stdin, idempotency keys,
  tratamento de timeouts, integração com upstream.
  Don't use when: webhooks recebidos (use webhook-receiver), tradução de vocabulários
  (use vocabulary-translator), deploy/infra (use me360-deployer).
disable-model-invocation: false
---

# SSH Orchestrator

> **Identity**: Especialista em integração SSH segura com o upstream — stdin para segredos, retry só em falha de transporte.

## Prerequisites

- `docs/ARCHITECTURE.md` — ADR-001 (comunicação SSH)
- `docs/REQUIREMENTS.md` — contratos CLI consumidos
- `docs/SSH API Reference — Nextcloud SaaS.md`
- Skill irmã: `vocabulary-translator` (`cmdToCliArgv` obrigatório)
- Guardrails universais: `capabilities/guardrails.md`

## Main Flow

1. **Idempotency key** — UUID v4 persistido antes de chamada mutável
   -> Details: `references/idempotency.md` (quando existir; ver `IdempotencyKey` model)

2. **Payload sensível** — `--payload-stdin`; nunca senha em argv
   -> Details: `references/payload-stdin.md` (quando existir; ver `SshClient::runAsync`)

3. **Timeouts e exit codes** — 2, 3, 4, 14, 15, 16, 17, 100, 124
   -> Details: `references/error-handling.md` (quando existir; ver `SshRemoteException`)

4. **Fallback polling** — quando webhook falha (`jobs:poll-stuck`)
   -> Details: `references/polling.md` (quando existir; ver `PollStuckJobsCommand`)

5. **Guardrails** — iron law, red flags, checklist pré-SSH
   -> Details: `references/guardrails.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** senhas/tokens em argv — sempre `--payload-stdin`
- **NUNCA** SSH real em testes automatizados — mock `SshClientInterface`
- **NUNCA** duplicar `--async`/`--json` — `runAsync()` adiciona automaticamente
- **SEMPRE** argv de verb via `JobTypeTranslator::cmdToCliArgv()`
- Cliente SSH abstraído por interface no módulo `Core`
- Anti-racionalização: `references/guardrails.md`
