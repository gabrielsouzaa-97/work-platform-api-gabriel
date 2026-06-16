# Objetivo de Arquitetura — Dois Contratos (API externa OpenAPI + protocolo NC interno)

## Visão em uma frase

Transformar o `work-platform-api` (control plane Laravel 12) num **middleman com dois
contratos explícitos e independentes**: para fora, uma **API OpenAPI estável e versionada**
no domínio meWork360 (consumida por clientes, parceiros, WHMCS, onboarding-api); para
dentro, um **contrato técnico com a plataforma Nextcloud** ("protocolo NC": SSH/`manage.sh`,
`occ-exec`, webhooks HMAC, e o Farm Agent outbound). O contrato interno pode mudar/endurecer
sem quebrar o externo — um clássico **Anti-Corruption Layer (ACL)** + **API Gateway/BFF**.

## Por que (dor atual, baseada no código real)

1. **Vazamento de protocolo NC para o cliente** — endpoints `PUT/POST /api/customers/{slug}/occ/*`
   (`OccController`) são passthrough cru de subcmds OCC. Quando a allowlist upstream bloqueia,
   o cliente recebe `403 occ_subcmd_not_allowed` com `subcmd`/`exit_code` do Nextcloud
   (ISSUE-011). Branding, quota, maintenance estão publicados mas quebrados upstream (D-02 pendente).
2. **Drift de contrato** — `docs/openapi.yaml` ainda promete envelope `{ success, message, data }`,
   mas o código retorna `{ error }` + `JsonResource`/`{ job_id }` (DOC-001). Paths documentados
   (`/occ/users`, `/occ/groups`, etc.) não batem 1:1 com `routes/api.php`.
3. **Orquestração jogada para o cliente** — quem integra precisa encadear manualmente:
   `POST /customers` → esperar readiness (`tenant_not_ready` 503) → criar admin → `apps/enable`
   → branding. Não há operação composta de negócio (onboarding).
4. **Acoplamento espalhado** — ~52 chamadas OCC/SSH espalhadas (auditoria 2026-06), sem um
   adapter único. Trocar SSH → Farm Agent hoje toca muitos pontos.

## O que já existe e deve ser preservado

- `ProvisionCustomerAction` / `RemoveCustomerAction` (provisionamento async, idempotency-key 24h,
  callback HMAC, branding via stdin <256KB ou SFTP `--staging-id`).
- `LifecycleAsyncAction` (`users:create`, `groups:*`, `apps:enable|disable` como jobs → 202 `job_id`).
- `JobTypeTranslator` (`create`↔`provision`, etc.) — já é um embrião de tradução de vocabulário.
- Máquina de estados do customer (`provisioning` → `provisioning_finishing` → `active` → `failed`).
- **Farm Agent (N19)** — transporte outbound: `POST /api/agent/v1/commands` (poll) e `/events`,
  `AgentTransportResolver` / `AgentUpstreamGateway` (escolhe SSH vs Agent por cluster).
- Webhook receiver HMAC-SHA256 + IP allowlist (`/api/jobs/hook`).
- `docs/PLATFORM-V2-PLAN.md` (Farm Agent em 3 fases, envelope JSON tipado, integrações comerciais).

## Stack e contexto

- Laravel 12 (PHP 8.x), Livewire (painel admin interno), Sanctum/api-key + sessão.
- PostgreSQL/MariaDB + Redis. Auth externa via Bearer (`/api-keys`).
- Upstream `nextcloud-saas-manager` (Bash + Redis worker) em repo separado (`work-platform-scripts`),
  acionado via SSH `nextcloud-manage ... --async` + webhook.
- Fase 13 do projeto (pós-MVP). Integrações comerciais previstas (N22): onboarding-api, WHMCS, Vindi.

## Escopo desejado da decisão (o que o plano deve resolver)

1. **Separação formal dos dois contratos**:
   - Contrato EXTERNO: novo namespace versionado (`/v1`) ou `openapi-external.yaml`, modelando
     **capabilities de negócio** (Tenant, Branding, Apps, Users, Onboarding, Job) — nunca subcmds NC.
     Erros de domínio próprios e estáveis (ex.: `tenant_not_ready`, `branding_unavailable`),
     com `retry_after` onde fizer sentido. Versionamento e política de deprecação.
   - Contrato INTERNO ("protocolo NC"): um **PlatformPort** (interface) com implementações
     `SshPlatformAdapter` e `AgentPlatformAdapter`. Só esse port conhece OCC, exit codes, staging,
     `manage.sh`. Consolidar as ~52 chamadas atrás dele.
2. **Onde o passthrough `/occ/*` deve ficar** — despublicar do contrato externo público?
   Mantê-lo só como API interna/admin? Como mapear capabilities que hoje falham por allowlist (D-02)?
3. **Operações compostas (saga)** — `POST /v1/onboarding` (tenant + admin + apps + branding) como
   prova do modelo, com endpoint de status expondo cada etapa. Como modelar idempotência e
   compensação/rollback parcial.
4. **Gestão dos dois specs** — fonte de verdade, geração de client SDK, testes de contrato
   (evitar repetir o drift DOC-001/CQ-F5-001), e CI que falhe se código divergir do spec externo.
5. **Caminho de migração sem big-bang** — alinhado às 3 fases do Platform V2 (SSH → Farm Agent →
   handlers tipados), de modo que trocar o transporte interno não toque o contrato externo.

## Restrições e princípios

- Sem big-bang; entregas incrementais com critérios de sucesso mensuráveis.
- Não renomear app ids/DNS em produção (legado congelado); greenfield nasce `work-*`.
- Segurança: mais forte por dentro (mTLS/token Farm Agent, HMAC webhook, allowlist OCC, argv vs stdin
  para segredos) sem expor superfície ao cliente externo. Nunca vazar stack trace/exit code upstream.
- Multi-tenant; auditoria LGPD (retenção 12 meses) já existente deve continuar.

## Entregáveis esperados do plano

- Decisão arquitetural clara (não um menu): topologia dos dois contratos, posição do PlatformPort,
  e o que acontece com `/occ/*`.
- Diagrama de módulos/camadas (ExternalController → ApplicationService → PlatformPort → Adapter).
- Roteiro em fases com critérios de sucesso mensuráveis e ordem de implementação.
- Riscos principais (incluindo over-engineering, duplo spec, falsa promessa sync) e mitigação.
