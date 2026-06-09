# Local stack

## First-time setup

```bash
cd mework360-deployer-api
cp .env.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Optional hosts entry (matches `.env.example`):

```
127.0.0.1 mework360.local
```

Panel: `http://localhost:8080` or `http://mework360.local:8080`

## Verify stack

```bash
docker compose ps          # all healthy
curl http://localhost:8080/up
docker compose exec -T app php artisan about
```

## Persisted data

| Volume | Data |
|--------|------|
| `db_data` | operators, cluster_servers, customers, jobs, audit |
| `redis_data` | sessions, cache, queue |
| `mailpit_data` | captured emails |

Reset DB only (keeps images):

```bash
docker compose exec -T app php artisan migrate:fresh --seed
```

## Simulation tiers

### Tier 0 — automated tests only

```bash
docker compose exec -T app php artisan test --parallel
```

SSH is mocked. Validates API logic, webhook handler, translators. **Does not** touch upstream.

### Tier 1 — API panel local (default)

After seed:

- Login: `admin@mework360.local` / `password`
- Cluster `dev-cluster-local` exists with **fake** credentials
- Provision API calls will fail at SSH unless you replace cluster credentials

Use for: UI, auth, API keys, audit, queue UI without upstream.

### Tier 2 — API local + real upstream (recommended E2E)

**Goal**: create a real customer on homolog/staging upstream from your laptop.

1. Complete Tier 1 setup
2. Register a real cluster in panel → **Cluster Servers** → **Novo Cluster**:
   - `ssh_host`, `ssh_port`, `ssh_user` = `ncsaas-api`
   - Paste real Ed25519/RSA private key (PEM)
   - Set webhook secret matching upstream
   - **Testar Conexão** must pass
3. Set `APP_URL` to a URL the upstream can POST to:
   - **Production/staging VM**: real HTTPS URL
   - **Local only**: ngrok/cloudflared tunnel → `https://xxx.ngrok.io` in `.env`, then `docker compose restart app worker nginx`
4. Provision via API or panel (`POST /api/customers` or `/customers/create`)
5. Watch job: `/queue/{job_id}`; confirm webhook or wait for `jobs:poll-stuck` scheduler

Opt-in contract test against homolog:

```bash
RUN_UPSTREAM_CONTRACT=1 \
UPSTREAM_CONTRACT_CLUSTER_ID=<uuid> \
UPSTREAM_CONTRACT_CUSTOMER_SLUG=<slug> \
docker compose exec -T app php artisan test --testsuite=Contract
```

### Tier 3a — tenant lab (NC + theme + meMail + Roundcube)

Sibling repo **`mework360-local-lab`** (`../mework360-local-lab`) — one-tenant slice for UI/integration tests without homolog SSH.

```powershell
cd ..\mework360-local-lab
copy .env.example .env
.\scripts\sync-workspace.ps1
docker compose up -d
# wait ~2 min first install
.\scripts\bootstrap.ps1
```

| URL | Purpose |
|-----|---------|
| http://localhost:9080 | Nextcloud + theme |
| http://localhost:9080/apps/mework360_memail/ | meMail |
| http://localhost:9080/roundcube/ | Roundcube (same-origin proxy) |

Login: `admin` / `admin`. Does **not** run `manage.sh create` or webhooks.

### Tier 3b — mework360-local FULL_LOCAL (stack Dev-like, sem SSH)

Sibling repo **`mework360-local`** (`../cursor/mework360-local` or `IA/cursor/mework360-local`) — Traefik + mkcert, tenants persistentes, RC Plus local, Collabora.

```bash
# .env: FULL_LOCAL=1, ROUNDCUBE_PLUS_LICENSE_KEY, ROUNDCUBE_EXTERNAL_URL=https://cloud.mework360.local/roundcube
bash scripts/up-full-local.sh
bash scripts/bootstrap-nc.sh          # tenant mework360 (primeira vez)
bash scripts/configure-full-local.sh  # Collabora + meMail + tema
```

| URL | Purpose |
|-----|---------|
| https://cloud.mework360.local | Nextcloud tenant `mework360` |
| https://cloud.mework360.local/apps/mework360_memail/ | meMail (requer login NC) |
| https://cloud.mework360.local/roundcube/ | Roundcube direto |
| https://office.mework360.local | Collabora |

Login NC: `admin` + `NC_ADMIN_PASSWORD` from `mework360-local/.env` — **not** deployer-api seed.

**Does not** run `manage.sh create`, webhooks, or `ProbeCustomerReadinessJob`. Tenant `mework360` may be long-lived with drift — for parity tests prefer `new-tenant.sh` + configure scripts (see `environment-and-parity.md`).

### Tier 4 — full provision E2E (future)

Requires `mework360-deploy-scripts` worker + shim locally **or** homolog SSH (Tier 2). Track in ISSUE-022.

**Today:** real `create` on Dev = **Tier 2** pointing at cluster `dev.mework360.com.br` (factory + runtime on same host).

## Windows notes

- Prefer Docker Desktop with WSL2 backend
- `/jarvis pipeline` and Beesy `pipeline.sh` → run inside **WSL2**, not PowerShell (`docs/upgrades/PLAN-2026-06-02.md`)
- Bind mounts work for code edits **when paths exist** — see traps below

### Windows bind-mount traps (`mework360-local`)

Compose expects repos under `IA/cursor/` (relative `../../../` from `tenants/mework360/`):

| Mount | Expected path | Common failure |
|-------|---------------|----------------|
| meMail | `cursor/mework360_memail/app/mework360_memail` | Empty dir; real code in worktree or `cursorwindows/` |
| Theme | `cursor/mework360_theme` | Empty; assets in `cursorwindows/mework360_theme` |
| me360_theme app | `cursor/nc-upgrade-sim/app-me360_theme` | Missing on Windows |

Symptoms and fixes: `environment-and-parity.md` § Armadilhas Windows. `occ` often fails on Windows mounts — use SQL helpers in `mework360-local/scripts/_sql_memail_config.sh`, `_enable_me360_theme_db.sh`.

## Mailpit (local)

- SMTP: `mailpit:1025` (from app container)
- UI: `http://127.0.0.1:8025/mailpit` (bound to localhost only)
- Use for operator invite, password reset emails

## Common failures

| Symptom | Cause | Fix |
|---------|-------|-----|
| 419 on login POST | `config:cache` with empty APP_KEY | `key:generate`, `config:clear` |
| Connection refused to DB | containers not healthy | `docker compose ps`, wait for MariaDB |
| Provision 503 | cluster `status != active` | fix SSH test / cluster row |
| Job stuck queued | webhook not reaching API | fix APP_URL, tunnel, firewall |
| `tenant_not_ready` 503 on users:* | normal after provision success | wait for `ProbeCustomerReadinessJob` (~minutes) |
| meMail blank / HTTP 200 length 0 | empty `mework360_memail` bind-mount | sync repo to expected path |
| NC 503 `#core-updater` on all routes | `installed_version` DB > mounted app | align `oc_appconfig` or update mounted `info.xml` |
| NC crash / theme errors | `me360_theme` + missing `themes/me360/config/*` | fix theme mount or disable app via SQL |
