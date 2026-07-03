# Ecosystem map — meWork360 SaaS (audited 2026-06-08)

> Fonte: código local + **auditoria SSH read-only 2026-06-09** + sessão paridade local/Dev 2026-06-09.
> Taxonomia de ambientes e checklist paridade: `environment-and-parity.md`.

## Live audit snapshot (2026-06-09)

| Alvo | Evidência | Resultado |
|------|-----------|-----------|
| `https://deployer.mework360.com.br/up` | curl | **200** |
| `https://dev.mework360.com.br/status.php` | curl | **200** |
| Homolog SSH `mecloud360@dev.mework360.com.br` (SaaS-01) | BatchMode | **OK** |
| Prod SSH `mecloud360@cloud.mework360.com.br` (SaaS-02) | BatchMode | **OK** — FQDN `MECloud360-NextCloud-SaaS-02.mecloud360.com.br` |
| `nextcloud-manage` version (SaaS-01) | SSH | **v12.3.0** |
| `nextcloud-manage` version (SaaS-02) | SSH | **v12.3.0** |
| `nextcloud-saas-worker` (both hosts) | systemctl | **active** |
| Worker queue SaaS-02 | `worker status --json` | `queue_depth:0`, `jobs_today:0` |
| Shared services (8) SaaS-01 | `shared-status` | **all ●** since 2026-06-08 |
| Shared services (8) SaaS-02 | `shared-status` | **all ●** since 2026-06-01 / 2026-06-08 |
| `dev-app` meMail (SaaS-01) | occ | `mework360_memail` 1.5.0, `externalLocation=https://dev.mework360.com.br/roundcube` |
| `dev-app` theme (SaaS-01) | occ | `me360_theme` 1.6.13 |
| `mework360` meMail (SaaS-02) | occ | `mework360_memail` 1.4.30, `externalLocation=https://cloud.mework360.com.br/roundcube` |
| `mework360` theme (SaaS-02) | occ | `me360_theme` 1.0.0 |
| `/opt/shared-services/custom_apps/` SaaS-01 | ls | **missing** |
| `/opt/shared-services/custom_apps/` SaaS-02 | ls | **present** — `mework360_memail`, `me360_theme` |
| Tenants SaaS-02 | `list --json` | **11** running |
| Local docker R3 | `docker compose ps` | app/db/redis/worker/mailpit healthy; **nginx not running** |
| Local docker R4 | `migrate:status` | **FAIL** — migration table not found (never migrated locally) |

Prod deployer VM SSH (`operador@deployer.mework360.com.br`): not verified this run (auth/path).

### Roundcube verification (2026-06-10 — SSH + HTTP)

| Item | Dev (SaaS-01) | Prod (SaaS-02) |
|------|---------------|----------------|
| Instância | **própria** — `/opt/roundcube/` compose, container `roundcube-app-1` (NÃO é proxy) | própria — idem |
| Imagem | `roundcube/roundcubemail:latest` — **sem pin** | `roundcube/roundcubemail:latest` — **sem pin** |
| Apache / PHP | 2.4.67 / 8.4.21 | 2.4.66 / 8.4.20 (drift de `:latest`) |
| Plugins | **83 dirs** (versão mais atual — baseline) | **62 dirs** (21 de drift) |
| Cookie sessão | `path=/roundcube/` host-only ✔ | `domain=.mework360.com.br` ✘ (SEC-002 vivo) |
| CSP | `frame-ancestors 'self' + dev` ✔ | `frame-ancestors *` ✘ (clickjacking) |

→ Triagem 2026-06-10: **ISSUE-024..031** em `docs/ISSUES.md` (pipeline NC+RC). Estado-alvo decidido: `mework360-roundcube` evolui para **distribuição** (Dockerfile pinado, camada B migrada do memail, deploy por tag); **dev = baseline**, prod replica por tag; create automatiza config meMail (ISSUE-024). Mitigação imediata pendente: pin de imagem + cookie/CSP no prod (ISSUE-026).

## Repositories & roles

| Repo | Path local | Role in customer create | Called by deployer-api? |
|------|------------|-------------------------|------------------------|
| **mework360-deployer-api** | `.../mework360-deployer-api` | Orquestrador REST + painel; SSH out, webhook in | — (this repo) |
| **mework360-deploy-scripts** | `.../mework360-deploy-scripts` | Motor Bash: `manage.sh`, worker, Redis, shim SSH | **Yes** — only integration surface |
| **mework360_memail** | `.../mework360_memail` | App NC privado (iframe → Roundcube) | **Indirect** — synced on `create` |
| **mework360-roundcube** | `.../mework360-roundcube` | Plugins/skin RC (kit camada A) | **No** — ops scripts from memail |
| **mework360_theme** / **me360_theme** | `.../mework360_theme` | Tema NC + UI (app `me360_theme`) | **Indirect** — synced on `create` |
| **mework360-deck** | `.../mework360-deck` | Patches Deck/Calendar (kit) | **No** — manual deploy scripts |
| **mework360_topbar** | `.../cursor/mework360_topbar` | Docs/widgets dashboard (overlap theme) | **No** |

**Not found:** repo named `mework360-tm`. Talk/messaging = **Nextcloud Talk (`spreed`)** + shared stack (Janus, NATS, signaling, recording) in `mework360-deploy-scripts/shared-services/`. Clarify with team if `tm` = another product.

## Production topology (from docs + scripts)

> VPS naming and Dev/Prod independence: `environment-and-parity.md` § VPS upstream.

```text
                    Internet
                        │
         ┌──────────────┴──────────────┐
         │ deployer.mework360.com.br   │  mework360-deployer-api (control plane)
         │  POST /api/customers        │
         │  POST /api/jobs/hook        │
         └──────────────┬──────────────┘
                        │ SSH per cluster_server (independent stacks)
            ┌───────────┴───────────┐
            ▼                       ▼
 ┌──────────────────────┐  ┌──────────────────────┐
 │ NextCloud-SaaS-01    │  │ NextCloud-SaaS-02    │
 │ dev.mework360.com.br │  │ cloud.mework360.com.br│
 │ homolog              │  │ prod (11 tenants)    │
 │ manage + worker      │  │ manage + worker      │
 │ shared-services      │  │ shared-services      │
 │ /opt/nextcloud-      │  │ /opt/nextcloud-      │
 │   customers/<slug>/  │  │   customers/<slug>/  │
 └──────────┬───────────┘  └──────────┬───────────┘
            │                         │
            │ RC on same host (dev)   │ meMail → externalLocation
            ▼                         ▼
   dev.../roundcube          cloud.mework360.com.br/roundcube
                             (shared RC — NOT per tenant)
```

| Cluster | UUID | Nome painel | `ssh_host` | Deployer |
|---------|------|-------------|------------|----------|
| Homolog / SaaS-01 | `119d74df-9011-4c0f-a6bf-ad03f84af10d` | `homolog` (ref. docs/tests) | `dev.mework360.com.br` | Tier 2 / histórico — **não** está no DB do deployer prod (2026-06-10) |
| Produção / SaaS-02 | `0e50e032-df0f-4387-aa00-43bae3672147` | `producao` | `cloud.mework360.com.br` | `deployer.mework360.com.br` (único cluster ativo no DB) |
| Produção piloto image-mode / image-pilot | *(cadastro pendente — N36.3)* | `image-pilot` (ref. docs) | `cloud.image-pilot.mework360.com.br` (`128.201.61.120`) | Futuro alvo prod — `create --image-mode --suite-catalog`; tenants `*.image-pilot.mework360.com.br` (cutover futuro: `*.mework360.com.br`) |

## Dev = TN factory + runtime (same host)

There is **no separate** “TN creation environment” for Dev. `dev.mework360.com.br` runs:

- `nextcloud-manage` + worker (factory)
- `shared-services` (shared MariaDB, Redis, Collabora, Talk stack)
- All homolog tenants under `/opt/nextcloud-customers/<slug>/` (e.g. `dev-app` + future `qa-*`)

`deployer.mework360.com.br` only orchestrates via SSH; it does **not** host Nextcloud tenants.

**Roundcube** is a **per-host shared** service — not created per `create`, not per tenant. **Verified 2026-06-10:** each host runs its **own** instance at `/opt/roundcube/` (container `roundcube-app-1`, image `:latest` unpinned — see ISSUE-025/026). Dev is NOT a proxy to cloud.

## Customer create — what actually runs

### Layer 1: deployer-api

`ProvisionCustomerAction` → SSH:

```text
nextcloud-manage <slug> <domain> create --async --json
  --idempotency-key=<uuid> --callback=<APP_URL>/api/jobs/hook?cluster=<id>
  [--payload-stdin | --staging-id] [--apps=...] [--full-apps]
```

Then: webhook `job.started` / `job.finished` → `WebhookHandler` → `ProbeCustomerReadinessJob` (`occ-exec user:list`) → customer `active`.

### Layer 2: deploy-scripts (`manage.sh` v12.3.0)

`cmd_create`:

1. MariaDB DB + tenant dir + generated `docker-compose.yml` (app, nginx, cron, harp, push)
2. Store apps: `richdocuments calendar contacts mail deck ... spreed notify_push`
3. OCC: Redis, Collabora, Talk (TURN/STUN/signaling/recording), HaRP, indexes
4. `custom_apps_sync_tenant` → `mework360_memail` + `me360_theme` from `/opt/shared-services/custom_apps/`
5. `occ app:enable mework360_memail me360_theme`
6. Branding: `dispatch.sh` / `feature_o_ext.sh` (stdin or SFTP staging)

Worker (`worker.sh`): Redis BRPOP → run job → HMAC callback to API.

Security: `ncsaas-api-shim` allowlist → `sudo nextcloud-manage` only.

### Layer 3: mail (NOT in deployer-api)

| Piece | Provisioned on create? | Notes |
|-------|------------------------|-------|
| NC app `mail` (store) | Yes | Official Mail app |
| `mework360_memail` | Yes (N4 sync) | meMail UI; needs `externalLocation` **manual** post-create |
| Roundcube | **No** | Per-host instance em `/opt/roundcube/` (dev e cloud); plugins via memail scripts; ISSUE-025 |
| `mework360-roundcube` repo | **No** | Versioned kit for RC patches |

### Layer 4: theme / deck

| Piece | On create? | Notes |
|-------|------------|-------|
| `me360_theme` | Yes (N4) | From `custom_apps` volume mount |
| `mework360-deck` patches | **No** | Separate kit; probes in deck repo |
| Dashboard/topbar widgets | **No** | `mework360_topbar` / theme docs — manual occ |

## Contract alignment (ISSUE-022 status)

| Frente | Scripts (`CONTRACTS.md`) | API | Gap |
|--------|--------------------------|-----|-----|
| Webhook `exit_code` | Required in v12.3 code | Persists if present | Prod may run old worker; `summary`/`log_tail` **not emitted** by worker |
| Branding stdin | `branding.*_data_url` | F13 fixed | Failures masked (`|| true`) in upstream |
| OCC allowlist | 35 subcmds in `occ_bridge.sh` | 5 endpoints blocked (ISSUE-016) | API maps exit 16 → 403 |
| argv | `user create` (space) | `JobTypeTranslator::cmdToCliArgv` | ISSUE-006 fixed in API |
| `apps:enable` | Store apps only | CSV async | Does not enable `mework360_memail` (already on create) |

## What deployer-api does NOT own

- Tenant Docker compose generation
- Shared services lifecycle
- Roundcube / RC plugins
- meMail `externalLocation` / SSO cookies
- Theme/deck kit deployment
- Custom-apps git pull (`manage.sh custom-apps update`)
- Mailbox/IMAP provisioning (user email in `users:create` = NC user field only)

## Ops commands outside API (upstream SSH)

| Command | Purpose |
|---------|---------|
| `nextcloud-manage custom-apps update --app=mework360_memail` | Propagate app to all tenants |
| `nextcloud-manage config set-webhook-secret --payload-stdin` | Sync secret + restart worker |
| `nextcloud-manage job <id> logs` | Introspection (JobLogFetcher) |
| `nextcloud-manage list --json` | `customers:sync` in API |

## Local paths (developer machine)

```
cursorwindows/
├── mework360-deployer-api/     ← API + this skill
├── mework360-deploy-scripts/   ← upstream (read CONTRACTS.md first)
├── mework360_memail/
├── mework360-roundcube/
├── mework360_theme/            ← may be canonical here on Windows
└── mework360-deck/

cursor/                         ← mework360-local compose resolves ../../../ here
├── mework360-local/            ← FULL_LOCAL stack (Tier 3b)
├── mework360-local-lab/        ← clean lab (Tier 3a)
├── mework360_memail/app/...    ← must exist for bind-mount (often empty — see environment-and-parity.md)
├── mework360_theme/            ← bind-mount themes/me360 (sync from cursorwindows if needed)
└── nc-upgrade-sim/app-me360_theme/
```

## Provision profile & canary TN (ops pattern)

Before changing `manage.sh` or custom apps on the factory host:

1. Pin baseline: `manage.sh` version, git SHAs of memail/theme, worker status
2. Apply profile change on host
3. `create` slug `qa-canary-YYYYMMDD` (Tier 2 API or SSH)
4. Run `post-create-runbook.md` + checklist in `environment-and-parity.md`
5. OK → adopt profile; NOK → git rollback + `remove qa-*`

**Caution:** `custom-apps update` affects **all** tenants on the host — not isolated to new TNs.

## Audit still required

- [x] SSH homolog / SaaS-01 version/worker (2026-06-09)
- [x] SSH read-only SaaS-02 @ `cloud.mework360.com.br` (2026-06-09): v12.3.0, worker, 11 tenants, custom_apps OK
- [x] `cluster_servers` prod: `0e50e032-df0f-4387-aa00-43bae3672147` (`producao` @ `cloud.mework360.com.br`) — DB deployer 2026-06-10
- [ ] Smoke: `scripts/runbooks/qa-tenant-create-api-smoke.sh` on upstream
- [ ] API Tier 2: provision **new** slug + webhook reachability
- [x] Post-create runbook drafted → `post-create-runbook.md`
- [x] RC dev = instância própria (não proxy) + drift dev/prod + `:latest` sem pin (2026-06-10) → ISSUE-024..031
- [x] Prod deployer `jobs.summary` sample (ISSUE-023): últimos 10 jobs terminados → **0/10** com `exit_code`/`summary` null (2026-06-10) — mitigação F10 validada nesta amostra
- [ ] Painel × host prod: **6 tenants** running no SaaS-02 sem registro em `customers` (76fibra, alloha, meltech, mework360, nextcloud-02, totum) + `teste` preso em `provisioning` — pendente triagem/backfill (`customers:sync`)
- [ ] Confirm `mework360-tm` repo (Talk = `spreed` + shared signaling stack)

## Version drift warning

| Source | Version cited |
|--------|---------------|
| `manage.sh` `SCRIPT_VERSION` | v12.3.0 |
| `README` deploy-scripts | v12.2 |
| `CONTRACTS.md` title | v12.0 |
| deployer-api homolog probing | v12.3.0 |

Always verify running binary on server before declaring cross-repo ready.
