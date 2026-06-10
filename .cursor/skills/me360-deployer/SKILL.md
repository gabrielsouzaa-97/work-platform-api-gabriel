---
name: me360-deployer
description: >
  Operações end-to-end do mework360-deployer-api: infraestrutura, Docker local e produção,
  deploy/atualização de versão, provisionamento de customer/TN, gates de prontidão, simulação local,
  paridade Dev, host fábrica (manage.sh), mework360-local FULL_LOCAL, meMail/tema, TN canário.
  Use when: deploy, atualizar versão, staging, produção, containers, infraestrutura, provisionar
  customer, criar cliente, criar TN, tenant, ambiente local, smoke test, está pronto, readiness,
  ISSUE-023, RUNBOOK, docker compose, rollback, homolog, simular local, dev.mework360, paridade,
  manage.sh, perfil provisionamento, mework360-local, FULL_LOCAL, meMail não abre, tema me360,
  NextCloud-SaaS-01, NextCloud-SaaS-02, SaaS-01, SaaS-02, VPS dev prod, fluxo contratação,
  ambientes independentes, cluster_server, dev vs prod.
  Don't use when: implementar endpoint REST (api-rest-patterns), SSH argv (ssh-orchestrator),
  vocabulários (vocabulary-translator), webhooks HMAC (webhook-receiver).
disable-model-invocation: false
---

# Deployer Ops

> **Identity**: Especialista em operar e validar o deployer — executa gates R1–R8 com evidência antes de declarar ambiente ou versão pronta.

## Prerequisites

- Docker Desktop com WSL2 (Windows) ou Docker nativo (Linux)
- Repositório clonado; `.env` copiado de `.env.example`
- **Mapa do ecossistema** lido: `references/ecosystem-map.md` (API + deploy-scripts + memail/RC/theme)
- Upstream local: `../mework360-deploy-scripts` (`docs/CONTRACTS.md`, `scripts/manage.sh`, `scripts/worker.sh`)
- `docs/RUNBOOK.md` e `docs/CI-CD.md` como referência humana
- Skills irmãs: `ssh-orchestrator`, `webhook-receiver`, `vocabulary-translator`, `api-rest-patterns`
- Guardrails universais: `capabilities/guardrails.md`

## Fast-Track

Condições (TODAS): usuário só quer subir painel local; sem provision; sem deploy remoto.
→ Seguir apenas `references/local-stack.md` § First-time setup + § Verify stack.

## Main Flow

1. **Carregar mapa do ecossistema** — quais repos participam do fluxo; o que a API não controla
   -> Details: `references/ecosystem-map.md`

1b. **Taxonomia de ambientes** — SaaS-01 (Dev) vs SaaS-02 (Prod); stacks independentes; fábrica+runtime; contratação por cluster
   -> Details: `references/environment-and-parity.md`

2. **Classificar intenção** — local dev | atualizar versão | provisionar customer/TN | paridade Dev | checar prontidão
   -> Details: `references/architecture-and-routing.md`

3. **Gates de prontidão (obrigatório antes de "pronto")** — executar R1–R8 com evidência
   -> Details: `references/readiness-gates.md`

4. **Stack local** — compose, migrate, seed, health, tiers de simulação
   -> Details: `references/local-stack.md`

5. **Deploy / atualização em VM** — build production, migrate, cache, rollback; upstream **antes** da API se contrato mudou
   -> Details: `references/production-deploy.md`

6. **Ciclo de provisionamento** — API → SSH → worker → webhook → probe; apps N4 (memail/theme) no upstream
   -> Details: `references/provision-lifecycle.md`

6b. **Pós-create (meMail + RC)** — `externalLocation`, RC shared, desabilitar `mail` store
   -> Details: `references/post-create-runbook.md`

7. **Comandos operacionais** — artisan via container, smoke, contract test
   -> Details: `references/quick-commands.md`

8. **Guardrails** — anti-racionalização, red flags, limites do escopo API vs scripts vs mail/RC
   -> Details: `references/guardrails.md`

## Rules

- Comunicação e clarificação: seguir `capabilities/communication-rules.md`
- **NUNCA** declarar "pronto" sem checklist R1–R8 explícito (mínimo R3+R5; provision exige R6–R8)
- **NUNCA** commitar `.env` nem colar chaves SSH / webhook secrets no chat
- **NUNCA** `docker compose down -v` nem `config:cache` com `APP_KEY` vazio
- **SEMPRE** deploy upstream antes da API quando contrato CLI/webhook mudou (ISSUE-022)
- **SEMPRE** `docker compose exec app` para artisan no Windows
- Anti-racionalização e red flags: `references/guardrails.md` + `capabilities/guardrails.md`
