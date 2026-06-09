# Ecosystem map — meWork360 SaaS (audited 2026-06-08)

> Fonte: código local + **auditoria SSH read-only 2026-06-09** + sessão paridade local/Dev 2026-06-09.
> Taxonomia de ambientes e checklist paridade: `environment-and-parity.md`.

## Live audit snapshot (2026-06-09)

| Alvo | Evidência | Resultado |
|------|-----------|-----------|
| `https://deployer.mework360.com.br/up` | curl | **200** |
| `https://dev.mework360.com.br/status.php` | curl | **200** |
| Homolog SSH `mecloud360@dev.mework360.com.br` | BatchMode | **OK** |
| `nextcloud-manage` version | SSH | **v12.3.0** (`SCRIPT_VERSION`, manage.sh 2026-05-24) |
| `nextcloud-saas-worker` | systemctl | **active** |
| Worker queue | `worker status --json` | `queue_depth:0`, `jobs_today:0` |
| Shared services (8) | `shared-status` | **all ●** since 2026-06-08 |
| `dev-app` meMail | occ | `mework360_memail` 1.5.0, `externalLocation=https://dev.mework360.com.br/roundcube` |
| `dev-app` theme | occ | `me360_theme` 1.6.13 |
| `/opt/shared-services/custom_apps/` | ls homolog | **missing** — N4 path absent; apps may live in tenant tree |
| Local docker R3 | `docker compose ps` | app/db/redis/worker/mailpit healthy; **nginx not running** |
| Local docker R4 | `migrate:status` | **FAIL** — migration table not found (never migrated locally) |

Prod deployer VM SSH (`operador@deployer.mework360.com.br`): not verified this run (auth/path).

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

```text
                    Internet
                        │
         ┌──────────────┴──────────────┐
         │ deployer.mework360.com.br   │  mework360-deployer-api (Docker VM)
         │  POST /api/customers        │
         │  POST /api/jobs/hook        │
         └──────────────┬──────────────┘
                        │ SSH (ncsaas-api + shim)
                        ▼
         ┌──────────────────────────────┐
         │ dev.mework360.com.br (homolog)│  nextcloud-saas host
         │  /opt/nextcloud-customers/   │
         │  nextcloud-manage + worker     │
         │  shared-services (MariaDB,     │
         │    Redis, Collabora, TURN,    │
         │    NATS, Janus, signaling)    │
         └──────────────┬───────────────┘
                        │ per-tenant compose
                        ▼
              /opt/nextcloud-customers/<slug>/
                        │
         ┌──────────────┴──────────────┐
         │ cloud.mework360.com.br      │  Roundcube shared (NOT per tenant)
         │ /opt/roundcube              │
         └─────────────────────────────┘
```

Known cluster (homolog): `119d74df-9011-4c0f-a6bf-ad03f84af10d` @ `dev.mework360.com.br`.

## Dev = TN factory + runtime (same host)

There is **no separate** “TN creation environment” for Dev. `dev.mework360.com.br` runs:

- `nextcloud-manage` + worker (factory)
- `shared-services` (shared MariaDB, Redis, Collabora, Talk stack)
- All homolog tenants under `/opt/nextcloud-customers/<slug>/` (e.g. `dev-app` + future `qa-*`)

`deployer.mework360.com.br` only orchestrates via SSH; it does **not** host Nextcloud tenants.

**Roundcube** is a **shared** service (`cloud.mework360.com.br` prod; homolog often `dev.../roundcube` proxy) — not created per `create`.

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
| Roundcube | **No** | Shared on `cloud`; plugins via memail scripts |
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

- [x] SSH homolog version/worker (2026-06-09)
- [ ] Smoke: `scripts/runbooks/qa-tenant-create-api-smoke.sh` on upstream
- [ ] API Tier 2: provision **new** slug + webhook reachability
- [x] Post-create runbook drafted → `post-create-runbook.md`
- [ ] Prod deployer VM SSH + `jobs.summary` sample (ISSUE-023)
- [ ] Confirm `mework360-tm` repo (Talk = `spreed` + shared signaling stack)

## Version drift warning

| Source | Version cited |
|--------|---------------|
| `manage.sh` `SCRIPT_VERSION` | v12.3.0 |
| `README` deploy-scripts | v12.2 |
| `CONTRACTS.md` title | v12.0 |
| deployer-api homolog probing | v12.3.0 |

Always verify running binary on server before declaring cross-repo ready.
