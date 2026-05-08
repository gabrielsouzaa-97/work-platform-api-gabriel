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

## Prerequisites

- `docs/REQUIREMENTS.md` — Feature 10 (Tradução de vocabulários).

## Main Flow

1. **Validação Estrita de Slug** — Garantir que o slug do customer atende ao padrão restrito.
   -> Details: `references/slug-validation.md`
2. **Tradução de Estados (StateTranslator)** — Mapear `state` com guard ortográfico.
   -> Details: `references/state-translator.md`
3. **Tradução de Comandos (JobTypeTranslator)** — Mapear os 15 verbs entre API e CLI.
   -> Details: `references/job-type-translator.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** tentar normalizar slugs inválidos (ex: substituir `_` por `-`). Rejeitar com 422 imediatamente.
- A tradução deve ser bidirecional (API → CLI e CLI → API).
- Qualquer falha na tradução (valor desconhecido) deve lançar uma exception clara e ser registrada.
- Testes unitários devem cobrir 100% dos pares de tradução mapeados.
