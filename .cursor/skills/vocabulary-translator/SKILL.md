---
name: vocabulary-translator
description: >
  Padrões para tradução bidirecional de vocabulários entre a API e o upstream.
  Use when: JobTypeTranslator, StateTranslator, validação de slug, mapeamento de enums,
  consistência de contratos.
  Don't use when: execução de SSH (use ssh-orchestrator) ou recebimento de webhooks (use webhook-receiver).
disable-model-invocation: false
---

# Vocabulary Translator

> **Identity**: Especialista em consistência de contratos e tradução de vocabulários.

## Os 3 vocabulários do sistema

O orquestrador opera com **três** vocabulários distintos para descrever a mesma operação async:

| # | Nome | Onde aparece | Exemplo | Tradutor |
|---|------|--------------|---------|----------|
| 1 | `cmd_canonical` (interno) | `Job.cmd_canonical`, `IdempotencyKey.cmd`, controllers/Livewire, AuditLog | `users:create` | — (origem) |
| 2 | `job_type` | webhook payloads do upstream, enum persistido | `user_create` | `JobTypeTranslator::cmdToJobType()` / `jobTypeToCmd()` |
| 3 | **CLI argv upstream** | argv passado ao `nextcloud-manage` via SSH | `['user', 'create']` | `JobTypeTranslator::cmdToCliArgv()` |

> **Histórico**: até a Sprint F5 (ISSUE-006), só existiam tradutores para #1↔#2. O vazamento de `cmd_canonical` direto no argv causou bug `cmd_not_allowed` em produção. `cmdToCliArgv()` fecha esse gap; o mapping `CMD_TO_CLI_ARGV` foi confirmado por SSH probing real contra cluster homolog (upstream v12.3.0). Ver `docs/DECISION-BRIEF.md` Decision #ARCH-4.

## Prerequisites

- `docs/REQUIREMENTS.md` — Feature 10 (Tradução de vocabulários).
- `docs/DECISION-BRIEF.md` — Decision #ARCH-4 (três vocabulários).

## Main Flow

1. **Validação Estrita de Slug** — Garantir que o slug do customer atende ao padrão restrito.
   -> Details: `references/slug-validation.md`
2. **Tradução de Estados (`StateTranslator`)** — Mapear `state` upstream → canônico com guard ortográfico (`done`/`finished` → `success`).
   -> Details: `references/state-translator.md`
3. **Tradução de Comandos cmd ↔ job_type (`JobTypeTranslator::cmdToJobType` / `jobTypeToCmd`)** — Mapear os 15 verbs entre API e enum persistido / webhook.
   -> Details: `references/job-type-translator.md`
4. **Tradução de Comandos cmd → CLI argv upstream (`JobTypeTranslator::cmdToCliArgv`)** — Mapear `cmd_canonical` para tokens do argv que o `nextcloud-manage` aceita. Lançar `BlockedOnUpstreamException` para verbs cujo equivalente upstream ainda não existe (atualmente: `groups:add`, `groups:remove`).
   -> Details: `references/job-type-translator.md` (seção "argv upstream")

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **3 vocabulários, 1 tradutor**: `JobTypeTranslator` é o ponto de verdade para tudo que parte de `cmd_canonical`. Adicionar um novo verb requer atualizar as **3 constantes** (`CMD_TO_JOB_TYPE`, `JOB_TYPE_TO_CMD`, `CMD_TO_CLI_ARGV`) no mesmo arquivo — visíveis em uma única tela.
- **NUNCA** injetar `cmd_canonical` diretamente no argv do SSH. Sempre passar por `cmdToCliArgv()`. Vazamento desse vocabulário foi a causa-raiz do ISSUE-006.
- **NUNCA** duplicar `--async`/`--json` no argv. O cliente `SshClient::runAsync()` adiciona essas flags automaticamente.
- **NUNCA** tentar normalizar slugs inválidos (ex: substituir `_` por `-`). Rejeitar com 422 imediatamente.
- Tradução `cmd_canonical ↔ job_type` deve ser bidirecional e estável (roundtrip teste obrigatório).
- Tradução `cmd_canonical → CLI argv` é unidirecional (upstream → canônico se faz via `job_type`).
- Verbs **blocked-on-upstream** lançam `BlockedOnUpstreamException`; controllers/Livewire mapeiam para **HTTP 501** / mensagem amigável. NUNCA retornar 500 para verbs explicitamente pendentes.
- Qualquer falha de tradução (verb desconhecido) lança `UnknownVerbException` (≠ blocked) — indica bug do caller.
- Testes unitários cobrem 100% dos pares de tradução mapeados + casos negativos (unknown + blocked).
