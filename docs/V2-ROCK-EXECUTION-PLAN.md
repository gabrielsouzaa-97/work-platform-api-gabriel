# Plano de Execução `/rock` — Platform V2

> Plano de **orquestração** das sprints V2 pendentes, consumido pelo `/rock`.
> Coordenação centralizada (este plano) + execução no repo executor de cada sprint.
> Fonte das sprints: [`PLATFORM-V2-PLAN.md`](./PLATFORM-V2-PLAN.md). Namespace: ADR #ARCH-8 — chave `(repo, ID-local)`.
> Criado: 2026-06-19 · Relacionado: ISSUE-042, #ARCH-7, #ARCH-8.

> **Local deste arquivo (temporário):** vive em `work-platform-api/docs/` porque é a âncora atual do plano V2.
> **Migração prevista:** mover para o meta-repo `work-platform` quando a task **N25.1** criá-lo (desacopla a coordenação do control plane — camada Programa).

---

## 0. Modelo de execução (obrigatório)

- **Somente `composer-2.5`** — **proibido** `composer-2.5-fast`, alias `fast`, ou qualquer variante `-fast` (F132.7, Decision #225).
- Custom agents (`test-writer`, `implementer`, `integrator`, `verifier`, `rock`) já declaram `model: composer-2.5` no YAML — **mesmo assim**, todo spawn via `Task` deve passar **`model: composer-2.5` explicitamente** (GAP-10: Cursor pode ignorar o YAML e cair em Fast).
- Se a UI mostrar "Composer 2.5 Fast" → **interromper imediatamente** e relançar com `model: composer-2.5`.

---

## 1. Princípio de operação

| Camada | Onde acontece |
|--------|---------------|
| **Coordenação / tracking** | Aqui (este plano) — sequência, prontidão, status agregado |
| **Execução da sprint** | **No repo executor** — perfil, hooks e sprint-gate daquele repo (`/rock` troca de contexto via `move_agent_to_root`) |

Por que não executar "tudo a partir daqui": os gates do framework (sprint-gate, commit-guard, validation-stamp) rodam **por repo**. Executar uma sprint do `scripts` a partir do `work-platform-api` burlaria os gates do `scripts`. A coordenação é central; a execução é local ao repo.

---

## 2. Gate de avanço (Definition of Done por sprint)

O `/rock` **só avança para a próxima sprint** quando a atual cumprir, **no repo executor**:

1. **Executada** — padrão AgentCoder (Decision #65): `test-writer` (RED) → validar falha → `implementer` (GREEN+REFACTOR) → validar; `integrator` a cada 3 tasks.
2. **Validada** — `validation-stamp.py --profile <stack>` com `result: APROVADA` no `.cursorsession`; CI é a autoridade final. **Validação runtime (mail/DNS/observ reais) é follow-up pós-LAB e NÃO bloqueia merge.**
3. **Conclusão registrada** — `docs/ROADMAP.md` do repo com status `concluída` + `.cursorsession` atualizado.
4. **Git por sprint** — branch própria da sprint (`sprint/N21-mail-pipeline`) + commit Conventional (`feat(sprint-N21): …`) + push.

Só após **(1–4)** a coordenação central marca a sprint `done` com a chave `(repo : ID-local)` + SHA. **Sem branch+commit+push de conclusão no repo → não avança.** (É a regra que você pediu.)

### Estratégia de branch / PR (decidida 2026-06-19)

- **Branch por sprint** durante a execução: `sprint/<ID>-<slug>`, commits Conventional, push ao concluir cada sprint.
- **PR para `main` por onda** (não por sprint): ao **fechar a onda**, o `/rock` consolida as branches de sprint daquela onda em **1 PR por repo** e roda `/git` → PR para `main`.
  - Onda A → 1 PR em `work-platform-api` (N21+N23+N29) **+** 1 PR em `work-platform-scripts` (N27).
- Mecânica: as branches de sprint mergeiam numa branch de integração `wave/<onda>-<repo>`; o PR é dessa branch para `main`. `main` é protegida — nunca commit direto.

---

## 3. Prontidão das 9 sprints pendentes

Ordem-base (PLATFORM-V2-PLAN §4): `N21 → N23 → N29 → N22 → N25 → [N26 ∥ N27] → N24 → N28`.

| Sprint `(repo : ID)` | Repo executor | Gate (resumo) | Depende | Prontidão |
|----------------------|---------------|---------------|---------|-----------|
| `api:N21` | work-platform-api (+ meApiMail) | MailApiClient + gate R6 + `MAIL_API_*` | N20 ✅ | 🟢 **agora** (código+testes; CI autoridade) |
| `api:N23` | work-platform-api | `farm.inventory` ingest + `PlacementService` | N19 ✅ | 🟢 **agora** (API-only) |
| `api:N29` | work-platform-api (+ meApiMail) | `PdnsClient` + `dns.zone.provision` + DKIM + `domain/verify` | N21, N23 | 🟢 **agora** (mock; creds PowerDNS só p/ validação real) |
| `scripts:N27` | work-platform-scripts | observability default no `deploy-server` + opt-out | — | 🟢 **agora** (código no scripts) |
| `scripts:N26` | work-platform-scripts | `restore-drill.sh` + cron + relatório | — | 🟡 **parcial** (script+doc agora; drill real precisa host/LAB) |
| `(agent+api+scripts):N24` | work-platform-agent + api + scripts | `custom-apps.update` por ring + rollback | N16 ✅, N23 | 🟡 **após N23** (multi-repo) |
| `(onboarding+api+whmcs):N22` | onboarding-api + api + WHMCS | `AddOrder`→`AcceptOrder`, webhook `bill_paid`, suspend/resume | N21; **work-gateway-whmcs V1/V2** | 🔴 **bloqueada** |
| `(work-platform+scripts):N25` | meta-repo `work-platform` + scripts | cluster `lab` + canário R8 + BOM | N14 ✅, N20 ✅, N30(scripts) ✅ | 🔴 **bloqueada** (LAB deferido) |
| `(api+scripts+whmcs+proxmox):N28` | api + scripts + WHMCS + Proxmox | tier `dedicated` via WHMCS; Proxmox read-only | N15 ✅, N23; N22 | 🔴 **bloqueada** |

---

## 4. Ondas de execução (o que o `/rock` toca)

### Onda A — desbloqueada, autônoma (começar já)
1. `api:N21` — mail no pipeline de create
2. `api:N23` — inventário + placement
3. `api:N29` — DNS/deliverability (PowerDNS)
4. `scripts:N27` — observability default no deploy-server

> Tudo com código + testes; **CI é a autoridade** (não depende de LAB para fechar o gate de código). Validação runtime (mail/DNS/observ reais) fica como follow-up pós-LAB, não bloqueia o merge do código.

### Onda B — após Onda A
5. `scripts:N26` — restore-drill (script + doc agora; drill real marcado como follow-up de ambiente)
6. `(…):N24` — ring rollout (depende de `api:N23` concluída)

### Onda C — bloqueada (não autônoma — exige ação externa)
| Sprint | Destrava quando |
|--------|-----------------|
| `(…):N22` | `work-gateway-whmcs` V1/V2 concluídas + whitelist IP da WHMCS API + patch delegador do `work-nc-whmcs` (#ARCH-7) |
| `(…):N25` | LAB reativado (`lab_deferred=false`) + meta-repo `work-platform` criado (N25.1) |
| `(…):N28` | N22 concluída + credenciais WHMCS pid=6 + token Proxmox `PVEAuditor` read-only |

---

## 5. Loop do `/rock` (pseudo-protocolo)

```
para cada ONDA (A → B):
    para cada sprint S da onda (em ordem de dependência):
        se S.prontidao == 🔴: registrar "bloqueada (motivo)"; pular
        move_agent_to_root(S.repo)             # executa no contexto do repo
        branch sprint/<S.id>-<slug>            # branch por sprint
        executar S  (AgentCoder #65)           # test-writer → implementer → integrator
        validar     (validation-stamp + CI)    # CI autoridade; runtime = follow-up
        commit Conventional + push             # DoD §2 (1–4)
        se gate §2 OK:
            marcar S done no plano central: (repo:ID) + SHA
            avançar
        senão:
            PARAR e reportar (não avançar)
    # fim da onda: consolidar por repo
    para cada repo tocado na onda:
        merge branches sprint/* → wave/<onda>-<repo>
        /git → PR para main          # PR por onda, por repo
Onda C: não executar — listar destravas e aguardar decisão humana/ops
```

---

## 6. Estado / ledger (atualizado a cada conclusão)

| Sprint | Repo | Status | SHA / PR | Validação |
|--------|------|--------|----------|-----------|
| N14–N20 | múltiplos | ✅ concluídas | — | (campanha anterior) |
| api:N21 | work-platform-api | ✅ concluída | `7dffff7` → `sprint/N21-mail-pipeline` | 610 passed local |
| api:N23 | work-platform-api | 🔴 RED | `sprint/N23-farm-placement` | 12 tests aguardando GREEN |
| api:N29 | work-platform-api | ⏳ pendente | — | — |
| scripts:N27 | work-platform-scripts | ⏳ pendente | — | — |
| scripts:N26 | work-platform-scripts | ⏳ pendente | — | — |
| N24 | agent+api+scripts | ⏳ pendente | — | — |
| N22 | onboarding+api+whmcs | 🔴 bloqueada | — | — |
| N25 | work-platform+scripts | 🔴 bloqueada | — | — |
| N28 | api+scripts+whmcs+proxmox | 🔴 bloqueada | — | — |
