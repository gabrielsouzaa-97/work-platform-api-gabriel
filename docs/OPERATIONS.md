# Operations log

## 2026-07-05T18:55:00Z — Deploy LAB pós-merge N37 + N39 (ISSUE-047 / ISSUE-049)

- **Control plane LAB:** `api.lab.mework360.com.br` (`.110`) — deploy `main` SHA `8e58fed` (merge PR #135 N39 sobre PR #136 N37); `/up` 200.
- **N37 (ISSUE-047):** viewer Scalar em `/docs/api` (gate `manage-operators`); manifest Vite `docs-api.js`; scopes v1 no create de `/api-keys` com badges na listagem; link sidebar "Documentação API".
- **N39 (ISSUE-049):** FQDN normalizado server-side; OccPanel lista usuários OCC; feedback async user create; `customers/show` poll + tail log; readiness card em `provisioning_finishing`; retrofit M3 `customers/*`; UI remoção cluster com guarda.
- **CI:** verde em ambos PRs (#136 N37, #135 N39) antes do merge.
- **Rollback:** imagem/commit anterior no host `.110` (swap diretório + compose restart).

## 2026-07-05T03:41:54Z — N25.4 canário `canario-n25-labwork` no cluster `labwork` (`.112`)

- **Request:** `POST /api/v1/tenants` (legado, sem `image_mode`) → slug `canario-n25-labwork`, domain `canario-n25-labwork.labwork.mework360.com.br`, cluster `262ab7f9-d284-4671-a05c-fc996a156730`.
- **Job upstream:** `36fcdb4f-b129-4055-9893-42f4e11c42eb` → **success** exit 0 (~105s); webhook `callback_started_delivered=true`, `callback_attempts=1`.
- **Control plane:** customer `provisioning` → **`provisioning_finishing`**; `ProbeCustomerReadinessJob` → **`ready=0`** (não promove a `active`).
- **Gates manuais:**
  - `/login` → **200** ✅
  - `/apps/mework360_memail/` → **404** ❌
  - `occ app:list`: `me360_theme` 1.6.15 ✅; **`mework360_memail` ausente** ❌ (idem tenant referência `suite` no mesmo host).
- **Causa raiz readiness (infra):** create no labwork sincroniza apps genéricos (`mail`, `me360_theme`, etc.) mas **não instala/habilita `mework360_memail`** — gap de suíte no host (ISSUE-046 camada 3, escopo labwork).
- **Causa adicional (API):** `TenantReadinessGateChecker` lê `parsedJson['enabled']` com `=== true`, mas `occ-exec` via shim retorna envelope `parsed_result.enabled` com **versões string** — gate OCC nunca passa mesmo com apps presentes (registrar finding separado).
- **Expectativa:** após ~1200s / 10 tentativas → `status=failed` (`customer_readiness_timeout`). **Confirmado:** customer `failed` (~04:00 UTC).
- **N25.4 gate completo:** **BLOQUEADO** (readiness + 16 settings + ISSUE-031 SSO).
- **Credenciais/secrets:** [REDACTED]

## 2026-07-05T03:40:00Z — Cadastro cluster `labwork` (`.112`) no control plane + bootstrap host

- **Decisão de escopo (usuário):** API provisiona em **labwork** (LAB oficial, `.112`) e **image-pilot** (teste de produção, `.120`); produção real ainda não existe; `.108` (`lab-upstream`) é legado F10.3 co-locado — não é alvo de provisionamento nesta fase.
- **Bootstrap `.112` (pré-requisito):** host estava incompleto — faltavam `nc_image*.sh`, `scripts/worker.sh`, usuário `ncsaas-api`, worker systemd. Sincronizado de `.120`: libs (`nc_image.sh`, `nc_image_create.sh`, `nc_image_upgrade.sh`, `suite_post_deploy_integrate.sh`, etc.), `scripts/`, shim `ncsaas-api-shim`, sudoers + sshd drop-in, `nextcloud-saas-worker` (env apontando para provision `929889b0-…/releases/suite_catalog.json` + `platform-1.0.0-labwork-baseline.yaml`). Worker → **active**.
- **N25.3 (corrigido):** cluster `labwork` UUID `262ab7f9-d284-4671-a05c-fc996a156730`; SSH `ncsaas-api@128.201.61.112`; chave dedicada `api-lab-labwork-2026`; `webhook_secret_history` v1 criada no cadastro (lição ISSUE-046 camada 2); `webhook_allowed_ip` 128.201.61.112; `nextcloud_version` 33.0.5.
- **R6:** `cluster:health-check` → `[labwork] → active`, `[image-pilot] → active`, `[lab-upstream] → active`.
- **Tenants existentes no labwork:** `suite` (`cloud.labwork.mework360.com.br`), `cloud2` (`cloud2.labwork.mework360.com.br`).
- **Credenciais/secrets:** [REDACTED]

## 2026-07-05T03:00:00Z — ISSUE-046 diagnóstico 3 camadas + fixes (config `.108` + webhook history)

- **Camada 1 (create, FIXED):** criado `/opt/work-platform-scripts/deploy/lab-deploy.conf` no `.108` com `LAB_DEPLOY_HOST=` vazio → `deploy_shell` deixa de tentar `apply-lab` remoto contra labwork `.112` e cai no caminho local `custom_apps_sync_tenant`. Create via API validado (`canario-n25c`/`d` → job success exit 0).
- **Camada 2 (webhook 401, FIXED):** cadastro N25.3 criou `cluster_server` sem linha em `webhook_secret_history` (feito por atribuição direta, fora do fluxo `ClusterCreate`). `WebhookSecretValidator` valida contra essa tabela → 401. Criada linha via `WebhookSecretHistory::createWithSecret` (v1) para `d7538710-...`. Callback validado (401→aceito; customer → `provisioning_finishing`).
- **Camada 3 (readiness, ESCOPO):** create local não instala a suíte me360 (`app:list` → 0 apps); gate de readiness (mework360_memail+me360_theme+SSO) nunca passa. `.108` não provisionado p/ suíte local. Decisão de escopo pendente (canário de suíte no `.112` vs `.108`).
- **Cleanup:** tenants `canario-n25c`/`canario-n25d` removidos (host `remove --confirm` + soft-delete control plane); API keys de teste revogadas. Override `lab-deploy.conf` mantido (documentado).
- **Credenciais/secrets:** [REDACTED]

## 2026-07-04T23:31:00Z — Retomada N25: cluster `lab-upstream` (.108) cadastrado; canário bloqueado por ISSUE-046

- **N25.3 — cluster `lab-upstream` (`.108`):** UUID `d7538710-676d-4673-a191-8decc0905596`; SSH `ncsaas-api@128.201.61.108`; tier shared; `webhook_allowed_ip` 128.201.61.108; `nextcloud_version` 31.0.0 (legado). Chave dedicada ed25519 `~/.ssh/api-lab-108-2026` (comment `api-lab-108-2026`), autorizada em `/home/ncsaas-api/.ssh/authorized_keys` no `.108` com o mesmo `ForceCommand` restrito (`ncsaas-api-shim`) usado em `.120`.
- **Webhook secret:** aplicado via `nextcloud-manage config set-webhook-secret --payload-stdin` no `.108` → success, worker `nextcloud-saas-worker` reiniciado.
- **R6 Testar Conexão:** `php artisan cluster:health-check` → `[lab-upstream] → active` (junto com `[image-pilot] → active`).
- **N25.4 — canário `canario-n25` (BLOQUEADO — ISSUE-046):** `POST /api/v1/tenants` (legado, sem `image_mode`) → job `4ba0824d-343c-44fd-bed7-aa1bd0b7de88` aceito (202), dispatch SSH OK, mas `nextcloud-manage create` no host **falhou exit 1**: script `suite-deploy` interno tenta aplicar tema/deploy contra `mecloud360@128.201.61.112` (host **labwork**, ambiente errado) e não encontra `apply-lab.sh`. Job Redis `state=failed`, `callback_failed=true` (4 tentativas — notificação de falha não chegou ao control plane). Ver ISSUE-046 para detalhes e evidência completa.
- **Cleanup pós-tentativa:** customer `canario-n25` soft-deletado (`status=provisioning_failed`); API key de teste `canary-n25` revogada.
- **Credenciais/secrets:** [REDACTED]

## 2026-07-04T21:25:00Z — Validação pós-merge N36: deploy LAB + canário `canario-n36e` (gate PASS)

- **Control plane LAB:** `api.lab.mework360.com.br` (`.110`) — deploy `main` SHA `7a79086` (merge PR #128, sem hotfix); readiness oficial validado pós-deploy.
- **Upstream ISSUE-045:** fix `dispatch.sh` D3.9b em `work-platform-scripts` commit `ba53ecc` (main) deployado no host image-pilot (`.120`).
- **N36.4 — canário E2E `canario-n36e`:** `POST /api/v1/tenants` com `image_mode=true`, `suite_catalog=true` → job `9904497b-ad3c-4390-ba61-c5f433cd00c1` (dispatch 21:19 UTC) **success** ~5m44s; customer `active` automático ~4s depois; `/login` 200; imagem mw4 (`ghcr.io/softwarebeesy/work-nextcloud-image:33.0.5-mw4`); `/status.php` 404 (esperado image-mode, gate N36.5).
- **Critérios R6–R8:** R6 cluster `image-pilot` ativo (cadastro N36.3); R7 webhook 204 + `jobs.summary` populado; R8 readiness PASS sem `mework360_memail` nem `/status.php`.
- **Histórico canários falhos (soft-delete):** `canario-n36`, `canario-n36b`, `canario-n36c` — bloqueados por ISSUE-045 antes do fix upstream. `canario-n36d` teve job success e tenant `active` (via hotfix manual de readiness no LAB, pré-fix ISSUE-045) — não é falho, mas redundante frente ao gate limpo `canario-n36e`; removido em 22:36 UTC (ver hardening abaixo).
- **Hardening `.120` (2026-07-04):** symlink `/opt/releases` → bundle `746ecb81` releases; removidos tenants residuais `canario-n36b`/`canario-n36c`/`canario-n36d`; instâncias remanescentes: `canario-n36e`, `imgpilot`, `teste2`; `NC_IMAGE_BOM` mantido no worker env até canário confirmar resolução só com defaults.
- **Credenciais/secrets:** [REDACTED]

## 2026-07-03T18:00:00Z — Sprint N36: cluster image-pilot + deploy LAB + canário API (ISSUE-043 / ISSUE-045)

- **Control plane LAB:** `api.lab.mework360.com.br` (`.110`) — código `sprint/N36` SHA `80f3063` implantado (swap diretório preservando `.env`/`storage`; imagens app `1daeee78af66` / worker `2f7649b7412b`); migration `customers.image_mode` aplicada; `/up` 200. Rollback: `/opt/mework360-deployer-old-202607031556` + imagens `0b2e4175f754`/`bb7fd318215d`.
- **N36.3 — cluster `image-pilot` (`.120`):** UUID `978d6dd4-33fa-48d6-8347-15da9d4aa672`; SSH `ncsaas-api@128.201.61.120`; tier shared; `webhook_allowed_ip` 128.201.61.120; `nextcloud_version` 33.0.5. Chave dedicada ed25519 `~/.ssh/ncsaas-api-imgpilot` (fingerprint `SHA256:PcgtysoDu17jy+uLHffY6ohorXThVf3Rhrh/rVHJPt4`, comment `api-lab-imgpilot-2026`).
- **Bootstrap host `.120`:** usuário `ncsaas-api` + shim `/usr/local/bin/ncsaas-api-shim` + `nextcloud-manage` (bundle `746ecb81-a7e9-4ed6-b387-89dc502e709f` v12.5.0); sudoers `/etc/sudoers.d/ncsaas-api` (`visudo -c` OK); drop-in sshd `50-ncsaas-api.conf` (`sshd -t` OK). **Desvio:** shell `/bin/bash` (com `/usr/sbin/nologin` o OpenSSH não executava ForceCommand — "This account is currently not available"); acesso segue restrito ao shim.
- **Webhook:** secret aplicado via `config set-webhook-secret --payload-stdin` (`{"secret":"..."}`) → success, `secret_file` `/opt/shared-services/secrets/worker_callback_secret`; registrado encrypted no cluster (`webhook_secret_version` 1).
- **R6 Testar Conexão:** `probeClusterHealth` → exit 0, status active.
- **Worker upstream:** `nextcloud-saas-worker` instalado via `setup-worker.sh` (bundle); corrigidos manualmente `worker_env_manifest.json` ausente + `WORKER_REDIS_PASS`; active/running.
- **ISSUE-042 recorrência no deploy:** overlay compose sem `!override` na VM — patch manual + fix versionado (`d480080`).
- **N36.4 canário API (BLOQUEADO — ISSUE-045):**
  - Tentativa 1 — slug `canario-n36`: job `682f675e-9a1d-4b24-9f88-5db71aa4e7a2` FAILED ~2s exit 1 (`suite_catalog: no nc-suite-snapshot-*.yaml in /opt/releases`). Webhooks `job.started`/`job.finished` OK. Fix env worker: `SUITE_RELEASES_DIR`/`SUITE_CATALOG_JSON` → bundle `746ecb81`; `suite_catalog_snapshot_path` validado.
  - Tentativa 2 — slug `canario-n36b`: job `8f15f56f-a119-4d19-9a31-546090f6fc76` (16:31:22Z→17:01:34Z) FAILED exit 124 (timeout 1800s) em docker pull `nextcloud:33-fpm`/`nginx:alpine` (modelo legado). **Causa raiz:** `dispatch.sh` D3.9b não propaga `--image-mode`/`--suite-catalog` ao Redis — ver ISSUE-045.
  - Tenants `canario-n36` / `canario-n36b`: failed + soft-delete. Tenant manual `teste2` mantido.
- **PR #128:** CI verde ao final (`sprint/N36` → `main`).
- **Credenciais/secrets:** [REDACTED]

## 2026-07-03T15:01:00Z — canário image-mode: tenant `teste2` no host image-pilot (ISSUE-043)

- **Host:** `128.201.61.120` (`image-pilot`, futuro ambiente de produção) — SSH `mecloud360`, dispatcher `manage.sh` v12.5.0 (release 12.5.7; alias `nextcloud-manage` ausente no host)
- **Ação:** `sudo manage.sh teste2 teste2.image-pilot.mework360.com.br create --image-mode --suite-catalog` (medição de tempo solicitada pelo usuário)
- **Resultado:** **success** — `real 7m9.9s` (pull imagem digest-pinned ~2 min; "Nextcloud pronto" +20s; restante = suíte de apps + índices mail + Collabora/signaling). TLS Let's Encrypt emitido ainda durante o create (espera adicional ~0s). NC `33.0.5.1` (meWork), imagem `ghcr.io/softwarebeesy/work-nextcloud-image:33.0.5-mw4`. 5 containers Up (`app`, `nginx`, `cron`, `push`, `harp`).
- **Achados:**
  - `/status.php` → **404 permanente** na plataforma image-mode (idem `imgpilot`; característica da imagem/nginx) — readiness da API não pode depender dele; usar `/login` (200) ou OCS capabilities (200).
  - `[WARN] notify_push:setup failed` (Redis 500 no teste inicial); daemon registrado em seguida, endpoint responde 400 sem erro Redis — reinspecionar.
  - Host usa imagem **mw4** para novos tenants (BOM), embora `imgpilot` tenha sido upgradeado para mw5 (N48.11) — alinhar BOM antes do go-live.
- **Tenant mantido** para inspeção (não removido). Credenciais: `manage.sh teste2 _ credentials` no host (não expostas).
- **Credenciais/secrets:** [REDACTED]

## 2026-06-26T05:03:00Z — remote-dev: autorizar chave SSH Mac no LAB

- **Host:** `lab.mework360.com.br` (`128.201.61.108`, sid=58)
- **Usuário:** `mecloud360`
- **Ação:** adicionar chave `mecloud360-lab@mac` (ed25519) em `~/.ssh/authorized_keys`
- **Método:** SSH via `MECLOUD_SSH_KEY` (workstation beesy) — não console WHMCS
- **Resultado:** `KEY_OK` — permissões `~/.ssh` 700, `authorized_keys` 600, 1 entrada confirmada
- **Credenciais/secrets:** [REDACTED]

## 2026-06-24T20:42:14Z — cloud-ops DIAGNOSTICAR (Proxmox read-only)

- **Objetivo:** validar viabilidade de VM isolada modesta (~16 vCPU / 32 GB RAM / 150 GB) no cluster Proxmox IDC-EVEO (`10.10.10.130:8006`) para ensaio de upgrade Nextcloud, sem alterações no LAB principal (`dev.mework360.com.br`, `128.201.61.6`).
- **Ação autorizada:** `sudo wg-quick up /home/beesy/wireguard/mecloud.conf`
  - **Resultado:** falha — `sudo` exige autenticação interativa (`sudo: A terminal is required to authenticate` / `sudo -n`: interactive authentication required). Túnel WireGuard **não** estabelecido.
- **Conectividade pós-tentativa:** interface `wg` / IP `10.10.10.0/24` ausente; teste TCP `10.10.10.130:8006` → **FAIL** (100% packet loss em ping).
- **Endpoints Proxmox planejados (não consultados — rede inacessível):**
  - `/api2/json/cluster/status`
  - `/api2/json/nodes`
  - `/api2/json/cluster/resources?type=node`
  - `/api2/json/cluster/resources?type=storage`
  - `/api2/json/cluster/resources?type=vm`
  - (candidatos) `/api2/json/nodes/{node}/qemu/{vmid}/config`
- **Escritas Proxmox:** nenhuma (somente leitura planejada).
- **Credenciais/secrets:** [REDACTED] (`~/.config/beesy/cloud.env`, `~/wireguard/mecloud.conf` não lidos em log).
- **Estado final WireGuard:** **DOWN** (não foi possível subir sem sudo interativo).

## 2026-06-24T20:50:00-03:00 — cloud-ops DIAGNOSTICAR (retentativa + script)

- **Objetivo:** validar capacidade Proxmox IDC-EVEO para VM de ensaio NC (~16 vCPU / 32 GB / 150 GB) com conta demo.
- **Ações:**
  - Teste TCP `10.10.10.130:8006` → **FAIL** (WireGuard ausente no host do agente).
  - `sudo wg-quick up ~/wireguard/mecloud.conf` → **FAIL** (`sudo: A terminal is required to authenticate`).
  - Poll 30s aguardando túnel → **FAIL**.
  - WHMCS `GetServers` → servidor Proxmox id=2 `sp1-cloud2-4` hostname `10.10.10.130` **ativo**.
  - SSH read-only no LAB (`dev`, KVM, `128.201.61.6`) → Proxmox `10.10.10.130:8006` **FAIL** (LAB fora da rede do cluster).
  - Criado `scripts/cloud-ops-validate-pve.sh` (consulta API read-only quando WG estiver UP).
- **Escritas Proxmox/WHMCS:** nenhuma.
- **Credenciais/secrets:** [REDACTED].

## 2026-06-24T23:55:00-03:00 — cloud-ops DIAGNOSTICAR (Proxmox read-only — sucesso)

- **Ação:** usuário executou `scripts/cloud-ops-wg-and-validate.sh` (WG via `/etc/wireguard/mecloud.conf` + consulta API).
- **Cluster:** IDC-EVEO, 4 nodes online, quorate.
- **Alvo:** VM ensaio NC ~16 vCPU / 32 GB / 150 GB.
- **Resultado:** `fits_target_vm: true` — folga agregada ~341 vCPU est., ~709 GB RAM, storage Ceph ~4.2 TB livre por pool (`cephfs-lvm` ~38% usado).
- **Templates:** 12 (incl. `ubuntu-2404-template` vmid 9001 em sp1-cloud2-4).
- **LAB no inventário:** vmid 121 `dev` (sp1-cloud2-2), vmid 107 SaaS-01, vmid 109 SaaS-02 — confirmado no cluster; IP público `128.201.61.6` é NAT/front.
- **Artefato:** `docs/CLOUD-OPS-PVE-VALIDATION.json`
- **Credenciais/secrets:** [REDACTED]

## 2026-06-24T23:55:00-03:00 — cloud-ops DIAGNOSTICAR (Proxmox read-only — sucesso)

- **Ação:** usuário executou `scripts/cloud-ops-wg-and-validate.sh` (WG via `/etc/wireguard/mecloud.conf` + consulta API).
- **Cluster:** IDC-EVEO, 4 nodes online, quorate.
- **Alvo:** VM ensaio NC ~16 vCPU / 32 GB / 150 GB.
- **Resultado:** `fits_target_vm: true` — folga agregada ~341 vCPU est., ~709 GB RAM, storage Ceph ~4.2 TB livre por pool (`cephfs-lvm` ~38% usado).
- **Templates:** 12 (incl. `ubuntu-2404-template` vmid 9001 em sp1-cloud2-4).
- **LAB no inventário:** vmid 121 `dev` (sp1-cloud2-2), vmid 107 SaaS-01, vmid 109 SaaS-02 — confirmado no cluster; IP público `128.201.61.6` é NAT/front.
- **Artefato:** `docs/CLOUD-OPS-PVE-VALIDATION.json`
- **Credenciais/secrets:** [REDACTED]
