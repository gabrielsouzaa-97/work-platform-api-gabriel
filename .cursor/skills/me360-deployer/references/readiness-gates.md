# Readiness gates

Use these tables when answering "is it ready to deploy?" or "can we provision now?"

## R1 — Code gate

```bash
docker compose exec -T app php artisan test --parallel
# CI equivalent: lint (pint) + test + composer audit
```

| Check | Pass criteria |
|-------|---------------|
| Tests | 0 failures (skipped contract tests OK) |
| Lint | `./vendor/bin/pint --test` |
| Security | `composer audit --no-dev --locked` |

## R2 — Cross-repo gate (ISSUE-022)

Required when release touches webhook payload, branding stdin, OCC, or CLI argv.

| Frente | Upstream issue | API state | Pass when |
|--------|----------------|-----------|-----------|
| Webhook `exit_code` / `log_tail` | scripts #23 | handler persists fields | new jobs have non-null `exit_code` |
| Branding on create | CONTRACTS §3.9 | F13 merged | logo visible on tenant |
| OCC allowlist | scripts #22 | 403/501 documented | P-15 matrix green or endpoints removed |

**Deploy order**: upstream first, API second.

## R3 — Container gate

```bash
docker compose ps
```

All of: `app`, `nginx`, `database`, `redis`, `worker` → `healthy` (mailpit optional).

## R4 — Application gate

```bash
docker compose exec -T app php artisan migrate:status   # no Pending
docker compose exec -T app php artisan about | grep -i key   # APP_KEY set
```

Production only:

```bash
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
```

## R5 — HTTP gate

```bash
curl -sf http://localhost:8080/up    # local
curl -sf https://deployer.mework360.com.br/up   # prod
```

## R6 — SSH gate (required for provision)

Panel: Cluster Servers → **Testar Conexão**

Or tinker:

```bash
docker compose exec app php artisan tinker --execute="
  \$c = App\Models\ClusterServer::where('status','active')->first();
  \$r = app(App\Modules\Core\Ssh\SshClientInterface::class)
    ->run(\$c, 'nextcloud-manage', ['version'], null, 15);
  echo \$r->stdout;
"
```

Pass: exit 0, JSON or version string returned.

## R7 — Webhook reachability gate

Upstream worker must POST to `{APP_URL}/api/jobs/hook?cluster={uuid}`.

| Environment | Requirement |
|-------------|-------------|
| Production | Public HTTPS, IP whitelist if configured |
| Local | ngrok/cloudflared; update `.env` APP_URL; restart app+worker |

Test manually (replace secret):

```bash
# Generate valid HMAC per VerifyWebhookHmac middleware — prefer real upstream callback
docker compose exec app tail -f storage/logs/laravel.log | grep webhook
```

## R8 — E2E job gate (ISSUE-023)

Checklist after deploy:

- [ ] Dispatch one async job (`users:create` or test provision)
- [ ] Wait for `job.finished` webhook (or `jobs:poll-stuck` within 5 min)
- [ ] `jobs.state` terminal (`success` / `failed` / `cancelled`)
- [ ] `jobs.summary` not empty (or `JobLogFetcher` filled it)
- [ ] Panel `/queue/{id}` shows log lines

Production snapshot 2026-06-02: 1/5 jobs had null summary — **not fully ready** until upstream #23 + F10.3 validation complete.

## OPS extras

| Item | Check | Notes |
|------|-------|-------|
| `failed_jobs` table | `migrate:status` | OPS-001 — optional for local queue visibility |
| Scheduler | `schedule:list` | cron must run on prod VM |
| Worker upstream | `systemctl status nextcloud-saas-worker` | on upstream host, not API VM |

## Agent response template

When reporting readiness, use:

```markdown
## Readiness report

| Gate | Status | Evidence |
|------|--------|----------|
| R1 Code | ✅/❌ | N tests, CI link |
| R2 Cross-repo | ✅/❌/N/A | ... |
| R3 Containers | ✅/❌ | docker compose ps |
| R4 App | ✅/❌ | migrate status |
| R5 HTTP | ✅/❌ | /up code |
| R6 SSH | ✅/❌/N/A | test connection |
| R7 Webhook | ✅/❌/N/A | APP_URL |
| R8 E2E job | ✅/❌/N/A | job_id ... |

**Verdict**: READY / NOT READY — <one line reason>
**Next action**: <single concrete step>
```
