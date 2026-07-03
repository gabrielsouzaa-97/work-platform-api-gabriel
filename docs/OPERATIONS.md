# Operations log

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
