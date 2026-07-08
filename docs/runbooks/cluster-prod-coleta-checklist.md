# Checklist — Coleta de dados para cluster de produção

> **Objetivo:** reunir tudo que o **work-platform (deployer)** e a **onboarding-api** precisam para provisionar tenants em um cluster Nextcloud real (sem mock SSH).
>
> **Use no servidor:** imprima ou abra este arquivo enquanto estiver no SSH do cluster e do control plane.
>
> **Segurança:** anote segredos em gerenciador de senhas ou vault — **nunca** commite chaves, tokens ou webhook secrets no Git.

---

## Antes de sair do desk

| # | Item | Anotar aqui |
|---|------|-------------|
| [ ] | Acesso SSH ao **servidor do cluster** (Nextcloud / nextcloud-saas-manager) | usuário: ________ |
| [ ] | Acesso SSH ou painel ao **deployer de produção** (control plane) | URL: ________ |
| [ ] | Acesso admin ao painel deployer (`/settings` → Cluster Servers) | login OK? ☐ |
| [ ] | Permissão para gerar/copiar chaves e webhook secret (infra/DevOps) | contato: ________ |
| [ ] | Saber qual **ambiente** é prod vs lab (não misturar UUIDs) | env: ________ |

---

## Parte A — No servidor do cluster (upstream Nextcloud)

Conecte no host que roda `nextcloud-manage` / `manage.sh`.

### A.1 Identificação do host

| # | O quê coletar | Comando / onde olhar | Valor anotado |
|---|---------------|----------------------|---------------|
| [ ] | **IP ou hostname público** (SSH) | `curl -4 ifconfig.me` ou IP fixo da infra | |
| [ ] | **Porta SSH** | `grep -E '^Port' /etc/ssh/sshd_config` (default 22) | |
| [ ] | **Nome amigável do cluster** | convenção infra (ex. `prod-farm-01`, `idc-eveo-01`) | |

### A.2 Canal A — Comandos (`ncsaas-api`)

Usado para `nextcloud-manage` (provision, jobs, OCC).

| # | O quê coletar | Comando / caminho | Valor anotado |
|---|---------------|-------------------|---------------|
| [ ] | Usuário SSH | doc: `ncsaas-api` | |
| [ ] | Config sshd Canal A | `cat /etc/ssh/sshd_config.d/50-ncsaas-api.conf` | existe? ☐ |
| [ ] | Shim ativo | `ls -la /usr/local/bin/ncsaas-api-shim` | |
| [ ] | Script manage | `ls -la /usr/local/bin/nextcloud-manage` | |
| [ ] | **Chave privada Ed25519** (par da pública em `authorized_keys`) | gerar nova **ou** exportar existente do deployer antigo | arquivo: ________ |
| [ ] | Chave pública correspondente | `cat /home/ncsaas-api/.ssh/authorized_keys` | fingerprint: ________ |

**Teste rápido (do seu laptop ou do deployer):**
```bash
ssh -i /caminho/chave-ncsaas-api -p PORTA ncsaas-api@HOST "nextcloud-manage list --json"
```
Esperado: JSON com `schema_version` e `instances` (exit 0).

| # | Teste | OK? |
|---|-------|-----|
| [ ] | SSH Canal A responde | ☐ |
| [ ] | `list --json` retorna JSON válido | ☐ |

### A.3 Canal B — SFTP branding (`ncsaas-sftp`)

Obrigatório se o fluxo envia logo/background grande (multipart > 256 KB).

| # | O quê coletar | Caminho | Valor anotado |
|---|---------------|---------|---------------|
| [ ] | Usuário SFTP | doc: `ncsaas-sftp` | |
| [ ] | Config sshd Canal B | `/etc/ssh/sshd_config.d/51-ncsaas-api-sftp.conf` | existe? ☐ |
| [ ] | Chroot inbox | `/opt/nextcloud-customers/inbox` | permissões OK? ☐ |
| [ ] | **Chave privada SFTP** (separada da Canal A) | vault / arquivo seguro | arquivo: ________ |

### A.4 Webhook (cluster → deployer)

O cluster chama o deployer quando jobs terminam.

| # | O quê coletar | Notas | Valor anotado |
|---|---------------|-------|---------------|
| [ ] | **Webhook secret** (plain text, uma vez) | gerar: `openssl rand -hex 32` ou pegar o já configurado no upstream | |
| [ ] | URL de callback que o cluster usa | template: `{DEPLOYER_URL}/api/jobs/hook?cluster={UUID}` | |
| [ ] | **IP público de saída** do cluster (se deployer filtra origem) | `curl -4 ifconfig.me` **do cluster** | |
| [ ] | Versão do script / schema | output de `nextcloud-manage list --json` → `schema_version` | |

### A.5 Rede e firewall (confirmar com infra)

| # | Regra | Direção | Porta / destino | Liberado? |
|---|-------|---------|-----------------|-----------|
| [ ] | Deployer → cluster SSH | inbound no cluster | TCP `PORTA_SSH` | ☐ |
| [ ] | Cluster → deployer webhook | outbound do cluster | HTTPS → `APP_URL` do deployer | ☐ |
| [ ] | Clientes → domínio tenant | DNS público | `{slug}.<dominio-prod>` | ☐ |

### A.6 Apps e domínio (referência)

| # | Item | Valor anotado |
|---|------|---------------|
| [ ] | Apps habilitados no catálogo v1 (ex. `mail`, `spreed`) | |
| [ ] | Zona DNS de produção para tenants | ex. `{slug}.mework360.com.br` |
| [ ] | Versão Nextcloud / manage.sh | |

---

## Parte B — No deployer (control plane / work-platform)

Painel: **Configurações → Cluster Servers** (ou API admin).

### B.1 Registrar ou localizar o cluster

| # | Campo no painel / DB | Valor anotado |
|---|----------------------|---------------|
| [ ] | **UUID** (`cluster_server_id`) | |
| [ ] | Nome | |
| [ ] | SSH Host | |
| [ ] | SSH Port | |
| [ ] | SSH User (`ncsaas-api`) | |
| [ ] | SSH Private Key (colar PEM no formulário) | ☐ salvo criptografado no DB |
| [ ] | SFTP User (`ncsaas-sftp`) | |
| [ ] | SFTP Private Key | ☐ |
| [ ] | Webhook Secret | ☐ |
| [ ] | Webhook Allowed IP (opcional) | IP saída do cluster |
| [ ] | Status | `active` |
| [ ] | Tier (se aplicável) | shared / dedicated |

### B.2 Variáveis `.env` do deployer

| # | Variável | Valor prod | Local usa mock? |
|---|----------|------------|-----------------|
| [ ] | `SSH_DRIVER` | `phpseclib3` | local: `fake` |
| [ ] | `APP_URL` | URL pública HTTPS acessível **pelo cluster** | |
| [ ] | `APP_KEY` | já configurado | |

### B.3 API key para onboarding-api

| # | Item | Valor anotado |
|---|------|---------------|
| [ ] | Token (`sk_...`) criado no painel | |
| [ ] | Escopos mínimos | `onboarding:run`, `customers:write` (se aplicável) |
| [ ] | `allowed_tenant_slugs` | vazio = todos, ou lista de slugs de teste | |

### B.4 Catálogo de apps

| # | Comando (no container app) | OK? |
|---|----------------------------|-----|
| [ ] | `php artisan app-catalog:sync` | ☐ |
| [ ] | Apps `mail`, `spreed` (ou os usados) aparecem no catálogo | ☐ |

---

## Parte C — Na onboarding-api (`.env`)

Preencher após ter UUID do cluster e API key do deployer.

| # | Variável | Exemplo prod | Valor anotado |
|---|----------|--------------|---------------|
| [ ] | `ME360_USE_V1_API` | `true` | |
| [ ] | `ME360_USE_V1_ONBOARDING_SAGA` | `true` | |
| [ ] | `ME360_WORK_PLATFORM_V1_BASE_URL` | `https://<deployer>/api/v1` | |
| [ ] | `ME360_DEPLOYER_TOKEN` | `sk_...` | |
| [ ] | `ME360_DEPLOYER_CLUSTER_SERVER_ID` | UUID da Parte B | |
| [ ] | `ME360_DEPLOYER_CUSTOMER_DOMAIN_TEMPLATE` | `{slug}.mework360.com.br` | |
| [ ] | `ME360_DEPLOYER_CUSTOMER_APPS` | `mail,spreed` | |

---

## Parte D — Validação pós-coleta (antes do E2E comercial)

Execute na ordem.

| # | Teste | Como | OK? |
|---|-------|------|-----|
| [ ] | Health deployer | `curl -sf https://<deployer>/up` | ☐ |
| [ ] | Ping SSH cluster | painel Cluster Servers ou `ssh -i ... ncsaas-api@host "nextcloud-manage list --json"` | ☐ |
| [ ] | POST onboarding dry-run | slug de teste via API `/api/v1/onboarding` | ☐ 202 |
| [ ] | Webhook recebido | `audit_logs` ou job `state=success` | ☐ |
| [ ] | Customer criado | painel `/customers` | ☐ |
| [ ] | Readiness / saga completa | GET `/api/v1/onboarding/{id}` → `completed` | ☐ |

---

## Armadilhas comuns (já vistas no LAB)

| Sintoma | Causa provável | O que conferir |
|---------|----------------|----------------|
| HTTP 422 `cluster_server_id inválido` | UUID do `.env` ≠ UUID no deployer | Parte B UUID vs Parte C |
| HTTP 422 `Unknown suite catalog app_id` | Apps legados (`calendar`, `contacts`) | `app-catalog:sync` + apps v1 |
| HTTP 503 `cluster_unreachable` | SSH fake local ou firewall | `SSH_DRIVER`, firewall Parte A.5 |
| Provision `queued` eterno | `provision_started_at` preso na onboarding-api | reset no `onboarding_flow` |
| Onboarding 202 mas sem customer | idempotency replay de saga órfã | limpar onboarding/idempotency keys |

---

## Onde guardar o que coletou

| Dado sensível | Onde guardar | Nunca |
|---------------|--------------|-------|
| Chaves SSH/SFTP | Vault / 1Password / secrets do deployer | Git, Discord, e-mail |
| Webhook secret | Campo criptografado `cluster_servers` | `.env.example` |
| API key `sk_...` | `.env` onboarding-api + painel | Repositório |
| UUID cluster | `.env` + doc interna (sem secret) | Misturar lab/prod |

---

## Referências no repo

- [`docs/SSH API Reference — Nextcloud SaaS.md`](../SSH%20API%20Reference%20—%20Nextcloud%20SaaS.md) — Canais A/B, paths, comandos
- [`docs/LAB-PROVISION-PLAN.md`](../LAB-PROVISION-PLAN.md) — §5.3 registro no control plane
- [`docs/runbooks/onboarding-saga.md`](onboarding-saga.md) — fluxo da saga v1
- [`me360-fluxo-onboarding-api/.env.example`](../../me360-fluxo-onboarding-api/.env.example) — variáveis ME360_DEPLOYER_*

---

**Data da coleta:** _______________  
**Operador:** _______________  
**Cluster / host visitado:** _______________  
**Deployer alvo:** _______________
