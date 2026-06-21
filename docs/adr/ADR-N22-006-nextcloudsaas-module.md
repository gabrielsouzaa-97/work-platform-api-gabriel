# ADR-N22-006 — Reuse nextcloudsaas WHMCS module

**Status:** Accepted  
**Date:** 2026-06-21  
**Sprint:** N22

## Context

Sprint N22 wires commercial signup (WHMCS + Vindi) to the work-platform-api control plane. WHMCS already ships a provisioning module (`nextcloudsaas` v3.1.4, fork [SoftwareBeesy/work-nc-whmcs](https://github.com/SoftwareBeesy/work-nc-whmcs)) bound to products pid 7 and 8 on `store.mecloud360.com.br`.

## Decision

Reuse the existing `nextcloudsaas` WHMCS module and products (pid 7/8). The control plane orchestrates tenant lifecycle via:

1. **WHMCS API** (`AddOrder`, `AcceptOrder`) from `WhmcsClient` for commercial writes.
2. **Dedicated webhook** (`POST /api/webhooks/whmcs`) with `X-Whmcs-Signature` HMAC validation — separate from the jobs/hook cluster HMAC pipeline.
3. **Internal provision path** (`WhmcsProvisionService` → `ProvisionCustomerAction`) on `InvoicePaid` — no direct module hooks into SSH from WHMCS PHP.

Do **not** build a custom WHMCS provisioning module for N22.

## Consequences

- WHMCS remains the billing source of truth; suspend/resume map `ModuleSuspend` / `ModuleUnsuspend` / `TrialExpired` to `TenantSuspendAction` / `TenantResumeAction`.
- Welcome/admin email is **not** sent from the WHMCS webhook handler. N22.1 gate: provision only after `InvoicePaid`; readiness and welcome mail stay in `ProbeCustomerReadinessJob` (existing F8 pipeline).
- Future dedicated tier (N28) reuses `WhmcsClient` with different product mapping; Nextcloud shared tier stays on pid 7/8.

## Alternatives considered

| Option | Rejected because |
|--------|------------------|
| Custom WHMCS module calling SSH directly | Duplicates control-plane guardrails (idempotency, audit, placement, readiness gates) |
| Vindi webhook → API without WHMCS | Violates `/cloud-ops` rule: commercial writes always via WHMCS API |
