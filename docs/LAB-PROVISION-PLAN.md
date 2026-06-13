# LAB Provision Plan — ambiente greenfield via /cloud-ops

> **Gerado em:** 2026-06-12  
> **Status:** pronto para execução  
> **Executor:** skill `/cloud-ops` + subagentes Composer 2.5 (delegação por fase)  
> **Mecanismo de execução (automatizado):** [`work-platform-scripts/provision/README.md`](../../work-platform-scripts/provision/README.md) — runbook do pipeline `provision.sh --remote` (bifásico S0–S7). Este documento passa a ser a referência **conceitual** (fases/critérios de aceite); a execução real roda pelo pipeline.  
> **Referências:** [`PLATFORM-V2-PLAN.md`](./PLATFORM-V2-PLAN.md) sprint N25 · [`PLATFORM-STACKS.md`](../../../mework360_memail/docs/PLATFORM-STACKS.md) (bootstrap Fase A–D)  
> **Decisão SO (2026-06-12):** LAB greenfield em **Debian 13 (trixie)** — auditoria de portabilidade (Composer 2.5) confirmou camada de host quase distro-agnóstica; esforço estimado 2–4 h (~5 linhas em `deploy-server.sh`). Sprint **N30** bloqueante para Fase 3.

---

## 1. Objetivo e resultado esperado

Provisionar um **LAB greenfield completo** — do pedido comercial WHMCS até tenant canário validado — sem tocar hosts legados (`dev.mework360.com.br`, `cloud.mework360.com.br`).

**Fluxo resumido:**

```text
WHMCS cliente me360-work → VM pid=6 (Proxmox IDC-EVEO)
  → DNS lab.mework360.com.br (PowerDNS)
  → bootstrap stack (work-platform-scripts)
  → Roundcube pinado (work-rc-kit)
  → tenant qa-platform-lab + gates R1–R8
  → BOM lab-baseline + observabilidade
```

### Entregáveis por fase

| Fase | Entregável | Critério de aceite |
|------|------------|-------------------|
| 0 | Pré-checks OK | WHMCS API, PowerDNS API, Proxmox read-only, repos clonados |
| 1 | VM Active no WHMCS | `GetClientsProducts` status Active; sid/vmid/IP capturados |
| 2 | DNS resolvendo | `lab` e `*.lab` A → IP público; dig/Resolve-DnsName OK |
| 3 | Host base healthy | Traefik + 8 shared ●; custom_apps; worker; cluster registrado |
| 4 | RC pinado healthy | Imagem taggeada (nunca `:latest`); 21 plugins `me360_*` |
| 5 | Canário R8 | Tenant `qa-platform-lab` active; gates R1–R8 PASS; smoke SSO |
| 6 | Fechamento | BOM baseline; PBS backup OK; OPERATIONS.md completo |

---

## 2. Fase 0 — Pré-checks (CONSULTAR, read-only)

> **Modo:** somente leitura. Registrar resultados na [§12 Tabela de outputs](#12-tabela-de-outputs-preencher-durante-execução).

| Status | Tarefa | Verificação |
|--------|--------|-------------|
| [ ] | WireGuard ativo | Saída WHMCS API via IP `177.104.164.190` (whitelist) |
| [ ] | WHMCS API acessível | `GetHealthStatus` ou `GetProducts` em `store.mecloud360.com.br` |
| [ ] | Credenciais WHMCS carregadas | `WHMCS_API_*` em `~/.config/beesy/cloud.env` (não logar valores) |
| [ ] | PowerDNS API acessível | `GET /api/v1/servers/localhost/zones` com `PDNS_API_URL` + `PDNS_API_KEY` |
| [ ] | Zona `mework360.com.br` existe | Serial `2026060801` ou superior; tipo Native |
| [ ] | Capacidade cluster Proxmox | Token read-only `PVEAuditor`: nodes com folga (80–112 cores, 252–378 GB RAM) |
| [ ] | Cliente `me360-work` — idempotência | `GetClients` search `me360-work` — anotar `client_id` se já existir |
| [ ] | gh auth + clones work-* | `work-platform-scripts`, `work-rc-kit`, `work-platform-api`, `work-nc-memail`, `work-nc-theme` |
| [ ] | Sprint N14 tag RC disponível | Verificar tag semver em GHCR; se ausente → bloqueio Fase 4 (tag provisória documentada) |
| [ ] | Sprint N30 concluída (port Debian `deploy-server.sh`) | Gate **bloqueante** para Fase 3 — `deploy-server.sh` deve aceitar Debian 13 + retrocompat Ubuntu 24.04 |

**Guardrails /cloud-ops:** nenhuma escrita nesta fase. Toda operação subsequente → entrada em `OPERATIONS.md` (repo de execução ou `docs/OPERATIONS.md` do projeto ativo).

---

## 3. Fase 1 — Cliente e VM (PROVISIONAR via WHMCS)

> **Modo:** escrita **somente via WHMCS API** (`AddClient` → `AddOrder` → `AcceptOrder` → `ModuleCreate`). **Nunca** criar VM direto no Proxmox.

### 3.1 Cliente interno

| Status | Tarefa | Detalhe |
|--------|--------|---------|
| [ ] | Verificar cliente existente | `GetClients` search `me360-work` — pular `AddClient` se `client_id` já conhecido |
| [ ] | `AddClient` (se necessário) | `companyname`: **ME360 — Work Platform**; e-mail administrativo interno; conta interna/colaborador (sem cobrança real) |
| [ ] | Registrar `client_id` | Preencher §12 |

### 3.2 Pedido VM customizada (pid=6)

| Status | Tarefa | Valor |
|--------|--------|-------|
| [ ] | `AddOrder` pid=**6** | Produto "Máquina Virtual Customizada", servidor provisionamento id=**2** `ProxmoxVeVpsCloud` |
| [ ] | Config: CPU | **8 vCPU** |
| [ ] | Config: RAM | **32 GB** |
| [ ] | Config: disco | **300 GB** |
| [ ] | Config: SO | **Debian 13** (catálogo WHMCS pid=6: Debian 12/13) |
| [ ] | Config: IPv4 | **1** público |
| [ ] | Config: Private Network | **ON** |
| [ ] | Justificativa sizing | Traefik + 8 shared services + RC + worker + tenants `qa-*` + observabilidade; cluster IDC-EVEO com folga |

**Sizing note:** spec acima supera mínimo documentado em PLATFORM-STACKS (8 vCPU / 16 GB) para margem de canários e observabilidade.

### 3.3 Ativação e provisionamento

| Status | Tarefa | Detalhe |
|--------|--------|---------|
| [ ] | `AcceptOrder` | Pedido interno; fatura zerada/colaborador — **aprovação humana** se política exigir |
| [ ] | `ModuleCreate` | Aguardar task Proxmox concluir (poll `GetClientsProducts`) |
| [ ] | Capturar outputs | `sid`, `vmid`, IP público, IP privado (`172.16.101.x`), node Proxmox |
| [ ] | Confirmar Active | `GetClientsProducts` status **Active** |
| [ ] | OPERATIONS.md | Registrar cada chamada API (timestamp, action, ids — sem secrets) |

**Credenciais iniciais:** root entregue pelo módulo WHMCS (`GetClientsProducts` / e-mail do produto) — usar apenas para bootstrap SSH; trocar por chave imediatamente na Fase 3.

---

## 4. Fase 2 — DNS (PowerDNS API, automatizado)

> **Zona existente:** `mework360.com.br.` (não criar zona nova).  
> **TTL bootstrap:** `300` (facilita rollback).  
> **Ordem crítica:** DNS **antes** do Traefik/Let's Encrypt na Fase 3.

Variáveis: `PDNS_API_URL`, `PDNS_API_KEY` em `~/.config/beesy/cloud.env`.

### 4.1 Registros obrigatórios (bootstrap)

| Status | Registro | Tipo | Conteúdo |
|--------|----------|------|----------|
| [ ] | `lab.mework360.com.br` | A | IP público da VM (Fase 1) |
| [ ] | `*.lab.mework360.com.br` | A | Mesmo IP público (wildcard: `cloud.lab`, `webmail.lab`, `api.lab`, tenants `qa-*.lab`) |

### 4.2 Registros mail (preparação — entrega completa sprint N29/N21)

| Status | Registro | Tipo | Conteúdo |
|--------|----------|------|----------|
| [ ] | `mail.lab.mework360.com.br` | A | IP público da VM |
| [ ] | `lab.mework360.com.br` | MX | `10 mail.lab.mework360.com.br` |
| [ ] | `lab.mework360.com.br` | TXT (SPF) | `v=spf1 a:mail.lab.mework360.com.br -all` |
| [ ] | `_dmarc.lab.mework360.com.br` | TXT | `v=DMARC1; p=none; rua=mailto:dmarc@lab.mework360.com.br` |
| [ ] | DKIM | TXT | Publicar **após** Stalwart configurado (sprint N29/N21) — não bloquear bootstrap |

**Decisão E1 (fechada):** hostname canônico LAB = **`lab.mework360.com.br`**.

### 4.3 Validação DNS

| Status | Tarefa | Comando |
|--------|--------|---------|
| [ ] | A `lab` | `dig +short lab.mework360.com.br` ou `Resolve-DnsName lab.mework360.com.br` |
| [ ] | Wildcard `cloud.lab` | `dig +short cloud.lab.mework360.com.br` |
| [ ] | Propagação mínima | Aguardar TTL/resolver antes de Fase 3 |

Idempotência: se registro já existe com IP correto → pular criação; se IP diverge → PATCH via API.

---

## 5. Fase 3 — Host base (SSH na VM nova)

> **Subagente sugerido:** Composer 2.5 com skill `plataforma-ambiente` para hardening CIS (opcional, fora do escopo mínimo).

### 5.1 Acesso e hardening inicial

> **Nota Debian 13:** imagem minimal pode vir sem `sudo` — necessário para shim SSH do control plane. `iptables-persistent` exige preseed debconf se instalado manualmente. Validar drop-ins OpenSSH com `sshd -t` após hardening.

| Status | Tarefa | Detalhe |
|--------|--------|---------|
| [ ] | SSH root inicial | Credenciais WHMCS (Fase 1) |
| [ ] | Chave SSH | Adicionar chave ops; **desabilitar** password auth |
| [ ] | `sudo` instalado | Debian minimal pode não incluir — garantir via cloud-init template ou `apt install sudo` **antes** do shim SSH |
| [ ] | `qemu-guest-agent` | **CRÍTICO** — backup PBS consistente (sem agent = crash-consistent) |
| [ ] | Preseed `iptables-persistent` | Evitar prompt debconf interativo (`DEBIAN_FRONTEND=noninteractive` + debconf-set-selections) se instalado fora do `deploy-server.sh` |
| [ ] | Validar OpenSSH | Após drop-ins de hardening: `sshd -t` OK; smoke login por chave |
| [ ] | Docker + Compose plugin | Versões pinadas (não `:latest`) — via `deploy-server.sh` (N30: repo Docker por `ID` debian/ubuntu) |
| [ ] | `ufw`, `fail2ban` | UFW: 22, 80, 443 (portas mail na fase posterior); `git`, `dnsutils`, `uuid-runtime` via `deploy-server.sh` (N30.3 / CQ-003) |

### 5.2 Bootstrap scripts (ordem obrigatória)

| Status | Script | Validação |
|--------|--------|-----------|
| [ ] | Clonar `work-platform-scripts` @ tag pinada do BOM | Anotar ref em §12 |
| [ ] | `sudo bash scripts/deploy-server.sh` | Traefik + deps up |
| [ ] | `shared-services/setup-shared.sh` | 8 serviços `shared-status` ● |
| [ ] | `scripts/setup-custom-apps.sh` | `/opt/shared-services/custom_apps/{mework360_memail,me360_theme}` |
| [ ] | `scripts/setup-worker.sh` | Worker Redis + fila |
| [ ] | `scripts/setup-ssh-gateway.sh` | Gateway SSH outbound |

### 5.3 Registro no control plane

| Status | Tarefa | Detalhe |
|--------|--------|---------|
| [ ] | Entrada `cluster_servers` | `ssh_host` = IP público; nome sugerido `lab` / `farm-lab-01` |
| [ ] | Webhook secret | Gerar `cluster_lab` novo; guardar em env/secrets — **nunca** no repo |
| [ ] | OPERATIONS.md | Registrar cluster_id + referência ao secret (nome da variável apenas) |

**URLs esperadas pós-Traefik:**

| Serviço | Hostname |
|---------|----------|
| Nextcloud (tenants) | `https://<tenant>.lab.mework360.com.br` |
| Cloud admin | `https://cloud.lab.mework360.com.br` |
| Webmail RC | `https://webmail.lab.mework360.com.br` |
| Control plane hook | conforme `deploy-server.sh` / painel |

---

## 6. Fase 4 — Roundcube pinado

> **Dependência:** sprint N14 (`work-rc-kit`) — imagem semver em GHCR.  
> **Bloqueio:** se tag N14 ausente → usar tag provisória de validação documentada em OPERATIONS.md; **nunca** `:latest` em LAB baseline.  
> **Auth GHCR:** usar credencial dedicada `GHCR_PULL_USER` / `GHCR_PULL_TOKEN` de `secret://env/cloud.env` (PAT classic, scope `read:packages` only — criado 2026-06-12). No host: `echo $GHCR_PULL_TOKEN | docker login ghcr.io -u $GHCR_PULL_USER --password-stdin`. **Não** usar token pessoal do `gh` CLI.

| Status | Tarefa | Detalhe |
|--------|--------|---------|
| [ ] | `docker login ghcr.io` no host | Credencial `GHCR_PULL_*` (cloud.env) via stdin — nunca em argv/log |
| [ ] | Resolver tag RC do BOM | Tag validada: `ghcr.io/softwarebeesy/mework360-rc:0.2.8` (única semver publicada em 2026-06-12) |
| [ ] | `/opt/roundcube/docker-compose.yml` | Imagem taggeada explícita |
| [ ] | Config cookies | host-only |
| [ ] | Config CSP | `frame-ancestors` restrito |
| [ ] | `me360_nc_origin` | **`https://qa-platform-lab.lab.mework360.com.br`** — deve ser o domínio do tenant provisionado (S6), não um host `cloud.lab` genérico (LAB-03). Validado por `roundcube_shared.sh` via curl |
| [ ] | Container healthy | `docker compose ps` → healthy |
| [ ] | Baseline plugins | Contar 21 plugins `me360_*` |

---

## 7. Fase 5 — Canário e gates

### 7.1 Tenant canário

| Status | Tarefa | Comando / ação |
|--------|--------|----------------|
| [ ] | Criar tenant | `qa-platform-lab` via control plane / `manage.sh create` |
| [ ] | Domínio | `qa-platform-lab.lab.mework360.com.br` (ou slug conforme contrato) |
| [ ] | Apps obrigatórios | `mework360_memail`, `me360_theme` |
| [ ] | Readiness → active | Poll job até estado terminal |

### 7.2 Gates R1–R8

| Gate | Verificação | Comando / critério |
|------|-------------|-------------------|
| R1 | `mework360_memail` enabled | `occ app:list` ou ProbeCustomerReadinessJob |
| R2 | `me360_theme` enabled | `occ app:list` |
| R3 | `occ user:list` | Admin presente |
| R4 | `externalLocation` não vazio | OCC config meMail |
| R5 | `forceSSO` configurado | OCC config meMail |
| R6 | HTTP meMail 200 | `curl -sf https://<tenant>/apps/mework360_memail/` |
| R7 | RC shared reachable | HTTP 200 em `webmail.lab.mework360.com.br` |
| R8 | Smoke SSO (ISSUE-031) | Login NC → meMail → inbox sem login RC duplicado |

| Status | Tarefa |
|--------|--------|
| [ ] | Gates R1–R8 todos PASS |
| [ ] | Matriz e2e settings memail contra LAB |
| [ ] | Smoke SSO documentado em OPERATIONS.md |

### 7.3 BOM baseline

| Status | Tarefa | Artefato |
|--------|--------|----------|
| [ ] | Registrar tags reais usadas | `releases/platform-1.0.0-lab-baseline.yaml` |
| [ ] | Incluir SHAs/refs | deploy-scripts, kits, imagem RC |

---

## 8. Fase 6 — Observabilidade e fechamento

| Status | Tarefa | Detalhe |
|--------|--------|---------|
| [ ] | Alertas básicos | Worker queue depth, containers down, restart loops |
| [ ] | PBS backup | VM no job 02:30 / 22:30; `qemu-guest-agent` OK |
| [ ] | OPERATIONS.md | Sumário de todas as fases + outputs §12 |
| [ ] | PLATFORM-V2-PLAN | Marcar tasks N25 `[x]` conforme concluídas |
| [ ] | Sign-off humano | Gate antes de promote LAB→PROD (N25.5) — **consultar usuário** |

---

## 9. Itens que dependem de humano (não automatizáveis)

| Item | Motivo | Ação |
|------|--------|------|
| **PTR/rDNS** do IP público | Provedor EVEO/mecloud | Abrir chamado para PTR → `mail.lab.mework360.com.br` (entregabilidade e-mail) |
| **Porta 25 outbound** | Datacenters frequentemente bloqueiam | Testar `nc -zv smtp.gmail.com 25`; abrir chamado se bloqueada |
| **Aprovação pedido WHMCS** | Política interna fatura zerada | Dono aprova `AcceptOrder` colaborador se necessário |
| **Sign-off baseline LAB** | Gate promote N25→PROD | Humano confirma R8 + BOM antes de qualquer PROD greenfield |

**Pontos de parada obrigatórios para /cloud-ops:** parar e consultar o usuário nos itens acima antes de prosseguir.

---

## 10. Riscos e rollback

| Risco | Mitigação | Rollback |
|-------|-----------|----------|
| VM defeituosa pós-`ModuleCreate` | Snapshot Proxmox pré-mudança (única escrita direta permitida) | Restaurar snapshot ou `ModuleTerminate` (**confirmação explícita** do usuário) |
| DNS incorreto | TTL 300 | PATCH registro PowerDNS; aguardar propagação |
| Let's Encrypt falha | DNS não propagado | Corrigir Fase 2 antes de retry Traefik |
| Tag RC indisponível | Bloqueio N14 | Tag provisória documentada; não promover a PROD |
| Blast radius | LAB isolado | `dev`/`cloud` legados intocados |

**Idempotência:** cada fase verifica estado antes de criar (cliente, DNS, cluster, tenant).

---

## 11. Prompt pronto para execução

Colar no chat para disparar o runbook:

```text
/cloud-ops Executar docs/LAB-PROVISION-PLAN.md do work-platform-api fase a fase.
Usar subagentes Composer 2.5 para execuções. Parar e me consultar nos pontos marcados
como humanos (PTR, porta 25, sign-off). Registrar tudo em OPERATIONS.md.
```

**Delegação sugerida por fase:**

| Fase | Subagente | Skill |
|------|-----------|-------|
| 0 | Composer 2.5 | `/cloud-ops` (read-only) |
| 1 | Composer 2.5 | `/cloud-ops` (WHMCS write) |
| 2 | Composer 2.5 | `/cloud-ops` (PowerDNS API) |
| 3–4 | Composer 2.5 | SSH + `work-platform-scripts` |
| 5 | Composer 2.5 | control plane + smoke |
| 6 | Composer 2.5 | observabilidade + fechamento |

---

## 12. Tabela de outputs (preencher durante execução)

| Campo | Valor |
|-------|-------|
| `client_id` | |
| `sid` (service id WHMCS) | |
| `vmid` (Proxmox) | |
| `node` (Proxmox) | |
| `ip_publico` | |
| `ip_privado` | |
| `dns_lab` | `lab.mework360.com.br` |
| `webhook_secret_ref` | env var name (ex. `CLUSTER_LAB_WEBHOOK_SECRET`) |
| `cluster_server_id` (control plane) | |
| `scripts_ref` (tag/sha) | |
| `rc_image_tag` | |
| `fase_0_concluida` | |
| `fase_1_concluida` | |
| `fase_2_concluida` | |
| `fase_3_concluida` | |
| `fase_4_concluida` | |
| `fase_5_concluida` | |
| `fase_6_concluida` | |
| `operador` | |
| `notas` | |

---

## Referências operacionais

| Recurso | Valor |
|---------|-------|
| WHMCS | `store.mecloud360.com.br` (9.0.3) |
| Servidor provisionamento | id=2 `ProxmoxVeVpsCloud` |
| Produto VM | pid=6 "Máquina Virtual Customizada" |
| Cluster Proxmox | `IDC-EVEO` (PVE 9.2.2) |
| PowerDNS Admin (referência) | `http://177.11.48.247:8080` |
| Zona DNS | `mework360.com.br` (Native) |
| Env secrets | `~/.config/beesy/cloud.env` |
| Política escrita | WHMCS API only; Proxmox write = snapshot only |
| Terminate/Suspend | Confirmação explícita do usuário |
