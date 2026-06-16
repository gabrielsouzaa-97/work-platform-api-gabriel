# Plano de Arquitetura FINAL — Dois Contratos (API externa `/api/v1` + protocolo NC interno)

> Autor: Arquiteto sênior (ETAPA 3 do painel adversarial — `tests/arch-panel.md`)
> Insumos: `v0.md` + 5 críticas (arquiteto, dev-senior, segurança, SRE, advogado), **todas severidade ALTA**
> Repositório-âncora: `work-platform-api` (Laravel 12 control plane)
> Status: **FINAL (DECIDIDO)** — supera o v0

---

## O QUE MUDOU vs. v0

Mudanças concretas, em ordem de impacto:

1. **Reordenação radical: nasce o "Sprint 0" (2 semanas) ANTES do PlatformPort.**
   O v0 colocava o port como Fase A (pré-requisito duro). Agora o valor entregável vem primeiro: (a) sanitização de erro na borda **atual** (`DomainError` no exception handler, sem `subcmd`/`exit_code`); (b) congelar `/occ/*` fora do spec público; (c) corrigir DOC-001 alinhando spec ao código; (d) rotas `/v1/*` como **aliases finos** sobre as Actions existentes. O port só é extraído **depois**, provado por **1 capability**. *(Incorpora advogado + dev-senior.)*

2. **AuthZ escopado por tenant+capability promovido a pré-requisito DURO de Sprint 0 — bloqueia abrir a v1 a terceiros.**
   O v0 não definia authn/authz da v1. Agora: middleware `VerifyExternalPrincipal`, `ApiKey.scopes` deixa de ser ignorado, binding explícito tenant↔principal, auditoria por capability. Sem isso, **nenhuma** rota `/v1` é exposta a WHMCS/onboarding-api/parceiros. *(Incorpora segurança — era a falha mais grave do v0.)*

3. **`execOcc` REMOVIDO da interface pública do `PlatformPort`.**
   O v0 institucionalizava `execOcc(OccCommand)` no port, anulando o ACL. Agora o port de domínio (`TenantLifecycle`) não tem `execOcc`; a válvula de operação admin chama o **adapter de Integração diretamente**, fora do contrato publicado. *(Incorpora arquiteto + dev-senior.)*

4. **Separação de bounded contexts: módulo `Integration` (comandos tipados, sem HTTP) vs. `TenantLifecycle` (linguagem publicada `/api/v1`).**
   O v0 tinha um God Port único espelhando o `JobTypeTranslator`. Agora há fronteira de domínio: `Integration` traduz argv/envelope; `TenantLifecycle` fala capabilities de negócio. *(Incorpora arquiteto, parcialmente — ver risco aceito R-1.)*

5. **Strangler incremental com port MÍNIMO (3-4 métodos), não consolidação big-bang dos ~52 pontos.**
   O v0 prometia "~52 → 1 adapter" como refactor puro. Agora extraímos um port mínimo (`createTenant`, `probeReadiness`, `setBranding`, `enableApps`), migramos só `ProvisionCustomerAction` + `LifecycleAsyncAction` primeiro, e **listamos explicitamente** `JobLogFetcher`, `CancelJobAction`, `CustomerSyncService`, comandos Artisan e Livewire `OccPanel` como ondas posteriores do roteiro (não escondidos no grep gate). Exige **characterization tests** antes de tocar cada caminho. *(Incorpora dev-senior.)*

6. **Observabilidade ponta-a-ponta é parte obrigatória da fase de transporte, não opcional.**
   `correlation_id` (`onboarding_id → job_id → operation_id`), alertas de job não-terminal > SLA, alerta de webhook ausente, monitor de paridade SSH vs Agent e runbooks de saga parcial. *(Incorpora SRE.)*

7. **D-02 (allowlist upstream) vira dependência DURA explícita e gate de release.**
   O v0 tratava como nota; agora nenhuma capability de branding/quota/maintenance entra na v1 "estável" enquanto o upstream (`work-platform-scripts`/Farm Agent) não a suportar. A saga `/v1/onboarding` fica **atrás de D-02 resolvido**. *(Incorpora advogado + arquiteto.)*

8. **Saga adiada (era Fase E imediata) → só após adoção medida da v1 + D-02.**
   *(Incorpora dev-senior + advogado.)*

---

## 1. Decisão em uma frase

Entregar **valor reversível primeiro** (sanitização de erro + congelamento de `/occ/*` + correção de spec + aliases `/v1` finos sobre as Actions, **com authz escopado por tenant+capability como porteiro**), e **só então** extrair incrementalmente um `PlatformPort` de domínio (sem `execOcc`, com contexto `Integration` separado do `TenantLifecycle`), provado capability a capability — mantendo a garantia de que trocar o transporte interno (SSH↔Agent) nunca toca o contrato externo, **desde que** acompanhada de observabilidade ponta-a-ponta e respeitando a dependência dura de D-02 para branding/quota.

---

## 2. Tratamento explícito de cada crítica

### 2.1 Segurança — AuthZ escopado (INCORPORADA, prioridade máxima)

**Crítica:** o guard `api-key` autentica como Operator inteiro e ignora `ApiKey.scopes`; abrir a v1 sem credencial escopada por tenant+capability é IDOR sistêmico / LGPD.

**Decisão:** **incorporada como pré-requisito duro do Sprint 0** (item de segurança bloqueante). Concretamente:
- Middleware `VerifyExternalPrincipal` em **todo** `/v1/*`, executado **antes** de qualquer Action/port.
- `ApiKey.scopes` passa a ser checado: escopos obrigatórios por capability (`tenants:write`, `branding:write`, `onboarding:run`, etc.).
- **Binding explícito tenant↔principal**: allowlist de slugs ou claim `tenant_id` na chave; checado em `/v1/tenants/{slug}/*` e `GET /v1/onboarding/{id}`.
- Auditoria por capability: `actor_id`/`api_key_id`/`integrator` no `AuditLog`.
- **PII da saga:** minimização — não persistir e-mail admin / branding em claro no hash de idempotência; reter só o necessário, com trilha por integrador.

**Critério de sucesso (gate de release da v1):** teste negativo provando que chave do parceiro A retorna **403 em tenant B**; nenhuma rota `/v1` aceita chave sem escopo correspondente.

> Nota: este item neutraliza a premissa frágil da crítica de segurança ("manter legado intacto dobra a superfície sem controles novos") — o `VerifyExternalPrincipal` e a checagem de `scopes` também passam a cobrir as rotas legadas reusadas pelos aliases.

### 2.2 Advogado do diabo — Sprint 0 de valor rápido (INCORPORADA)

**Crítica:** 5 fases empilhadas antes de entregar valor; D-02 ainda bloqueia branding/quota; sugere Sprint 0 reversível + aliases `/v1` finos; port só depois, provado por 1 capability; saga atrás de D-02.

**Decisão:** **incorporada quase integralmente.** A reordenação é a mudança #1 do "O QUE MUDOU". Os aliases `/v1` são finos sobre `LifecycleAsyncAction`/`ProvisionCustomerAction`. **Refinamento que adiciono:** o Sprint 0 **não** abre a v1 a terceiros sem o authz da §2.1 — os aliases nascem atrás do `VerifyExternalPrincipal`. D-02 vira gate explícito (mudança #7).

**Refuto parcialmente** apenas o tom "drift OpenAPI é risco menor que prazo": mantenho a correção de DOC-001 dentro do Sprint 0 porque o spec mentiroso é justamente o que impede onboarding-api de migrar com segurança — é barato e habilita a medição de tráfego que o próprio advogado pede.

### 2.3 Dev sênior — escopo da Fase A subestimado (INCORPORADA)

**Crítica:** o grep gate força refatorar muito além das 4 Actions (`JobLogFetcher`, `CancelJobAction`, `CustomerSyncService`, Artisan, Livewire `OccPanel`); falta characterization test; sugere strangler com port mínimo e adiar a saga.

**Decisão:** **incorporada.** O grep gate **deixa de ser critério de sucesso da primeira onda do port** — passa a ser meta de uma onda final, depois que cada superfície for migrada com characterization test próprio. Roteiro de migração do port agora lista explicitamente as ondas (ver §4, Fases 2/3). Saga adiada (mudança #8). Port mínimo de 3-4 métodos (mudança #5).

### 2.4 Arquiteto — God Port / bounded contexts (INCORPORADA com 1 risco aceito)

**Crítica:** `PlatformPort` vira catálogo de verbos NC; `execOcc` no port anula o ACL; separar `Integration` de `TenantLifecycle`; eventos de domínio em vez de `JobRef` genérico.

**Decisão:**
- **`execOcc` fora do port público — incorporado** (mudança #3).
- **Separação `Integration` vs `TenantLifecycle` — incorporada** como organização de módulos (mudança #4): `Integration` = adapters + tradução argv/envelope, comandos tipados (`Tenant.Create`, `Branding.Apply`), sem HTTP; `TenantLifecycle` = linguagem publicada da v1.
- **Eventos de domínio em vez de `JobRef` — RISCO ACEITO (R-1).** Reconheço o ponto, mas substituir `JobRef` por eventos de domínio (`TenantProvisioningStarted`) em toda operação long-running é uma reescrita do modelo Job atual, que hoje é o backbone de idempotência/polling/webhook. Faço a separação de contextos **mantendo `JobRef`** como handle de correlação na v1 inicial; eventos de domínio ficam como evolução posterior, não no caminho crítico de entrega de valor. Justificativa: YAGNI + caminho crítico de provisionamento estável.
- **Premissa "herda o grafo de dependências do upstream" — aceita como verdadeira e endereçada** via gate D-02 (mudança #7): não fingimos desacoplar o que o upstream ainda acopla; bloqueamos a capability em vez de maquiá-la.

### 2.5 SRE — observabilidade ponta-a-ponta (INCORPORADA)

**Crítica:** sem observabilidade nem alertas de job preso; trocar transporte SSH↔Agent É contrato operacional (latência/retry/falha); falta canary/paridade; runbooks de saga parcial.

**Decisão:** **incorporada** (mudança #6). A garantia "transporte não toca o contrato externo" é **emendada**: vale para o contrato *funcional* (schema/semântica HTTP), **não** para o contrato *operacional* — por isso a fase de transporte exige: `correlation_id` propagado, métricas/alertas de (a) job não-terminal > SLA, (b) webhook não recebido após dispatch, (c) divergência de outcome SSH vs Agent no mesmo `job_type`; canary por cluster ao trocar adapter; runbook por estado terminal parcial da saga. Sem esses sinais, a troca de adapter de um cluster **não** é liberada.

---

## 3. Topologia final (dois contextos, port sem `execOcc`)

```
┌───────────────── CONTRATO EXTERNO (estável, versionado, AUTENTICADO) ─────────────────┐
│  VerifyExternalPrincipal  → scopes + binding tenant↔principal + audit  (PORTEIRO)       │
│  App\Http\Controllers\Api\V1\*   (routes/api_v1.php → /api/v1)                           │
│    FormRequest + ApiResource (envelope alinhado ao código)                              │
│    DomainError → HTTP estável (sem subcmd/exit_code)                                     │
│            │ capability DTO (TenantSpec, BrandingSpec, ...)                              │
│            ▼                                                                              │
│  Módulo TenantLifecycle  — linguagem publicada                                          │
│    ProvisionCustomerAction / LifecycleAsyncAction (reaproveitados; aliases no Sprint 0) │
│    OnboardingSaga (adiada, atrás de D-02)                                                │
└────────────┼─────────────────────────────────────────────────────────────────────────┘
             │ fala em capabilities de domínio — NUNCA subcmd OCC
             ▼
┌───────────────── CONTEXTO INTEGRATION (sem HTTP, comandos tipados) ────────────────────┐
│  PlatformPort (mínimo): createTenant, probeReadiness, setBranding, enableApps           │
│    (SEM execOcc na interface pública)                                                    │
│   ┌──────────────┴───────────────┐                                                       │
│   ▼                              ▼                                                        │
│  SshPlatformAdapter            AgentPlatformAdapter                                       │
│   argv nextcloud-manage,        envelope JSON tipado (operation_id, callback_token)       │
│   occ-exec, exit-codes          ── correlation_id propagado nos dois ──                   │
│      ▲ válvula admin chama o adapter de Integração DIRETAMENTE (fora do spec público)     │
└────────────┼──────────────────────────────────┼────────────────────────────────────────┘
             ▼                                  ▼
   SSH nextcloud-manage --async        POST /api/agent/v1/commands
   + webhook HMAC /api/jobs/hook        + /events   [observabilidade obrigatória]
```

---

## 4. Roteiro FINAL em fases (com critérios mensuráveis)

> Ordem: **Sprint 0** (valor + segurança) → **Fase 1** (port mínimo provado por 1 capability) → **Fase 2** (migração por ondas + observabilidade) → **Fase 3** (despublicar `/occ/*` + mutações via port) → **Fase 4** (saga, atrás de D-02). Sprint 0 e o authz são gate duro de abertura externa.

**Sprint 0 — Valor reversível + porteiro de segurança (2 semanas).**
Sanitizar erros na borda atual (`DomainError` no exception handler) + congelar `/occ/*` fora do spec público + corrigir DOC-001 (spec alinhado ao código) + rotas `/v1/*` como aliases finos sobre as Actions + **middleware `VerifyExternalPrincipal` com `scopes` + binding tenant↔principal + audit por capability**.
*Sucesso:* nenhuma resposta de borda contém `subcmd`/`exit_code`/stack trace; `git diff` do spec vs. código limpo; chave do parceiro A recebe **403 em tenant B** (teste negativo); aliases `/v1` em produção atrás do porteiro.

**Fase 1 — PlatformPort mínimo, provado por 1 capability (branding).**
Extrair `App\Modules\Integration\Contracts\PlatformPort` (3-4 métodos, **sem `execOcc`**) + `SshPlatformAdapter` + `AgentPlatformAdapter` + factory; migrar **só** `ProvisionCustomerAction` + `LifecycleAsyncAction`; characterization tests antes de mover `JobTypeTranslator`/`AgentTransportResolver`.
*Sucesso:* `PUT /v1/tenants/{slug}/branding` servido 100% via port com paridade comportamental comprovada por characterization test; regra de transporte (`stagingId === null`) movida para a factory sem mudança de outcome observável.

**Fase 2 — Migração por ondas + observabilidade ponta-a-ponta.**
Ondas explícitas com characterization test cada: (a) `CustomerReadinessProbe`/`OccPassthroughService`; (b) `JobLogFetcher`/`CancelJobAction`/`CustomerSyncService`; (c) Artisan (`JobsPollStuckCommand`, `ClusterHealthCheckCommand`) + Livewire (`OccPanel`, `ClusterServers\Index`). `correlation_id` propagado; alertas de job preso / webhook ausente / divergência SSH vs Agent; canary por cluster.
*Sucesso:* grep gate (uso de `SshClientInterface`/`AgentUpstreamGateway` só em `Integration/Adapters`) passa no CI **sem regressão** (todos os caracterization tests verdes); dashboard com `correlation_id` ponta-a-ponta e alerta disparando em job não-terminal > SLA em staging.

**Fase 3 — Despublicar `/occ/*` + capabilities de mutação via port.**
`quota/{user}`, `apps`, `users` na v1 via port; `OccController` rebaixado a admin (chama adapter de Integração direto, fora do `openapi-external.yaml`); `Deprecation`/`Sunset` nas rotas legadas equivalentes — **somente** capabilities suportadas pelo upstream (gate D-02).
*Sucesso:* `/occ/*` ausente do `openapi-external.yaml`; onboarding-api consumindo `/v1/*` (consumidor real medido); capability bloqueada por D-02 retorna `404 capability_not_available` limpo (sem `subcmd`/`exit_code`).

**Fase 4 — `POST /v1/onboarding` (saga) — atrás de D-02 resolvido + adoção medida.**
`OnboardingSaga` com status por etapa, idempotência (`IdempotencyKey` 24h, PII minimizada), compensação assistida + runbook por estado terminal parcial.
*Sucesso:* signup completo (tenant→admin→apps→branding) por 1 chamada + polling; replay idempotente não duplica tenant; etapa falha expõe retry seguro; runbook validado em game-day de saga parcial.

---

## 5. Riscos ACEITOS (explícitos)

- **R-1 — `JobRef` mantido em vez de eventos de domínio.** Aceito (vs. arquiteto). Migrar para eventos de domínio é reescrita do backbone Job (idempotência/polling/webhook) fora do caminho de valor; fica como evolução posterior. *Mitigação: separação de contextos já isola o ponto de mudança futura.*
- **R-2 — Manutenção tripla (legado + v1 + adapters) durante a transição.** Aceito (vs. arquiteto/SRE), mas **limitado no tempo** por `Deprecation`/`Sunset` (N e N-1, 90 dias) e medição de tráfego antes de desligar legado.
- **R-3 — Capabilities de branding/quota/maintenance indisponíveis na v1 até D-02.** Aceito e tornado explícito (vs. advogado). Preferimos `404 capability_not_available` honesto a maquiar a borda; desbloqueio depende do `work-platform-scripts`/Farm Agent.
- **R-4 — Dependência de tooling code-first (Scramble ou similar) para o spec externo.** Aceito (do v0) por matar o drift na raiz; revisitar se a manutenção do tooling custar mais que o YAML manual.
- **R-5 — Compensação automática da saga não implementada (só assistida).** Aceito (vs. SRE): rollback de tenant provisionado é caro/arriscado; mitigado por runbook + retry idempotente por etapa + alertas de estado parcial.

---

## 6. Resumo das decisões-chave

1. **Sprint 0 primeiro:** sanitizar erro + congelar `/occ/*` + corrigir DOC-001 + aliases `/v1` finos — **atrás do porteiro de authz**.
2. **AuthZ escopado por tenant+capability é gate duro** de abertura externa (`VerifyExternalPrincipal`, `ApiKey.scopes`, binding, audit, teste negativo 403 cross-tenant).
3. **`execOcc` fora do port público**; contextos `Integration` (comandos tipados, sem HTTP) e `TenantLifecycle` (linguagem `/api/v1`) separados.
4. **Strangler com port mínimo (3-4 métodos)** provado por 1 capability; ondas de migração (Livewire/Artisan incluídos) com characterization tests; grep gate é meta final, não da primeira onda.
5. **Observabilidade obrigatória** na fase de transporte (`correlation_id`, alertas de job preso, paridade SSH vs Agent, runbooks).
6. **D-02 é dependência dura**: branding/quota/maintenance e a saga só entram na v1 com suporte upstream comprovado.
