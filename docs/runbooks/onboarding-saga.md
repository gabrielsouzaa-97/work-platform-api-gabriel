# N34 — Runbook saga de onboarding

> Saga composta `POST /api/v1/onboarding` → steps assíncronos via `PlatformPort` + readiness gate.

## Fluxo resumido

| Step | Ação | Async | Gate |
|------|------|-------|------|
| `provision_tenant` | `ProvisionCustomerAction` | job SSH/agent | webhook `job.finished` |
| `wait_readiness` | `CustomerReadinessProbe` | poll local | `tenant_not_ready` até OCC OK |
| `create_admin` | `LifecycleAsyncAction` (`users:create`) | job | webhook |
| `enable_apps` | `LifecycleAsyncAction` (`apps:enable`) | job | webhook |
| `set_branding` | `PlatformPort::setBranding` | sync/async | D-02 → `skipped` |

`correlation_id` da saga = `onboarding.id` → propagado em cada `jobs.correlation_id` (ver N32 observabilidade).

## Estados terminais

| `onboarding.state` | Significado | Ação on-call |
|--------------------|-------------|--------------|
| `completed` | Todos os steps obrigatórios OK | Nenhuma |
| `failed` | Step crítico falhou (provision/admin/apps) | Ver job + logs; runbook parcial abaixo |
| `partial` | Tenant operacional mas step opcional falhou/skipped (ex. branding D-02) | Informar cliente; branding manual ou após D-02 |
| `running` | Saga em progresso | Poll `GET /v1/onboarding/{id}` |

## Falha parcial por step

### `provision_tenant` failed

- Tenant ghost pode existir localmente; upstream pode não ter criado instância.
- Verificar `GET /v1/jobs/{job_id}` (job do step) e logs no painel `/queue/{job_id}`.
- **Retry seguro:** novo slug OU remover ghost e re-POST onboarding (idempotency key diferente).
- **Não** re-POST com mesmo slug se customer ainda existe (422 / state conflict).

### `wait_readiness` pending (`tenant_not_ready`)

- Customer em `provisioning_finishing`; `ProbeCustomerReadinessJob` faz backoff até timeout (~20 min).
- Poll `GET /v1/onboarding/{id}` — step `wait_readiness` com `status: pending`.
- Se timeout: customer → `failed`; saga não avança. Investigar OCC/upstream (cluster health).
- **Retry seguro:** após cluster OK, operador pode forçar probe (`customers:sync` + readiness manual) ou re-provision com slug novo.

### `create_admin` / `enable_apps` failed

- Tenant pode estar `active` mas sem admin/apps.
- Ver job terminal + `summary` no registro local.
- **Retry seguro:** `POST /v1/tenants/{slug}/users` ou `POST /v1/tenants/{slug}/apps` (scopes adequados) — não duplicar onboarding saga.

### `set_branding` skipped (`capability_not_available`)

- D-02 allowlist upstream; step marcado `skipped`, saga `partial`.
- **Retry seguro:** após D-02 resolvido, `PUT /v1/tenants/{slug}/branding` isolado.
- Não re-executar saga completa só por branding.

## Rastreio `correlation_id`

1. Anotar `onboarding_id` / `correlation_id` da resposta 202.
2. Buscar jobs: `SELECT job_id, job_type, state, correlation_id FROM jobs WHERE correlation_id = '<onboarding_id>'`.
3. Logs app: filtrar `correlation_id` no contexto (webhook handler, transport observability).
4. Métricas N32: `jobs:observability-check` — jobs presos > SLA, webhook ausente, paridade SSH vs agent.

Ver também: `config/observability.php`, `TransportObservability`, sprint N32.7 alertas.

## Game-day checklist

1. `GET /api/v1/onboarding/{id}` responde 200 com `steps[]` coerente.
2. Provision webhook dispara readiness gate (`wait_readiness` pending ou advance).
3. Após readiness, `current_step` → `create_admin`.
4. Branding bloqueado retorna step `skipped` sem vazar `subcmd`/`exit_code`.
5. `npx @redocly/cli lint docs/openapi-external.yaml` — 0 errors no CI.
6. Replay idempotente (24h) não duplica tenant.

## Comandos úteis

```bash
# Status saga (Bearer onboarding:run)
curl -sS -H "Authorization: Bearer $API_KEY" \
  "https://deployer.mework360.com.br/api/v1/onboarding/$ONBOARDING_ID"

# Jobs correlacionados (SQL local)
psql "$DATABASE_URL" -c \
  "SELECT job_id, job_type, state, correlation_id FROM jobs WHERE correlation_id = '$ONBOARDING_ID';"

# Observabilidade transporte (N32)
php artisan jobs:observability-check
```

## Referências

- ADR Fase 4 / ISSUE-038 — `docs/ROADMAP.md` sprint N34
- Contrato externo — `docs/openapi-external.yaml`, `docs/CONTRACTS-V1.md`
- Readiness gate F8 — `ProbeCustomerReadinessJob`, `CustomerReadinessProbe`
- Observabilidade N32 — `app/Modules/Jobs/Services/TransportObservability.php`
