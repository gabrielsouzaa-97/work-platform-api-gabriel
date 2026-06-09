# Provision & customer lifecycle

End-to-end flow when creating a new customer (tenant) on Nextcloud via the deployer.

## Sequence diagram

```text
Operator/API                Deployer API              Upstream (SSH)           Webhook
     |                           |                         |                    |
     | POST /api/customers       |                         |                    |
     |-------------------------->|                         |                    |
     |                           | SSH nextcloud-manage    |                    |
     |                           | <slug> create --async   |                    |
     |                           |------------------------>| enqueue job        |
     |                           |<-------- { job_id } ----|                    |
     | 202 Customer queued       |                         |                    |
     |<--------------------------|                         |                    |
     |                           |                         | worker runs        |
     |                           |                         | create tenant      |
     |                           | POST /api/jobs/hook     |                    |
     |                           |<-----------------------------------------------|
     |                           | job.finished success    |                    |
     |                           | status=provisioning_finishing                 |
     |                           | dispatch ProbeCustomerReadinessJob            |
     |                           | SSH occ-exec user:list  |                    |
     |                           |------------------------>|                    |
     |                           | probe OK → active       |                    |
     |                           | users:* now allowed     |                    |
```

## Entry points

| Channel | Route / component | Auth |
|---------|-------------------|------|
| REST | `POST /api/customers` | Bearer Sanctum (`/api-keys`) |
| Panel | Livewire `Customers\Create` | Session (admin/operador) |

Core action: `ProvisionCustomerAction` → `SshClient::runAsync('nextcloud-manage', [$slug, $domain, 'create', ...])`

## Payload rules

- Slug: validated by `App\Rules\Slug` (lowercase, hyphens, no `_`)
- Idempotency: UUID persisted **before** SSH call
- Callback URL: `config('app.url').'/api/jobs/hook?cluster='.$cluster->id` — **must be HTTPS and reachable from upstream**
- Branding:
  - Inline `branding.logo_data_url` / `background_data_url` in stdin if ≤ 256KB total
  - Larger files → SFTP staging (`inboxInit` + `sftpUpload` + `--staging-id`)
- Sensitive data: **stdin only**, never argv

## Customer status machine (provision path)

| Status | Meaning |
|--------|---------|
| `provisioning` | Job dispatched, waiting terminal webhook |
| `provisioning_finishing` | Webhook success; readiness probe running |
| `active` | Probe passed (`occ-exec user:list` OK) — `users:create` / `users:delete` allowed |
| `failed` | Terminal failure; ghost slug may block re-provision (ISSUE-018) |

`users:*` on `provisioning_finishing` → **503** `tenant_not_ready` (ISSUE-010 / F8).

## Local test provision (Tier 2)

### Via API

```bash
# Get token from panel /api-keys or create via tinker
curl -X POST http://localhost:8080/api/customers \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "slug": "acme-local-test",
    "domain": "acme.local.test",
    "cluster_server_id": "<real-cluster-uuid>",
    "email": "admin@acme.local.test"
  }'
```

### Via panel

1. Login → Customers → Create
2. Select real cluster (not `dev-cluster-local` unless SSH works)
3. Submit → note `job_id` on queue page

### Confirm completion

```bash
docker compose exec -T app php artisan tinker --execute="
  \$c = App\Models\Customer::where('slug','acme-local-test')->first();
  \$j = App\Models\Job::where('customer_slug','acme-local-test')->latest()->first();
  echo 'customer='.$c?->status.' job='.$j?->state.' summary='.(empty(\$j?->summary)?'no':'yes');
"
```

Or simulate webhook in tests — see `tests/Feature/E2E/CriticalFlowsTest.php`.

## Post-provision operations

| Operation | Mechanism |
|-----------|-----------|
| Sync list from upstream | `php artisan customers:sync` |
| User/group/apps lifecycle | `LifecycleAsyncAction` → SSH async |
| OCC passthrough | `OccPassthroughService` → SSH sync |
| Cancel job | `CancelJobAction` → SSH cancel |
| Remove customer | `RemoveCustomerAction` → SSH remove |

All lifecycle async commands use `JobTypeTranslator::cmdToCliArgv()` — never raw `cmd_canonical` in argv.

## Failure mapping (provision)

| HTTP | error | Typical cause |
|------|-------|---------------|
| 409 | `idempotency_conflict` | duplicate idempotency key |
| 409 | `state_conflict` | slug exists upstream |
| 422 | validation | bad slug/domain |
| 502 | `upstream_error` | SSH exit ≠ 0 |
| 503 | `cluster_unreachable` | SSH down or cluster inactive |

## Related code paths

- `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
- `app/Modules/Jobs/Services/WebhookHandler.php` (`applyFinishedEvent`)
- `app/Jobs/ProbeCustomerReadinessJob.php`
- `app/Modules/Customers/Services/CustomerReadinessProbe.php`
