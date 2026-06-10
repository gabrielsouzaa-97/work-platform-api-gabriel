# Environment taxonomy & Dev parity

> Consolidado em 2026-06-09; VPS Dev/Prod e independência de fluxos em 2026-06-09.

## Vocabulário (evitar confusão)

| Termo que o time usa | Significa na prática | Não confundir com |
|----------------------|----------------------|-------------------|
| **NextCloud-SaaS-01** | VPS upstream **Dev/homolog** (`dev.mework360.com.br`) | Painel `deployer.mework360.com.br` |
| **NextCloud-SaaS-02** | VPS upstream **Produção** (host NC + `manage.sh` + worker) | VM do deployer-api |
| **Ambiente Dev / homolog** | Host upstream `dev.mework360.com.br` (= SaaS-01) | Painel `deployer.mework360.com.br` |
| **Ambiente que cria TNs** | Mesmo host upstream (`manage.sh` + worker) | Segundo host “só fábrica” no Dev — **não existe** |
| **Produção (fábrica)** | Host upstream (SaaS-02) onde `create` roda — com ou sem clientes reais | TNs de teste `qa-*` no mesmo host |
| **Produção (clientes)** | TNs com usuários finais em operação no SaaS-02 | Slug de homolog no SaaS-01 |
| **deployer-api** | Control plane (API + painel + webhook) — orquestra **N** clusters | Nextcloud tenant |

**Regra:** um **cluster** no painel = um **host upstream** SSH. Esse host é **fábrica e runtime ao mesmo tempo**: cada `create` adiciona `/opt/nextcloud-customers/<slug>/` no mesmo servidor (ex.: `dev-app` + `qa-canary-01` coexistem).

## VPS upstream — Dev vs Prod (independência)

Cada VPS upstream é um **stack completo e isolado** do outro. Contratar/criar TN em Dev **não** provisiona em Prod, e vice-versa.

| VPS (nome interno) | Papel | DNS / SSH conhecido | Auditado na skill |
|--------------------|-------|---------------------|-------------------|
| **NextCloud-SaaS-01** | Dev / homolog | `dev.mework360.com.br` (`mecloud360@…`); hostname curto `dev` (FQDN interno: `MECloud360-NextCloud-SaaS-01` — ISSUE-006) | Sim (2026-06-09) |
| **NextCloud-SaaS-02** | Produção | `cloud.mework360.com.br` (`mecloud360@…`); FQDN `MECloud360-NextCloud-SaaS-02.mecloud360.com.br` | Sim (2026-06-09 SSH read-only) |

**O que é independente entre SaaS-01 e SaaS-02**

| Recurso | Isolado entre VPS? |
|---------|-------------------|
| `nextcloud-manage` + `nextcloud-saas-worker` | Sim — um daemon/fila por host |
| Redis de jobs upstream | Sim |
| MariaDB / shared-services do host | Sim — cada VPS tem o seu |
| Tenants `/opt/nextcloud-customers/<slug>/` | Sim |
| Webhook secret por `cluster_server` | Sim |
| Fluxo de contratação (`create` → webhook → probe → `active`) | Sim — mesmo pipeline, **cluster destino** define o host |

**O que NÃO é independente (matizes)**

| Recurso | Comportamento |
|---------|---------------|
| **deployer.mework360.com.br** | Control plane **compartilhado** — um painel pode orquestrar SaaS-01 e SaaS-02 via `cluster_server_id` |
| **Roundcube produção** | Serviço **compartilhado** no **mesmo host** SaaS-02 (`cloud.mework360.com.br/roundcube`) — não nasce no `create`; meMail aponta via `externalLocation` |
| **Dentro da mesma VPS** | `custom-apps update` e mudanças em shared-services afetam **todos** os TNs **daquele** host |
| **Versões** | `manage.sh`, worker e apps N4 podem **divergir** entre 01 e 02 — pinar antes de comparar paridade |

Fluxo de contratação (idêntico em qualquer cluster): `provision-lifecycle.md` — `POST /api/customers` → SSH `create --async` → worker → `POST /api/jobs/hook` → `ProbeCustomerReadinessJob` → `active`.

## Topologia operacional (Dev + Prod)

```text
                    deployer.mework360.com.br
                    (mework360-deployer-api — control plane)
                              │ SSH + webhook (por cluster_server)
              ┌───────────────┴───────────────┐
              ▼                               ▼
   NextCloud-SaaS-01 (Dev)        NextCloud-SaaS-02 (Prod)
   dev.mework360.com.br            cloud.mework360.com.br
     ├── nextcloud-manage             ├── nextcloud-manage
     ├── nextcloud-saas-worker       ├── nextcloud-saas-worker
     ├── shared-services              ├── shared-services
     └── /opt/nextcloud-customers/    └── /opt/nextcloud-customers/
              │                               │
              │ RC homolog (mesmo host)       │ externalLocation → RC prod
              ▼                               ▼
   dev.mework360.com.br/roundcube    cloud.mework360.com.br/roundcube
                                     (shared — NÃO é TN por cliente)
```

### Dev — SaaS-01 (audit 2026-06-09)

- Cluster homolog cadastrado: `119d74df-9011-4c0f-a6bf-ad03f84af10d` @ `dev.mework360.com.br`
- `nextcloud-manage` v12.3.0 + worker **active**
- TN referência: `dev-app` — meMail 1.5.0, `me360_theme` 1.6.13, `externalLocation=https://dev.mework360.com.br/roundcube`
- Gap audit: `/opt/shared-services/custom_apps/` **ausente** no homolog — N4 pode sincar apps da árvore do tenant, não do path documentado

### Prod — SaaS-02 (audit SSH read-only 2026-06-09)

- FQDN: `MECloud360-NextCloud-SaaS-02.mecloud360.com.br` — SSH/curl: `cloud.mework360.com.br`
- `nextcloud-manage` **v12.3.0** + worker **active** (`queue_depth:0`)
- Shared services (8): db, redis, collabora, turn, nats, janus, signaling, recording — **all ●** (since 2026-06-01 / 2026-06-08)
- Edge prod: `collabora-02.mecloud360.com.br`, `signaling-02.mecloud360.com.br`, `turn-02.mecloud360.com.br`
- `/opt/shared-services/custom_apps/`: **presente** — `mework360_memail`, `me360_theme` (gap Dev não se repete aqui)
- **11 tenants** em `/opt/nextcloud-customers/` (ex.: `mework360` → `cloud.mework360.com.br`, `76fibra`, `alloha`, `totum`, …)
- TN referência `mework360`: meMail **1.4.30**, `me360_theme` **1.0.0**, `externalLocation=https://cloud.mework360.com.br/roundcube`
- Roundcube shared no **mesmo host** — `post-create-runbook.md` §2
- Cluster cadastrado no deployer prod (`deployer.mework360.com.br`, audit DB 2026-06-10):
  - **UUID:** `0e50e032-df0f-4387-aa00-43bae3672147`
  - **Nome painel:** `producao`
  - **ssh_host:** `cloud.mework360.com.br` | **ssh_user:** `ncsaas-api` | **status:** `active`

## Ambientes na máquina do desenvolvedor

| Ambiente | Repo | URL típica | Login | O que simula |
|----------|------|------------|-------|--------------|
| **deployer-api local** | `mework360-deployer-api` | `http://localhost:8080` | `admin@mework360.local` / `password` | Painel/API (Tier 1); cluster seed **fake** |
| **mework360-local FULL_LOCAL** | `cursor/mework360-local` | `https://cloud.mework360.local` | `admin` + `NC_ADMIN_PASSWORD` do `.env` | Stack NC+RC+Collabora; **não** passa por `manage.sh` |
| **mework360-local-lab** | `mework360-local-lab` | `http://localhost:9080` | `admin` / `admin` | Tenant limpo UI; **não** é `create` real |
| **Tier 2 (laptop → homolog)** | deployer-api + cluster real | painel local | operador seed | `create` **real** no `dev.mework360.com.br` |

**Não existe hoje** um script único `simulate-client-create.sh` que replique `manage.sh create` + pós-create localmente. Está espalhado em `new-tenant.sh`, `bootstrap-nc.sh`, `configure-full-local.sh`, `apply-me360-theme.sh`.

## O que `manage.sh create` entrega vs “TN pronto”

| Entregue no `create` | Exige pós-create manual |
|----------------------|---------------------------|
| DB + compose + containers tenant | `mework360_memail` → `externalLocation` |
| Apps store (richdocuments, spreed, mail, deck…) | `occ app:disable mail` (política meWork360) |
| N4: `mework360_memail` + `me360_theme` enabled | Plugins/sessão Roundcube shared |
| `theme => me360` em config | Branding logo/background (stdin/SFTP) |
| OCC Redis, Collabora, Talk, HaRP | Validar meMail iframe + Collabora smoke |

Runbook: `post-create-runbook.md`. **HTTP 202 da API ≠ tenant pronto para demo.**

## Estratégia: perfil de provisionamento + TN canário

Válida no **host fábrica** (sem clientes em uso ou com slug descartável):

1. **Baseline (pin do perfil)** — gravar antes de mudar:
   - `nextcloud-manage version` / git tag `mework360-deploy-scripts`
   - commit/versão de `mework360_memail`, `mework360_theme`, `me360_theme`
   - versão do worker (`systemctl status nextcloud-saas-worker`)
2. **Aplicar melhorias no perfil** — bump `manage.sh` e/ou custom apps no host
3. **TN canário** — `nextcloud-manage qa-canary-YYYYMMDD <domain> create --async` (via API Tier 2 ou SSH)
4. **Checklist paridade** — apps, tema, login, meMail, Collabora (ver § abaixo)
5. **Decisão** — OK → perfil vira default para novos TNs; NOK → `git checkout` baseline + `remove qa-*`

### Risco por tipo de mudança (host fábrica sem tráfego)

| Ação | Isolamento | Risco |
|------|------------|-------|
| `create` slug novo | Alto (DB/compose próprios) | **Baixo** |
| `remove` slug teste | Alto | Baixo |
| Rollback git `manage.sh` / custom apps | Global para próximos `create` | Baixo se pin documentado |
| `custom-apps update` | Propaga a **todos** TNs do host | **Médio** |
| Mudança shared-services / RC Plus | Host inteiro | **Médio** |

**Não** usar `custom-apps update` em massa antes de validar em **um** TN canário.

## Checklist paridade local ↔ Dev (pós-create)

Use após `create` canário ou após simulação local completa:

- [ ] `occ app:list` → `mework360_memail`, `me360_theme` enabled
- [ ] `config.php` → `'theme' => 'me360'`
- [ ] `occ config:app:get mework360_memail externalLocation` (Dev: `https://dev.mework360.com.br/roundcube`; local FULL_LOCAL: `https://cloud.mework360.local/roundcube/`)
- [ ] Login NC + dashboard com branding me360
- [ ] `/apps/mework360_memail/` abre (não página em branco / 503 update)
- [ ] `/roundcube/` HTTP 200
- [ ] Collabora abre documento (`office.*`)
- [ ] (Opcional Dev) Talk `spreed` — omitido no FULL_LOCAL v1
- [ ] App store `mail` desabilitado se política prod

## mework360-local FULL_LOCAL (`FULL_LOCAL=1`)

Sibling repo: `cursor/mework360-local` (não confundir com `mework360-local-lab`).

```text
Traefik + mkcert
  cloud.mework360.local     → mework360-app (NC + meMail + tema)
  cloud.../roundcube        → roundcube-app-1 (Plus local)
  office.mework360.local    → shared-collabora
```

Scripts: `up-full-local.sh`, `bootstrap-nc.sh`, `configure-full-local.sh`, `apply-me360-theme.sh`, `new-tenant.sh` (tenant extra sem meMail automático).

### Gaps vs Dev real

| Dev / upstream | FULL_LOCAL v1 |
|----------------|---------------|
| `manage.sh create` | manual / scripts parciais |
| Talk + signaling | omitido |
| HaRP, nginx, push por tenant | omitido (só app+cron) |
| RC shared cloud | RC local same-origin |
| Webhook + probe `active` | ausente |
| `custom_apps_sync` N4 servidor | bind-mount repos locais |

## Armadilhas Windows (bind-mount)

Compose em `mework360-local/tenants/mework360/docker-compose.yml` resolve paths relativos a `cursor/`:

| Mount esperado | Path relativo | Problema observado |
|----------------|---------------|-------------------|
| meMail app | `../../../mework360_memail/app/mework360_memail` | Pasta **vazia** se código só está em worktree ou `cursorwindows/` |
| Tema | `../../../mework360_theme` → `themes/me360` | Idem — assets reais podem estar em `cursorwindows/mework360_theme` |
| me360_theme app | `../../../nc-upgrade-sim/app-me360_theme` | Path pode não existir no Windows |

**Sintomas:**

| Sintoma | Causa provável |
|---------|----------------|
| meMail HTTP 200, body vazio | Mount `mework360_memail` vazio |
| NC HTTP 503, `#core-updater` | `installed_version` DB > `info.xml` do app montado |
| NC 503 / crash | `me360_theme` enabled + `themes/me360/config/*` ausente |
| `occ` falha “diretório não gravável” | bind-mount `data/` no Docker Desktop Windows (uid 1000 vs www-data) — usar SQL (`_sql_memail_config.sh`, `_enable_me360_theme_db.sh`) |

**Correção durável:** symlink ou ajustar compose para paths reais; copiar para paths esperados é hotfix, não perfil.

## Versões de referência (2026-06-09/10)

| Componente | Homolog `dev-app` (SaaS-01) | Prod `mework360` (SaaS-02) | Local FULL_LOCAL (após hotfix) |
|------------|------------------------------|----------------------------|--------------------------------|
| Nextcloud | 33.0.4.1 | **33.0.3.2** (occ status 2026-06-10) | 33.0.4.1 |
| mework360_memail | 1.5.0 | **1.4.30** | 1.4.24 (worktree baseline; alinhar antes de comparar) |
| me360_theme | 1.6.13 | **1.0.0** | depende do mount |
| manage.sh | v12.3.0 | v12.3.0 | N/A local |

**Drift Dev → Prod confirmado (2026-06-10):** meMail 1.5.0 vs 1.4.30; `me360_theme` 1.6.13 vs 1.0.0. Antes de prometer paridade de feature em prod, validar versão do app **no host SaaS-02** — homolog está à frente.

Sempre pinar versões no checklist canário — drift entre DB `installed_version` e `info.xml` bloqueia o NC.

## Roteamento de intenção

| Pergunta do usuário | Ler primeiro |
|---------------------|--------------|
| “SaaS-01 vs SaaS-02?” / “Dev e Prod são independentes?” | Este doc § VPS upstream |
| “O fluxo de contratação é o mesmo nos dois?” | Este doc § VPS + `provision-lifecycle.md` |
| “Onde criam TNs no Dev?” | Este doc § Vocabulário + SaaS-01 |
| “Como simular create local?” | `local-stack.md` § Tier 3b + este doc § Gaps |
| “Posso testar create no host fábrica?” | Este doc § Estratégia canário |
| “meMail não abre local” | Este doc § Armadilhas Windows |
| “Igual Dev após provision?” | `post-create-runbook.md` + checklist § paridade |
