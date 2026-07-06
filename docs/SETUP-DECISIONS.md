# Setup Decisions

> Product and architecture decisions for work-platform-api.
> See also `docs/DECISION-BRIEF.md` for infrastructure ADRs.

---

## Decision #ARCH-7 — Plan apps by designation (`plan_apps`), not `max_apps` count

- **Status**: accepted
- **Date**: 2026-07-06
- **Related sprint**: F18
- **Supersedes**: numeric `plans.max_apps` limit (removed)

### Decision

Plans designate **which** apps a tenant may use via the `plan_apps` junction (`PlanAppResolver`), not **how many** via a numeric `max_apps` column. Provision and `POST .../apps/enable` validate that requested apps are a subset of the plan's designated apps (422 validation). Only `max_users` remains as an optional numeric plan limit enforced by `PolicyResolver`.

### Rationale

A count limit does not express product intent (e.g. "basic plan includes mail + calendar" vs "any 2 apps"). Designation via `plan_apps` aligns with catalog sync (N42) and provision inheritance.

### Consequences

- `plans.max_apps` dropped from DB and API contract.
- `PolicyResolver::assertCanEnableApps` removed.
- `CustomerLifecycleController::enableApps` uses `PlanAppResolver` before dispatch.
- FINDINGS CQ-N43-002 descartado.

---
