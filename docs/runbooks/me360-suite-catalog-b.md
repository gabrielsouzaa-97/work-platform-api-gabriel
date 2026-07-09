# ISSUE-057 opção B — Suite catalog me360 (upstream)

> **Blocker upstream:** `ISSUE-057-B-upstream` em `work-platform-scripts` — instalação real da suíte me360 no fluxo `--suite-catalog`.

## Contexto

O readiness gate legado (`image_mode=false`) exige apps `mework360_memail` e `me360_theme` ativos no tenant. Antes da opção B, payloads default `{}` com `suite_catalog=true` eram rejeitados na borda com `422 LEGACY_READINESS_UNSATISFIABLE` (Sprint F28).

A fatia API (Sprint N48) prepara o control plane para reabrir provision legado **quando**:

1. O cluster tem `cluster_servers.legacy_me360_capable = true` (flag admin).
2. O `suite_catalog.json` local (resolvido por `SuiteCatalogPathResolver`) lista `mework360_memail` e `me360_theme` com `status: active`.

Isso **não** instala os apps no host — apenas alinha validação HTTP com o contrato upstream esperado.

## Contrato upstream (`work-platform-scripts`)

Quando `manage.sh create` roda com `--suite-catalog`, o catálogo referenciado (`releases/suite_catalog.json` no bundle de releases do host) **deve** incluir e instalar a suíte me360:

| `app_id` | `status` mínimo |
|----------|-----------------|
| `mework360_memail` | `active` |
| `me360_theme` | `active` |

Sem esses entries, o tenant pode passar na borda API (se `legacy_me360_capable`) mas **falhar** no readiness gate SSH após provision.

### Checklist operador (pós-upstream)

1. Atualizar `releases/suite_catalog.json` no bundle de releases do cluster com `mework360_memail` + `me360_theme` (`active`).
2. Garantir que `create --suite-catalog` habilita/instala esses apps (validar em canário LAB).
3. Sincronizar catálogo no control plane: `php artisan app-catalog:sync`.
4. Marcar cluster como capaz: `legacy_me360_capable = true` (ver `docs/OPERATIONS.md`).
5. Canário: `POST /api/v1/tenants` payload default `{}` (sem `image_mode`) → **202**, depois readiness PASS.

## Estado N48 (API only)

| Componente | Status |
|------------|--------|
| Migration `legacy_me360_capable` | Entregue (N48) |
| `SuiteCatalogAppLister` + `ProvisioningReadinessValidator` | Entregue (N48) |
| Fixture `tests/fixtures/suite_catalog_me360.json` | Entregue (N48) |
| Install real no host (`work-platform-scripts`) | **Pendente** — `ISSUE-057-B-upstream` |

## Referências

- `docs/ISSUES.md` — ISSUE-057 (ADOPT_A_WITH_B)
- `app/Modules/Customers/Contracts/ProvisioningReadinessContract.php` — lista canônica de apps legados
- `tests/Feature/Customers/Me360SuiteCatalogReadinessTest.php` — contrato readiness N48
