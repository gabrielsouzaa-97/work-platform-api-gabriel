# Crítica SRE/Operações — Plano v0

**Papel:** Engenheiro de SRE/Operações | **Severidade geral:** **alta**

## (1) Falha/risco mais grave ignorado

O plano descreve uma cadeia assíncrona longa (API → `PlatformPort` → SSH *ou* Agent → webhook HMAC → estado do customer/saga) mas **não define observabilidade ponta-a-ponta nem alertas de jobs presos**. Hoje o `WebhookHandler` já trata pull SSH como fallback “non-fatal” sem métrica de callback perdido; com `OnboardingSaga` multi-etapa e dual-transport por cluster, um webhook ausente ou adapter divergente deixa tenant em estado híbrido (ex.: apps habilitados, branding falhou) **invisível ao on-call** até o cliente reclamar — janela de 5–15 min vira horas.

## (2) Premissa que não se sustenta

A garantia “trocar transporte interno **nunca** toca o contrato externo” ignora que **latência, semântica de retry e taxa de falha** dos adapters SSH vs Agent *são* contrato operacional. Fase A→D mantém legado + v1 + dois adapters sem canary por cluster nem monitor de paridade entre caminhos — regressão silenciosa é provável, não mitigada só por characterization tests em CI.

## (3) Melhoria concreta (uma)

Introduzir **`correlation_id` obrigatório** (`onboarding_id` → `job_id` → `operation_id` do Agent) propagado em logs estruturados, com métricas e alertas: (a) job não-terminal > SLA configurável, (b) webhook não recebido após dispatch, (c) divergência de outcome SSH vs Agent no mesmo `job_type`. Acoplar runbook por estado terminal parcial da saga (`retry` seguro vs escalação manual).

## (4) Severidade

**Alta** — custo operacional e risco de incidente crescem com saga parcial sem compensação automática nem visibilidade; migração incremental triplica superfície (legado/v1/adapters) sem plano de rollback de deploy nem reconciliação de dados da saga.
