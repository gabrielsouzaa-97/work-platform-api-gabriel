# Issues — mework360-deployer

> Fonte de verdade para enhancements, melhorias e change requests. Bugs e findings de segurança → docs/FINDINGS.md.

## Índice

| ID | Tipo | Título | Módulo | Prioridade | Status |
|----|------|--------|--------|------------|--------|
| ISSUE-001 | change_request | Per-job webhook_token na callback URL | Jobs, Core | HIGH | open |
| ISSUE-002 | postmortem | Webhook 401 — worker upstream não recarregou novo secret | ClusterServers, Webhook | HIGH | mitigated (upstream PR pendente) |
| ISSUE-003 | postmortem | Webhook 422 — vocabulário `finished` não mapeado + dedupe persistia em falha | Jobs, Core, Webhook | HIGH | fixed in API (upstream issue #15 aberta) |
| ISSUE-004 | change_request | Webhook receiver aceita `event=job.started` (callbacks de transição) + dedupe per `(job_id, event)` | Jobs, Core, Webhook | HIGH | implemented |
| ISSUE-005 | change_request | Webhook receiver loga payload em nível debug quando APP_ENV=local | Jobs, Webhook | LOW | implemented |
| ISSUE-006 | postmortem | Lifecycle async envia vocabulário canônico-API ao upstream (`users:create` em vez de `user-create`) + duplica `--async --json` | Customers, Core/Ssh | HIGH | open (Fix Brief aprovado) |
| ISSUE-007 | change_request | E2E browser coverage via Dusk/Playwright para Livewire (cobre wire:submit/click real, MeRC ribbon do bug QA-F5-019) | DevOps, Livewire | MEDIUM | open (backlog — sprint N-UI dedicada) |
| ISSUE-008 | change_request | Fluxo de "Esqueci a senha" para operadores (broker nativo Laravel) | Auth | MEDIUM | open |
| ISSUE-009 | change_request | Logs de Job ausentes na tela `queue/{jobId}` — popular `jobs.summary` via SSH pull pós-`job.finished` | Jobs, Core/Ssh, Webhook | HIGH | **closed (2026-06-19)** — evidência LAB N35.6 (job `1e036c43-…`, summary POPULATED) |
| ISSUE-010 | postmortem | Callback `provision success` prematuro — tenant não ready para `users:*` (~10 min pós-callback) | Jobs, Customers, Webhook | CRITICAL | closed (F8) |
| ISSUE-011 | postmortem | Diagnóstico errado em comentários do `OccController`: causa real é allowlist de subcmd no `occ-exec` upstream, não "flag stripping" | Occ, Core/Ssh | CRITICAL | implemented — **validação APROVADA (R1)** |
| ISSUE-012 | bug | 404 em rotas inexistentes sob `/api/*` retorna HTML completo do Laravel (~30 KB) quando o cliente não envia `Accept: application/json` — info leak + DX ruim | Core (HTTP layer) | HIGH | closed (F9 — validação APROVADA R1) |
| ISSUE-013 | bug | Callbacks de webhook chegam com `exit_code=null` e `summary=null` — causa raiz upstream (#23); mitigação API ISSUE-014 | Jobs, Webhook, Core/Ssh | HIGH | open upstream; **prod 2026-06-02: 1/5 jobs 7d** (não 100%) — ver ISSUE-013 §Validação |
| ISSUE-014 | bug | `JobLogFetcher` SSH fallback falha 100% com exit 101 (`cmd_not_allowed`) — argv incorreto inclui `<client>` em comando de introspection `job`; descoberto durante investigação de ISSUE-013 | Jobs, Core/Ssh | MEDIUM | fixed (fix/issue-014-job-log-fetcher-argv) |
| ISSUE-015 | enhancement | `WebhookHandler` salva apenas subset reconstruído em `audit_logs.payload` (5 chaves) em vez do payload raw — descoberto durante investigação de ISSUE-013, mascarou hipótese inicial | Jobs, Webhook | MEDIUM | open (observabilidade — fast-track) |
| ISSUE-016 | change_request | 5 endpoints OCC mutativos indisponíveis — allowlist upstream bloqueia subcmds (quota/branding/maintenance) | Occ, Core/Ssh, Livewire | HIGH | mitigated (fast-track F?-OCC-1..3 — contrato OpenAPI + OccPanel UX; D-02 / #ARCH-7 pendente) |
| ISSUE-017 | bug | OCC argv com espaços falha no hop SSH `ncsaas-api` ForceCommand — quota `"5 GB"` e branding `"Acme Corp"` retornam exit 16 via API apesar de funcionarem no node | Occ, Core/Ssh | HIGH | open (fix quota compact + single-quote; branding validar pós-deploy) |
| ISSUE-018 | bug | Slug bloqueado após falha de provisioning — `Customer` com status `failed` não é soft-deletado, impede re-provisioning com mesmo slug | Customers, Jobs/Webhook | HIGH | open (Fix Brief aprovado — Sprint F11) |
| ISSUE-019 | bug | Branding não garantido no payload do job `create` quando cliente tem logo cadastrado — logo/background devem ir como `branding.*_data_url` via stdin ou `--staging-id` | Customers | MEDIUM | fixed API (F13); **e2e pendente upstream** (ISSUE-022) |
| ISSUE-020 | bug | Readiness probe vaza `phpseclib` `ConnectionClosedException` quando conexão SSH pooled fecha antes de `exec()` | Core/Ssh, Customers | MEDIUM | fixed (Sprint F12) |
| ISSUE-021 | change_request | OpenAPI global desalinhado do formato real (`{ error }` + JsonResource vs envelope `{ success, message, data }`) | Core, docs | MEDIUM | open |
| ISSUE-022 | change_request | Cross-repo: fechar contrato API ↔ `mework360-deploy-scripts` (webhook payload, branding stdin, OCC allowlist) | Core, Jobs, Customers, Occ | HIGH | open (coordenação) |
| ISSUE-023 | change_request | Validação LAB pós-F10: deploy `api.lab` + smoke `/queue/{id}` + schema ops (`failed_jobs`) | Jobs, DevOps | MEDIUM | **closed (2026-06-19)** — Sprint N35; job `1e036c43-23f7-4dfa-8d8b-f9a028b2c5b6`; OPS-001 decisão B |
| ISSUE-024 | change_request | Automatizar config meMail no `create` (externalLocation, forceSSO, emailAddressChoice, disable `mail`) — eliminar runbook manual pós-create | Cross-repo (deploy-scripts), Customers, Webhook | HIGH | open |
| ISSUE-025 | change_request | Evoluir `mework360-roundcube` para distribuição: Dockerfile pinado + camada B migrada + deploy por tag (dev = baseline; replica prod) | Cross-repo (mework360-roundcube, memail, deploy-scripts) | HIGH | open |
| ISSUE-026 | bug | RC prod desalinhado do dev: cookie `domain=.mework360.com.br` (SEC-002 vivo) + `frame-ancestors *` (clickjacking) + imagem `:latest` sem pin nos 2 hosts | Cross-repo (infra SaaS-01/SaaS-02) | HIGH | open |
| ISSUE-027 | change_request | Teto de escala Redis upstream: `--databases 17` limita ~15 tenants com dbindex dedicado (prod já tem 11) | Cross-repo (deploy-scripts) | HIGH | open |
| ISSUE-028 | change_request | Remover `\|\| true` de passos críticos do `cmd_create`/`cmd_update` upstream + reportar falha parcial no webhook (readiness honesto R6–R8) | Cross-repo (deploy-scripts), Jobs, Webhook | MEDIUM | open |
| ISSUE-029 | change_request | Limites de CPU/memória no template docker-compose do tenant (isolamento de recursos no host compartilhado) | Cross-repo (deploy-scripts) | MEDIUM | open |
| ISSUE-030 | change_request | Corrigir SSRF no meMail (SEC-001/SEC-006): teste de conexão e autodetect IMAP sem bloqueio de redes privadas | Cross-repo (mework360_memail) | MEDIUM | open |
| ISSUE-031 | change_request | Smoke E2E versionado do fluxo SSO meMail↔Roundcube (login, cookies, troca de perfil) rodando pós-create | Cross-repo (memail), QA, Customers | MEDIUM | open |
| ISSUE-032 | ops | Remover tenants de teste do host prod SaaS-02 (teste, teste2, gabrielteste08062026; mercadodoconstrutor mantido por decisão do usuário) + limpar registros no painel | Ops (SaaS-02), Customers | HIGH | **closed (2026-06-10)** — 3 removidos com backup, painel atualizado |
| ISSUE-033 | security | Conta seed `admin@mework360.local` é o ÚNICO admin ativo no deployer prod — criar admin nominal antes de desativar a seed (risco lockout) | Auth, Ops | HIGH | open — decisão usuário 2026-06-10: manter seed por ora |
| ISSUE-034 | change_request | 6 tenants prod sem registro no painel (76fibra, alloha, meltech, mework360, nextcloud-02, totum) — backfill via `customers:sync`; mework360=conta real colaboradores, demais=demo | Customers, Ops | MEDIUM | **closed (2026-06-10)** — sync inserted=6, painel consistente com host |
| ISSUE-035 | investigacao | Tabela `personal_access_tokens` ausente no banco do deployer prod — API Bearer (Sanctum) não pode funcionar; verificar migrations pendentes em prod | Core, DevOps | HIGH | **closed (2026-06-10)** — premissa incorreta: projeto não usa Sanctum; auth Bearer usa `api_keys` (existe em prod); `failed_jobs` segue em OPS-001/ISSUE-023 |
| ISSUE-036 | bug | Containers `*-push` (notify_push) em `Restarting (127)` em 4 tenants do SaaS-02 | Cross-repo (deploy-scripts) | MEDIUM | open |
| ISSUE-037 | security | `ApiKey.scopes` nunca aplicado + sem autorização por tenant — qualquer chave age sobre qualquer customer (IDOR latente; vira CRITICAL ao abrir `/v1` a terceiros) | Core (Auth/api-key), Customers | HIGH | **corrigido local** — Sprint F15 (2026-06-16) |
| ISSUE-038 | change_request | API externa `/api/v1` com dois contratos (OpenAPI estável + protocolo NC interno via ACL/PlatformPort) — ADR do painel adversarial | Core (HTTP/Auth), Customers, Occ, Agents | HIGH | **Fases 0–4 concluídas** (N30–N34, branch `sprint/N34`; validation R2 APROVADA) — adoção N22 pendente |
| ISSUE-039 | bug | CI vermelho no `main` — regressão de testes pós-N19 + `phpseclib` desatualizado | ClusterServers, Audit, Core | HIGH | **closed (2026-06-16)** — Sprint F14 merge PR #112; CI verde |
| ISSUE-040 | change_request | LAB mirror de prod: VM dedicada para o control plane (`api.lab`) separada do upstream — SSH/webhook cross-host real | DevOps, Infra | MEDIUM | **in_progress** — sid=65 @ `128.201.61.110`: bootstrap CONCLUÍDO 2026-06-22 (Docker 26.1.5 + Compose v5.1.4 + Traefik LE + control plane src@`83880bd` + migrations); `https://api.lab.mework360.com.br/up` → **200** TLS OK. Pendente: cadastro `cluster_servers` → `.108` (chave SSH `ncsaas-api`), gates R1–R8, orphan sid=64 `.109` |
| ISSUE-042 | bug | `docker-compose.lab.yml` — overlay do `worker` define `entrypoint` com o comando completo (`php artisan queue:work redis …`), mas o `command:` do compose-base é **anexado** → "Too many arguments to queue:work command". Worker entra em CrashLoop. Fix: `command: !override []` no serviço `worker` (mesmo padrão de `volumes: !override []`). Descoberto no deploy greenfield LAB N25 (.110); mitigado só no artefato deployado da VM até N36 | DevOps, Jobs | HIGH | **fixed no repo (2026-07-03, sprint/N36, commit `d480080`)** — `docker compose config` OK; histórico: mitigação manual na VM N25/N36 deploy |
| ISSUE-041 | bug | ~~Dispatch lifecycle async via `PlatformPort` trava em `--payload-stdin`~~ **Root cause**: `manage` não lia `REDIS_PASSWORD_FILE` pós-bl07 → NOAUTH → `idem_check` mascarava como `conflict` | Customers, Integration, Core/Ssh | HIGH | **closed (2026-06-19)** — UP-A/UP-B aplicados LAB + backport `work-platform-scripts` |
| ISSUE-043 | change_request | Apontar a API para o ambiente que será PRODUÇÃO: piloto image-mode `cloud.image-pilot.mework360.com.br` (`.120`); tenants prod = `<tenant>.mework360.com.br` | Customers, ClusterServers, Dns, Integration, Infra | HIGH | open — **fase inicial (N36) concluída** com gate E2E canário `canario-n36e` (2026-07-04); cutover domínio `<tenant>.mework360.com.br` e migração SaaS-02 fora do escopo N36 |
| ISSUE-044 | bug | CI vermelho no `main` pós-F3 (`6b29717`): 7 testes falham — `OnboardingSagaTest` ×4 (`Unknown suite catalog app_id: calendar` → 422) + `AgentTransport`/`Provision` ×3 (mocks `runAsync` sem `--suite-catalog` no argv esperado) | Customers, Onboarding, QA/CI | HIGH | **fixed (2026-07-03, sprint/N36 PR #128)** — evidência pré-fix: run main 28349563271 |
| ISSUE-045 | bug | Cross-repo `work-platform-scripts`: `dispatch.sh` D3.9b não re-injeta `--image-mode`/`--suite-catalog` no `args_json` Redis — create async via API roda modelo legado silenciosamente (NC-ARCH-017) | Cross-repo (work-platform-scripts), Jobs, Customers | CRITICAL | **fixed (2026-07-04)** — fix upstream `ba53ecc` + deploy `.120` + canário `canario-n36e` gate PASS |
| ISSUE-046 | bug | Cross-repo `.108` (LAB upstream): create suite-catalog aponta `deploy_shell` para host labwork `.112` (`apply-lab.sh` ausente) → exit 1. Diagnóstico 3 camadas: (1) suite-deploy misdirecionado **[fix config]**, (2) webhook 401 por falta de `webhook_secret_history` no cadastro N25.3 **[fixed]**, (3) readiness gate exige suíte me360 que o create local não instala **[escopo]** | Cross-repo (work-platform-scripts), Jobs, Customers, Webhook | HIGH | **parcial** — create+webhook OK; gate completo N25.4 depende de decisão de escopo (suíte no `.108` vs `.112`) + fix estrutural via `/pmo fix` |
| ISSUE-047 | enhancement | API Console fase 1: viewer privado de documentação (`/docs/api` via Scalar renderizando `openapi-external.yaml`) + seleção de scopes v1 na criação de credenciais em `/api-keys` | Core (Auth/api-key), Livewire, docs | MEDIUM | **fixed (2026-07-05)** — Sprint N37; PR #136; deploy LAB `8e58fed` |
| ISSUE-048 | bug | Painel LAB sem CSS (nginx sem `public/build`) + Livewire `/customers/create` não envia `image_mode` (gap N36) | Livewire, DevOps | HIGH | **fixed (2026-07-05)** — Sprint N38; deploy LAB `.110` |
| ISSUE-049 | change_request | UX operador: provisionamento + OCC — normalizar FQDN, feedback async, lista usuários, readiness visível, retrofit visual `customers/*` | Livewire, Customers, Occ, ClusterServers | HIGH | **fixed (2026-07-05)** — Sprint N39; PR #135; deploy LAB `8e58fed` |
| ISSUE-050 | change_request | Read model local de usuários de tenant (`tenant_users`) + política "nenhum cliente tem admin NC" — API como única escritora; elimina SSH síncrono da aba Usuários (lenta) | Customers, Occ, Livewire, DB, Cross-repo (provision policy) | HIGH | **planned — Sprint N40** (2026-07-05; brief PASS_WITH_NOTES) |
| ISSUE-051 | change_request | Control plane de produto: planos (quotas), tenant com seleção de apps, templates de usuário (papéis/permissões) — API-first, sem apps NC nesta fase | Product, Customers, Livewire, API v1, DB | HIGH | **concluída (fase 1)** — N41–N43 + F16–F20 (2026-07-06); backlog F17 MEDIUM/LOW em FINDINGS |
| ISSUE-052 | bug | `/docs/api/spec` retorna 404 na imagem Docker de produção/LAB — `openapi-external.yaml` ausente após `rm -rf docs` no Dockerfile | DevOps, Livewire, docs | HIGH | **fixed (2026-07-08)** — Sprint F21; deploy LAB `95d1152` |
| ISSUE-053 | bug | Rotas admin entregues (`/plans`, `/farms`) sem link na sidebar — operador não descobre features deployadas | Livewire, Product | MEDIUM | **fixed (2026-07-08)** — Sprint F21; deploy LAB `95d1152` |
| ISSUE-054 | bug | Painéis Product admin (Planos etc.) com texto ilegível no dark theme — inputs/selects sem `text-on-surface`; popup nativo do `<select>` renderizado pelo OS (GTK dark) ignora CSS | Livewire, Product, CSS | MEDIUM | **fixed (2026-07-08)** — Sprint F22-q (+q.2/q.3/q.4); deploy LAB `7492358`; merge `c50c9ea` (PRs #149/#150/#151/#153); validado pelo operador |
| ISSUE-055 | change_request | `POST /api/v1/tenants` com `objectstore: { enabled, bucket? }` — S3 primary storage MeStorage no provision image-mode (ENH-014 Fase B; credenciais resolvem no host, D8) | Customers, API v1, Livewire, Cross-repo (work-platform-scripts ENH-014) | HIGH | **planned — Sprint N44** (2026-07-08); bloqueada pelo gate da Sprint N56 upstream (Fase A) |
| ISSUE-056 | change_request | UX OCC Grupos — governança explícita, projeção `tenant_groups`, pickers e confirmações (DESIGN.md §9) | Livewire, Customers, Occ, DB, API v1 | HIGH | **implemented — Sprints N45+N46** (2026-07-08); aguarda VERIFY CI |
| ISSUE-057 | change_request | Contrato de provisionamento desacoplado do readiness gate — os 5 entry points aceitam payloads que nunca chegam a `active` (payload default `{}` incluso); OpenAPI documenta o exemplo condenado | Customers, Product, Onboarding, Billing, Livewire, docs | HIGH | open — **decisão `ADOPT_A_WITH_B`** (deliberar 2026-07-09); A→F28, B→N stub; findings ARQ-A1..A3, ARQ-A6..A8 |
| ISSUE-058 | bug | Sucesso silencioso: webhook `state=success` com `occ_command_failed` embutido no summary projeta read-model falso (users/groups/saga); grupos de template não validados | Jobs, Webhook, Customers, Onboarding, Occ | HIGH | **fixed (F27)** — PR #163; parser JSON + effectiveState + ResolvedTenantGroupsValidator |
| ISSUE-059 | bug | `failed` por readiness timeout é beco sem saída: slug bloqueado p/ retry (gap do ISSUE-018/F11), sem cleanup na UI, sem override manual, saga onboarding presa em `Running` | Customers, Jobs, Livewire, Onboarding | CRITICAL | **fixed (F26)** — PR #162; Remover UI + soft-delete deprovision + promote + onboarding Failed |
| ISSUE-060 | bug | `jobs:poll-stuck` atualiza job mas não replica efeitos do `WebhookHandler` (transição de customer, probe, projectors) — webhook perdido deixa customer em `provisioning` eterno | Jobs, Customers, Webhook | HIGH | **fixed (F26)** — PR #162; poll terminal → WebhookHandler pipeline |
| ISSUE-061 | change_request | Matriz canônica de estados do customer (status × ações permitidas) + `failure_reason` exposto em API/UI + gate de status no OCC mount e lifecycle async | Customers, Livewire, Occ, API | MEDIUM | open — findings ARQ-D1..D3, ARQ-A4 |

---

## ISSUE-057 — Contrato de provisionamento desacoplado do readiness gate

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Módulos**: Customers, Product, Onboarding, Billing, Livewire, docs
- **Origem**: Auditoria arquitetural 2026-07-09 (incidente "tenant failed com URL viva" — ISSUE-046 camada 3 generalizada)
- **Findings**: ARQ-A1, ARQ-A2, ARQ-A3, ARQ-A6, ARQ-A7, ARQ-A8 (docs/FINDINGS.md)

**Problema**: Os 5 entry points de criação de tenant (API legacy, API v1, Livewire Create, `POST /v1/onboarding`, WHMCS) validam formato/plano/catálogo, mas nenhum cruza o payload com os requisitos do `TenantReadinessGateChecker`. Em cluster legado (`image_mode=false`), o gate exige `mework360_memail` + `me360_theme` + SSO — apps que não estão no suite catalog e que nenhum payload aceito pela API consegue instalar. Resultado: tenant provisionado (infra viva) que expira `failed` após ~1200s. O payload default `{}` e o exemplo `minimal` do `openapi-external.yaml` já reproduzem o problema.

**Decisão de produto (2026-07-09, `/deliberar` Tier 2)**: **`ADOPT_A_WITH_B`**
- **A** (contenção): rejeitar na borda (422) payloads *determinísticamente impossíveis* de passar no readiness gate legado — fail-fast, sem infra órfã.
- **B** (causa raiz): garantir instalação da suíte me360 (`mework360_memail` + `me360_theme` + SSO) no fluxo suite-catalog — sprint cross-repo separada (`work-platform-scripts`); decompor se risco-G.
- **C** rejeitada como política de provisionamento (máscara falha; não cobre WHMCS/onboarding). Escape auditado só depois de ARQ-C1/ISSUE-059.
- **Condições**: contrato compartilhado `ProvisioningReadinessContract` (borda + gate, sem duplicar lista de apps / sem importar checker SSH na HTTP); avaliar payload **resolvido** (defaults+plano+catálogo); um validador nos 5 entry points; 422 acionável (`LEGACY_READINESS_UNSATISFIABLE`) + OpenAPI (ARQ-A8); ponte operacional = `image_mode=true` (já entregue N36.5); flag opcional `legacy_me360_capable` por cluster para reabrir legado pós-B.
- **Escopo de sprint**: A → F28; B → stub N (cross-repo); recovery CRITICAL (ISSUE-059) → F26 primeiro.

**Critério de aceite**: nenhum entry point aceita payload que o gate correspondente não pode aprovar; OpenAPI com exemplos válidos e contrato de readiness documentado; painel/WHMCS/onboarding roteados pela mesma validação da API; B entregue ou legado bloqueado até `legacy_me360_capable`.

---

## ISSUE-058 — Sucesso silencioso em webhooks e projeções

- **Tipo**: bug
- **Prioridade**: HIGH
- **Módulos**: Jobs, Webhook, Customers, Onboarding, Occ
- **Origem**: Auditoria arquitetural 2026-07-09
- **Findings**: ARQ-B1, ARQ-B2, ARQ-B3, ARQ-A5 (docs/FINDINGS.md)

**Problema**: O upstream retorna `state=success, exit_code=0` mesmo quando um subcomando embutido falhou (evidência real: `users:create` com summary `{"error":"occ_command_failed","subcommand":"group:adduser","stdout":"group not found"}`). `WebhookHandler`, projectors (`TenantUserProjector`/`TenantGroupProjector`), `OnboardingSaga` e o poll do OccPanel confiam no estado canônico sem inspecionar o summary — read-model e UI reportam sucesso com upstream inconsistente. Relacionado ao ISSUE-028 (upstream honesto), mas o lado API precisa se defender independentemente.

**Critério de aceite**: parser de summary detecta envelopes de erro embutidos; jobs parciais não projetam como sucesso pleno; saga não avança step com falha parcial; painel surfa o aviso; grupos herdados de template validados antes do dispatch.

---

## ISSUE-059 — `failed` por readiness timeout é beco sem saída

- **Tipo**: bug
- **Prioridade**: CRITICAL
- **Módulos**: Customers, Jobs, Livewire, Onboarding
- **Origem**: Auditoria arquitetural 2026-07-09 (incidente do operador: tenant `failed` com URL viva e painel travado)
- **Findings**: ARQ-C1, ARQ-C2, ARQ-C4, ARQ-C5 (docs/FINDINGS.md)

**Problema**: `readiness_timeout` seta `status=failed` sem soft-delete — o fix do ISSUE-018 (F11) só cobre o webhook `provision.failed`, então re-provisionar o mesmo slug retorna 422 `slug.unique`. A UI não oferece remover/deprovisionar `failed` (a action aceita via API), não existe override manual `provisioning_finishing → active`, e a saga de onboarding correlacionada fica `Running` para sempre. Infra órfã viva no upstream sem caminho de recovery.

**Critério de aceite**: retry de slug possível após `failed` por timeout (com política explícita para a infra órfã); painel expõe cleanup para `failed`; override manual auditado; saga terminalizada com motivo estruturado.

---

## ISSUE-060 — `jobs:poll-stuck` não fecha o ciclo do WebhookHandler

- **Tipo**: bug
- **Prioridade**: HIGH
- **Módulos**: Jobs, Customers, Webhook
- **Origem**: Auditoria arquitetural 2026-07-09
- **Findings**: ARQ-C3 (docs/FINDINGS.md)

**Problema**: O fallback agendado (5min) atualiza `job.state`/`exit_code`/`last_poll_at` mas não dispara nada do que o `WebhookHandler` faria no terminal: sem transição de status do customer, sem `ProbeCustomerReadinessJob`, sem projectors. Webhook perdido (`callback_failed` upstream) deixa o job `success` e o customer `provisioning` eterno.

**Critério de aceite**: resultado do poll roteado pela mesma pipeline terminal do webhook (transições + probe + projeções), idempotente com o webhook que possa chegar depois.

---

## ISSUE-061 — Matriz canônica de estados do customer

- **Tipo**: change_request
- **Prioridade**: MEDIUM
- **Módulos**: Customers, Livewire, Occ, API
- **Origem**: Auditoria arquitetural 2026-07-09
- **Findings**: ARQ-D1, ARQ-D2, ARQ-D3, ARQ-A4 (docs/FINDINGS.md)

**Problema**: `CustomerLifecycleStatus` documenta 3 constantes; os estados reais (`failed`, `removing`, `removed`, `error`) têm regras ad hoc espalhadas por views, actions e controllers. Consequências: OCC acessível por URL direta em tenant não-`active`, lifecycle async parcialmente bloqueado (`groups:*`/`apps:*` passam pré-active), motivo do `failed` invisível fora do audit trail.

**Critério de aceite**: enum/matriz central (status × ações permitidas) consumida por UI, controllers e `LifecycleAsyncAction`; `failure_reason` persistido e exposto em `CustomerResource` + painel; OCC mount valida status.

---

## ISSUE-055 — Objectstore S3 no `POST /api/v1/tenants` (ENH-014 Fase B)

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: **planned — Sprint N44** (2026-07-08); **bloqueada** pelo gate da Sprint **N56** do `work-platform-scripts` (Fase A: entrypoint + `manage.sh --objectstore`)
- **Origem**: piloto `s3pilot` 2026-07-08 — tenant criado via API (job `f1bee7f1` success) mas objectstore pós-install quebrou leitura; plano completo em `work-platform-scripts/docs/planning/PLAN-OBJECTSTORE-S3-PROVISION.md` (decisões D1–D9)
- **Descrição**: `POST /api/v1/tenants` (e Livewire `Customers\Create`) aceita `objectstore: { enabled: bool, bucket?: string }`; quando `enabled` + `image_mode` → argv inclui `--objectstore` (+ `--objectstore-bucket=` se informado). Credenciais/endpoint **não transitam** pela API — resolvem no host via `secrets/objectstore.env` (decisão D8). `objectstore.enabled` sem `image_mode` → 422.
- **Contrato**: OpenAPI `CreateTenantRequest.objectstore` (`enabled`, `bucket` opcional); persistência `customers.objectstore_enabled` (bool) + bucket em metadado não-secreto
- **Critério de aceite**: testes Pest argv (com/sem flag, bucket override, 422 sem image_mode); sanitização (`jobs.summary` sem credencial — padrão SEC-N30); OpenAPI atualizado; deploy LAB; canário `s3pilot2` **via API only** com gates G1–G6 do plano
- **Reportado em**: 2026-07-08 (`/pmo plan` cross-repo)

---

## ISSUE-056 — UX OCC Grupos: governança explícita, projeção local e pickers (DESIGN.md §9)

- **Tipo**: change_request / melhoria UX
- **Prioridade**: HIGH
- **Status**: **implemented — Sprints N45 + N46** (2026-07-08); aguarda VERIFY CI + deploy LAB
- **Registrado em**: 2026-07-08 (audit `/designer` + execução `/rock`)
- **Módulos afetados**: OccPanel, `tenant_groups`, `CreateUserRequest`, webhook projector, sync commands

### Decisão âncora

Grupo só nasce em fluxo **explícito** (aba Grupos / API). Na criação de usuário: **multi-select** de grupos existentes (vazio = herdar template).

### Entregas

| Sprint | Entregue |
|--------|----------|
| **N45** | Membership 501 oculto; confirmações destrutivas; pickers username; multi-select interim; polish quota/manutenção |
| **N46** | `tenant_groups` + projector + `tenant-groups:sync` + aba Grupos + validação API/UI |

### Fora de escopo

- `groups:add`/`groups:remove` operacional (upstream 501)
- CRUD admin templates/apps (ISSUE-051 item 7)

### Fontes

- `docs/design/DESIGN.md` §9
- Espelho arquitetural N40 (`tenant_users`)

---

## ISSUE-054 — Contraste ilegível em painéis Product (dark theme)

- **Tipo**: bug (UX)
- **Prioridade**: MEDIUM
- **Status**: **fixed** (2026-07-08) — Sprint F22-q + follow-ups q.2/q.3/q.4; deploy LAB `7492358`; merge `c50c9ea`; **validado pelo operador**
- **Registrado em**: 2026-07-08 (operador LAB — `/plans` com inputs e tabela quase invisíveis; dropdowns com opções invisíveis)
- **Módulos afetados**: `resources/css/app.css`, `resources/views/components/select-menu.blade.php` (novo), `resources/views/livewire/product/plans/index.blade.php`, `resources/views/livewire/customers/create.blade.php`, `resources/views/livewire/cluster-servers/index.blade.php`, `tests/Feature/Livewire/Product/PlansIndexTest.php`

### Descrição

Formulários em `/plans` usavam `bg-surface-container-lowest` sem `text-on-surface` (texto escuro sobre `#060e20`). Além disso, o **popup nativo do `<select>`** é renderizado pelo sistema operacional (GTK dark no Linux) e **ignora o CSS da página** — mesmo com `select option { background:#fff; color:#1e293b }` servido corretamente (verificado via curl no asset do LAB), as opções permaneciam ilegíveis.

### Correção (F22-q → q.4, PRs #149/#150/#151/#153)

1. **q** (`308fec4`): regra global `input`/`select`/`textarea` + classes M3 nos modais + `tbody text-on-surface`
2. **q.2** (`244740b`): `max-w-lg`→`max-w-[32rem]` (Tailwind v4 `--spacing-lg` sobrescrevia `max-w-lg` para 24px, colapsando o modal); `select option` explícito; container no apps picker
3. **q.3** (`e5b158b`): `color-scheme: light` no select (tentativa CSS para o popup)
4. **q.4** (`c50c9ea`, definitivo): componente **`x-select-menu`** (Blade + Alpine listbox, 100% controlado pelo tema; `$set` preserva hooks `.live`) substituindo selects nativos em `/plans` e `/customers/create`

### Lição aprendida

Popup de `<select>` nativo é OS-rendered — CSS da página não alcança. Em dark theme, usar dropdown customizado (`x-select-menu`). Selects nativos restantes (filtros Customers/Operators, OccPanel, Audit) têm o mesmo risco — migração em sprint futura.

---

## ISSUE-052 — `/docs/api/spec` 404 na imagem Docker (Scalar não carrega contrato)

- **Tipo**: bug (regressão de empacotamento pós-N37)
- **Prioridade**: HIGH
- **Status**: **fixed** (2026-07-08) — Sprint F21; deploy LAB `95d1152`
- **Registrado em**: 2026-07-07 (diagnóstico operador LAB — `/docs/api` renderiza Scalar mas spec falha; badge `Spec vunknown`)
- **Módulos afetados**: `Dockerfile`, `.dockerignore`, `app/Http/Controllers/DocsController.php`, `tests/Feature/Docs/ApiDocsTest.php`

### Descrição

Sprint N37 (ISSUE-047) entregou viewer Scalar em `/docs/api` lendo `base_path('docs/openapi-external.yaml')` via `GET /docs/api/spec`. Localmente e no CI (Pest) passa; na imagem Docker de produção/LAB o spec retorna **404**. **Causa primária**: `.dockerignore` exclui `docs` do contexto de build — o `COPY . .` do stage `build` nunca leva o spec para a imagem. O `rm -rf tests docs layout .cursor .github` (`Dockerfile:112`) é secundário/redundante para `docs/`. LAB usa `target: production` sem volume de código (`docker-compose.lab.yml`), então só o conteúdo da imagem conta.

Validação pós-deploy N37 (`8e58fed`) checou página `/docs/api` + manifest `docs-api.js` + `/up` 200, mas **não** validou `/docs/api/spec` dentro do container.

### Evidência

- LAB: `GET /docs/api/spec` → 404 (autenticado como admin)
- Scalar: `Document 'api-1' could not be loaded`
- `DocsController::resolveSpecVersion()` → `unknown` (arquivo ilegível no runtime)

### Correção proposta (F21.1)

1. `.dockerignore`: exceção `!docs/openapi-external.yaml` após a linha `docs`
2. `Dockerfile` (stage build): copiar o spec para `storage/app/openapi-external.yaml` antes do `rm -rf docs`
3. `DocsController` ler path via config com fallback dev (`docs/openapi-external.yaml`)
4. Teste Pest cobrindo path de produção + smoke pós-deploy documentado

---

## ISSUE-053 — Sidebar sem links para Planos e Fazendas

- **Tipo**: bug (gap de UX pós-N41/N18)
- **Prioridade**: MEDIUM
- **Status**: **fixed** (2026-07-08) — Sprint F21; deploy LAB `95d1152`
- **Registrado em**: 2026-07-07 (operador LAB não encontrou CRUD de planos no menu; URL `/plans` funciona)
- **Módulos afetados**: `resources/views/layouts/app.blade.php`, `tests/Feature/Livewire/Product/PlansIndexTest.php`, `tests/Feature/Livewire/Farms/FarmCapacityPanelTest.php`

### Descrição

N41.4 entregou Livewire `GET /plans` (gate `manage-operators`) e N18 entregou `GET /farms`, mas `layouts/app.blade.php` não inclui entradas no `$navItems`. N37.4 adicionou padrão de link na sidebar para Documentação API; o mesmo padrão não foi replicado para Product/Farms.

Operador precisa conhecer URL direta — sensação de que “nada foi deployado” apesar do LAB estar em `2313ea1` com N41–N43.

### Correção proposta (F21.2 + F21.3)

1. Adicionar **Planos** (`route: plans.index`, gate `manage-operators`, highlight `plans.*`)
2. Adicionar **Fazendas** (`route: farms.index`, gate `manage-operators`, highlight `farms.*`)
3. Testes Pest espelhando `ApiDocsTest` (admin vê / operador não vê)

### Fora de escopo (Sprint N futura)

Painéis admin de **Catálogo** e **Templates** (ISSUE-051 item 7) — hoje só API v1 + pickers em create customer/OCC.

---

## ISSUE-051 — Product Governance: planos, catálogo de apps e templates de usuário (API-first)

- **Tipo**: change_request / arquitetura (FEATURE)
- **Prioridade**: HIGH
- **Status**: **concluída (fase 1)** (2026-07-06) — N41 (PR #138), N42 (PR #139), N43 (PR #140); correções F16–F20; ARCH-7 (`plan_apps` em vez de `max_apps`); deploy LAB `2313ea1`. Backlog não-bloqueante: findings F17 MEDIUM/LOW em `docs/FINDINGS.md`.
- **Registrado em**: 2026-07-06 (triagem via `/rock` — usuário quer o **meio** no control plane para governar produto via API até existirem apps Nextcloud customizados)
- **Módulos afetados**: novo módulo `Product` (ou `Catalog`), `Customers` (provision), `tenant_users` (N40), Livewire admin, API v1, migrations

### Descrição

Hoje o provisionamento aceita flags técnicas (`suite_catalog`, `image_mode`, `apps[]`) sem UX de produto. Quotas são por OCC/usuário, não por **plano**. Não há cadastro reutilizável de **papéis de usuário** (Admin, Supervisor, Colaborador) com apps e permissões de negócio.

**Objetivo (fase 1):** construir na API + painel o cadastro e a aplicação de:

1. **Planos** — definem quota (e limites opcionais) herdados pelo tenant
2. **Catálogo de apps** — apps disponíveis na suíte; seleção na criação do tenant
3. **Templates de usuário** — perfis com apps permitidos + permissões lógicas (ex.: contratar usuários, habilitar app da loja, criar integração, auditoria)

**Premissa explícita:** **não** desenvolver apps Nextcloud (Gestão do Work, loja, auditoria) nesta fase — apenas modelar, expor via API e **enforçar** no control plane até os apps consumirem os metadados.

### Escopo proposto (para `/arquiteto dados` + `/pmo plan`)

1. CRUD **plans** (quota default, limite opcional `max_users`; apps designados via `plan_apps` — sem `max_apps` desde F18/ARCH-7)
2. CRUD **app_catalog** (app_id alinhado ao upstream `suite_catalog.json` / `occ app:enable`)
3. CRUD **user_templates** (slug, label, apps[], permissions json)
4. **Provision** referencia `plan_id` + `app_ids[]` resolvidos do catálogo
5. **users:create** aceita `user_template_id` → aplica groups/apps/quota conforme template
6. **Enforcement** na API (422/403) quando operação viola plano ou template — independente de app NC existir
7. Painel Livewire: áreas Planos, Catálogo, Templates + picker no create tenant/user

### Fora de escopo (fase 1)

- Apps Nextcloud customizados (Work, loja, integrações UI)
- Billing WHMCS / cobrança por plano (ISSUE-022 / N22)
- Marketplace self-service para cliente final

### Relacionado

- ISSUE-050 / N40 (`tenant_users`, projeção local)
- ISSUE-038 (API v1, scopes)
- `ProvisionPayload` (`apps`, `suite_catalog`)
- REQUIREMENTS F3/F6 (provision + lifecycle users)
- `docs/ARCHITECTURE.md` §10.1 (delta arquitetural)

### Próximo passo

`/arquiteto dados` → `/arquiteto contratos` → `/pmo plan`

---

## ISSUE-050 — Read model local de usuários de tenant + política sem admin para clientes

- **Tipo**: change_request / arquitetura (FEATURE)
- **Prioridade**: HIGH
- **Status**: **planned — Sprint N40** (2026-07-05); brief `docs/.briefs/N40.brief.md` PASS_WITH_NOTES, verifier PASS
- **Registrado em**: 2026-07-05 (triagem — usuário reportou lentidão na aba Usuários do OccPanel pós-N39 e propôs BD próprio centralizando controle)
- **Módulos afetados**: `database/migrations/` (nova tabela `tenant_users`), `app/Http/Livewire/Customers/OccPanel.php`, `app/Modules/Customers/` (service + sync command), webhook/job handlers de `users:*`, política de provisionamento (cross-repo)

### Descrição

A aba Usuários do OccPanel (N39.2) faz SSH síncrono (`occ-exec user:list --json`, timeout 30s) a cada abertura — operador percebe lentidão de 2–10s+. Proposta do usuário: BD próprio armazenando usuários de tenant, com o painel/API como fonte central.

**Premissa de governança (decisão do usuário 2026-07-05):** nenhum cliente terá conta admin no Nextcloud — só usuários comuns. A conta `admin` criada no provisionamento é da plataforma e governada por regra da API. Com isso, 100% dos creates/deletes de usuários passam pela API → a projeção local é autoritativa, não apenas cache.

### Escopo proposto (para `/pmo plan`)

1. Tabela `tenant_users` (`customer_slug` FK → `customers.slug` — PK de customers é slug, não id; username, email, quota, groups json, origin, timestamps) + model/casts
2. Escrita na projeção em cada `users:create`/`users:delete` via painel/API (inclusive `admin` do provisionamento, registrado no provision)
3. Refactor `OccPanel::loadUsers()` → SELECT local (aba instantânea); botão "Atualizar" dispara sync sob demanda
4. Sync periódico de reconciliação (`tenant-users:sync`, padrão `customers:sync`) — detector de drift (ops manual, ISSUE-013)
5. Enforcement da política: create de usuário nunca em grupo `admin`; checagem de drift no sync
6. (Cross-repo, coordenação) política upstream/provision alinhada — nenhum admin de cliente. A política upstream (provision sem admin de cliente) permanece pendente de coordenação cross-repo.

**Execução (2026-07-05)**: Sprint N40 onda A+B implementadas — ver ROADMAP N40.

### Relacionado

- N39.2 (origem da aba Usuários com SSH síncrono)
- ISSUE-013 (webhooks com dados nulos — motivo do sync de segurança)
- ISSUE-033 (governança de conta admin seed — análogo no painel)
- `customers:sync` (precedente de projeção + reconciliação)

---

## ISSUE-049 — UX operador: provisionamento + OCC (DESIGN.md §8)

- **Tipo**: change_request / melhoria UX
- **Prioridade**: HIGH
- **Status**: **fixed (2026-07-05)** — Sprint N39; PR #135 merge `8e58fed`
- **Registrado em**: 2026-07-05 (triagem via UX Audit DESIGN.md §8 + incidentes LAB `pacoteste`: trailing slash em FQDN, `user create` falhou sem feedback inline)
- **Módulos afetados**: `app/Http/Livewire/Customers/{Create,Show,OccPanel}.php`, `app/Http/Requests/ProvisionCustomerRequest.php`, `app/Http/Requests/V1/ProvisionTenantRequest.php`, `app/Modules/Customers/`, `app/Http/Livewire/ClusterServers/Index.php`, `resources/views/livewire/customers/*`, `app/Jobs/ProbeCustomerReadinessJob.php`

### Descrição

Sessão operacional real no LAB (provisionamento `pacoteste`, criação de usuários OCC) revelou fricções documentadas em `docs/design/DESIGN.md` §8: domínio com barra final quebra Traefik/readiness; página do customer não atualiza durante provisioning; `provisioning_finishing` é caixa-preta; Painel OCC não lista usuários existentes nem mostra erro real de `users:create` (só "enfileirado"); views `customers/*` usam CSS inline legado (hex cru) em vez dos tokens M3 de `cluster-servers/*`; remoção de cluster legado exige tinker/SSH.

### Escopo (Sprint N39)

1. **N39.1** — Normalizar FQDN (strip `/`, lowercase, regex) em Livewire Create + `ProvisionCustomerRequest` + API v1; preview no form; testes Pest.
2. **N39.2** — OccPanel aba Usuários: listar via `OccPassthroughService` `user:list --json`; refresh; bloquear username `admin`.
3. **N39.3** — Feedback async user create: poll job até terminal; summary/erro inline; senha ≥10 + hint política NC.
4. **N39.4** — `customers/show`: `wire:poll` durante provisioning/finishing; link `/queue/{job_id}`; tail log (`JobLogFetcher` throttled).
5. **N39.5** — Readiness visível em `provisioning_finishing` (tentativa/último erro via `ReadinessReport` estendido ou `AuditLog`).
6. **N39.6** *(stretch P)* — Retrofit visual `customers/*` para tokens M3 (match `cluster-servers`).
7. **N39.7** *(stretch M)* — Remover cluster soft-delete na UI com guarda de customers ativos.

### Fontes

- `docs/design/DESIGN.md` §8 (UX Audit 2026-07-05)
- `docs/REQUIREMENTS.md` F3 (provisionar) + F6 (OCC/users)
- Incidentes LAB: FQDN `pacoteste.../` (trailing slash); `users:create` rejeitado (admin duplicado / senha fraca) sem feedback no OCC

### Fora de escopo

- Try-it-out Scalar (ISSUE-047 / N37)
- Mudanças upstream `nextcloud-manage` ou política de senha no NC (só alinhar validação/hint no painel)
- Cutover domínio produção (ISSUE-043 fases posteriores)

### Resolução (2026-07-05)

- **correcao:** N39.1–N39.7 entregues — FQDN normalizado server-side (strip `/`, lowercase, 422 só protocolo/formato); OccPanel lista usuários via `user:list --json` (timeout 30s) e bloqueia `admin`; feedback async de `users:create` com poll até terminal; `customers/show` com `wire:poll`, link job e tail log throttled; readiness visível via AuditLog `customer_readiness_probe`; retrofit M3 em `customers/*`; remoção de cluster na UI com guarda de tenants ativos.
- **validacao:** PR #135 mergeada; CI verde; deploy LAB `.110` SHA `8e58fed`; `/up` 200.
- **resolved_by:** Sprint N39

---

## ISSUE-047 — API Console fase 1: docs viewer privado (Scalar) + scopes nas credenciais

- **Tipo**: enhancement
- **Prioridade**: MEDIUM
- **Status**: **fixed (2026-07-05)** — Sprint N37; PR #136 merge `b43422c`
- **Registrado em**: 2026-07-05 (triagem via plano `/rock` "Ambiente Admin de APIs + Swagger" → `/pmo plan`)
- **Módulos afetados**: `app/Http/Livewire/ApiKeys/Index.php`, `app/Modules/Core/Services/ApiKeyService.php`, `routes/web.php`, `resources/views/` (layout/sidebar + viewer), `docs/openapi-external.yaml` (consumo read-only)

### Descrição

O contrato externo v1 (`docs/openapi-external.yaml`) existe e é lintado no CI, mas não há nenhum viewer de documentação — operadores e futuros integradores dependem do arquivo cru. Além disso, o create de credenciais em `/api-keys` sempre grava `scopes: null` (chave irrestrita), apesar de o enforcement por scope já existir (`EnsureApiKeyScope` + `config/api-scopes.php`, Sprint N30/F15).

### Escopo fase 1 (Sprint N37)

1. Viewer de docs **privado** em `/docs/api` (gate `manage-operators`), renderizando **apenas** `openapi-external.yaml` via **Scalar** (npm/Vite, sem CDN); spec servido por rota autenticada, arquivo não vai para `public/`.
2. Seleção de scopes v1 (checkboxes) no create de credenciais; default sem seleção = irrestrita (`null`, comportamento atual); validação contra `config/api-scopes.php`.
3. Badges de scopes na listagem de `/api-keys`; link "Documentação API" na sidebar; banner de ambiente (LAB) + versão do spec no viewer.

### Premissas aprovadas (defaults-first, 2026-07-05)

- Doc privada (operadores autenticados) — doc pública é fase posterior, se surgir parceiro externo real.
- Só `openapi-external.yaml`; o `openapi.yaml` interno/legado fica fora do portal.
- Scalar em vez de Swagger UI clássico (UX, try-it-out nativo, bundle menor).

### Fase 2 (fora de escopo — nova issue quando priorizar)

- Try-it-out com proxy autenticado server-side + audit
- Expiração/rotação de credenciais; obrigar seleção explícita de scopes
- Doc pública (spec externo simplificado)

### Resolução (2026-07-05)

- **correcao:** Viewer privado `/docs/api` (Scalar via Vite entry `docs-api.js`) renderiza `openapi-external.yaml` com gate `manage-operators`; spec servido por rota autenticada; scopes v1 selecionáveis no create de `/api-keys` com validação `config/api-scopes.php` e enforcement `EnsureApiKeyScope`; badges na listagem; link "Documentação API" na sidebar com `@can('manage-operators')`.
- **validacao:** PR #136 mergeada; CI verde; deploy LAB `.110` SHA `8e58fed` (inclui N39); manifest Vite `docs-api.js` presente; `/up` 200.
- **resolved_by:** Sprint N37

---

## ISSUE-046 — `.108` (LAB upstream): create legado falha via `suite-deploy` misdirecionado + webhook de falha não entregue

- **Tipo**: bug (cross-repo — `work-platform-scripts` / host `.108`)
- **Prioridade**: CRITICAL / BLOCKER (gate N25.4 `create_canary`)
- **Sprint**: descoberto em N25.4 (retomada da campanha v2-platform)
- **Status**: open
- **Reportado em**: 2026-07-04

### Contexto

N25.3 registrou `.108` (`nextcloud-saas-manager`, upstream LAB legado — tenants `360`, `360cat`, `lab`) como `cluster_server` `lab-upstream` (`d7538710-676d-4673-a191-8decc0905596`) no control plane `.110`. Chave SSH dedicada `api-lab-108-2026` gerada e autorizada; `nextcloud-manage list --json` funcional; R6 (`cluster:health-check`) → **active**.

N25.4 disparou canário `canario-n25` via `POST /api/v1/tenants` (sem `image_mode`, path legado). Job aceito (202), dispatch SSH OK (`transport.dispatch` logado), mas o `nextcloud-manage create` no host **falhou**.

### Reprodução

```
$ ssh -i api-lab-108-2026 ncsaas-api@128.201.61.108 "nextcloud-manage canario-n25 canario-n25.lab.mework360.com.br create"
[suite-deploy] deploy_shell client=canario-n25 fqdn=canario-n25.lab.mework360.com.br snapshot=nc-suite-snapshot-2026-06-29.yaml
[suite-deploy] target host=mecloud360@128.201.61.112 url=https://cloud.labwork.mework360.com.br
[suite-deploy] error: apply-lab.sh not found at //work-nc-theme-fork/deploy/apply-lab.sh
exit 1
```

**Achado 1 (causa raiz provável):** o `nextcloud-manage` instalado em `.108` foi atualizado para um fluxo `suite-deploy` que **hardcoded** o alvo `mecloud360@128.201.61.112` (host **labwork**, ambiente diferente) e um path de script (`work-nc-theme-fork/deploy/apply-lab.sh`) que não existe em `.108`. Ou seja: criar um tenant NOVO em `.108` tenta indevidamente aplicar tema/deploy contra o host `labwork`, não contra si mesmo.

**Achado 2 (secundário, agrava o diagnóstico):** o job `state: failed` no Redis (`nc:jobs:4ba0824d-…`) tem `callback_failed: true` (4 tentativas) — a notificação de falha nunca chegou ao control plane `.110`. `GET /api/v1/tenants/canario-n25` ficou preso em `status: provisioning` mesmo minutos após a falha real. Conectividade de rede `.108→api.lab.mework360.com.br` confirmada OK (405 em GET no endpoint webhook); secret HMAC confirmado idêntico dos dois lados. Causa exata do callback não investigada a fundo (não há log da tentativa no `laravel.log` do app — possível rejeição em camada anterior ao controller, ex. IP allowlist behind Traefik/proxy real IP, ou timeout do worker).

### Evidência

- Job Redis: `nc:jobs:4ba0824d-343c-44fd-bed7-aa1bd0b7de88` — `state=failed`, `exit_code=1`, `callback_failed=true`, `callback_attempts=4`
- Cluster `lab-upstream` R6 PASS (`cluster:health-check` → active) — dispatch/transporte SSH funcionam corretamente; bug é no script remoto
- Customer `canario-n25` soft-deletado (`status=provisioning_failed`) e API key de teste `canary-n25` revogada — cleanup feito

### Impacto

Bloqueia **N25.4** (gate `create_canary` da umbrella BOM `platform-1.0.0.yaml`). Os gates `memail_sso_smoke` (ISSUE-031) e `settings_matrix` (16/16) dependem de um create bem-sucedido e portanto também ficam bloqueados por tabela.

### Diagnóstico completo (2026-07-05) — 3 camadas

Investigação a fundo no `.108` identificou **três problemas empilhados**:

**Camada 1 — create falha (PRIMÁRIA, RESOLVIDA):** `/opt/nextcloud-customers/manage.sh` resolve o platform root para `/opt/work-platform-scripts` (onde está `releases/suite_catalog.json`). O `create` sem `--legacy-vendor` entra em "suite-catalog mode" (default) → chama `deploy_shell`. Como `/opt/work-platform-scripts/deploy/labwork-deploy.conf` define `LAB_DEPLOY_HOST=mecloud360@128.201.61.112` (host **labwork**, ambiente diferente), o `deploy_shell` entra no ramo "apply-lab remoto" e busca `${work_root}/work-nc-theme-fork/deploy/apply-lab.sh` — que resolve para `//work-nc-theme-fork/...` (work_root vazio) e **não existe** no `.108`. → exit 1.
- **Fix aplicado (config, reversível):** criado `/opt/work-platform-scripts/deploy/lab-deploy.conf` no `.108` com `LAB_DEPLOY_HOST=` (vazio). O loader `suite_deploy_load_lab_conf` faz source dele DEPOIS do `labwork-deploy.conf`, então o valor vazio vence → `deploy_shell` cai no caminho local `custom_apps_sync_tenant`. **Validado:** create via API `canario-n25c`/`canario-n25d` → job `success`, `exit_code=0`.

**Camada 2 — webhook 401 (SECUNDÁRIA, RESOLVIDA — era gap do setup N25.3):** o `WebhookSecretValidator::valid()` **não lê** `cluster.webhook_secret_encrypted`; valida contra a tabela `webhook_secret_history`. O cadastro N25.3 (feito por atribuição direta no model, fora do fluxo `ClusterCreate` Livewire) criou o `ClusterServer` sem a linha correspondente em `webhook_secret_history` → validator sem secret → **401 invalid_signature** (não era mismatch: os secrets batiam). Mesma assimetria do finding sobre `Create.php` sem `DB::transaction` (FINDINGS).
- **Fix aplicado:** criada a linha via `WebhookSecretHistory::createWithSecret(...)` (version 1, valid_from now, valid_until null) para `cluster_server_id=d7538710-...`. **Validado:** callback do `canario-n25d` passou de 401 → aceito; customer avançou `provisioning` → `provisioning_finishing`.

**Camada 3 — readiness gate nunca passa (ESCOPO, não resolvido por config):** o `TenantReadinessGateChecker::passesAll` (via `ProbeCustomerReadinessJob`) exige, para tenant não-image-mode: apps `mework360_memail` **e** `me360_theme` habilitados + `externalLocation` + `forceSSO=yes` + HTTP 200 em `/apps/mework360_memail/`. Mas o caminho local (`custom_apps_sync_tenant` com `LAB_DEPLOY_HOST` vazio) **NÃO instala a suíte** — o create do `canario-n25d` logou "sem app/ — sync ignorado" e `app:list` confirmou `total enabled: 0`. Ou seja: o create "sobe" um Nextcloud **cru**, e o gate de readiness (16/16 settings + SSO) **nunca passa** → tenant expiraria em `failed` (deadline 1200s).
- **Implicação de escopo:** a suíte me360 completa é justamente o que o `apply-lab.sh` (bypassado) instalaria. O `.108` **não está provisionado para hospedar a suíte localmente** — a máquina de suite-deploy aponta para o host de proving-ground labwork (`.112`). Portanto o gate completo do N25.4 (canário + 16/16 + SSO memail) **não é satisfazível no `.108` como está hoje**.

### Estado após diagnóstico

- Camadas 1 e 2 corrigidas e validadas end-to-end (create via API → job success → webhook 204 → `provisioning_finishing`).
- Camada 3 é decisão de escopo/arquitetura (ver abaixo). Tenants de teste `canario-n25c`/`canario-n25d` removidos (host + control plane soft-delete); API key `canary-n25b` revogada; override `lab-deploy.conf` mantido no `.108` (documentado).

### Próximos passos sugeridos

1. **Fix estrutural (`/pmo fix` em `work-platform-scripts`):** decidir por que os defaults do labwork proving-ground (`labwork-deploy.conf`) são embarcados no checkout do `.108` — env/config drift entre hosts, mesma classe do ISSUE-045. Idealmente o `create` num host self-contained deveria fazer deploy local da suíte (não remoto).
2. **Decisão de escopo N25.4:** o canário de suíte completa (16/16 + SSO) deve rodar contra o host de suíte (`.112`/labwork) e não contra o `.108` legado? Reavaliar o gate no `PLATFORM-V2-PLAN §18` antes de mais mudanças de infra.
3. Sub-item resolvido: o problema de webhook (Camada 2) era gap do cadastro N25.3, não bug upstream — já corrigido.

---

## ISSUE-045 — Dispatch async perde flags `--image-mode` / `--suite-catalog` (upstream `dispatch.sh` D3.9b)

- **Tipo**: bug (integração cross-repo — `work-platform-scripts`)
- **Prioridade**: CRITICAL / BLOCKER (gate N36.4; violação silenciosa NC-ARCH-017)
- **Sprint**: descoberto em N36.4; fix upstream em `work-platform-scripts` (`ba53ecc`, main)
- **Status**: **fixed (2026-07-04)**
- **Reportado em**: 2026-07-03
- **Módulos afetados**: upstream `dispatch.sh` (~407–417, bloco D3.9b); impacto em `Jobs`, `Customers` (provisionamento async via API)

### Sintoma

`POST /api/v1/tenants` com `image_mode=true` e `suite_catalog=true` retorna 202 e o host recebe argv completo via SSH, mas o worker executa `create` no **modelo legado** (pull `nextcloud:33-fpm`/`nginx:alpine`) — proibido para novos tenants image-mode.

### Evidência

| Fonte | Observação |
|-------|------------|
| sudo audit host `.120` (16:31:23Z) | argv recebido do LAB: `… create --idempotency-key=… --callback=… --suite-catalog --image-mode --payload-stdin --async --json` |
| Redis `args_json` job `8f15f56f-a119-4d19-9a31-546090f6fc76` | `["nextcloud-manage","canario-n36b","<domain>","create"]` — flags ausentes |
| Job `682f675e-9a1d-4b24-9f88-5db71aa4e7a2` (tentativa 1) | exit 1 — `suite_catalog: no nc-suite-snapshot-*.yaml` (env worker; corrigido antes da tentativa 2) |
| Job `8f15f56f-…` (tentativa 2) | exit 124 (`WORKER_JOB_TIMEOUT_SEC=1800`) durante docker pull de imagens legado |
| Canário manual sync `teste2` | success — sem enqueue async; imagem pinada já cacheada |

### Root cause

O bloco D3.9b de `dispatch.sh` (`work-platform-scripts`) re-injeta apenas `--staging-id`/`--apps`/`--full-apps` ao montar `job_argv` para Redis. Flags booleanas `--image-mode` e `--suite-catalog` **não** são propagadas. A API (`work-platform-api`) está correta até o hop SSH (N36.1 validada).

### Impacto

Todo `create` image-mode disparado via API (async) executa provisionamento legado sem erro explícito na borda — risco operacional em produção.

### Fix sugerido

Re-injetar flags booleanas (`--image-mode`, `--suite-catalog`) no `job_argv` em `dispatch.sh` D3.9b, espelhando o argv auditado no shim. Coordenação cross-repo (tipo ISSUE-022).

### Resolução (2026-07-04)

- **Fix upstream:** commit `ba53ecc` em `work-platform-scripts` (main) — bloco D3.9b passa a propagar `--image-mode` e `--suite-catalog` ao `args_json` Redis.
- **Deploy host `.120`:** artefato atualizado no image-pilot; canários falhos anteriores (`canario-n36`, `canario-n36b`, `canario-n36c`, `canario-n36d`) em soft-delete.
- **Gate N36.4 — canário `canario-n36e`:** job `9904497b-ad3c-4390-ba61-c5f433cd00c1` (dispatch 21:19 UTC) → **success** ~5m44s; customer `active` automático ~4s depois; `/login` 200; imagem mw4; `/status.php` 404 (esperado em image-mode, N36.5).
- **Validação pós-merge:** deploy `main` `7a79086` no LAB (`.110`) com readiness oficial (sem hotfix).

---

## ISSUE-044 — CI vermelho no `main` pós-F3 (7 testes)

- **Tipo**: bug (regressão de testes / fixtures desalinhados)
- **Prioridade**: HIGH
- **Sprint**: N36 (PR #128)
- **Status**: **fixed (2026-07-03, sprint/N36 PR #128)**
- **Reportado em**: 2026-07-03 (commit base `6b29717`; run main 28349563271)

### Causa

7 falhas pré-existentes no `main` desde F3, mascarando sinal do PR N36:

1. `OnboardingSagaTest` ×4 — `app_id: calendar` inexistente no fixture do suite catalog (ids válidos: `dashboard`, `mail`, `spreed`, `deck`) → 422.
2. `AgentTransportCutoverTest` + `ProvisionTest` ×3 — mocks `runAsync` sem `--suite-catalog` / stdin `{"shell":true}` desalinhados do contrato F3.

### Correção (commit `80f3063`)

- `calendar` → `mail` nos testes de onboarding.
- Mocks alinhados ao contrato F3 (`--suite-catalog`, stdin esperado).
- Sem mudança de produção. CI verde após o fix.

---

## ISSUE-043 — Integrar novo sistema piloto (`cloud.image-pilot`) direcionado a produção (`<tenant>.mework360.com.br`)

- **Tipo**: FEATURE (change_request — integração de novo sistema upstream/imagem + mudança de padrão de domínio)
- **Prioridade**: HIGH (redefine o alvo de produção do provisionamento)
- **Sprint**: N36 (fase inicial concluída 2026-07-04); fases seguintes a definir
- **Status**: aberto — **fase inicial (N36) concluída** com gate E2E `canario-n36e` (ISSUE-045 resolvida upstream)
- **Reportado em**: 2026-07-03

### Descrição

Existe um novo sistema em piloto acessível em `https://cloud.image-pilot.mework360.com.br` (não referenciado em nenhum repositório até 2026-07-03; presumivelmente derivado do ensaio de upgrade Nextcloud na VM do Proxmox IDC-EVEO — ver `docs/OPERATIONS.md` 2026-06-24 e `docs/CLOUD-OPS-PVE-VALIDATION.json`). A demanda é atualizar o control plane (`work-platform-api`) para operar com esse novo sistema **já direcionado para produção**, onde os tenants passariam a usar o padrão de domínio `<tenant>.mework360.com.br` (ex.: `tenant.mework360.com.br`), em vez do modelo atual de host único de produção SaaS-02 (`cloud.mework360.com.br`, cluster `producao` `0e50e032-…`).

### Esclarecimentos (usuário + evidência cross-repo, 2026-07-03)

1. **Natureza do piloto — RESOLVIDO**: `cloud.image-pilot.mework360.com.br` (`128.201.61.120`) é o piloto greenfield **NC Suite image-mode** (ENH-010 p3 / Sprints N45–N48 do `work-platform-scripts`): tenant `imgpilot`, NC `33.0.5-mw5` (digest `sha256:535c86b4…`), `create --image-mode --suite-catalog`, plano `provision/plans/image-pilot-baseline.yaml`, runbook `docs/runbooks/NC-IMAGE-PILOT.md`. Status N48.11: **GO condicional** (signoff humano pendente itens 1/4/8). Esse ambiente **será a produção**.
2. **Cenários confirmados pelo usuário**: LAB = `labwork.mework360.com.br` (`.112`, homolog canônica NC-ARCH-012, tenants `*.labwork.mework360.com.br`); PRODUÇÃO (em testes) = host image-pilot. Em produção os tenants usarão `<tenant>.mework360.com.br` (ex.: `tenant.mework360.com.br`).
3. **Escopo pedido**: atualizar a API **inicialmente** para operar contra o ambiente que será produção (image-pilot) — cadastro de `cluster_server`, contrato de provisionamento (`fqdn`/domínio), DNS e validação canário — antes do cutover de domínio final.
4. **Questão residual**: estratégia para os 11 tenants do SaaS-02 legado (`cloud.mework360.com.br`) — migração/convivência fica fora deste escopo inicial; relação com N25 (N25.3/N25.4 LAB) a decidir no planejamento.

### Artefatos impactados (Impact Scan 2026-07-03)

- **ClusterServers**: cadastro de novo `cluster_server` de produção (painel `/clusters` — CRUD F9; chave SSH dedicada + webhook secret + sync)
- **Customers/provisionamento**: `app/Modules/Customers/Actions/ProvisionCustomerAction.php`, `app/Modules/Customers/Dto/ProvisionPayload.php` (campo `domain`), `app/Modules/Customers/Services/TenantReplicaSynchronizer.php`, `CustomerSyncService`
- **Onboarding**: `app/Modules/Onboarding/Saga/OnboardingSaga.php` (propagação de `domain`)
- **Dns**: `app/Modules/Dns/Actions/{ProvisionDnsZoneAction,PublishDkimRecordAction,VerifyDomainAction}.php` — padrão de zona/registros para `<tenant>.mework360.com.br`
- **Mail**: `app/Modules/Mail/Actions/ProvisionTenantMailAction.php`, `MailApiClient` (domínio derivado)
- **Billing/WHMCS**: `app/Modules/Billing/Services/WhmcsProvisionService.php`, `ProvisionDedicatedTenantAction`
- **Integration**: `app/Modules/Integration/Support/TenantReadinessGateChecker.php` (URL de readiness `https://<domain>/…`)
- **Testes**: `tests/Feature/Api/V1/SuiteCatalogProvisionV1Test.php`, suites de provisioning/lifecycle que assumem domínio atual
- **Cross-repo**: `work-platform-scripts` / upstream `nextcloud-saas-manager` (template de tenant, vhosts/TLS, webhook) — coordenação tipo ISSUE-022

### Documentação afetada

- `.cursor/skills/me360-deployer/references/{ecosystem-map,architecture-and-routing,environment-and-parity,production-deploy}.md` (mapa aponta SaaS-02 como único prod)
- `docs/PLATFORM-V2-PLAN.md`, `docs/OPERATIONS.md`, `docs/SUITE-ENV.md`
- `docs/openapi.yaml` / `docs/openapi-external.yaml` (se contrato de `domain`/provisionamento mudar)

### Proposta de escopo

FEATURE grande (multi-sprint provável): (a) descoberta/contrato do novo sistema piloto; (b) cadastro e validação de cluster de produção novo (SSH + webhook + canário); (c) padrão de domínio + DNS/TLS; (d) estratégia de convivência/migração com SaaS-02. Detalhamento e split → `/pmo plan — ISSUE-043`.

---

## ISSUE-041 — Dispatch lifecycle async trava em `--payload-stdin` (PlatformPort SSH adapter)

- **Tipo**: bug (integração SSH async)
- **Prioridade**: HIGH (bloqueia toda operação lifecycle async com payload: `users:create`, etc.)
- **Status**: open — descoberto no smoke F10.3 (N35.6) no LAB 2026-06-18; **rotear `/qa debug` → `/pmo fix`** (não corrigido inline)
- **Módulos afetados**: `app/Modules/Integration/` (`SshPlatformAdapter`), `app/Modules/Core/Ssh/` (`SshClient::runAsync`), `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
- **Relacionado**: ISSUE-023 / F10.3 (bloqueia o gate); migração PlatformPort N31–N33

### Sintoma

`users:create` disparado via deployer (`LifecycleAsyncAction` → `PlatformPort::dispatchManageAsync`) **trava indefinidamente** (>9 min observado) no dispatch SSH. O processo PHP fica preso; nenhum `job_id` é retornado; `jobs`/`idempotency_keys` permanecem sem registro persistido após kill.

### Root cause (evidência LAB)

O adapter SSH do `PlatformPort` envia o comando `nextcloud-manage … --payload-stdin --async` mas **não escreve o JSON do payload no stdin do canal SSH e/ou não sinaliza EOF**. O `manage.sh` upstream bloqueia no read do stdin (espera o payload que nunca chega/fecha).

| Evidência | Resultado |
|-----------|-----------|
| Shim audit (`ncsaas-api-ssh`) | `invoke` + `accept` do comando registrados (auth/allowlist OK) |
| Worker journal upstream | **sem entrada** — job nunca enfileirado |
| Dispatch via deployer (PlatformPort) | trava >9 min, sem `job_id` |
| **CLI equivalente** (`printf '{…}' \| ssh … --payload-stdin --async`) | **retorna em 0 s** (stdin fechado pelo pipe) |

A diferença CLI (stdin fechado) × deployer (trava) isola o defeito no **envio/fechamento do stdin pelo adapter**, não no upstream nem na rede.

### Suspeita

Regressão da migração para `PlatformPort` (N31–N33): o caminho async com `--payload-stdin` não é exercitado por testes reais (apenas mocks), então a falha de stdin passou pelo CI. Em produção (deployer descontinuado) o caminho legado dispatchava jobs (ISSUE-013: "1/5 jobs 7d"), o que reforça regressão pós-migração.

### Impacto no gate F10.3

Bloqueia N35.6 (smoke `users:create`) e, por consequência, a validação de `jobs.summary` / UI `/queue/{id}`. O **deploy** (N35.5) está validado; o gate funcional de F10.3 fica **parcial** até este bug ser corrigido.

---

### 🔁 RE-DIAGNÓSTICO — `/qa debug` runtime (2026-06-18, sessão `02e90f`)

A root cause original (acima) está **INCORRETA**. Debug com instrumentação runtime (NDJSON, `SshClient::execWithStdin`) no LAB **rejeitou todas as hipóteses do lado deployer** e isolou a falha no upstream.

**Lado deployer — PROVADO CORRETO (log runtime):**

| Etapa (`execWithStdin`) | Evidência | Veredito |
|---|---|---|
| `exec($command, false)` | retorna `true` em **246ms** | não bloqueia |
| `write($payload)` | instantâneo (payloadLen=31) | entrega stdin |
| `sendEOF()` | instantâneo | sinaliza EOF |
| read loop | fecha canal na 1ª iteração (`chunk===true`, `isTimeout=false`) em **677ms** | sem hang |

→ O caminho `exec(cmd,false)→write→sendEOF→read` do `SshClient` funciona. O "hang de 9min" **não foi reproduzível** (ambiente mudou: bl07 quebrou o Redis do `manage` *após* o smoke original).

**Causa raiz REAL (upstream `nextcloud-saas-manager`, evidência runtime):**

1. Dispatch retorna **exit 3** (stdout vazio) → deployer mapeia p/ `idempotencyConflict` (`SshClient::buildRemoteException` case 3). Correto dado o exit code.
2. `lib/job_queue.sh::idem_check` faz `EVAL … 2>/dev/null`; em falha de Redis `result=""` → não é `"new"` → `existing_hash="" ≠ args_hash` → retorna **`conflict`** (exit 3). **Falha de Redis mascarada como conflito de idempotência** (com `idempotency-key` novo!).
3. `_redis_resolve_conn` (invocado via shim SSH, sem env systemd) só lê `^REDIS_PASSWORD=` texto-plano de `/opt/shared-services/.env`. A migração **bl07 (2026-06-18 19:48)** trocou para `REDIS_PASSWORD_FILE=/opt/shared-services/secrets/redis_password` e removeu `REDIS_PASSWORD=` → `manage` resolve senha **vazia** → `redis-cli` retorna `NOAUTH/WRONGPASS`.
4. Assimetria comprovada: **worker** (systemd, `WORKER_REDIS_PASS` ✅) funciona; **manage-via-SSH** (sem env, lê shared `.env` plaintext) quebra. Senha real (`requirepass 226ead…`) bate com o secrets-file e com `WORKER_REDIS_PASS`.

**Findings de upstream/infra (precisam de triagem — repo `nextcloud-saas-manager` + infra LAB, fora deste deployer):**

- **UP-A (infra/config)**: `_redis_resolve_conn` deve suportar `REDIS_PASSWORD_FILE` e/ou ler `WORKER_REDIS_PASS` do `.env` do worker — quebrou após a migração secrets-file (bl07).
- **UP-B (bug latente)**: `idem_check` deve distinguir falha de Redis de conflito real — hoje mascara `NOAUTH`/erro como `conflict` (exit 3), escondendo falha de infra como conflito de negócio.

**Pendente**: o hang original de 9min só pode ser debugado após UP-A destravar o Redis do `manage` (para alcançar o caminho `enqueue` real).

**Estado do ambiente pós-debug**: instrumentação revertida (container LAB e arquivo local restaurados ao original; artefatos `/tmp` removidos). Nenhuma alteração de código no deployer.

### ✅ FIX APLICADO — UP-A (2026-06-19, LAB)

O `manage` roda como **root** via shim (`exec sudo -n nextcloud-manage`), então tem permissão para ler o secrets-file root-only. Correção aplicada (aditiva) em `lib/job_queue.sh::_redis_resolve_conn` nas 2 cópias deployadas (`/opt/nextcloud-customers/lib/` + `/opt/work-platform-scripts/scripts/lib/`; backup `*.bak-issue041-*`):

```bash
# quando REDIS_PASSWORD plaintext ausente (pós-bl07):
#  1) ler REDIS_PASSWORD_FILE → conteúdo do secrets-file
#  2) fallback: WORKER_REDIS_PASS do .env do worker
```

**Verificação runtime (antes/depois, sessão `02e90f`):**

| Run | idem_check EVAL | Dispatch | Worker |
|-----|-----------------|----------|--------|
| run1 (antes) | vazio (NOAUTH) → `conflict` | exit 3 / `PortIdempotencyConflictException` (851ms) | — |
| post-fix | `empty:false head:"new"` | **`JOBID` real** (1142ms) | **dequeue + processa** o job |
| clean (sem instrum.) | — | `JOBID=02170d18…` (1172ms) | — |

**Não aplicado (precisam de backport/triagem no repo-fonte upstream — LAB sem VCS):**

- **UP-B (bug latente)**: `idem_check` ainda mascara falha de Redis como `conflict` (exit 3). Hardening: distinguir erro de transporte (`EVAL` vazio) e emitir `redis_unavailable` (exit retryable). Não aplicado ad-hoc (muda contrato + exige editar `dispatch.sh`).
- **Backport UP-A**: portar a correção de `_redis_resolve_conn` para o source do `nextcloud-saas-manager` (a LAB só tem cópias deployadas, sobrescritas no próximo deploy).

### ⚠️ Callback worker→deployer 404 — RE-DIAGNOSTICADO (não é bug de rota)

Pós-fix ISSUE-041, o worker processa o job mas o journal registrou **HTTP 404** no callback. Investigação PMO (2026-06-19):

| Evidência | Resultado |
|-----------|-----------|
| `curl -X POST https://api.lab.../api/jobs/hook` (sem HMAC) | **400** — rota existe |
| `php artisan route:list --path=jobs/hook` | `POST api/jobs/hook` registrada |
| `WebhookController::receive` | retorna **404** quando `Job::find($job_id)` falha (`ModelNotFoundException`) |
| Smoke de debug (`PlatformPort::dispatchManageAsync` direto) | **não persiste** row em `jobs` → callback 404 **esperado** |

**Conclusão:** não abrir Sprint F separada. N35.6 deve usar **`LifecycleAsyncAction::execute`** (ou painel/API) para persistir o job antes do callback. Se 404 persistir com job persistido → investigar HMAC/IP allowlist (401/403, não 404).

---

## ISSUE-040 — LAB mirror de prod: VM dedicada para o control plane (`api.lab`)

- **Tipo**: change_request (infra / fidelidade de ambiente)
- **Prioridade**: MEDIUM
- **Status**: **in_progress** — VM-A provisionada via `/cloud-ops` (2026-06-19)
- **Registrado em**: 2026-06-18 (`/devops` — decisão do usuário durante N35)
- **Módulos afetados**: topologia LAB, `docs/INFRASTRUCTURE.md`, `docs/LAB-PROVISION-PLAN.md`, `cluster_servers`
- **Solicitante**: usuário (objetivo: LAB como espelho fiel de produção)

### Contexto

N35.5 deployou o control plane (`mework360-deployer`) **co-locado** na mesma VM LAB (sid=58, `128.201.61.108`) que já hospeda o upstream (`nextcloud-saas-manager`) + shared-services. Funciona para o gate F10.3 (valida `JobLogFetcher`/`summary`/UI), mas **não exercita o caminho de rede real**: o SSH control plane→upstream vira loopback no setup co-locado.

Em **produção** ([`INFRASTRUCTURE.md`](INFRASTRUCTURE.md)) o deployer é **VM dedicada** que faz SSH/webhook **pela rede** até o upstream em outro host.

### Objetivo

Provisionar VM-A dedicada para o control plane, deixando a VM-B atual (sid=58) como upstream:

```
VM-A (nova)  ──SSH/webhook (rede real)──▶  VM-B (sid=58, atual)
api.lab                                    cloud.lab / *.lab
mework360-deployer                         nextcloud-saas-manager
```

Assim valida SSH cross-host, firewall, egress IP whitelist e HMAC pela rede — fidelidade real com prod.

### Pré-requisitos / roteamento

- [x] **Decisão de arquitetura** (`/arquiteto`): topologia 2-VM confirmada (2026-06-18)
- [x] **Provisionamento** (`/cloud-ops` PROVISIONAR): sid=**65** @ **`128.201.61.110`** (order 64, SSH CF18 staged antes AcceptOrder — 2026-06-19)
- [ ] **sid=64 orphan** @ `128.201.61.109` — provisionado sem CF18; SSH inacessível; cancelar via WHMCS quando aprovado
- [x] DNS `api.lab` → **`128.201.61.110`** (PowerDNS PATCH 204)
- [ ] Bootstrap VM-A: aguarda **TCP/22** + `deploy-server.sh` / migrar stack de VM-B

### Não-bloqueante

F10.3 / ISSUE-023 **não depende** desta issue — fecha no setup co-locado atual. Esta é melhoria de fidelidade de ambiente para sprint de infra futura.

---

## ISSUE-037 — `ApiKey.scopes` nunca aplicado + ausência de autorização por tenant

- **Tipo**: security (authorization gap)
- **Prioridade**: HIGH (latente hoje; CRITICAL ao emitir chaves a terceiros)
- **Status**: corrigido local — Sprint **F15** (2026-06-16); `EnsureApiKeyScope` + `EnsureTenantBinding` + `AuditLog.api_key_id`
- **Registrado em**: 2026-06-16
- **Solicitante**: triagem do painel de arquitetura adversarial (`/arquiteto`) sobre o objetivo "dois contratos / ACL"
- **Módulos afetados**: Core (Auth / guard `api-key`), Customers (rotas `/customers/{customer}/*`)
- **Finding**: SEC-V1-001 (docs/FINDINGS.md)

### Descrição

Verificado no código durante a triagem:

1. **`scopes` não é aplicado** — `Auth::viaRequest('api-key', ...)` em `app/Providers/AppServiceProvider.php` (L50-70) resolve a chave pelo `token_hash` e devolve o `Operator` inteiro; **nunca lê `ApiKey.scopes`**. A coluna existe (`app/Models/ApiKey.php`, cast `array`) e é gravada em `ApiKeyService::generate()`, mas a UI (`ApiKeys/Index.php` L72-76) sempre passa `scopes: null` e nenhum middleware/gate a consulta. É *dead code* de segurança (falsa sensação de least-privilege).
2. **Sem autorização por tenant** — as rotas em `routes/api.php` usam `auth:web,api-key` + `active.operator`; o `{customer}` vem do slug na URL. Qualquer chave válida (→ operador ativo) opera sobre **qualquer** customer. Apenas `DELETE /customers` adiciona `can:provision-customers` (gate por role, não por tenant).

### Impacto

- **Hoje (contido)**: chaves são emitidas só a operadores internos via painel admin → acesso amplo é o design atual do control plane.
- **Risco**: ao abrir `/v1` a WHMCS / onboarding-api / parceiros (plano Platform V2) sem binding tenant↔principal, vira **IDOR sistêmico** e exposição cross-tenant (LGPD). Por isso o painel promoveu authz escopado a **gate duro** antes da v1 (ver `.arch-panel/panel/final.md`).

### Ação proposta (a detalhar no Fix Brief)

1. Aplicar `ApiKey.scopes` no guard (capabilities) — fechar o gap deixado por SEC-F004.
2. Binding explícito tenant↔principal (allowlist de slugs ou claim `tenant_id`) verificado em todo `/customers/{customer}/*`.
3. Teste negativo: chave do parceiro A → `403` no tenant B.
4. `AuditLog` por capability com `api_key_id`/escopo.

### Relacionados

- SEC-F004 (guard Bearer implementado, escopo nunca aplicado), SEC-V1-001 (FINDINGS), painel `.arch-panel/panel/` (v0 + 5 críticas + final).

---

## ISSUE-038 — API externa `/api/v1` com dois contratos (OpenAPI estável + protocolo NC interno via ACL)

- **Tipo**: change_request (iniciativa arquitetural)
- **Prioridade**: HIGH
- **Status**: **Fases 0–4 concluídas** — Sprint 0 (**N30**, PR #115); Fase 1 (**N31**, PR #116); Fase 2 (**N32**, PR #117); Fase 3 (**N33**, PR #117); Fase 4 (**N34**, branch `sprint/N34`, validation R2 APROVADA); pré-req ISSUE-037/F15 ✓
- **Registrado em**: 2026-06-16
- **Solicitante**: discussão de produto + painel de arquitetura adversarial (`/arquiteto`, protocolo `tests/arch-panel.md`)
- **Módulos afetados**: Core (HTTP/Auth), Customers, Occ, Agents, docs (openapi)
- **ADR (decisão)**: `.arch-panel/panel/final.md` (insumos: `v0.md` + 5 críticas — arquiteto, dev-senior, segurança, SRE, advogado, todas severidade ALTA)

### Descrição

Formalizar o `work-platform-api` como middleman com **dois contratos independentes**: para fora, uma API OpenAPI estável e versionada (`/api/v1`) em capabilities de negócio (Tenant, Branding, Apps, Users, Onboarding, Job); para dentro, um contrato técnico com a plataforma Nextcloud ("protocolo NC": SSH `nextcloud-manage`/`occ-exec`/webhook HMAC + Farm Agent) isolado atrás de um **PlatformPort** (ACL) com adapters `Ssh`/`Agent` intercambiáveis. Objetivo completo em `tests/arch-objetivo.md`.

Decisão do painel (resumo — detalhe no ADR):
1. **Sprint 0 reversível primeiro** (sanitizar erro na borda atual sem `subcmd`/`exit_code`; congelar `/occ/*` fora do spec público; corrigir DOC-001/ISSUE-021; rotas `/v1` como aliases finos sobre as Actions) **atrás de authz escopado**.
2. **AuthZ por tenant+capability é gate duro** (= ISSUE-037 / SEC-V1-001 — pré-requisito).
3. **`execOcc` fora do port público**; separar contextos `Integration` (comandos tipados, sem HTTP) e `TenantLifecycle` (linguagem `/api/v1`).
4. **Strangler com port mínimo** (3-4 métodos) provado por 1 capability; ondas (Livewire/Artisan inclusos) com characterization tests.
5. **Observabilidade obrigatória** na troca de transporte (`correlation_id`, alerta de job preso, paridade SSH vs Agent).
6. **D-02 (allowlist upstream) é dependência dura** para branding/quota/maintenance e para a saga `/v1/onboarding`.

### Dependências e relacionados

- **Bloqueado por**: ISSUE-037 / SEC-V1-001 (authz escopado — gate duro antes de abrir `/v1` a terceiros).
- **Relacionados**: ISSUE-021 / DOC-001 (drift OpenAPI), ISSUE-016 + D-02 / #ARCH-7 (allowlist OCC upstream), ISSUE-024 (memail.configure), `docs/PLATFORM-V2-PLAN.md` (Farm Agent 3 fases — a migração de transporte da v1 mapeia 1:1).

### Próximo passo

Sprint 0 (N30), Fases 1–4 (N31–N34) entregues — PlatformPort completo, grep gate estrito, `/occ/*` fora do spec externo, saga `POST /v1/onboarding` idempotente + runbook (branch `sprint/N34`; validation R2 APROVADA; CQ-N34-001/002/003 corrigidos). **Próximo passo**: PR/merge `sprint/N34`; adoção N22/onboarding-api; expandir allowlist D-02 (ISSUE-016) em produção.

## ISSUE-039 — CI vermelho no `main` (regressão N19 + phpseclib)

- **Tipo**: bug (CI blocker)
- **Prioridade**: HIGH
- **Status**: **closed (2026-06-16)** — Sprint F14 mergeada (PR #112); CI run `27652086261` verde
- **Registrado em**: 2026-06-16
- **Origem**: Investigação CI run `27646529336` (commit `1eebced`); lint corrigido via sprint-Q (`CI-FAIL-20260616200847` parcial)
- **Módulos afetados**: Audit, ClusterServers, Core (composer)
- **Findings**: QA-F14-001, QA-F14-002, SEC-F14-001

### Descrição

Após merge do sprint N19 (`4e2d1e9`), o workflow CI no `main` falha em dois jobs:

1. **Test (Pest/PHPUnit)** — 6 falhas em `AuditLogTest` (2) e `RotateSecretTest` (4). Causa: remoção de secrets do `$fillable` + factory auto-seed de `WebhookSecretHistory` sem atualização dos testes.
2. **Security (composer audit)** — `phpseclib/phpseclib` 3.0.52 vulnerável (GHSA-m557-wrgg-6rp4); fix `>=3.0.54`.

Não relacionado ao push de docs/contratos v1.

### Próximo passo

Nenhum — ISSUE-039 encerrada; CI verde no `main`.

## ISSUE-032 — Remover tenants de teste do host prod SaaS-02

- **Tipo**: ops (limpeza)
- **Prioridade**: HIGH
- **Status**: approved (usuário 2026-06-10) — em execução
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (auditoria SaaS-02 2026-06-10; decisão do usuário no chat)
- **Módulos afetados**: Ops (host SaaS-02), Customers (painel)

### Descrição

Auditoria read-only no SaaS-02 (`cloud.mework360.com.br`) encontrou 4 tenants de teste/demonstração sem uso real (criados via API do deployer com domínio `.dev.mework360.com.br` ou slug `teste*`; usuários finais nunca logaram; storage = skeleton ~62MB):
`teste` (painel preso em `provisioning` há 8+ dias), `teste2`, `gabrielteste08062026`, `mercadodoconstrutor`.

### Execução (2026-06-10) — CLOSED

Decisão do usuário: remover `teste`, `teste2`, `gabrielteste08062026`; **manter `mercadodoconstrutor`** (demo prospect). Com backup prévio.

| Slug | Job upstream (`backup-then-remove`) | Exit | Backup |
|------|--------------------------------------|------|--------|
| teste | `f0afcaa1-5db3-40b7-be4d-bf8627ce9a11` | 0 | `teste_20260610_074122.tar.gz` (324M) |
| teste2 | `863904b8-1320-47ee-ac6c-9ef8935a6b86` | 0 | `teste2_20260610_074230.tar.gz` (320M) |
| gabrielteste08062026 | `578ef656-1f79-4ffb-8684-19644a20768c` | 0 | `gabrielteste08062026_20260610_074338.tar.gz` (317M) |

Evidência pós-remove: `nextcloud-manage list --json` → 8 tenants (sem os 3). Painel: `customers.status='removed'` para os 3 (incluindo `teste` que estava preso em `provisioning`) + entradas `customer.removed_ops_cleanup` no audit_logs referenciando ISSUE-032. Backups em `/opt/nextcloud-customers/backups/`.

---

## ISSUE-033 — Conta seed `admin@mework360.local` é o único admin ativo no deployer prod

- **Tipo**: security
- **Prioridade**: HIGH
- **Status**: approved (usuário 2026-06-10) — aguardando definição do admin substituto
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (auditoria deployer prod 2026-06-10)
- **Módulos afetados**: Auth (operators), Ops

### Descrição

`operators` no deployer prod (2026-06-10): `admin@mework360.local` (admin, **active** — conta do seeder, senha potencialmente default), `hiparco.pocetti@me360.com.br` (operador, pending), `quality_contato@hotmail.com` (operador, pending). A seed é o **único admin ativo** — remover sem substituto = lockout do painel. Foi ela que executou os provisions de teste (ip/user_agent vazios no audit log).

### Ação aprovada

1. Criar admin nominal (`operators:create-admin`) **antes** de qualquer remoção
2. Desativar/remover `admin@mework360.local`
3. Ativar/ajustar role dos operadores pendentes conforme política

---

## ISSUE-034 — Backfill de 6 tenants prod sem registro no painel

- **Tipo**: change_request (inventário)
- **Prioridade**: MEDIUM
- **Status**: **closed (2026-06-10)**
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (verificação painel × host 2026-06-10)
- **Módulos afetados**: Customers, Ops

### Descrição

6 tenants rodando no SaaS-02 sem registro em `customers`: `76fibra`, `alloha`, `meltech`, `mework360`, `nextcloud-02`, `totum` (provisionados antes do deployer). Classificação do usuário (2026-06-10): **mework360 = conta real (colaboradores)**; demais = **contas de demonstração**. Backfill via `customers:sync` + revisar status.

### Execução (2026-06-10) — CLOSED

Executado por subagente (Composer 2.5) com guardrails — pré-check do código do comando (`CustomersSyncCommand` + `CustomerSyncService`: insert/update/soft-remove a partir de `nextcloud-manage list --json`) e do upstream antes de rodar.

```text
php artisan customers:sync --cluster=0e50e032-df0f-4387-aa00-43bae3672147
[producao] inserted=6 updated=0 deleted=5
```

- Estado final: 8 `active` (76fibra, alloha, meltech, mercadodoconstrutor, mework360, nextcloud-02, suzukisol2, totum) + 5 `removed`
- Os 6 tenants alvo registrados como `active`; `mercadodoconstrutor` intocado
- Efeito esperado do sync: 5 registros de teste ausentes no upstream (já `removed`) receberam soft delete
- Follow-up opcional: marcar metadado demo vs real (mework360 = real) — sem suporte no schema atual

---

## ISSUE-035 — Tabela `personal_access_tokens` ausente no deployer prod

- **Tipo**: investigacao
- **Prioridade**: HIGH
- **Status**: **closed (2026-06-10)** — premissa incorreta
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (consulta DB prod 2026-06-10)
- **Módulos afetados**: Core (auth API), DevOps

### Descrição

`SELECT` em `personal_access_tokens` no banco prod retorna `Base table or view not found`. Sem essa tabela o auth Bearer Sanctum (`/api-keys`, `POST /api/customers` via token) **não pode funcionar em prod**. Verificar `migrate:status` em prod (paralelo ao `failed_jobs` ausente — OPS-001/ISSUE-023) e rodar migrations pendentes.

### Investigação (2026-06-10) — CLOSED

Executado por subagente (Composer 2.5):

- `migrate:status` em prod: **18/18 migrations Ran, 0 pendentes** — nada para aplicar (nenhum `migrate --force` necessário)
- Repo local: **não existe** migration de `personal_access_tokens` nem de `failed_jobs`; `composer.json` **não inclui** `laravel/sanctum`
- O auth Bearer do projeto usa a tabela **`api_keys`** (guard `api-key`), que **existe** em prod — a API por token não depende de Sanctum

**Conclusão**: premissa do registro estava incorreta — não há quebra de auth. A tabela `personal_access_tokens` não é usada pelo projeto. `failed_jobs` ausente permanece rastreado em **OPS-001 / ISSUE-023** (decisão pendente: criar migration ou documentar no RUNBOOK).

**Follow-up sugerido**: smoke de validação `auth:api-key` em prod (criar token via painel `/api-keys` + `GET /api/customers` com Bearer) — encaixa no checklist do ISSUE-023.

---

## ISSUE-036 — Containers `*-push` em Restarting(127) no SaaS-02

- **Tipo**: bug
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (verificação subagente 2026-06-10)
- **Módulos afetados**: Cross-repo (`mework360-deploy-scripts` — template compose tenant)

### Descrição

Os containers `notify_push` (`<slug>-push`) dos 4 tenants verificados estão em `Restarting (127)` (binário/entrypoint ausente?). Não impede o `*-app`, mas degrada push notifications. Diagnosticar no upstream; possivelmente afeta todos os tenants do host.

---

## ISSUE-031 — Smoke E2E versionado do fluxo SSO meMail↔Roundcube

- **Tipo**: change_request (qualidade/QA)
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360_memail` (dono do teste); deployer-api (gancho pós-create)

### Descrição

O fluxo SSO meMail→RC (login server-to-server via cURL, propagação de cookies `roundcube_sessid`/`sessauth`, iframe, troca de perfil) não tem smoke E2E versionado (QA-004 no repo memail). Regressões recorrentes (BUG-001/002/004 memail) só são detectadas manualmente.

### Proposta

Smoke automatizado (Playwright ou script HTTP) cobrindo: login NC → abrir meMail → inbox carrega sem tela de login RC → troca de perfil. Executável standalone e como gate pós-create (depende de ISSUE-024).

### Artefatos impactados

- `mework360_memail`: novo smoke E2E + CI
- deployer-api: `ProbeCustomerReadinessJob`/runbook R8 referenciando o smoke; `.cursor/skills/me360-deployer/references/readiness-gates.md`, `post-create-runbook.md` §7

### Documentação afetada

- `post-create-runbook.md` §7 (smoke manual → automatizado), `readiness-gates.md` R8

---

## ISSUE-030 — SSRF no meMail: teste de conexão e autodetect IMAP (SEC-001/SEC-006)

- **Tipo**: change_request (segurança — fix em repo externo)
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360_memail` (SEC-001, SEC-006 no FINDINGS.md daquele repo)

### Descrição

`/api/profile/test` e a autodetecção IMAP fazem handshake a partir do container NC contra host arbitrário informado pelo usuário — superfície SSRF contra a rede interna do host compartilhado (shared-db, shared-redis, socket-proxy). Também há `CURLOPT_SSL_VERIFYPEER=0` herdado no `RequestService` (SEC-005).

### Proposta

Bloquear ranges privados/loopback na validação de host, usar o HTTP client do NC com verificação TLS, e allowlist opcional de servidores IMAP por ambiente.

### Artefatos impactados

- `mework360_memail`: `lib/Service/` (teste de conexão, autodetect, RequestService) + testes
- deployer-api: nenhum código; skill `me360-deployer` (guardrails) referencia o risco

---

## ISSUE-029 — Limites de CPU/memória no template de compose do tenant

- **Tipo**: change_request (infra upstream)
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360-deploy-scripts` (`manage.sh` template compose, ~L155–256)

### Descrição

O docker-compose gerado por tenant (app, nginx, cron, harp, push) não define `mem_limit`/`cpus`. Um tenant com carga anômala pode degradar todos os tenants do host (SaaS-02 tem 11). Único precedente de limites é o overlay meOffice do Collabora.

### Proposta

Adicionar limites parametrizáveis por perfil de plano no template (`.env` shared com defaults), aplicáveis em `create` e retrofit via `update`.

### Artefatos impactados

- `mework360-deploy-scripts`: template compose em `cmd_create`, `.env.example`, `docs/ADMINISTRATION.md`
- deployer-api: skill `ecosystem-map.md` (seção topologia)

---

## ISSUE-028 — Falhas mascaradas (`|| true`) no create/update upstream + falha parcial no webhook

- **Tipo**: change_request (confiabilidade — relacionado a ISSUE-022)
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360-deploy-scripts`; deployer-api: `Jobs/WebhookHandler`, readiness

### Descrição

`cmd_create`/`cmd_update` engolem falhas com `2>/dev/null || true` em passos críticos: `app:enable` (L406), HaRP register (L442–446), `occ upgrade` (L653), sync de custom apps (best-effort). Resultado: webhook `job.finished success` com tenant sem meMail/tema ou upgrade incompleto — a API marca `active` sem estar.

### Proposta

1. Upstream: distinguir passos fatais (abortar + exit≠0) de toleráveis (acumular warnings) e emitir `summary.warnings[]` no callback.
2. API: `WebhookHandler` persiste warnings; `ProbeCustomerReadinessJob` valida apps obrigatórios (`mework360_memail`, `me360_theme`) antes de `active` — complementa ISSUE-024.

### Artefatos impactados

- `mework360-deploy-scripts`: `manage.sh`, `worker.sh` (payload callback), `docs/CONTRACTS.md`
- deployer-api: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Jobs/ProbeCustomerReadinessJob.php`, `app/Modules/Customers/Services/CustomerReadinessProbe.php`, `docs/openapi.yaml` (shape webhook), testes Feature

### Documentação afetada

- `docs/CONTRACTS.md` (upstream), `docs/openapi.yaml`, skill `readiness-gates.md`

---

## ISSUE-027 — Teto de escala Redis upstream (`--databases 17`)

- **Tipo**: change_request (escala — bloqueio de venda previsível)
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360-deploy-scripts` (`shared-services/docker-compose.yml`, `legacy_helpers.sh::get_next_redis_db`)

### Descrição

`shared-redis` roda com `--databases 17`; cada tenant recebe um `dbindex` dedicado (worker usa DB 0). Teto prático: ~15 tenants com isolamento por dbindex. **Prod (SaaS-02) já tem 11.** Sem ação, o `create` do ~15º tenant falha ou colide.

### Proposta

Curto prazo: subir `--databases` (ex.: 64) — mudança de 1 linha + restart coordenado do shared-redis. Médio prazo: avaliar key-prefix por tenant (remove o teto) ou Redis por tenant no compose.

### Artefatos impactados

- `mework360-deploy-scripts`: `shared-services/docker-compose.yml`, `get_next_redis_db()` (validação de teto + erro explícito), `docs/ADMINISTRATION.md`
- deployer-api: nenhum código; skill `ecosystem-map.md`

---

## ISSUE-026 — RC prod desalinhado do dev: cookie domain pai + `frame-ancestors *` + imagem `:latest`

- **Tipo**: bug (segurança/configuração de infra — verificado em produção)
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (verificação SSH/HTTP read-only 2026-06-10)
- **Módulos afetados**: Cross-repo — infra `/opt/roundcube/` em SaaS-01 e SaaS-02

### Evidência (2026-06-10)

| Item | Dev (SaaS-01) | Prod (SaaS-02) |
|------|---------------|----------------|
| Cookie sessão | `path=/roundcube/` host-only ✔ | `domain=.mework360.com.br` ✘ (SEC-002 memail vivo) |
| CSP | `frame-ancestors 'self' + dev` ✔ | `frame-ancestors *` ✘ (clickjacking) |
| Imagem | `roundcube/roundcubemail:latest` ✘ | `roundcube/roundcubemail:latest` ✘ |
| Apache/PHP | 2.4.67 / 8.4.21 | 2.4.66 / 8.4.20 (drift de `:latest`) |
| Plugins | 83 dirs | 62 dirs (21 de drift) |

### Descrição

O fix de cookies host-only e CSP restritiva já existe no dev mas não foi replicado ao prod. Ambos os hosts usam `:latest` sem pin — um `docker compose pull` acidental salta a versão do RC contra ~39 patches version-coupled. Mitigação imediata independe de ISSUE-025: pin da tag atual + replicar config de cookie/CSP do dev no prod.

### Proposta

1. Pin imediato: fixar tag da imagem RC nos dois hosts (digest atual do prod documentado antes).
2. Replicar `config.inc.php`/vhost do dev (cookie host-only, `frame-ancestors` restrito ao(s) domínio(s) NC) no prod.
3. Resolução estrutural via ISSUE-025 (imagem própria taggeada).

### Artefatos impactados

- Hosts: `/opt/roundcube/docker-compose.yml` + `config/` (SaaS-01 e SaaS-02)
- deployer-api: skills `ecosystem-map.md`, `post-create-runbook.md` §4

---

## ISSUE-025 — Evoluir `mework360-roundcube` para distribuição com imagem pinada e deploy por tag

- **Tipo**: change_request (arquitetura — multi-repo)
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360-roundcube` (dono), `mework360_memail` (cede camada B), `mework360-deploy-scripts` (consome imagem), hosts SaaS-01/02

### Descrição

Hoje o RC de cada host é `roundcube/roundcubemail:latest` patchado in-place via SSH (`scp → docker cp → docker exec`), com ~39 patches version-coupled vivendo no monorepo memail e a verdade efetiva nos containers (drift dev/prod confirmado: 21 plugins, Apache/PHP divergentes). Decisão acordada: **usar o repo `mework360-roundcube` existente** (não criar novo) e promovê-lo de "kit camada A" para distribuição.

### Escopo proposto

1. `Dockerfile` com `FROM roundcube/roundcubemail:<versão pinada>` aplicando camada A (in-tree) + camada B (patches migrados do memail) na build
2. `config/` templates (`me360_nc_origin`, sessão, cookies host-only, CSP)
3. Snippet compose para `shared-services/` consumir a imagem por tag
4. Baseline capturada **do dev** (versão mais atual) via `capture-rc-from-dev.sh` ampliado; matriz de compatibilidade preenchida
5. Fluxo: validar imagem no dev → mesma tag no prod (replicação dev→prod vira deploy de tag)

Entra no provisionamento **de cluster** (shared-services), não no `create` por tenant — o tenant conecta via ISSUE-024.

### Artefatos impactados

- `mework360-roundcube`: Dockerfile, patches/, config/, CI build, README/WORKFLOW, CHANGELOG, tags
- `mework360_memail`: remoção gradual de `scripts/rc-patch/` (migrados), `docs/DEPLOY.md`
- `mework360-deploy-scripts`: `shared-services/docker-compose.yml` (serviço roundcube), `docs/ADMINISTRATION.md`
- deployer-api: skills `ecosystem-map.md`, `environment-and-parity.md`, `post-create-runbook.md` §4

### Documentação afetada

- README/WORKFLOW do kit, CONTRACTS/ADMINISTRATION upstream, skills do deployer

---

## ISSUE-024 — Automatizar configuração meMail no `create` (eliminar runbook manual pós-create)

- **Tipo**: change_request (provisionamento — relacionado a ISSUE-022)
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-06-10
- **Solicitante**: `/triagem` (análise Nextcloud+Roundcube 2026-06-10)
- **Módulos afetados**: Cross-repo — `mework360-deploy-scripts` (dono); deployer-api: Customers, Webhook, readiness

### Descrição

`cmd_create` instala `mework360_memail` mas não configura `externalLocation` (sem isso o iframe RC não carrega), `forceSSO` nem `emailAddressChoice`; também instala o app `mail` da store que a política prod desabilita depois. Resultado: tenant `active` na API com mail quebrado até execução manual do `post-create-runbook.md`. Objetivo (visão acordada 2026-06-10): **tenant novo nasce com a customização do dev automaticamente**.

### Escopo proposto

1. Upstream `cmd_create`: após enable do meMail, aplicar `occ config:app:set mework360_memail externalLocation --value=<RC do cluster>` (valor de `.env` shared por host), `forceSSO`, `emailAddressChoice`, e `occ app:disable mail` (flag de política)
2. Falha nesses passos = warning estruturado no callback (não `|| true` silencioso — ver ISSUE-028)
3. API: `ProbeCustomerReadinessJob` passa a validar `externalLocation` configurado antes de `active` (gate R8)

### Artefatos impactados

- `mework360-deploy-scripts`: `manage.sh` (`cmd_create`), `.env.example` shared (`MEMAIL_EXTERNAL_LOCATION` etc.), `docs/CONTRACTS.md`, `docs/ADMINISTRATION.md`
- deployer-api: `app/Jobs/ProbeCustomerReadinessJob.php`, `app/Modules/Customers/Services/CustomerReadinessProbe.php`, testes Feature correspondentes
- Skills: `post-create-runbook.md` (encolhe para validação), `readiness-gates.md` (R8), `ecosystem-map.md` (Layer 3)

### Documentação afetada

- `docs/CONTRACTS.md` upstream, skills me360-deployer (3 arquivos), `docs/RUNBOOK.md` se citar pós-create

---

## ISSUE-020 — Readiness probe vaza `ConnectionClosedException` do phpseclib

- **Tipo**: bug (resiliência de transporte SSH)
- **Prioridade**: MEDIUM
- **Status**: fixed — `SshClient` normaliza exceções de transporte do phpseclib e reaplica retry (Sprint F12, 2026-05-27)
- **Registrado em**: 2026-05-27
- **Solicitante**: `/qa debug`
- **Módulos afetados**: `Core/Ssh`, `Customers`

### Sintoma

Após `job.finished state=success`, o `ProbeCustomerReadinessJob` executa `nextcloud-manage <slug> occ-exec user:list --json`.
Quando a conexão SSH reaproveitada pelo pool fecha antes de abrir o canal de `exec()`, o job registra `local.ERROR: Connection closed prematurely` com exceção crua de `phpseclib3\Exception\ConnectionClosedException`.

### Causa raiz

`SshClient::executeCommand()` normaliza apenas `exec() === false`. Se `SSH2::exec()` lança uma exceção de transporte, ela escapa sem virar `SshConnectionException`.
Com isso, o retry interno de `SshClient::run()` não é acionado e callers como `CustomerReadinessProbe` não conseguem tratar a falha como "not ready".

### Fix Brief aprovado

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `app/Modules/Core/Ssh/SshClient.php` | Envolver `exec()`/`execWithStdin()` em `try/catch \Throwable`, remover a conexão do pool e lançar `SshConnectionException` com `previous` preservado. |
| 2 | `tests/Feature/Core/SshClientTest.php` | Adicionar regressão simulando `SSH2::exec()` lançando `ConnectionClosedException`, validando retry e sucesso na tentativa seguinte. |

### Critério de aceite

- Exceções de transporte lançadas pelo phpseclib durante `exec()` são normalizadas como `SshConnectionException`.
- `SshClient::run()` remove a conexão stale do pool e tenta novamente conforme `RETRY_DELAYS_SECONDS`.
- `CustomerReadinessProbe` volta a tratar a falha transitória como `false`, sem `local.ERROR` não tratado.
- Teste filtrado: `php artisan test --filter SshClientTest`.

---

## ISSUE-019 — Branding não garantido no payload do job `create` quando cliente tem logo cadastrado

- **Tipo**: bug
- **Prioridade**: MEDIUM
- **Status**: fixed na API (Sprint F13 — validação APROVADA R1); **e2e pendente** se upstream só aplicar logo via `--staging-id` — ver **ISSUE-022**, `docs/HANDOFF-BRANDING-BUG.md`
- **Registrado em**: 2026-05-24
- **Solicitante**: `/qa debug`
- **Módulos afetados**: `Customers/Dto/ProvisionPayload`, `Customers/Actions/ProvisionCustomerAction`, `Http/Controllers/Api/CustomerController`, `Models/Customer`

### Descrição

Ao enfileirar um job `create` (provisioning), o campo `branding.logo_data_url` (e `branding.background_data_url`) **não é garantido no payload SSH** quando o cliente já possui logo cadastrado no sistema, mas nenhum arquivo foi enviado na requisição corrente.

**Root causes**:

1. `ProvisionPayload::fromRequest()` resolve `logoPath` e `backgroundPath` exclusivamente de arquivos do request HTTP corrente (`$request->hasFile('logo')`). Não existe mecanismo de persistência de logo — `branding_meta` nunca recebe a referência do disco. O temp file do upload é consumido no SSH dispatch e descartado após o request.
2. O payload inline atual monta `logo_data_url` e `background_data_url` no topo do JSON enviado via `--payload-stdin`; o contrato upstream esperado exige essas chaves dentro de `branding`.

Consequência:
1. **First provision com logo** → logo incluído quando temp file está presente, mas no shape incorreto se inline (`logo_data_url` top-level). ❌
2. **Re-provisioning (ghost restore) sem re-upload** → `logoPath=null` → bloco de branding não executa → SSH `create` omite `branding`. ❌
3. **Qualquer provision sem upload** mesmo que o cliente tenha logo registrado de outra forma → sem branding no job. ❌

### Contrato esperado upstream

```json
{
  "branding": {
    "logo_data_url": "data:image/png;base64,<base64>",
    "background_data_url": "data:image/png;base64,<base64>"
  }
}
```

Passado via `--payload-stdin` (≤ 256 KB) ou pré-uploadado via SFTP com `--staging-id` (> 256 KB).

### Fix Brief

**Plano em 4 passos**:

1. **`ProvisionCustomerAction`** — montar o payload stdin como `['branding' => ['logo_data_url' => ..., 'background_data_url' => ...]]`; manter `--payload-stdin` para inline ≤ 256 KB e `--staging-id` para SFTP > 256 KB.

2. **`ProvisionCustomerAction`** — após a transação DB (customer criado/restaurado), persistir o temp file em `Storage::disk('local')` → `branding/{slug}/logo.png` (e `background.*`) e atualizar `customer->branding_meta` com `logo_path` e `background_path`.

3. **`ProvisionPayload`** — adicionar `fromRequestWithCustomer(Request $request, ?Customer $ghost): self` que, quando nenhum arquivo estiver no request, resolve `logoPath` de `$ghost->branding_meta['logo_path']` (via `Storage::disk('local')->path(...)`).

4. **`CustomerController::store()`** — consultar ghost antes de construir o payload (usar `Customer::withTrashed()->where('slug', ...)->whereNotNull('deleted_at')->first()`), repassar ao novo factory method.

5. **Testes** — cobrir inline com JSON aninhado em `branding`, re-provision de ghost com `branding_meta.logo_path` populado → `runAsync` chamado com `branding.logo_data_url` no stdin (sem upload no request corrente), e branch SFTP mantendo `--staging-id` sem payload inline.

**Arquivos**:
- `app/Modules/Customers/Dto/ProvisionPayload.php`
- `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
- `app/Http/Controllers/Api/CustomerController.php`
- `tests/Feature/Customers/ProvisionTest.php`

**Riscos**:
- Storage `local` deve ser consistente (sem S3 por padrão) — baixo risco.
- Dupla consulta de ghost (controller + action) — aceitável; simplificar extraindo helper.
- Corrida em re-provision concorrente já protegida pela idempotency key.

### Resultado Sprint F13

- Payload inline agora usa `branding.logo_data_url` e `branding.background_data_url`.
- Se o JSON base64 final excede 256 KB, o fluxo usa SFTP staging com `--staging-id` sem stdin.
- Uploads iniciais são persistidos em `Storage::disk('local')` e `branding_meta` para re-provisionamento.
- Re-provision de ghost reaproveita logo/background salvos quando não há novo upload no request.
- Testes: `php artisan test tests/Feature/Customers/ProvisionTest.php` → 16 passed, 63 assertions.
- Validação senior+qa R1: APROVADA, sem findings remanescentes.

---

## ISSUE-018 — Slug bloqueado após falha de provisioning (re-provisioning impossível)

- **Tipo**: bug (lifecycle gap — missing cleanup path)
- **Prioridade**: HIGH (bloqueia completamente re-provisioning após qualquer falha de job de provisão; sem workaround via UI/API)
- **Status**: open (Fix Brief aprovado — 2026-05-24, aguarda Sprint F11)
- **Registrado em**: 2026-05-24
- **Reportado por**: `/qa debug` (sessão de diagnóstico ao vivo)
- **Módulos afetados**: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Http/Requests/ProvisionCustomerRequest.php`, `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
- **Relacionados**: `app/Models/Customer.php` (SoftDeletes), `database/migrations/2026_05_08_000004_create_customers_table.php`

### Sintoma

Após um job de provisioning falhar (webhook chega com `state=failed`), tentar re-provisionar o mesmo `slug` retorna **422 "Slug já em uso."** — permanentemente, sem possibilidade de recuperação via UI/API.

### Causa raiz (três lacunas encadeadas)

**Lacuna 1 — `WebhookHandler` (linha 169):** Quando `job_type === 'provision'` e `canonical === 'failed'/'cancelled'`, o handler apenas atualiza `customer.status = 'failed'`. O registro `Customer` **permanece vivo** na tabela (nunca é soft-deletado). Como `slug` é a PK, o registro "fantasma" bloqueia qualquer inserção futura.

**Lacuna 2 — `ProvisionCustomerRequest` (linha 21):** A regra `unique:customers,slug` não exclui soft-deleted records (usa count plain sem `whereNull('deleted_at')`). Mesmo após corrigir a Lacuna 1, esta regra bloquearia re-tentativas com 422 enquanto o registro soft-deleted existir.

**Lacuna 3 — `ProvisionCustomerAction` (linha 144):** Se um registro soft-deleted com mesmo slug existir, `Customer::create()` colide na PK do banco (constraint única ignora `deleted_at`). Necessário `forceDelete` do fantasma antes do `create`.

### Fix Brief aprovado

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `app/Modules/Jobs/Services/WebhookHandler.php` | Soft-delete do Customer quando provision falha/cancela |
| 2 | `app/Http/Requests/ProvisionCustomerRequest.php` | `Rule::unique('customers', 'slug')->whereNull('deleted_at')` |
| 3 | `app/Modules/Customers/Actions/ProvisionCustomerAction.php` | `Customer::withTrashed()->where('slug', ...)->whereNotNull('deleted_at')->forceDelete()` antes do `create` |

### Critério de aceite

- Provisionar slug `foo` → falhar (job.finished state=failed via webhook) → provisionar `foo` novamente → **202 accepted** com novo `job_id`
- Validação HTTP retorna 422 apenas se existe customer com mesmo slug **ativo** (não soft-deleted)
- `Customer::create()` não falha com PK duplicate quando existe registro soft-deleted anterior
- Testes: webhook `provision.failed` + re-provisioning happy path

---

## ISSUE-016 — Endpoints OCC quota/branding/maintenance indisponíveis (allowlist upstream)

- **Tipo**: change_request (capability gap upstream + contrato enganoso)
- **Prioridade**: HIGH (REQUIREMENTS §6.4–6.6 promete MVP; Sofia não consegue quota/branding/maintenance via API/UI)
- **Status**: mitigated (fast-track 2026-05-24) — **F?-OCC-1..3 implementados** (OpenAPI 403/501, OccPanel exit 16, argv default quota). Gap funcional upstream permanece — **aguarda decisão estratégica D-02** + resposta de [`SoftwareBeesy/mework360-deployer-scripts#22`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/22) (OPEN)
- **Sprint**: a alocar após D-02 — mitigações de contrato (OpenAPI + OccPanel UX) podem ir como fast-track independente
- **Origem**: P-17 em `docs/PROBLEMAS-ENCONTRADOS.md` (testes dinâmicos 2026-05-21)
- **Módulos afetados**:
  - `routes/api.php` (5 rotas publicadas)
  - `app/Http/Controllers/Api/OccController.php` (`setQuotaDefault`, `setQuota`, `setQuotaAll`, `setBranding`, `toggleMaintenance`)
  - `app/Http/Livewire/Customers/OccPanel.php` (espelha os mesmos subcmds — UI também quebrada)
  - `docs/openapi.yaml` (declara endpoints como funcionais; sem responses 403/501)
  - `docs/REQUIREMENTS.md` §6.4–6.6 (MVP prometido)
- **Upstream afetado**: `nextcloud-saas-manager` — allowlist de `occ-exec` (exit 16)
- **Relacionados**: ISSUE-011 / P-15 (causa raiz confirmada), P-10 (argv multi-key latente em `setBranding`), P-07 (drift OpenAPI), Decision `#ARCH-6`

### Descrição

Cinco endpoints OCC mutativos estão publicados em `routes/api.php` mas **nunca funcionam** contra o upstream atual porque os subcmds OCC subjacentes estão **fora da allowlist** de `nextcloud-manage <slug> occ-exec` (exit 16).

| Endpoint | Subcmd OCC | Comportamento atual (pós ISSUE-011) |
|---|---|---|
| `PUT .../occ/quota/default` | `config:app:set` | **403** `occ_subcmd_not_allowed` |
| `PUT .../occ/quota/{username}` | `user:setting` | **403** `occ_subcmd_not_allowed` |
| `PUT .../occ/quota/all` | `user:setting --all` | **501** `occ_subcmd_not_supported` (short-circuit local) |
| `PUT .../occ/branding` | `theming:config` | **403** `occ_subcmd_not_allowed` |
| `POST .../occ/maintenance` | `maintenance:mode` | **403** `occ_subcmd_not_allowed` |

ISSUE-011 corrigiu o diagnóstico (allowlist, não "flag stripping") e mapeou exit 16 → 403 honesto (antes: 502 genérico). **O gap funcional permanece**: operadores precisam de SSH manual para quota, branding e maintenance.

`OccPanel` (Livewire) chama os mesmos subcmds — abas Quota/Branding/Maintenance falham com mensagem genérica `"Erro upstream (exit 16)"` (sem mapeamento explícito como no controller).

### Endpoints OCC que funcionam (contraste)

| Endpoint | Subcmd | Status |
|---|---|---|
| `GET .../occ/quota/options` | estático | ✅ |
| `GET .../occ/quota/audit` | `user:list` | ✅ |
| `POST .../occ/files-rescan?username=` | `files:scan <user>` | ✅ |
| `POST .../occ/apps/{appId}/enable` | `app:enable` | ✅ |

### Decisão estratégica pendente (D-02 → `#ARCH-7`)

| Caminho | Descrição | Prós | Contras | Quando escolher |
|---|---|---|---|---|
| **A** | Pedir ampliação da allowlist upstream (`user:setting`, `config:app:set`, `theming:config`, `maintenance:mode`) | Zero redesign na API; endpoints passam a funcionar como projetados | Expande superfície OCC exposta; depende de maintainer upstream | Upstream aceita e documenta allowlist expandida (#22 opção a) |
| **B** | Despublicar endpoints quebrados (`routes/api.php`, OpenAPI, abas OccPanel) | Contrato honesto imediato; sem falsa promessa | Remove capacidades MVP (REQUIREMENTS §6.4–6.6); breaking change | Upstream recusa expandir allowlist e não oferece verbos alternativos |
| **C** | Usar verbos de domínio upstream (ex.: `maintenance enable`, `quota set`, `branding apply`) em vez de `occ-exec` cru | Melhor boundary de segurança; alinhado com alternativa D de `#ARCH-6` | Requer design upstream + refactor API; investigação prévia via `--help` | Upstream prefere expor verbos dedicados (#22 opção d) |

**Recomendação de triagem (2026-05-24)**:

1. **Não despublicar (B) agora** — REQUIREMENTS trata quota/branding/maintenance como MVP; remover sem alternativa piora operação de Sofia.
2. **Priorizar A + investigação C em paralelo** — issue #22 já aberta; rodar `nextcloud-manage --help` / `occ-exec --help` no node para mapear verbos de domínio existentes antes de commitar em C.
3. **Fast-track API (independente de D-02)**: documentar 403/501 no OpenAPI; mapear exit 16 no `OccPanel::formatError`; alinhar argv de `OccPanel::submitQuota` default com controller (`--value` obrigatório em `config:app:set` — ver F?-OCC-4).

### Investigação F?-OCC-4 (2026-05-24, `dev.mework360.com.br` upstream v12.3.0)

SSH read-only via `sudo nextcloud-manage` no node SaaS:

| Descoberta | Detalhe |
|---|---|
| **Verbos de domínio (caminho C)** | ❌ Inexistentes. Namespaces hierárquicos: `user`, `group`, `apps`, `occ-exec` apenas. Sem `maintenance enable`, `quota set`, `branding apply`. |
| **OCC_ALLOWLIST expandida** | ✅ `occ_bridge.sh` inclui `user:setting`, `config:app:set`, `theming:config`, `maintenance:mode` (entre 35 subcmds). |
| **Exit code 16 ≠ allowlist** | ⚠️ **Correção semântica**: exit **16** = `occ_command_failed` (OCC retornou ≠0); exit **100** = `occ_command_not_allowed` (fora da allowlist/blocklist). ISSUE-011 mapeia 16→403 — revisar em follow-up. |
| **Subcmds funcionais (argv correto)** | `theming:config`, `maintenance:mode --on\|--off`, `user:setting <u> files quota "<valor>"`, `files:scan <u>`, `app:enable <id>` → exit 0 em `teste5`. |
| **`config:app:set` argv** | Requer `--value`: `config:app:set files default_quota --value "3 GB"`. Positional extra → exit 16 (too many arguments). **OccController corrigido** neste fast-track. |
| **`maintenance:mode` argv** | Positional `on`/`off` falha; `--on`/`--off` funciona (confirma argv canônico da API). |

**Implicação para D-02**: caminho **A** (allowlist) já parece entregue no upstream v12.3.0; bloqueios remanescentes em produção podem ser **argv incorreto** (exit 16) ou **versão upstream desatualizada** no cluster de staging — não necessariamente allowlist. Recomenda-se reexecutar matriz P-15 pós-deploy e corrigir mapeamento 16 vs 100.

### Critério de aceite (por caminho)

**Se A (allowlist expandida)**:

- Matriz P-15 reexecutada: os 5 subcmds retornam exit 0 em dev.
- `OccControllerTest` + `OccPanelTest` happy-path verdes contra upstream real (`RUN_UPSTREAM_CONTRACT=1`).
- OpenAPI atualizado com responses 200 documentadas.

**Se B (despublicar)**:

- Rotas removidas de `routes/api.php`; OpenAPI e REQUIREMENTS §6.4–6.6 ajustados.
- OccPanel: abas removidas ou desabilitadas com mensagem "indisponível".
- CHANGELOG registra breaking change.

**Se C (verbos de domínio)**:

- Decision `#ARCH-7` documenta mapeamento endpoint → verbo upstream.
- `OccPassthroughService` ou gateway dedicado encapsula tradução.
- P-10 validado após allowlist/verbo permitir `theming:config`.

### Mitigações imediatas (fast-track, sem bloqueio upstream)

| Task | Escopo | Estimativa |
|---|---|---|
| F?-OCC-1 | OpenAPI: responses 403 `occ_subcmd_not_allowed` + 501 nos 5 endpoints | ~2h |
| F?-OCC-2 | `OccPanel::formatError`: exit 16 → mensagem explícita (paridade com API) | ~30min |
| F?-OCC-3 | `OccPanel::submitQuota` default: argv com `--value` (OCC exige flag; F?-OCC-4 refutou positional) | ~30min |
| F?-OCC-4 | Investigar verbos upstream (`ssh … nextcloud-manage --help`) — input para D-02 | ~1h read-only | ✅ 2026-05-24 |

### Próximo passo

1. **PMO/Product**: escolher caminho A/B/C → registrar como **Decision `#ARCH-7`** (D-02).
2. **Paralelo**: executar mitigações F?-OCC-1..4 via `/fix` ou sprint fast-track.
3. **Upstream**: acompanhar resposta em #22 antes de sprint de implementação do caminho escolhido.

### Matriz P-15 reexecutada (2026-05-24 pós PR #68, tenant `teste5`)

| Resultado | Detalhe |
|---|---|
| **P-10 corrigido** | `PUT branding` com `name` + `color` → **200** (2× `theming:config`) |
| **Maintenance** | `--on` / `--off` → **200** |
| **Quota compacta** | `3GB` / `5GB` → **200** |
| **Quota/branding com espaço** | `"3 GB"`, `"5 GB"`, `"Acme Corp"` → **403** exit 16 via API |
| **Upstream direto** | Mesmos payloads com `sudo nextcloud-manage` no node → **exit 0** |
| **Diagnóstico** | Gap no hop SSH `deployer → ncsaas-api` ForceCommand, não allowlist — ver **ISSUE-017** |

---

## ISSUE-017 — OCC argv com espaços falha no hop SSH ForceCommand (ncsaas-api)

- **Tipo**: bug (transporte SSH — argv com whitespace)
- **Prioridade**: HIGH (UI/API expõe `"5 GB"` em `quota/options`; operadores usam labels com espaço)
- **Status**: open — fix em andamento (`OccQuotaValue` compact + `SshClient` single-quote para demais args)
- **Origem**: matriz P-15 reexecutada 2026-05-24 pós PR #68 (`rr/fix/occ-ssh-quoting-and-branding`)
- **Módulos afetados**:
  - `app/Modules/Core/Ssh/SshClient.php` (`formatRemoteArg`)
  - `app/Http/Controllers/Api/OccController.php` (quota argv)
  - `app/Http/Livewire/Customers/OccPanel.php` (quota argv)
  - `app/Modules/Customers/Support/OccQuotaValue.php` (novo — compactação quota)
- **Relacionados**: ISSUE-016, P-15, P-10 (corrigido), PR #68, `UserCreateStdinPayload::normalizeQuota` (mesma regra já usada em `users:create`)

### Descrição

PR #68 adicionou aspas duplas em `SshClient::formatRemoteArg()` para sobreviver ao word-split do ForceCommand. A matriz P-15 pós-deploy mostrou que **não resolve** o hop `deployer → ncsaas-api@dev`:

| Payload API | Via API (SSH hop) | Direto no node (`sudo nextcloud-manage`) |
|---|---|---|
| `quota: "5 GB"` | **403** exit 16 | **exit 0** |
| `quota: "5GB"` | **200** | exit 0 |
| `branding: {"name":"Acme Corp"}` | **403** exit 16 | exit 0 |
| `branding: {"name":"TestBrand","color":"#112233"}` | **200** (P-10 OK) | exit 0 |

Exit 16 continua mapeado como `occ_subcmd_not_allowed` (ISSUE-011) mas aqui é **`occ_command_failed`** por argv corrompido no transporte — não allowlist (exit 100).

### Fix proposto (API)

1. **Quota**: compactar labels antes do SSH (`"5 GB"` → `"5GB"`) via `OccQuotaValue::forSshArgv()` — reutiliza `UserCreateStdinPayload::normalizeQuota`. OCC aceita ambos os formatos (confirmado F?-OCC-4).
2. **Branding/outros args com espaço**: trocar aspas duplas por **single-quote** bash em `formatRemoteArg()` (`'Acme Corp'`).
3. **Upstream (médio prazo)**: issue em `mework360-deployer-scripts` para ForceCommand preservar quoting em `SSH_ORIGINAL_COMMAND`.

### Critério de aceite

- Matriz P-15: `PUT quota/default {"quota":"3 GB"}` e `PUT quota/admin {"quota":"5 GB"}` → **200**
- `PUT branding {"name":"Acme Corp"}` → **200** (pós single-quote)
- Testes: `OccQuotaValueTest`, `OccControllerTest`, `SshClientTest`, `OccPanelTest`
- API continua aceitando labels com espaço na request (sem breaking change)

---

## ISSUE-013 — Callbacks de webhook com `exit_code` e `summary` null em 100% dos jobs

- **Tipo**: bug (observabilidade — diagnóstico remoto inviabilizado)
- **Prioridade**: HIGH (não derruba produção, mas mascara P-21/P-15/P-01 e impede triagem de qualquer falha de job)
- **Status**: investigated — **causa raiz upstream confirmada e issue aberta** (Fase 1 read-only concluída 2026-05-24); fix primário fora desta API. Fast-tracks na API: ISSUE-014 (SSH fallback quebrado — **fixed no código**) + ISSUE-015 (audit raw payload). Rastreado em **ISSUE-022** (coordenação cross-repo).
- **Sprint**: a alocar — **fix primário rastreado em [`SoftwareBeesy/mework360-deployer-scripts#23`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/23)**; mitigações ISSUE-014/ISSUE-015 nesta API podem ir como fast-track
- **Origem**: testes dinâmicos API dev (`deployer.mework360.com.br/api`) — P-05 em `docs/PROBLEMAS-ENCONTRADOS.md` (28 jobs verificados / 5 verbos: provision, deprovision, users:create, users:delete, occ-exec)
- **Módulos afetados (suspeitos — a confirmar pela investigação)**:
  - `app/Modules/Jobs/Services/WebhookHandler.php` (`applyFinishedEvent` linhas 111–164 — persistência de `exit_code` e `summary`)
  - `app/Modules/Jobs/Dto/WebhookPayload.php` (linha 66 — coerção `exit_code`; linha 24 — `logTail`)
  - `app/Http/Middleware/VerifyWebhookHmac.php` (linhas 71–82 — só valida `job_id`+`state` como obrigatórios; demais campos opcionais sem aviso quando ausentes)
  - `app/Http/Controllers/Api/WebhookController.php` (linhas 21–24 — passagem do payload já decodado)
  - `app/Modules/Jobs/Services/JobLogFetcher.php` (fallback SSH quando `log_tail` ausente — `WebhookHandler` linha 146)
  - `app/Models/AuditLog.php` + tabela `audit_logs` (`action='webhook_received'`, linhas 188–202 — **fonte de verdade do payload raw em produção**)
- **Upstream afetado**: `nextcloud-saas-manager` (worker — emissão dos campos `exit_code`, `duration_ms`, `log_tail` no callback `job.finished`) — a confirmar pela investigação
- **Relacionados**:
  - **Mascara**: P-21 (a definir), P-15 (matriz de allowlist OCC — exit codes invisíveis impedem refinar matriz), P-01 (provision premature — sem `exit_code` não dá para distinguir falha real vs. janela de readiness)
  - **Amplifica**: ISSUE-010 / FINDINGS §F8 — readiness gate diagnostica "users:create falha silenciosamente, sem exit_code" justamente por causa deste bug
  - **Contraste**: ISSUE-009 (logs ausentes em `queue/{jobId}` — pull via SSH pós-`job.finished`); se P-05 for upstream, ISSUE-009 vira mitigação obrigatória

### Descrição

Em **28 de 28 jobs verificados** (5 verbos distintos) na API dev, o callback `job.finished` chega ao webhook receiver com **ambos** `exit_code=null` **e** `summary=null` na tabela `jobs` após o ciclo completo. O comportamento é 100% reprodutível, independente do verbo (provision, deprovision, users:create, users:delete, occ-exec) e do `state` final (`success` vs. `failed`).

Consequência prática: quando um job falha, a API persiste apenas `state='failed'` + `finished_at` — sem **nenhum** sinal de **por que** falhou. Operadores e logs de produção veem "job failed" sem `exit_code`, sem `summary`, sem últimas linhas de log. Toda triagem remota vira "abrir SSH manualmente no node e procurar". P-21/P-15/P-01 herdam essa cegueira.

### Validação SSH produção (2026-06-02)

> Origem: `/pmo` + inspeção read-only em `deployer.mework360.com.br` (git `cf773dc`, `/up` 200, stack Docker healthy).

| Métrica | Valor |
|---------|-------|
| Jobs últimos 7 dias | 5 |
| `state=success` | 4 |
| `state=queued` | 1 |
| `exit_code` null (jobs no período) | 1 |
| `summary` null (jobs no período) | 1 |

**Interpretação:** em staging/dev a amostra era **100% null** (28/28); em produção recente o problema **persiste parcialmente** (1/5), não desapareceu. Fechar ISSUE-013 exige fix upstream (#23) **e** validar pós-deploy que novos callbacks tragam `exit_code`/`log_tail`; mitigação `JobLogFetcher` (ISSUE-014 / sprint F10) deve ser validada via **ISSUE-023**.

### Hipóteses (a refutar/confirmar pela investigação read-only)

1. **`WebhookHandler` ou `WebhookPayload` descarta os campos**
   - **Evidência preliminar a favor**: nenhuma — leitura do código mostra `WebhookPayload::fromArray()` linha 66 lendo `$data['exit_code']` direto, e `WebhookHandler::applyFinishedEvent` linha 134 fazendo `$updates['exit_code'] = $payload->exitCode` sem filtragem.
   - **Evidência preliminar contra**: persistência é simples e literal; testes em `tests/Unit/Modules/Jobs/Dto/WebhookPayloadTest.php` exercitam `exit_code`.
   - **Como confirmar**: comparar `audit_logs.payload->>'exit_code'` (raw upstream) com `jobs.exit_code` em uma amostra de 5+ jobs failed.

2. **Upstream não envia os campos**
   - **Evidência preliminar a favor**: contrato em `docs/SSH API Reference — Nextcloud SaaS.md` lista `exit_code` e `duration_ms` como **opcionais** no callback (`VerifyWebhookHmac` linha 77 confirma — só `job_id`+`state` são obrigatórios); 100% de ausência é sintoma típico de campo nunca emitido pelo worker, não de bug intermitente de parsing.
   - **Como confirmar**: inspecionar `audit_logs.payload` (JSONB completo, persistido tal-qual o upstream enviou — linhas 188–202 do `WebhookHandler` armazenam o payload bruto em `payload->>'event'`, `payload->>'state'`, `payload->>'exit_code'`, etc.). Se as chaves **não existem** no JSONB, é upstream. Se existem mas vêm `null`, é upstream emitindo null. Se existem com valor mas a coluna `jobs.exit_code` está null, é (1).

3. **Ambos** (worker upstream emite parcialmente + algum caminho da API descarta em fallback)
   - **Como confirmar**: matriz cruzada {upstream emite sim/não} × {API persiste sim/não} sobre amostra de 10 jobs.

### Resultado da investigação Fase 1 (2026-05-24, staging `deployer.mework360.com.br`)

Veredito **inequívoco**: **Hipótese 2 confirmada** (upstream).

**Sample**: 118 audits `webhook_received` (59 `job.finished` + 59 `job.started`), 8 `job_types` distintos (provision, deprovision, user_create, user_delete, group_create, group_delete, apps_enable, apps_disable).

| Métrica | Resultado |
|---|---|
| `jobs.exit_code IS NOT NULL` | 0 / 59 (0%) |
| `jobs.summary IS NOT NULL` | 0 / 59 (0%) |
| `jobs.finished_at IS NOT NULL` | **59 / 59 (100%)** — upstream **envia** `finished_at` |
| `audit_logs payload.exit_code` chave presente / valor não-null | 59/59 / **0/59** — upstream emite key com valor `null` |
| `audit_logs payload.duration_ms` chave presente / valor não-null | 59/59 / **0/59** — idem |
| `audit_logs payload.log_tail` chave presente | **0/59** — upstream nunca envia |

**Sample raw (job.finished)** — `audit_logs[recent]`:

```json
{"event":"job.finished","state":"success","cmd":"user_create","exit_code":null,"duration_ms":null}
```

**Ressalva metodológica**: o que está em `audit_logs.payload` **não é** o payload bruto do upstream — é uma reconstrução parcial montada pelo próprio `WebhookHandler::applyFinishedEvent` (linhas 188–202: salva só `event/state/cmd/exit_code/duration_ms`). Mas isso é prova **por implicação** da hipótese 2: como `WebhookPayload::fromArray` (linha 66) lê `(int) $data['exit_code']` literalmente, se chegou null no audit é porque chegou null em `$data['exit_code']`. E o fato de `jobs.finished_at` estar populado em 100% dos jobs prova que **o upstream envia `finished_at`** no body original — só o audit que não copia.

**Achado secundário (escopo novo — virou ISSUE-014)**: `JobLogFetcher` SSH fallback é invocado em 100% dos `job.finished` (porque `log_tail` ausente) e **falha 100%** com `SSH exit code 101` (network unreachable):

```
[2026-05-24 03:29:38] local.WARNING: jobs.log_fetch.failed
  job_id=079bdc3f-... cluster_id=1eb7e788-... exit code 101
```

**Achado terciário (virou ISSUE-015)**: `audit_logs.payload` armazena apenas reconstrução parcial — primeira hipótese de investigação (que o audit teria o payload raw) ficou comprometida. Forçar a salvar payload raw completo é mitigação independente que vale a pena.

### Plano de investigação read-only — concluído (referência histórica)

> **Restrição explícita do reporter**: nenhuma alteração de código antes desta fase concluir.

1. **Snapshot do payload raw em produção (5 min)** — Tinker/SQL read-only:
   ```sql
   SELECT id, resource_id AS job_id, payload, created_at
   FROM audit_logs
   WHERE action = 'webhook_received'
   ORDER BY created_at DESC
   LIMIT 20;
   ```
   - Inspecionar quais chaves estão presentes em `payload` (esperado pelo contrato: `event`, `state`, `cmd`, `exit_code`, `duration_ms`, `started_at`, `finished_at`, `log_tail`).
   - Marcar cada job como: `[A] upstream omite chave`, `[B] upstream envia null`, `[C] upstream envia valor mas API descarta`.

2. **Cross-check com `jobs` table**:
   ```sql
   SELECT j.job_id, j.state, j.exit_code, j.summary IS NULL AS summary_null,
          a.payload->>'exit_code' AS raw_exit_code,
          a.payload ? 'exit_code' AS has_exit_code_key,
          a.payload ? 'log_tail'  AS has_log_tail_key
   FROM jobs j
   JOIN audit_logs a ON a.resource_id = j.job_id AND a.action = 'webhook_received'
   ORDER BY j.created_at DESC
   LIMIT 30;
   ```

3. **Snapshot de `JobLogFetcher` (fallback SSH)** — checar `Log::warning('jobs.log_fetch.failed')` e `Log::info('jobs.log_fetch.duration_ms')` (`WebhookHandler` linhas 151–160) nos últimos 7 dias:
   - Se 100% dos jobs estão chamando o fallback (porque `log_tail` vem null) e o fallback **também falha**, há dois problemas independentes: upstream omite `log_tail` **e** SSH pull não está conseguindo recuperar `summary`.

4. **Decision Brief com matriz preenchida** — registrar conclusão em `docs/DECISION-BRIEF.md` antes de qualquer fix.

### Caminho de fix definido (pós-investigação)

**Fix primário — fora desta API** ([`mework360-deployer-scripts#23`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/23)):

- Smoking gun localizado em `scripts/worker.sh` linhas 144–152: `_fire_callback` (terminal) emite **apenas 4 campos** (`schema_version, job_id, state, ts`) quando o contrato `CallbackEventFinished` (CONTRACTS.md §5.3) declara **11 obrigatórios** (incluindo `event, cmd, client, exit_code, queued_at, finished_at, duration_ms, args_hash`). Sem o fix upstream, **nenhum fix nesta API resolve P-05** — ela já persiste corretamente o que recebe.

**Mitigações na API (sob nosso controle, fast-track)**:

- **ISSUE-014** — Consertar `JobLogFetcher` SSH fallback (exit 101). Mesmo após upstream começar a enviar `log_tail`, o fallback continua sendo backup; precisa funcionar.
- **ISSUE-015** — Fazer `WebhookHandler` salvar payload raw completo em `audit_logs` (não a reconstrução parcial atual). Primeira fonte de verdade para qualquer P-05-like futuro.

### Critério de aceite

- **ISSUE-013 (este)**: fica `closed` quando o upstream começar a emitir os campos e validarmos com `audit_logs.payload->>'exit_code' IS NOT NULL` em ≥ 95% dos `job.finished` em janela de 7 dias.
- **ISSUE-014** e **ISSUE-015**: critérios próprios nas seções respectivas.

### Próximo passo

1. **Rastreabilidade upstream**: ✅ feito — issue [`mework360-deployer-scripts#23`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/23) aberta em 2026-05-24 com smoking gun em `worker.sh:144-152`, evidência empírica (118 callbacks, 0% exit_code), spec violation explícita (4/11 campos obrigatórios), patch sugerido (`HGET nc:jobs:<id>` para hidratar payload), test plan e critério de fechamento (≥ 95% non-null em janela de 7 dias).
2. **Fast-tracks API**: rotear ISSUE-014 e ISSUE-015 via `/git` (cada um < 1 dia, isolados, sem impacto cross-module).
3. Manter este ticket aberto até o fix upstream rolar para staging e atender o critério de aceite acima.

---

## ISSUE-014 — `JobLogFetcher` SSH fallback falha 100% com exit 101 (`cmd_not_allowed`)

- **Tipo**: bug (mitigação quebrada — observabilidade)
- **Prioridade**: MEDIUM (não bloqueia produção; vira HIGH se upstream começar a omitir `log_tail` por design)
- **Status**: fixed — argv corrigido para introspection `nextcloud-manage job <id> logs|status`; `SshRemoteException(notImplemented)` tratado (2026-05-24)
- **Sprint**: fix avulso (< 1 dia, isolado em `app/Modules/Jobs/Services/JobLogFetcher.php` + testes)
- **Origem**: descoberto durante investigação Fase 1 de ISSUE-013 (2026-05-24, staging `deployer.mework360.com.br`)
- **Módulos afetados**: `app/Modules/Jobs/Services/JobLogFetcher.php`, `tests/Feature/Jobs/JobLogFetcherTest.php`, `tests/Contract/Jobs/UpstreamJobLogsContractTest.php`
- **Upstream afetado**: nenhum (problema é argv incorreto **nesta** API)
- **Relacionados**: ISSUE-013 (parent), ISSUE-009, ISSUE-006 (mesmo exit 101 = `cmd_not_allowed` no ecossistema)

### Descrição

`WebhookHandler::applyFinishedEvent` invoca `JobLogFetcher::fetch($job, $cluster)` (linha 146) sempre que `WebhookPayload->logTail` é null — o que acontece em **100%** dos `job.finished` (upstream nunca envia `log_tail`, ver ISSUE-013). A chamada falha em **100%** dos casos com exit 101.

### Resultado da investigação (2026-05-24, `/qa debug`)

**Veredito**: causa raiz confirmada — **argv incorreto no `JobLogFetcher`**, não problema de rede/SSH.

**Evidência produção** (`storage/logs/sshclient.log`, job `079bdc3f-50fc-4c51-bb79-599394061817`):

```
command: nextcloud-manage teste2 job 079bdc3f-50fc-4c51-bb79-599394061817 logs --json
exit_code: 101
stdout: {"error":"cmd_not_allowed","cmd":"079bdc3f-50fc-4c51-bb79-599394061817"}
```

**Comparação com código que funciona** — `JobsPollStuckCommand` (linha 45) usa sintaxe de introspection **sem** prefixo de client:

```php
['job', $job->job_id, 'status', '--json']
// → nextcloud-manage job <job_id> status --json  ✅
```

**Contrato upstream** (`docs/SSH API Reference — Nextcloud SaaS.md` §3.4, §5.3–5.4):

```
nextcloud-manage job <job_id> status [--json]
nextcloud-manage job <job_id> logs
```

Comandos `job` são **introspection** — não seguem a sintaxe hierárquica `<client> <namespace> <verb>`.

**Hipóteses descartadas**:

| # | Hipótese | Veredito |
|---|----------|----------|
| 1 | Cluster perdeu conectividade SSH | ❌ SSH funciona para outros comandos no mesmo cluster/host |
| 2 | Path de log mudou no upstream | ❌ Comando nem chega a executar — rejeitado no parser (101) |
| 3 | Regressão no `SshClient` | ❌ `JobsPollStuckCommand` usa o mesmo client com argv correto |

**Bug secundário identificado**: fallback `exit_code=99` nunca dispara com `SshClient` real — o client **lança** `SshRemoteException` (flag `notImplemented=true`) em vez de retornar `SshResponse`. Testes passam porque mockam `SshClientInterface` e retornam response diretamente.

### Fix Brief

```
Fix Brief — ISSUE-014 JobLogFetcher argv incorreto (exit 101 cmd_not_allowed)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Causa raiz: JobLogFetcher monta `nextcloud-manage <client> job <id> logs --json`
            mas comandos `job` são introspection e devem ser `nextcloud-manage job <id> logs`.
            Upstream interpreta o UUID como subcomando inválido → exit 101 cmd_not_allowed.
Tipo: contract_violation (argv diverge do SSH API Reference §3.4)

Arquivos a modificar:
  - app/Modules/Jobs/Services/JobLogFetcher.php — remover $client dos args; tratar SshRemoteException notImplemented
  - tests/Feature/Jobs/JobLogFetcherTest.php — assertar argv sem client slug; teste fallback via exception
  - tests/Contract/Jobs/UpstreamJobLogsContractTest.php — corrigir comentário de contrato

Plano de correção:
  1. Alterar args de `[$client, 'job', ...]` para `['job', $job->job_id, 'logs', '--json']` (logs + status fallback)
  2. Capturar SshRemoteException com notImplemented=true e chamar fetchViaStatus (mesmo argv fix)
  3. Adicionar teste de regressão que simula SshRemoteException(99) em vez de SshResponse
  4. Rodar suite JobLogFetcherTest + validar em staging pós-deploy

Risco: baixo — diff isolado, alinha com JobsPollStuckCommand e SSH API Reference
```

### Critério de aceite

- 100% dos `job.finished` que entram no fallback SSH retornam pelo menos 1 linha de log (`jobs.summary IS NOT NULL`) para jobs com output upstream.
- Teste de regressão em `tests/Feature/Jobs/JobLogFetcherTest.php` cobrindo argv correto + fallback via `SshRemoteException`.
- Log `sshclient` em staging mostra `command: nextcloud-manage job <uuid> logs --json` (sem client slug) com `exit_code: 0`.

### Próximo passo

Fast-track via `/git` — implementar Fix Brief acima (< 1 dia, sem Sprint F).

---

## ISSUE-015 — `WebhookHandler` salva apenas reconstrução parcial em `audit_logs.payload` (não o raw)

- **Tipo**: enhancement (observabilidade — gap de auditoria)
- **Prioridade**: MEDIUM
- **Status**: open — fast-track candidate
- **Sprint**: fix avulso (< 1 dia, isolado em `app/Modules/Jobs/Services/WebhookHandler.php`)
- **Origem**: descoberto durante investigação Fase 1 de ISSUE-013 (2026-05-24)
- **Módulos afetados**: `app/Modules/Jobs/Services/WebhookHandler.php` (linhas 188–202), possivelmente `app/Modules/Jobs/Dto/WebhookPayload.php` (preservar `rawPayload`), `database/migrations/*audit_logs*` se quisermos coluna dedicada
- **Upstream afetado**: nenhum
- **Relacionados**: ISSUE-013 (parent — falha de auditoria comprometeu hipótese inicial da investigação), ISSUE-005 (já tem `Log::debug` com payload em `APP_ENV=local`, mas Monolog **trunca** em ~295 chars com "..." e não chega a staging/prod com `APP_ENV != local`)

### Descrição

`WebhookHandler::applyFinishedEvent` (linhas 188–202) registra em `audit_logs` apenas um subset reconstruído do payload:

```php
'payload' => [
    'event'       => $payload->event,
    'state'       => $canonical,
    'cmd'         => $payload->cmd ?? $job->job_type,
    'exit_code'   => $payload->exitCode,
    'duration_ms' => $payload->durationMs,
],
```

Faltam: `job_id`, `client`, `started_at`, `finished_at`, `log_tail`, `ts`, `schema_version` e qualquer campo futuro adicionado pelo upstream. Consequência:

1. Investigação remota tipo P-05 fica cega — não dá para distinguir "upstream omitiu chave" de "upstream emitiu null" sem o raw.
2. Qualquer mudança contratual no upstream (campos novos, tipos diferentes) passa despercebida na auditoria.
3. `Log::debug('webhook.payload_received')` (`VerifyWebhookHmac` linha 104) **só roda em `APP_ENV=local`** e o Monolog default trunca a linha em 295 chars (verificado: linhas terminam em `...` literal). Não é fonte confiável.

### Caminho de fix proposto

Em `WebhookHandler::applyFinishedEvent`:

```php
$updates['payload'] = [
    'event'       => $payload->event,
    'state'       => $canonical,
    'cmd'         => $payload->cmd ?? $job->job_type,
    'exit_code'   => $payload->exitCode,
    'duration_ms' => $payload->durationMs,
    'raw'         => $payload->raw, // payload bruto preservado pelo fromArray
];
```

E em `WebhookPayload::fromArray`, preservar o array original em uma propriedade pública `?array $raw` para uso aqui (e em testes/debug futuro). Eventualmente migrar para uma coluna dedicada `audit_logs.raw_payload JSONB` se o overhead em `payload` JSONB virar problema.

Equivalente em `applyStartedEvent` (job.started também tem `audit_logs` registro).

### Critério de aceite

- `audit_logs.payload->>'raw'` contém o objeto JSON bruto recebido do upstream em todos os `webhook_received` posteriores ao deploy.
- Teste de regressão em `tests/Feature/Jobs/WebhookHandlerTest.php` validando que payload customizado (com chaves não-mapeadas no DTO) é preservado em `raw`.
- Sem regressão em performance — `audit_logs` row deve continuar < 4 KB no caso médio.

### Próximo passo

`/git` direto — fast-track via Fix Brief Lite (escopo trivial, sem impacto cross-module).

---

## ISSUE-012 — 404 sob `/api/*` retorna HTML do Laravel em vez de JSON

- **Tipo**: bug (information leak + DX/contract)
- **Prioridade**: HIGH
- **Status**: closed (Sprint F9 — validação `/qa validar` R1 **APROVADA** 2026-05-24)
- **Sprint**: a alocar (estimado < 1 dia; bom candidato para fast-track / "fix avulso")
- **Origem**: testes dinâmicos API dev (`deployer.mework360.com.br/api`) em 2026-05-21 — P-19 em `docs/PROBLEMAS-ENCONTRADOS.md`
- **Módulos afetados**: `bootstrap/app.php` (handler de exceções)
- **Upstream afetado**: nenhum
- **Relacionados**: P-19 (origem); contraste com 409/422/502/503 que já retornam JSON corretamente

### Descrição

`GET /api/<rota-inexistente>` **sem** `Accept: application/json` retorna `HTTP 404` com **HTML completo** (DOCTYPE + ~30 KB de CSS inline normalize.css/Tailwind + body com `<h1>404</h1>`). Quando o mesmo request envia `Accept: application/json`, o Laravel já responde JSON corretamente.

Apenas o caminho de `NotFoundHttpException` é sensível ao `Accept` header — todos os outros erros da API (`409 Conflict`, `422 Unprocessable Entity`, `502 Bad Gateway`, `503 Service Unavailable`) já retornam JSON independentemente do `Accept` enviado.

### Causa identificada

O Laravel 12 usa o `Accept` header para decidir entre `renderHtmlResponse` e `renderJsonResponse` no handler de exceções padrão. Sem `Accept: application/json` explícito, `NotFoundHttpException` cai no template HTML — incluindo sob `/api/*`. Falta um handler `shouldRenderJsonWhen` em `bootstrap/app.php` que force JSON sempre que `$request->is('api/*')`, **ignorando** o `Accept` header. Clientes HTTP/SDKs frequentemente não enviam `Accept: application/json` por padrão (curl simples, `fetch` sem options, libs HTTP minimalistas), então o bug é observável na prática.

### Consequências

1. **Information leak (HIGH)**: HTML revela stack (Laravel + Tailwind + normalize.css 8.0.1) — útil para fingerprinting de atacante.
2. **Quebra de contrato com clientes**: SDKs/parsers esperam JSON sob `/api/*` e recebem HTML — `JSON.parse` quebra ou mascara o erro real.
3. **Banda desperdiçada**: ~30 KB para responder "rota não existe" em endpoint de API.
4. **Inconsistência de contrato**: dos cinco status de erro testados (404/409/422/502/503), só 404 quebra o contrato JSON.

### Caminho de fix (opção única — escopo mínimo)

Em `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(function (Request $request) {
        return $request->is('api/*') || $request->expectsJson();
    });
})
```

Customizar payload de `NotFoundHttpException` para:

```json
{ "error": "route_not_found", "path": "/api/...", "method": "GET" }
```

### Critério de aceite

- `GET /api/rota-inexistente` **sem** `Accept: application/json` → `HTTP 404` + `Content-Type: application/json` + body `{"error":"route_not_found", ...}` (era HTML).
- `GET /api/rota-inexistente` **com** `Accept: application/json` → idem, sem regressão (já era JSON; deve continuar).
- `MethodNotAllowedHttpException` (e.g. `POST` em rota só `GET`) idem com `error: method_not_allowed`, **independente** do `Accept`.
- Fluxos de UI (web não-API) **não** afetados — handler só dispara para `api/*` ou `expectsJson()`.
- Teste de regressão: `tests/Feature/ApiNotFoundJsonTest.php` cobrindo (a) 404 JSON sob `/api/*` sem `Accept`, (b) 404 JSON sob `/api/*` com `Accept: application/json`, (c) HTML preservado fora de `/api/*`, (d) método inválido.
- Verificar interação com regra "nunca exponha stack traces em respostas de API" — confirmar que payload novo não vaza `trace`/`file` em `APP_DEBUG=true` quando o request é de API.

### Próximo passo

1. Alocar fix avulso (ou anexar à próxima sprint F com capacidade < 1 dia).
2. Validar antes do PR que nenhum dos endpoints listados em `routes/api.php` redefine 404 manualmente (grep por `abort(404`/`response()->json(...,404)` para evitar conflito de contrato).

---

## ISSUE-011 — Diagnóstico errado embutido no código sobre falhas OCC (allowlist vs. "flag stripping")

- **Tipo**: postmortem (BUG — knowledge debt em comentários + diagnóstico de causa raiz upstream)
- **Prioridade**: CRITICAL (entendimento errado da causa raiz; bloqueia P-10/P-17 indiretamente; induz manutenção a investigar o lugar errado)
- **Status**: implemented — **validação APROVADA (R1)** (2026-05-23, opção A — Decision #ARCH-6, branch `fix/issue-011-occ-allowlist-comments`)
- **Decision**: `docs/DECISION-BRIEF.md` `#ARCH-6`
- **Sprint**: fix mínimo autocontido (sem sprint formal — escopo limitado à correção de diagnóstico). P-10/P-17 e coordenação upstream permanecem em aberto.
- **Origem**: testes dinâmicos API dev (`deployer.mework360.com.br/api`) em 2026-05-21
- **Módulos afetados**: `app/Http/Controllers/Api/OccController.php`, `docs/SETUP-DECISIONS.md`, `docs/openapi.yaml`, referência SSH
- **Upstream afetado**: `nextcloud-saas-manager` (`occ-exec` subcmd allowlist)
- **Relacionados**: P-09 (SUPERSEDED), P-10 (bloqueado por este), P-16 (exit 16 não documentado), P-17 (5 endpoints quebrados em prod)

### Descrição

Quatro comentários no `OccController` (linhas 42, 56–57/59–60, 67, 95, 105/108–109) afirmam que o upstream `nextcloud-manage dispatch.sh` filtra/remove `--flags` de subcmds OCC, e propõem workarounds positional. **Esse diagnóstico é falso** e foi refutado empiricamente: `maintenance:mode on` (argv positional puro, **sem nenhuma flag**) também falha com `exit_code: 16`. A causa real é uma **allowlist de subcmds em `nextcloud-manage <client> occ-exec`** no upstream — não tem relação com flags.

### Evidência empírica (matriz P-15)

| Subcmd OCC | Tem `--flag`? | Resultado | Conclusão |
|---|---|---|---|
| `user:list` | não | ✅ HTTP 200, exit 0 | dentro da allowlist |
| `app:enable calendar` | não | ✅ HTTP 200, exit 0 | dentro da allowlist |
| `files:scan admin` | não | ✅ HTTP 200, exit 0 | dentro da allowlist |
| `maintenance:mode on` (positional) | **não** | ❌ HTTP 502, exit 16 | **fora da allowlist — refuta "stripping"** |
| `user:setting admin files quota 5 GB` | não | ❌ HTTP 502, exit 16 | fora da allowlist |
| `config:app:set files default_quota 3 GB` | não | ❌ HTTP 502, exit 16 | fora da allowlist |
| `theming:config name "X"` | não | ❌ HTTP 502, exit 16 | fora da allowlist |

**Allowlist deduzida (parcial)**:
- ✅ Permitidos: `user:list`, `user:add`, `user:resetpassword`, `app:enable`, `files:scan` (e provavelmente `app:disable`, `app:list`).
- ❌ Bloqueados (exit 16): `user:setting`, `config:app:set`, `theming:config`, `maintenance:mode`.

### Consequências

1. **Knowledge debt no código**: futuros mantenedores (humanos ou IAs) seguem a pista falsa "implementar flag passthrough no dispatch.sh" e perdem ciclo investigando o lugar errado.
2. **Roadmap upstream desalinhado**: o comentário "Pending upstream fix in `nextcloud-manage dispatch.sh`: pass non-global `--flags` to occ-exec" está mirando alvo errado.
3. **Mensagens ao cliente enganosas**: respostas 502 com `error: upstream_dispatch_limitation` falam de stripping em vez de allowlist.
4. **P-10 oculto**: bug do argv multi-key em `setBranding` não pode ser validado enquanto allowlist não expandir.
5. **P-17 oculto**: 5 endpoints publicados em `routes/api.php` permanentemente 502 com diagnóstico errado.

### Arquivos suspeitos (escopo de descoberta — **não plano de implementação**)

| Arquivo | Sintoma |
|---|---|
| `app/Http/Controllers/Api/OccController.php` (linhas 42, 56–60, 67, 95, 105–109) | 4 comentários afirmando "flag stripping" + 2 respostas com `error: upstream_dispatch_limitation` |
| `app/Http/Controllers/Api/OccController.php::runOcc` (≈146–154) | Exit 16 cai em `default → 502 upstream_error`, perdendo semântica de allowlist (P-16) |
| `docs/SETUP-DECISIONS.md` | Falta decisão registrada sobre `occ-exec` ter allowlist e estratégia adotada |
| `docs/openapi.yaml` | Responses 502 nos endpoints OCC mutativos sem distinguir `subcmd_not_allowed` |
| Referência SSH (§4.11 / §8) | Allowlist oficial e exit code 16 não documentados |

### Caminhos de fix (a decidir em `/qa debug` + Architect — **não planejar agora**)

| Opção | Escopo | Trade-off |
|---|---|---|
| **A — Correção de comentários + mapeamento de exit code** (mínimo) | Reescrever os 4 comentários do `OccController`; adicionar mapeamento `exit_code 16 → HTTP 403 occ_subcmd_not_allowed`; atualizar `docs/SETUP-DECISIONS.md` com a allowlist deduzida | Barato, melhora observabilidade e knowledge base; **não resolve P-17** (endpoints continuam quebrados, mas com erro honesto) |
| **B — Coordenar com upstream** (paralelo) | Pedir ao mantenedor do `nextcloud-saas-manager` a allowlist oficial + decisão sobre expandir para incluir `user:setting`, `config:app:set`, `theming:config`, `maintenance:mode` | Resolve P-10 e P-17 se aceito; depende de upstream |
| **C — Refatorar gateway** | Fazer o `nextcloud-saas-manager` expor um caminho alternativo (ex.: `occ-direct` com lista própria controlada pela API) ou rotas de domínio (`branding set`, `quota default`, `maintenance toggle`) que cubram os casos quebrados sem expor `occ-exec` cru | Maior; demanda design upstream |
| **D — Despublicar endpoints quebrados** | Remover ou marcar como `503 deprecated` os 5 endpoints OCC mutativos enquanto B/C não chegam | UX honesta; perde feature publicada |

### Critério de aceite (mínimo, opção A)

- Nenhum comentário no `OccController` afirma "flag stripping"; cada subcmd com risco de allowlist tem comentário factual citando exit 16 + referência a esta issue.
- Exit 16 mapeado para 403 `occ_subcmd_not_allowed` (ou outro contrato discutido) com mensagem que cita a allowlist.
- `docs/SETUP-DECISIONS.md` registra a allowlist deduzida e a estratégia (manter endpoints com 403 vs. despublicar).
- Allowlist oficial obtida do upstream **ou** issue upstream aberta com a matriz P-15 anexada.

### Próximo passo

1. ✅ **Opção A implementada** (2026-05-23, branch `fix/issue-011-occ-allowlist-comments`):
   - 4 comentários falsos do `OccController` reescritos com referência a ISSUE-011 + allowlist.
   - `runOcc()` mapeia `exit_code 16` → HTTP 403 `occ_subcmd_not_allowed` com `subcmd` no payload.
   - Erros 501 renomeados: `upstream_dispatch_limitation` → `occ_subcmd_not_supported` (quota/all) / `occ_bulk_not_supported` (files-rescan sem username).
   - Decision `#ARCH-6` em `docs/DECISION-BRIEF.md`.
   - 21 testes passam em `OccControllerTest` (4 novos, 1 atualizado, 1 de regressão de texto).
   - `OccController::toggleMaintenance` alinhado com `OccPanel`: argv canônico `--on`/`--off` (antes positional `on`/`off` por workaround falso de P-09).
2. ✅ **Issue upstream aberta** (2026-05-23): [`SoftwareBeesy/mework360-deployer-scripts#22`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/22) — anexada matriz P-15, pede confirmação da allowlist oficial, documentação de `exit_code 16` em `SSH API Reference §8` e decisão entre expandir allowlist vs. expor verbos de domínio dedicados (alternativa D em `#ARCH-6`).
3. **Pendente**: atualizar `OpenAPI` com response 403 `occ_subcmd_not_allowed` para os endpoints OCC mutativos (follow-up).
4. ✅ **API alinhada com OccPanel** (2026-05-23): `toggleMaintenance` passa `--on`/`--off` via SSH (argv canônico OCC; ver REQUIREMENTS §6.6).

### Notas

- Esta issue **substitui** a teoria de P-09 (deprecated). Comentários do código que apontam para "P-09" ou "dispatch.sh strip" devem ser removidos junto com a correção, não preservados como histórico.
- P-10 fica **bloqueado por esta issue** até a allowlist permitir `theming:config` ou existir caminho alternativo.

---

## ISSUE-010 — Callback `provision success` prematuro; tenant não ready para operações de usuário

- **Tipo**: postmortem (BUG — causa raiz)
- **Prioridade**: CRITICAL
- **Status**: implemented — **validação APROVADA (R1)**
- **Sprint**: F8 (gerada `/fix` 2026-05-23)
- **Registrado em**: 2026-05-23 (triagem de P-21 em `docs/PROBLEMAS-ENCONTRADOS.md`)
- **Origem**: testes dinâmicos API dev (`deployer.mework360.com.br`) + correlação queue 2026-05-21
- **Módulos afetados**: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Modules/Customers/Actions/LifecycleAsyncAction.php`, `app/Http/Controllers/Api/CustomerLifecycleController.php`
- **Upstream afetado**: `nextcloud-saas-manager` (timing do callback `create` / provision)
- **Relacionados**: P-01 (sintoma), P-05 (observabilidade zero em falhas), P-22 (saga de onboarding — solução de produto)

### Descrição

O upstream `nextcloud-saas-manager` emite callback `state=success` para o job `create` (provision) **antes** do tenant Nextcloud estar funcionalmente pronto para operações no subsistema de usuários (`user:add`, `user:remove`). A API, ao receber o webhook, propaga imediatamente `Customer.status = active` (`WebhookHandler` linha 164), sinalizando ao cliente que o tenant está operacional.

Há uma **janela de readiness de ~10 minutos** pós-callback em que `users:create` e `users:delete` falham silenciosamente (job `state=failed`, sem `exit_code`/`summary` — ver P-05). `groups:create` e `apps:enable` funcionam na mesma janela, coerente com a `SSH API Reference §4.1.7`: Redis/Collabora/14 apps ainda configurando enquanto o core install já concluiu.

### Evidência empírica

| Métrica | Resultado |
|---|---|
| Δt provision → `users:create` **< 10 min** | **5/5 failed** |
| Δt provision → `users:create` **> 30 min** | **8/8 success** |
| Mesmo argv/código/upstream | Falha só muda com tempo (refuta hipótese argv/stdin de P-03/P-04) |

Exemplo circunstancial (`qa-test-1779378939`, ~2 min pós-provision): `groups:create` ✅, `apps:enable` ✅, `users:delete` ❌, `users:create` ❌.

### Causa raiz (confirmada)

1. **Upstream**: callback emitido após passo ~6 (core install + admin), antes dos passos 7–9 (Redis, Collabora, 14 apps, allowlists).
2. **API**: `WebhookHandler` trata `provision + success` como sinal definitivo de readiness — sem probe nem estado intermediário.

### Arquivos suspeitos

| Arquivo | Papel |
|---|---|
| `app/Modules/Jobs/Services/WebhookHandler.php:161-173` | Propaga `active` imediatamente em `provision success` |
| `app/Modules/Customers/Actions/LifecycleAsyncAction.php` | Dispatch sem gate de readiness do tenant |
| `app/Http/Controllers/Api/CustomerLifecycleController.php` | Endpoints `users/*` aceitam dispatch em tenant recém-provisionado |
| `tests/Feature/Jobs/WebhookHandlerTest.php:96-115` | Teste codifica comportamento atual (provision success → active) |
| `database/migrations/2026_05_08_000004_create_customers_table.php` | Enum `status` não tem estado `provisioning_finishing` |

### Reprodução

1. `POST /customers` → aguardar `GET /queue/{job_id}` com `state=success` (provision).
2. **Imediatamente** (≤10 min): `POST /customers/{slug}/users` → HTTP 202, job termina `state=failed` em ~1–4s, sem motivo (`exit_code=null`, `summary=null`).
3. Repetir após ≥30 min no mesmo tenant → `state=success`, user visível via `user:list`.

### Caminhos de fix (a decidir em `/qa debug` + Architect)

| Opção | Escopo | Trade-off |
|---|---|---|
| **A — Upstream** | `nextcloud-saas-manager` só emite success após passos 7–9 + health probe | Causa raiz correta; depende de deploy upstream |
| **B — API readiness gate** | Novo estado `provisioning_finishing`; probe periódico (`occ status` / `user:list`); endpoints `users/*` retornam **503 + Retry-After** até ready | Defensivo; desacopla cliente do timing upstream (**recomendado**) |
| **C — Retry inteligente** | Reagendar jobs `users:*` com backoff se tenant <15 min | Mitigação local; não resolve contrato `active` enganoso |
| **D — Documentar só** | OpenAPI/README avisa janela | Pior UX; transfere complexidade ao consumidor |

**Não recomendado**: trocar para `occ-exec user:add` síncrono — não resolve readiness e quebra contrato 202.

### Critério de aceite (mínimo)

- Cliente que faz `provision → users:create` sequencial **não recebe failed silencioso** dentro da janela de readiness.
- `Customer.status` reflete readiness real (ou endpoints retornam 503 explícito com `Retry-After` até ready).
- Teste de contrato ou feature reproduz janela (mock ou opt-in upstream).
- Issue upstream aberta se opção A for perseguida em paralelo.

### Próximo passo

`/pmo sprint F8` para executar Fix Brief (opção B — readiness gate). Issue upstream (opção A) em paralelo recomendada.

### Fix Brief (2026-05-23 — `/qa debug` inline, pendente aprovação)

```
Fix Brief — Callback provision success prematuro (ISSUE-010)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Causa raiz: Upstream emite success após passo 6 (core install, §4.1);
             passos 7–9 (Redis/Collabora/14 apps) continuam async.
             API propaga `Customer.status=active` imediatamente (WebhookHandler:164),
             enganando consumidores sobre readiness real do subsistema users.

Tipo: contract_violation (upstream timing) + product_bug (API não protege cliente)

Hipótese confirmada: H1 race readiness — refutadas H2 argv/stdin (P-03/P-04)
                     e H1 original de ISSUE-006 (mesmo argv funciona após 30 min).

Arquivos a modificar:
  - app/Modules/Jobs/Services/WebhookHandler.php:164 — provision success → `provisioning_finishing` (não `active`)
  - database/migrations/* — aceitar novo status OU reutilizar `provisioning` + coluna `readiness_verified_at`
  - app/Modules/Customers/Services/CustomerReadinessProbe.php (novo) — probe via `occ-exec user:list`
  - app/Jobs/ProbeCustomerReadinessJob.php ou Console/Commands (novo) — retry com backoff pós-webhook
  - app/Modules/Customers/Actions/LifecycleAsyncAction.php — gate `users:create|users:delete` se !ready
  - app/Modules/Customers/Exceptions/TenantNotReadyException.php (novo)
  - app/Http/Controllers/Api/CustomerLifecycleController.php — 503 + Retry-After: 60
  - tests/Feature/Jobs/WebhookHandlerTest.php — atualizar expectativa de status
  - tests/Feature/Customers/LifecycleTest.php — cenário tenant not ready → 503
  - docs/openapi.yaml — response `tenant_not_ready` (503)
  - docs/DECISION-BRIEF.md — Decision #ARCH-5 (readiness gate)

Plano de correcao (opção B — recomendada):
  1. TDD: teste webhook provision success → status `provisioning_finishing` (não `active`)
  2. Probe service: `occ-exec user:list` exit 0 → transiciona para `active`
  3. Gate em LifecycleAsyncAction só para `users:create|users:delete` (groups/apps seguem liberados)
  4. Controller mapeia TenantNotReadyException → 503 `{error: tenant_not_ready, retry_after: 60}`
  5. Documentar janela na OpenAPI; abrir issue upstream (opção A) em paralelo

Paralelo upstream (opção A): issue no nextcloud-saas-manager para emitir success
  somente após passos 7–9 + health funcional — não bloqueia fix defensivo na API.

Risco: MEDIO — muda semântica de `Customer.status`; UI/Livewire que assume
       `active` imediato precisa exibir `provisioning_finishing`; sync cron
       (CustomerSyncService) pode conflitar se upstream reportar `running` antes
       do probe local — validar ordem de precedência.
```

**Notas de investigacao**:
- **Padroes comparados**: `groups:create`/`apps:enable` OK na janela; `users:*` falha — subsistema users estabiliza depois.
- **Codigo atual**: `LifecycleAsyncAction` so valida `cluster.status === active`, nunca `customer.status`.
- **Evidencias**: matriz Δt em P-01/P-21; SSH API Ref §4.1 passos 7–9 pos-callback.
- **Workaround atual**: aguardar 30+ min entre provision e user ops.

---

## ISSUE-001 — Sincronizar webhook secret com o upstream via SSH

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-05-18
- **Revisado em**: 2026-05-18 (2ª revisão de design)
- **Solicitante**: operador (dev)
- **Módulos afetados**: `app/Http/Livewire/ClusterServers/`, `app/Modules/ClusterServers/Actions/`

### Descrição

O `webhook_secret` gerado pela API (e armazenado criptografado em `cluster_servers.webhook_secret_encrypted`) nunca é comunicado ao upstream `nextcloud-saas-manager`. O upstream usa esse secret para assinar os callbacks HMAC-SHA256 — sem sincronização, a validação de HMAC jamais funcionará sem configuração manual.

**Design:** Sempre que um ClusterServer for criado ou o webhook secret for rotacionado, chamar o comando SSH `nextcloud-manage config set-webhook-secret --payload-stdin` passando o secret plain via stdin (JSON). O upstream armazena o secret e passa a assinar os webhooks com ele.

Nota: `webhook_secret` e `webhook_token` são o mesmo conceito neste sistema.

### Critério de aceite

- Criar ClusterServer → chama SSH `config set-webhook-secret`; se SSH falhar, cluster fica com `status='error'` e Livewire exibe erro (sem redirect)
- Rotacionar secret → chama SSH `config set-webhook-secret` com novo secret; se SSH falhar, grace period garante continuidade + log de segurança + audit
- Secret passado via `--payload-stdin` (nunca como arg CLI, per regra do ssh-orchestrator)
- `SyncWebhookSecretAction` encapsula o SSH call — reutilizado em Create e Rotate
- 225+ testes passando; CI verde

---

## ISSUE-002 — Webhook 401 quando worker upstream não recarrega secret novo

- **Tipo**: postmortem
- **Prioridade**: HIGH
- **Status**: mitigated (upstream PR pendente)
- **Registrado em**: 2026-05-20
- **Cluster afetado**: `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`)
- **Módulos afetados**: `app/Modules/ClusterServers/Actions/SyncWebhookSecretAction.php` (sem alteração nesta API), `mework360-deployer-scripts/scripts/lib/config_admin.sh` (fix upstream)

### Sintoma

Após criação do cluster `homolog` em 2026-05-20 00:18:21 UTC com `SyncWebhookSecretAction` (PR #26 já em produção), todo callback do upstream retornava 401 `invalid_signature`:

```
mework360-deployer-nginx | "POST /api/jobs/hook?cluster=119d74df-... HTTP/1.0" 401
```

`audit_logs.action='webhook_invalid_signature'` confirmou HMAC mismatch (não era `unknown_cluster` nem replay).

### Causa raiz

O comando SSH `nextcloud-manage config set-webhook-secret --payload-stdin` (executado pelo `SyncWebhookSecretAction` no upstream) escrevia o novo secret em `/opt/shared-services/secrets/worker_callback_secret` (exit 0 ✓), mas o **worker daemon** (`nextcloud-saas-worker.service`) lê o secret via `LoadCredential` do systemd:

```ini
LoadCredential=callback_secret:/opt/shared-services/secrets/worker_callback_secret
```

Essa diretiva faz uma cópia **congelada** do arquivo em `/run/credentials/<service>/callback_secret` na partida do serviço. Como `_read_callback_secret()` no `worker.sh` sempre encontra `$CREDENTIALS_DIRECTORY` setado quando rodando via systemd, ele lê da cópia congelada — não do arquivo atualizado. Resultado: worker continua assinando callbacks com o secret carregado no boot anterior, enquanto a API espera o secret novo.

### Mitigação imediata (aplicada 2026-05-20 00:58:17 UTC)

```bash
ssh mecloud360@dev.mework360.com.br "sudo systemctl restart nextcloud-saas-worker"
```

Validado por comparação SHA-256: secret no banco da API e secret carregado pelo worker (em `/run/credentials/nextcloud-saas-worker.service/callback_secret`) agora idênticos (`af87c327...ce90`).

### Fix duradouro (em `mework360-deployer-scripts`)

Branch `rr/fix/webhook-secret-reload-worker` modifica `cmd_config_set_webhook_secret` (em `scripts/lib/config_admin.sh`) para executar `systemctl try-restart nextcloud-saas-worker` ao final, reportando o resultado em `worker_reload` (`restarted` / `skipped` / `failed`). Após merge + deploy do upstream, qualquer rotação subsequente vai funcionar sem intervenção manual.

### Lições aprendidas

1. **Operações que mutam configuração consumida por daemons devem incluir o reload do consumidor.** O upstream sabia disso — a mensagem dizia "reinicie o worker" — mas delegava ao caller, que não tinha como saber que isso era obrigatório (era só uma string em JSON).
2. **`systemd LoadCredential` não tem hot-reload nativo.** Sempre que um serviço usa `LoadCredential`, o produtor do credential precisa garantir o restart. Documentar essa constraint em qualquer ADR que defina credenciais via systemd.
3. **Validação E2E pós-criação não cobriu callback HMAC.** O happy path foi `criar cluster → status=active → smoke-test SSH ping`. Faltou um teste que dispara um job real e valida que o callback chega 204. Considerar feature de smoke-test pós-criação que faça um job dummy e valide o webhook round-trip.

### Critério de aceite (fix duradouro)

- PR no `mework360-deployer-scripts` mergeado e deployed em produção
- Próxima rotação de secret via UI da API: webhook chega 204 no primeiro disparo, sem restart manual
- Output JSON do `set-webhook-secret` inclui `worker_reload="restarted"`

---

## ISSUE-003 — Webhook 422 + dedupe-em-falha mascara jobs travados em queued

- **Tipo**: postmortem
- **Prioridade**: HIGH
- **Status**: fixed in API (upstream issue #15 aberta)
- **Registrado em**: 2026-05-20
- **Cluster afetado**: `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`)
- **Jobs travados (reprocessados manualmente)**: `9b200bcb-0ce9-478b-9ca8-a63d05237afd`, `98f44c15-4dde-47a2-8305-41a9db9ef320`, `18c6d4d4-6dc6-489f-bcd3-f2347ffd589c`
- **Módulos afetados**: `app/Modules/Core/Translators/StateTranslator.php`, `app/Http/Middleware/VerifyWebhookHmac.php`

### Sintoma

Logo após o ISSUE-002 ser mitigado (HMAC voltou a bater), os webhooks passaram a chegar mas o painel não atualizava o estado dos jobs. Logs:

```
01:43:13 +0000 "POST /api/jobs/hook?cluster=119d74df-... HTTP/1.0" 422 0
01:43:18 +0000 "POST /api/jobs/hook?cluster=119d74df-... HTTP/1.0" 204 0
```

Body 0 no 422 (não `{"error":"..."}`) — vinha do controller, não do middleware. O 204 imediatamente depois era enganoso: vinha do dedupe do middleware.

### Causa raiz (dois bugs convergindo)

**Bug A — Vocabulário desalinhado**: `worker.sh` (upstream) emite `state="finished"` no callback HMAC quando `exit_code=0`. Mas `StateTranslator::MAP` na nossa API só conhecia `'done' => 'success'` (per docstring `nextcloud-manage §5.2`). Resultado: `UnknownStateException` → controller responde `response('', 422)`.

**Bug B — Dedupe persistido antes do controller**: `VerifyWebhookHmac` chamava `Cache::put($dedupeKey, true, ...)` ANTES de `$next($request)`. Quando o controller falhava (com 422 do bug A, ou qualquer 4xx/5xx no futuro), o retry seguinte do upstream batia o cache e recebia 204 fake — silenciando o problema. O job ficava preso para sempre no estado anterior (queued/running) na nossa API, mesmo o upstream tendo terminado.

A combinação dos dois bugs é insidiosa: o operador via webhooks "chegando" (logs com 204) mas jobs nunca atualizando — sem nenhum 4xx visível após a primeira tentativa.

### Mitigação imediata + fix permanente

1. **Reprocessamento manual dos 3 jobs travados** (2026-05-20 ~01:55 UTC): consultado o estado canônico no Redis upstream (db=15, `nc:jobs:<id>`), atualizado manualmente o banco da API com `state=success|failed`, `exit_code`, `finished_at`, `customer.status`, e `audit_logs.action='webhook_received_manual_replay'` para rastreabilidade.

2. **Fix A** em `app/Modules/Core/Translators/StateTranslator.php`: adicionado `'finished' => 'success'` no MAP, mantendo `'done' => 'success'` por compatibilidade. Comentário documenta a discrepância docstring vs impl real do upstream.

3. **Fix B** em `app/Http/Middleware/VerifyWebhookHmac.php`: dedupe key agora é persistida APENAS quando `$response->getStatusCode() < 300`. Em qualquer 4xx/5xx, o cache fica vazio para que o retry do upstream possa fazer uma nova tentativa real.

4. **Issue upstream**: [`SoftwareBeesy/mework360-deployer-scripts#15`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/15) — alinhar docs com impl real (define `finished` ou ajustar worker.sh para emitir `done`).

### Critério de aceite

- ✓ `StateTranslator` aceita `finished` e `done` (testes em `tests/Unit/Core/StateTranslatorTest.php`)
- ✓ Dedupe não persiste em respostas 4xx/5xx (regression test em `tests/Feature/Api/WebhookReceiveTest.php`: "controller falha com 422 NÃO seta dedupe — retry com payload corrigido sucede")
- ✓ 244/244 testes passando localmente
- ✓ 3 jobs travados reprocessados manualmente; `customer.status` propagado
- ☐ Próximo callback orgânico com `state=finished` chega 204 e atualiza o job (validação pós-deploy)

### Lições aprendidas

1. **Idempotência baseada em "request received" é diferente de "request processed"**. A semântica do dedupe deve refletir "este job já foi processado com sucesso" — caso contrário, falhas transitórias do consumidor e bugs do produtor ficam silenciados em retries que recebem 204.
2. **Documentação de contratos não é fonte-de-verdade automática.** O `StateTranslator` foi codificado a partir de uma docstring (`§5.2: queued, running, done, failed, cancelled`) que não estava alinhada com a implementação real do worker. Sugestão: adicionar teste end-to-end no upstream que valida exatamente o vocabulário emitido por `_fire_callback`, OU gerar o `MAP` automaticamente a partir de um arquivo de contrato compartilhado entre os dois repositórios.
3. **Falsos 204 são tóxicos.** Mais perigoso que um 5xx visível, porque o operador acredita que está tudo OK. Considerar adicionar telemetria que alerta quando jobs ficam em `queued/running` por mais de N minutos sem callback de transição (independente do que o webhook receiver retornou).

---

## ISSUE-004 — Webhook receiver aceita `event=job.started` + dedupe per `(job_id, event)`

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: implemented
- **Registrado em**: 2026-05-20
- **Solicitante**: upstream sprint (mework360-deployer-scripts — expansão aditiva schema_version="1")
- **Módulos afetados**: `app/Modules/Jobs/Dto/WebhookPayload.php`, `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Http/Middleware/VerifyWebhookHmac.php`, `tests/Feature/Api/WebhookReceiveTest.php`

### Contexto

A sprint do `mework360-deployer-scripts` introduziu callbacks de transição: o worker passa a emitir **dois** webhooks por job — um `job.started` (na transição queued→running) e um `job.finished` (na transição running→terminal). Antes, só havia um callback terminal. A mudança é aditiva: `schema_version` permanece `"1"`, mas o payload ganha o campo `event` e o enum de `state` ganha `running`.

### Mudanças no contrato (vindas do upstream)

1. `event ∈ {"job.started", "job.finished"}` no payload (antes inexistente).
2. `state` passa a aceitar `"running"` (antes só `done|finished|failed|cancelled`).
3. `finished_at`, `exit_code` e `duration_ms` **ausentes** quando `event=job.started`.
4. Workers reiniciados podem reenviar `(job_id, job.started)` — o consumer precisa deduplicar por `(job_id, event)`, não apenas por `job_id`.

### Decisões de implementação

- **`WebhookPayload` DTO**: `event` opcional na entrada (default `job.finished` para retro-compatibilidade com workers pré-expansão); `startedAt`, `finishedAt`, `exitCode`, `durationMs` nulláveis; `ts` usado como fallback para `started_at`/`finished_at` dependendo do evento (como o upstream já faz em `_fire_callback`).
- **Dedupe per evento**: chave passa de `webhook_processed:{job_id}` para `webhook_processed:{job_id}:{event}`. O dedupe continua persistido apenas em respostas `< 300` (mantém o fix do ISSUE-003).
- **Replay window**: trocado o ancoramento de `finished_at` para `ts` (sempre presente nos callbacks de transição), com `finished_at` como fallback para workers legados. O fallback para `now()` foi mantido apenas como último recurso, para não desabilitar silenciosamente a janela de replay.
- **`WebhookHandler` ramifica por evento**:
  - `job.started`: seta `state=running` e `started_at` (apenas se ainda nulo); **não** atualiza `finished_at`, `exit_code` ou `customer.status`.
  - `job.finished`: comportamento atual completo (estado terminal, `finished_at`, `exit_code`, propagação Customer).
- **Guarda contra regressão de estado terminal**: se um `job.started` chega DEPOIS de um `job.finished` (out-of-order causado por retry tardio do upstream), o handler retorna 204 silenciosamente em vez de reverter para `running`.
- **Validação de `event`**: valores desconhecidos retornam 422 `invalid_event` no middleware (antes do dedupe), evitando chave de cache com evento espúrio.

### Critério de aceite

- ✓ Payload com `event=job.started` + `state=running` → 204 + job atualizado para `running` + `started_at` setado, sem mexer em `finished_at`/`exit_code`/`customer.status`.
- ✓ Payload com `event=job.finished` → fluxo terminal completo (igual ao anterior).
- ✓ Sequência `(job_id, job.started)` seguida de `(job_id, job.finished)` → ambos 204 + job converge para `success`.
- ✓ Retry de `(job_id, job.started)` após worker restart → 204 idempotente, `started_at` original preservado.
- ✓ Payload sem campo `event` (legacy worker) → 204 tratado como `job.finished`.
- ✓ Payload com `event` desconhecido → 422 `invalid_event`, sem persistir dedupe.
- ✓ Out-of-order (`job.started` chegando após terminal) → 204 sem regredir o estado.
- ✓ 250/250 testes passando (244 anteriores + 6 novos cenários de webhook).

### Observações operacionais

- A coluna `duration_ms` **não** foi adicionada ao modelo `Job` neste change request — o campo é tolerado e registrado no `AuditLog.payload` para forense, mas não consumido no domínio. Se virar requisito de UX (ex.: exibir duração em `/queue/{job_id}`), uma migration aditiva pode ser criada sem impacto na lógica do receiver.
- Backwards compatibility: o release pode ser deployed **antes** do upstream começar a emitir `event` — payloads legados (sem `event`) continuam funcionando exatamente como antes.

---

## ISSUE-005 — Webhook receiver loga payload em nível debug quando APP_ENV=local

- **Tipo**: change_request
- **Prioridade**: LOW
- **Status**: implemented
- **Registrado em**: 2026-05-20
- **Solicitante**: operador (dev)
- **Módulos afetados**: `app/Http/Middleware/VerifyWebhookHmac.php`, `tests/Feature/Middleware/VerifyWebhookHmacTest.php`

### Descrição

Postmortems ISSUE-002 e ISSUE-003 expuseram um gap de observabilidade: quando o webhook receiver rejeita ou silencia callbacks (replay, dedupe, state desconhecido), o desenvolvedor não vê o payload bruto — só AuditLog estruturado. Investigar "por que esse job não atualizou?" exigia tcpdump ou middleware ad-hoc.

**Design:** `Log::debug('webhook.payload_received', [...])` em `VerifyWebhookHmac` guardado por `app()->environment('local')` — gate mais restritivo que `APP_DEBUG`. Nunca dispara em staging (mesmo com `APP_DEBUG=true`) nem em produção. Posicionado após HMAC + struct + event-enum (só loga payload autêntico) e ANTES de replay/dedupe (loga inclusive os rejeitados como duplicados/replay — exatamente os casos mais úteis para forense).

### Critério de aceite

- ✓ `APP_ENV=local` → `Log::debug('webhook.payload_received', {cluster_server_id, ip, event, payload})`
- ✓ `APP_ENV=testing|staging|production` → nenhum log debug emitido
- ✓ Falha do canal de log não converte HTTP em 500 (try/catch + report seguindo padrão de `securityLog()`)
- ✓ NÃO loga: rate-limit (429), unknown_cluster (401), invalid_signature (401), invalid_payload (422) — esses já têm AuditLog próprio
- ✓ Testes pareados local/testing em `VerifyWebhookHmacTest`
- ✓ 46/46 testes da suite de webhook passando

### Observações de segurança

- Payload do webhook NÃO contém segredo (HMAC signature está no header `X-Signature`, não no body)
- Gate por `APP_ENV=local` é mais restritivo que `APP_DEBUG` — staging com debug ativo NÃO loga
- SEC-N1-008 trata do PEM em `/livewire/update`, não deste endpoint

---

## ISSUE-006 — Lifecycle async manda vocabulário canônico-API ao upstream + duplica `--async --json`

- **Tipo**: postmortem
- **Prioridade**: HIGH
- **Status**: open (Fix Brief aprovado — aguarda `/fix` → Sprint F)
- **Registrado em**: 2026-05-20
- **Reportado por**: `/qa debug` sobre log de produção do cluster `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`, host `dev.mework360.com.br`)
- **Módulos afetados**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php`, `app/Modules/Core/Translators/JobTypeTranslator.php`, `app/Modules/Core/Ssh/SshClient.php`, `tests/Feature/Customers/LifecycleTest.php`, `tests/Unit/Core/JobTypeTranslatorTest.php`

### Sintoma

Tentativa de criar usuário pelo OccPanel (Livewire) ou pelo `POST /api/customers/{c}/users` falha com upstream retornando `exit 101 / cmd_not_allowed`. Log:

```
local.DEBUG: SSH command executed {
  "command":"nextcloud-manage teste5 users:create joao.silva joao.silva@example.com --async --json --idempotency-key=d212a0b4-08a7-44dc-93ab-a1e35c973b35 --callback=https://deployer.mework360.com.br/api/jobs/hook?cluster=119d74df-... --payload-stdin --async --json",
  "exit_code":101,
  "stdout":"{\"error\":\"cmd_not_allowed\",\"cmd\":\"joao.silva\"}"
}
```

A feature inteira de lifecycle async de usuários/grupos/apps (`OccPanel::createUser/deleteUser/createGroup/deleteGroup/addUserToGroup`, `CustomerLifecycleController::createUser/deleteUser/createGroup/deleteGroup/addUserToGroup/removeUserFromGroup/enableApps/disableApps`) está quebrada.

### Causa raiz

Dois bugs interagindo — um arquitetural, um mecânico.

**Bug A — terceiro vocabulário (CLI argv upstream) sem tradutor** (arquitetural)

O sistema tem três vocabulários distintos que precisam de tradução, mas só dois estão implementados:

| Vocabulário | Onde vive | Exemplo | Tradutor |
|---|---|---|---|
| API canônica (`cmd`) | `Job.cmd_canonical`, `IdempotencyKey.cmd`, AuditLog | `users:create` | — (é o vocabulário "raiz") |
| `job_type` | `Job.job_type`, webhook payloads | `user_create` | `JobTypeTranslator::cmdToJobType()` |
| **CLI argv upstream** | argv passado ao `nextcloud-manage` | **`user create`** (namespace hierárquico `user` + verb `create`, per `SSH API Reference §3.3`; §14 lista `user-create` com hífen mas isso é inconsistência da doc — o real é com espaço, confirmado via SSH em `mecloud360@MECloud360-NextCloud-SaaS-01`: `nextcloud-manage teste5 user create --async` → `{"error":"missing_username","message":"user create requer <username>"}`) | **AUSENTE** |

`LifecycleAsyncAction::execute()` injeta o `$cmd` canônico (`users:create`) diretamente no argv:

```php
$sshArgs = array_merge(
    [$customer->slug, $cmd],   // ← $cmd vai cru para o argv
    $args,
    ['--async', '--json', "--idempotency-key={$idempotencyKey}", "--callback={$callbackUrl}"],
);
```

Como `users:create` não é verb async upstream válido (per §14 só aceita `user-create`, `user-remove`, `user-modify`, `group-create`, `group-remove`, `group-modify`, `apps-enable`, `apps-disable`), o parser do upstream sobe um nível e interpreta o próximo token (`joao.silva`) como subcomando → `cmd_not_allowed`.

O `docs/ROADMAP.md` linha 2421 chegou a antecipar isso (`...explode(' ', $cmd)` para cmds multi-palavra), mas a implementação descartou o split e o tradutor argv nunca foi criado.

**Bug B — `--async --json` duplicado no argv** (mecânico)

- `LifecycleAsyncAction::execute()` linhas 70-72 adicionam `'--async', '--json'`.
- `SshClient::runAsync()` linha 69 também faz `array_merge($args, ['--async', '--json'])`.

`ProvisionCustomerAction` (linha 112) e `RemoveCustomerAction` (linha 55) seguem o contrato correto e têm comentário "runAsync appends --async --json automatically". `LifecycleAsyncAction` quebrou esse contrato e ninguém percebeu porque o upstream falhava antes no Bug A.

**Por que os testes não pegaram**

`tests/Feature/Customers/LifecycleTest.php:59,155,179,337,373,410` asserta `in_array('users:create', $args, true)` — ou seja, valida exatamente o vocabulário canônico-API estar no argv, justamente o comportamento bugado. Nenhum teste compara argv contra o que o upstream realmente aceita.

### Critério de aceite

- Criar/deletar usuário via OccPanel ou `POST/DELETE /api/customers/{c}/users` resulta em job upstream enfileirado (`exit 0` + `job_id`), não `cmd_not_allowed`
- Criar/deletar grupo, adicionar/remover usuário do grupo, enable/disable apps idem
- `JobTypeTranslator` ganha método `cmdToCliArgv(string $cmd): array<string>` cobrindo os 8 verbs async lifecycle + verbs estruturais (`create`/`remove`/etc se aplicável)
- `LifecycleAsyncAction` usa o tradutor (substituindo `[$customer->slug, $cmd]` por `[$customer->slug, ...$translator->cmdToCliArgv($cmd)]`) **e** remove o `'--async', '--json'` manual (delegado a `SshClient::runAsync`)
- Assinatura argv upstream confirmada por teste manual contra `dev.mework360.com.br` (cluster `homolog`) antes de codificar o mapping
- Testes de `LifecycleTest` reescritos para asserir o argv **upstream-correto** (`user-create`, etc.) e ausência de `--async --json` duplicado
- `JobTypeTranslatorTest` ganha cobertura dos pares cmd→argv
- `docs/SETUP-DECISIONS.md` registra a decisão sobre o terceiro vocabulário
- `.cursor/skills/vocabulary-translator/SKILL.md` documenta o terceiro vocabulário e seu tradutor
- 230+ testes passando; CI verde

### Decisões aprovadas (via Fix Brief)

1. **Tradutor**: expandir `JobTypeTranslator` com `cmdToCliArgv()` (sem criar classe nova).
2. **Assinatura upstream**: capturar via SSH (`ncsaas-api@dev.mework360.com.br`) antes de codificar mapping.
3. **Email/groups em `createUser`**: decidir após confirmar assinatura upstream — Sprint F gather.

### Descobertas via SSH (`mecloud360@MECloud360-NextCloud-SaaS-01`, upstream v12.3.0)

`nextcloud-manage --help` confirma sintaxe hierárquica (`§3.3` da SSH API Reference é a verdadeira; `§14` está desatualizada):

```
nextcloud-manage <cliente> user   create|remove|modify [--async] [--payload-stdin]
nextcloud-manage <cliente> group  create|remove|modify [--async]
nextcloud-manage <cliente> apps   enable|disable [--async]
nextcloud-manage <cliente> occ-exec <subcmd> [args]
```

**Mapping consolidado (FINAL):**

| API canônica | CLI argv upstream | Status | Args/flags |
|---|---|---|---|
| `users:create` | `['user', 'create']` | ✅ pronto | `<username>` positional + `--payload-stdin` `{password, email?, groups?}` |
| `users:delete` | `['user', 'remove']` | ✅ pronto | `<username>` positional (NÃO `user delete`) |
| `groups:create` | `['group', 'create']` | ✅ pronto | `<groupname>` positional |
| `groups:delete` | `['group', 'remove']` | ✅ pronto | `<groupname>` positional (NÃO `group delete`) |
| `groups:add` | **— bloqueado upstream —** | ❌ não existe | retornar 501 até upstream D3/D4 entregar |
| `groups:remove` | **— bloqueado upstream —** | ❌ não existe | retornar 501 até upstream D3/D4 entregar |
| `apps:enable` | `['apps', 'enable']` | ✅ pronto | `<apps_csv>` positional (CSV nativo!) |
| `apps:disable` | `['apps', 'disable']` | ✅ pronto | `<apps_csv>` positional (CSV nativo!) |

### Design points descobertos no probing

**DP1 — `group modify` NÃO faz membership; é rename**

`group modify <groupname> <action> [new_name]` — o campo `new_name` no `args_json` retornado pelo probing denuncia o propósito real (renomear grupo). O upstream aceitou strings arbitrárias como `action` (até `--add-user` virou string posicional) e descartou args extras silenciosamente, criando jobs "queued" que iriam falhar na execução real do worker. **Conclusão**: `groups:add`/`groups:remove` ficam blocked-on-upstream — a API deve retornar `501 not_implemented` explícito.

**DP2 — `apps enable/disable` aceita CSV nativo**

Assinatura: `apps enable <apps_csv>`. O código atual `CustomerLifecycleController::dispatchMulti()` faz loop disparando N jobs (um por app), gerando N round-trips SSH + N rows em `jobs`/`idempotency_keys` e perdendo atomicidade. Sprint F deve consolidar em **um único job** passando o CSV.

**DP3 — `user create` exige `--payload-stdin` (sem positional após username)**

Probing confirma: nenhum positional além de `<username>` é aceito; email/groups precisam ir no JSON do stdin junto com password. Hoje `OccPanel::createUser` e `CustomerLifecycleController::createUser` passam `email` como segundo positional e `--group=X` como flag — falham silenciosamente. Schema do stdin a padronizar: `{password, email?, groups: string[]?}` (validar com upstream se aceita keys além de `password`).

### Riscos descobertos

1. **Upstream em desenvolvimento (D3/D4)** — vários verbs retornam `not_implemented_yet`. Coordenar com `mework360-deployer-scripts` para implementação dos verbs de membership (`group add-user`/`remove-user`) ou definir contrato alternativo.

2. **Documentação upstream desatualizada** — `SSH API Reference §14` lista `user-create` (hífen) que não existe. O real é `user create` (espaço/namespace hierárquico per §3.3). Abrir issue no `mework360-deployer-scripts` para alinhar.

3. **Testes mockam `SshClientInterface` com asserções simétricas ao bug** — `LifecycleTest.php` valida `in_array('users:create', $args)` (argv canônico-API). Os mocks nunca compararam contra contrato upstream real. Sprint F precisa de pelo menos um teste de **contrato/integração** (com flag de skip em CI) que dispare SSH real e valide `exit 0 + job_id`.

### Próximo passo

Executar `/fix` para criar Sprint F com TDD + auditoria HIGH no delta. Escopo recomendado da Sprint F:

- **F1**: Implementar `JobTypeTranslator::cmdToCliArgv()` com mapping fechado acima + exceção `BlockedOnUpstreamException` para `groups:add`/`groups:remove`.
- **F2**: Refatorar `LifecycleAsyncAction::execute()` — usar tradutor + remover `--async/--json` manual (delegação a `SshClient::runAsync`).
- **F3**: Atualizar `CustomerLifecycleController` — `groups:add`/`groups:remove` retornam 501 explícito; `apps:enable`/`apps:disable` consolidam em job único com CSV; `createUser` move email/groups para stdin payload.
- **F4**: Espelhar mudanças no `OccPanel` (Livewire).
- **F5**: Reescrever asserções de teste para argv upstream-correto + adicionar testes de pares cmd→argv no tradutor.
- **F6**: Atualizar `docs/SETUP-DECISIONS.md` (decisão sobre 3º vocabulário) e `.cursor/skills/vocabulary-translator/SKILL.md`.
- **F7** (opcional, atrás de flag): teste de contrato SSH real disparando 1 verb de cada categoria contra cluster `homolog`.

Executor deve usar modelo diferente do diagnosticador (model diversity per framework rule).

---

## ISSUE-007 — E2E browser coverage via Dusk/Playwright

- **Tipo**: change_request
- **Prioridade**: MEDIUM
- **Status**: open (backlog — sprint N-UI dedicada)
- **Registrado em**: 2026-05-20
- **Origem**: spillover de F5 R2 — finding `QA-F5-019` apontou que cobertura de UI Livewire via `Livewire::test()` não exercita o navegador real (HTML parsing, JS Alpine, eventos `wire:submit`/`wire:click`). F5.11 corrigiu o bug em camada de same-path (`wire:model` + `set('userPasswordPlain')` em testes), mas continua faltando proteção contra divergências futuras blade↔componente que só apareçam em browser real.
- **Solicitante**: auditor-qa R2 (gemini-3.1-pro) + auditor-senior R2 (claude-4.6-sonnet-medium-thinking)

### Descrição

A stack de testes do projeto cobre:
- **Unit** (Pest): translators, slug rule, value objects.
- **Feature/HTTP** (Pest + Laravel TestCase): controllers via `$this->get/post`, autorização via Gate, validação.
- **Feature/Livewire** (Pest + Livewire\Livewire): componentes via `Livewire::test()->set()->call()->assert*()`.
- **Contract** (Pest, opt-in `RUN_UPSTREAM_CONTRACT=1`): SSH real contra cluster `homolog`.

Falta uma camada **browser real** (Dusk ou Playwright) para:
1. Validar que `wire:model` e `wire:submit` populam o payload Livewire conforme a view renderizada (não apenas conforme assumimos no teste Livewire).
2. Pegar divergências HTML/CSS/JS que `Livewire::test()` por design não enxerga (ex.: input com `type="password"` sem `wire:model` — exatamente o cenário do bug `QA-F5-019`).
3. Cobrir interações Alpine.js, modais, navegação multi-página (login → dashboard → painel → ação).

### Critério de aceite (proposta para sprint dedicada)

- Instalar `laravel/dusk` (Chrome) **ou** Playwright (Node) — decidir após avaliar custo do container browser no CI/dev.
- 1 teste E2E happy-path por área crítica:
  - Auth: login + redirect ao dashboard.
  - Customers: criar + ver na listagem.
  - **OccPanel/createUser**: digitar senha no campo + click no "Criar Usuário" → job enfileirado e mensagem de sucesso visível (regressão guard sobre `QA-F5-019`).
  - ApiKeys: criar + copiar via clipboard.
  - Operators: criar via convite + aceitar com URL assinada.
- Setup CI: container browser separado (Selenium standalone-chrome ou Playwright official image) com lifecycle apenas para a job `e2e`; não bloquear a job `test`.
- Documentar em `docs/TESTING.md` quando rodar E2E (pre-release vs PR vs branch protected).

### Riscos / não-decisões

- **Custo de manutenção**: testes E2E são frágeis (CSS selectors mudam, animações causam flakiness). Restringir a happy paths críticos; nunca espelhar cobertura unit/feature em E2E.
- **Custo de CI**: container browser adiciona ~30-60s ao pipeline; manter job opcional em PRs e obrigatória em releases.
- **Decisão Dusk vs Playwright**: Dusk integra mais limpo com Laravel (factories + database transactions), Playwright tem melhor DX e suporte cross-browser (Firefox/Safari). Decidir na sprint.

### Próximo passo

Não há próximo passo imediato — esta issue fica em backlog até decisão de roadmap para uma sprint N-UI dedicada (não bloquear sprints F/N atuais).


---

## ISSUE-008 — Fluxo de "Esqueci a senha" para operadores

- **Tipo**: change_request
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-05-21
- **Solicitante**: `/qa debug` (operador)
- **Módulos afetados**: `app/Http/Livewire/Auth/`, `routes/web.php`, `resources/views/livewire/auth/`, `resources/views/emails/`, `config/auth.php`

### Descrição

A tela `/login` (`resources/views/livewire/auth/login.blade.php`) não oferece link "Esqueci minha senha". Verificado por `grep password.request|forgot|recuperar|esqueci`: nenhuma rota, Livewire component ou mailable de password reset existe no código. Apenas a senha trocada manualmente (via `/profile/password`) ou via convite (`AcceptInvite`) está implementada.

### Critério de aceite

- Adicionar rotas `password.request` (GET form), `password.email` (POST submit), `password.reset` (GET form com token), `password.update` (POST submit) — todas dentro do grupo `guest` em `routes/web.php`.
- Criar Livewire `Auth/ForgotPassword` (form com `email`) e `Auth/ResetPassword` (form com `email`, `password`, `password_confirmation`, `token`).
- Usar `Illuminate\Support\Facades\Password` (broker padrão sobre tabela `password_reset_tokens` + provider `operators` já configurado em `config/auth.php`).
- Mailable `OperatorPasswordResetMail` com URL assinada (template em `resources/views/emails/`).
- Link "Esqueci minha senha" em `login.blade.php` abaixo do botão "Entrar".
- Auditar via `AuditLog` (`action=password_reset_requested`, `action=password_reset_completed`).
- Rate-limit `password.email` (3 tentativas / 15 min por IP+email).

### Riscos / decisões

- **Enumeração de e-mail**: usar resposta genérica ("se o e-mail existir, enviaremos instruções") independentemente do resultado de `Password::sendResetLink()`.
- **Operadores `status != active`**: bloquear silenciosamente o envio (mesma resposta genérica), logar em audit como `password_reset_blocked`.

### Próximo passo

Aguardar `/fix` para gerar Sprint F com TDD (Pest Feature tests cobrindo happy path + rate limit + invalid token).

---

## ISSUE-009 — Logs de Job ausentes na tela `queue/{jobId}`

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: mitigated (código — ISSUE-014 / F10.1–F10.2); **validação produção pendente** (ISSUE-023 / ROADMAP F10.3)
- **Registrado em**: 2026-05-21
- **Solicitante**: `/qa debug` (operador)
- **Módulos afetados**: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Modules/Core/Ssh/SshClient.php`, `app/Modules/Jobs/Services/` (novo), `app/Http/Livewire/Jobs/Show.php`

### Descrição

A view `resources/views/livewire/jobs/show.blade.php` renderiza `$logLines` a partir de `Job::$summary` (cast JSON). Confirmado:

- `app/Http/Livewire/Jobs/Show.php::parsedLogLines()` retorna `[]` quando `$job->summary` é vazio.
- `app/Modules/Jobs/Services/WebhookHandler.php` (callback `job.started`/`job.finished`) **nunca** atribui `summary`. Só toca `state`, `started_at`, `finished_at`, `exit_code`, `callback_received_at`.
- `app/Modules/Jobs/Dto/WebhookPayload::fromArray()` sequer lê campo `summary`/`log_tail`/`stdout` — o contrato upstream não envia logs no callback.

Resultado: 100% dos jobs exibem "Nenhum log disponível." em produção/staging.

### Design escolhido

**Pull SSH pós-`job.finished`**: após o `applyFinishedEvent()` persistir o estado terminal, executar via `SshClient` o comando `nextcloud-manage job <job_id> logs --json` no cluster do job, parsear `stdout` e persistir em `jobs.summary` (array JSON). Decisão tomada para evitar dependência de PR upstream e desacoplar logging do canal de callback.

### Critério de aceite

- Novo serviço `App\Modules\Jobs\Services\JobLogFetcher` injetando `SshClientInterface`:
  - método `fetch(Job $job, ClusterServer $cluster): array` retorna lista de linhas (sem nulls/vazios).
  - timeout configurável (`config('services.ssh.log_fetch_timeout_seconds', 15)`).
  - tolera comando ausente / exit_code != 0 → não falha o webhook, só loga em `Log::warning()` com `job_id` e `cluster_id`.
- `WebhookHandler::applyFinishedEvent()` chama `JobLogFetcher` dentro da transação **após** o `update()` do estado terminal, persistindo `summary`. Em estados não-terminais (`running`), não fetcha.
- Idempotência: se `summary` já estiver populado, pular fetch (proteção contra retry de webhook).
- Comando SSH alvo: `nextcloud-manage job <job_id> logs --json`. Esperar JSON array de strings ou objeto `{"lines": [...]}` (ajustar parser conforme contrato real do upstream — validar antes da implementação executando contra cluster `homolog`).
- Audit: incluir `log_lines_count` no payload da entry `webhook_received` em `applyFinishedEvent()`.
- Pest Feature test: webhook `job.finished` → `summary` populada com fixture do `SshClient` mockado.
- Pest Contract test (opt-in `RUN_UPSTREAM_CONTRACT=1`): comando SSH real em cluster `homolog` retorna formato esperado.

### Riscos / decisões

- **Custo SSH por job**: cada `job.finished` agora abre/usa conexão pooled. Latência adicional ~200-800ms — aceitável pois o callback já é assíncrono. Monitorar via `Log::info('job.log_fetch.duration_ms')`.
- **Falha do `nextcloud-manage job logs`**: NÃO deve marcar o job como `failed` — só falha o enriquecimento. View já tolera `summary` vazia.
- **Contrato upstream pendente**: se o subcomando `job <id> logs --json` não existir ainda no `nextcloud-manage`, abrir PR upstream em paralelo (acoplar a ISSUE-001/006).
- **Vazamento de secrets**: logs do upstream podem conter linhas com tokens/senhas. Aplicar sanitização similar a `payload_sanitized` antes de persistir (regex sobre `password=`, `token=`, `--password-stdin`).

### Validação SSH produção (2026-06-02)

- `JobLogFetcher` com argv corrigido está em `main` (`cf773dc` em `deployer.mework360.com.br`).
- Amostra 7d: ainda há job com `summary` null (1/5) — pode ser job antigo, webhook magro (ISSUE-013), ou fetch não executado.
- **Critério de fechamento:** após deploy explícito (F10.3), disparar job novo e confirmar `jobs.summary` populado + UI `/queue/{jobId}` com linhas de log.

### Próximo passo

Executar **ISSUE-023** (smoke prod) antes de considerar ISSUE-009 fechada. Sprint **F6** pode consolidar pull pós-webhook se ainda houver gap.

---

## ISSUE-021 — OpenAPI global desalinhado do formato real de resposta

- **Tipo**: change_request (contrato / documentação)
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-06-02
- **Solicitante**: `/pmo` (síntese arquitetura + skill `api-rest-patterns`)
- **Módulos afetados**: `docs/openapi.yaml`, consumidores REST externos, geradores de cliente
- **Finding relacionado**: `DOC-001` em `docs/FINDINGS.md`

### Descrição

O código da API REST (controllers + `JsonResource`) é a fonte de verdade operacional:

- **Erros:** `{ "error": "<snake_code>", ... }` (ex.: `idempotency_conflict`, `cluster_unreachable`, `tenant_not_ready`).
- **Sucesso:** `CustomerResource` / `JobResource` (objeto na raiz) ou JSON manual (`{ "job_id": "uuid" }` com HTTP 202).
- **Validação Laravel:** 422 no formato padrão `message` + `errors` (não o envelope `error`).

`docs/openapi.yaml` ainda documenta em `info.description` e em vários `components/schemas` o envelope legado:

```json
{ "success": true, "message": "...", "data": {} }
```

`CQ-F5-001` corrigiu drift **pontual** (apps/enable, 501 groups) na v2.1; **não** alinhou o envelope global nem todos os endpoints.

### Critério de aceite

- Remover ou marcar deprecated o envelope `{ success, message, data }` no OpenAPI.
- Documentar `components/schemas` para: `ErrorResponse` (`error` + campos opcionais), resources de sucesso alinhados aos `Http/Resources/*`.
- Documentar exceção 422 (Laravel validation) vs erros de domínio.
- `redocly lint` sem erros; smoke: comparar 3 endpoints (provision 202, conflict 409, 404 JSON) com exemplos no spec.
- Referência interna: `.cursor/skills/api-rest-patterns/references/response-format.md`.

### Próximo passo

Sprint doc-only ou task em sprint N: `/dev doc` ou issue dedicada; não bloqueia runtime se integradores usam código como referência.

---

## ISSUE-022 — Cross-repo: contrato API ↔ mework360-deploy-scripts

- **Tipo**: change_request (coordenação entre repositórios)
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-06-02
- **Solicitante**: `/pmo` + validação SSH produção
- **Repos**: `mework360-deployer-api` (este) + `mework360-deploy-scripts` (upstream / `nextcloud-saas-manager`)

### Escopo (três frentes)

| Frente | Issue / doc existente | Gap |
|--------|----------------------|-----|
| **Webhook payload** | ISSUE-013, upstream [#23](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/23) | `exit_code`, `summary`/`log_tail` ausentes ou null no callback `job.finished` |
| **Branding no create** | ISSUE-019 (API F13 fixed), `docs/HANDOFF-BRANDING-BUG.md` | Upstream `cmd_create_post_extended` pode aplicar logo só via `--staging-id`; stdin `branding.*_data_url` documentado em CONTRACTS mas não implementado no Bash |
| **OCC allowlist / argv** | ISSUE-016, ISSUE-017, ISSUE-011 | `occ-exec` exit 16; quota com espaços no hop SSH `ncsaas-api` |

### Critério de aceite (definição de “fechado”)

1. **Webhook:** worker emite payload conforme `mework360-deploy-scripts/docs/CONTRACTS.md` § callback; smoke em staging com 1 job por verbo; API persiste `exit_code` non-null em jobs novos.
2. **Branding:** provision com logo ≤256KB e >256KB (SFTP) → logo visível no Nextcloud do tenant; ordem de deploy: upstream primeiro, API depois.
3. **OCC:** decisão registrada (Decision `#ARCH-7` ou ISSUE-016): expandir allowlist upstream **ou** despublicar endpoints até suportar.

### Priorização sugerida (PMO 2026-06-02)

1. Webhook (#23) + validação ISSUE-023 (logs na UI).
2. Branding upstream (handoff §7.1) + re-test ISSUE-019 e2e.
3. OCC (ISSUE-017 quota quoting; ISSUE-016 estratégica).

### Próximo passo

Abrir/atualizar issues espelho no repo `mework360-deploy-scripts`; reunião técnica curta com checklist CONTRACTS.md ↔ `ProvisionCustomerAction` ↔ `feature_o_ext.sh`.

---

## ISSUE-023 — Validação LAB pós-F10 + débitos ops schema

- **Tipo**: change_request (validação / DevOps)
- **Prioridade**: MEDIUM
- **Status**: **closed (2026-06-19)** — Sprint **N35** concluída
- **Runbook (LAB):** [F10.3-lab-validation.md](runbooks/F10.3-lab-validation.md) — host `api.lab.mework360.com.br`; tenant canário `qa-platform-lab.lab.mework360.com.br`
- **Runbook legado (deprecado):** [F10.3-prod-validation.md](runbooks/F10.3-prod-validation.md) — host `deployer.mework360.com.br` **descontinuado**
- **Registrado em**: 2026-06-02
- **Atualizado em**: 2026-06-18 — migração deployer → LAB
- **Solicitante**: `/pmo` (validação ops; antes SSH read-only deployer)
- **Módulos afetados**: deploy LAB, `Jobs`, fila Laravel, migrations
- **Sprint ROADMAP**: **F10.3** + **N35**; relacionado **F6**, **F7**
- **Findings relacionados**: `OPS-001` (`failed_jobs`), F7 (`CQ-N1-001/002`, `QA-N1-001`)

### Contexto

Código em `main` (`07f827c`) inclui F10.1–F10.2 (`JobLogFetcher` argv). **Deployer prod descontinuado (2026-06-18).** Autoridade de smoke integração = **LAB** (`api.lab.mework360.com.br`), conforme [`LAB-PROVISION-PLAN.md`](LAB-PROVISION-PLAN.md).

| Check | Resultado histórico (deployer 2026-06-02) | Alvo LAB (N35) |
|-------|-------------------------------------------|----------------|
| `GET …/up` | 200 OK (deployer) | `api.lab.mework360.com.br/up` → 200 |
| Migrations | Todas Ran | Sem Pending pós-deploy LAB |
| Tabela `failed_jobs` | **Ausente** (`OPS-001`) | **Decisão B** — documentado ausência; fila local e-mail; logs em `laravel.log` |
| Job async / `summary` | 1/5 null (7d) | Smoke N35.6 → **POPULATED** (job `1e036c43-…`) |

### Checklist de validação (LAB)

- [x] **N35.5:** Deploy API @ SHA `07f827c` no LAB (`api.lab.mework360.com.br`).
- [x] `cluster_servers` LAB registrado (SSH + webhook secret).
- [x] Disparar 1 job async via **`LifecycleAsyncAction`** (customer `lab`) → terminal `success`.
- [x] Confirmar `jobs.summary` JSON populado no MariaDB LAB.
- [x] Abrir `/queue/{job_id}` no painel LAB → logs visíveis.
- [x] `JobLogFetcher` preencheu `summary` (ISSUE-014).
- [x] **OPS-001:** Decisão B — `failed_jobs` ausente documentado (sem migration no LAB).
- [ ] **F7 (opcional):** smoke cluster LAB + rotate secret.

### Evidência (2026-06-19)

- Job ID: `1e036c43-23f7-4dfa-8d8b-f9a028b2c5b6`
- `FINAL_STATE=success`, `EXIT_CODE=0`, callback webhook 204
- Worker journal: `callback_attempt` success

> Smoke integração exige LAB Fase 3+; agente não faz SSH sem credenciais ops.
