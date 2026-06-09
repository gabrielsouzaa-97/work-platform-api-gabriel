# Production deploy & version update

Source of truth: `docs/RUNBOOK.md §1`, `docs/CI-CD.md`. CD is **manual** (no GitHub Actions deploy job).

## Preconditions

- [ ] CI green on `main` (or release tag)
- [ ] Upstream deployed first if contract changed (ISSUE-022)
- [ ] SSH access to VM
- [ ] Backup DB if migration is non-trivial

## Standard update (existing VM)

```bash
ssh operador@<VM-IP>
cd /opt/mework360-deployer

git fetch origin
git checkout main && git pull origin main

docker compose build --target production
docker compose up -d

# Wait healthy
docker compose ps

docker compose exec app php artisan migrate --force

# ONLY if APP_KEY already in .env
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache

docker compose logs --tail=50 app
```

## First deploy (new VM)

See `docs/INFRASTRUCTURE.md §6` + `docs/RUNBOOK.md §1`:

1. Provision VM (4 vCPU, 8 GB RAM, Docker installed)
2. Clone repo to `/opt/mework360-deployer`
3. `cp .env.example .env` — fill `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, DB passwords
4. `docker compose run --rm app php artisan key:generate --show` → paste into `.env`
5. Build production target, `up -d`, migrate, seed (first time only), config:cache
6. TLS on nginx (Let's Encrypt)
7. Smoke: `/up`, login, SSH test connection on cluster

## Rollback

```bash
cd /opt/mework360-deployer
git checkout <previous-tag-or-sha>
docker compose build --target production
docker compose up -d
docker compose exec app php artisan migrate:rollback --step=1   # only if last migration safe to reverse
```

## After deploy — mandatory smoke (ISSUE-023)

```bash
curl -sf https://deployer.mework360.com.br/up

docker compose exec app php artisan migrate:status

# Trigger test job (via API or tinker), then:
docker compose exec app php artisan tinker --execute="
  \$j = App\Models\Job::latest()->first();
  echo \$j?->job_id.' summary='.(\$j?->summary ? 'ok' : 'empty');
"
```

Panel: open `/queue/{job_id}` — logs must not show "Nenhum log disponível" if ISSUE-014 fix is deployed.

## Webhook secret rotation (during or after deploy)

Order matters:

1. Rotate in panel (grace 24h on old secret)
2. On upstream: `sudo nextcloud-manage config set-webhook-secret ...`
3. `sudo systemctl restart nextcloud-saas-worker` (ISSUE-002 — worker caches secret)
4. Verify `audit_logs` action `webhook_received`, not `webhook_invalid_signature`

## Cross-repo version matrix

When releasing a coordinated version:

| Change type | Deploy order | Validation |
|-------------|--------------|------------|
| Webhook fields (`exit_code`, `event`) | Upstream → API | ISSUE-023 + webhook audit |
| New CLI verb / argv | Upstream → API | `UpstreamContractTest` |
| Branding stdin shape | Upstream → API | provision with logo e2e |
| API-only (UI, DB index) | API only | CI + smoke `/up` |
| OCC allowlist | Upstream (or API despublish) | P-15 matrix |

## Scheduled jobs (must be running)

Laravel scheduler requires cron on VM or `schedule:work` container:

| Command | Schedule |
|---------|----------|
| `customers:sync` | daily 03:00 |
| `jobs:poll-stuck` | every 5 min |
| `cluster:health-check` | every 5 min |
| `clean:expired-webhook-secrets` | daily 03:00 |
| `audit:purge` | monthly |

Verify: `docker compose exec app php artisan schedule:list`
