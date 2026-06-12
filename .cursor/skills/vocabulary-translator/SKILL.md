---
name: vocabulary-translator
scope: project
created-by: manual
description: >
  Padrões para tradução bidirecional de vocabulários entre a API e o upstream.
  Use when: JobTypeTranslator, StateTranslator, validação de slug, mapeamento de enums,
  cmd_canonical, job_type, cmdToCliArgv, consistência de contratos.
  Don't use when: execução de SSH (use ssh-orchestrator), webhooks recebidos (use webhook-receiver),
  deploy/infra (use me360-deployer).
disable-model-invocation: false
---

# Vocabulary Translator

> **Identity**: Guardião dos três vocabulários — nunca vazar `cmd_canonical` no argv SSH; tradução completa ou exceção explícita.

## Os 3 vocabulários

| # | Nome | Exemplo | Tradutor |
|---|------|---------|----------|
| 1 | `cmd_canonical` | `users:create` | — (origem interna) |
| 2 | `job_type` | `user_create` | `cmdToJobType()` / `jobTypeToCmd()` |
| 3 | CLI argv upstream | `['user','create']` | `cmdToCliArgv()` |

Histórico: ISSUE-006 — vazamento de #1 no argv. Decision #ARCH-4.

## Prerequisites

- `docs/REQUIREMENTS.md` — Feature 10
- `docs/DECISION-BRIEF.md` — Decision #ARCH-4
- `app/Modules/Core/Translators/JobTypeTranslator.php`, `StateTranslator.php`
- Guardrails universais: `capabilities/guardrails.md`

## Main Flow

1. **Slug** — padrão restrito; rejeitar, não normalizar
   -> Details: `references/slug-validation.md` (quando existir; ver `App\Rules\Slug`)

2. **StateTranslator** — upstream → canônico (`done`/`finished` → `success`)
   -> Details: `references/state-translator.md` (quando existir; ver classe)

3. **cmd ↔ job_type** — 15 verbs bidirecionais
   -> Details: `references/job-type-translator.md` (quando existir; ver constantes)

4. **cmd → CLI argv** — `cmdToCliArgv()`; `BlockedOnUpstreamException` → 501
   -> Details: `references/job-type-translator.md`

5. **Guardrails** — iron law, red flags, checklist de novo verb
   -> Details: `references/guardrails.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** `cmd_canonical` direto no argv SSH — sempre `cmdToCliArgv()`
- **NUNCA** duplicar `--async`/`--json` no argv
- **NUNCA** normalizar slug inválido — 422 imediato
- Novo verb → atualizar **3 constantes** no mesmo PR
- `BlockedOnUpstreamException` → HTTP **501**, nunca 500
- `UnknownVerbException` → bug do caller, não blocked
- Testes: 100% pares mapeados + unknown + blocked
- Anti-racionalização: `references/guardrails.md`
