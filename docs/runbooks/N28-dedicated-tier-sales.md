# N28 — Dedicated Tier Sales & Upsell Runbook

## Overview

Dedicated tenants receive an isolated VPS provisioned via WHMCS (product pid=6, "Máquina Virtual Customizada"). The control plane **never** writes to Proxmox directly for commercial provisioning.

## Sales flow

1. Customer selects dedicated tier (`tier: dedicated`) during provisioning or upsell.
2. API creates a WHMCS order (`AddOrder` → `AcceptOrder` → `ModuleCreate`) using product id **6**.
3. WHMCS module `ProxmoxVeVpsCloud` provisions the VM on cluster **IDC-EVEO**.
4. Customer record is saved with `tier=dedicated` and assigned to a dedicated `cluster_server`.

## WHMCS configuration

| Setting | Value |
|---------|-------|
| Product ID | `6` (`WHMCS_DEDICATED_PRODUCT_ID`) |
| Provisioning server | id=2 `ProxmoxVeVpsCloud` |
| Cluster | `IDC-EVEO` |

## Operational rules

- **Write path:** WHMCS API only (`AddOrder`, `AcceptOrder`, `ModuleCreate`).
- **Read path:** Proxmox API token `PVEAuditor` (read-only) for inventory and VM health.
- **Never:** create, start, stop, or delete VMs from the control plane API.
- **Exception:** Proxmox snapshot before manual intervention (documented in `/cloud-ops`).

## Upsell from shared

1. Confirm customer slug and domain.
2. Create dedicated order via API: `POST /api/customers` with `tier: dedicated`, `auto_place: true`.
3. Monitor WHMCS service activation and Proxmox VM status (read-only).
4. Migrate tenant workload after VPS is active (separate runbook).

## Troubleshooting

| Symptom | Check |
|---------|-------|
| Order stuck | WHMCS admin → Orders → AcceptOrder status |
| VM not created | WHMCS → Module Queue → `ModuleCreate` logs |
| Health unknown | Proxmox read-only: `listClusterResources` / `getVmStatus` |
