<!-- FINDINGS-INDEX
synced_at: 2026-07-08
open_critical: 0
open_high: 9
open_medium: 52
open_low: 48
sprints_with_open_blockers: []
notes: Sprint F24 APROVADA — CQ-F23-001..003 + CQ-F17-001..004 + CQ-N40-003 + QA-N40-003/004 validados (PR #160 merge 5addd2f). 4 novos non-blocking CQ-F24-001..003 + INT-F24-001.
FINDINGS-INDEX -->


# Findings — mework360-deployer

> Fonte de verdade para findings de QA, auditoria e validação.

## Estatísticas

| Sprint | CRITICAL | HIGH | MEDIUM | LOW | Pendentes | Corrigidos | Validados |
|--------|----------|------|--------|-----|-----------|------------|-----------|
| D1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| D2 | 0 | 0 | 0 | 1 | 0 | 1 | 0 |
| D3 | 0 | 2 | 7 | 1 | 1 | 3 | 6 |
| D4 | 0 | 2 | 4 | 3 | 4 | 3 | 2 |
| D5 | 0 | 0 | 1 | 3 | 0 | 4 | 0 |
| D6 | 0 | 0 | 2 | 3 | 2 | 3 | 0 |
| D7 | 0 | 2 | 5 | 0 | 0 | 7 | 0 |
| D8 (DBA) | 0 | 2 | 3 | 4 | 2 | 9 | 0 |
| D8 (SEC) | 0 | 5 | 7 | 5 | 4 | 13 | 0 |
| N1 | 0 | 3 | 8 | 12 | 23 | 0 | 0 |
| F5 | 1 | 6 | 12 | 8 | 0 | 12 | 15 |
| F8 | 0 | 0 | 2 | 2 | 2 | 6 | 6 |
| F9 | 0 | 0 | 3 | 2 | 5 | 0 | 0 |
| F10 | 0 | 0 | 1 | 0 | 0 | 1 | 0 |
| F11 | 1 | 2 | 4 | 0 | 0 | 1 | 6 |
| F12 | 0 | 0 | 1 | 0 | 0 | 1 | 0 |
| F13 | 0 | 2 | 2 | 0 | 0 | 3 | 1 |
| F14 | 0 | 2 | 1 | 0 | 3 | 0 | 0 |
| F15 | 0 | 0 | 0 | 2 | 2 | 4 | 1 |
| N30 | 0 | 2 | 0 | 0 | 0 | 2 | 2 |
| N32 | 0 | 7 | 0 | 0 | 1 | 6 | 6 |
| N34 | 0 | 3 | 0 | 0 | 0 | 3 | 3 |
| N19 | 0 | 2 | 9 | 6 | 15 | 0 | 2 |
| N21 | 0 | 5 | 0 | 0 | 0 | 0 | 5 |
| N29 | 0 | 3 | 0 | 0 | 0 | 0 | 3 |
| PMO | 0 | 0 | 1 | 1 | 2 | 0 | 0 |
| N40 | 0 | 0 | 4 | 5 | 0 | 6 | 9 |
| N41 | 0 | 2 | 1 | 1 | 4 | 0 | 0 |
| N42 | 0 | 0 | 1 | 1 | 2 | 0 | 0 |
| N43 | 0 | 3 | 5 | 2 | 10 | 0 | 0 |
| F17 | 0 | 0 | 0 | 0 | 0 | 0 | 7 |
| F18 | 0 | 0 | 2 | 5 | 0 | 0 | 7 |
| F19 | 0 | 0 | 0 | 2 | 0 | 0 | 2 |
| F20 | 0 | 0 | 0 | 0 | 0 | 0 | 2 |
| N45 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| N46 | 0 | 0 | 0 | 0 | 0 | 8 | 8 |
| F23 | 0 | 0 | 0 | 0 | 0 | 0 | 3 |
| F24 | 0 | 0 | 0 | 4 | 4 | 0 | 9 |

> **Validação F24 R1** (2026-07-08, `/qa validar F24`): scope = PR #160 merge `5addd2f` (branch `campanha/fix-f24-occ-polish`). **Preflight**: PROC-025/027 PASS. **Testes**: validation-stamp APROVADA; CI PR #160 verde (Pest/Lint/Security/OpenAPI/Docker production/coverage/security-review). **auditor-senior** → APROVADA (9/9 findings-alvo). **Findings-alvo validados**: CQ-F23-001..003, CQ-F17-001..004, CQ-N40-003, QA-N40-003/004 (9/9). **Deploy LAB**: SHA `5addd2f99794732d4f89e774deb04366882e7490` @ `api.lab.mework360.com.br`; migration `tenant_groups` Ran [7]; smoke `/up`+`/login` 200. **Resultado: APROVADA** — 0 CRITICAL/HIGH; 4 novos non-blocking (`CQ-F24-001`..`003` + `INT-F24-001`).

### Findings — Sprint F23 (validação R1)

#### [CQ-F23-001] — Regras de nome de grupo duplicadas (DRY)
- **Severidade**: MEDIUM
- **Tipo**: maintainability
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php:673-686` + `app/Http/Requests/Lifecycle/CreateGroupRequest.php:19-29`
- **Sprint origem**: F23 (post-fix review)
- **Status**: validado (F24)
- **Esforço**: P
**Descrição**: `groupNameRules()` duplica regex, max length e closure de nome reservado `admin` já presentes em `CreateGroupRequest`. Comportamento correto, mas duas fontes podem divergir.
**Correção sugerida**: Extrair regras compartilhadas (ex. `TenantGroupNameRules::forAttribute('name'|'groupName')`) e reutilizar em API + Livewire.

#### [CQ-F23-002] — Métrica `updated` do sync subreporta em cenário misto
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Modules/Customers/Services/TenantGroupSyncService.php:77-79`
- **Sprint origem**: F23 (post-fix review)
- **Status**: validado (F24)
- **Esforço**: P
**Descrição**: `updated` só incrementa quando `inserted === 0`. Sync com inserts e backfill de `synced_at` simultâneos subreporta refreshes.
**Correção sugerida**: Sempre `$report->updated += $refreshedExisting` ou incrementar por row no loop.

#### [CQ-F23-003] — Poll concorrente de grupo pode sobrescrever feedback
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php:566-589`, `688-718`
- **Sprint origem**: F23 (post-fix review)
- **Status**: validado (F24)
- **Esforço**: P
**Descrição**: `pollPendingGroupJob()` executa create e delete em sequência; cada `pollSingleGroupJob()` chama `clearMessages()` no terminal. Se ambos jobs terminam no mesmo tick de 3s, a segunda mensagem apaga a primeira.
**Correção sugerida**: Acumular mensagens, poll de um job por tick, ou handlers `wire:poll` separados por tipo.

#### [INT-F24-001] — Poll de usuário pode apagar mensagem de grupo no mesmo tick
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: Integrator
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php` (pollPendingUserJob)
- **Sprint origem**: F24 (integration check)
- **Status**: pendente
- **Esforço**: P
**Descrição**: `pollPendingUserJob` chama poll de grupo e depois handlers de usuário que fazem `clearMessages()` incondicionalmente — mensagem de grupo pode sumir se job de usuário termina no mesmo tick.
**Correção sugerida**: Estender `preserveMessages` aos handlers de usuário ou não limpar quando outro job do tick ainda não finalizou.

#### [CQ-F24-001] — handleUserDeleteJobTerminal reutiliza projector de create
- **Severidade**: LOW
- **Tipo**: maintainability
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Sprint origem**: F24 (validação R1)
- **Status**: pendente
- **Esforço**: P
**Descrição**: `handleUserDeleteJobTerminal` chama `projectUserCreateIntoReadModel()` (nome enganoso); projector roteia delete corretamente.
**Correção sugerida**: Renomear para `projectUserJobIntoReadModel`.

#### [CQ-F24-002] — TenantGroupNameRules::forAttribute ignora parâmetro
- **Severidade**: LOW
- **Tipo**: maintainability
- **Auditoria**: Senior
- **Arquivo**: `app/Modules/Customers/Support/TenantGroupNameRules.php`
- **Sprint origem**: F24 (validação R1)
- **Status**: pendente
- **Esforço**: P
**Descrição**: `$attribute` não é usado; API e painel compartilham o mesmo array.
**Correção sugerida**: Remover parâmetro ou documentar reserva futura.

#### [CQ-F24-003] — Poll grupo dual-success ainda sobrescreve mensagem
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Sprint origem**: F24 (validação R1)
- **Status**: pendente
- **Esforço**: P
**Descrição**: Com `preserveMessages`, dual success no mesmo tick ainda sobrescreve `successMessage` (teste cobre success+error).
**Correção sugerida**: Acumular/concatenar mensagens quando preserveMessages.



> **Validação N45+N46 R1** (2026-07-08, `/qa validar`): scope = PR #158 merge `f465768` (delta `8262408..f465768`, ISSUE-056). **Preflight**: PROC-025/027 PASS; parity gate skip. **Testes**: Pest Docker **118 passed, 366 assertions** (WebhookTenantGroupProjection + TenantGroupSync + OccPanel + Lifecycle + CreateUserPolicy + UserCreateTemplateResolver); CI PR #158 verde (Pest/Lint/Security/OpenAPI). **auditor-senior** ([`9a3dbedd`](9a3dbedd-f056-4565-952f-759ca30652d8)) → 0 CRITICAL, **1 HIGH**, 5 MEDIUM, 2 LOW. **Resultado: REPROVADA** — `CQ-N46-001` HIGH: projector só aceita `groups:create`/`groups:delete` mas jobs persistem `group_create`/`group_delete` via `JobTypeTranslator` (padrão correto já existe em `TenantUserProjector`).

### Findings — Sprint N46 (validação R1)

#### [CQ-N46-001] — TenantGroupProjector ignora job_type persistido
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Modules/Customers/Services/TenantGroupProjector.php:13-31`
- **Sprint origem**: N46 (task N46.2 — TenantGroupProjector + webhook hooks)
- **Status**: validado (F23)
- **Esforço**: P
**Descrição**: `LifecycleAsyncAction` persiste `job_type` via `JobTypeTranslator::cmdToJobType()` como `group_create`/`group_delete`, mas o projector só trata `groups:create`/`groups:delete`. Webhook terminal não projeta `tenant_groups` até sync manual.
**Correção sugerida**: Alinhar ao `TenantUserProjector`: `GROUP_CREATE_TYPES = ['group_create', 'groups:create']` e `GROUP_DELETE_TYPES = ['group_delete', 'groups:delete']`.

#### [CQ-N46-002] — Testes de projeção usam job_type incorreto (falso verde)
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: Senior
- **Arquivo**: `tests/Feature/Jobs/WebhookTenantGroupProjectionTest.php`
- **Sprint origem**: N46 (task N46.2)
- **Status**: validado (F23)
- **Esforço**: P
**Descrição**: Fixtures injetam `job_type => 'groups:create'|'groups:delete'`, mas jobs reais usam `group_create`/`group_delete`. Suite passa com CQ-N46-001 ativo.
**Correção sugerida**: Fixtures com `group_create`/`group_delete` + teste integrado LifecycleAsyncAction → webhook → assert row.

#### [CQ-N46-003] — Validação membership painel case-sensitive vs API
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php:404-405`
- **Sprint origem**: N46 (task N46.5)
- **Status**: validado (F23)
- **Esforço**: P
**Descrição**: Painel usa `in_array(..., true)` case-sensitive; API `TenantGroupMembership` compara `LOWER(name)`.
**Correção sugerida**: Reutilizar `TenantGroupMembership` ou lookup case-insensitive idêntico.

#### [CQ-N46-004] — createGroup/deleteGroup painel sem regex da API
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php:521-543`
- **Sprint origem**: N45 (task N45.2/N45.5)
- **Status**: validado (F23)
- **Esforço**: P
**Descrição**: Painel aceita `required|string|max:256` sem `regex:/^[a-zA-Z0-9._\- ]+$/` da API.
**Correção sugerida**: Extrair regras compartilhadas e aplicar no OccPanel.

#### [CQ-N46-005] — Criação de grupo não bloqueia nome reservado admin
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Requests/Lifecycle/CreateGroupRequest.php` + `OccPanel::createGroup`
- **Sprint origem**: N46
- **Status**: validado (F23)
- **Esforço**: P
**Descrição**: Membership bloqueia atribuir `admin`, mas create group não rejeita nome `admin`.
**Correção sugerida**: Rule case-insensitive `strtolower($name) === 'admin'` em CreateGroupRequest e OccPanel.

#### [CQ-N46-006] — Sem poll/refresh pós-create de grupo no painel
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php:519-536`
- **Sprint origem**: N45/N46
- **Status**: validado (F23)
- **Esforço**: M
**Descrição**: Após createGroup sucesso não há poll do job nem `loadGroups()` (assimetria com users).
**Correção sugerida**: Após fix CQ-N46-001, poll terminal group_create/delete ou reload no success.

#### [CQ-N46-007] — TenantGroupSyncReport.updated nunca incrementado
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Modules/Customers/Services/TenantGroupSyncService.php:52-72`
- **Sprint origem**: N46 (task N46.3)
- **Status**: validado (F23)
- **Esforço**: P
**Motivo LOW**: métrica operacional enganosa; sync funcional.
**Correção sugerida**: Incrementar `$report->updated++` em row existente ou remover campo.

#### [CQ-N46-008] — tenant-groups:sync exit 0 com falhas parciais
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Arquivo**: `app/Console/Commands/TenantGroupsSyncCommand.php:35-44`
- **Sprint origem**: N46 (task N46.3)
- **Status**: validado (F23)
- **Esforço**: P
**Motivo LOW**: cron não alerta; drift detectável via logs.
**Correção sugerida**: Retornar FAILURE se qualquer customer falhar.

> **Validação F19 R1** (2026-07-06, `/qa validar F19`): scope = delta `a623f8e...887b53f` (PR #144 — correção dos 7 findings F18). **Review**: senior+qa. **Preflight**: PROC-025/027 PASS; parity gate skip. **Testes**: Pest Docker **259 passed, 1075 assertions** (`Product/`, `Api/V1/`, `Database/`, `Customers/`). **auditor-senior** ([`d14c0e14`](d14c0e14-31da-447b-b86b-4bc58d4df922)) → 0 CRITICAL, 0 HIGH, 0 MEDIUM, 2 LOW. **Findings F18 validados**: CQ-F18-001, QA-F18-002, CQ-F18-002, CQ-F18-003, QA-F18-003, QA-F18-004, QA-F18-005 (7/7). **Resultado: APROVADA** — 0 CRITICAL/HIGH/MEDIUM; 2 LOW novos non-blocking.

### Findings — Sprint F19 (validação R1)

- **[CQ-F19-001] LOW — validado (F20)**: testes `empty-plan` (v1+legado) só assertam `422` + `assertJsonValidationErrors(['apps'])`, sem espelhar as asserções QA-F18-005 (`assertJsonMissingPath('error')` + `AuditLog policy_denied` false) dos testes de app fora do plano. **Correção**: asserções replicadas nos dois testes `empty-plan`. `tests/Feature/Product/PolicyResolverTest.php:269-308`.
- **[CQ-F19-002] LOW — validado (F20)**: `down()` de `2026_07_06_000002` re-adiciona `max_apps` incondicionalmente, mas `000001` (pós-hygiene) não define mais a coluna — rollback parcial (`--step=1`) em fresh install causa drift até re-migrar. **Correção**: `down()` guardado com `if (! Schema::hasColumn(...))`. `database/migrations/2026_07_06_000002_drop_max_apps_from_plans_table.php:20-25`.

> **Validação F20** (2026-07-06, hygiene pós-F19): scope = PR #145 (`2313ea1`). **Testes**: Pest Docker **14 passed** (`PolicyResolverTest` + `DropMaxAppsMigrationTest`). **Deploy LAB**: SHA `2313ea1`, `/up`+`/login` 200. **Resultado: concluída** — 2 LOW F19 corrigidos; sem novos findings.

### Findings — Sprint F18 (validação R1) (2026-07-06, `/qa validar F18`): scope = delta `661033e...e8b1ad6` (PR #143, 12 arquivos-fonte — remoção `max_apps`, enable via `PlanAppResolver`, ARCH-7). **Review**: senior+qa. **Preflight**: PROC-025/027 PASS; parity gate skip (delta sem `install.*`/`scripts/*.py`/`hooks/*.sh`). **Testes**: Pest Docker **120 passed, 591 assertions** (`Product/`, `Api/V1/`) incluindo os 2 novos casos de rejeição enable (v1+legado, app fora de `plan_apps` → 422, SSH não chamado). **auditor-senior** ([`bb41562f`](bb41562f-bfc4-4453-8695-4df944026b41)) → 0 CRITICAL, 0 HIGH, 1 MEDIUM, 2 LOW. **auditor-qa** ([`6ba5046a`](6ba5046a-c680-47e5-880b-d26dfb653bcf)) → 0 CRITICAL, 0 HIGH, 2 MEDIUM, 3 LOW (dedup: senior CQ-001 ≡ qa QA-001 = happy path enable). **Deploy LAB**: SHA `e8b1ad6`, migration `drop_max_apps` DONE (coluna ausente), `app-catalog:sync` OK, `/up`+`/login` 200. **Resultado: APROVADA** — 0 CRITICAL/HIGH; 2 MEDIUM + 5 LOW novos non-blocking em backlog.

### Findings — Sprint F18 (validação R1)

- **[CQ-F18-001 ≡ QA-F18-001] MEDIUM — validado (F19)**: enable com `plan_slug` + app ⊆ `plan_apps` sem teste de sucesso (202 + SSH `runAsync` uma vez) em v1 e legado; delta só adiciona casos de rejeição. **Correção sugerida**: 2 testes espelhando `PlanAppInheritanceTest` com mock SSH `shouldReceive('runAsync')->once()`. `tests/Feature/Product/PolicyResolverTest.php`.
- **[QA-F18-002] MEDIUM — validado (F19)**: edge plano com `plan_apps` vazio no caminho `enable` não coberto (só provision via QA-005 backlog). Plano sem junction + tenant com `plan_slug` → qualquer app deve dar 422. **Correção sugerida**: teste `empty-plan` sem inserts em `plan_apps` → `POST .../apps` 422 (v1+legado). `PlanAppResolver.php`, `PolicyResolverTest.php`.
- **[CQ-F18-002] LOW — validado (F19)**: `CustomerLifecycleController::enableApps` chama `$customer->loadMissing('plan')` mas o resolver usa `$customer->plan_slug` — relação nunca lida (código morto + query extra). **Correção sugerida**: remover `loadMissing('plan')`. `app/Http/Controllers/Api/CustomerLifecycleController.php:167`.
- **[CQ-F18-003] LOW — validado (F19)**: `create_plans_table` (000001) ainda define `max_apps`, removida logo em 000002 — ruído no histórico de schema. **Correção sugerida**: sprint de hygiene remove `max_apps` de 000001 (se política permitir squash). `database/migrations/2026_07_06_000001_create_plans_table.php:19`.
- **[QA-F18-003] LOW — validado (F19)**: migration `drop_max_apps_from_plans_table` sem teste de schema (`Schema::hasColumn('plans','max_apps')->toBeFalse()` + rollback `down()`/`up()`). `tests/Feature/Database/`.
- **[QA-F18-004] LOW — validado (F19)**: `PlanApiTest` não assere ausência de `max_apps` no `PlanResource` (`assertJsonMissing(['max_apps'])`) nem que POST com `max_apps` no body é ignorado — risco de drift de contrato. `tests/Feature/Product/PlanApiTest.php`.
- **[QA-F18-005] LOW — validado (F19)**: rejeição do enable pós-F18 não documenta por teste que é `422 validation` (não `plan_limit_exceeded`) nem que `AuditLog policy_denied` não é mais gerado. **Correção sugerida**: `assertJsonMissing` do code legado + `expect(AuditLog::where('action','policy_denied')->exists())->toBeFalse()`. `PolicyResolverTest.php`.

### Findings — Sprint F17 (validação R1) (2026-07-06, `/qa validar F17`): scope = delta `f73c149...661033e` (PR #142, 8 arquivos-fonte). **Review**: senior+qa. **Testes**: Pest Docker **185 passed, 789 assertions** (`Product/`, `Livewire/Customers/`, `Api/V1/`) incluindo os 3 testes novos F17 (PlanApi 404, AppCatalogSync fallback, resolver groups []). **auditor-senior** ([`8aba1a5a`](8aba1a5a-6116-45d1-91a9-d193115747df)) → 0 CRITICAL, 0 HIGH, 2 MEDIUM, 2 LOW. **Findings-alvo validados**: OPS-F16-001 (fallback catalog), CQ-N41-003 (PlanNotFound), CQ-N43-003 (groups override). **Deploy LAB**: SHA `661033e`, `app-catalog:sync` OK 11 apps sem workaround, `/up`+`/login` 200. **Resultado: APROVADA** — 0 CRITICAL/HIGH; 2 MEDIUM + 2 LOW novos non-blocking em backlog.

### Findings — Sprint F17 (validação R1)

- **[OPS-F16-001] MEDIUM — validado (F17.1)**: `app-catalog:sync` quebrava no container quando `platform.suite_catalog.path` apontava para sibling `work-platform-scripts` inexistente na imagem. Fix: `SuiteCatalogPathResolver` com fallback `storage/app/suite_catalog.json` + env `NC_SUITE_CATALOG_JSON`; `AppCatalogSyncService` e `SuiteCatalogValidator` adotam o resolver. Validado no deploy LAB `661033e` (sync 11 apps sem `docker compose cp`).
- **[CQ-F17-001] MEDIUM — validado (F24)**: emissão de `groups: []` depende de override pós-`build()` nos call sites, não de `UserCreateStdinPayload::build()` (que ainda omite `groups` vazio via `if ($groups !== [])`). Novo entry point que use `UserCreateTemplateResolver` sem o override regride silenciosamente. **Correção sugerida**: mover a emissão de groups vazio para `UserCreateStdinPayload` (flag tri-state `?array $groups` + `bool $emitEmptyGroups` ou helper `withGroups`), removendo os overrides duplicados de controller+Livewire. `app/Modules/Customers/Support/UserCreateStdinPayload.php:46-48`.
- **[CQ-F17-002] MEDIUM — validado (F24)**: OccPanel sem teste para override explícito de groups vazio (API tem cobertura; Livewire só cobre merge + override não-vazio). **Correção sugerida**: teste Pest em `OccPanelUserTemplateTest` com template com groups + `userGroups` que parseia para `[]` (ex.: `','`) asserindo stdin `"groups": []`. `app/Http/Livewire/Customers/OccPanel.php:356-368`.
- **[CQ-F17-003] LOW — validado (F24)**: `groups: null` no JSON é tratado como `[]` explícito (via `$request->has` + cast `(array) null`) em vez de herdar template. **Correção sugerida**: `$request->exists('groups') && is_array($request->input('groups')) ? $request->array('groups') : null`. `app/Http/Controllers/Api/CustomerLifecycleController.php:60`.
- **[CQ-F17-004] LOW — validado (F24)**: `SuiteCatalogPathResolver` sem unit test dedicado (só coberto indiretamente via `AppCatalogSyncCommandTest`). **Correção sugerida**: teste Pest stubando `config()` + temp files em `storage/app/` asserindo first-readable-wins, fallthrough de path inexistente, `RuntimeException` quando todos ilegíveis. `app/Modules/Integration/Support/SuiteCatalogPathResolver.php:11-29`.

> **Validação F16 R1** (2026-07-06, `/qa validar F16` via `/rock`): scope = sprint/F16 pós-merge PR #140. **Testes**: Pest Docker **33 passed** (suite F16) + **135 passed** regressão Product/Livewire/Jobs (402 assertions). **Fixes**: F16.1 sync `plan_apps` via API; F16.2 `is_default` lockForUpdate; F16.3 `max_users` inflight jobs + re-check no persist; F16.4/F16.5 testes legacy+OccPanel. **5 HIGH** N41–N43 → corrigido. **Resultado: APROVADA** — 0 HIGH pendentes da campanha ISSUE-051.

> **Validação N41+N42+N43 R1** (2026-07-06, `/qa validar` — campanha Product Governance ISSUE-051): scope = delta combinado `a1e5c79..sprint/N43` (9 commits, 73 arquivos, +4786 linhas; N41/N42 já em `main`, N43 = PR #140 aberto/CI verde). **Review**: senior+qa (conforme `review:` das 3 sprints). **Testes**: Pest Docker **179 passed, 527 assertions** nas suites do delta (`Product/`, `Livewire/Product/`, `Livewire/Customers/`, `Jobs/`); suite completa não medida por OOM local (`memory_limit`) — ambiente, não código. **auditor-senior** ([`77709b41`](77709b41-184e-4f47-bca1-a608c9cb80a5)) → 0 CRITICAL, 3 HIGH, 3 MEDIUM, 3 LOW. **auditor-qa** ([`ad825a89`](ad825a89-1ee9-498f-acd2-def3cd6c5681)) → 0 CRITICAL, 3 HIGH, 12 MEDIUM, 4 LOW. **Resultado: COM RESSALVAS** — features entregues e testadas, mas 5 HIGH pendentes (1 defeito funcional + 2 concorrência justificáveis para ferramenta interna + 2 gaps de teste). Nenhum HIGH bloqueia o merge de PR #140, mas **CQ-N41-002 deve ser corrigido antes de considerar Product Governance production-ready**.

### Findings HIGH — Campanha N41-N43 (pendentes)

- **[CQ-N41-002 ≡ QA-N41-001] HIGH — corrigido (F16.1)**: `POST/PATCH /api/v1/plans` persiste `app_ids` em `plan_apps`; `PlanResource` expõe apps; `Rule::exists` em FormRequests.
- **[CQ-N41-001] HIGH — corrigido (F16.2)**: `is_default` com `lockForUpdate` em `clearDefaultFlag`/`update`/`setAsDefault`.
- **[CQ-N43-001] HIGH — corrigido (F16.3)**: `max_users` conta `tenant_users` + jobs `users:create` inflight; re-check em `persistJobAndAudit`; `AuditLog` fora do rollback.
- **[QA-N43-001] HIGH — corrigido (F16.4)**: teste legado `POST /api/customers/{slug}/apps/enable` → 422 `plan_limit_exceeded`.
- **[QA-N43-002] HIGH — corrigido (F16.5)**: teste Livewire OccPanel `max_users` excedido.

### Findings MEDIUM/LOW — Campanha N41-N43 (backlog non-blocking)

- **[CQ-N41-003] MEDIUM — validado (F17.2)**: `PlanV1Controller` agora retorna `DomainError::PlanNotFound` (404 `plan_not_found`) em `show`/`update`; testes `PlanApiTest` GET/PATCH plano inexistente. Validado `/qa validar F17` R1.
- **[CQ-N43-002] MEDIUM — descartado**: Decisão produto: apps por designação `plan_apps`; `max_apps` removido F18.
- **[CQ-N43-003] MEDIUM — validado (F17.3)**: `groups: []` explícito sobrescreve groups do template — `UserCreateTemplateResolver::resolve` usa `?array $explicitGroups` (null=herda, []=limpa); call sites API+OccPanel + stdin emitem `groups: []`. Validado `/qa validar F17` R1.
- **[CQ-N42-001] LOW**: `PlanAppResolver::validateSubset` ignora silenciosamente app IDs não-string/vazios (`PlanAppResolver.php:60-63`).
- **[CQ-N43-004] LOW**: `PermissionsSchemaV1` não valida `schema_version` internamente (depende de regra duplicada nos FormRequests).
- **[CQ-N43-005] LOW**: rota API user-create não passa `origin` no `payload_sanitized` (projector defaulta `'api'`; trilha de auditoria incompleta).
- **QA MEDIUM/LOW (auditor-qa `ad825a89`)**: QA-004 boundary de sucesso de limites; QA-005 plano zero-apps no provision; QA-006 401 não-autenticado nos 3 recursos; QA-007 PATCH 403 scope; QA-008 404 GET/PATCH plans; QA-009 permissions malformadas (5+ casos); QA-010 template inativo rota legada; QA-011 OccPanel template inativo em `createUser`; QA-012 herança quota v1; QA-013 precedência quota plano×template; QA-014 `syncAppIds` update/detach/inválido; QA-015 regressão OccPanel template+poll; QA-016 `AppCatalogSync` error paths; QA-017 limites `null`; QA-018 assert fraco `max_apps` audit; QA-019 PATCH 404. Detalhes completos no output do subagent.

> **Re-validação N40 R2** (2026-07-05, `/qa validar`): revalidação independente (auditor-senior). **Testes**: Pest Docker **789 passed, 7 skipped** (2609 assertions). **auditor-senior R2** → **PASS** (0 findings) — confirmados item-a-item os 6 fixes R1 (`N40-001`, `CQ-N40-001` drift subadmin, `CQ-N40-002` guard admin plataforma, `QA-N40-001` summary-recovery, `QA-N40-002` parser tests, `QA-N40-006` senha API min 10) + guardrails #213 intactos (projector pós-transação non-fatal, password nunca na projeção, enforcement admin sem bypass, `origin` não vaza ao upstream). 3 LOW parkados confirmados non-blocking; nenhuma regressão nova. **CI**: PR #137 MERGEABLE/CLEAN, todos os checks verdes. **Resultado: APROVADA**.

> **Validação N40 R1** (2026-07-05, `/qa validar N40` via `/rock`): scope = `main...sprint/N40` (read model `tenant_users` + política no-client-admin, ISSUE-050). **Testes**: suite completa Docker **789 passed, 7 skipped** (2609 assertions); suites N40 93 passed. **verifier** → PASS_WITH_NOTES (guardrails #213 8/8; N40-001 origin=panel corrigido in-sprint). **auditor-senior** → PASS_WITH_NOTES (`CQ-N40-001` drift subadmin MEDIUM, `CQ-N40-002` guard admin plataforma no stale-delete MEDIUM — **ambos corrigidos in-sprint**; `CQ-N40-003` deleteUser refresh LOW backlog). **auditor-qa** → PASS_WITH_NOTES (25/25 cenários ROADMAP 1:1; `QA-N40-001` teste summary-recovery MEDIUM e `QA-N40-002` TenantUserListParserTest MEDIUM — **corrigidos in-sprint**; `QA-N40-006` senha API min 10 LOW — **corrigida**; `QA-N40-003`/`QA-N40-004` LOW backlog; `QA-N40-005` dedupe com CQ-N40-003). **Gate anti-tamper**: PASS (nenhum arquivo de gate no diff). **Resultado: APROVADA** — 0 CRITICAL/HIGH pendentes; 3 LOW non-blocking em backlog.

### Backlog LOW N40 (non-blocking)

- **[CQ-N40-003 ≡ QA-N40-005] LOW — validado (F24)**: `OccPanel::deleteUser` não recarrega lista pós-success (create tem poll; delete fica stale até "Atualizar"). `app/Http/Livewire/Customers/OccPanel.php:395-413`.
- **[QA-N40-003] LOW — validado (F24)**: falta teste cruzando webhook out-of-order (`job.started` após terminal) com projeção `tenant_users` intacta.
- **[QA-N40-004] LOW — validado (F24)**: teste de poll create success usa seed manual em vez de integração webhook→projector→poll.

> **Validação F15 R2** (2026-06-17, `/qa validar F15` via `/rock`): scope = follow-up `ce86325` (campanha `f15-authz-followup`). **Testes**: `ApiKeyAuthorizationTest` 9 passed + `CancelJobTest` 4 passed, 29 assertions (SQLite local). **auditor-senior R2** → **PASS** (0 HIGH). **Findings validados**: `CQ-F15-001`, `CQ-F15-002`, `CQ-F15-003`, `CQ-F15-005`, `SEC-V1-001`. **Backlog non-blocking**: `CQ-F15-007`, `CQ-F15-008` (LOW). **Hard Rule #2**: OK (0 arquivos fora whitelist). **Resultado: APROVADA**.

> **Validação N30 R1** (2026-06-17, `/qa validar N30`): scope = PR #115 + follow-up `837173c`. **Review**: senior+security (superfície externa `/api/v1`). **Testes**: ~35 Pest em `tests/Feature/Api/V1/`; CI verde (Lint, Test/Pest, composer audit, Redocly). **2 HIGH** detectados R1 e corrigidos in-sprint: `CQ-N30-001` (IDOR `GET /v1/jobs/{id}`), `SEC-N30-001` (vazamento `exit_code` em provision). **Resultado: APROVADA** — validation_gate_qa APROVADA; gate ADR Sprint 0 fechado.

> **Validação F15 R1** (2026-06-16, `/qa validar F15`): scope = delta `main...430078a` (ApiKey authz). **Testes**: `ApiKeyAuthorizationTest` 5 passed, 7 assertions (SQLite local, APP_KEY). **auditor-senior** → **FAIL** (`CQ-F15-001` HIGH — `DELETE /customers/{slug}` ignora `api.tenant`). **auditor-security** → PASS no gate literal F15 (`customers/{customer}/*`); 2 MEDIUM residuais (provision/delete). **SEC-V1-001** → correcao parcial validada (scopes + tenant em lifecycle/OCC); gap DELETE pendente via `CQ-F15-001`. **Hard Rule #2**: 3 arquivos probe fora de whitelist (`tests/_probe.*`). **Resultado: REPROVADA** — PROC-012 exige corrigir HIGH in-sprint (`F15.6`).

> **Validação N19 R2** (2026-06-12, `/qa validar N19`): correções CQ-N19-001 (ownership `operation_id` em `AgentEventHandler` + `ack` scoped) + QA-N19-001 (teste SFTP bypass com agent ON). **Testes**: 12 passed, 29 assertions (local SQLite). **Resultado: APROVADA** — 0 HIGH pendentes; 15 MEDIUM/LOW backlog non-blocking.

> **Validação N19 R1** (2026-06-12, `/qa validar N19`): scope = delta `3a8fb65..HEAD` (AgentUpstreamGateway, Provision/Remove actions, AgentEventHandler, AgentTransportCutoverTest, runbook N19). Senior R1 + QA R1. **Testes**: CI verde `29d54b8` (GitHub Actions workflow CI, SQLite in-memory). **2 HIGH pendentes** (`CQ-N19-001` segurança operation_id; `QA-N19-001` gap teste SFTP+agent). **Resultado: REPROVADA** — PROC-012 exige corrigir HIGH in-sprint antes de fechar N19.

> **Validação F5 R4** (2026-06-02, `/qa validar F5` + subagentes): scope = delta backlog (LifecycleAsyncAction refactor, OccPanel short-circuit, LifecycleTest boundaries, UpstreamContractTest, JobTypeTranslatorTest). **Testes**: 123 passed, 6 skipped, 241 assertions (Docker). **auditor-senior** → PASS_WITH_NOTES (0 HIGH). **auditor-qa** → FAIL bruto (3 HIGH out-of-contract pré-existentes: quotaUsername, apps bulk disable, idempotency orphan sem job_id — triados como Notes Non-Blocking; não regressões R4). **8 findings backlog** → Validado (CQ-F5-004/005/006, QA-F5-009/011/012/013/014). **Resultado: APROVADA COM RESSALVAS** — Hard Rule #2 (5 arquivos código não commitados). E2E: ISSUE-007.
>
> **Re-validação F5 (R3)** (2026-06-02, `/qa validar R3`): scope = F5.11 (`OccPanel.php`, `occ-panel.blade.php`, `OccPanelTest.php`). Senior R3 + QA R3 — **0 findings novos**. `QA-F5-019` → **Validado**. Testes re-executados (Docker `app`, `.env` + `APP_KEY`): **`OccPanelTest` 25 passed, 55 assertions** (2026-06-02). **Resultado: APROVADA** — sem HIGH/CRITICAL F5 pendentes; 7 findings F5 LOW/MEDIUM em backlog (non-blocking). E2E browser: **ISSUE-007**.

> **Registro PMO / validação produção (2026-06-02)** — `/pmo` + SSH read-only `deployer.mework360.com.br` (`cf773dc`, `/up` 200). Novos: `DOC-001` (OpenAPI envelope global vs código `{ error }` + Resources — **ISSUE-021**), `OPS-001` (tabela `failed_jobs` ausente em prod — **ISSUE-023**). Cross-repo e F7/F10: **ISSUE-022**, **ISSUE-023**; F7 permanece `CQ-N1-001/002`, `QA-N1-001` (sprint ROADMAP F7). ISSUE-013: amostra prod 1/5 jobs com `exit_code`/`summary` null (não 100% como staging).

> **Validação F11 R1** (2026-05-24, `/qa validar R1`): senior (auditor-senior, `claude-4.6-sonnet-medium-thinking`) → **REPROVADA** (CRITICAL CQ-F11-001 + HIGH CQ-F11-002). QA (`gemini-3.1-pro`) → **REPROVADA** (HIGH QA-F11-001 + 3 MEDIUM). Convergência em FK RESTRICT bug (forceDelete bloqueado por jobs.customer_slug). **Todos os 7 findings corrigidos in-sprint** (R1 follow-up). Fix: `restore()+update()` em vez de `forceDelete` — preserva FK e audit trail. Testes adicionados: re-provisioning e2e, audit log assertion, mapLifecycleException coverage (3 testes), SshRemoteException apps. **Suite final: 394+ passed, 7 skipped**. **Resultado após R1: APROVADA** (aguarda suite completa).

> **Validação F9 R1** (2026-05-24, `/qa validar F9`): senior (auditor-senior, readonly) → PASS_WITH_NOTES (0 blockers). QA (auditor-qa, readonly) → 5 candidatos; após triagem: **5 registrados** (`QA-F9-001` MEDIUM downgrade de HIGH — side effect handler amplo; `QA-F9-002/003` MEDIUM test gaps; `QA-F9-004/005` LOW). Testes F9: **4 passed**. Full suite: **374 passed**, 7 skipped, 982 assertions. **ISSUE-012 core fix validado** (404/405 JSON sem `Accept`). **Resultado: APROVADA** — nenhum HIGH/CRITICAL pendente; MEDIUM/LOW backlogados.

> **Validação F8 R1** (2026-05-23): follow-up F8.7–F8.10. Testes F8: 46 passed. **APROVADA** — QA-F8-001/002/003/004/005/006/007/009/010 corrigidos; QA-F8-008/011 remanescentes (MEDIUM/LOW, non-blocking).

> **Pendentes pós-D8**: D4-F004/F008/F009/F005 (backlog), SEC-F013/F014/F015/F016 (backlog), DBA-F010/F011/F012 (backlog). Nenhum CRITICAL ou HIGH aberto.
>
> **Pendentes pós-N1** (2026-05-20): 3 HIGH abertos — `CQ-N1-001` (transação faltante no Create), `CQ-N1-002` (actor_id=null no AuditLog de Rotate), `QA-N1-001` (error path "sem current secret" sem teste). Plus 8 MEDIUM e 12 LOW. Brief em `docs/.briefs/N1.brief.md`. Nenhum CRITICAL aberto. Sprint `/fix` recomendada para os 3 HIGH (~2-4h de trabalho).
>
> **Pós-F5** (2026-05-20): 4 blockers resolvidos in-PR (QA-F5-001 CRITICAL + QA-F5-002/003/004 HIGH). Remanescentes: 1 HIGH (`CQ-F5-001` OpenAPI drift), 9 MEDIUM, 8 LOW. Brief em `docs/.briefs/F5.brief.md`. Sprint `/fix` recomendada para `CQ-F5-001/002/003` (~1-3h).
>
> **Re-validação F5 (R1)** (2026-05-20T17:30Z, `/qa validar`): senior review (gpt-5.3-codex, readonly) → 0 novos findings. QA review (gemini-3.1-pro, readonly) → 5 candidatos; após verificação: **3 novos registrados** (`QA-F5-017` HIGH + `QA-F5-015` MEDIUM + `QA-F5-016` MEDIUM + `QA-F5-018` MEDIUM) e 1 dedup (estende `QA-F5-005`). Testes: 301 passed, 6 skipped, 781 assertions. **Resultado: REPROVADA** — 2 HIGH pendentes em sprint aberta (`CQ-F5-001`, `QA-F5-017`) — PROC-012 exige corrigir in-sprint antes de merge.
>
> **Sprint F5 R1 follow-up implementado** (2026-05-20T18:00Z, `/pmo sprint F5` continuação): F5.8/F5.9/F5.10 corrigem 6 findings (2 HIGH + 4 MEDIUM) — `CQ-F5-001` (OpenAPI v2.0→v2.1), `QA-F5-017` (rollback assertions nos 3 testes SSH-failure), `QA-F5-005` ampliado (helper `noUpstreamFlagDuplication` em 7 testes), `QA-F5-015` (stdin email/groups no Contract test), `QA-F5-016` (OccPanelTest novo, 19 testes), `QA-F5-018` (SshConnectionException em cluster ativo). Testes: **321 passed, 6 skipped, 830 assertions** (+20 testes vs R1). Status: aguardando `/qa validar R2` para reaprovação.
>
> **Re-validação F5 (R2)** (2026-05-20T19:30Z, `/qa validar R2`): scope = 6 arquivos do R1 follow-up (LifecycleTest, Pest, UpstreamContractTest, OccPanelTest, OccPanel, openapi.yaml); rubric R2 round-aware (apenas HIGH/CRITICAL ou regressões diretas). Senior R2 (claude-4.6-sonnet-medium-thinking, readonly) + QA R2 (gemini-3.1-pro, readonly). **Convergência crítica**: 1 finding HIGH detectado pelos 2 auditores — `QA-F5-019` (createUser quebrado em produção; teste cobertura falso-positiva via escape-hatch). QA também detectou 2 HIGH out-of-scope (theming:config multi-key, disableApps `remove` ignorado) e 1 MEDIUM out-of-scope (Contract test false positive em queued state) — registrados como Notes (Non-Blocking) por serem pré-existentes em `main` e fora do escopo R1 follow-up. As 6 correções R1 foram **validadas in-code**: CQ-F5-001, QA-F5-005/015/016/017/018 → `Validado`. Testes: 321 passed, 6 skipped, 830 assertions. **Resultado: REPROVADA** — 1 HIGH pendente (QA-F5-019) em sprint aberta — PROC-012 exige correção in-sprint ou justificação documentada para COM_RESSALVAS.
>
> **Sprint F5 R2 follow-up implementado** (2026-05-20T20:30Z, `/pmo sprint F5` continuação): task F5.11 corrige `QA-F5-019` via **same-path strategy**: blade refatorada (`<form wire:submit.prevent="createUser">` + `wire:model="userPasswordPlain"`), componente sem escape-hatch (lê de propriedade pública e zera no `finally`), 4 testes atualizados + 2 novos (production scenario sem senha + cleanup pós-sucesso). E2E real coverage backlogada como `ISSUE-007` (Dusk/Playwright em sprint N-UI dedicada). Status: aguardando `/qa validar R3` para reaprovação.
>
> **Validação F8** (2026-05-23, `/qa validar F8`): senior (auditor-senior, readonly) + QA (auditor-qa, readonly). Scope = implementação F8.1–F8.6 + docs. Testes F8: **17 passed**, 57 assertions. Full suite: **348 passed**, 10 failed pré-existentes (`OccPanelTest` — fora escopo). **Convergência**: core fix ISSUE-010 validado (webhook→finishing, probe→active, gate 503, sync guard). **2 HIGH novos**: `QA-F8-001` (timeout probe ~83 min vs spec ~20 min), `QA-F8-002` (paths failure/timeout/exhaustion do probe sem teste). **6 MEDIUM + 2 LOW**. Brief em `docs/.briefs/F8.brief.md`. **Resultado: REPROVADA** — PROC-012: corrigir HIGH in-sprint (F8.7+) antes de merge.

---

## Findings

Nenhum finding registrado para D1 na validação atual.

---

### D2-F001 — LOW — phpseclib/phpseclib não instalado (requer composer install manual)

- **Sprint**: D2
- **Severidade**: LOW
- **Status**: Corrigido
- **Arquivo**: `composer.json`
- **Descrição**: A dependência `phpseclib/phpseclib:^3.0` foi adicionada ao `composer.json` mas não pôde ser instalada automaticamente porque o shell tool está bloqueado pelo hook `./hooks/rtk-rewrite.sh` (retorna JSON inválido). Os testes do `SshClientTest.php` exigem que a classe `phpseclib3\Net\SSH2` esteja disponível via autoload.
- **Ação necessária**: Executar `composer install` (ou `docker compose exec app composer install`) no terminal do usuário antes de rodar os testes da Sprint D2.
- **Impacto**: Testes Feature/Core/SshClientTest falham até a dependência ser instalada. Restante dos testes (Unit/Core/{JobTypeTranslatorTest, StateTranslatorTest, SlugRuleTest}) não são afetados.

---

### D3-F001 — HIGH — Aceite de convite não revalida assinatura/expiração no submit Livewire

- **Sprint**: D3
- **Severidade**: HIGH
- **Status**: Validado
- **Arquivo**: `app/Http/Livewire/Auth/AcceptInvite.php`
- **Descrição**: A rota GET usa middleware `signed`, mas `acceptInvite()` ativa a conta sem validar assinatura/expiração novamente nem consultar um token persistido. O teste atual chama o componente diretamente com `Livewire::test(AcceptInvite::class, ['operator' => $operator])`, o que exercita ativação sem URL assinada.
- **Ação necessária**: Persistir convite com token/expiração server-side e consumir em transação, ou revalidar dados assinados no submit antes de ativar a conta.
- **Impacto**: Um formulário carregado antes da expiração pode ativar conta depois das 48h; testes não protegem o contrato real de convite assinado.
- **Correção**: Convites agora usam token persistido com hash + expiração server-side e consumo transacional; o submit revalida estado, token e TTL antes de ativar a conta.

### D3-F002 — HIGH — Operador desativado mantém acesso em sessão existente

- **Sprint**: D3
- **Severidade**: HIGH
- **Status**: Validado
- **Arquivo**: `app/Providers/AppServiceProvider.php`
- **Descrição**: O login bloqueia `status != active`, mas os gates verificam somente `role`. A ação `deactivate()` altera o status para `inactive`, sem invalidar sessões existentes nem bloquear autorização posterior de usuários já autenticados.
- **Ação necessária**: Centralizar bloqueio de usuários não ativos em middleware/gates/policies e invalidar sessões do operador ao desativar.
- **Impacto**: Um operador desativado pode continuar acessando rotas autenticadas até logout/expiração da sessão.
- **Correção**: Middleware autenticado bloqueia operadores não ativos, gates exigem status active e desativação remove sessões database do operador.

### D3-F003 — MEDIUM — Dashboard admin acessível a qualquer usuário autenticado

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `routes/web.php`
- **Descrição**: `/admin/dashboard` está protegido apenas por `auth`, sem `can:manage-operators` ou middleware de role admin.
- **Ação necessária**: Proteger a rota com gate/role admin e adicionar teste cobrindo `suporte`/`operador` com 403.
- **Impacto**: A fronteira de privilégios fica inconsistente e conteúdo admin futuro pode ser exposto a não-admins.
- **Correção**: `/admin/dashboard` agora exige gate admin (`manage-operators`) e possui teste de 403 para perfil suporte.

### D3-F004 — MEDIUM — Tabela `sessions.user_id` incompatível com UUID de operadores

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `database/migrations/2026_05_08_164611_create_sessions_table.php`
- **Descrição**: `sessions.user_id` usa `foreignId()`/BIGINT, enquanto `operators.id` é UUID string. Se `SESSION_DRIVER=database` for usado, sessões autenticadas tentarão gravar UUID em coluna numérica.
- **Ação necessária**: Trocar para coluna UUID/string ou documentar/remover o contrato de sessão em database, mantendo Redis como driver obrigatório.
- **Impacto**: Login e persistência de sessão podem quebrar em ambiente que use database sessions.
- **Correção**: Migration incremental troca `sessions.user_id` para UUID mantendo compatibilidade com `operators.id`.

### D3-F005 — MEDIUM — Gate de suporte para `/customers/create` não está exercitado

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `routes/web.php`
- **Descrição**: O gate da D3 exige que suporte receba 403 em `/customers/create` e não veja opções de provisionar/remover. A rota ainda não existe e a suíte cobre apenas bloqueio em `/operators`.
- **Ação necessária**: Adicionar teste/rota sentinela ou registrar explicitamente esse gate para D6, garantindo que suporte não veja ações destrutivas em `/customers` e receba 403 em criação.
- **Impacto**: A restrição crítica de operações destrutivas pode regredir na D6 sem alarme de teste.
- **Correção**: Rota sentinela `/customers/create` foi protegida por gate `provision-customers`; suporte recebe 403 em teste.

### D3-F006 — MEDIUM — Testes do convite não comprovam URL assinada real e TTL de 48h

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: A suíte valida que o mailable foi enviado, mas não valida que o `signedUrl` está no email, aponta para a rota correta, tem assinatura válida antes de 48h e falha após expirar. O happy path também chama o componente diretamente, sem atravessar a URL real.
- **Ação necessária**: Capturar o mailable fake, validar/renderizar o link e testar o fluxo real GET link assinado -> definir senha -> autenticar -> redirecionar.
- **Impacto**: Quebras entre email, rota assinada, binding e componente Livewire podem passar despercebidas.
- **Correção**: Testes agora validam URL assinada real no mailable, presença do token, GET do link, TTL de 48h e recusa após expiração server-side.

### D3-F007 — MEDIUM — Migration de sessões descarta `user_id` existente

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Corrigido
- **Arquivo**: `database/migrations/2026_05_08_164612_fix_sessions_user_id_uuid.php`
- **Descrição**: A migration trocava `sessions.user_id` com `dropColumn()` + recriação sem truncar a tabela primeiro, deixando sessões BIGINT "fantasmas" que `deactivate()` não conseguia remover por `user_id`.
- **Correção**: `DB::table('sessions')->truncate()` adicionado no início do `up()` e do `down()`. BIGINT → UUID é incompatível por definição; todas as sessões existentes são invalidadas explicitamente no deploy. O `active.operator` middleware garante que qualquer sessão residual seja bloqueada no próximo request. Comentário na migration documenta o comportamento esperado.

### D3-F008 — MEDIUM — Teste de aceite não comprova autenticação final explicitamente

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Corrigido
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: O teste validava ativação e redirect após senha, mas não usava `assertAuthenticatedAs` para comprovar sessão autenticada com o operador aceito.
- **Correção**: `$this->assertAuthenticatedAs($operator)` adicionado ao final do teste "accept invite with valid signed URL activates operator and logs in", garantindo que a sessão está autenticada com o operador recém-ativado.

### D3-F009 — MEDIUM — Sentinela de autorização não cobre remoção de customers

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `app/Http/Livewire/Customers/Show.php`, `routes/api.php`, `tests/Feature/Customers/RemoveTest.php`
- **Descrição**: A validação cobre `/customers/create`, mas ainda não há rota/policy/teste sentinela para remoção de customers.
- **Ação necessária**: Em D6, garantir rota/policy/teste para `customers.destroy` bloqueando suporte e outros perfis sem permissão destrutiva.
- **Impacto**: A restrição de ações destrutivas pode regredir quando remoção de customers for implementada.
- **Correção**: `Gate::authorize('provision-customers')` em `Show::remove()` (Livewire); middleware `can:provision-customers` em `api.customers.destroy`; botão Remover oculto via `@can` na blade; testes API (`role=suporte → 403`) e Livewire D3-F009 em `RemoveTest.php`.

### D3-F010 — LOW — Email de convite não é renderizado nos testes

- **Sprint**: D3
- **Severidade**: LOW
- **Status**: Corrigido
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: A suíte validava `signedUrl` no mailable, mas não renderizava o HTML para garantir que o link aparece no corpo entregue.
- **Correção**: Novo teste "invite email HTML contains the signed URL in the rendered body" usa `$mailable->render()` para renderizar o HTML e verifica presença da `$signedUrl`, do texto "Ativar minha conta" e do nome do operador — protegendo contra quebras no template `emails/operator-invite.blade.php`.

---

### D4-F001 — HIGH — SshClient::executeCommand — payloadStdin escrito após exec() retornar (latente F3)

- **Sprint**: D4
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Arquivo**: `app/Modules/Core/Ssh/SshClient.php` (método `executeCommand`)
- **Descrição**: `$ssh->exec($command)` bloqueia até o comando remoto concluir. Somente após retornar é que `$ssh->write($payloadStdin)` era chamado — quando o canal já estava fechado. O `payloadStdin` nunca chegava ao processo remoto.
- **Correção**: Adicionado método privado `pipeStdin()` que constrói `printf %s <payload_escapado> | <comando>`. Em `executeCommand()`, quando `$payloadStdin !== null`, o piping é feito ANTES de `exec()`. O `logExecution()` continua recebendo o comando limpo (sem payload) para não vazar segredos nos logs. Removido `$ssh->write()` — nunca mais chamado. Dois novos testes adicionados em `SshClientTest.php`: verificação de que o payload aparece no comando passado ao `exec()` e de que `write()` não é invocado (`shouldNotReceive`).

---

### D4-F002 — MEDIUM — Teste D3-F010 falha — asserção verifica URL não-escapada vs HTML com `&amp;`

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Status**: Corrigido
- **Arquivo**: `tests/Feature/Operators/CreateTest.php` (linha 208), `resources/views/emails/operator-invite.blade.php`
- **Descrição**: O teste adicionado pelo D3-F010 chama `->toContain($signedUrl)` onde `$signedUrl` contém `&` entre query params. O template usa `{{ $signedUrl }}` (Blade HTML-escaping), que persiste `&amp;` no HTML renderizado. O teste falha porque procura `&token=` mas encontra `&amp;token=`. O email funciona corretamente em clientes de email (que decodificam `&amp;` → `&`), confirmado em Fase 2 via Mailpit — o link "Ativar minha conta" abre a página correta.
- **Impacto**: Suite CI falha em 1 teste (D3-F010 closure), gerando falso positivo.
- **Ação necessária**: Corrigir a asserção no teste para `->toContain(e($signedUrl))` ou `->toContain(htmlspecialchars($signedUrl, ENT_QUOTES))`. O template está correto — `{{ }}` com HTML-escaping é o comportamento esperado do Blade.

**Cenários mínimos sugeridos:**
- [ ] Happy path: HTML renderizado contém `htmlspecialchars($signedUrl)` (URL com `&amp;`)
- [ ] Edge case: URL sem parâmetros extras (sem `&`) → `{{ $signedUrl }}` == `{!! $signedUrl !!}` → teste passa em ambos os casos

---

### D4-F003 — HIGH — 8 testes Feature/ClusterServers/StoreTest falham (D4.1 não implementado)

- **Sprint**: D4
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Arquivo**: `tests/Feature/ClusterServers/StoreTest.php`, `routes/web.php`
- **Descrição**: Os testes da Sprint D4 foram escritos em TDD (correto) mas as implementações não existem ainda: rota `cluster-servers.index` não registrada, Livewire components `ClusterServers\{Index,Create,Edit}` ausentes. Gate da Sprint D4 exige que todos esses testes passem. Erros: `RouteNotFoundException` e `ComponentNotFoundException`.
- **Impacto**: Gate da D4 bloqueado. 8/9 falhas na suíte (9 total na suite, 8 neste arquivo).
- **Ação necessária**: Implementar D4.1 (CRUD Livewire ClusterServers + rotas). Ver mini design doc no ROADMAP.md seção "4.1 — Module ClusterServers CRUD".

**Cenários pendentes (budget 8 testes, todos failing):**
- [ ] admin acessa index e vê clusters listados
- [ ] operador comum recebe 403 em GET /cluster-servers
- [ ] admin cria cluster_server com PEM válido → redireciona e persiste no DB
- [ ] PEM inválido retorna erro de validação
- [ ] operador comum não consegue salvar via Livewire (gate bloqueia)
- [ ] admin edita nome do cluster_server → persiste no DB
- [ ] ClusterServer listado em Index tem botões de ação (Test, Rotate, Edit)
- [ ] webhook_secret_encrypted é gerado server-side na criação

---

### D4-F004 — MEDIUM — No docker-compose dev, nenhum queue worker em execução → convites não enviados automaticamente

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: environment
- **Status**: Corrigido (F2.4)
- **URL**: http://localhost:8080/operators/create
- **Ação**: Admin cria operador → clica "Enviar convite"
- **Esperado**: Email de convite entregue ao Mailpit automaticamente em poucos segundos
- **Obtido**: Email fica na fila Redis sem processamento. Mailpit mostra "No messages". Requer execução manual de `php artisan queue:work --once` para processar.
- **Arquivo**: `docker-compose.yml`, `app/Mail/OperatorInviteMail.php`
- **Descrição**: `OperatorInviteMail` implementa `ShouldQueue`. O `docker-compose.yml` define `QUEUE_CONNECTION: ${QUEUE_CONNECTION:-database}` como padrão, mas o ambiente em execução usa `QUEUE_CONNECTION=redis` (provavelmente via `.env`). Não há serviço `worker` no docker-compose que processe a fila automaticamente.
- **Ação necessária**: Adicionar serviço `worker` ao `docker-compose.yml` (`php artisan queue:work --tries=3`) ou mudar `QUEUE_CONNECTION` para `sync` no `.env.example` para desenvolvimento.

---

### D4-F006 — MEDIUM — Toast de rotação exibe timestamp incorreto (`valid_from - 1s` em vez de expiry do secret anterior)

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Status**: Corrigido
- **Arquivo**: `app/Http/Livewire/ClusterServers/Index.php` (método `rotateSecret`)
- **Descrição**: O cálculo `$new->valid_from->subSeconds(1)` retornava ≈ `now() - 1 segundo` como data limite do grace period. `$new` é o registro **novo** (válido de agora em diante), portanto `valid_from - 1s` é praticamente o instante atual, não quando a versão anterior expira (`now() + 24h`). O toast exibia uma data no passado como se o grace period já tivesse expirado.
- **Correção**: Substituído por `$new->valid_from->copy()->addHours(config('services.webhook.grace_period_hours', 24))`, calculando corretamente o fim do grace period da versão anterior.

---

### D4-F007 — LOW — `ClusterHealthCheckCommand` usa `whereNotNull('id')` redundante

- **Sprint**: D4
- **Severidade**: LOW
- **Tipo**: code_smell
- **Status**: Corrigido
- **Arquivo**: `app/Console/Commands/ClusterHealthCheckCommand.php`
- **Descrição**: `ClusterServer::whereNotNull('id')->get()` — a condição `id IS NOT NULL` é sempre verdadeira para registros existentes e não expressa a intenção real. Com o global scope de `SoftDeletes`, o Eloquent já exclui registros soft-deletados automaticamente. O código era funcionalmente equivalente a `ClusterServer::all()` mas mais confuso.
- **Correção**: Substituído por `ClusterServer::all()`.

---

### D4-F008 — MEDIUM — PEM da chave SSH em propriedade Livewire síncrona (security debt vs executor_prompt)

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: security_debt
- **Status**: Pendente
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php`
- **Descrição**: O executor_prompt especificava `WithFileUploads` + `TemporaryUploadedFile` para o PEM, evitando que a chave privada trafegasse como propriedade Livewire. A implementação usa `public string $ssh_private_key = ''` com `wire:model`, o que significa que o PEM completo é serializado no snapshot do componente Livewire (cookie encriptado ou sessão server-side) e reenviado em cada request do ciclo de vida do componente. Em ambientes com `APP_DEBUG=true` ou com ferramentas de observabilidade que registram request bodies, o PEM pode ser exposto.
- **Ação necessária**: Migrar para `WithFileUploads` + `TemporaryUploadedFile` conforme especificado no executor_prompt, ou garantir que `APP_DEBUG=false` em produção e que nenhum middleware/proxy registra Livewire request bodies com o PEM.
- **Impacto**: Em produção com debug desligado, risco direto é baixo (Livewire criptografa o snapshot). Risco aumenta em staging/dev ou com ferramentas de log/APM mal configuradas.

---

### D4-F009 — LOW — Rotate webhook secret não registra ação específica no AuditLog

- **Sprint**: D4
- **Severidade**: LOW
- **Tipo**: compliance_gap
- **Status**: Validado
- **Arquivo**: `app/Observers/ClusterServerObserver.php`, `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`
- **Descrição**: O mini design doc da tarefa 4.3 especifica `acao=rotate_webhook_secret` no AuditLog. A implementação atual depende do observer genérico: quando `RotateWebhookSecretAction` chama `$cluster->update([...])`, o observer registra `cluster_server.update` (não `cluster_server.rotate_webhook_secret`). O registro existe mas com semântica genérica — não é possível filtrar por tipo de operação "rotate" no painel de audit.
- **Ação necessária**: Adicionar `AuditLog::create([..., 'action' => 'cluster_server.rotate_webhook_secret', ...])` explicitamente no `RotateWebhookSecretAction::execute()`, ou no método Livewire `rotateSecret()` após a ação concluir.
- **Impacto**: Trail de auditoria existe, mas não é semanticamente preciso para operações de rotação de segredo. Impacto LGPD baixo (a operação está registrada), impacto operacional baixo (filtro por "rotate" não funciona).

---

### D4-F005 — LOW — Mensagens de validação e páginas de erro em inglês (app em pt-BR)- **Sprint**: D4
- **Severidade**: LOW
- **Tipo**: product_bug
- **Status**: Validado
- **URL**: http://localhost:8080/operators/create, http://localhost:8080/operators
- **Ação**: Submeter formulário vazio; acessar rota protegida sem permissão
- **Esperado**: Mensagens em pt-BR ("O campo nome é obrigatório.", "Ação não autorizada.")
- **Obtido**: Inglês — "The name field is required.", "This action is unauthorized."
- **Arquivo**: `config/app.php` (locale), `resources/lang/` (não existe)
- **Descrição**: Laravel usa locale `en` por padrão. As mensagens de validação e erros HTTP aparecem em inglês apesar do sistema ser pt-BR. Arquivos de lang pt-BR não foram publicados (`php artisan lang:publish` não executado).
- **Ação necessária**: Executar `composer require laravel-lang/lang` (ou `php artisan lang:publish`) e definir `'locale' => 'pt_BR'` em `config/app.php`.

---

### D5-F001 — MEDIUM — WebhookPayload campos obrigatórios não validados antes do fromArray()

- **Sprint**: D5
- **Severidade**: MEDIUM
- **Tipo**: security_gap
- **Status**: Corrigido
- **Arquivo**: `app/Http/Middleware/VerifyWebhookHmac.php`
- **Descrição**: O middleware validava apenas `finished_at` do payload antes de passar ao controller. Se o payload chegasse sem `job_id`, `state`, `cmd` ou `client`, `WebhookPayload::fromArray()` lançaria `Undefined array key` resultando em HTTP 500 ao invés de 422.
- **Correção**: Middleware agora valida todos os campos obrigatórios (`job_id`, `state`, `cmd`, `client`, `finished_at`) retornando 422 `invalid_payload` se qualquer um estiver ausente.

---

### D5-F002 — LOW — WebhookHandler criava AuditLog duplicado no idempotent path

- **Sprint**: D5
- **Severidade**: LOW
- **Tipo**: logic_gap
- **Status**: Corrigido
- **Arquivo**: `app/Modules/Jobs/Services/WebhookHandler.php`
- **Descrição**: Quando o mesmo webhook chegava duas vezes para o mesmo job_id+state, a segunda chamada executava a transação completa e criava um segundo `AuditLog` entry `webhook_received`. O estado permanecia correto mas o audit log ficava poluído.
- **Correção**: Early return quando `$job->state === $canonical` — o no-op idempotente agora é silencioso (sem AuditLog duplicado).

---

### D5-F003 — LOW — Rate limit no webhook não gera log de segurança

- **Sprint**: D5
- **Severidade**: LOW
- **Tipo**: observability_gap
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Middleware/VerifyWebhookHmac.php`
- **Descrição**: Tentativas de flood que atingem o rate limit (100 req/min/IP) retornavam 429 silenciosamente, sem entrada no canal de segurança. Ataques volumétricos ficavam invisíveis para monitoramento.
- **Correção**: Adicionado `Log::channel('security')->warning('webhook.rate_limit', ['ip' => $ip])` no path de rate limit.

---

### D5-F004 — LOW — customerFilter aceita LIKE wildcards sem escape

- **Sprint**: D5
- **Severidade**: LOW
- **Tipo**: logic_gap
- **Status**: Corrigido
- **Arquivo**: `app/Http/Controllers/Api/JobController.php`
- **Descrição**: O filtro `customer` nos endpoints `GET /api/queue` e `GET /queue` (Livewire) interpolava a string diretamente em `LIKE "%{$c}%"`. Um valor como `%` retornava todos os jobs; `_` atuava como wildcard de caractere único. Não é injection (query builder usa prepared statements) mas produz resultados inesperados.
- **Correção**: Aplicado `addcslashes($c, '%_')` antes da interpolação no LIKE.

---

### D6-F001 — MEDIUM — CustomerSyncService sem guard para upstream vazio

- **Sprint**: D6
- **Severidade**: MEDIUM
- **Tipo**: logic_gap
- **Status**: Corrigido inline
- **Arquivo**: `app/Modules/Customers/Services/CustomerSyncService.php`
- **Descrição**: Quando o upstream retornava lista vazia (exit_code 0, stdout `[]`), o serviço marcava todos os customers locais como soft-deleted sem questionar a validade da resposta. Um cluster temporariamente sem customers upstream resultaria em soft-delete em cascata dos registros locais.
- **Correção**: Adicionado guard `if ($exitCode !== 0) throw new SshRemoteException(...)` antes de processar a lista.

---

### D6-F002 — MEDIUM — SCP staging path sem cobertura de teste

- **Sprint**: D6
- **Severidade**: MEDIUM
- **Tipo**: test_gap
- **Status**: Corrigido inline
- **Arquivo**: `tests/Feature/Customers/ProvisionTest.php`
- **Descrição**: O path de SCP staging (logo > 256 KB) não tinha cobertura de feature test. `scpUpload` podia falhar silenciosamente.
- **Correção**: Adicionado teste `logo > 256 KB → scpUpload chamado + --staging-id repassado ao SSH`.

---

### D6-F003 — LOW — useScp bundla ambos os arquivos

- **Sprint**: D6
- **Severidade**: LOW
- **Tipo**: design_decision
- **Status**: Aceito (design decision)
- **Arquivo**: `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
- **Descrição**: Se logo > 256 KB mas background ≤ 256 KB, ambos os arquivos vão via SCP (flag `$useScp` é true se qualquer um excede o limite). Background pequeno poderia ir inline. Comportamento conservador aceitável para MVP.

---

### D6-F004 — LOW — JobsPollStuck usa queued_at em vez de updated_at

- **Sprint**: D6
- **Severidade**: LOW
- **Tipo**: logic_gap
- **Status**: Aceito (funcional)
- **Arquivo**: `app/Http/Livewire/Jobs/Index.php`
- **Descrição**: O critério de detecção de job preso usa `queued_at < now() - 60min` em vez de `updated_at`. Jobs que receberam updates intermediários mas nunca completaram ainda são detectados como stuck. Comportamento funcional e conservador.

---

### D6-F005 — LOW — SyncTest com descrição enganosa

- **Sprint**: D6
- **Severidade**: LOW
- **Tipo**: test_quality
- **Status**: Corrigido inline
- **Arquivo**: `tests/Feature/Customers/SyncTest.php`
- **Descrição**: Nome do teste sugeria comportamento diferente do que o código testava.
- **Correção**: Descrição do teste atualizada para refletir o comportamento real.

---

### D7-F001 — HIGH — `OccPanel::$userPassword` como propriedade pública Livewire

- **Sprint**: D7
- **Severidade**: HIGH
- **Tipo**: security
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`, `resources/views/livewire/customers/occ-panel.blade.php`
- **Descrição**: A senha do novo usuário estava vinculada via `wire:model="userPassword"` a uma propriedade pública, sendo serializada no snapshot do componente Livewire (JSON trafegando entre browser e servidor a cada interação). Senhas nunca devem transitar como estado de componente.
- **Correção**: Propriedade marcada com `#[Locked]`. Campo de senha usa `name="password"` sem `wire:model`. Senha lida via `HttpRequest::input('password')` diretamente no método `createUser()` e destruída com `unset()` no `finally`.

---

### D7-F002 — HIGH — URL params `$group` sem validação em 3 endpoints lifecycle

- **Sprint**: D7
- **Severidade**: HIGH
- **Tipo**: input_validation
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Controllers/Api/CustomerLifecycleController.php`
- **Descrição**: `deleteGroup`, `addUserToGroup` e `removeUserFromGroup` recebiam `$group` da URL sem qualquer sanitização, passando-o diretamente para argv do SSH. `RemoveUserFromGroupRequest` existia mas nunca era injetado (código morto).
- **Correção**: Adicionado `preg_match('/^[a-zA-Z0-9._\- ]+$/', $group)` + `strlen <= 256` nos três métodos. `RemoveUserFromGroupRequest` injetado corretamente em `removeUserFromGroup`. Import adicionado.

---

### D7-F003 — MEDIUM — `SshTimeoutException` não capturada em `CustomerLifecycleController`

- **Sprint**: D7
- **Severidade**: MEDIUM
- **Tipo**: error_handling
- **Status**: Corrigido inline (D8 — /qa validar 2026-05-14)
- **Arquivo**: `app/Http/Controllers/Api/CustomerLifecycleController.php`
- **Descrição**: O método `dispatch()` não captura `SshTimeoutException`. Em timeout de SSH async, a exceção propaga sem handler → HTTP 500. Adicionalmente, a `IdempotencyKey` já foi persistida mas nenhum `Job` é criado — key orphaned por 24h.
- **Correção**: Adicionado `use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;` — import ausente impedia o catch block de funcionar. Teste `LifecycleTest > SSH timeout em lifecycle` validado (201→504).

---

### D7-F004 — MEDIUM — Senha sem regras de complexidade na `CreateUserRequest`

- **Sprint**: D7
- **Severidade**: MEDIUM
- **Tipo**: validation_gap
- **Status**: Corrigido inline (D8 — SEC audit)
- **Arquivo**: `app/Http/Requests/Lifecycle/CreateUserRequest.php`
- **Descrição**: Apenas `min:8` — senhas como `12345678` passam localmente e só são rejeitadas pelo upstream (exit 22 → 422) após o SSH já ter sido iniciado (ciclo caro).
- **Correção**: `Password::min(8)->letters()->numbers()` adicionado ao rules() via D8 SEC audit.

---

### D7-F005 — MEDIUM — `explode(' ', $cmd)` em `LifecycleAsyncAction` frágil

- **Sprint**: D7
- **Severidade**: MEDIUM
- **Tipo**: code_quality
- **Status**: Corrigido inline (D8)
- **Arquivo**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php:67`
- **Descrição**: `explode(' ', $cmd)` para construir argv funciona acidentalmente (nenhum cmd atual tem espaço), mas é inconsistente com `ProvisionCustomerAction` (usa string direta) e silenciosamente quebraria se um cmd futuro tivesse espaço.
- **Correção**: Substituído por `[$customer->slug, $cmd]` via D8 SEC audit.

---

### D7-F006 — MEDIUM — 5 endpoints sem cobertura de feature test

- **Sprint**: D7
- **Severidade**: MEDIUM
- **Tipo**: test_gap
- **Status**: Corrigido inline (D8 — D8.2 E2E testes + /qa validar)
- **Arquivos**: `tests/Feature/Api/OccControllerTest.php`, `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: `deleteGroup`, `removeUserFromGroup`, `addUserToGroup`, `setBranding` e `setQuotaAll` sem nenhum teste de feature. Falha em D7-F002 (validação de `$group`) não seria detectável pelos testes existentes.
- **Correção**: Testes adicionados em D8.2 (E2E CriticalFlowsTest) e LifecycleTest. Total 199/199 passando.

---

### D7-F007 — MEDIUM — `IdempotencyKey` orphaned após `SshRemoteException`

- **Sprint**: D7
- **Severidade**: MEDIUM
- **Tipo**: logic_gap
- **Status**: Corrigido inline (D8)
- **Arquivo**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
- **Descrição**: Se `ssh->runAsync()` lança `SshRemoteException` após a key já ser persistida (passo anterior), a key bloqueia retries por 24h com `job_id=null`. Mesmo padrão existe em `ProvisionCustomerAction`.
- **Correção**: `IdempotencyKey::where('key', $idempotencyKey)->delete()` adicionado no catch block via D8.

---

## Sprint D8 — Auditoria DBA (Tarefa 8.3)

### DBA-F001 — HIGH — N+1 em `CustomerSyncService::sync()`

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: performance
- **Status**: Corrigido inline
- **Arquivo**: `app/Modules/Customers/Services/CustomerSyncService.php`
- **Descrição**: `Customer::find($u['slug'])` dentro de `foreach ($upstream)` gerava N+1 queries. Para 50 customers, 51 SELECT statements por execução de sync.
- **Correção**: Pre-load de todos os customers do cluster com `->get()->keyBy('slug')` antes do loop; lookup em memória substituiu cada `find()`.

---

### DBA-F002 — HIGH — DELETEs individuais por linha em `AuditPurgeCommand`

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: performance
- **Status**: Corrigido inline
- **Arquivo**: `app/Console/Commands/AuditPurgeCommand.php`
- **Descrição**: `$log->delete()` dentro de `chunkById(1000)` gerava até 1000 `DELETE WHERE id = ?` por chunk, resultando em até 100.000 round-trips para purge de 100k registros.
- **Correção**: `AuditLog::whereIn('id', $ids)->delete()` — um único DELETE por chunk com até 1000 ids.

---

### DBA-F003 — MEDIUM — Índice duplicado em `operators.email`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: schema
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: `->unique()` já cria índice UNIQUE implícito; `$table->index('email', 'idx_operators_email')` cria segundo índice regular redundante.
- **Correção**: `DROP INDEX idx_operators_email` na migration de polish.

---

### DBA-F004 — MEDIUM — Índice duplicado em `api_keys.token_hash`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: schema
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: Índice regular `idx_api_keys_token_hash` redundante com o UNIQUE implícito.
- **Correção**: `DROP INDEX idx_api_keys_token_hash` na migration de polish.

---

### DBA-F005 — MEDIUM — Índice composto ausente em `audit_logs(resource_type, resource_id, created_at)`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: missing_index
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: Query `WHERE resource_type = ? AND resource_id = ? ORDER BY created_at DESC LIMIT 20` — planner fazia Index Scan + Sort sem composto.
- **Correção**: `idx_audit_logs_rtype_rid_cat` adicionado.

---

### DBA-F006 — MEDIUM — Índice ausente em `jobs.queued_at` (poll stuck)

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: missing_index
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: `WHERE state = 'running' AND queued_at < ?` — `queued_at` sem índice.
- **Correção**: `idx_jobs_state_queued_at` adicionado.

---

### DBA-F007 — MEDIUM — Índice ausente em `jobs.created_at` (paginação)

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: missing_index
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: `ORDER BY created_at DESC LIMIT 25` sem índice → Sort antes de LIMIT.
- **Correção**: `idx_jobs_state_created_at` adicionado.

---

### DBA-F008 — MEDIUM — LIKE com wildcard inicial força Seq Scan

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: performance
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: `LIKE '%termo%'` em `slug`, `customer_slug`, `action` impede índices B-tree.
- **Correção**: `pg_trgm` + índices GIN em `audit_logs.action`, `jobs.customer_slug`, `customers.slug`.

---

### DBA-F009 — MEDIUM — Índice ausente em `webhook_secret_history.valid_until`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: missing_index
- **Status**: Corrigido via migration
- **Arquivo**: `database/migrations/2026_05_14_000001_add_missing_indexes_d8_polish.php`
- **Descrição**: `WHERE valid_until IS NOT NULL AND valid_until < ?` sem índice → Seq Scan.
- **Correção**: `idx_wsh_cluster_valid_until` adicionado.

---

### DBA-F010 — LOW — `sessions.user_id` sem FK para `operators`

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: schema
- **Status**: Validado
- **Arquivo**: `database/migrations/2026_05_08_164612_fix_sessions_user_id_uuid.php`
- **Descrição**: `user_id` UUID sem FK — sessões órfãs permanecem após soft-delete de operator. Middleware `active.operator` mitiga mas não elimina o problema de integridade.
- **Ação**: FK com `onDelete('cascade')` ou observer no soft-delete de operators.

---

### DBA-F011 — LOW — `operators.invite_token_hash` sem UNIQUE constraint

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: schema
- **Status**: Validado
- **Arquivo**: `database/migrations/2026_05_08_164613_add_invite_fields_to_operators_table.php`
- **Descrição**: `invite_token_hash` indexado mas sem `->unique()` — colisão de tokens possível sem garantia no banco.
- **Ação**: Migration para `ADD UNIQUE (invite_token_hash)`.

---

## Sprint D8 — Auditoria Security (Tarefa 8.4)

### SEC-F001 — HIGH — `Password` rule não importada em `CreateUserRequest`

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: security / validação
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Requests/Lifecycle/CreateUserRequest.php`
- **Descrição**: `use Illuminate\Validation\Rules\Password;` ausente → `Password::min(8)` causaria `Error: Class not found`, bypassando validação de complexidade de senha.
- **Correção**: Import adicionado.

---

### SEC-F002 — HIGH — `HttpRequest` inexistente em `OccPanel::createUser()`

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: security / funcionalidade quebrada
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Descrição**: `HttpRequest::input('password', '')` — classe inexistente causaria Error ou senha em branco no upstream.
- **Correção**: Substituído por `request()->input('password', '')`.

---

### SEC-F003 — HIGH — `Locked` não importado em `OccPanel`

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: security / Livewire snapshot
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Descrição**: `use Livewire\Attributes\Locked;` ausente → `#[Locked]` em `$userPassword` não tinha efeito, expondo a propriedade a manipulação via payload Livewire.
- **Correção**: Import adicionado.

---

### SEC-F004 — HIGH — Bearer token auth declarado mas não implementado

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: design / fora de escopo MVP
- **Status**: Aceito (fora de escopo MVP — Sprint 2)
- **Arquivo**: `config/auth.php`, `app/Models/ApiKey.php`
- **Descrição**: `ApiKey` model existe mas sem guard `api`. API acessível apenas via sessão web. Por decisão de design (`auth_api_externa: gestao via UI fica para sprint 2`), o guard Bearer é Sprint 2.
- **Ação**: Implementar via Sanctum ou custom token guard na Sprint 2.

---

### SEC-F005 — HIGH — `SshTimeoutException` não importada em `LifecycleAsyncAction`

- **Sprint**: D8
- **Severidade**: HIGH
- **Tipo**: security / idempotency key leak
- **Status**: Corrigido inline
- **Arquivo**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
- **Descrição**: `catch (SshTimeoutException)` sem import → Error em PHP 8; key de idempotência vaza por 24h bloqueando retries.
- **Correção**: `use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;` adicionado.

---

### SEC-F006 — MEDIUM — Lógica invertida em `EnsureOperatorIsActive`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: security / bypass teórico
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Middleware/EnsureOperatorIsActive.php`
- **Descrição**: `if (! $user || $user->status === 'active')` → requests sem usuário passavam pelo middleware. Semanticamente errado; bypass possível se ordem de middleware fosse alterada.
- **Correção**: Invertido para `$user->status !== 'active'` com lógica de bloqueio correta.

---

### SEC-F007 — MEDIUM — Replay attack via reuso de webhook dentro de 60 min

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: security / replay protection
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Middleware/VerifyWebhookHmac.php`
- **Descrição**: Proteção anti-replay baseada apenas em `finished_at` — mesmo webhook podia ser reenviado N vezes dentro da janela sem rejeição.
- **Correção**: Deduplicação por `job_id` via `Cache::put("webhook_processed:{$jobId}", true, TTL)` com 409 para duplicatas.

---

### SEC-F008 — MEDIUM — `$username` da URL sem validação em `removeUserFromGroup`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: input_validation
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Controllers/Api/CustomerLifecycleController.php`
- **Descrição**: `$username` do path param chegava sem regex nem max length ao SSH.
- **Correção**: Adicionado `preg_match('/^[a-zA-Z0-9._-]+$/', $username) && strlen <= 64`.

---

### SEC-F009 — MEDIUM — `quotaUsername`/`rescanUsername` sem validação no `OccPanel`

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: input_validation
- **Status**: Corrigido inline (D8 Polish — 2026-05-14)
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Descrição**: `quotaUsername` e `rescanUsername` passam diretamente para OCC sem validação de formato.
- **Correção**: `'regex:/^[a-zA-Z0-9._@-]*$/', 'max:64'` adicionado em `submitQuota()` e `submitRescan()`.

---

### SEC-F010 — MEDIUM — `OccPanel` sem controle de acesso por role

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: authorization
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Descrição**: Qualquer operador ativo (incluindo role `suporte`) podia acessar o painel OCC sem restrição de gate.
- **Correção**: `Gate::authorize('provision-customers')` adicionado no `mount()`.

---

### SEC-F011 — MEDIUM — Ausência de rate limiting nos endpoints API

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: availability
- **Status**: Corrigido inline
- **Arquivo**: `routes/api.php`
- **Descrição**: Grupo de rotas autenticadas sem throttle — operador autenticado podia flooding do upstream via SSH.
- **Correção**: `throttle:120,1` adicionado ao grupo de rotas `auth + active.operator`.

---

### SEC-F012 — MEDIUM — `CreateGroupRequest` sem validação de formato no nome

- **Sprint**: D8
- **Severidade**: MEDIUM
- **Tipo**: input_validation
- **Status**: Corrigido inline (D8 Polish — 2026-05-14)
- **Arquivo**: `app/Http/Requests/Lifecycle/CreateGroupRequest.php`
- **Descrição**: `name` aceita qualquer string até 256 chars sem regex — tabs, newlines, `<>` passam.
- **Correção**: `'regex:/^[a-zA-Z0-9._\\- ]+$/'` adicionado ao `rules()` com mensagem pt-BR.

---

### SEC-F013 — LOW — Rate limiting de login apenas por IP (sem lockout por conta)

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: brute_force
- **Status**: Validado
- **Arquivo**: `app/Http/Livewire/Auth/Login.php`
- **Descrição**: Chave `login:{ip}` permite brute force com IPs rotativos contra uma conta específica.
- **Ação**: Adicionar rate limiter secundário por email: `login:{email}`.

---

### SEC-F014 — LOW — Args SSH completos nos logs (idempotency keys, callback URLs)

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: information_disclosure
- **Status**: Validado
- **Arquivo**: `app/Modules/Core/Ssh/SshClient.php`
- **Descrição**: `--idempotency-key=<uuid>` e `--callback=<url>` registrados em log sem mascaramento.
- **Ação**: Estender `SshSecretsMasker` para args com prefixos sensíveis.

---

### SEC-F015 — LOW — `Operator.$fillable` inclui campos privilegiados (`role`, `status`)

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: mass_assignment
- **Status**: Validado
- **Arquivo**: `app/Models/Operator.php`
- **Descrição**: `role` e `status` mass-assignable — risco se algum controller usar `->fill($request->all())`.
- **Ação**: Remover `role`, `status`, `invite_token_hash` do `$fillable`.

---

### SEC-F016 — LOW — IP whitelist webhook baseada em DNS (cache 5 min)

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: dns_spoofing
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Http/Middleware/VerifyWebhookHmac.php`
- **Descrição**: `gethostbyname()` com cache 5 min — DNS poisoning pode permitir IP não autorizado por até 5 min.
- **Ação**: Usar IP estático em coluna separada `webhook_allowed_ip`.

---

### SEC-F017 — LOW — Ausência de security headers HTTP

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: missing_headers
- **Status**: Corrigido inline (D8 Polish — 2026-05-14)
- **Arquivo**: `bootstrap/app.php`, `app/Http/Middleware/SecureHeaders.php`
- **Descrição**: Sem `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` — clickjacking possível no painel.
- **Correção**: Middleware `SecureHeaders` criado e adicionado ao grupo `web` — injeta `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-XSS-Protection: 1; mode=block`.

---

### DBA-F012 — LOW — Lazy load de `clusterServer` em `OccController` e `OccPassthroughService`

- **Sprint**: D8
- **Severidade**: LOW
- **Tipo**: performance
- **Status**: Validado
- **Arquivos**: `app/Http/Controllers/Api/OccController.php`, `app/Modules/Customers/Services/OccPassthroughService.php`
- **Descrição**: Route model binding resolve `Customer` sem eager load de `clusterServer`; cada OCC request gera uma query extra para resolver a relação ao criar `AuditLog`.
- **Ação**: Adicionar `->load('clusterServer')` no controller ou ajustar route binding para eager-load.

---

## Sprint N1 — Sync Webhook Secret com Upstream via SSH

> Auditoria executada em 2026-05-20 via `/qa auditoria N1` (comprehensive). Quality Brief em `docs/.briefs/N1.brief.md`. Auditores: senior + security + qa (paralelo, readonly).
> Sprint shippada e em produção; findings rastreados aqui para `/fix` futuro.

### CQ-N1-001 — HIGH — Two writes em `Create::save()` fora de transação quebram invariante cluster ↔ history

- **Sprint**: N1
- **Severidade**: HIGH
- **Tipo**: atomicity / data_consistency
- **Status**: Corrigido (Sprint F7 — 2026-06-09)
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php`
- **Descrição**: `ClusterServer::create()` (linha 59) e `WebhookSecretHistory::create()` (linha 73) são duas operações sequenciais SEM `DB::transaction()`. Se a inserção em `webhook_secret_history` falhar (timeout, constraint, etc.), o `ClusterServer` permanece persistido com `webhook_secret_encrypted` setado mas SEM linha correspondente em `webhook_secret_history`. `RotateWebhookSecretAction::execute()` (linhas 24-54) corretamente envolve as duas mesmas escritas em `DB::transaction()` — a assimetria é evidente.
- **Impacto**: `WebhookSecretValidator::valid()` consulta APENAS `webhook_secret_history`. Cluster órfão (sem history row) rejeita 100% dos webhooks até intervenção manual. Recuperação manual exige `RotateWebhookSecretAction`, que falha em `! $current` (linha 30) porque também exige history row ativa. Estado de "cluster zumbi" que precisa de DB surgery.
- **Ação necessária**: Envolver as duas inserções em `DB::transaction(function () use (...) { ... })` no `Create::save()`, espelhando o padrão de `RotateWebhookSecretAction`. SSH sync deve permanecer FORA da transação (já está).

---

### CQ-N1-002 — HIGH — Perda de rastreabilidade: `actor_id => null` no AuditLog de rotação

- **Sprint**: N1
- **Severidade**: HIGH
- **Tipo**: audit_traceability / forensics
- **Status**: Corrigido (Sprint F7 — 2026-06-09)
- **Arquivo**: `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`
- **Descrição**: AuditLog de falha de sync durante rotação grava `'actor_id' => null`. Porém o único caller produtivo é `Index::rotateSecret()` precedido por `Gate::authorize('manage-cluster-servers')` — `auth()->id()` está GARANTIDO disponível. Em `Create.php:87` o mesmo evento `cluster_server.secret_sync_failed` é registrado com `'actor_id' => auth()->id()`. Inconsistência clara.
- **Impacto**: Em incidente de produção (admin rotacionou e SSH falhou), segurança não consegue identificar QUAL admin disparou a operação a partir do AuditLog. Quebra a cadeia de causalidade forense e contradiz a semântica do mesmo evento em outro caminho.
- **Ação necessária**: Aceitar `?string $actorId = null` como parâmetro de `execute()` (default null para chamadas de sistema/cron) e propagá-lo no AuditLog. `Index::rotateSecret` passa `auth()->id()`. Alternativa: invocar `auth()->id()` diretamente na Action (acoplamento mais forte mas mais simples).

---

### CQ-N1-003 — MEDIUM — `#[Locked]` silenciosamente inoperante (import ausente)

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: defense_in_depth / silent_failure
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php:30`
- **Descrição**: A propriedade `$ssh_private_key` recebe o atributo `#[Locked]` (linha 30), mas o `use Livewire\Attributes\Locked;` NÃO está nos imports (linhas 7-16). PHP resolve `#[Locked]` ao FQCN do namespace atual (`App\Http\Livewire\ClusterServers\Locked`) que não existe — a reflection do Livewire compara pelo FQCN da attribute class e simplesmente não encontra match. Resultado: a propriedade NÃO está locked. Os testes passam por coincidência (Livewire não lança erro de reflection para attribute classes ausentes, só "não enxerga" o atributo).
- **Impacto**: A propriedade pública `$ssh_private_key` pode ser mutada via snapshot do Livewire pelo cliente. Como já há `Gate::authorize('manage-cluster-servers')`, o impacto real é limitado a admins, e o mesmo admin já pode setar o PEM via input HTML — então o delta de segurança é baixo. Porém: o autor claramente acreditava que a propriedade estava bloqueada (comentário em linha 29). A defesa não funciona, futuro mantenedor pode confiar nela erroneamente.
- **Ação necessária**: Adicionar `use Livewire\Attributes\Locked;` aos imports. Adicionar teste regressivo que valida que `Livewire::test(Create::class)->set('ssh_private_key', 'x')` lança ou ignora a setagem (verificar comportamento de Locked no test harness da versão de Livewire em uso).

---

### CQ-N1-004 — MEDIUM — Fontes assimétricas para "plain secret" entre Create e Rotate

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: fragility / contract_drift_risk
- **Status**: Pendente (Backlog)
- **Arquivos**: `app/Http/Livewire/ClusterServers/Create.php:57,82`, `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php:60`
- **Descrição**: Os dois callers de `SyncWebhookSecretAction::execute($cluster, $plainSecret)` obtêm `$plainSecret` por caminhos diferentes: **Create** mantém `$plainSecret` local capturado no momento da geração (linha 57) e passa-a literal (linha 82); **Rotate** descarta o valor gerado e lê via `$cluster->webhook_secret_encrypted` (linha 60), confiando no cast `'encrypted'` para decifrar on-read. O autor de Create deixou comentário explicando ("holding the plain var is explicit and avoids any future cast-related surprises"); Rotate ignora essa mitigação.
- **Impacto**: Se o cast `webhook_secret_encrypted => 'encrypted'` for renomeado/removido/alterado, Create continua funcionando e Rotate quebra silenciosamente — sincronizaria um secret CIFRADO com o upstream, derrubando todos os webhooks no grace period. Fragiliza refactors.
- **Ação necessária**: Padronizar no caminho do Create — manter o `$newSecret` local dentro do `DB::transaction()` do Rotate e passá-lo diretamente ao `syncAction->execute($cluster, $newSecret)`.

---

### CQ-N1-005 — MEDIUM — Acoplamento Livewire ↔ `request()` em `Create::save()`

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: testability / coupling
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php:47`
- **Descrição**: `$pem = $this->ssh_private_key !== '' ? $this->ssh_private_key : request()->input('ssh_private_key', '')` mescla duas fontes de input incompatíveis: estado público do componente Livewire e request HTTP global. O comentário admite a intenção (testes vs produção), mas o padrão é frágil.
- **Impacto**: O caminho exercitado pelos testes (`->set('ssh_private_key', ...)`) NÃO é o caminho exercitado em produção (`request()->input(...)`). Teste valida o ramo "if", produção exercita "else" — cobertura ilusória. Combinado com `CQ-N1-003`, reforça o problema: a propriedade pública existe mas não tem semântica clara.
- **Ação necessária**: Escolher um único caminho. Opção A (preferida): expor `wire:model="ssh_private_key"` no blade e remover `request()->input(...)`. Opção B: tornar a propriedade `protected`/remover e ler SEMPRE do `request()`; testes usariam HTTP factory.

---

### CQ-N1-006 — LOW — `unset($pem)` é placebo de memória

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: dead_code / misleading_intent
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php:71`
- **Descrição**: `unset($pem)` remove o símbolo do scope, mas PHP não zera os bytes do string no Zend memory manager. O conteúdo pode permanecer em memória até ser sobrescrito por outra alocação.
- **Impacto**: Cosmético/enganoso. Leitor inexperiente replicará o padrão acreditando ser proteção real.
- **Ação necessária**: Remover a linha (GC fará o trabalho quando o escopo fechar) OU manter com comentário `// best-effort; PHP does not zero memory — see sodium_memzero() if hardening needed`. Preferência: remover.

---

### CQ-N1-008 — LOW — Invariante implícito `cluster_servers ↔ webhook_secret_history` sem enforcement de schema

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: domain_modeling / data_integrity
- **Status**: Pendente (Backlog)
- **Arquivos**: `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php:48-51`, migration de `webhook_secret_history`
- **Descrição**: A relação "secret ativo" é definida por `webhook_secret_history.valid_until IS NULL` e simultaneamente espelhada em `cluster_servers.webhook_secret_encrypted` + `cluster_servers.webhook_secret_version`. Essa duplicação só é mantida pelo código aplicacional. Não há trigger nem constraint garantindo "exatamente uma row com valid_until IS NULL por cluster_server_id".
- **Impacto**: Drift possível em cenários degenerados: dupla rotação concorrente com worker stale, inserção manual via tinker, restore parcial de backup. Drift quebra `WebhookSecretValidator` que aceita TODOS os secrets ativos OU em grace.
- **Ação necessária**: Adicionar índice único parcial `CREATE UNIQUE INDEX webhook_secret_history_one_active_per_cluster ON webhook_secret_history (cluster_server_id) WHERE valid_until IS NULL` (PostgreSQL suporta partial index nativamente). Decisão arquitetural — registrar como Decision se aceito.

---

### SEC-N1-001 — LOW — UI exibe últimos 4 caracteres do webhook secret em texto plano

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: information_disclosure
- **Status**: Pendente (Backlog)
- **Arquivo**: `resources/views/livewire/cluster-servers/index.blade.php:90`
- **Descrição**: A listagem de clusters renderiza o sufixo do secret descriptografado para "fingerprinting" visual: `••••••{{ substr($cluster->webhook_secret_encrypted ?? '????', -4) }}`. Como `webhook_secret_encrypted` tem cast `'encrypted'`, esse acesso retorna o secret PLAIN. `substr(..., -4)` extrai 4 caracteres base64 (~24 bits) do segredo de 256 bits.
- **Impacto**: Atacante com acesso a screenshots/cache/SaaS de monitoring (Datadog RUM, FullStory) pode confirmar a qual cluster pertence um secret obtido de outra fonte (dump de log, leak, backup). Reduz entropia 256→~232 bits — ainda computacionalmente seguro mas elimina propriedade "secret 100% confidencial". Snapshot Livewire também serializa o valor renderizado.
- **Ação necessária**: Não exibir nenhuma porção do secret. Alternativa: exibir `webhook_secret_version` (já no schema) ou hash determinístico não-invertível: `substr(hash('sha256', $cluster->webhook_secret_encrypted), 0, 8)`.

---

### SEC-N1-002 — MEDIUM — Estado dessincronizado persistente (secret no DB, sem upstream) sem reconciliação automática

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: operational_security / silent_failure
- **Status**: Pendente (Backlog)
- **Arquivos**: `app/Http/Livewire/ClusterServers/Create.php:73-96`, `app/Http/Livewire/ClusterServers/Index.php:35`
- **Descrição**: Fluxo de criação grava `webhook_secret_history` ANTES da chamada SSH. Se sync falha, cluster vai para `status='error'` mas o secret permanece persistido sem cópia no upstream. `SshClient::validateCluster()` impede execução SSH enquanto `status !== 'active'`, mas `Index::testConnection()` faz `update(['status' => 'active', 'last_health_at' => now()])` quando o ping responde 0, **sem revalidar se o secret está em paridade com o upstream**.
- **Impacto**: Cluster criado com falha transitória de SSH pode ser "reativado" pelo health check sem ter o secret no upstream. Resultado: todos os webhooks do upstream chegam sem assinatura válida → callbacks 100% rejeitados em `VerifyWebhookHmac`. Jobs ficam silenciosamente travados. Não há job de reconciliação ou retry agendado. AuditLog registra o evento mas nada o torna acionável (sem alerta, sem flag `secret_sync_pending`, sem retry policy).
- **Ação necessária**: Três camadas (ordem decrescente de impacto):
  1. (mais forte) Adicionar coluna `webhook_secret_synced_at` em `cluster_servers`; setar como `now()` apenas após sync SSH bem-sucedido. `testConnection` deve recusar transição `error → active` se `webhook_secret_synced_at IS NULL` ou anterior à última rotação.
  2. Job agendado (`webhook-secrets:reconcile`) detecta clusters em `status='error'` por > N minutos e tenta re-executar `SyncWebhookSecretAction`.
  3. (mínimo) Dashboard widget contando `WHERE status='error'` para visibilidade operacional.

---

### SEC-N1-003 — LOW — `webhook_secret_encrypted` em `$fillable` permite mass assignment futuro

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: mass_assignment / defense_in_depth
- **Status**: Pendente (Backlog)
- **Arquivos**: `app/Models/ClusterServer.php:35-48`, `app/Models/WebhookSecretHistory.php:31-37`
- **Descrição**: `ClusterServer.$fillable` inclui `webhook_secret_encrypted` e `ssh_private_key_encrypted`. `WebhookSecretHistory.$fillable` inclui `secret_encrypted`. Verificação atual: nenhum endpoint chama `->fill($request->all())` ou `->create($request->all())` para esses models. Sem vetor explorável hoje.
- **Impacto**: Defense-in-depth: qualquer endpoint futuro (REST API, import, bulk update, console command) que use mass assignment pode permitir a usuário sobrescrever `webhook_secret_encrypted` arbitrariamente, derrubando paridade com upstream e potencialmente controlando o canal HMAC após sync subsequente. Risco materializa apenas em futuro PR.
- **Ação necessária**: Remover `webhook_secret_encrypted` e `ssh_private_key_encrypted` de `$fillable`; setar explicitamente nos pontos de criação. Mesmo tratamento para `WebhookSecretHistory.secret_encrypted`. Alternativa: usar `$guarded` apenas para esses campos.

---

### SEC-N1-004 — LOW — Mensagem de exceção SSH propagada à UI e AuditLog vaza metadados internos

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: information_disclosure
- **Status**: Pendente (Backlog)
- **Arquivos**: `app/Http/Livewire/ClusterServers/Create.php:91,93`, `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php:68,73`
- **Descrição**: `SshConnectionException::getMessage()` (construída em `SshClient::executeCommand()` como `"SSH exec failed for cluster [{id}]: {errorMsg}"`) é exibida ao admin via `addError()` e gravada em `AuditLog.payload['error']`. **Confirmação positiva**: o secret PLAIN não trafega por essa string. Mas a string pode conter: UUID interno do cluster, mensagens cruas do phpseclib (`"Authentication failed"`, `"Connection closed"`, `"Unable to negotiate kex algorithm"` — útil para fingerprinting), e potencialmente IP interno em revisões futuras de phpseclib.
- **Impacto**: Vazamento limitado a contexto admin autenticado. Risco real baixo: admin já conhece cluster e IP. Mais relevante: `AuditLog.payload['error']` persiste em DB indefinidamente, aumentando janela de exposição se DB for comprometido.
- **Ação necessária**: Sanitizar a mensagem antes de exibir/logar — categoria genérica (`"Falha de conexão SSH"`, `"Comando remoto rejeitado"`, `"Timeout"`) baseada no tipo da exceção. Gravar `$e->getMessage()` apenas em `Log::channel('security')` (já existe), não em `AuditLog.payload`. Pattern:
  ```php
  $category = match (true) {
      $e instanceof SshTimeoutException => 'timeout',
      $e instanceof SshConnectionException => 'connection_failed',
      default => 'unknown',
  };
  ```

---

### SEC-N1-005 — LOW — Loop de validação HMAC em `WebhookSecretValidator` não é totalmente timing-constant

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: timing_attack / defense_in_depth
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Modules/ClusterServers/Services/WebhookSecretValidator.php:24-29`
- **Descrição**: Cada `hash_equals` é constant-time, mas o loop em si tem early-return: tempo total revela aproximadamente a posição do match (1 iteração ≈ X µs vs N iterações ≈ N·X µs). Para configuração típica (current + grace = 2 secrets por cluster), o oráculo expõe ~1 bit por requisição.
- **Impacto**: Ínfimo na prática. Jitter de rede (>1 ms) e variabilidade do scheduler PHP-FPM excedem a diferença de microssegundos. Sem vetor remoto exploitable. `hash_equals` cobre o caso crítico (não vaza posição do match dentro de cada secret).
- **Ação necessária** (defense-in-depth, custo zero): avaliar TODOS os secrets independente de match:
  ```php
  $valid = false;
  foreach ($secrets as $secret) {
      $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
      $valid = hash_equals($expected, $signature) || $valid;
  }
  return $valid;
  ```

---

### SEC-N1-006 — LOW — Sem mecanismo de revogação imediata de secret em grace period

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: incident_response / missing_capability
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Modules/ClusterServers/Services/WebhookSecretValidator.php:18-32` (ausência de Action)
- **Descrição**: `WebhookSecretValidator::valid()` aceita qualquer entrada em `webhook_secret_history` cujo `valid_until > now()`. Se um secret em grace vaza (dump de log antigo, backup roubado, leak), atacante tem até 24h restantes para forjar callbacks autenticados. Única mitigação atual: rotacionar de novo (gera NOVO grace de 24h); não existe Action para "revogar este registro de grace agora".
- **Impacto**: Janela de exploit até 24h após detecção. Atacante com secret válido em grace + conhecimento do `cluster_id` (UUID, descobrível por enumeração ou fonte pública/leak) pode forjar callbacks que passam HMAC + replay + dedupe → potencialmente injeta estados falsos em jobs reais. Mitigação parcial via `webhook_allowed_ip` é opcional.
- **Ação necessária**: Adicionar `RevokeGraceSecretAction` que executa `WebhookSecretHistory::where('cluster_server_id', $clusterId)->whereNotNull('valid_until')->update(['valid_until' => now()->subSecond()])` — expira imediatamente todos os secrets em grace. Expor no Index como botão "Revogar grace" gated por `manage-cluster-servers` + audit log entry. ~30min de implementação, redução de janela de 24h para tempo-de-detecção.

---

### SEC-N1-007 — LOW — `json_encode` sem `JSON_THROW_ON_ERROR` no payload SSH (canônico — dedupa CQ-N1-007 e QA-N1-009)

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: defensive_programming
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Modules/ClusterServers/Actions/SyncWebhookSecretAction.php:26`
- **Descrição**: `json_encode(['secret' => $plainSecret])` retorna `false` silenciosamente em falha. `$plainSecret` é `base64_encode(random_bytes(32))` → ASCII puro, `json_encode` jamais falha em prática. Mas com `strict_types=1`, um futuro caller que passar bytes binários crus geraria `TypeError` confuso em runtime ou (se cast string) enviaria `"false"` ao upstream.
- **Impacto**: Sem vetor explorável conhecido. Defense-in-depth: comportamento atual depende de invariantes implícitos sobre o conteúdo de `$plainSecret`. Reportado por 3 auditores (Senior CQ-N1-007, Security SEC-N1-007, QA QA-N1-009) — dedup para esta entrada canônica.
- **Ação necessária**: `json_encode(['secret' => $plainSecret], JSON_THROW_ON_ERROR)`. Sem mudança no caminho feliz; mensagem clara no infeliz.

---

### SEC-N1-008 — LOW — `request()->input('ssh_private_key')` envia PEM como request body em texto plano (operacional)

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: operational_security / configuration
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php:47`
- **Descrição**: A escolha de NÃO usar `wire:model` para o PEM (com `#[Locked]` na property) protege contra round-tripping em snapshots Livewire (design correto). Porém no submit, o PEM ainda trafega como `application/x-www-form-urlencoded` no body do POST `/livewire/update`. TLS protege em trânsito; logs de Nginx/Apache por default não logam body. Mas WAF/CDN podem temporariamente reter bodies para inspeção/debug; `LOG_LEVEL=debug` com middleware logando `request->all()` gravaria PEM em log.
- **Impacto**: Depende da infra. Não é bug de código — é orientação operacional.
- **Ação necessária**: Garantir `LOG_LEVEL >= info` em produção, validar que nenhum middleware loga `$request->all()` para a rota Livewire `/livewire/update`, configurar masking explícito via `Request::macro('except', ...)` para `ssh_private_key`, `password`, etc. Se WAF/CDN em frente, configurar regra de redação para o campo.

---

### QA-N1-001 — HIGH — Error path "sem secret atual no histórico" do `RotateWebhookSecretAction` não tem teste

- **Sprint**: N1
- **Severidade**: HIGH
- **Tipo**: missing_test / equivalence_class_uncovered
- **Status**: Corrigido (Sprint F7 — 2026-06-09)
- **Arquivo**: `tests/Feature/ClusterServers/RotateSecretTest.php` (+ `RotateWebhookSecretAction.php`)
- **Descrição**: Linha 30 lança `\RuntimeException("ClusterServer {$cluster->id} sem secret atual no histórico")` quando `WebhookSecretHistory::where(...)->whereNull('valid_until')->lockForUpdate()->first()` retorna null. Busca por `RuntimeException` e a string literal `sem secret atual` em `tests/` → zero matches. `ClusterServerFactory` NÃO cria entrada em `webhook_secret_history`, então a partição "cluster sem current secret" é alcançável.
- **Impacto**: Equivalence partition crítica não exercitada. Se alguém remover o `if (! $current) { throw ... }` (mutation), nenhum teste falha. Bug em produção quando admin tenta rotacionar cluster órfão (ex: importação parcial, ou estado pós-CQ-N1-001 onde history insert falhou).
- **Ação necessária**: Adicionar em `RotateSecretTest.php`:
  ```php
  it('rotateSecret falha com RuntimeException quando cluster não tem secret current no histórico', function () {
      $cluster = ClusterServer::factory()->create(); // sem WebhookSecretHistory
      expect(fn () => app(RotateWebhookSecretAction::class)->execute($cluster))
          ->toThrow(\RuntimeException::class, 'sem secret atual no histórico');
  });
  ```
  E um teste de integração via `Index::rotateSecret()` para verificar que admin recebe erro user-friendly em vez de 500.

---

### QA-N1-002 — MEDIUM — Boundary value `valid_until == now()` do grace period não testado

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: missing_test / boundary_value
- **Status**: Pendente (Backlog)
- **Arquivos**: `app/Modules/ClusterServers/Services/WebhookSecretValidator.php:21`, cobertura em `tests/Feature/ClusterServers/RotateSecretTest.php:54-116`
- **Descrição**: Query usa comparação estrita `where('valid_until', '>', now())`. Testes cobrem `now()->addHours(23)` e `now()->subHours(24)` — distantes do boundary. Não há teste com `valid_until == now()` (deve retornar false) nem `valid_until == now()->addSecond()` (deve retornar true).
- **Impacto**: Mutação trocando `>` por `>=` não capturada. Equívoco comum em revisão; documentação só fala em "24h grace" sem especificar inclusivo/exclusivo.
- **Ação necessária**: Adicionar 2 testes em `RotateSecretTest.php` com `Carbon::setTestNow(...)`:
  - `webhook validator rejeita secret com valid_until exatamente == now()` (expected: false)
  - `webhook validator aceita secret com valid_until == now()->addSecond()` (expected: true)

---

### QA-N1-003 — MEDIUM — `WebhookSecretValidator::valid()` com cluster sem nenhum histórico (coleção vazia) não testado

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: missing_test / equivalence_class
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Modules/ClusterServers/Services/WebhookSecretValidator.php:20-31`
- **Descrição**: Se `pluck('secret_encrypted')` retornar coleção vazia (cluster sem qualquer entrada em `webhook_secret_history`), o `foreach` não executa e retorna `false` silenciosamente. Não há teste explícito para esse equivalence class.
- **Impacto**: Cenário fail-closed atual depende do retorno default. Se alguém mudar para `return true` ou inverter lógica em refactor de DRY, nenhum teste pega — toda a suite cria pelo menos 1 history. Bug seria catastrófico (HMAC bypass).
- **Ação necessária**: Adicionar teste defensivo:
  ```php
  it('webhook validator retorna false para cluster sem nenhum WebhookSecretHistory', function () {
      $cluster = ClusterServer::factory()->create();
      expect(WebhookSecretHistory::where('cluster_server_id', $cluster->id)->count())->toBe(0);
      expect(app(WebhookSecretValidator::class)->valid($cluster, 'sha256=anything', 'body'))->toBeFalse();
  });
  ```

---

### QA-N1-004 — MEDIUM — Asserção fraca em `RotateSecretTest:20` e `mockSshSuccess()`: ausência de `->once()` permite mutações silenciosas

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: weak_assertion / mutation_gap
- **Status**: Pendente (Backlog)
- **Arquivos**: `tests/Feature/ClusterServers/RotateSecretTest.php:19-21`, `tests/Feature/ClusterServers/SyncWebhookSecretTest.php:35-40`
- **Descrição**: Mock aceita 0..N chamadas sem falhar. O teste `'rotate secret cria versão N+1...'` só verifica `webhook_secret_version === 2` — coisa que acontece dentro da `DB::transaction` independente da chamada SSH externa. Se alguém remover `$this->syncAction->execute(...)` do `RotateWebhookSecretAction:60`, este teste continua VERDE. Única defesa atual: teste isolado `'SyncWebhookSecretAction chama SSH com config set-webhook-secret...'` (com `->once()`), mas esse não cobre a orquestração `Index → RotateAction → SyncAction`.
- **Impacto**: Refactor "limpa código morto" pode remover a chamada de sync no Rotate, e produção fica sem sync no upstream após cada rotate (postmortem ISSUE-002 já queimou em padrão similar).
- **Ação necessária**:
  1. Em `RotateSecretTest:20`, trocar para `->shouldReceive('run')->once()->andReturn(...)`.
  2. No helper `mockSshSuccess()`, aceitar parâmetro `int $times = 1` e usar `->times($times)`.
  3. Adicionar teste explícito "rotate dispara SSH sync" usando `withArgs` para capturar o secret e assert que bate com `cluster->fresh()->webhook_secret_encrypted`.

---

### QA-N1-005 — MEDIUM — Integração `Create::save()` → `SyncWebhookSecretAction` → SSH não verifica que o secret no payload é o EXATO secret persistido no DB

- **Sprint**: N1
- **Severidade**: MEDIUM
- **Tipo**: weak_integration_test / contract_drift_gap
- **Status**: Pendente (Backlog)
- **Arquivo**: `tests/Feature/ClusterServers/SyncWebhookSecretTest.php:52-69`
- **Descrição**: O teste "criar cluster com SSH success" valida apenas (a) `status === 'active'`, (b) `assertRedirect(...)`. Não captura o payload SSH nem asserta `json_decode($payload, true)['secret'] === $cluster->fresh()->webhook_secret_encrypted`. O teste do nível Action verifica isso para um valor literal (`'my-plain-secret'`), mas não para o secret REAL gerado pelo `WebhookSecretGenerator` dentro do `Create::save()`.
- **Impacto**: Equivalence class "secret enviado == secret persistido (decrypted)" não exercitada end-to-end. Combinado com CQ-N1-004 (fontes assimétricas), aumenta risco de drift contract entre Create e Rotate sem detecção em testes.
- **Ação necessária**: Refatorar o teste para capturar payload via `withArgs` e assert `$decoded['secret'] === $cluster->webhook_secret_encrypted` após o `save()`. Aplicar mesmo padrão ao teste de Rotate.

---

### QA-N1-006 — LOW — `WebhookSecretGenerator::generate()` sem teste defensivo de formato/aleatoriedade

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: missing_test / defense_in_depth
- **Status**: Pendente (Backlog)
- **Arquivo**: `app/Modules/ClusterServers/Services/WebhookSecretGenerator.php:11`
- **Descrição**: A função é trivial (`base64_encode(random_bytes(32))`), mas não há teste verificando: (a) duas chamadas consecutivas retornam valores DIFERENTES (sanity de aleatoriedade), (b) tamanho ≈ 44 chars, (c) charset base64 válido.
- **Impacto**: `random_bytes` é CSPRNG do PHP — falha exigiria refactor humano hostil/distraído (ex: trocar `random_bytes(32)` por `Str::random(8)` "porque é mais legível", reduzindo entropia 256→48 bits). Sem teste, regressão passa silenciosa.
- **Ação necessária**: Criar `tests/Unit/Modules/ClusterServers/WebhookSecretGeneratorTest.php` com 2 testes simples (chamadas diferentes + tamanho mínimo).

---

### QA-N1-007 — LOW — Contract test do payload `nextcloud-manage config set-webhook-secret --payload-stdin` ausente (gap inter-repo)

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: missing_contract_test / inter_repo_drift
- **Status**: Pendente (Backlog) — sprint dedicada futura
- **Arquivo**: `app/Modules/ClusterServers/Actions/SyncWebhookSecretAction.php:22-27`
- **Descrição**: Testes mockam `SshClientInterface` e validam que JSON contém chave `secret`. Não há contract test validando que `{"secret": "..."}` é EXATAMENTE o aceito pelo upstream (`mework360-deployer-scripts/nextcloud-manage`). Esse contrato vive em outro repositório. Postmortem ISSUE-002 já registra que webhook round-trip end-to-end não é exercitado pela suite.
- **Impacto**: Drift inter-repo (ex: upstream renomear chave para `webhook_secret`) só seria detectado em produção via SSH stderr. Status do cluster gravaria `error` e admin receberia mensagem — não silent failure, mas feedback loop lento.
- **Ação necessária**: Tracking item para sprint futura (categoria D ou F): smoke test em CI que executa `nextcloud-manage config set-webhook-secret --payload-stdin` em container e valida exit code 0 com payload `{"secret": "test"}`. Alternativa: registrar contract em `docs/SSH API Reference — Nextcloud SaaS.md` com versionamento explícito.

---

### QA-N1-008 — LOW — Helpers SSH-mock duplicados entre `bindSshSuccessMock` (StoreTest) e `mockSshSuccess` (SyncWebhookSecretTest)

- **Sprint**: N1
- **Severidade**: LOW
- **Tipo**: test_duplication / maintainability
- **Status**: Pendente (Backlog)
- **Arquivos**: `tests/Feature/ClusterServers/StoreTest.php:17-22`, `tests/Feature/ClusterServers/SyncWebhookSecretTest.php:35-40`
- **Descrição**: Mesmo padrão de mock (`Mockery::mock(SshClientInterface::class)` + `andReturn(new SshResponse('', '', 0))` + `app()->instance(...)`) duplicado em dois arquivos com nomes diferentes. Padrão é trivial (~5 linhas) mas a duplicação tende a divergir.
- **Impacto**: Manutenção; não afeta correção. Risco de drift cosmético em futuras sprints que adicionarem SSH mocks.
- **Ação necessária**: Mover para `tests/Pest.php` como helper global (`function mockSshSuccess(int $times = 1, ...)`) ou criar trait `MocksSshClient` em `tests/Concerns/`.

---

## Sprint F5 — Lifecycle async cmd → CLI argv translator (ISSUE-006 fix)

> Auditoria executada em 2026-05-20 via `/pmo sprint F5` (auditor-senior + auditor-qa em paralelo, ambos readonly). Quality Brief em `docs/.briefs/F5.brief.md`. 4 blockers resolvidos in-PR (1 CRITICAL + 3 HIGH).

### QA-F5-001 — CRITICAL — `UpstreamContractTest` em `tests/Feature/` torna o opt-in inviável por colisão com `RefreshDatabase`

- **Sprint**: F5
- **Severidade**: CRITICAL
- **Tipo**: test_architecture_defect
- **Status**: **Corrigido (in-PR)** — arquivo movido para `tests/Contract/Customers/`; `tests/Pest.php` ganhou regra `->in('Contract')` sem `RefreshDatabase`; `phpunit.xml` ganhou testsuite `Contract`.
- **Arquivos (antes)**: `tests/Feature/Customers/UpstreamContractTest.php`, `tests/Pest.php`
- **Descrição**: O opt-in `UpstreamContractTest` foi colocado em `tests/Feature/`, que aplica `RefreshDatabase` globalmente. Quando o operador setasse `RUN_UPSTREAM_CONTRACT=1` para rodar contra o cluster homolog, o `setUp()` wiparia as rows seed de `ClusterServer` e `Customer`, e `upstreamContractCluster()` falharia em `ClusterServer::find($id) === null` via `test()->fail("...not found")`. O gate de regressão contra ISSUE-006 — toda a razão de existir do arquivo — seria não-funcional.
- **Impacto**: Quem rodasse o opt-in pré-merge encontraria erro `Cluster not found` e poderia (a) desativar o gate, (b) introduzir hack que mascarasse a falha, (c) shippar uma futura regressão do CMD_TO_CLI_ARGV. Anular a entrega da Sprint F5.
- **Ação executada**: `mv tests/Feature/Customers/UpstreamContractTest.php tests/Contract/Customers/UpstreamContractTest.php`; adicionado em `tests/Pest.php` `pest()->extend(TestCase::class)->in('Contract')` (sem `RefreshDatabase`); adicionado em `phpunit.xml` `<testsuite name="Contract">`; docblock do arquivo atualizado para refletir o novo path e o motivo (`testsuite=Contract`, não `--filter`).

---

### QA-F5-002 — HIGH — Teste `apps:disable` sem guard de bug-symmetry (`! in_array('apps:disable', $args)`)

- **Sprint**: F5
- **Severidade**: HIGH
- **Tipo**: test_quality / bug_symmetry_gap
- **Status**: **Corrigido (in-PR)**
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php:325-348`
- **Descrição**: O teste de `apps:enable` (linha 304-306) usa o triple guard `argsContainConsecutive(['apps','enable']) + !in_array('apps:enable') + presence check`. O irmão `apps:disable` (linha 330-332) usava apenas presença + token consecutivo; faltava o `!in_array('apps:disable', $args)`. Bug B (`--async/--json` duplicados) tampouco tinha guard. Se uma futura refatoração reintroduzisse o bug específico para o verb `apps:disable`, o test passaria.
- **Ação executada**: Adicionado ao `withArgs` do teste: `! in_array('apps:disable', $args, true)` + `! in_array('--async', $args, true)` + `! in_array('--json', $args, true)`. Adicionado teste extra "POST apps/disable com 3 apps" para cobrir QA-F5-007 (multi-CSV) no mesmo PR.

---

### QA-F5-003 — HIGH — Teste `groups:remove → 501` sem assertion de `IdempotencyKey` hygiene

- **Sprint**: F5
- **Severidade**: HIGH
- **Tipo**: test_coverage / asymmetric_assertion
- **Status**: **Corrigido (in-PR)**
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php:528-550`
- **Descrição**: O teste de `groups:add → 501` assertava `expect(IdempotencyKey::where('cmd', 'groups:add')->count())->toBe(0)` para provar que blocked verbs short-circuit antes de tocar DB. O teste irmão de `groups:remove → 501` omitia essa assertion. Risco de divergência futura: se um refactor movesse `cmdToCliArgv()` para depois de `IdempotencyKey::create()` apenas para `groups:remove`, o bug passaria.
- **Ação executada**: Adicionado expect chain combinado com QA-F5-004 (ver abaixo) — verifica `IdempotencyKey`, `Job` e `AuditLog` em ambos os testes 501.

---

### QA-F5-004 — HIGH — Testes blocked-verb não validam Job + AuditLog hygiene (só IdempotencyKey)

- **Sprint**: F5
- **Severidade**: HIGH
- **Tipo**: test_coverage / weak_invariant
- **Status**: **Corrigido (in-PR)**
- **Arquivos**: `tests/Feature/Customers/LifecycleTest.php` (ambos os testes 501)
- **Descrição**: O contrato defensivo para blocked verbs é "short-circuit ANTES de QUALQUER write" (a chamada `cmdToCliArgv()` é a primeira instrução de `LifecycleAsyncAction::execute()`). Os testes só checavam `IdempotencyKey`. Faltavam `Job::where('cmd_canonical', 'groups:add')->count() === 0` e `AuditLog::where('action', 'groups_add_initiated')->count() === 0`. Uma regressão por reordenação interna poderia escrever `Job + AuditLog` mesmo sem `IdempotencyKey`.
- **Ação executada**: Ambos os testes 501 (`groups:add` e `groups:remove`) agora verificam **as 3 tabelas** de side-effect via `expect(...)->toBe(0)->and(...)->toBe(0)->and(...)->toBe(0)`.

---

### CQ-F5-001 — HIGH — OpenAPI não reflete novo shape de `apps/enable|disable` (breaking) nem HTTP 501

- **Sprint**: F5
- **Severidade**: HIGH
- **Tipo**: api_contract_drift / documentation
- **Status**: **Validado (R2 — 2026-05-20T19:30Z)**
- **Arquivos**: `docs/openapi.yaml:380-425`, `app/Http/Controllers/Api/CustomerLifecycleController.php`
- **Descrição**: `docs/openapi.yaml` descreve `POST /customers/{customer}/apps/enable` e `/apps/disable` retornando `$ref: '#/components/responses/JobAccepted'`. A implementação F5 retorna `{job_id, apps_csv}` flat em 202 e `{error, exit_code, apps_csv}` em 502. Adicionalmente, a Sprint F5 introduziu HTTP 501 (`{error: not_implemented_yet, reason, cmd}`) para `POST/DELETE /customers/{customer}/groups/{group}/users` — não documentado em OpenAPI.
- **Correção (F5.10)**: `docs/openapi.yaml` v2.0 → v2.1: (a) `apps/enable` e `apps/disable` agora declaram inline schema `{job_id: uuid, apps_csv: string}` (202) + ErrorResponse com `exit_code`/`apps_csv` (502) + `ClusterUnreachable` (503); (b) novo response component `NotImplementedYet` (`{error, reason, cmd}`) referenciado em `POST /customers/{customer}/groups/{group}/users` e `DELETE /customers/{customer}/groups/{group}/users/{username}`; (c) bump `info.version` 2.0 → 2.1 + entrada em `docs/CHANGELOG.md` (versão 0.10 do ROADMAP).

---

### CQ-F5-002 — MEDIUM — `CMD_TO_CLI_ARGV` tem 7 entradas customer-level dead-code / risco de drift silencioso

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: dead_code / contract_drift_risk / YAGNI
- **Status**: **Corrigido (F11.2 — 2026-05-24)** — 7 entradas customer-level removidas de `CMD_TO_CLI_ARGV`; escopo documentado em comentário (`ProvisionCustomerAction`/`RemoveCustomerAction` constroem argv à mão).
- **Arquivos**: `app/Modules/Core/Translators/JobTypeTranslator.php`, `app/Modules/Customers/Actions/ProvisionCustomerAction.php`, `app/Modules/Customers/Actions/RemoveCustomerAction.php`
- **Descrição**: `CMD_TO_CLI_ARGV` mapeia `create/remove/backup/restore/update/stop/start` (customer-level), mas nenhum caller de `cmdToCliArgv()` consome essas entradas — `ProvisionCustomerAction` e `RemoveCustomerAction` constroem argv à mão. Drift silencioso se o upstream renomear verb.
- **Correção (F11.2)**: Opção A aplicada — entradas YAGNI removidas; comentário de escopo no tradutor.

---

### CQ-F5-003 — MEDIUM — Duplicate exception handling entre `dispatch()` e `dispatchAppsCsv()`

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: dry_violation / maintainability
- **Status**: **Corrigido (F11.3 — 2026-05-24)** — `mapLifecycleException()` extraído; `dispatch()` e `dispatchAppsCsv()` delegam 503/504/409 idempotency. `SshRemoteException` permanece por caller.
- **Arquivo**: `app/Http/Controllers/Api/CustomerLifecycleController.php`
- **Descrição**: Ambos os métodos privados capturam 4 exceções idênticas (`ClusterUnreachableException`, `SshTimeoutException`, `IdempotencyConflictException`, `SshRemoteException`) com corpos quase idênticos. Diferenças mínimas: `dispatch()` adicionalmente captura `BlockedOnUpstreamException` (501); `dispatchAppsCsv()` injeta `apps_csv` no error payload. ~30 LoC duplicadas.
- **Correção (F11.3)**: `private function mapLifecycleException(\Throwable $e): ?JsonResponse` — retorna `null` para exceções que exigem routing por exit code.

---

### CQ-F5-004 — LOW — `LifecycleAsyncAction::execute()` ~110 LoC misturando 7 responsabilidades

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: clean_code / function_length / srp
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
- **Descrição**: O método concentra 7 responsabilidades. Refatorado em privados; `execute()` agora orquestra ~25 LoC.
- **Ação executada**: decomposto em `resolveActiveCluster`, `assertTenantReadyForUserOps`, `buildSshArgs`, `persistIdempotencyKey`, `dispatchSshAsync`, `persistJobAndAudit`.

---

### CQ-F5-005 — LOW — `OccPanel::addUserToGroup()` tem código de sucesso unreachable até D3/D4

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: dead_code / misleading_code
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `app/Http/Livewire/Customers/OccPanel.php`
- **Descrição**: A chamada `$action->execute($this->customer, 'groups:add', ...)` sempre lança `BlockedOnUpstreamException`. Linhas `$this->successMessage = "Adição ao grupo enfileirada — job {$job->job_id}."` e o reset de variáveis são unreachable. `catch (\Throwable)` + `formatError()` rotam para a mensagem amigável.
- **Ação necessária**: (A) short-circuit no topo do método com mensagem amigável, OU (B) manter + adicionar comentário `// blocked-on-upstream; success branch wakes up when CMD_TO_CLI_ARGV gains 'groups:add'`.

---

### CQ-F5-006 — LOW — `argsContainConsecutive` não valida ordenação `[slug, verb_token_1, verb_token_2]`

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: test_quality / weak_assertion
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: O helper valida que tokens aparecem consecutivos, mas não que `<slug>` vem IMEDIATAMENTE antes (a ordem que o upstream `nextcloud-manage <slug> <verb> ...` exige). Hoje o código está correto, mas refactor poderia mover o slug sem detecção.
- **Ação necessária**: Estender helper para `argsStartWithSequence($args, [$slug, ...])` OU adicionar `expect($args[0])->toBe($slug)` em pelo menos 1 teste por verb.

---

### CQ-F5-007 — LOW — Sem teste Livewire para `BlockedOnUpstreamException → friendly message` em `OccPanel`

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: test_coverage_gap
- **Status**: **Corrigido (F5.10 — 2026-05-20)**
- **Arquivos**: `app/Http/Livewire/Customers/OccPanel.php`, `tests/Feature/Livewire/Customers/OccPanelTest.php`
- **Descrição**: O path HTTP `groups:add → 501` é coberto. O path Livewire análogo (`OccPanel::addUserToGroup → formatError(BlockedOnUpstreamException) → errorMessage = "Funcionalidade pendente..."`) não tinha teste regressivo.
- **Correção (F5.10)**: `OccPanelTest` — `addUserToGroup → BlockedOnUpstreamException` asserta mensagem amigável + hygiene `IdempotencyKey`.

---

### QA-F5-005 — MEDIUM — Bug-B guards incompletos em múltiplos endpoints (`--async`/`--json` asymmetry)

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: test_quality / bug_symmetry_gap
- **Status**: **Validado (R2 — 2026-05-20T19:30Z)**
- **Arquivos**: `tests/Feature/Customers/LifecycleTest.php` (vários testes)
- **Descrição (ampliada pela re-validação R1)**: Apenas 3 testes tinham guard completo `! in_array('--async')` + `! in_array('--json')`. Os demais 4 endpoints (`POST groups`, `DELETE users/{username}`, `DELETE groups/{group}`, `POST apps/enable`) tinham guard parcial ou ausente. Bug B (`--json` duplicado causando JSON envelope malformado upstream) poderia regredir sem alarme.
- **Correção (F5.9)**: Helper global `noUpstreamFlagDuplication(array $args, string $canonicalCmd): bool` em `tests/Pest.php` centraliza a checagem (`! --async`, `! --json`, `! $canonicalCmd`). Aplicado em 7 testes de sucesso: `POST users` (happy + groups), `POST groups`, `DELETE users/{username}`, `DELETE groups/{group}`, `POST apps/enable`, `POST apps/disable` (single + 3 apps).

---

### QA-F5-006 — MEDIUM — Tests não verificam que `--idempotency-key=` e `--callback=` chegam ao SSH

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: test_coverage / contract_invariant
- **Status**: **Corrigido (F11.5 — 2026-05-24)**
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: `LifecycleAsyncAction::execute()` anexa `--idempotency-key={UUID}` e `--callback={url}` ao `$sshArgs`. Nenhum `withArgs` assertava presença.
- **Correção (F11.5)**: Asserts em 3 paths (users, groups, apps): `str_starts_with(..., '--idempotency-key=')` e callback `/api/jobs/hook?cluster=`.

---

### QA-F5-008 — MEDIUM — Idempotency hash order-sensitivity para CSV apps não documentada nem testada

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: design_decision_gap / test_coverage
- **Status**: **Corrigido (F11.6 — 2026-05-24)**
- **Arquivo**: `app/Http/Controllers/Api/CustomerLifecycleController.php`, `app/Modules/Customers/Actions/LifecycleAsyncAction.php`, `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: `implode(',', $apps)` preserva ordem de input. O hash `$customer->slug.'|'.$cmd.'|'.json_encode($args)` torna `'calendar,mail'` ≠ `'mail,calendar'` em deduplicação.
- **Correção (F11.6)**: **Policy A** (ordem preservada) documentada em comentário no controller + teste dedicado `QA-F5-008` em `LifecycleTest`.

---

### QA-F5-009 — MEDIUM — Boundary value tests faltando (max-length, empty array, password edge)

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: test_coverage / equivalence_partitioning
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: Gaps de boundary value: username exatamente 64 chars (valid edge), group exatamente 256 chars, `apps: []` empty array, email com `+`, password exatamente 8 chars, App ID com uppercase apenas.
- **Ação executada**: Testes Pest cobrindo cada boundary (válido na borda + off-by-one inválido onde aplicável).

---

### QA-F5-010 — MEDIUM — `phpunit.xml` não força `RUN_UPSTREAM_CONTRACT=0` (defense-in-depth)

- **Sprint**: F5
- **Severidade**: MEDIUM
- **Tipo**: test_safety / defense_in_depth
- **Status**: **Corrigido (F11.4 — 2026-05-24)**
- **Arquivo**: `phpunit.xml`
- **Descrição**: Mitigado parcialmente pela movimentação para `tests/Contract/`; risco de SSH real se `RUN_UPSTREAM_CONTRACT=1` no ambiente CI.
- **Correção (F11.4)**: `<env name="RUN_UPSTREAM_CONTRACT" value="0" force="true"/>` no bloco `<php>`.

---

### QA-F5-011 — LOW — `UpstreamContractTest` não asserta argv canônico, só presença de job_id

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: test_quality / blind_spot
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `tests/Contract/Customers/UpstreamContractTest.php`
- **Descrição**: Cada cenário valida `$job->job_id` UUID + `$job->state === 'queued'`. Não valida que o argv ENVIADO foi o canônico (`['user','create']`). Se um typo regredisse `CMD_TO_CLI_ARGV['users:create']` para `['users','create']` (com `s`) e upstream aceitasse ambos, o teste passaria falsamente.
- **Ação necessária**: Adicionar no topo de cada cenário `expect(app(JobTypeTranslator::class)->cmdToCliArgv('users:create'))->toBe(['user', 'create']);` — short-circuit por typo antes mesmo de SSH.

---

### QA-F5-012 — LOW — `UpstreamContractTest` deixa artifacts `qa-*` no cluster homolog sem cleanup

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: test_hygiene
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `tests/Contract/Customers/UpstreamContractTest.php`
- **Descrição**: `'qa-'.substr(uniqid(), -8)` gerava nomes únicos por run sem teardown. Cada execução opt-in poluía o cluster.
- **Ação executada**: `finally` com best-effort `users:delete` / `groups:delete` após cenários que criam recursos.

---

### QA-F5-013 — LOW — `JobTypeTranslatorTest` não asserta que `cmdToCliArgv` retorna `list` (re-indexado)

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: test_quality / list_contract
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Arquivo**: `tests/Unit/Core/JobTypeTranslatorTest.php`
- **Descrição**: `->toBe([...])` checa valores + keys, mas não locka explicitamente o contrato "lista (chaves int sequenciais)". `LifecycleAsyncAction` faz spread `[$slug, ...$tokens]` — se a const virasse assoc, spread injetaria string keys → fatal.
- **Ação necessária**: Adicionar 1 test unitário `expect(array_is_list($this->translator->cmdToCliArgv('users:create')))->toBeTrue()`.

---

### QA-F5-014 — LOW — Auditor não pôde executar `composer audit` / `semgrep` (sandbox readonly)

- **Sprint**: F5
- **Severidade**: LOW
- **Tipo**: audit_environment_note
- **Status**: **Validado** (R4 `/qa validar F5` — 2026-06-02)
- **Descrição**: A sandbox readonly do auditor-qa subagent não tinha acesso a Docker socket. **Re-execução 2026-06-02**: `docker compose exec app composer audit` — 11 advisories em 7 pacotes Symfony (CVE-2026-*). Semgrep permanece opt-in CI.
- **Ação necessária**: Em release engineering, antes de merge final, rodar `docker compose exec -T app composer audit` + `semgrep scan` localmente.

---

### QA-F5-015 — MEDIUM — `UpstreamContractTest` não exercita `email`/`groups` no stdin de `users:create`

- **Sprint**: F5
- **Severidade**: MEDIUM (downgrade de HIGH detectado pelo auditor-qa Gemini; opt-in + campos opcionais)
- **Tipo**: contract_test_integrity / test_coverage_gap
- **Status**: **Validado (R2 — 2026-05-20T19:30Z)**
- **Arquivo**: `tests/Contract/Customers/UpstreamContractTest.php:90-96`
- **Correção (F5.9)**: O cenário `user create` no `UpstreamContractTest` agora injeta `{password, email: 'qa-contract@example.com', groups: ['editors']}` no stdin (vez de só `password`). O comentário no teste documenta a motivação (gate de regressão para schema-strict upstream). Assertion `$job->state === 'queued'` confirma que o upstream `nextcloud-manage` parseou o payload estendido sem rejeitar.

---

### QA-F5-016 — MEDIUM — Ausência total de testes para `OccPanel` (Livewire)

- **Sprint**: F5
- **Severidade**: MEDIUM (downgrade de HIGH detectado pelo Gemini; OccPanel é UI espelho de controller totalmente testado)
- **Tipo**: test_coverage_gap / livewire_component_uncovered
- **Status**: **Validado (R2 + F5.11 — 2026-05-20)** — `OccPanelTest` cobre same-path `userPasswordPlain`; escape-hatch removido.
- **Arquivos**: `app/Http/Livewire/Customers/OccPanel.php` (corrigido import faltante de `Gate`); `tests/Feature/Livewire/Customers/OccPanelTest.php` (novo, 19 testes, 38 assertions)
- **Correção (F5.10)**:
  - Novo arquivo `tests/Feature/Livewire/Customers/OccPanelTest.php` cobrindo todas as 8 actions: `setTab`, `submitQuota`, `submitRescan`, `submitBranding`, `toggleMaintenance`, `submitApp`, `createUser` (4 cenários: happy, IdempotencyConflict, SshTimeout, weak password), `deleteUser`, `createGroup`, `deleteGroup`, `addUserToGroup` (BlockedOnUpstream).
  - Cobertura de error mapping (`BlockedOnUpstreamException`, `IdempotencyConflictException`, `SshTimeoutException`, `SshRemoteException` exit 4) via `formatError()`.
  - Autorização: teste com role `suporte` → 403.
  - **Bug pré-existente descoberto + corrigido**: `OccPanel.php` usava `Gate::authorize('provision-customers')` em `mount()` SEM importar `Illuminate\Support\Facades\Gate`. Como não havia testes, o bug era latente — qualquer acesso ao painel falharia com `Class App\Http\Livewire\Customers\Gate not found`. Adicionado o import.
  - **Refactor de testabilidade**: `OccPanel::createUser()` ganhou parâmetro opcional `?string $password = null` (fallback para `request()->input('password')` mantendo produção idêntica). Permite tests Livewire injetarem senha via `->call('createUser', 'Secret123!')` sem disparar `CannotUpdateLockedPropertyException` no `#[Locked] $userPassword`.

---

### QA-F5-017 — HIGH — Testes de falha SSH não assertam rollback de `IdempotencyKey` (weak invariant)

- **Sprint**: F5
- **Severidade**: HIGH
- **Tipo**: test_coverage_gap / weak_invariant / regression_silent
- **Status**: **Validado (R2 — 2026-05-20T19:30Z)**
- **Arquivos**: `tests/Feature/Customers/LifecycleTest.php:415-446, 584-599`
- **Correção (F5.8)**: 3 testes existentes ganharam expect chains após `assertStatus`:
  - `SSH exit 4 → 409`: `expect(IdempotencyKey::where('cmd', 'groups:create')->count())->toBe(0)`.
  - `SSH exit 22 → 422`: `expect(IdempotencyKey::where('cmd', 'users:create')->count())->toBe(0)`.
  - `SSH timeout → 504`: triplo expect — `IdempotencyKey` + `Job::where('cmd_canonical')` + `AuditLog::where('action', 'groups_create_initiated')` todos === 0 (alinhado com padrão QA-F5-004).
  - Comentários inline em cada teste explicam que o contrato defensivo é deliberado e o assert protege contra refactor silencioso.

---

### QA-F5-018 — MEDIUM — Path negativo `SshConnectionException` em cluster ativo não testado

- **Sprint**: F5
- **Severidade**: MEDIUM (downgrade de HIGH detectado pelo Gemini; o error mapping é simétrico — mesmo ClusterUnreachableException)
- **Tipo**: test_coverage_gap / negative_path_gap
- **Status**: **Validado (R2 — 2026-05-20T19:30Z)**
- **Arquivos**: `tests/Feature/Customers/LifecycleTest.php:455-477`; `app/Modules/Customers/Actions/LifecycleAsyncAction.php:108-110`
- **Correção (F5.8)**: Novo teste `SshConnectionException em cluster ativo → 503 cluster_unreachable + nada persistido` em `LifecycleTest`. Faz `ClusterServer::factory()->create(['status' => 'active'])` (cluster ATIVO — diferente do teste `cluster offline → 503` que usa `status='unreachable'`), mocka `SshClientInterface::runAsync()->andThrow(new SshConnectionException(...))`, asserta 503 + `cluster_unreachable` + verifica que `IdempotencyKey`, `Job` e `AuditLog` estão limpos. Comentário inline distingue este path (catch block) do path da guard preemptiva.
- **Descrição**: O teste `cluster offline → 503` cria `ClusterServer::factory()->create(['status' => 'unreachable'])`, o que dispara a guard preemptiva em `LifecycleAsyncAction:58` (`$cluster->status !== 'active'`) — esse path **nunca invoca SSH**. A catch block `SshConnectionException → ClusterUnreachableException` (linha 108-110) trata o cenário oposto: **cluster ativo, conexão SSH cai em runtime** (network glitch, host down momentaneamente). Esse path não é exercitado por nenhum teste, embora seja o cenário mais comum em produção.
- **Risco**: Médio — se a catch block `SshConnectionException` for removida em refactor, um cluster ativo com SSH morto retornaria 500 (uncaught exception) em vez de 503 amigável. Métrica de observability seria distorcida.
- **Ação necessária**: Adicionar teste:
  ```php
  it('SshConnectionException em cluster ativo → 503 cluster_unreachable', function () {
      $cluster = makeLifecycleCluster(); // status=active
      $customer = makeLifecycleCustomer($cluster);
      $operator = makeLifecycleOperator();
      $ssh = Mockery::mock(SshClientInterface::class);
      $ssh->shouldReceive('runAsync')->once()
          ->andThrow(new SshConnectionException('Connection refused'));
      $this->app->instance(SshClientInterface::class, $ssh);
      $this->actingAs($operator)
          ->postJson("/api/customers/{$customer->slug}/groups", ['name' => 'editors'])
          ->assertStatus(503)
          ->assertJsonPath('error', 'cluster_unreachable');
      expect(IdempotencyKey::count())->toBe(0);
  });
  ```
- **Esforço estimado**: P (~10min).

---

### QA-F5-019 — HIGH — `OccPanel::createUser` quebrado em produção: `request()->input('password')` sempre vazio (cobertura de teste falso-positiva via escape-hatch)

- **Sprint**: F5
- **Severidade**: HIGH
- **Tipo**: product_bug + test_fragility (convergente: auditor-senior R2 claude-4.6-sonnet + auditor-qa R2 gemini-3.1-pro)
- **Status**: **Validado (R3 — 2026-06-02)** — same-path strategy F5.11 confirmada por senior+qa R3; escape-hatch/`request()->input` ausentes; blade `wire:submit` + `wire:model="userPasswordPlain"`; 6 testes `createUser` no mesmo path. E2E browser permanece backlog **ISSUE-007**.
- **Arquivos**:
  - `app/Http/Livewire/Customers/OccPanel.php:214-220` (createUser)
  - `resources/views/livewire/customers/occ-panel.blade.php:180-203` (form + wire:click)
  - `tests/Feature/Livewire/Customers/OccPanelTest.php:266,293,306,321` (4 testes usando escape-hatch)
- **Descrição**: A fix R1 (F5.10, QA-F5-016) adicionou `?string $password = null` como escape-hatch ao `createUser` para permitir testes Livewire bypassarem `#[Locked]`. O fallback de produção `$password ?? request()->input('password', '')` é **inviável** em Livewire 3: a view dispara `wire:click="createUser"` (sem args, sem `<form wire:submit>`), e o input `<input type="password" name="password">` (sem `wire:model`) **não é incluído no payload JSON** enviado a `/livewire/update`. Resultado: `request()->input('password')` sempre retorna `''`, `strlen('') < 8` é sempre true, `addError('userPassword', ...)` dispara em **toda invocação real de criação de usuário**. Os 19 testes em `OccPanelTest` passam **exclusivamente** porque injetam a senha via `->call('createUser', 'Secret123!')` (escape-hatch — exercita o ramo do parâmetro, jamais o ramo `request()->input`). Test/production divergence — divergência semântica entre teste e produção.
- **Histórico**: O fallback `request()->input('password')` é pré-existente em `main` (introduzido em F2.5 quando `userPassword` virou `#[Locked]`). O F5 R1 follow-up **não introduziu o bug de produção**, mas **mascarou-o** ao adicionar 19 testes que dão falsa confiança de cobertura. A view não foi modificada nesta sprint.
- **Evidência (production):**
  ```blade
  {{-- resources/views/livewire/customers/occ-panel.blade.php:194-203 --}}
  <input class="form-input" type="password" name="password" autocomplete="new-password">
  <button class="btn-primary" wire:click="createUser">Criar Usuário</button>
  ```
  ```php
  // app/Http/Livewire/Customers/OccPanel.php:214-220
  public function createUser(LifecycleAsyncAction $action, ?string $password = null): void
  {
      $password = $password ?? request()->input('password', '');
      // ...
      if (strlen($password) < 8) {
          $this->addError('userPassword', 'Senha deve ter ao menos 8 caracteres.');
          return;
      }
  }
  ```
- **Evidência (test escape-hatch):**
  ```php
  // tests/Feature/Livewire/Customers/OccPanelTest.php:266,293,306,321
  ->call('createUser', 'Secret123!')  // injeta password via param — não exercita request()->input
  ```
- **Impacto**:
  - **Produção**: criação de usuário via OccPanel UI é **100% inoperante**. Toda tentativa retorna erro "Senha deve ter ao menos 8 caracteres" mesmo quando senha válida foi digitada.
  - **CI**: 19 testes passam (false-positive coverage); regressão é silenciosa.
  - **Workaround atual**: operadores devem usar `POST /api/customers/{slug}/users` (controller HTTP, funciona) ou SSH direto. UI inviável.
- **Ação necessária** (escolher uma):
  - **(A) Submit + wire:model.live (preferido, mínima)**: envolver o bloco "Criar Usuário" em `<form wire:submit.prevent="createUser">` + mudar botão para `type="submit"` + (opcional) `wire:model="userPasswordPlain"` em propriedade pública sem `#[Locked]` (com `unset()` no `finally`). `wire:submit` serializa FormData completo.
  - **(B) Alpine.js bridge**: `wire:click="createUser($el.closest('form').querySelector('[name=password]').value)"` — passa senha como argumento via referência DOM.
  - **(C) Hard fail loudly**: remover o fallback `request()->input(...)` e tornar `$password` obrigatório, forçando a view a passar o argumento explicitamente. Tests permanecem válidos.
  - **(D) Acompanhar com teste E2E real (Browser QA)** — qualquer das acima exige novo teste cobrindo o caminho `wire:click` real (via Playwright/Dusk) para evitar repetir a regressão de teste-vs-produção.
- **Convergência**: 2 auditores independentes (claude-4.6-sonnet-medium-thinking + gemini-3.1-pro) flagraram a mesma raiz; QA classificou CRITICAL, senior classificou HIGH. Mantida em HIGH (não CRITICAL) porque existe workaround documentado via API REST. **Decisão de stop-loss não se aplica** — HIGH em sprint aberta exige correção in-sprint (PROC-012) ou justificação documentada para COM_RESSALVAS.
- **Esforço estimado**: M (~30-60min) para opção (A) + teste E2E mínimo.
- **Correção (F5.11 — 2026-05-20T20:30Z)**:
  - **Blade** (`resources/views/livewire/customers/occ-panel.blade.php`): seção "Criar Usuário" envolvida em `<form wire:submit.prevent="createUser">`; input de senha agora usa `<input type="password" wire:model="userPasswordPlain">` (era `name="password"` sem `wire:model`); botão passa a ser `type="submit"`. O payload Livewire JSON enviado a `/livewire/update` agora carrega `userPasswordPlain` como parte do snapshot.
  - **Componente** (`app/Http/Livewire/Customers/OccPanel.php`): removida propriedade `#[Locked] public string $userPassword = '';` e atributo `Locked` (não usado em mais lugar nenhum). Adicionada `public string $userPasswordPlain = ''` (sem `#[Locked]` — é o canal natural do `wire:model`, mesmo modelo de qualquer formulário HTML, protegido por HTTPS + CSRF do endpoint Livewire). Método `createUser` perdeu o parâmetro `?string $password = null` e o fallback `request()->input('password', '')`; lê diretamente de `$this->userPasswordPlain`. `finally` zera `$this->userPasswordPlain = ''` para não persistir a senha no snapshot entre invocações. Chave da bag de erros mantida como `'userPassword'` para preservar `@error('userPassword')` na view e contratos de teste com `assertHasErrors(['userPassword'])`.
  - **Testes** (`tests/Feature/Livewire/Customers/OccPanelTest.php`): 4 testes existentes de `createUser` trocaram `->call('createUser', 'Secret123!')` por `->set('userPasswordPlain', 'Secret123!')->call('createUser')` — escape-hatch eliminado, mesmo path da produção. Acrescentados 2 testes novos: (a) regressão guard cobrindo o cenário original do bug (`createUser` sem `set('userPasswordPlain')` → `assertHasErrors(['userPassword'])`); (b) cleanup do snapshot (`userPasswordPlain === ''` após sucesso).
  - **Backlog**: criada `ISSUE-007` em `docs/ISSUES.md` para E2E real coverage via Dusk/Playwright (sprint N-UI dedicada — cobre o gap residual de browser real que `Livewire::test()` não cobre por design).
  - **Validação formal**: `/qa validar R3` **concluída** (2026-06-02) — **APROVADA** (`OccPanelTest` 25/25 no Docker). E2E browser backlog: **ISSUE-007**.

---

### QA-DYN-021 — CRITICAL — Callback `provision success` prematuro; tenant não ready para `users:*` (~10 min)

- **Sprint**: F8 (implementada 2026-05-23)
- **Severidade**: CRITICAL
- **Tipo**: race_condition / upstream_contract / onboarding_blocker
- **Status**: **Corrigido (F8 — Decision #ARCH-5)**
- **Origem**: testes dinâmicos API dev 2026-05-21; promovido de P-21 via `/triagem`
- **Arquivos**:
  - `app/Modules/Jobs/Services/WebhookHandler.php:161-173` (provision success → `Customer.status=active`)
  - `app/Modules/Customers/Actions/LifecycleAsyncAction.php` (sem readiness gate)
  - `app/Http/Controllers/Api/CustomerLifecycleController.php` (`users:create`, `users:delete`)
  - `tests/Feature/Jobs/WebhookHandlerTest.php:96-115` (assertion `active` imediato)
- **Descrição**: Upstream emite `job.finished` + `state=success` para provision antes de Redis/Collabora/14 apps estabilizarem. API marca tenant `active` e aceita lifecycle ops. Operações `users:create`/`users:delete` falham silenciosamente na janela Δt<10min (5/5); sucesso consistente Δt>30min (8/8). `groups:*` e `apps:*` funcionam na janela — subsistema de users demora mais a estabilizar.
- **Cadeia**: causa raiz de P-01; amplificado por P-05 (`exit_code`/`summary` null); mitigável em produto por P-22 (saga + readiness check).
- **Ação necessária**: ~~Fix Brief via `/qa debug ISSUE-010`~~ Implementado Sprint F8. ~~Validar via `/qa validar F8`~~ Validado REPROVADA (2 HIGH — F8.7+). Issue upstream (opção A) em paralelo recomendada.
- **Impacto**: Bloqueia onboarding automatizado ponta a ponta; fluxo manual com espera 30+ min funciona (mascara bug).

---

## Sprint F8 {#sprint-f8}

> Validação `/qa validar F8` — 2026-05-23. Brief: `docs/.briefs/F8.brief.md`. R1 follow-up F8.7–F8.10. **Resultado: APROVADA** (HIGH resolvidos; QA-F8-008/011 remanescentes non-blocking).

### QA-F8-001 — HIGH — Probe wall-clock timeout ~4× spec (~83 min vs ~20 min)

- **Sprint**: F8
- **Severidade**: HIGH
- **Tipo**: spec_drift / operational_sla
- **Status**: **Corrigido (F8.7)**
- **Arquivos**:
  - `app/Jobs/ProbeCustomerReadinessJob.php:27-30,59-77`
  - `config/services.php:52-55`
  - `docs/ROADMAP.md:3894,3931` (spec ~20 min)
- **Descrição**: `max_attempts=20` + backoff `[30,60,120,300×16]` ≈ 5010 s (~83 min) de delays, além de até 20× probes SSH. ROADMAP F8.2 e Fix Brief citam timeout **~20 min** → `failed` + audit `customer_readiness_timeout`.
- **Ação necessária**: Adicionar `max_wait_seconds` (~1200) ou reduzir attempts/intervals; teste de boundary; documentar env vars.

### QA-F8-002 — HIGH — Probe failure/timeout/exhaustion paths sem cobertura de teste

- **Sprint**: F8
- **Severidade**: HIGH
- **Tipo**: test_gap / regression_risk
- **Status**: **Corrigido (F8.8)**
- **Descrição**: Apenas happy path (SSH exit 0 → `active`) testado. Sem testes para: SSH connection failure, timeout, exit≠0 (not ready), max attempts → `status=failed` + audit `customer_readiness_timeout`, comportamento de `release()`/backoff.
- **Ação necessária**: Adicionar 3–4 cenários Pest em `CustomerReadinessTest.php`.

### QA-F8-003 — MEDIUM — Gate `DELETE users` → 503 não testado

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: test_gap / contract
- **Status**: **Corrigido (F8.9)**
- **Descrição**: OpenAPI e ARCH-5 gateiam POST e DELETE; testes cobrem só POST.
- **Ação necessária**: Mirror POST test para `DELETE /api/customers/{slug}/users/{username}`.

### QA-F8-004 — MEDIUM — Gate em `provisioning` (pre-finishing) não testado

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: test_gap
- **Status**: **Corrigido (F8.9)**
- **Descrição**: `USER_OPS_BLOCKED` inclui `provisioning` e `provisioning_finishing`; testes só exercitam finishing.
- **Ação necessária**: Teste POST users com `status=provisioning` → 503.

### QA-F8-005 — MEDIUM — Sync pode promover `provisioning` → `active` (bypass gate)

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: race_condition / fail_open
- **Status**: **Corrigido (F8.10)**
- **Descrição**: Guard aplica só a `provisioning_finishing`. Resync/cron com upstream `running` pode promover `provisioning` → `active` antes do probe, reabrindo janela de race para `users:*`.
- **Ação necessária**: Estender guard para `provisioning`; teste de regressão.

### QA-F8-006 — MEDIUM — OccPanel sem UX para `TenantNotReadyException`

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: ux / production_divergence
- **Status**: **Corrigido (F8.10)**
- **Descrição**: Gate funciona (mesmo `LifecycleAsyncAction`), mas `formatError()` não trata `TenantNotReadyException` → mensagem genérica. OccPanelTest sempre seeda `active`.
- **Ação necessária**: Branch PT amigável + Livewire test com customer `provisioning_finishing`.

### QA-F8-007 — MEDIUM — Idempotência webhook `job.finished` duplicado não testada

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: test_gap
- **Status**: **Corrigido (F8.8)**
- **Descrição**: Guard early-return quando `$job->state === $canonical` evita re-dispatch do probe; `job.started` replay testado, `job.finished` não.
- **Ação necessária**: `handle()` 2× com mesmo payload success → `Queue::assertPushed(ProbeCustomerReadinessJob::class, 1)`.

### QA-F8-008 — MEDIUM — E2E Marina mascara dependência de queue worker

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: test/production_divergence
- **Status**: Pendente
- **Arquivos**: `tests/Feature/E2E/CriticalFlowsTest.php:123-141`
- **Descrição**: `Queue::fake()` + probe inline via `->handle()` — E2E passa mesmo se worker nunca processar job em produção.
- **Ação necessária**: Teste negativo 503 durante finishing; documentar dependência de worker no RUNBOOK.

### QA-F8-009 — MEDIUM — Job `ProbeCustomerReadinessJob` sem `$timeout`

- **Sprint**: F8
- **Severidade**: MEDIUM
- **Tipo**: ops / spec_drift
- **Status**: **Corrigido (F8.7)**
- **Descrição**: ROADMAP especifica `$timeout = 120`; job não declara timeout. Worker pode ficar preso em SSH lento.
- **Ação necessária**: `public int $timeout = 120;` (ou config-driven).

### QA-F8-010 — LOW — Badge UI ausente para `status=failed` pós-timeout

- **Sprint**: F8
- **Severidade**: LOW
- **Tipo**: ux
- **Status**: **Corrigido (F8.10)**
- **Descrição**: Timeout do probe seta `failed`; badges definem `badge-error` mas não mapeiam `failed` explicitamente; filtro dropdown omite `failed`.
- **Ação necessária**: Estilo `badge-failed` ou alias para `failed`.

### QA-F8-011 — LOW — Customer soft-deleted mid-probe — no-op silencioso

- **Sprint**: F8
- **Severidade**: LOW
- **Tipo**: edge_case
- **Status**: Pendente
- **Arquivos**: `ProbeCustomerReadinessJob.php:35-38`
- **Descrição**: `Customer::find()` retorna null em soft-delete → job retorna sem audit.
- **Ação necessária**: Teste documentando comportamento; opcional audit `customer_readiness_aborted`.

---

### QA-F9-001 — MEDIUM — `ModelNotFoundException` em rotas API existentes retorna `route_not_found`

- **Sprint**: F9
- **Severidade**: MEDIUM (downgrade de HIGH proposto pelo auditor-qa — side effect do handler amplo, fora do escopo primário ISSUE-012)
- **Tipo**: contract_violation
- **Auditoria**: QA + Senior (convergente como nota non-blocking)
- **Status**: Pendente
- **Arquivo**: `bootstrap/app.php` (handler global `NotFoundHttpException`)
- **Descrição**: O handler customizado captura **todos** os `NotFoundHttpException` sob `api/*`, incluindo os convertidos de `ModelNotFoundException` pelo Laravel (`Handler::prepareException`). Ex.: `GET /api/queue/{uuid-inexistente}` (rota existe, recurso não) retorna `{error: route_not_found}` em vez de sinal de recurso ausente (`not_found` como em `OccController`, ou `{message: ...}` padrão Laravel).
- **Impacto**: DX/contract — clientes não distinguem URL inválida vs recurso inexistente. Melhoria líquida vs pré-F9 quando cliente não enviava `Accept` (antes: HTML; agora: JSON parseável).
- **Ação necessária**: Guard no handler (`$e->getPrevious() instanceof ModelNotFoundException` → `{error: not_found}` ou deixar fallback Laravel); teste Feature autenticado para job/customer slug inexistente.

### QA-F9-002 — MEDIUM — Critério ISSUE-012 (`APP_DEBUG` sem leak) sem teste de regressão

- **Sprint**: F9
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: QA + Senior
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Api/ApiNotFoundJsonTest.php`
- **Descrição**: ISSUE-012 exige verificar que payload 404/405 não expõe `trace`/`file` quando `APP_DEBUG=true`. Implementação atual é segura (payload fixo), mas nenhum teste seta `config(['app.debug' => true])` e asserta ausência de chaves de debug.
- **Ação necessária**: Adicionar testes 404/405 com `APP_DEBUG=true` + `assertJsonMissing(['trace','file','exception'])`.

### QA-F9-003 — MEDIUM — 405 JSON sem `Accept: text/html` não exercitado explicitamente

- **Sprint**: F9
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Api/ApiNotFoundJsonTest.php`
- **Descrição**: Teste 405 usa `$this->call('GET', '/api/jobs/hook')` sem `Accept` explícito, mas não prova o cenário hostil `Accept: text/html,*/*` (modo de falha original do ISSUE-012 para 404).
- **Ação necessária**: Repetir 405 com header `HTTP_ACCEPT: text/html,application/xhtml+xml` + assert JSON.

### QA-F9-004 — LOW — Cenário 405 invertido vs spec (POST em rota GET-only)

- **Sprint**: F9
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Api/ApiNotFoundJsonTest.php`
- **Descrição**: ROADMAP F9.2 exemplifica `POST` em rota só-GET; teste cobre `GET` em rota só-POST (`/api/jobs/hook`). Mesma exception class, mas direção inversa não testada.
- **Ação necessária**: Adicionar `POST /api/queue` (sem auth) → 405 JSON `method_not_allowed`.

### QA-F9-005 — LOW — `GET /api` (path exato) pode continuar retornando HTML

- **Sprint**: F9
- **Severidade**: LOW
- **Tipo**: product_bug
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `bootstrap/app.php` — `$request->is('api/*')`
- **Descrição**: `Str::is('api/*', 'api')` não casa (`api/foo` sim). Rota raiz `/api` sem segmento trailing pode cair no template HTML 404 se cliente não enviar `Accept`.
- **Ação necessária**: Expandir match para `$request->is('api', 'api/*')`; teste `GET /api` sem Accept → JSON 404.

---


## Sprint F10 — JobLogFetcher argv fix (ISSUE-014)

> Fast-track 2026-05-24. Código F10.1–F10.2 mergeado (`197ff46`). Gate operacional F10.3 / ISSUE-023 pendente.

### QA-F10-001 — MEDIUM — `JobLogFetcher` argv incluía client slug em comando introspection `job`

- **Sprint**: F10
- **Severidade**: MEDIUM
- **Tipo**: bug / ssh_argv
- **Status**: **Corrigido (F10.1–F10.2 — 2026-05-24)**
- **Issue**: ISSUE-014
- **Arquivos**: `app/Modules/Jobs/Services/JobLogFetcher.php`, `tests/Feature/Jobs/JobLogFetcherTest.php`
- **Descrição**: Fallback SSH pós-`job.finished` montava argv com slug do customer antes de `job`, causando exit 101 `cmd_not_allowed` em 100% das tentativas — sintoma de logs vazios em `/queue/{jobId}` (ISSUE-009).
- **Correção**: argv `['job', $jobId, 'logs', '--json']` (sem client slug); fallback `status --json`; catch `SshRemoteException(notImplemented)`.
- **Validação produção**: pendente — **F10.3** / **ISSUE-023**.

---

## Sprint F11 — Slug reuse pós-falha + cleanup MEDIUM F5 (ISSUE-018)

> Auditoria `/qa validar R1` 2026-05-24: REPROVADA → 7 findings corrigidos in-sprint → **APROVADA** após follow-up. Suite 394+ passed.

### CQ-F11-001 — CRITICAL — `forceDelete` de ghost Customer viola FK `jobs.customer_slug` RESTRICT

- **Sprint**: F11
- **Severidade**: CRITICAL
- **Tipo**: data_integrity / fk_violation
- **Status**: **Corrigido (F11.1 R1 follow-up — 2026-05-24)**
- **Issue**: ISSUE-018
- **Arquivo**: `app/Modules/Customers/Actions/ProvisionCustomerAction.php`
- **Descrição**: Re-provision tentava `forceDelete` em customer soft-deleted com jobs históricos referenciando o slug — bloqueio FK ou perda de audit trail.
- **Correção**: `restore()` + `update()` no ghost em vez de `forceDelete` + `create`; jobs anteriores preservados.

### CQ-F11-002 — HIGH — `ProvisionCustomerRequest` `unique:customers,slug` não ignorava soft-deleted

- **Sprint**: F11
- **Severidade**: HIGH
- **Tipo**: validation / slug_reuse
- **Status**: **Corrigido (F11.1 — 2026-05-24)**
- **Issue**: ISSUE-018
- **Arquivo**: `app/Http/Requests/ProvisionCustomerRequest.php`
- **Descrição**: Slug de ghost soft-deleted retornava 422 "Slug já em uso" impedindo re-provisioning.
- **Correção**: `Rule::unique('customers', 'slug')->whereNull('deleted_at')`.

### QA-F11-001 — HIGH — Re-provisioning e2e após `provision.failed` sem teste de FK + restore

- **Sprint**: F11
- **Severidade**: HIGH
- **Tipo**: test_coverage / e2e
- **Status**: **Validado (F11.1 R1 — 2026-05-24)**
- **Arquivo**: `tests/Feature/Customers/ProvisionTest.php`
- **Descrição**: Cenário ghost + Job FK + re-POST `/api/customers` não coberto; regressão em `forceDelete` seria silenciosa.
- **Correção**: Teste `re-provisionar slug após provision.failed → ghost restaurado` + assert jobs históricos preservados.

### QA-F11-002 — MEDIUM — `WebhookHandler` provision failed: soft-delete sem teste de audit trail

- **Sprint**: F11
- **Severidade**: MEDIUM
- **Tipo**: test_coverage
- **Status**: **Validado (F11.1 — 2026-05-24)**
- **Arquivo**: `tests/Feature/Jobs/WebhookHandlerTest.php`
- **Descrição**: Branch `provision failed → customer soft-deleted` sem regressão; risco de hard-delete ou audit omitido.
- **Correção**: Testes `job.finished provision failed/cancelled → customer soft-deletado` + assert `AuditLog webhook_received`.

### QA-F11-003 — MEDIUM — `dispatchAppsCsv` sem cobertura de `mapLifecycleException` (503/504)

- **Sprint**: F11
- **Severidade**: MEDIUM
- **Tipo**: test_coverage / negative_path
- **Status**: **Validado (F11 R1 follow-up — 2026-05-24)**
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: Após extração de `mapLifecycleException` (CQ-F5-003), paths apps CSV para cluster offline e SSH timeout não testados.
- **Correção**: Testes `apps/enable: cluster offline → 503` e `apps/enable: SSH timeout → 504`.

### QA-F11-004 — MEDIUM — `dispatchAppsCsv` `SshRemoteException` sem assert de `apps_csv` no 502

- **Sprint**: F11
- **Severidade**: MEDIUM
- **Tipo**: test_coverage / contract
- **Status**: **Validado (F11 R1 follow-up — 2026-05-24)**
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: Erro upstream em apps/disable deve incluir `apps_csv` no payload 502 — simétrico ao sucesso 202.
- **Correção**: Teste `apps/disable: SSH error → 502 com apps_csv`.

### QA-F11-005 — MEDIUM — Idempotency conflict via `dispatchAppsCsv` sem teste dedicado

- **Sprint**: F11
- **Severidade**: MEDIUM
- **Tipo**: test_coverage
- **Status**: **Validado (F11 R1 follow-up — 2026-05-24)**
- **Arquivo**: `tests/Feature/Customers/LifecycleTest.php`
- **Descrição**: Path 409 `idempotency_conflict` coberto em `dispatch()` mas não explicitamente em apps CSV após refactor.
- **Correção**: Cobertura ampliada no follow-up F11 (mapLifecycle + apps paths); suite lifecycle verde pós-R1.

---

## Sprint F12 — SSH transport exception normalization (ISSUE-020)

> Concluída 2026-05-27. Sem `/qa validar F12` formal registrado — testes `SshClientTest` verdes.

### QA-F12-001 — MEDIUM — `ConnectionClosedException` do phpseclib escapa sem retry no pool SSH

- **Sprint**: F12
- **Severidade**: MEDIUM
- **Tipo**: resilience / exception_leak
- **Status**: **Corrigido (F12.1 — 2026-05-27)**
- **Issue**: ISSUE-020
- **Arquivos**: `app/Modules/Core/Ssh/SshClient.php`, `tests/Feature/Core/SshClientTest.php`
- **Descrição**: Conexão pooled fechada antes de `exec()` lançava exceção crua; `ProbeCustomerReadinessJob` registrava `local.ERROR` e não acionava retry.
- **Correção**: `try/catch` em `exec()`/`execWithStdin()` → `SshConnectionException` + remove conexão stale do pool; teste retry na segunda tentativa.

---

## Sprint F13 — Branding payload no job create (ISSUE-019)

> Validação F13 R1 **APROVADA** (2026-05-28). ProvisionTest 16 passed.

### CQ-F13-001 — HIGH — Limite base64/JSON stdin subestimado para branding inline

- **Sprint**: F13
- **Severidade**: HIGH
- **Tipo**: contract / payload_size
- **Status**: **Corrigido (F13 R1 follow-up — 2026-05-28)**
- **Issue**: ISSUE-019
- **Descrição**: Payload inline podia exceder limite real do stdin upstream quando logo em base64.
- **Correção**: Threshold alinhado ao limite real; branch SFTP staging quando >256KB.

### CQ-F13-002 — HIGH — Tratamento de staging SFTP incompleto para anexos grandes

- **Sprint**: F13
- **Severidade**: HIGH
- **Tipo**: ssh / sftp
- **Status**: **Corrigido (F13 R1 follow-up — 2026-05-28)**
- **Issue**: ISSUE-019
- **Correção**: `inboxInit` + `sftpUpload` + `--staging-id` no argv create.

### CQ-F13-003 — MEDIUM — `Storage::put` / persistência de branding_meta frágil

- **Sprint**: F13
- **Severidade**: MEDIUM
- **Tipo**: persistence
- **Status**: **Corrigido (F13 R1 follow-up — 2026-05-28)**
- **Issue**: ISSUE-019
- **Correção**: `persistBrandingFiles` + `branding_meta` atualizado; re-provision reutiliza logo cadastrado.

### QA-F13-001 — MEDIUM — Gap de teste HTTP multipart / branding no create

- **Sprint**: F13
- **Severidade**: MEDIUM
- **Tipo**: test_coverage
- **Status**: **Validado (F13 R1 — 2026-05-28)**
- **Issue**: ISSUE-019
- **Correção**: `ProvisionTest` ampliado (16 passed, 63 assertions); cenários inline + SFTP + ghost re-provision com logo.

---

### DOC-001 — MEDIUM — OpenAPI documenta envelope `{ success, message, data }`; código usa `{ error }` + JsonResource

- **Sprint**: PMO
- **Severidade**: MEDIUM
- **Tipo**: api_contract_drift / documentation
- **Status**: Pendente
- **Registrado em**: 2026-06-02
- **Issue**: ISSUE-021
- **Arquivos**: `docs/openapi.yaml` (`info.description` L26-28, `components/schemas/*`), referência `.cursor/skills/api-rest-patterns/references/response-format.md`
- **Descrição**: Controllers (`CustomerController`, `CustomerLifecycleController`, `OccController`, `JobController`) retornam erros como `{ "error": "<code>", ... }` e sucesso via `JsonResource` ou `{ "job_id": "..." }` (202). O OpenAPI ainda descreve envelope legado `{ success, message, data }` para sucesso/erro genérico. `CQ-F5-001` (Validado) corrigiu apenas drift de endpoints `apps/*` e 501 — não o contrato global.
- **Impacto**: Integradores e geradores de cliente que confiam só no OpenAPI implementam parsers incorretos; suporte perde tempo em “API bugada”.
- **Ação necessária**: Alinhar `docs/openapi.yaml` ao código (ou bump major version se houver consumidores externos no envelope antigo); `redocly lint`; exemplos reais nos paths críticos.

## Sprint N19 — Cutover transporte SSH → agente (Fase 1)

> Validação R1: 2026-06-12 (`/qa validar N19`). Delta `3a8fb65..29d54b8`. review: senior+qa.

### CQ-N19-001 — HIGH — Evento de agente grava `job_id` em `operation_id` sem validar ownership

- **Sprint**: N19
- **Severidade**: HIGH
- **Tipo**: product_bug / security
- **Auditoria**: Senior
- **Status**: **Validado (N19 R2 — 2026-06-12)**

### QA-N19-001 — HIGH — Branding SFTP não testado com agent transport habilitado

- **Sprint**: N19
- **Severidade**: HIGH
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: **Validado (N19 R2 — 2026-06-12)**

### CQ-N19-002 — MEDIUM — `waitForJobId` bloqueia worker PHP até 30s

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Status**: Pendente
- **Arquivo**: `app/Modules/Agents/Services/AgentUpstreamGateway.php`
- **Registrado em**: 2026-06-12
- **Descrição**: Polling síncrono com `usleep(100_000)` por até 30s na thread HTTP. Risco de esgotar workers FPM sob carga no piloto.
- **Correção sugerida**: Reduzir janela, mover para queue assíncrona, ou documentar SLA/limites de concorrência no runbook.

### CQ-N19-003 — MEDIUM — Exceções do agent path não mapeadas para domínio da API

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Status**: Pendente
- **Arquivo**: `app/Modules/Customers/Actions/ProvisionCustomerAction.php`, `RemoveCustomerAction.php`
- **Registrado em**: 2026-06-12
- **Descrição**: `catch` cobre `SshRemoteException`/`SshConnectionException`, mas `AgentUpstreamGateway` lança `\RuntimeException` (timeout, agente ausente). Vira 500 genérico.
- **Correção sugerida**: `AgentTransportException` ou mapear para `ClusterUnreachableException` equivalente.

### CQ-N19-004 — MEDIUM — TOCTOU: `findAgentForCluster` ignora `isOnline()`

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Status**: Pendente
- **Arquivo**: `app/Modules/Agents/Services/AgentTransportResolver.php`
- **Registrado em**: 2026-06-12
- **Descrição**: `shouldUseAgentTransport()` exige online; `runAsync()` usa `findAgentForCluster()` sem revalidar. Agente pode cair entre as chamadas → timeout 30s sem fallback SSH.
- **Correção sugerida**: Unificar `resolveActiveAgent()` usado por resolver e gateway.

### CQ-N19-005 — MEDIUM — Falha do agente com `data` estruturado não encerra o poll

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Auditoria**: Senior
- **Status**: Pendente
- **Arquivo**: `app/Modules/Agents/Services/AgentEventHandler.php`
- **Registrado em**: 2026-06-12
- **Descrição**: Erro só vai para cache quando `data` não é array e `state === 'failed'`. `state: failed` + `data: { error }` deixa `waitForJobId` esperar timeout.
- **Correção sugerida**: Tratar `data.error` / `state === 'failed'` sem `job_id`; alinhar contrato com agent.

### QA-N19-002 — MEDIUM — Fallback SSH no remove sem teste simétrico

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Customers/AgentTransportCutoverTest.php`
- **Registrado em**: 2026-06-12
- **Descrição**: Existe fallback SSH no provision (`transport_enabled=false`), mas não no remove.
- **Correção sugerida**: Teste remove com flag off: `ssh->runAsync` once, `gateway->shouldNotReceive`.

### QA-N19-003 — MEDIUM — Fallback SSH com agente offline não testado no action layer

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Customers/AgentTransportCutoverTest.php`
- **Registrado em**: 2026-06-12
- **Descrição**: Resolver testa offline em unit; não há feature test provando SSH quando flag ON mas agente offline/ausente.
- **Correção sugerida**: Flag ON + `FarmAgent::offline()`: assert `ssh->runAsync`, `gateway->shouldNotReceive`.

### QA-N19-004 — MEDIUM — `AgentUpstreamGateway` sem testes dedicados

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `app/Modules/Agents/Services/AgentUpstreamGateway.php`
- **Registrado em**: 2026-06-12
- **Descrição**: Zero testes para `resolveOperation`, timeout, erro via cache, cmd inválido.
- **Correção sugerida**: Unit tests com `Cache::fake()` + mock `AgentCommandQueue`.

### QA-N19-005 — MEDIUM — Handshake enqueue→event→cache não integrado

- **Sprint**: N19
- **Severidade**: MEDIUM
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Api/AgentGatewayTest.php`
- **Registrado em**: 2026-06-12
- **Descrição**: Cutover tests mockam gateway; caminho real enqueue → POST events → cache → `waitForJobId` não exercitado.
- **Correção sugerida**: Feature test sem mock do gateway: enqueue → evento com `data.job_id` → assert retorno.

### CQ-N19-006 — LOW — Sem teste do bypass SFTP/staging → SSH

- **Sprint**: N19
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: Senior
- **Status**: Pendente
- **Motivo LOW**: Duplicata parcial de QA-N19-001; cobertura ausente mas lógica presente no código.

### CQ-N19-007 — LOW — Bridge cache↔eventos só testado via mock

- **Sprint**: N19
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: Senior
- **Status**: Pendente
- **Motivo LOW**: Sobreposição com QA-N19-005; risco mitigável com smoke manual no piloto.

### QA-N19-006 — LOW — Asserts rasos nos mocks de cutover

- **Sprint**: N19
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Motivo LOW**: Não valida `--idempotency-key`, `--callback`, `stdinJson` nos mocks.

### QA-N19-007 — LOW — Remove via agente com `--backup-first` não coberto

- **Sprint**: N19
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Motivo LOW**: Cutover remove usa `backupFirst: false` apenas.

### QA-N19-008 — LOW — Provision com branding inline via agente não testado

- **Sprint**: N19
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Motivo LOW**: Cenário stdin ≤256KB com agent não exercitado.

### QA-N19-009 — LOW — Testes legados não fixam `transport_enabled=false`

- **Sprint**: N19
- **Severidade**: LOW
- **Tipo**: test_fragility
- **Auditoria**: QA
- **Status**: Pendente
- **Motivo LOW**: Default false hoje; frágil se config global mudar.

---

### OPS-001 — LOW — Tabela `failed_jobs` ausente em produção

- **Sprint**: PMO
- **Severidade**: LOW
- **Tipo**: ops / schema_gap
- **Status**: Pendente
- **Registrado em**: 2026-06-02
- **Issue**: ISSUE-023
- **Evidência**: SSH `deployer.mework360.com.br` 2026-06-02 — `Schema::hasTable('failed_jobs')` → false; migrations listadas todas Ran (nenhuma migration `failed_jobs` no histórico do projeto).
- **Descrição**: Worker Laravel (`queue:work redis`) está ativo no host, mas falhas de jobs locais (e-mail, probes, etc.) não têm destino `failed_jobs` padrão se a tabela não existir — comportamento depende da config `queue.failed` e versão Laravel.
- **Impacto**: Perda de visibilidade de falhas da fila **local** (não confundir com fila Redis upstream de jobs Nextcloud). Baixo volume hoje, mas dificulta debug de `ProbeCustomerReadinessJob` e mail queue.
- **Ação necessária**: Decidir: (a) publicar migration `failed_jobs` + `job_batches` se necessário, ou (b) documentar em RUNBOOK que falhas locais só aparecem em `storage/logs/laravel.log`. Validar em ISSUE-023 checklist.

---

### SEC-V1-001 — HIGH — `ApiKey.scopes` nunca aplicado + ausência de autorização por tenant (IDOR latente)

- **Sprint**: — (registrado via triagem 2026-06-16; pré-requisito da API externa `/v1`)
- **Severidade**: HIGH (torna-se CRITICAL ao emitir chaves para terceiros)
- **Tipo**: security / authorization gap
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: Painel de arquitetura adversarial (crítico de Segurança) sobre o objetivo "dois contratos / ACL" (`.arch-panel/panel/critique-seguranca.md`); verificado no código.
- **Arquivo**: `app/Providers/AppServiceProvider.php` (L50-70, guard `api-key`), `app/Models/ApiKey.php` (coluna `scopes` cast array), `app/Modules/Core/Services/ApiKeyService.php`, `app/Http/Livewire/ApiKeys/Index.php` (L72-76), `routes/api.php`.
- **Descrição**: O guard `Auth::viaRequest('api-key', ...)` resolve a chave pelo `token_hash` e retorna o `Operator` **inteiro**, sem nunca consultar `ApiKey.scopes`. A coluna existe (fillable + cast `array`) e é persistida em `ApiKeyService::generate()`, mas **nenhum ponto do código a verifica** — e a UI (`ApiKeys/Index.php`) sempre cria com `scopes: null`. Além disso, **não há autorização por tenant**: as rotas usam `auth:web,api-key` + `active.operator`, e o `{customer}` vem do slug na URL; qualquer chave válida (→ operador ativo) age sobre **qualquer** customer. Só `DELETE /customers` tem gate adicional (`can:provision-customers`), e mesmo assim por role, não por tenant.
- **Impacto**: Hoje o risco é **latente/contido** — chaves são emitidas apenas a operadores internos confiáveis via painel admin. Mas (a) `scopes` é *dead code* que dá falsa sensação de segurança (least-privilege que não existe), e (b) abrir a API a terceiros (WHMCS, onboarding-api, parceiros — plano da v1) sem binding tenant↔principal vira **IDOR sistêmico** e exposição cross-tenant de dados de clientes (LGPD). Pré-requisito DURO antes de qualquer credencial externa.
- **Ação necessária**:
  1. Aplicar `ApiKey.scopes` no guard (negar capability fora do escopo) — encerrar o gap deixado por SEC-F004.
  2. Introduzir binding explícito tenant↔principal (allowlist de slugs ou claim `tenant_id`) verificado em **todo** `/customers/{customer}/*` antes de qualquer ação no `PlatformPort`/Action.
  3. Teste negativo obrigatório: chave do parceiro A → `403` no tenant B.
  4. Registrar `api_key_id`/escopo no `AuditLog` por capability.
- **Correção** (Sprint F15, 2026-06-16): Guard `api-key` vincula `ApiKey` ao request (`current_api_key()`); middlewares `EnsureApiKeyScope` (`api.scope`) e `EnsureTenantBinding` (`api.tenant`) aplicados em `routes/api.php`; coluna `allowed_tenant_slugs` em `api_keys`; `AuditLog.api_key_id` preenchido automaticamente em ações via Bearer. Testes: `ApiKeyAuthorizationTest`.
- **Relacionados**: SEC-F004 (guard Bearer — implementado, escopo nunca aplicado), painel `.arch-panel/panel/final.md` (authz escopado promovido a gate de Sprint 0/Fase inicial), ISSUE-037.
- **Validação** (F15 R2, 2026-06-17): Scopes + tenant binding completos (lifecycle/OCC/DELETE/POST customers); `api_key_id` testado; senior R2 PASS. **Validado**.

---

## Sprint F15 — AuthZ ApiKey (validação R1)

> Registrado via `/qa validar F15` R1 em 2026-06-16. Senior review no delta `main...HEAD`.

### CQ-F15-001 — HIGH — `DELETE /customers/{slug}` ignora tenant binding

- **Sprint**: F15
- **Severidade**: HIGH (IDOR destrutivo cross-tenant)
- **Tipo**: security / authorization gap
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: `/qa validar F15` — auditor-senior R1
- **Arquivo**: `routes/api.php`, `app/Http/Middleware/EnsureTenantBinding.php`, `tests/Feature/Auth/ApiKeyAuthorizationTest.php`
- **Descrição**: `EnsureTenantBinding` só lê `route('customer')`. A rota de remoção usa parâmetro `{slug}` e não carrega middleware `api.tenant`. Chave Bearer com `allowed_tenant_slugs: ['tenant-a']` + `customers:write` pode iniciar `DELETE /api/customers/tenant-b` sem `forbidden_tenant`.
- **Correção** (campanha F15 follow-up, 2026-06-17): `api.tenant` em `DELETE /customers/{slug}`; `resolveCustomerSlug()` lê `route('slug')`; teste negativo cross-tenant DELETE.
- **Validação** (F15 R2, 2026-06-17): fix verificado in-code + teste negativo passa; senior R2 PASS.
- **Impacto**: Remoção cross-tenant com chave tenant-restrita; bloqueia confiança em credenciais externas (ISSUE-037 / SEC-V1-001).

### CQ-F15-002 — MEDIUM — `POST /customers` contorna allowlist de tenant

- **Sprint**: F15
- **Severidade**: MEDIUM
- **Tipo**: security / least privilege
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: `/qa validar F15` — auditor-senior + auditor-security R1
- **Arquivo**: `routes/api.php`, `app/Http/Middleware/EnsureTenantBinding.php`, `tests/Feature/Auth/ApiKeyAuthorizationTest.php`
- **Descrição**: Provisionamento não passa por `api.tenant`. Chave restrita a `tenant-a` pode criar customer com slug arbitrário se tiver `customers:write`.
- **Correção** (campanha F15 follow-up, 2026-06-17): `api.tenant` em `POST /customers`; `resolveCustomerSlug()` lê `slug` do body; teste negativo provision cross-tenant.
- **Validação** (F15 R2, 2026-06-17): fix verificado in-code + teste negativo passa.

### CQ-F15-003 — MEDIUM — `POST /queue/{id}/cancel` exige apenas `queue:read`

- **Sprint**: F15
- **Severidade**: MEDIUM
- **Tipo**: authorization / least privilege
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: `/qa validar F15` — auditor-senior R1
- **Arquivo**: `routes/api.php`, `tests/Feature/Auth/ApiKeyAuthorizationTest.php`
- **Descrição**: Cancelamento é mutação de estado mas está no grupo `api.scope:queue:read`. Chave read-only pode cancelar jobs.
- **Correção** (campanha F15 follow-up, 2026-06-17): `POST /queue/{id}/cancel` exige `queue:write`; GETs permanecem em `queue:read`; teste negativo cancel com só `queue:read`.
- **Validação** (F15 R2, 2026-06-17): fix verificado + `CancelJobTest` regressão OK.

### CQ-F15-005 — MEDIUM — Sem teste de `api_key_id` em ações Bearer (F15.5)

- **Sprint**: F15
- **Severidade**: MEDIUM
- **Tipo**: test gap
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: `/qa validar F15` — auditor-senior R1
- **Arquivo**: `tests/Feature/Auth/ApiKeyAuthorizationTest.php`, `app/Models/AuditLog.php`
- **Descrição**: F15.5 exige `api_key_id` em audit via Bearer. Implementação existe no `creating` hook, mas nenhum teste asserta o campo.
- **Correção** (campanha F15 follow-up, 2026-06-17): Teste `records api_key_id on AuditLog when bearer key cancels a job` asserta `api_key_id` após `POST /queue/{id}/cancel`.
- **Validação** (F15 R2, 2026-06-17): teste passa; assert confirma `api_key_id`.

### CQ-F15-007 — LOW — FK `audit_logs.api_key_id` sem `onDelete`

- **Sprint**: F15
- **Severidade**: LOW
- **Tipo**: data integrity
- **Status**: Pendente
- **Registrado em**: 2026-06-16
- **Origem**: `/qa validar F15` — auditor-senior R1
- **Arquivo**: `database/migrations/2026_06_16_000002_add_api_key_id_to_audit_logs_table.php`
- **Descrição**: FK default RESTRICT impede hard-delete de `api_keys` referenciadas.
- **Ação necessária**: `->nullOnDelete()` se auditoria deve sobreviver à remoção da chave.

### CQ-F15-008 — LOW — Semântica de `allowed_tenant_slugs: []` não testada

- **Sprint**: F15
- **Severidade**: LOW
- **Tipo**: test gap / contract clarity
- **Status**: Pendente
- **Registrado em**: 2026-06-16
- **Origem**: `/qa validar F15` — auditor-senior R1
- **Arquivo**: `app/Http/Middleware/EnsureTenantBinding.php`, `tests/Feature/Auth/ApiKeyAuthorizationTest.php`
- **Descrição**: `[]` não é `null` → deny em rotas tenant-scoped (correto), mas sem teste nem documentação explícita.
- **Ação necessária**: Teste 403 em lifecycle + documentar: `null` = unrestricted, `[]` = deny-all tenant routes.

---

## Sprint N30 — API `/api/v1` Sprint 0 (ISSUE-038)

> Registrado via `/qa validar N30` R1 em 2026-06-17. Senior + security review na superfície externa. PR #115 mergeada; follow-up segurança `837173c`.

### CQ-N30-001 — HIGH — `GET /api/v1/jobs/{id}` sem tenant binding (IDOR)

- **Sprint**: N30
- **Severidade**: HIGH
- **Tipo**: security / IDOR
- **Status**: Validado
- **Registrado em**: 2026-06-17
- **Origem**: `/qa validar N30` — auditor-senior + auditor-security R1
- **Arquivo**: `app/Http/Controllers/Api/V1/JobV1Controller.php`, `routes/api_v1.php`
- **Descrição**: Lookup de job por UUID global sem verificar `allowed_tenant_slugs` — chave parceiro tenant A podia ler job de tenant B via `GET /api/v1/jobs/{id}` (rota sem `{slug}`).
- **Correção** (Sprint N30, commit `837173c`): `JobV1Controller::isJobForbiddenForCurrentApiKey()` nega acesso quando `customer_slug` do job está fora da allowlist da chave; retorna `403 forbidden_scope` via `DomainError`.
- **Validação** (N30 R1, 2026-06-17): teste `denies cross-tenant GET /api/v1/jobs/{id}` em `ApiV1AuthorizationTest`; validation_gate_qa APROVADA.

### SEC-N30-001 — HIGH — vazamento `exit_code` em erro de provision `POST /api/v1/tenants`

- **Sprint**: N30
- **Severidade**: HIGH
- **Tipo**: security / protocol leak
- **Status**: Validado
- **Registrado em**: 2026-06-17
- **Origem**: `/qa validar N30` — auditor-security R1
- **Arquivo**: `app/Http/Exceptions/RenderDomainError.php`, controllers V1 de provision, `tests/Feature/Api/V1/DomainErrorSanitizationTest.php`
- **Descrição**: Falha SSH no provision propagava campos do protocolo NC (`exit_code`) na resposta JSON v1 — viola gate ADR Sprint 0 (sanitização na borda).
- **Correção** (Sprint N30, commit `837173c`): mapeamento upstream → `DomainError` antes da borda HTTP; respostas v1 usam envelope `{ error: { code, message } }` sem termos NC.
- **Validação** (N30 R1, 2026-06-17): teste `returns DomainError without exit_code when provision SSH fails on POST /api/v1/tenants`; `DomainErrorSanitizationTest` regex negativa; validation_gate_qa APROVADA.

---

## Sprint F14 — CI verde no main (regressão N19 + phpseclib)

> Registrado via `/pmo fix` em 2026-06-16. CI run `27646529336` (commit `1eebced`): Lint OK; Test 6 failed; composer audit 1 advisory.

### QA-F14-001 — HIGH — `AuditLogTest` quebrado após remoção de secrets do `$fillable` (N19)

- **Sprint**: F14
- **Severidade**: HIGH (CI blocker — 2 testes)
- **Tipo**: regression / test gap
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: Investigação CI run `27646529336`; commit `4e2d1e9` (sprint N19)
- **Arquivo**: `tests/Feature/Audit/AuditLogTest.php`, `app/Models/ClusterServer.php` (`$fillable`)
- **Descrição**: N19 removeu `ssh_private_key_encrypted` e `webhook_secret_encrypted` do `$fillable` (hardening correto). Os testes `observer registra audit_log` e `observer sanitiza campos sensíveis` ainda usam `ClusterServer::create([...])` com mass assignment desses campos — Eloquent descarta silenciosamente → `NOT NULL constraint failed: cluster_servers.ssh_private_key_encrypted`.
- **Ação necessária**: Ajustar testes para padrão de produção (`factory()->create()` + assign direto + `save()`), sem reabrir mass assignment.
- **Correção**: Sprint F14 — testes usam `factory()->make()` + assign direto de secrets + `save()`; `$fillable` permanece sem secrets.

### QA-F14-002 — HIGH — `RotateSecretTest` desalinhado com factory auto-seed de `WebhookSecretHistory` (N19)

- **Sprint**: F14
- **Severidade**: HIGH (CI blocker — 4 testes)
- **Tipo**: regression / test gap
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: Investigação CI run `27646529336`; commit `4e2d1e9` (sprint N19)
- **Arquivo**: `tests/Feature/ClusterServers/RotateSecretTest.php`, `database/factories/ClusterServerFactory.php`
- **Descrição**: N19 adicionou `afterCreating` na factory que cria `WebhookSecretHistory` v1 automaticamente. Quatro testes ainda assumem cluster sem histórico ou fazem seed manual redundante de v1: `toHaveCount(2)` recebe 3; `RuntimeException` não lançada; `toast type=error` não disparado; `count() === 0` recebe 1.
- **Ação necessária**: Alinhar testes ao novo contrato da factory — remover seed redundante; para cenários “sem histórico”, usar state `withoutWebhookHistory()` na factory ou `ClusterServer::withoutEvents()`.
- **Correção**: Sprint F14 — seed manual redundante removido; `ClusterServerFactory::withoutWebhookHistory()` deleta histórico pós-create; 4 cenários alinhados.

### SEC-F14-001 — MEDIUM — `phpseclib/phpseclib` 3.0.52 vulnerável (GHSA-m557-wrgg-6rp4)

- **Sprint**: F14
- **Severidade**: MEDIUM (CI blocker — job Security)
- **Tipo**: security / dependency
- **Status**: Validado
- **Registrado em**: 2026-06-16
- **Origem**: `composer audit --no-dev --locked` no CI e local
- **Arquivo**: `composer.lock` (`phpseclib/phpseclib` 3.0.52)
- **Descrição**: Advisory SSRF via validação X.509 (AIA). Versões afetadas `<=3.0.53`; projeto locked em 3.0.52. Fix: `>=3.0.54`.
- **Ação necessária**: `composer update phpseclib/phpseclib --with-dependencies`; commit lockfile; re-run `composer audit`.
- **Correção**: Sprint F14 — `phpseclib/phpseclib` 3.0.55; `composer audit --no-dev --locked` exit 0.

> **Validação N32 R2** (2026-06-18, `/qa validar N32` via `/rock`): commits `491f5d9`..`db21720`. **Testes**: 82 passed Docker (Characterization + Jobs + DomainErrorSanitization). **auditor-senior R2** → **PASS** (0 HIGH no delta). **HIGH R1**: 6/7 validados; CQ-N32-003 parked N33 (architectural). **Resultado: APROVADA COM RESSALVAS**.

### CQ-N32-001 — HIGH — Schedule observability sem import em `routes/console.php`

- **Sprint**: N32
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — auditor-senior R1
- **Arquivo**: `routes/console.php:15,20`
- **Descrição**: `JobsObservabilityCheckCommand::class` e `AuditPurgeCommand::class` usados sem `use`; Laravel resolve string errada e o scheduler nunca executa checks de SLA/paridade.
- **Correção** (Sprint N32, PR #117): imports `use App\Console\Commands\JobsObservabilityCheckCommand;` e `use App\Console\Commands\AuditPurgeCommand;` adicionados em `routes/console.php`.
- **Validação** (N32 R2, 2026-06-18): auditor-senior R2 PASS; schedule observability executável; CI run `27768621255` verde.

### CQ-N32-002 — HIGH — Lógica de domínio/persistência dentro de `SshPlatformAdapter`

- **Sprint**: N32
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — auditor-senior R1
- **Arquivo**: `app/Modules/Integration/Adapters/SshPlatformAdapter.php`
- **Descrição**: `syncTenant()`/`applyTenantSync()` executa `Customer::create/update/delete` e `AuditLog::create` no adapter SSH.
- **Correção** (Sprint N32, PR #117): sincronização de réplica movida para `CustomerSyncService`; adapter SSH delega transporte e mapeia DTO apenas.
- **Validação** (N32 R2, 2026-06-18): `CustomerSyncServiceCharacterizationTest` verde; auditor-senior R2 PASS (0 HIGH no delta).

### CQ-N32-003 — HIGH — `PlatformPort` vaza exceções de transporte SSH

- **Sprint**: N32 → N33
- **Severidade**: HIGH
- **Tipo**: product_bug / architectural
- **Status**: Validado
- **parked_since**: N33
- **resolved_by**: Sprint N33 (N33.2)
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — auditor-senior R1
- **Arquivo**: `app/Modules/Integration/Contracts/PlatformPort.php`
- **Descrição**: Interface declara `@throws SshClientException` etc.; consumidores ainda acoplam ao transporte.
- **Correção** (Sprint N33, PR #117, N33.2): exceções de domínio no port (`UpstreamUnavailableException`, `CapabilityBlockedException`, `PortStateConflictException`, `PortIdempotencyConflictException`); adapters SSH/Agent mapeiam transporte; interface sem `@throws` de transporte.
- **Validação** (N33 R1, 2026-06-18): `PlatformPortContractTest` + characterization suite verdes; auditor-senior R1 PASS (0 HIGH/CRITICAL no delta); grep em `Customers`/`Jobs` não importa `SshClientException`.

### CQ-N32-004 — HIGH — `correlation_id` incompleto em remove/cancel/poll

- **Sprint**: N32
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — auditor-senior R1
- **Arquivo**: `app/Modules/Customers/Actions/RemoveCustomerAction.php`, `app/Modules/Jobs/Actions/CancelJobAction.php`, `app/Console/Commands/JobsPollStuckCommand.php`
- **Descrição**: Gate sprint exige `correlation_id` dispatch→webhook→audit; remove/cancel/poll omitem geração/propagação.
- **Correção** (Sprint N32, PR #117): `correlation_id` gerado/propagado em `RemoveCustomerAction`, `CancelJobAction`, `JobsPollStuckCommand` e entradas `AuditLog` de lifecycle.
- **Validação** (N32 R2, 2026-06-18): `CorrelationIdEndToEndTest` verde; auditor-senior R2 PASS.

### CQ-N32-005 — HIGH — `CancelJobAction` grava `previous_state` errado no audit

- **Sprint**: N32
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — auditor-senior R1
- **Arquivo**: `app/Modules/Jobs/Actions/CancelJobAction.php:39-48`
- **Descrição**: `getOriginal('state')` após `update()` retorna `cancelled` em vez do estado anterior.
- **Correção** (Sprint N32, PR #117): `$previousState = $job->state` capturado antes do `update()`; audit persiste estado anterior correto.
- **Validação** (N32 R2, 2026-06-18): `CancelJobActionCharacterizationTest` verde; auditor-senior R2 PASS.

### CQ-N32-006 — HIGH — `dispatchManageAsync` ignora transporte Agent

- **Sprint**: N32
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — auditor-senior R1
- **Arquivo**: `app/Modules/Integration/Services/PlatformPortFactory.php`, `app/Modules/Customers/Actions/LifecycleAsyncAction.php`
- **Descrição**: `dispatchManageAsync()` sempre delega a `SshPlatformAdapter`, mesmo com Agent ativo.
- **Correção** (Sprint N32, PR #117): `LifecycleAsyncAction` delega via `PlatformPortFactory::for()` — mesma regra de transporte SSH/Agent que demais métodos do port.
- **Validação** (N32 R2, 2026-06-18): characterization tests verdes; auditor-senior R2 PASS.

### CQ-N32-007 — HIGH — Artefatos críticos N32 não commitados

- **Sprint**: N32
- **Severidade**: HIGH
- **Tipo**: environment
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N32` — preflight PROC-025 + auditor-senior R1
- **Arquivo**: `scripts/grep-gate-adapters.sh`, `database/migrations/2026_06_18_*`, `TransportObservability.php`, DTOs Integration
- **Descrição**: Implementação existe só no working tree; branch tem apenas commit de docs.
- **Correção** (Sprint N32, PR #117): commit `491f5d9` (`feat(sprint-N32): PlatformPort migration waves + transport observability`) com todos os artefatos de produção; CI run `27768621255` verde.
- **Validação** (N32 R2, 2026-06-18): preflight PROC-025 OK; 82 tests Docker passed; validation_gate_qa APROVADA R2.

---

## Sprint N34 — Saga `POST /v1/onboarding` (ISSUE-038 Fase 4)

> Registrado via `/qa validar N34` R1 em 2026-06-18. Senior review na saga composta. Branch `sprint/N34`; follow-up R2 `5bd7456`.

> **Validação N34 R2** (2026-06-18, `/qa validar N34` R2): commits `22118d1`..`06c97bf` + follow-up `5bd7456`. **Testes**: 582 passed Docker (suite completa local). **auditor-senior R2** → **PASS** (0 HIGH no delta). **HIGH R1**: 3/3 corrigidos in-sprint (`CQ-N34-001`, `CQ-N34-002`, `CQ-N34-003`). **Resultado: APROVADA**.

### CQ-N34-001 — HIGH — Saga incompleta: steps pós-readiness nunca despachados

- **Sprint**: N34
- **Severidade**: HIGH
- **Tipo**: product_bug / architectural
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N34` — auditor-senior R1
- **Arquivo**: `app/Modules/Onboarding/Saga/OnboardingSaga.php`, `app/Modules/Jobs/Services/WebhookHandler.php`
- **Descrição**: `OnboardingSaga` implementava apenas `provision_tenant` + `wait_readiness`. Após readiness OK, `advanceToCreateAdmin()` só persistia `create_admin: pending` — sem dispatch de `LifecycleAsyncAction` (`users:create`), `enable_apps` nem `setBranding`. Webhook não avançava saga além do gate.
- **Correção** (Sprint N34, commit `5bd7456`): saga despacha `users:create` → `enable_apps` → `setBranding`; `WebhookHandler::handleOnboardingTerminalJob` roteia jobs terminais por `correlation_id`; estados `completed`/`partial` ao final.
- **Validação** (N34 R2, 2026-06-18): `OnboardingSagaTest` + `OnboardingSagaTest` feature verdes; auditor-senior R2 PASS.

### CQ-N34-002 — HIGH — Credenciais de admin validadas mas descartadas

- **Sprint**: N34
- **Severidade**: HIGH
- **Tipo**: product_bug / security
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N34` — auditor-senior R1
- **Arquivo**: `app/Http/Controllers/Api/V1/OnboardingV1Controller.php`, `app/Models/Onboarding.php`, `database/migrations/2026_06_18_000003_add_admin_payload_to_onboardings_table.php`
- **Descrição**: `CreateOnboardingRequest` exigia `admin.password`, mas password nunca entrava no DTO nem era persistido. Step `create_admin` impossível após retorno 202.
- **Correção** (Sprint N34, commit `5bd7456`): `OnboardingSpec.adminPassword` no controller; coluna `admin_payload` com cast `encrypted:array` + `hidden`; consumo em `dispatchCreateAdmin`.
- **Validação** (N34 R2, 2026-06-18): `OnboardingModelTest` verifica persistência criptografada; auditor-senior R2 PASS.

### CQ-N34-003 — HIGH — Falha de job não propaga para estado `failed` da saga

- **Sprint**: N34
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-18
- **Origem**: `/qa validar N34` — auditor-senior R1
- **Arquivo**: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Modules/Onboarding/Saga/OnboardingSaga.php`
- **Descrição**: Job `provision` terminal `failed`/`cancelled` atualizava `Customer` mas não `Onboarding`. Poll GET retornava `running` indefinidamente; runbook documentava `state=failed` mas código nunca setava.
- **Correção** (Sprint N34, commit `5bd7456`): `markStepFailed()` seta `OnboardingState::Failed`; webhook cobre provision `failed`/`cancelled` e steps assíncronos subsequentes via `handleTerminalJob`.
- **Validação** (N34 R2, 2026-06-18): `OnboardingReadinessGateTest` + feature tests de step failure verdes; auditor-senior R2 PASS.

---

## Sprint Onda A — N21 + N23 + N29 (branch `sprint/N29-dns-powerdns`)

> **Validação Onda A R1** (2026-06-20, `/qa validar`): scope = delta `main...HEAD` (N19–N29 commits; foco N21 mail, N23 placement, N29 DNS). **Testes**: 644 passed, 7 skipped, 2143 assertions (Docker `app`). **auditor-senior R1** → **FAIL** (6 HIGH). **N23**: 0 HIGH no delta. **Resultado: REPROVADA** — PROC-012 exige corrigir HIGH in-sprint antes de merge da Onda A.

> **Validação Onda A R2** (2026-06-20, `/qa validar`): follow-up correções `/rock`. **Testes**: 654 passed, 7 skipped, 2161 assertions (Docker). **auditor-senior R2** → **FAIL** (2 HIGH novos). **R1**: 6/6 HIGH **Validado** in-code. **Resultado: REPROVADA** — CQ-N21-004, CQ-N21-005 pendentes.

### CQ-N21-001 — HIGH — Mail hook lê payload do AgentCommand, mas enqueue não popula `mail`

- **Sprint**: N21
- **Severidade**: HIGH
- **Tipo**: product_bug / architectural
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R1 — auditor-senior
- **Arquivo**: `app/Modules/Agents/Services/AgentEventHandler.php:182-189`, `app/Modules/Agents/Services/AgentUpstreamGateway.php:44-48`
- **Descrição**: `maybeProvisionMailAfterContainersUp()` resolve mail via `$command->payload['mail']`, mas `AgentUpstreamGateway::enqueue()` só grava `cmd`, `args` e `stdin_json`. Mail fica em `Customer.mail_provision_payload` (gravado por `ProvisionCustomerAction`) e nunca é copiado para o comando. Com agent transport ativo, hook de mail nunca dispara.
- **Correção**: `resolveMailPayload` prioriza `$customer->mail_provision_payload` com fallback ao payload do comando; customer resolvido antes do mail payload; teste atualizado.
- **Validação** (Onda A R2, 2026-06-20): auditor-senior R2 PASS no item; `MailProvisionPipelineTest` verde.

### CQ-N21-002 — HIGH — Senha admin gerada é descartada quando `admin_password` ausente

- **Sprint**: N21
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R1 — auditor-senior
- **Arquivo**: `app/Modules/Agents/Services/AgentEventHandler.php:172-176`
- **Descrição**: Quando `mail.admin_password` está ausente, usa `Str::password(16)` e chama `ProvisionTenantMailAction`, mas a senha não é persistida. Mailbox criado com credencial irrecuperável.
- **Correção**: Senha gerada ou fornecida persistida criptografada em `branding_meta['mail_admin_password_encrypted']` antes da chamada mail-api.
- **Validação** (Onda A R2, 2026-06-20): core fix validado; ressalva CQ-N21-005 em retry duplicado.

### CQ-N21-003 — HIGH — Provisioning de mail sem idempotência; exceção derruba handler de eventos

- **Sprint**: N21
- **Severidade**: HIGH
- **Tipo**: product_bug / resilience
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R1 — auditor-senior
- **Arquivo**: `app/Modules/Mail/Actions/ProvisionTenantMailAction.php:18-24`, `app/Modules/Agents/Services/AgentEventHandler.php:176`
- **Descrição**: Sem flag/idempotency guard (`mail_provisioned`). Reenvio de `containers_up` reexecuta `createDomain`/`createMailbox`. Falha 409/422 do mail-api propaga `MailApiException` sem try/catch no `AgentEventHandler`, quebrando `POST /api/agent/v1/events`.
- **Correção**: Flag `mail_provisioned` + audit guard; HTTP 409 idempotente no MailApiClient; try/catch com AuditLog em AgentEventHandler.
- **Validação** (Onda A R2, 2026-06-20): mail path validado; ressalva DNS fora do catch (CQ-N21-004).

### CQ-N29-001 — HIGH — Lookup DKIM usa `DNS_NS` e nunca encontra registros TXT

- **Sprint**: N29
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R1 — auditor-senior
- **Arquivo**: `app/Modules/Dns/Services/DnsLookupService.php:77-95`
- **Descrição**: `lookupDkim()` consulta `dns_get_record($domain, DNS_NS)` e filtra `host` por `_domainkey`. DKIM vive em TXT de `selector._domainkey.<domain>`. Retorna `[]` sempre em produção; testes passam porque mockam `DnsLookupServiceInterface`.
- **Correção**: `lookupDkim` consulta TXT em hosts do `DomainDnsPlanner` (`default._domainkey` + padrão mail-api).
- **Validação** (Onda A R2, 2026-06-20): auditor-senior R2 PASS.

### CQ-N29-002 — HIGH — `dns.zone.provision` e DKIM publish órfãos (sem integração ao pipeline)

- **Sprint**: N29
- **Severidade**: HIGH
- **Tipo**: architectural / incomplete deliverable
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R1 — auditor-senior
- **Arquivo**: `app/Modules/Dns/Actions/ProvisionDnsZoneAction.php`, `app/Modules/Dns/Actions/PublishDkimRecordAction.php`
- **Descrição**: Actions implementadas e testadas via `app(...)` direto, porém nenhum handler de provision/onboarding/agent/mail as invoca. Zonas PowerDNS e DKIM nunca são criadas no fluxo comercial.
- **Correção**: `AgentEventHandler` encadeia `ProvisionDnsZoneAction` + `PublishDkimRecordAction` após mail provision bem-sucedido (DI no construtor).
- **Validação** (Onda A R2, 2026-06-20): pipeline wired; ressalva resiliência PdnsException (CQ-N21-004).

### CQ-N29-003 — HIGH — Provisionamento DNS não-atômico quebra idempotência em retry

- **Sprint**: N29
- **Severidade**: HIGH
- **Tipo**: architectural / saga consistency
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R1 — auditor-senior
- **Arquivo**: `app/Modules/Dns/Actions/ProvisionDnsZoneAction.php:32-34`, `app/Modules/Dns/Services/PdnsClient.php:21-32`
- **Descrição**: `createZone()` + 6× `upsertRecord()` sem transação/compensação. Falha parcial deixa zona criada mas `dns_zone_provisioned` não marcado. Retry chama `createZone()` → HTTP 409 → `PdnsException`; tenant preso sem flag e sem records completos.
- **Correção**: `PdnsClient::ensureZoneExists` trata 409/getZone; `ProvisionDnsZoneAction` usa ensure; teste de retry após falha parcial.
- **Validação** (Onda A R2, 2026-06-20): auditor-senior R2 PASS; `DnsZoneProvisionTest` retry verde.

> **Validação Onda A R3** (2026-06-20, `/qa validar` via `/rock`): follow-up CQ-N21-004/005. **Testes**: 656 passed, 7 skipped, 2171 assertions (Docker). **auditor-senior R3** → **PASS** (0 HIGH). **Resultado: APROVADA** — Onda A pronta para PR.

### CQ-N21-004 — HIGH — `PdnsException` no pipeline DNS derruba `POST /api/agent/v1/events`

- **Sprint**: N21
- **Severidade**: HIGH
- **Tipo**: product_bug / resilience
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R2 — auditor-senior
- **Arquivo**: `app/Modules/Agents/Services/AgentEventHandler.php:176-230`
- **Descrição**: try/catch captura apenas `MailApiException`. `provisionDnsAfterMail()` propaga `PdnsException` em falhas PowerDNS após mail OK — agent perde ack 202 (mesma classe de bug que CQ-N21-003).
- **Ação necessária**: try/catch dedicado para `PdnsException` + audit `dns_provision_failed`, espelhando `logMailProvisionFailure`.
- **Correção**: try/catch aninhado para `PdnsException` + `logDnsProvisionFailure`; teste PDNS failure após mail OK retorna 202.
- **Validação** (Onda A R3, 2026-06-20): auditor-senior R3 PASS.

### CQ-N21-005 — HIGH — Reenvio de `containers_up` rotaciona senha após mail já provisionado

- **Sprint**: N21
- **Severidade**: HIGH
- **Tipo**: product_bug / idempotency
- **Status**: Validado
- **Registrado em**: 2026-06-20
- **Origem**: `/qa validar` Onda A R2 — auditor-senior
- **Arquivo**: `app/Modules/Agents/Services/AgentEventHandler.php:176-211`
- **Descrição**: `resolveAndPersistMailPassword()` roda antes do guard `mail_provisioned` em `ProvisionTenantMailAction`. Retry de `containers_up` gera nova senha e sobrescreve `mail_admin_password_encrypted` enquanto mailbox no mail-api mantém senha antiga.
- **Ação necessária**: Checar `mail_provisioned` antes de gerar/persistir senha; reutilizar senha criptografada existente em retry.
- **Correção**: `isMailProvisioned()` antes de gerar senha; reutiliza decrypt existente; skip total se provisionado sem senha criptografada; teste retry idempotente.
- **Validação** (Onda A R3, 2026-06-20): auditor-senior R3 PASS.

---
