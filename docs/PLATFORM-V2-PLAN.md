# Platform V2 — Plano Mestre (Farm Agent + Integrações Comerciais)

> Gerado em: 2026-06-12  
> Repositório âncora: `work-platform-api` (control plane Laravel)  
> Execução: operador autônomo `/rock` (framework Beesy)  
> Status: **planejado** — aguardando revisão do dono (sem commit)

---

## 1. Visão e contexto

### 1.1 Problema atual (auditoria 2026-06)

| Dimensão | Nota | Risco principal |
|----------|------|-----------------|
| Provisioning | 7/10 | 52 chamadas OCC espalhadas; transporte SSH inbound |
| Upgrades | 5/10 | Blast radius total em `custom-apps update` |
| Isolamento | 4/10 | SEC-004 (compose 0644); MariaDB/Redis/Collabora compartilhados |
| DR/backup | 5/10 | Restore nunca ensaiado |
| Observabilidade | 5/10 | Opt-in no deploy |
| Automação comercial | 2/10 | Sem billing/self-service end-to-end |
| Kits (RC) | 4/10 manutenibilidade | 58 patches; prod = `:latest` patchado via SSH |
| Reprodutibilidade RC | 4/10 | Fonte da verdade é o container, não o Git; 2/21 plugins no kit |

### 1.2 Objetivo Platform V2

Transformar o control plane em orquestrador de **fazendas** (hosts) via **Farm Agent** outbound, com operações tipadas, integração comercial (onboarding + e-mail) e reprodutibilidade de kits — **sem big-bang**, em 3 fases de migração do agente.

### 1.3 Princípios

- **Outbound-only:** agente liga para o control plane (HTTPS + mTLS/token por fazenda); elimina SSH inbound, chaves, shim, sudoers.
- **Contrato JSON tipado:** `tenant.create`, `memail.configure`, `tenant.health`, `custom-apps.update` com progresso por etapa.
- **Um adapter OCC:** retry/timeout/parse centralizado (não 52 pontos).
- **DNA da fazenda:** inventário reportado → placement automático de clientes novos.
- **Preservar o que funciona:** máquina de estados do customer, idempotency keys 24h, callbacks HMAC, fila Redis upstream.
- **Legado congelado:** app ids/DNS em produção não renomeiam in-place; greenfield nasce com nomenclatura `work-*`.

### 1.4 Nomenclatura de repositórios

| Atual | Final | Papel |
|-------|-------|-------|
| `work-platform-api` | (mantém) | Control plane REST + painel |
| `work-platform-scripts` | (mantém) | Bash/worker (absorvido gradualmente pelo agente) |
| *(criar)* | `work-platform-agent` | Daemon por host (Farm Agent) |
| `meApiMail` | `work-mail-api` *(rename futuro)* | Provisioning de caixas Stalwart |
| `me360-fluxo-onboarding-api` | `work-platform-onboarding-api` *(rename futuro)* | Signup, trial, disparo de provisionamento |
| `mework360-roundcube` | `work-rc-kit` | Imagem RC pinada + plugins |

---

## 2. Arquitetura — Farm Agent

### 2.1 Diagrama (texto)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         CONTROL PLANE (work-platform-api)                    │
│  REST /api/customers  │  FarmRegistry  │  PlacementService  │  Webhooks   │
│  AgentGateway (mTLS)  │  Job orchestration (idempotency 24h)               │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │ HTTPS OUTBOUND (agente inicia)
                                │ POST /api/agent/v1/commands (poll long)
                                │ POST /api/agent/v1/events   (progresso)
                                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FARM HOST — work-platform-agent (daemon)                  │
│  CommandRouter ──► OperationHandlers ──► manage.sh adapter (Fase 1)         │
│                 └──► TypedExecutor (Fase 2+) ──► OccAdapter (único)         │
│  InventoryReporter (Fase 3) ──► tenants, versões RC/NC, capacidade          │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │ local exec / docker
                                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│              work-platform-scripts (manage.sh + worker + Redis)              │
│  Fase 1: ainda executa bash por baixo │ Fase 2+: lógica migrada p/ agent   │
└─────────────────────────────────────────────────────────────────────────────┘

Integrações comerciais (paralelo):
  work-platform-onboarding-api ──HTTP Bearer──► work-platform-api
  work-platform-onboarding-api ──WHMCS API──► mecloud360 (pedidos, suspend, billing)
  WHMCS ──gateway Vindi──► pagamento (cartão, boleto, PIX)
  work-platform-api ──HTTP API Key──► work-mail-api (domínios + mailboxes)
  work-platform-api ──HTTP API Key──► PowerDNS Authoritative (zonas + registros DNS)
  WHMCS ──ModuleCreate──► Proxmox (VPS dedicated; leitura read-only via API token)
```

### 2.2 Migração em 3 fases (sem big-bang)

| Fase | Escopo | Critério de saída |
|------|--------|-------------------|
| **1 — Transporte** | Agente fino; por baixo chama `manage.sh`; substitui SSH | 1 fazenda piloto sem SSH inbound; create/remove via agente |
| **2 — Operações tipadas** | Handlers nativos absorvem bash; OCC em um adapter | `tenant.create` e `memail.configure` sem invocar 52 occ diretos |
| **3 — DNA + anéis** | Inventário, placement automático, rollout canário por ring | Novo cliente auto-colocado; `custom-apps.update` por ring |

### 2.3 Contrato JSON — envelope comum

Todas as operações usam o mesmo envelope:

```json
{
  "schema_version": 1,
  "operation_id": "550e8400-e29b-41d4-a716-446655440000",
  "operation": "tenant.create",
  "idempotency_key": "6ba7b810-9dad-11d1-80b4-00c04fd430c8",
  "farm_id": "farm-saas-prod-01",
  "tenant_slug": "acme-corp",
  "payload": {},
  "callback_url": "https://deployer.mework360.com.br/api/jobs/hook",
  "callback_token": "hmac-secret-per-farm",
  "requested_at": "2026-06-12T14:00:00Z",
  "deadline_at": "2026-06-12T14:30:00Z"
}
```

**Resposta de progresso** (agente → control plane):

```json
{
  "operation_id": "550e8400-e29b-41d4-a716-446655440000",
  "state": "running",
  "step": "containers_up",
  "steps_completed": ["db_created"],
  "steps_pending": ["apps_installed", "memail_configured", "ready"],
  "percent": 45,
  "message": "Nextcloud containers healthy",
  "ts": "2026-06-12T14:05:00Z"
}
```

**Estados terminais:** `succeeded` | `failed` | `cancelled`  
**Steps canônicos `tenant.create`:** `accepted` → `db_created` → `containers_up` → `apps_installed` → `memail_configured` → `readiness_probe` → `ready`

### 2.4 Operações tipadas (catálogo v1)

| Operação | Descrição | Fase |
|----------|-----------|------|
| `tenant.create` | Provisiona tenant NC + apps + branding | 2 |
| `tenant.remove` | Remove com backup opcional | 2 |
| `tenant.health` | 8 checks paralelos (espelha `manage.sh health`) | 1 |
| `memail.configure` | `externalLocation`, `forceSSO`, `emailAddressChoice`, disable `mail` (ISSUE-024) | 2 |
| `custom-apps.update` | Update apps privados com `--tenant` / `--ring` | 2 |
| `occ.exec` | Passthrough allowlisted via OccAdapter único | 2 |
| `farm.inventory` | Reporta tenants, versões, capacidade | 3 |
| `dns.zone.provision` | Cria zona PowerDNS + MX/SPF/DMARC/A/CNAME para domínio próprio | N29 |

#### Exemplo `tenant.create` payload

```json
{
  "domain": "acme.example.com",
  "apps": ["mework360_memail", "me360_theme"],
  "branding": {
    "logo_data_url": "data:image/png;base64,...",
    "background_data_url": null
  },
  "admin": {
    "email": "admin@acme.example.com",
    "display_name": "Admin Acme"
  },
  "mail": {
    "provision_domain": true,
    "default_mailbox": "admin@acme.example.com"
  }
}
```

#### Exemplo `memail.configure` payload (ISSUE-024)

```json
{
  "external_location": "https://mail.acme.example.com",
  "force_sso": true,
  "email_address_choice": "primary",
  "disable_core_mail_app": true
}
```

#### Exemplo `custom-apps.update` payload (canary/ring)

```json
{
  "apps": ["mework360_memail", "me360_theme"],
  "ring": "canary",
  "tenant_slugs": ["qa-platform-lab-001"],
  "health_check": true,
  "rollback_on_failure": true
}
```

### 2.5 Fluxo comercial alvo

```
signup (frontend)
  → work-platform-onboarding-api (JWT, trial 7d)
  → WHMCS API: AddOrder (produto/serviço) + gateway Vindi (pagamento)
  → webhook pagamento confirmado (WHMCS hook ou webhook Vindi)
  → work-platform-api: POST /customers + mail provisioning
  → PlacementService escolhe fazenda
  → [domínio próprio] dns.zone.provision (PowerDNS) + DKIM via work-mail-api
  → [domínio próprio] POST /domain/verify (MX/SPF/DKIM/DMARC) antes de ativar
  → work-platform-agent (tenant.create)
  → work-mail-api (domínio + mailbox admin)
  → memail.configure automático (ISSUE-024)
  → ProbeCustomerReadinessJob (gates R4–R8)
  → e-mail boas-vindas (após R6+)
  → cliente ativo sem operador

Inadimplência:
  WHMCS ModuleSuspend → work-platform-api tenant.suspend
  WHMCS ModuleUnsuspend → work-platform-api tenant.resume
```

---

## 3. Integrações

### 3.1 work-mail-api (meApiMail)

- **Quando:** após `containers_up`, antes de `ready`
- **Chamadas:** `POST /v1/domains`, `POST /v1/mailboxes` (escopo `email.domains:manage`)
- **Config:** `MAIL_API_BASE_URL`, `MAIL_API_KEY` no control plane (encrypted)
- **Fecha:** ISSUE-024 parcialmente (domínio/caixa); OCC meMail continua em `memail.configure`

### 3.2 work-platform-onboarding-api

- **Já consome:** `POST /customers`, polling `/queue/{id}`, OCC branding, users
- **Evoluir:** usar placement API; aguardar gates R4–R8 antes de e-mail de boas-vindas; webhook de trial expirado → `tenant.suspend`
- **Env existentes:** `ME360_DEPLOYER_BASE_URL`, `ME360_DEPLOYER_TOKEN`, `ME360_DEPLOYER_CLUSTER_SERVER_ID`

### 3.3 Readiness gates (ProbeCustomerReadinessJob)

| Gate | Verificação | Sprint |
|------|-------------|--------|
| R1 | `mework360_memail` enabled | existente |
| R2 | `me360_theme` enabled | existente |
| R3 | `occ user:list` | existente |
| R4 | `externalLocation` não vazio | N20 |
| R5 | `forceSSO` configurado | N20 |
| R6 | HTTP meMail 200 | N21 |
| R7 | RC shared reachable | N14 |
| R8 | Smoke SSO (ISSUE-031) | N20 |

### 3.4 PowerDNS (DNS / deliverability)

- **O que é:** PowerDNS Authoritative com frontend PowerDNS-Admin em `http://177.11.48.247:8080` (referência da instância; acesso programático via API do pdns auth, porta típica 8081).
- **Como integra:** `PdnsClient` no control plane (`PDNS_API_URL`, `PDNS_API_KEY`); operações `dns.zone.provision` e validação de registros MX/SPF/DKIM/DMARC via `POST /api/customers/{id}/domain/verify`.
- **Sprint:** **N29** (depende de N21 + N23).
- **Pendências:** credenciais PowerDNS fora do repo (env/secrets); rotacionar senha do admin web do PowerDNS-Admin (trafegou em chat); nunca usar login web para automação — somente API key do pdns auth.

### 3.5 WHMCS + Vindi (comercial / cobrança)

- **O que é:** WHMCS 9.0.3 em `store.mecloud360.com.br` (instância mecloud360, skill `/cloud-ops`) + Vindi como gateway de pagamento recorrente (cartão, boleto, PIX) integrado ao WHMCS como módulo de gateway.
- **Como integra:** signup/onboarding cria pedido via WHMCS API (`AddOrder` → `AcceptOrder`); pagamento processado pela Vindi; webhook pagamento confirmado (hook WHMCS ou webhook Vindi) dispara provisionamento no control plane. Suspensão/reativação via `ModuleSuspend`/`ModuleUnsuspend`.
- **Sprint:** **N22**. Regra: operações de escrita comercial (criar pedido, suspender, cancelar) **sempre via WHMCS API** — nunca manipular Vindi ou Proxmox diretamente para isso.
- **Gateway Vindi:** integração Vindi↔WHMCS **não existia**; será construída no repo `work-gateway-whmcs` ([SoftwareBeesy](https://github.com/SoftwareBeesy/work-gateway-whmcs), criado 2026-06-12) — sprints V1/V2 em `docs/PROJECT-PLAN.md`.
- **Provisionamento Nextcloud:** integração Nextcloud↔WHMCS **já existe** — módulo `nextcloudsaas`, fork canônico [SoftwareBeesy/work-nc-whmcs](https://github.com/SoftwareBeesy/work-nc-whmcs) (upstream [defensystechbr/nextcloudsaas-whmcs-module](https://github.com/defensystechbr/nextcloudsaas-whmcs-module) v3.1.4; produtos NextCloud Workspace pid=7, 8; servidores `nextcoloud-saas-01`/`02`).
- **Pendências:** whitelist de IP da API WHMCS para o ambiente de execução (ou IP fixo/VPN) — API rejeitou IP de dev com HTTP 403 Invalid IP; concluir sprints V1/V2 do `work-gateway-whmcs` antes de N22.4.

### 3.6 Proxmox via WHMCS (virtualização)

- **O que é:** cluster Proxmox `IDC-EVEO` (PVE 9.2.2, 4 nodes, Ceph, backups PBS 2×/dia) gerenciado comercialmente via WHMCS — servidor de provisionamento id=2 `ProxmoxVeVpsCloud` (ModulesGarden).
- **Como integra:** provisionamento VPS dedicated via WHMCS API (`AddOrder` → `ModuleCreate`), produto "Máquina Virtual Customizada" (pid=6); inventário/saúde via Proxmox API token read-only (`PVEAuditor`).
- **Sprint:** **N28** (depende de N15 + N23). Padrão `/cloud-ops`: escrita sempre WHMCS; leitura Proxmox read-only; snapshot pré-mudança como **única** escrita direta permitida no Proxmox.
- **Pendências:** mapear config options do pid=6 (CPU 1–32 cores, RAM 1–128 GB, disco 64 GB–10 TB, SO Ubuntu/Debian/Rocky) no contrato do control plane.

---

## 4. Prioridade técnica (ordem aprovada)

1. RC Dockerfile pinado + 21 plugins (`work-rc-kit`) — **N14**
2. SEC-004 + limites CPU/RAM — **N15**
3. Canary/ring `custom-apps update` — **N16**
4. DNS / deliverability (PowerDNS) — **N29**
5. Billing/signup no pipeline (WHMCS + Vindi) — **N22**
6. Port Debian 13 camada de host — **N30**
7. LAB greenfield + BOM — **N25**
8. Restore ensaiado + observabilidade default — **N26, N27**
9. Tier Dedicated (WHMCS/Proxmox) — **N28**

**Farm Agent** (N17–N20, N23–N24) corre em paralelo após N16, intercalado com integrações N21–N22 e N29.

---

## 5. Pré-requisitos antes do bloco V2

| Item | Status | Ação |
|------|--------|------|
| Sprint F3 (tech debt LOW) | pendente | Concluir ou aceitar carry-over documentado |
| Sprint F10.3 (deploy prod ISSUE-023) | pendente | Deploy + smoke `/queue/{id}` |
| Rename repos Onda B | opcional paralelo | `work-platform-api` já renomeado |

---

## 6. Índice de sprints Platform V2

> Categoria: **N** (novas features / transformação)  
> Detalhes completos nas seções §7–§23 abaixo.  
> Sprints com **repo de execução** diferente devem ser abertos no repo indicado pelo operador `/rock`.

| Sprint | Repo execução principal | Objetivo (resumo) | Depende de |
|--------|-------------------------|-------------------|------------|
| N14 | `work-rc-kit` | Dockerfile RC pinado + 21 plugins `me360_*` | — |
| N15 | `work-platform-scripts` | SEC-004 retrofix + CPU/RAM no compose tenant | — |
| N16 | `work-platform-scripts` | Canary/ring em `custom-apps update` | N14 |
| N17 | `work-platform-agent` *(novo)* | Daemon + outbound mTLS + poll comandos | — |
| N18 | `work-platform-api` | FarmRegistry + AgentGateway + feature flag | N17 |
| N19 | `work-platform-agent` + `work-platform-api` | Fase 1 cutover: create/remove sem SSH | N17, N18 |
| N20 | `work-platform-agent` + scripts | Fase 2: `tenant.create` + `memail.configure` tipados | N19 |
| N21 | `work-platform-api` + `meApiMail` | Integração mail no pipeline create | N20 |
| N22 | `me360-fluxo-onboarding-api` + api + WHMCS + `work-gateway-whmcs` | Signup/trial/billing WHMCS+Vindi no pipeline | N21; N29 *(opcional, domínio próprio)*; **V1/V2** `work-gateway-whmcs` *(gateway Vindi)* |
| N23 | `work-platform-api` | Fase 3: inventário + placement automático | N19 |
| N24 | multi-repo | Fase 3: rollout por ring no agente | N16, N23 |
| N30 | `work-platform-scripts` | Port Debian 13 da camada de host (`deploy-server.sh`) | — |
| N25 | `work-platform` *(meta)* + scripts | LAB greenfield + BOM promote | N14, N20, N30 |
| N26 | `work-platform-scripts` | Restore drill mensal automatizado | — |
| N27 | `work-platform-scripts` | Observabilidade default em deploy-server | — |
| N28 | `work-platform-api` + WHMCS + scripts | Tier Dedicated (1 VPS/cliente via WHMCS/Proxmox) | N15, N23 |
| N29 | `work-platform-api` + `meApiMail` | DNS & deliverability (PowerDNS) | N21, N23 |

**Total: 17 sprints**

### Ordem de execução recomendada (`/rock`)

```
F3/F10.3 (carry-over) → N14 → N15 → N16
  → [N30 ∥ N14] (repos diferentes: work-platform-scripts)
  → [N17 ∥ N18] → N19 → N20 → N21
  → N23 → N29 → N22 (fluxo comercial completo com DNS + billing)
  → N30 → N25 (após N14+N20+N30)
  → [N26 ∥ N27] (podem iniciar após N15)
  → N24 → N28
```

**Primeiro comando para o dono:**

```text
/rock Executar Platform V2 a partir de N14 (work-rc-kit): Dockerfile RC pinado + 21 plugins me360_* conforme docs/PLATFORM-V2-PLAN.md sprint N14
```

---

## 7. Sprint N14 — RC reproducível (ISSUE-025)

> **repo de execução:** `work-rc-kit`  
> Categoria: N  
> Gate: imagem `ghcr.io/softwarebeesy/mework360-rc:<semver>` buildável do Git; 21 plugins `me360_*` no repo; CI push tag imutável; sem patch SSH em prod  
> Gerado por: PLATFORM-V2-PLAN 2026-06-12  
> review: senior+qa

| Status | Tam | Tarefa | Skill | Depende |
|--------|-----|--------|-------|---------|
| [x] | M | N14.1 — Dockerfile multi-stage pinado Nextcloud/RC base | docker-setup | — |
| [x] | M | N14.2 — Consolidar 21 plugins `me360_*` no kit (hoje 2/21) | — | N14.1 |
| [x] | P | N14.3 — CI GH Actions: build + push tag semver (sem `:latest` em prod) | ci-automations | N14.2 |
| [x] | P | N14.4 — Documentar tag policy + BOM entry em `releases/` | — | N14.3 |
| [x] | M | N14.5 — Migrar patches críticos de `app.min.js`/Sabre para source versionado | — | N14.2 |

> **Fechado:** 2026-06-12 — repo `work-rc-kit`, branch `rock/n14-close`; BOM `mework360-rc` @ `0.2.8`; 21/21 plugins em `manifest.json`.

### Task N14.1 — Dockerfile pinado

- **Files:** `Dockerfile`, `.dockerignore`, `docker-compose.build.yml`
- **Correção:** bases ARG pinadas (`NC_VERSION`, `RC_VERSION`); layer de plugins copiada do repo
- **Test:** `docker build` local + smoke HTTP 200 no container

### Task N14.2 — 21 plugins consolidados

- **Files:** `plugins/me360_*/`, `manifest.json` ou script de inventário
- **Correção:** inventariar 21 plugins de prod; copiar fonte; eliminar patch ad-hoc onde possível
- **Test:** checklist 21/21 presentes; diff vs container prod atual

### Task N14.3 — CI push tag

- **Files:** `.github/workflows/rc-image.yml`
- **Test:** workflow em branch de teste produz imagem em GHCR com tag `v0.1.0-rc1`

### Task N14.4 — Tag policy

- **Files:** `docs/RELEASE.md`, entrada em `work-platform/releases/platform-*.yaml` (coordenação)
- **Critério:** PROD só referencia tag semver explícita

### Task N14.5 — Patches versionados

- **Files:** `patches/`, `scripts/apply-patches.sh`
- **Critério:** zero edição manual em container running para fluxo greenfield

---

## 8. Sprint N15 — SEC-004 + isolamento de recursos

> **repo de execução:** `work-platform-scripts`  
> Categoria: N  
> Gate: zero compose tenant com mode 0644; template com `mem_limit`/`cpus`; audit script para instâncias existentes  
> review: senior+qa (SEC)

| Status | Tam | Tarefa | Skill | Depende |
|--------|-----|--------|-------|---------|
| [ ] | P | N15.1 — Auditar hosts prod: `find` compose 0644 + relatório | — | — |
| [ ] | P | N15.2 — Confirmar fix SEC-004 (`mktemp`+`chmod 0600`+`mv`) em todas paths de geração | — | — |
| [ ] | M | N15.3 — Defaults CPU/RAM no template docker-compose tenant (ISSUE-029) | — | N15.2 |
| [ ] | P | N15.4 — Script remediação em massa `chmod 0600` + rollback doc | — | N15.1 |
| [ ] | P | N15.5 — Testes regressão create gera compose 0600 | — | N15.2 |

### Task N15.3 — Resource limits

- **Files:** `scripts/manage.sh` (template heredoc), `docs/CONTRACTS.md`
- **Correção:** `deploy.resources.limits` cpus/memory por tier `shared`; env `TENANT_MEM_LIMIT`, `TENANT_CPU_QUOTA`
- **Test:** `create` dry-run → compose contém limits; container respeita cgroup

---

## 9. Sprint N16 — Canary/ring custom-apps

> **repo de execução:** `work-platform-scripts`  
> Categoria: N  
> Gate: `custom-apps update --tenant=X` e `--ring=canary|stable` com health check + rollback automático  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N16.1 — Flags `--tenant`, `--ring`, `--health-check`, `--rollback-on-failure` | — |
| [ ] | M | N16.2 — Ring registry file `/opt/mework360/rings.yaml` | N16.1 |
| [ ] | P | N16.3 — Health hook pós-update (HTTP meMail + occ status) | N16.1 |
| [ ] | P | N16.4 — Testes bats + doc operacional | N16.1–N16.3 |

### Task N16.1 — Flags canary

- **Files:** `scripts/manage.sh`, `scripts/lib/custom_apps.sh`
- **Critério:** update em 1 tenant não afeta demais; falha health → git checkout tag anterior

---

## 10. Sprint N17 — Farm Agent scaffold (Fase 1a)

> **repo de execução:** `work-platform-agent` *(criar repositório)*  
> Categoria: N  
> Gate: daemon sobe via systemd; conecta outbound ao control plane; recebe ping; zero portas inbound  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N17.1 — Scaffold repo Go/Rust (escolha: Go 1.22+) + CI | — |
| [ ] | M | N17.2 — mTLS client + token por `farm_id` | N17.1 |
| [ ] | M | N17.3 — Long-poll `GET /api/agent/v1/commands` | N17.2 |
| [ ] | P | N17.4 — `POST /api/agent/v1/events` heartbeat + progress | N17.3 |
| [ ] | P | N17.5 — Unit tests + `docs/AGENT-SETUP.md` | N17.4 |

### Task N17.3 — Long-poll

- **Files:** `internal/transport/client.go`, `cmd/agent/main.go`
- **Critério:** reconexão exponencial; offline queue local (sqlite) para eventos

---

## 11. Sprint N18 — Control plane AgentGateway (Fase 1b)

> **repo de execução:** `work-platform-api`  
> Categoria: N  
> Gate: CRUD `farm_agents`; rotas agent autenticadas; feature flag `AGENT_TRANSPORT_ENABLED`  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N18.1 — Migration `farm_agents` (farm_id, mTLS cert fingerprint, status) | — |
| [ ] | M | N18.2 — `AgentGatewayController` poll + ack comandos | N18.1 |
| [ ] | P | N18.3 — Vincular `farm_agents` ↔ `cluster_servers` (1:1 MVP) | N18.1 |
| [ ] | M | N18.4 — Feature flag: rotear jobs para agente vs SSH | N18.2, N17 |
| [ ] | P | N18.5 — Feature tests AgentGateway + OpenAPI stub | N18.4 |

### Task N18.1 — Migration

- **Files:** `database/migrations/*_create_farm_agents_table.php`, `app/Models/FarmAgent.php`

---

## 12. Sprint N19 — Cutover transporte SSH → agente (Fase 1)

> **repo de execução:** `work-platform-agent` + `work-platform-api`  
> Categoria: N  
> Gate: 1 fazenda piloto: create + remove via agente; SSH desabilitado no piloto; callbacks HMAC preservados  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N19.1 — Handler `manage.sh` adapter (invoke local `nextcloud-manage`) | N17 |
| [ ] | M | N19.2 — Mapear operation `tenant.create` → `manage.sh create --async` | N19.1 |
| [ ] | P | N19.3 — Mapear `tenant.remove` | N19.1 |
| [ ] | M | N19.4 — Control plane: `SshClient` bypass quando flag + farm online | N18.4 |
| [ ] | P | N19.5 — Runbook cutover + rollback SSH | N19.4 |

---

## 13. Sprint N20 — Operações tipadas Fase 2

> **repo de execução:** `work-platform-agent` (+ scripts para deprecação gradual)  
> Categoria: N  
> Gate: `tenant.create` reporta steps; `memail.configure` fecha ISSUE-024; OccAdapter único com retry  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N20.1 — `OccAdapter` único (timeout, retry, parse JSON) | N19 |
| [ ] | M | N20.2 — Handler `tenant.create` nativo (sem 52 occ soltos) | N20.1 |
| [ ] | M | N20.3 — Handler `memail.configure` (ISSUE-024) | N20.1 |
| [ ] | P | N20.4 — Gates R4–R5 no ProbeCustomerReadinessJob | N20.3 |
| [ ] | P | N20.5 — Contract tests JSON schema operações | N20.2 |

### Task N20.3 — memail.configure

- **Files:** `internal/ops/memail_configure.go` (ou equivalente)
- **Critério:** OCC + app config conforme runbook ISSUE-024; idempotente

---

## 14. Sprint N21 — Integração work-mail-api

> **repo de execução:** `work-platform-api` (+ `meApiMail` para contrato)  
> Categoria: N  
> Gate: create provisiona domínio + mailbox admin via API; secrets encrypted; gate R6  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N21.1 — `MailApiClient` service (domains + mailboxes) | — |
| [ ] | M | N21.2 — Hook pós-`containers_up` em provision pipeline | N20, N21.1 |
| [ ] | P | N21.3 — Config `MAIL_API_*` encrypted + health check | N21.1 |
| [ ] | P | N21.4 — ProbeCustomerReadinessJob gate R6 | N21.2 |
| [ ] | P | N21.5 — Feature tests com Http::fake | N21.2 |

### Task N21.1 — MailApiClient

- **Files:** `app/Modules/Mail/Services/MailApiClient.php`, `config/mail_api.php`

---

## 15. Sprint N22 — Pipeline comercial onboarding

> **repo de execução:** `me360-fluxo-onboarding-api` + `work-platform-api` + WHMCS (mecloud360)  
> Categoria: N  
> Gate: signup → WHMCS/Vindi → provision → mail → e-mail boas-vindas só após R6+; trial cron suspend; inadimplência suspend/resume  
> review: senior+qa

**Pré-requisitos (antes de N22.4):**

- Repo **`work-gateway-whmcs`** (SoftwareBeesy) com sprints **V1** (gateway base) e **V2** (recorrência/ciclo de vida) concluídas — ver `work-gateway-whmcs/docs/PROJECT-PLAN.md`.
- Módulo provisionamento Nextcloud **já existente:** `nextcloudsaas`, fork canônico [SoftwareBeesy/work-nc-whmcs](https://github.com/SoftwareBeesy/work-nc-whmcs) (upstream defensystechbr v3.1.4, pid 7/8).
- WHMCS `store.mecloud360.com.br` (9.0.3) acessível via WHMCS API (skill `/cloud-ops`).
- **Whitelist de IP** da API WHMCS para o ambiente de execução (ou IP fixo/VPN) — API rejeitou IP de dev com HTTP 403 Invalid IP.
- Gateway Vindi instalado e configurado no WHMCS via módulo do `work-gateway-whmcs` (cartão, boleto, PIX).

**Regra operacional:** operações de escrita comercial (criar pedido, suspender, cancelar) **sempre via WHMCS API** — nunca manipular Vindi ou Proxmox diretamente para billing/lifecycle comercial (padrão skill `/cloud-ops`).

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N22.1 — Onboarding: aguardar readiness antes de e-mail admin | N21 |
| [ ] | M | N22.2 — Placement API: onboarding passa a não fixar `cluster_server_id` | N23 ou flag |
| [ ] | P | N22.3 — Webhook trial expirado → suspend via API | — |
| [ ] | M | N22.4 — Integração WHMCS: signup cria pedido (`AddOrder` → `AcceptOrder`); pagamento via módulo gateway Vindi do `work-gateway-whmcs` (`modules/gateways/vindi.php` + callback); webhook `bill_paid` → dispara provisionamento no work-platform-api | N22.1; V1 `work-gateway-whmcs` |
| [ ] | M | N22.5 — Inadimplência: WHMCS `ModuleSuspend`/`ModuleUnsuspend` mapeados para `tenant.suspend`/`tenant.resume` no control plane | N22.4 |
| [ ] | P | N22.6 — Integrar módulo `nextcloudsaas` existente (v3.1.4, pid 7/8) ao pipeline work-platform-api — ADR: reuso vs módulo custom | N22.4 |
| [ ] | P | N22.7 — E2E teste fluxo signup + pagamento em staging | N22.1–N22.6; N29 *(opcional, domínio próprio)* |

### Task N22.4 — WHMCS + Vindi no signup

- **Gateway Vindi:** módulo em `work-gateway-whmcs` (`modules/gateways/vindi.php`, `modules/gateways/callback/vindi.php`) — pré-requisito sprint V1
- **Files (onboarding/control plane):** `app/Modules/Billing/Services/WhmcsClient.php`, handlers webhook WHMCS, onboarding-api adapter
- **Config:** `WHMCS_API_URL` (`store.mecloud360.com.br`), `WHMCS_API_IDENTIFIER`, `WHMCS_API_SECRET` (env/secrets — nunca no repo); API key Vindi no admin WHMCS (gateway config)
- **Critério:** `AddOrder` + `AcceptOrder` idempotente; webhook Vindi `bill_paid` marca fatura paga → dispara `POST /customers`; falha de pagamento não provisiona

### Task N22.6 — Produto WHMCS

- **Módulo existente:** `nextcloudsaas` v3.1.4 — fork canônico [SoftwareBeesy/work-nc-whmcs](https://github.com/SoftwareBeesy/work-nc-whmcs) (upstream defensystechbr) — produtos pid 7/8 já configurados
- **Critério:** avaliar integração do módulo existente ao pipeline work-platform-api vs módulo custom; decisão documentada em ADR

### Task N22.5 — Suspend/resume por inadimplência

- **Files:** webhook handler WHMCS → `TenantSuspendAction` / `TenantResumeAction`
- **Critério:** `ModuleSuspend` no WHMCS suspende tenant no control plane; `ModuleUnsuspend` reativa

---

## 16. Sprint N23 — DNA fazenda + placement (Fase 3a)

> **repo de execução:** `work-platform-api`  
> Categoria: N  
> Gate: `farm.inventory` persistido; `PlacementService` escolhe fazenda com capacidade  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N23.1 — Endpoint ingest `farm.inventory` | N19 |
| [ ] | M | N23.2 — `PlacementService` (capacidade, versão RC, latência) | N23.1 |
| [ ] | P | N23.3 — UI painel: visão fazendas + capacidade | N23.1 |
| [ ] | P | N23.4 — Tests placement com farms mock | N23.2 |

---

## 17. Sprint N24 — Rollout por ring no agente (Fase 3b)

> **repo de execução:** `work-platform-agent` + `work-platform-api` + scripts  
> Categoria: N  
> Gate: `custom-apps.update` orquestrado do control plane por ring; rollback automático  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N24.1 — Operation `custom-apps.update` no agente | N16, N20 |
| [ ] | M | N24.2 — Control plane UI/API disparar ring update | N24.1 |
| [ ] | P | N24.3 — Audit log promote + ring + operator | N24.2 |
| [ ] | P | N24.4 — Drill: canary 1 tenant → stable full | N24.2 |

---

## 18. Sprint N25 — LAB greenfield + BOM

> **repo de execução:** `work-platform` *(meta-repo)* + `work-platform-scripts`  
> Categoria: N  
> Gate: cluster `lab` no painel; tenant canário `qa-platform-lab-001` readiness R8; BOM `requires_lab_signoff`  
> review: comprehensive  
> **Runbook de execução:** [`docs/LAB-PROVISION-PLAN.md`](./LAB-PROVISION-PLAN.md) — provisionamento fim a fim via `/cloud-ops` (cliente `me360-work`, VM pid=6 Debian 13, DNS `lab.mework360.com.br`)  
> **Dependência:** sprint **N30** (port Debian `deploy-server.sh`) deve estar concluída antes do bootstrap host (Fase 3 do runbook).

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N25.1 — Criar `work-platform` com `releases/platform-1.0.0.yaml` | N14 |
| [ ] | M | N25.2 — Bootstrap VPS LAB Debian 13 (runbook `LAB-PROVISION-PLAN` Fase 3–4) | N25.1, N30 |
| [ ] | P | N25.3 — Seed `cluster_servers` lab no control plane | N25.2 |
| [ ] | M | N25.4 — Canário + gates 16/16 settings + ISSUE-031 smoke | N20, N25.3 |
| [ ] | P | N25.5 — Pipeline promote LAB→PROD (manual gate) | N25.4 |

---

## 19. Sprint N26 — Restore drill mensal

> **repo de execução:** `work-platform-scripts`  
> Categoria: N  
> Gate: script `restore-drill.sh` + cron mensal + relatório; último drill < 35 dias  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N26.1 — `scripts/restore-drill.sh` (tenant sintético, backup, restore, verify) | — |
| [ ] | P | N26.2 — Systemd timer ou cron + log estruturado | N26.1 |
| [ ] | P | N26.3 — Alerta se drill falhar ou atrasar | N26.2 |
| [ ] | P | N26.4 — Doc `docs/DR-RESTORE-DRILL.md` | N26.1 |

---

## 20. Sprint N27 — Observabilidade default

> **repo de execução:** `work-platform-scripts`  
> Categoria: N  
> Gate: `deploy-server.sh` instala node_exporter + promtail por default; opt-out explícito  
> review: senior+qa

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N27.1 — Bundles observability no deploy-server (ISSUE-036 related) | — |
| [ ] | P | N27.2 — Dashboards Grafana mínimos (worker, RC, push) | N27.1 |
| [ ] | P | N27.3 — Flag `OBSERVABILITY_ENABLED=false` para opt-out | N27.1 |

---

## 21. Sprint N28 — Tier Dedicated

> **repo de execução:** `work-platform-api` + `work-platform-scripts` + WHMCS (mecloud360) + Proxmox  
> Categoria: N  
> Gate: `tier=dedicated` provisiona 1 VPS isolado via WHMCS (`AddOrder` → `ModuleCreate`); inventário/saúde via Proxmox read-only; limites recursos dedicados  
> review: comprehensive

**Ambiente:** cluster Proxmox `IDC-EVEO` (PVE 9.2.2, 4 nodes, Ceph, backups PBS 2×/dia); servidor WHMCS de provisionamento id=2 `ProxmoxVeVpsCloud` (ModulesGarden).

**Padrão operacional:** skill `/cloud-ops` — escrita sempre WHMCS API; leitura/diagnóstico Proxmox API com token read-only (`PVEAuditor`). Nunca criar VPS diretamente no Proxmox para fluxo comercial; snapshot pré-mudança é a **única** escrita direta permitida no Proxmox.

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N28.1 — Model `tier` em customers + placement dedicated farm | N15, N23 |
| [ ] | M | N28.2 — Provisionamento VPS via WHMCS API (`AddOrder` → `ModuleCreate`), produto "Máquina Virtual Customizada" (pid=6; config: CPU 1–32 cores, RAM 1–128 GB, disco 64 GB–10 TB, SO Ubuntu/Debian/Rocky); scripts template single-tenant (sem shared MariaDB) | N28.1 |
| [ ] | M | N28.3 — `ProxmoxClient` read-only (`PVEAuditor`): inventário e saúde do VPS no cluster `IDC-EVEO` | N28.2 |
| [ ] | M | N28.4 — API create aceita `tier: dedicated` (orquestra WHMCS, não Proxmox direto) | N28.1 |
| [ ] | P | N28.5 — Runbook vendas + upsell doc (WHMCS/Proxmox) | N28.4 |
| [ ] | P | N28.6 — Piloto 1 cliente dedicated em staging | N28.2, N28.3 |

### Task N28.2 — WHMCS provisionamento VPS

- **Files:** `app/Modules/Billing/Services/WhmcsClient.php` (reuso N22), dedicated product mapping
- **Config:** produto WHMCS pid=6 no servidor id=2 `ProxmoxVeVpsCloud`; credenciais via env (skill `/cloud-ops`)
- **Critério:** `ModuleCreate` no WHMCS dispara provisionamento no cluster `IDC-EVEO`; sem chamadas write no Proxmox pelo control plane

### Task N28.3 — Proxmox read-only

- **Files:** `app/Modules/Infrastructure/Services/ProxmoxClient.php`
- **Config:** `PROXMOX_API_URL`, `PROXMOX_API_TOKEN` (read-only / `PVEAuditor`)
- **Critério:** status VM, CPU/RAM/disk para painel e health checks; zero create/delete/start/stop — exceto snapshot pré-mudança manual conforme `/cloud-ops`

---

## 22. Sprint N29 — DNS & deliverability (PowerDNS)

> **repo de execução:** `work-platform-api` + `meApiMail`  
> Categoria: N  
> Gate: domínio próprio com zona PowerDNS provisionada; DKIM publicado; `domain/verify` valida MX/SPF/DKIM/DMARC antes de ativar pipeline  
> review: senior+qa

**Nota de segurança:** credenciais do PowerDNS-Admin ficam fora do repo (env/secrets). O acesso programático usa a API key do PowerDNS Authoritative (porta da API do pdns auth, geralmente 8081), **não** o login web do PowerDNS-Admin (`http://177.11.48.247:8080` é referência da instância apenas).

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | M | N29.1 — `PdnsClient` service: cliente HTTP para API PowerDNS Authoritative (`PDNS_API_URL`, `PDNS_API_KEY`); criar zona; criar/atualizar registros (A, CNAME, MX, TXT/SPF, TXT/DKIM, TXT/DMARC); listar zona; config em `config/services.php` | — |
| [ ] | M | N29.2 — Operação `dns.zone.provision`: dado tenant com domínio próprio, cria zona + MX/SPF/DMARC + A/CNAME (`cloud.`, `mail.`, `webmail.`) apontando para fazenda do tenant via `PlacementService` | N29.1, N23 |
| [ ] | M | N29.3 — DKIM: obter chave pública DKIM do Stalwart via work-mail-api; publicar TXT DKIM na zona; se endpoint inexistente, criar `GET /domains/{domain}/dkim` no meApiMail | N29.2, N21 |
| [ ] | P | N29.4 — `POST /api/customers/{id}/domain/verify`: resolve MX/SPF/DKIM/DMARC (DNS lookup) e compara com esperado; domínio só ativa após validação OK; DNS externo retorna lista de registros para criação manual | N29.3 |
| [ ] | P | N29.5 — Feature tests com mock `PdnsClient` + atualização OpenAPI | N29.4 |

### Task N29.1 — PdnsClient

- **Files:** `app/Modules/Dns/Services/PdnsClient.php`, `config/services.php` (`pdns.api_url`, `pdns.api_key`)
- **Critério:** nunca credenciais hardcoded; retry/timeout; erros tipados

### Task N29.2 — dns.zone.provision

- **Files:** handler operação ou action no provision pipeline
- **Critério:** idempotente; subdomínios `cloud.<dominio>`, `mail.<dominio>`, `webmail.<dominio>` resolvem para IP/host da fazenda colocada

### Task N29.3 — DKIM cross-api

- **Files:** `MailApiClient` extension; possível endpoint em `meApiMail`
- **Critério:** registro TXT `_domainkey` publicado na zona PowerDNS após mail-api retornar chave

### Task N29.4 — domain/verify

- **Files:** `DomainVerifyController`, DNS resolver service
- **Critério:** cliente com DNS externo recebe JSON com registros esperados; cliente com zona nossa passa verify automático

---

## 23. Sprint N30 — Port Debian 13 da camada de host

> **repo de execução:** `work-platform-scripts`  
> Categoria: N  
> Gate: `deploy-server.sh` roda limpo em VM Debian 13 (smoke no LAB) e continua funcionando em Ubuntu 24.04 (retrocompatível)  
> review: senior+qa  
> **Contexto:** auditoria de portabilidade (Composer 2.5, 2026-06-12) — camada de host quase distro-agnóstica; bloqueio real ~5 linhas em `deploy-server.sh` + finding CQ-003 (pacotes runtime ausentes).

| Status | Tam | Tarefa | Depende |
|--------|-----|--------|---------|
| [ ] | P | N30.1 — Gate de OS do `deploy-server.sh` (linhas ~85–87): allowlist `ID` (`ubuntu` ou `debian`) em `/etc/os-release`; mensagem de erro atualizada ("Debian 13 / Ubuntu 24.04") | — |
| [ ] | P | N30.2 — Repo Docker por distro (linhas ~230–235): URL GPG e apt line derivadas do `ID` (`linux/debian` ou `linux/ubuntu`) + codename via `VERSION_CODENAME` | N30.1 |
| [ ] | P | N30.3 — Adicionar `git`, `dnsutils`, `uuid-runtime`, `sudo` ao apt install + preseed `iptables-persistent` (finding CQ-003 da auditoria) | N30.1 |
| [ ] | P | N30.4 — Atualizar docs do repo (`README.md`, `docs/INFRASTRUCTURE.md`, `docs/REQUIREMENTS.md`, skill `host-provision.md`): "Debian 13 (preferido) ou Ubuntu 24.04" | N30.1–N30.3 |
| [ ] | P | N30.5 — *(opcional)* `local-vm`: cloud image Debian 13 para paridade do dev local (`scripts/local-vm/lib/common.sh`, `create-vm.sh`) | N30.2 |

### Task N30.1 — Gate de OS

- **Files:** `scripts/deploy-server.sh` (linhas ~85–87, ~9/~31 mensagens)
- **Correção:** substituir `grep -q "Ubuntu"` por allowlist `ID` (`ubuntu|debian`); opcionalmente validar codename `trixie` em Debian
- **Test:** aborta em Rocky/Alpine; aceita Debian 13 e Ubuntu 24.04

### Task N30.2 — Repo Docker por distro

- **Files:** `scripts/deploy-server.sh` (linhas ~230–235)
- **Correção:** branch GPG `linux/debian/gpg` vs `linux/ubuntu/gpg`; apt line `linux/debian` + `$VERSION_CODENAME` no Debian
- **Test:** `apt update` após add repo em VM Debian 13 e Ubuntu 24.04

### Task N30.3 — Pacotes runtime + preseed

- **Files:** `scripts/deploy-server.sh` (bloco apt install ~202–212)
- **Correção:** incluir `git`, `dnsutils`, `uuid-runtime`, `sudo` (usados por `setup-custom-apps.sh`, `manage.sh`/`dig`, `dispatch.sh`/`uuidgen`); preseed debconf `iptables-persistent`
- **Test:** smoke pós-install — `git`, `dig`, `uuidgen`, `sudo` disponíveis; `deploy-server.sh` noninteractive

### Task N30.4 — Documentação

- **Files:** `README.md`, `docs/INFRASTRUCTURE.md`, `docs/REQUIREMENTS.md`, skill `host-provision.md`
- **Critério:** texto unificado "Debian 13 (preferido) ou Ubuntu 24.04"

### Task N30.5 — local-vm Debian *(opcional)*

- **Files:** `scripts/local-vm/lib/common.sh`, `scripts/local-vm/create-vm.sh`
- **Critério:** dev local com paridade Debian 13; baixa prioridade

**Riscos residuais (validar no smoke LAB):** Debian minimal sem `sudo`; prompt debconf `iptables-persistent`; defaults OpenSSH `PermitRootLogin` — drop-ins devem cobrir; validar com `sshd -t`.

---

## 24. Grafo de dependências (V2)

```
N14 ──┬──► N16 ──► N24
      └──► N25

N30 ──► N25

N15 ──┬──► N28
      └──► [N26 ∥ N27]

N17 ──► N18 ──► N19 ──► N20 ──┬──► N21 ──┬──► N29 ──► N22 (domínio próprio)
                               │          └──► N22 (billing; N29 opcional)
                               └──► N25 (gates)

N19 ──► N23 ──┬──► N24
              ├──► N28
              └──► N29
```

---

## 25. Validação `/rock` — checklist por sprint

Sprints Platform V2 no escopo deste plano: **N14–N30** (inclui N29 DNS PowerDNS e N30 port Debian).

Cada sprint ao concluir:

1. Tasks `[x]` no ROADMAP + este documento
2. `php artisan test` / testes do repo de execução verdes
3. `/qa validar` quando `review: senior+qa` ou `comprehensive`
4. Atualizar `.cursorsession` `sprint_atual` / `planning_history`
5. Cross-repo: PR no repo de execução + issue linkada no control plane

---

## 26. Referências

| Documento | Local |
|-----------|-------|
| PLATFORM-FORK-PLAN | `mework360_memail/docs/PLATFORM-FORK-PLAN.md` |
| PLATFORM-STACKS | `mework360_memail/docs/PLATFORM-STACKS.md` |
| REPO-RENAME-PLAN | `mework360_memail/docs/REPO-RENAME-PLAN.md` |
| ISSUE-024 / 025 / 031 | `work-platform-api/docs/ISSUES.md` |
| OpenAPI control plane | `work-platform-api/docs/openapi.yaml` |
| manage.sh contracts | `work-platform-scripts/docs/CONTRACTS.md` |

---

## Changelog

| Data | Versão | Alteração |
|------|--------|-----------|
| 2026-06-12 | 1.4 | Decisão SO LAB: Debian 13 (auditoria de portabilidade Composer 2.5 — ~5 linhas no `deploy-server.sh`); sprint N30 criada (port Debian + CQ-003); N25 depende de N30; `LAB-PROVISION-PLAN.md` atualizado |
| 2026-06-12 | 1.3 | `LAB-PROVISION-PLAN.md` criado (provisionamento via `/cloud-ops`: cliente `me360-work`, VM pid=6, DNS `lab.mework360.com.br` no PowerDNS) |
| 2026-06-12 | 1.2 | NC↔WHMCS localizado (`nextcloudsaas-whmcs-module` v3.1.4); criado repo `work-gateway-whmcs` para gateway Vindi; N22 atualizada (pré-requisitos V1/V2, N22.4 referencia módulo Vindi) |
| 2026-06-12 | 1.1 | Integrações: sprint N29 (DNS PowerDNS — `PdnsClient`, `dns.zone.provision`, DKIM, `domain/verify`); N22 (WHMCS `store.mecloud360.com.br` + Vindi, `AddOrder`→`AcceptOrder`, inadimplência, produto `nextcloudsaas`); N28 (cluster `IDC-EVEO`, pid=6 via WHMCS, Proxmox read-only); seções 3.4–3.6 com pendências, fluxo 2.5, índice §6, grafo §23, checklist §24 |
| 2026-06-12 | 1.0 | Plano mestre inicial — 15 sprints N14–N28 |
