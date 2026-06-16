# Provision Guardrails V2 — análise de falhas e desenho anti-erro

> **Origem:** execução real do `LAB-PROVISION-PLAN.md` em 2026-06-12 (Fases 0–5) + auditoria consolidada (`work-platform-scripts/docs/LAB-AUDIT-2026-06-12.md`).
> **Tese:** nenhuma das 21 falhas observadas foi aleatória — todas são lacunas de projeto previsíveis. Este documento mapeia **cada falha → guardrail** e especifica o pipeline de provisionamento V2 que não permite repetir nenhuma delas.
> **Sprint de implementação:** N31 (ver `PLATFORM-V2-PLAN.md` §23-B).

---

## 1. Catálogo completo de falhas (execução 2026-06-12)

| # | Fase | Falha observada | Causa raiz | Classe |
|---|------|-----------------|------------|--------|
| E-01 | 0 | WHMCS 403 `IP Banned` / `Invalid IP` (egress mudou 2×) | Egress varia com VPN on/off; whitelist estática | **Contrato de ambiente** |
| E-02 | 0 | GHCR 403/404 — token sem `read:packages` | Credencial não validada antes do início | Contrato de ambiente |
| E-03 | 1 | `AddOrder` HTTP 500 → pedido órfão (orderid 46) precisou cancel+delete | Formato `configoptions[n]` inválido descoberto em produção | **Operação não tipada** |
| E-04 | 1 | `ModuleCreate` → `VM Model "Node" is not empty` (2×, falso negativo) | Módulo provisiona assíncrono; erro não confiável | Operação não tipada |
| E-05 | 1 | VM inacessível por SSH → terminate + reprovisão completa (sid 48→49) | Chave SSH gravada no custom field **depois** do `ModuleCreate`; cloud-init só aplica no 1º boot | **Ordem de passos não forçada** |
| E-06 | 1 | Falso negativo "VM não existe no Proxmox" | SSL verify do Python no Windows falhou silenciosamente | Tooling não verificado |
| E-07 | 3 | `git clone` falhou no host (repo privado, sem credencial) | Host sem deploy key; mitigação improvisada (bundle) | Contrato de ambiente |
| E-08 | 3 | Scripts `.sh` com CRLF → `syntax error` no bash | Bundle gerado de estação Windows sem normalização | **Integridade de transferência** |
| E-09 | 3 | `apt install software-properties-common` falhou (pacote Ubuntu-only) | Port Debian (N30) incompleto — lista de pacotes não testada por distro | Drift runbook↔script |
| E-10 | 3 | Pulls Docker Hub presos em retry ~1h (CDN timeout) → Etapa 4/7 **pendurada sem falhar** | Sem timeout/fail-loud; sem mirror configurado de início | **Execução sem watchdog** |
| E-11 | 3 | `daemon.json` com CRLF → dockerd **down**, restart counter esgotado | Config aplicada sem validação prévia; written-from-Windows | Integridade de transferência + **config sem validação** |
| E-12 | 3 | `deploy-server.sh` morto e ninguém retomou (parado ~40min) | Sem supervisor, sem checkpoint/resume, sem alerta de stall | Execução sem watchdog |
| E-13 | 4 | Roundcube `Internal Error` — DB `roundcube` nunca criado no shared-db | Passo inexistente nos scripts (arquitetura RC shared é nova; legado criava DB no tenant) | **Passo ausente p/ arquitetura nova** |
| E-14 | 4 | `me360_nc_origin` → `cloud.lab` (404, tenant não existe) | Convenção legada hardcoded; sem validação da URL alvo | Passo ausente / config sem validação |
| E-15 | 4/5 | Healthcheck "healthy" com app quebrado (HTTP 200 = Internal Error) | Gate raso: testa transporte, não função | **Gate raso / falso verde** |
| E-16 | 5 | Job tenant morreu: `HARP_SHARED_KEY: unbound variable` → tenant meio-criado | Sem preflight de env contract; job não-atômico, sem estado `failed` | Contrato de ambiente + **job não-atômico** |
| E-17 | 5 | `notify_push` sidecar em crash loop (60 restarts, horas, despercebido) | Sidecar sobe sem dependência do app instalado; sem alerta restart-loop | Execução sem watchdog |
| E-18 | 3 | Loki crash loop (104 restarts): config incompatível com versão da imagem `:latest` | Imagem não pinada + config não validada contra a versão real | **Pinning ausente** + config sem validação |
| E-19 | 3 | UFW/fail2ban nunca instalados (runbook §5.1 exige) | Runbook é prosa; script não implementa; ninguém compara | **Drift runbook↔script** |
| E-20 | 6 | Grafana sem rota, alerta Redis falso-positivo (+Inf), sem Alertmanager | Observabilidade montada sem smoke próprio | Gate raso |
| E-21 | * | Operações manuais SSH↔PowerShell com erros de quoting recorrentes | Orquestração ad-hoc de estação Windows | Tooling não verificado |

**Síntese: 8 classes de causa raiz.** Toda falha futura cai numa delas. O pipeline V2 ataca as classes, não os sintomas.

---

## 2. Princípios do pipeline V2 (anti-erro por construção)

1. **Nada executa sem preflight completo.** Todas as pré-condições (credenciais, egress, DNS, capacidade, env vars, tags de imagem resolvíveis) são validadas ANTES do primeiro write. Falha de preflight = aborta com relatório, zero efeitos colaterais.
2. **Toda operação é tipada e idempotente.** Cada passo declara: pré-condições, ação, pós-condição verificável, e é seguro re-executar. Erros conhecidos de APIs de terceiros (E-03/E-04) ficam encapsulados no client tipado com retry/poll corretos.
3. **Ordem forçada por máquina de estados.** O orquestrador não permite pular passos nem inverter ordem (E-05 vira impossível: o estado `vm.ordered` exige `ssh_key.staged` antes).
4. **Nenhuma config é aplicada sem validação.** Padrão write-validate-commit: escreve em temp → valida com o validador da própria ferramenta (`dockerd --validate`, `loki -verify-config`, `sshd -t`, `nginx -t`, `promtool check`, parse JSON/YAML) → move atômico → reload. Validação falhou = config antiga intacta.
5. **Integridade de transferência garantida.** Artefatos viajam como tarball com SHA-256 conferido no destino + normalização LF forçada (`.gitattributes` no repo + strip no empacotamento). CRLF torna-se estruturalmente impossível.
6. **Timeout e fail-loud em tudo.** Nenhum passo pode "pendurar": todo comando tem timeout explícito; estouro = falha do passo com log, nunca espera infinita (E-10/E-12).
7. **Gates semânticos, não de transporte.** Pós-condição de cada fase testa a FUNÇÃO: login page do RC contém form de login E query no DB responde; tenant NC responde OCS autenticado; R1–R8 rodam automaticamente no fim — HTTP 200 sozinho nunca é "verde".
8. **Jobs atômicos com estado explícito.** Worker valida env contract no início (`set -u` + manifest de vars obrigatórias); falha em qualquer ponto = estado `failed` registrado + recursos parciais marcados (nunca tenant "meio-vivo" silencioso).
9. **Watchdog contínuo.** Monitor de restart-loop (container com RestartCount crescendo > 3 em 5 min = alerta + para o pipeline); supervisor do orquestrador com checkpoint/resume — qualquer interrupção retoma do último estado bom.
10. **BOM é lei.** Toda imagem vem do BOM com tag/digest pinado; lint do pipeline rejeita `:latest`/tags flutuantes antes de qualquer `compose up` (E-18).
11. **Runbook é código.** O checklist do runbook vira manifest declarativo lido pelo orquestrador; "passo no runbook sem implementação" é erro de lint, não surpresa em produção (E-19).
12. **Ledger automático.** Cada passo escreve entrada estruturada (timestamp, ação, resultado, evidência) no ledger da execução — o OPERATIONS.md se escreve sozinho.

---

## 3. Arquitetura do orquestrador (`provision/`)

```
work-platform-scripts/provision/
├── provision.sh            # entrypoint: provision.sh <plan.yaml> [--resume <run-id>] [--dry-run]
├── lib/
│   ├── state.sh            # máquina de estados + checkpoint JSON (run ledger)
│   ├── preflight.sh        # validações globais (credenciais, egress, DNS, BOM, env contract)
│   ├── transfer.sh         # empacota tarball LF-only + sha256, envia, confere no host
│   ├── config-apply.sh     # write-validate-commit p/ cada tipo de config (registry de validadores)
│   ├── gates.sh            # gates semânticos por fase (incl. R1–R8 automatizados)
│   └── watchdog.sh         # timeout wrapper + monitor restart-loop
├── plans/
│   └── lab-baseline.yaml   # manifest declarativo: estágios, pré/pós-condições, BOM ref
└── clients/
    ├── whmcs.sh            # operações tipadas: order_vm (com chave SSH ANTES do ModuleCreate), poll_active
    ├── pdns.sh             # zone_upsert idempotente
    └── ghcr.sh             # login com GHCR_PULL_*, resolve digest da tag (manifest check sem pull)
```

### Estágios do plano `lab-baseline.yaml`

| Estágio | Pré-condição (gate de entrada) | Pós-condição (gate de saída) |
|---------|-------------------------------|------------------------------|
| S0 preflight | — | TODAS as credenciais/vars/tags/egress OK (relatório) |
| S1 vm | S0; cliente existe | SSH por chave responde; OS = Debian 13; **chave foi staged antes do create** (forçado pela ordem interna do client) |
| S2 dns | S1 (IP conhecido) | `dig` de todos os registros = IP da VM |
| S3 host-base | S2; tarball íntegro no host | deploy 7/7 + UFW ativo (22/80/443+3478doc) + fail2ban ativo + docker validado + **zero containers em restart-loop** |
| S4 shared | S3 | Todos os shared healthy com healthchecks semânticos; Loki config validada; Grafana com rota ou túnel documentado |
| S5 roundcube | S4; **DB roundcube criado + schema aplicado** | Login page real (form presente) + DB query OK + 21 plugins + origin validado por HTTP 200/302 no alvo |
| S6 tenant | S5; env contract worker completo | Job atômico OK; notify_push instalado ANTES do sidecar; R1–R8 **todos PASS automaticamente** |
| S7 fechamento | S6 | Ledger completo; BOM snapshot gravado; pendências humanas listadas (PTR, porta 25) |

### Modos de execução

- `--dry-run`: roda só preflights e imprime o plano (zero writes) — o "antes" que o usuário pediu.
- `--resume <run-id>`: retoma do último estágio com pós-condição verde (re-valida o gate antes de seguir).
- Execução remota: orquestrador roda **no host** (não da estação Windows) — elimina classe E-21; a estação só dispara `provision.sh` via SSH e acompanha o ledger.

---

## 4. Mapa falha → guardrail (rastreabilidade)

| Falha | Guardrail que a torna impossível |
|-------|----------------------------------|
| E-01/E-02/E-07 | S0 preflight: credencial + egress + deploy key testados antes de qualquer write |
| E-03/E-04 | `clients/whmcs.sh` tipado: serialize validado, erro do módulo tratado como assíncrono com poll |
| E-05 | Máquina de estados: `order_vm` interno faz chave→create; impossível inverter |
| E-06/E-21 | Orquestrador roda no host Linux; tooling com verificação explícita de TLS |
| E-08/E-11 | `transfer.sh` LF-only + sha256; `config-apply.sh` valida antes de aplicar |
| E-09/E-19 | Runbook = manifest declarativo; lint detecta passo sem implementação; CI N30 testa matriz de distro |
| E-10/E-12 | `watchdog.sh`: timeout em todo passo + supervisor com resume; mirror de registry desde S3 |
| E-13/E-14 | S5 tem passo explícito de DB + gate que valida origin por HTTP real |
| E-15/E-20 | Gates semânticos em todos os estágios; observabilidade tem smoke próprio (S4) |
| E-16 | Worker: manifest de env vars + `set -u` + estado `failed` explícito |
| E-17 | Dependência declarada (app antes do sidecar) + monitor restart-loop para o pipeline |
| E-18 | BOM lint: zero `:latest`; config validada contra a versão pinada |

---

## 5. O que NÃO entra nesta versão (gaps aceitos, com dono)

| Gap | Dono |
|-----|------|
| Stack de e-mail no LAB (IMAP/SMTP) | Sprints N21/N29 (Stalwart + DNS/DKIM) |
| Pinning completo do BOM com digests de TODAS as imagens | Sprint N25 (baseline release) |
| Substituição de SSH por Farm Agent (operações tipadas via HTTPS outbound) | Fase Farm Agent do plano V2 (N17–N20) — este pipeline é o degrau intermediário |
| PTR/rDNS, porta 25 | Humano/EVEO (registrar no ledger como pendência) |

---

## 6. Remediation scope map (E-NN → SAFE / HOST_INVASIVE / DESTRUCTIVE)

> **Policy:** `safe_only` — only **SAFE** entries are auto-applied (once per stage). **HOST_INVASIVE** and **DESTRUCTIVE** stop the run, register `blocked_needs_human`, and require operator action. Engine: `work-platform-scripts/provision/lib/remediation.sh`; env: `PROVISION_AUTO_REMEDIATE=safe|off`, `PROVISION_STRICT=1`.

| ID | Scope | Auto-fix | Operator action |
|----|-------|----------|-----------------|
| E-01 | HOST_INVASIVE | No | Connect VPN; align egress with `WHMCS_ALLOWED_EGRESS_IPS` |
| E-02 | HOST_INVASIVE | No | Refresh `GHCR_PULL_TOKEN` (`read:packages`) |
| E-03 | SAFE | Yes (`_rem_retry_stage`) | Typed WHMCS client retries order |
| E-04 | SAFE | Yes (`_rem_retry_stage`) | Poll `ModuleCreate`; treat transient errors as async |
| E-05 | SAFE | Yes (`_rem_wait_ssh`) | Wait for SSH post-boot |
| E-06 | HOST_INVASIVE | No | Run orchestrator on Linux; fix TLS tooling |
| E-07 | SAFE | Yes (`_rem_retry_stage`) | Re-run bundle transfer (no `git clone` on host) |
| E-08 | SAFE | Yes (`_rem_strip_crlf_bundle`) | Strip CRLF from bundle `.sh/.yml/.yaml/.json` |
| E-09 | HOST_INVASIVE | No | Install distro-correct packages (`apt`) on host |
| E-10 | SAFE | Yes (`_rem_use_registry_mirror`) | Set `PROVISION_REGISTRY_MIRROR` / docker mirror |
| E-11 | HOST_INVASIVE | No | Fix `/etc/docker/daemon.json`; restart **dockerd** manually |
| E-12 | SAFE | Yes (`_rem_retry_stage`) | Retry after watchdog timeout |
| E-13 | SAFE | Yes (`_rem_restart_container`) | Restart `shared-db`; re-run shared setup |
| E-14 | HOST_INVASIVE | No | Set `me360_nc_origin` to real tenant URL |
| E-15 | HOST_INVASIVE | No | Fix semantic gates (not transport-only HTTP 200) |
| E-16 | HOST_INVASIVE | No | Complete worker env contract before tenant job |
| E-17 | HOST_INVASIVE | No | Install `notify_push` (`apt`/`occ`) before sidecar |
| E-18 | SAFE (S4) | Yes (`_rem_fix_loki_bundle`) | Patch bundle `loki-config.yaml`; restart **loki** container |
| E-19 | HOST_INVASIVE | No | Install/enable UFW + fail2ban (host-base) |
| E-20 | HOST_INVASIVE | No | Fix Grafana routes / Alertmanager rules |
| E-21 | HOST_INVASIVE | No | Use `--remote` host-phase; avoid ad-hoc Windows SSH |
| E-BOM | HOST_INVASIVE | No | Pin images in BOM; preflight `bom_tags` blocks `:latest` |

**DESTRUCTIVE** (VM terminate, volume wipe, DNS delete) is never auto-applied; any future entry with that scope follows the same `blocked_needs_human` path as HOST_INVASIVE.

**Registration:** per-run ledger (`adaptation` events) + `provision/.provision-runs/adaptations.jsonl` + `docs/PROVISION-ADAPTATIONS.md`. S7 closure includes `adaptations_summary` for promotion to permanent fixes.
