# Problemas Encontrados — Sessão 2026-05-21 / Revisão 2026-05-22

> **Escopo**: lista consolidada de problemas observados durante a auditoria estática (`/qa auditoria`) e a sessão de **testes dinâmicos da API** (`deployer.mework360.com.br/api`, ambiente dev), seguida de análise direta do código.
>
> **Status**: rascunho de triagem. Itens daqui devem ser promovidos para `docs/FINDINGS.md` e/ou `docs/ISSUES.md` após classificação via `/triagem` (regra `00-no-cowboy-coding`).
>
> **Não implementar nada a partir deste arquivo sem antes passar pela triagem e o pipeline de sprint.**
>
> 🔄 **Revisão item-a-item em 2026-05-22 01:45 UTC-3**: ver Sumário Executivo abaixo. Itens reavaliados após evidências novas (P-15, P-17, P-21). 1 SUPERSEDED, 4 REVISADOS, 1 BLOQUEADO, 16 mantidos.

---

## SUMÁRIO EXECUTIVO PÓS-REVISÃO

### Status de cada item

| ID | Severidade atual | Status revisão | Observação |
|---|---|---|---|
| P-01 | HIGH | ✅ triado 2026-05-23 → subsumed por **ISSUE-010** (P-21) / **P-22** (saga) | Sintoma de readiness; mitigado pelo readiness gate (Sprint F8, entregue) e a ser resolvido em definitivo pela saga de onboarding (P-22). Sem fix independente |
| P-02 | HIGH (arq.) | ✅ válido | Acoplamento arquitetural; causa estrutural de muitos itens |
| P-03 | MEDIUM ⬇️ | ⚠️ revisado | Teoria do código não está "errada", está **incompleta**. P-21 mostrou que `user create` namespace + `--payload-stdin` funciona quando tenant está pronto |
| P-04 | MEDIUM ⬇️ | ⚠️ revisado | Mesmo motivo de P-03 — `--payload-stdin` não é universalmente inválido, falta validação por verbo |
| P-05 | HIGH | ✅ ampliado | Confirmado: `exit_code=null`/`summary=null` em **100% dos callbacks** (sucesso E falha), 13 jobs verificados |
| P-06 | LOW ⬇️ | ⚠️ revisado | Shell rodou normal nesta sessão; possivelmente transiente. Manter como observação |
| P-07 | MEDIUM | ✅ válido | Drift OpenAPI ↔ API real |
| P-08 | — | ✅ válido | Já consolidado em `FINDINGS.md` |
| **P-09** | — | ❌ **SUPERSEDED** (triado 2026-05-23) | Diagnóstico ("flag stripping") refutado empiricamente em P-15. Sem ação independente — correção dos comentários do `OccController` consolidada em ISSUE-011 (já entregue). Ver P-15 para causa real |
| **P-10** | HIGH | 🔒 **bloqueado por P-15** | `theming:config` fora da allowlist `occ-exec` (exit 16). Bug do código existe mas não pode ser validado até P-15 resolver |
| P-11 | MEDIUM | ✅ válido | `exit_code=1` mapeado para 404 |
| P-12 | MEDIUM | ✅ válido | Audit log só em sucesso OCC |
| P-13 | MEDIUM | ✅ válido | Shadowing de rota quota |
| P-14 | INFO | ✅ válido | Decisão de design pendente |
| **P-15** | CRITICAL | ✅ triado → **ISSUE-011** (2026-05-23) | Allowlist de subcmd no `occ-exec` upstream (não flag stripping). `/fix` aguarda decisão sobre P-17 |
| P-16 | HIGH | ✅ válido | Exit code 16 não documentado |
| P-17 | HIGH | ✅ válido | 5 endpoints OCC mutativos quebrados em prod |
| P-18 | HIGH | ✅ válido | Não há proxy OCS |
| P-19 | HIGH | ✅ triado 2026-05-23 → **ISSUE-012** | 404 retorna HTML do Laravel; fix isolado em `bootstrap/app.php` (Fix Brief aprovado, < 1 dia) |
| P-20 | HIGH | ✅ válido | Reset password ausente |
| **P-21** | CRITICAL | ✅ triado → **ISSUE-010** / **QA-DYN-021** | **Causa raiz de P-01** — callback prematuro do provision |
| **P-22** | HIGH (feature) | ✅ válido | Saga de onboarding (feature de produto) |

### Cadeias de causa

- **P-01 ← P-21 ← (parcialmente) P-22 corrige**: bug de `users:create` é sintoma de readiness; saga encapsula
- **P-17 ← P-15 ← P-09 (deprecated)**: endpoints OCC quebrados; causa é allowlist (não flag stripping)
- **P-03, P-04 ← P-02**: acoplamento arquitetural cria fragilidades específicas
- **P-10 ← P-15**: branding bugado só pode ser validado se allowlist expandir
- **P-18, P-20 ← P-02**: gaps funcionais alinhados com a falta de gateway/adapter
- **P-19 isolado**: handler de exceção JSON

### Top 5 prioridades sugeridas para triagem

1. **P-21** (CRITICAL) — race condition de readiness; bloqueia automação
2. **P-15** (CRITICAL) — diagnóstico errado sobre OCC; afeta P-17/P-10/comentários do código
3. **P-19** (HIGH) — 404 HTML; impacto em DX + info leak; fix trivial
4. **P-22** (HIGH feature) — saga de onboarding; resolve P-21 a nível de produto
5. **P-02** (HIGH arquitetural) — refatoração estrutural; pré-requisito de muitos fixes

---

## INVENTÁRIO DE ENDPOINTS — Status empírico em 2026-05-21

Cobertura: **22 de 24 endpoints testados** contra `deployer.mework360.com.br/api` (token operador dev). Não testados: `POST /jobs/hook` (precisa HMAC válido) e `DELETE /customers/{slug}` (destrutivo, evitado para preservar tenants).

### Endpoints OK em pelo menos uma execução

| Endpoint | Método | Observação |
|---|---|---|
| `/customers` | POST | criou `meuframe` com sucesso |
| `/customers/{slug}/users` | POST | **OK em sessões recentes**; falhou em janela anterior — ver P-01 atualizado |
| `/customers/{slug}/users/{username}` | DELETE | aceita dispatch (202); user inexistente vira job failed sem motivo |
| `/customers/{slug}/groups` | POST | OK |
| `/customers/{slug}/groups/{group}` | DELETE | OK |
| `/customers/{slug}/apps/enable` | POST | OK |
| `/customers/{slug}/apps/disable` | POST | OK |
| `/customers/{slug}/occ/quota/options` | GET | OK (estático, sem SSH) |
| `/customers/{slug}/occ/quota/audit` | GET | OK (executa `user:list`) |
| `/customers/{slug}/occ/files-rescan?username=` | POST | OK (com `?username=` obrigatório) |
| `/customers/{slug}/occ/apps/{appId}/enable` | POST | OK |
| `/queue` | GET | OK |
| `/queue/stats` | GET | OK |
| `/queue/{id}` | GET | OK (404 com HTML — ver P-19) |
| `/queue/{id}/cancel` | POST | OK; 422 em estado terminal com JSON correto |

### Endpoints quebrados ou degradados

| Endpoint | Método | HTTP | Erro | Causa | Problema |
|---|---|---|---|---|---|
| `/customers/{slug}/occ/quota/default` | PUT | 502 | `exit_code 16` | subcmd `config:app:set` fora da allowlist `occ-exec` | P-15, P-17 |
| `/customers/{slug}/occ/quota/all` | PUT | 501 | `upstream_dispatch_limitation` (hardcoded) | código retorna 501 antes de chamar upstream | P-17 |
| `/customers/{slug}/occ/quota/{username}` | PUT | 502 | `exit_code 16` | `user:setting` fora da allowlist | P-15, P-17 |
| `/customers/{slug}/occ/branding` | PUT | 502 | `exit_code 16` | `theming:config` fora da allowlist (+ argv potencialmente inválido em P-10) | P-10, P-15, P-17 |
| `/customers/{slug}/occ/maintenance` | POST | 502 | `exit_code 16` | `maintenance:mode` fora da allowlist | P-15, P-17 |
| `/customers/{slug}/occ/files-rescan` (sem `?username=`) | POST | 501 | `upstream_dispatch_limitation` (hardcoded) | falta de fallback global | P-17 |
| `/customers/{slug}/groups/{group}/users` | POST | 501 | `not_implemented_yet` | `groups:add` bloqueado upstream (D3/D4 pending) | esperado (translator) |
| `/customers/{slug}/groups/{group}/users/{username}` | DELETE | 501 | `not_implemented_yet` | `groups:remove` bloqueado upstream (D3/D4 pending) | esperado (translator) |

> **Nota**: a referência a "P-09" (flag stripping) foi **removida** após P-15 — diagnóstico desmentido empiricamente. Ver `P-15` para a causa real (allowlist de subcmd).

### Endpoints ausentes mas documentados ou esperados

| Endpoint esperado | Referência | Problema |
|---|---|---|
| `PUT /customers/{slug}/users/{u}/password` | SSH API Ref §10 linha 678 | **P-20** |
| `POST /customers/{slug}/ocs` (proxy OCS) | Padrão Nextcloud | **P-18** |
| `POST /customers/{slug}/occ/exec` (passthrough OCC genérico) | Útil para operação | **P-14** |

### Observabilidade — todos os jobs assíncronos

**Toda** resposta de webhook do upstream chega com `exit_code: null` e `summary: null`, em ambos sucesso e falha. Verificado em 13 jobs de 4 verbos diferentes (`users:create`, `users:delete`, `groups:create`, `groups:delete`, `apps:disable`). Detalhe em **P-05**.

### Higiene de resposta HTTP

`404 de rota inexistente` retorna HTML completo do Laravel (~30 KB de CSS inline) em vez de JSON. Confirmado em pelo menos 6 rotas testadas. **P-19**.

---

---

## Índice

- [P-01 — `users:create` falha quando chamado dentro da janela de readiness do tenant (~10 min pós-provision) (HIGH)](#p-01--userscreate-falha-quando-chamado-dentro-da-janela-de-readiness-do-tenant-10-min-pós-provision-high)
- [P-02 — Mistura de contratos A (HTTP) e B (SSH upstream) (HIGH, arquitetural)](#p-02--mistura-de-contratos-a-http-e-b-ssh-upstream-high-arquitetural)
- [P-03 — Translator declara referência oficial como "stale doc" sem evidência (HIGH)](#p-03--translator-declara-referência-oficial-como-stale-doc-sem-evidência-high)
- [P-04 — `--payload-stdin` aplicado indiscriminadamente por verbo (HIGH)](#p-04--payload-stdin-aplicado-indiscriminadamente-por-verbo-high)
- [P-05 — Worker callback retorna `exit_code=null` e `summary=null` em falhas (HIGH, observabilidade)](#p-05--worker-callback-retorna-exit_codenull-e-summarynull-em-falhas-high-observabilidade)
- [P-06 — Scans `composer audit` / Semgrep / Trivy não rodam no ambiente local (MEDIUM, CI)](#p-06--scans-composer-audit--semgrep--trivy-não-rodam-no-ambiente-local-medium-ci)
- [P-07 — Drift entre OpenAPI publicado e API real (MEDIUM, contrato)](#p-07--drift-entre-openapi-publicado-e-api-real-medium-contrato)
- [P-08 — Auditoria estática F5 (`/qa auditoria`): 21 findings adicionais](#p-08--auditoria-estática-f5-qa-auditoria-21-findings-adicionais)
- [P-09 — Upstream `dispatch.sh` filtra `--flags` do OCC; código convive com workarounds (HIGH)](#p-09--upstream-dispatchsh-filtra---flags-do-occ-código-convive-com-workarounds-high)
- [P-10 — `setBranding` envia múltiplos pares chave/valor para `theming:config` (HIGH)](#p-10--setbranding-envia-múltiplos-pares-chavevalor-para-themingconfig-high)
- [P-11 — `OccController::runOcc` mapeia qualquer `exit_code=1` para HTTP 404 (MEDIUM)](#p-11--occcontrollerrunocc-mapeia-qualquer-exit_code1-para-http-404-medium)
- [P-12 — Audit log só registra sucesso em OCC; falhas não são auditadas (MEDIUM)](#p-12--audit-log-só-registra-sucesso-em-occ-falhas-não-são-auditadas-medium)
- [P-13 — Rotas OCC quota têm shadowing latente entre estáticas e `{username}` (MEDIUM)](#p-13--rotas-occ-quota-têm-shadowing-latente-entre-estáticas-e-username-medium)
- [P-14 — Não existe passthrough OCC genérico exposto (INFO/POSSÍVEL GAP)](#p-14--não-existe-passthrough-occ-genérico-exposto-infopossível-gap)
- [P-15 — Diagnóstico errado sobre OCC: é allowlist de subcmd, não "flag stripping" (CRITICAL)](#p-15--diagnóstico-errado-sobre-occ-é-allowlist-de-subcmd-não-flag-stripping-critical)
- [P-16 — Exit code `16` retornado pelo upstream não está documentado na referência (HIGH)](#p-16--exit-code-16-retornado-pelo-upstream-não-está-documentado-na-referência-high)
- [P-17 — Endpoints OCC quota/branding/maintenance permanentemente quebrados em dev (HIGH)](#p-17--endpoints-occ-quotabrandingmaintenance-permanentemente-quebrados-em-dev-high)
- [P-18 — Não há proxy OCS na API; capability inteira do Nextcloud inacessível (HIGH, gap funcional)](#p-18--não-há-proxy-ocs-na-api-capability-inteira-do-nextcloud-inacessível-high-gap-funcional)
- [P-19 — 404 da API retorna página HTML completa do Laravel em vez de JSON (HIGH, leak + DX)](#p-19--404-da-api-retorna-página-html-completa-do-laravel-em-vez-de-json-high-leak--dx)
- [P-20 — Endpoint reset de senha de usuário está ausente apesar de listado na referência §10 (HIGH)](#p-20--endpoint-reset-de-senha-de-usuário-está-ausente-apesar-de-listado-na-referência-10-high)
- [P-21 — Callback `state=success` do `create` é prematuro; tenant não está realmente pronto (CRITICAL — causa raiz de P-01)](#p-21--callback-statesuccess-do-create-é-prematuro-tenant-não-está-realmente-pronto-critical--causa-raiz-de-p-01)
- [P-22 — Não há orquestração de onboarding multi-passo; cliente precisa coordenar provision + setup inicial manualmente (HIGH, gap de produto)](#p-22--não-há-orquestração-de-onboarding-multi-passo-cliente-precisa-coordenar-provision--setup-inicial-manualmente-high-gap-de-produto)

---

## P-01 — `users:create` falha quando chamado dentro da janela de readiness do tenant (~10 min pós-provision) (HIGH — subsumed)

> ⚠️ **REVISÃO 1 em 2026-05-21 19:25 UTC-3**: análise inicial declarava 100% de falha. Testes posteriores mostraram 8 sucessos e 5 falhas no histórico. Severidade rebaixada de CRITICAL → HIGH.
>
> ⚠️ **REVISÃO 2 em 2026-05-21 20:55 UTC-3**: causa raiz identificada — **race condition de readiness**. Ver matriz empírica abaixo. P-01 vira **sintoma** de **P-21** (callback de provision prematuro). Mantido como item separado porque o caminho de fix pode ser diferente (`users:create` ter retry inteligente vs P-21 corrigir o sinal de readiness).
>
> ✅ **TRIADO em 2026-05-23 23:36 UTC-3**: sem fix independente. Mitigado pelo readiness gate de ISSUE-010 (Sprint F8 entregue — `provisioning_finishing` + `ProbeCustomerReadinessJob` + 503 `tenant_not_ready` em `users:create`/`users:delete`). Resolução definitiva delegada a **P-22** (saga de onboarding). Caminho alternativo de "retry inteligente em `users:create`" descartado em favor da abordagem de produto.

- **Severidade**: HIGH (intermitência sem causa identificada + observabilidade zero — quando falha, não há como diagnosticar).
- **Origem**: testes dinâmicos em 2026-05-21.

### Evidência empírica

#### Histórico observado na queue (33 jobs amostrados)

| cmd_canonical | success | failed |
|---|---|---|
| `users:create` | **8** | **5** |
| `users:delete` | 0 | 1 |
| `groups:create` | 3 | 0 |
| `apps:enable` | 3 | 0 |
| `create` (provision) | 8 | 0 |
| `remove` (deprovision) | 3 | 0 |

#### Padrão das falhas (5 jobs failed)

| Tenant | Quando | Estado |
|---|---|---|
| `meuframe` | 19:50:29 UTC | failed |
| `meuframe` | 19:46:38 UTC | failed |
| `qa-full-1779380141` | 19:18:46 UTC | failed |
| `qa-full-1779380141` | 19:18:43 UTC | failed |
| `qa-test-1779378939` | 18:57:51 UTC | failed |

#### Replicação após o intervalo (22:22 UTC)

- `users:create qa_mf_1779391340` em `meuframe` → **state=success** (mesmo tenant que tinha 2 falhas anteriores).
- `users:create qa_t4_1779391340` em `teste4` → **state=success**.
- Confirmado via `user:list` que ambos os users existem nos tenants.

### Causa raiz confirmada (Revisão 2)

**H1 estava correta** — race condition entre callback de provision e readiness real do tenant.

#### Matriz Δt (provision → users:create) vs resultado

| Tenant | Δt provision → users:create | Resultado |
|---|---|---|
| qa-test-1779378939 | +2m11s | ❌ failed |
| qa-full-1779380141 | +3m01s | ❌ failed |
| qa-full-1779380141 | +3m04s | ❌ failed |
| meuframe | +4m50s | ❌ failed |
| meuframe | +8m41s | ❌ failed |
| meuframe | **+2h40m** | ✅ success |
| teste2 | +38m | ✅ success |
| mano | +38m | ✅ success |
| teste4 (5 tentativas) | +1h39m a +3h15m | ✅ success (5/5) |

**Regra observada**: Δt < 10 min → 5/5 failed. Δt > 30 min → 8/8 success.

#### Evidência circunstancial adicional

Para `qa-test-1779378939` aos ~2 min após provision:

```
18:55:40  create        success
18:57:43  groups:create success   ← funcionou
18:57:46  apps:enable   success   ← funcionou
18:57:48  users:delete  failed    ← falhou
18:57:51  users:create  failed    ← falhou
```

`groups` e `apps` operam logo após o callback de provision, mas operações de `user` falham. Isso é coerente com o passo §4.1.7 da `SSH API Reference` que indica que o provisionamento configura Redis/memcache/trusted proxies/Collabora/Talk e instala 14 apps após o "core install". O backend de auth/users do Nextcloud demora mais para estabilizar do que os subsistemas de grupos e apps.

Reforça que **o callback `provision success` está sendo emitido pelo upstream antes do tenant estar funcionalmente pronto para `user:add`** (P-21).

### O problema que persiste e é PIOR que P-01 isolado

**Mesmo nos sucessos, `exit_code` e `summary` chegam null** no callback. Em uma falha, **não há literalmente nenhuma informação** sobre por que falhou. Esse é o P-05 ampliado: sem observabilidade, não conseguimos confirmar nenhuma das três hipóteses acima.

### Sintoma (revisado)

- `POST /customers/{slug}/users` responde **HTTP 202 + `job_id`**.
- Em 1–4s o job termina, geralmente em `state=success`.
- Em uma fração (5/13 ≈ 38% no histórico observado), termina em `state=failed`.
- Em **ambos** os estados, `exit_code=null`, `summary=null`. Sem causa identificável.

### O que foi REFUTADO sobre P-01 nesta sessão

A hipótese inicial era que o argv `nextcloud-manage <slug> user create <username> --payload-stdin --async` estava incorreto (deveria ser `occ-exec user:add`). Essa hipótese **foi refutada** pelos testes que comprovaram P-21:

- O **mesmo argv** funciona perfeitamente quando o tenant está pronto (>30 min após provision).
- `meuframe` falhou 2× em ≤9 min e funcionou na 3ª tentativa 2h40m depois — **mesmo argv, mesmo código, mesmo upstream**, apenas tempo diferente.
- Logo, P-03 e P-04 (que apontavam o argv/stdin como erro) também precisam ser reclassificados — não são "errados", são "incompletos" em validação.

### Ação recomendada (a decidir em triagem)

- **A — Resolver via P-21 (causa raiz)**: corrigir o callback de provision upstream ou implementar readiness check ativo na API. Elimina o sintoma sem mudar nada em `users:create`.
- **B — Resolver via P-22 (encapsular em saga)**: a orquestração de onboarding aguarda readiness internamente; cliente nunca vê o problema.
- **C — Retry inteligente em `users:create`**: se o job falhar e o tenant for novo (<15 min), reagendar com backoff. Mitigação local sem mexer em readiness/saga.
- **NÃO recomendado**: mudar para `occ-exec user:add` síncrono. Apesar de viável tecnicamente, quebra contrato OpenAPI (202→200) **sem resolver a causa real** (readiness ainda afetaria `users:delete`, `apps:enable` precoce, etc.).

---

## P-02 — Mistura de contratos A (HTTP) e B (SSH upstream) (HIGH, arquitetural)

- **Severidade**: HIGH (arquitetural).
- **Origem**: análise de código a partir de P-01.

### Problema

Existem dois contratos distintos que **deveriam ser isolados**:

- **Contrato A**: HTTP REST público, conforme `docs/openapi.yaml` (cliente → deployer-api).
- **Contrato B**: argv/stdin do `nextcloud-manage`, conforme `docs/SSH API Reference — Nextcloud SaaS.md` (deployer-api → nextcloud-saas-manager via SSH).

Hoje o vocabulário do Contrato B vaza para a camada HTTP:

1. `CustomerLifecycleController::createUser` (linhas 30–58) monta `$stdinPayload` com decisão consciente de "isto vai em positional, isto vai em stdin" — vocabulário do upstream dentro de um controller HTTP.
2. `LifecycleAsyncAction::execute` recebe `array $args, ?array $stdinPayload` separados e apenas concatena — não traduz, só repassa.
3. `JobTypeTranslator` mistura três responsabilidades:
   - `cmd_canonical` ↔ `job_type` (interno à API, contrato A) — pertence à camada de aplicação.
   - `cmd_canonical` → argv do upstream (contrato B) — pertence ao adapter SSH.

### Consequência

O bug P-01 só é difícil de corrigir porque o conhecimento de "como o upstream cria usuário" está espalhado por três camadas (controller, action, translator) em vez de encapsulado num único adapter.

### Ação recomendada

Introduzir uma porta `UpstreamGateway` (Ports & Adapters), com DTOs ricos:

```
HTTP Controller → LifecycleAsyncAction → UpstreamGateway (interface)
                                              │
                                              ▼
                              SshNextcloudManagerGateway (impl)
                              - sabe occ-exec vs namespace
                              - sabe stdin schema
                              - sabe exit codes
```

DTOs do contrato interno (ex: `CreateUserCommand { username, password, email?, groups[] }`) em vez de `array $args + ?array $stdinPayload`.

Tamanho estimado: **sprint dedicada** (N2 ou refactor sprint). Skills relevantes já existem no projeto: `modular-architecture`, `coupling-analysis`.

---

## P-03 — Translator declara referência oficial como "stale doc" sem registro do probing (MEDIUM)

> ⚠️ **REVISÃO 2026-05-22**: severidade rebaixada de HIGH → MEDIUM. A escolha do código (`user create` namespace) **funcionou empiricamente** em P-21 quando o tenant estava pronto. O problema não é que o código está usando o verbo errado — é que **a justificativa não está documentada** e a referência oficial sugere outro caminho. Não é bug de causa raiz, é problema de rastreabilidade.

- **Severidade**: MEDIUM (rastreabilidade/decisão, não correctness).
- **Origem**: análise de código.
- **Arquivo**: `app/Modules/Core/Translators/JobTypeTranslator.php` linhas 67–71.

### Problema (revisado)

O comentário do translator afirma:

> `nextcloud-manage` uses the hierarchical namespace `user create|remove`, `group create|remove`, `apps enable|disable`. The flat `user-create`/`user-delete` forms listed in `SSH API Reference §14` are stale doc; §3.3 is the truth.

A referência oficial sugere, em paralelo:

- **§4.x linha 389**: `user:add` e `user:resetpassword` como verbos canônicos via `occ-exec`.
- **§10 linha 677**: `POST /api/v1/tenants/{client}/users → occ-exec user:add ... --payload-stdin --json`.

Há **dois caminhos válidos no upstream**: namespace `user create` async (usado pelo código) e `occ-exec user:add` síncrono (sugerido pela referência). Ambos funcionam quando o tenant está pronto (P-21 confirmou o primeiro).

O problema é que:

- A escolha pelo namespace async não está em `docs/SETUP-DECISIONS.md`.
- A justificativa "SSH probing" não foi versionada — não há logs, scripts ou ata de probing.
- Não há teste de contrato (`UpstreamContractTest`) que valide o argv real esperado por cada verbo.

### Ação recomendada

- Criar Decision (provavelmente nova) em `docs/SETUP-DECISIONS.md` registrando **por que** namespace async foi escolhido em vez de `occ-exec` síncrono (trade-offs: latência, idempotência, observabilidade via callback, etc.).
- Adicionar teste de contrato em `tests/Contract/Customers/UpstreamContractTest.php` que valida argv real por verbo.
- Anexar evidência do SSH probing original (se existir em algum lugar) à decisão para rastreabilidade futura.

---

## P-04 — `--payload-stdin` aplicado sem validação por verbo (MEDIUM)

> ⚠️ **REVISÃO 2026-05-22**: severidade rebaixada de HIGH → MEDIUM. P-21 demonstrou que `user create --payload-stdin` async **funciona empiricamente** (Nextcloud aceita stdin nesse caminho também, embora a referência §4.x só liste `occ-exec`). O problema não é o uso indevido da flag — é a falta de validação defensiva por verbo no caso de evolução do upstream.

- **Severidade**: MEDIUM (defensive coding / preparação para mudança upstream).
- **Origem**: análise de código.
- **Arquivo**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php` linhas 86–88.

### Problema (revisado)

```php
if ($stdinPayload !== null) {
    $sshArgs[] = '--payload-stdin';
}
```

A flag `--payload-stdin` é adicionada para qualquer verbo que tenha payload, sem distinção. Hoje **funciona** porque o único verbo lifecycle que usa stdin é `users:create` (P-21 confirmou). Mas:

- Não há validação de que o verbo aceite stdin.
- Se algum dia outro verbo precisar de stdin e o upstream rejeitar, o erro será silencioso (mesma classe de problema observada em P-15 com `occ-exec`).
- A documentação oficial §4.x lista stdin como suportado apenas em `occ-exec user:add` / `user:resetpassword` — o uso em `user create` async não está documentado e pode quebrar em versão futura do upstream.

### Ação recomendada

- Curto prazo: adicionar registro `SUPPORTS_STDIN_BY_VERB` no `JobTypeTranslator` (allowlist explícita por verbo).
- Longo prazo: encapsular no `UpstreamGateway` (P-02) onde a decisão "este verbo aceita stdin" fica no adapter junto com o resto do conhecimento do upstream.
- Adicionar entrada em `tests/Contract/Customers/UpstreamContractTest.php` que reproduz cada verbo com stdin esperado vs sem stdin e captura regressões.

---

## P-05 — Callbacks de webhook chegam SEM `exit_code` e `summary` em 100% dos jobs (HIGH, observabilidade)

> ⚠️ **REVISÃO 2026-05-22**: escopo ampliado. Análise inicial dizia "em falhas". Verificação em 13 jobs (de 4 verbos diferentes — `users:create`, `users:delete`, `groups:create`, `groups:delete`, `apps:disable`) mostrou que `exit_code` e `summary` chegam **`null` em TODOS os callbacks**, tanto sucesso quanto falha.

- **Severidade**: HIGH (observabilidade).
- **Origem**: testes dinâmicos 2026-05-21 (sessão completa).

### Problema (escopo ampliado)

Qualquer job assíncrono que termina (independente de sucesso ou falha) chega à API via webhook com:

- `exit_code = null` (era esperado o exit code real do `nextcloud-manage` upstream)
- `summary = null` (era esperado um resumo curto da operação)
- `started_at`, `finished_at`, `callback_received_at` preenchidos corretamente
- `state` correto (`success` ou `failed`)

### Evidência

Verificação em 2026-05-21 22:22 UTC-3 contra `GET /queue?per_page=100`:

| Verbo | Sucessos com exit_code/summary | Falhas com exit_code/summary |
|---|---|---|
| `users:create` | 0 / 8 sucessos | 0 / 5 falhas |
| `users:delete` | — | 0 / 1 falha |
| `groups:create` | 0 / 3 sucessos | — |
| `apps:enable` | 0 / 3 sucessos | — |
| `create` (provision) | 0 / 8 sucessos | — |

**Todos os 28 jobs amostrados chegaram com `exit_code=null` e `summary=null`.**

### Consequência

- Impossibilita debugging remoto (foi o que tornou P-01 difícil de diagnosticar — sem o `exit_code` real, não dava para distinguir "falha de readiness" de "falha de argv" sem rodar correlação manual).
- Audit log fica sem evidência forense de operação.
- Mascara P-21: se o callback de provision incluísse `summary` apontando "ainda configurando", o problema seria descoberto cedo.
- Mascara P-15: exit code 16 do upstream nunca chegaria à camada de aplicação.

### Hipóteses de causa (revisadas)

1. **Mais provável**: `WebhookHandler::applyFinishedEvent` descarta `exit_code`/`summary` mesmo quando presentes no payload (CQ-F5-022 em findings anteriores aponta para isso).
2. O upstream `nextcloud-saas-manager` não envia esses campos no callback (contrato webhook não os inclui).
3. Ambos.

### Ação recomendada

1. **Investigação imediata** (não-implementação): capturar 1 payload raw do callback adicionando log temporário em `WebhookController::receive`. Se vier com `exit_code` no payload → bug na API (hipótese 1). Se vier sem → bug upstream (hipótese 2).
2. Garantir que `WebhookHandler` persiste todos os campos do payload, com migration adicionando colunas `exit_code` / `summary` na tabela `jobs` se ausentes.
3. Se hipótese 2 confirmada: abrir issue formal no `nextcloud-saas-manager` para incluir `exit_code` e pelo menos `reason` (string curta) no callback.
4. Painel Filament para exibir callback raw por job (facilita suporte).
5. Adicionar campos ao `JobResource` para que `GET /queue/{id}` exponha esses dados quando persistidos.

---

## P-06 — Scans `composer audit` / Semgrep / Trivy não rodam consistentemente no ambiente local Windows (LOW, infra-dev)

> ⚠️ **REVISÃO 2026-05-22**: severidade rebaixada de MEDIUM → LOW. Em rodadas posteriores da mesma sessão, comandos shell (incluindo `Invoke-WebRequest`/`curl.exe`) **rodaram normalmente**. O problema parece **intermitente** e específico de ambiente local Windows com espaço no path. Não bloqueia desenvolvimento crítico.

- **Severidade**: LOW (infra-dev / ergonomia local).
- **Origem**: tentativa de execução durante `/qa auditoria` em 2026-05-21.

### Problema

Tentativas iniciais de rodar `composer audit`, Semgrep e Trivy localmente falharam por um hook `rtk` que não tolerou espaços no path do usuário no Windows (`C:\Users\Carlos Rodrigo Born\...`). Rodadas posteriores no mesmo terminal funcionaram para comandos similares — provavelmente questão de PATH/quoting transitório.

### Ação recomendada

- **Não bloqueante para próximas sprints**.
- Migrar esses scans para GitHub Actions (`.github/workflows/`) como gate de PR, removendo dependência do ambiente local Windows com espaços no path. A skill `ci-automations` já cobre esse setup.
- Documentar a recomendação "rodar scans em CI" em `docs/CONTRIBUTING.md` ou equivalente.

---

## P-07 — Drift entre OpenAPI publicado e API real (MEDIUM, contrato)

- **Severidade**: MEDIUM.
- **Origem**: testes dinâmicos cruzados com `document.json` (OpenAPI).

### Discrepâncias confirmadas

| Endpoint | OpenAPI declara | API real |
|---|---|---|
| Cancelar job | `DELETE /queue/{id}` | `POST /queue/{id}/cancel` |
| `jobId` em `/queue/{id}` | `integer` | UUID string |
| Schema `JobStatus` | enum `pending/done` | enum canônico `queued/running/success/failed/cancelled` |
| OCC endpoints | métodos/paths divergem | confirmado em testes |

Já registrado como finding `*` em `docs/FINDINGS.md` (linhas 1800–1810 aprox), repetido aqui para visibilidade.

### Ação recomendada

Decidir fonte canônica de OpenAPI: ou YAML manual com teste de drift, ou Scramble com overrides. Alinhar `docs/openapi.yaml` e `routes/api.php`.

---

## P-08 — Auditoria estática F5 (`/qa auditoria`): 21 findings adicionais

- **Origem**: subagentes security/performance/DBA/QA/senior em modo read-only (2026-05-21).
- **Severidades**: 8 HIGH, 12 MEDIUM, 1 LOW.
- **Local**: já consolidados em `docs/FINDINGS.md` (sprint F5, bloco "Auditoria completa F5 2026-05-21").

### Resumo por domínio (não-exaustivo)

- **Security**: drift de contrato HMAC; replay window discutível; CI supply chain (workflow `beesy-pr-security-review.yml`).
- **Performance**: gargalo no polling SSH em `JobsPollStuckCommand`; loops N+1 potenciais em provisionamento; falta de cache em OCC repetitivos.
- **DBA**: índices ausentes em `idempotency_keys` e `audit_logs` para queries de expiração/purga; cascade rules ambíguas.
- **QA**: cobertura insuficiente em paths de falha do `LifecycleAsyncAction`; ausência de testes de contrato com upstream; E2E ainda backlogado (`ISSUE-007`).
- **Senior**: drift OpenAPI (ver P-07); idempotência transacional incompleta (a key é gravada antes do SSH, mas se o crash for entre SSH OK e Job::create, fica órfã).

Para detalhe completo, ver `docs/FINDINGS.md` linhas correspondentes.

---

## P-09 — ~~Upstream `dispatch.sh` filtra `--flags` do OCC~~ **(SUPERSEDED por P-15)**

> ❌ **SUPERSEDED em 2026-05-22**: o diagnóstico original ("upstream dispatch strips OCC --flags") foi **refutado empiricamente** em P-15. Testes mostraram que `maintenance:mode on` (positional puro, sem nenhuma flag) **também falha** com exit 16 — o problema não é stripping de flag, é **allowlist de subcmds no `occ-exec` upstream**. Ver P-15 para a causa real.

**Mantido aqui apenas como histórico** porque o diagnóstico está espalhado em comentários do `OccController` (linhas 42, 56, 95, 105) e influenciou a estratégia de workarounds atual. Ação concreta de correção dos comentários e roadmap está em P-15.

### Por que isso importa de qualquer forma

Os comentários no código induzem qualquer futuro mantenedor (humano ou IA) a investigar o lugar errado. **A ação "corrigir os comentários" foi consolidada na P-15** (ação recomendada item 1).

---

## P-10 — `setBranding` envia múltiplos pares chave/valor para `theming:config` (HIGH — 🔒 bloqueado por P-15)

> 🔒 **BLOQUEADO em 2026-05-22**: este bug não pode ser validado nem corrigido enquanto P-15 não for resolvido. `theming:config` está **fora da allowlist** do `occ-exec` upstream (retorna exit 16 antes mesmo do argv ser interpretado). Sem allowlist expandida ou caminho alternativo, qualquer fix de argv aqui é teórico. Mantido como problema válido porque, **se o upstream expandir a allowlist**, este bug ficará visível imediatamente.

- **Severidade**: HIGH (bug funcional latente, oculto por P-15).
- **Status**: análise estática confirma o bug; validação empírica bloqueada por P-15.
- **Arquivo**: `app/Http/Controllers/Api/OccController.php` linhas 78–90.

### Problema

```php
public function setBranding(Customer $customer, SetBrandingRequest $request): JsonResponse
{
    $args = [];
    foreach (['name', 'color', 'url', 'slogan', 'imprintUrl', 'privacyUrl'] as $field) {
        if ($request->filled($field)) {
            $args[] = $field;
            $args[] = $request->string($field)->toString();
        }
    }

    return $this->runOcc($customer, 'theming:config', $args, 'occ_set_branding', $request);
}
```

Resultado: `theming:config name "Foo" color "#fff" url "https://..."`.

Mas `theming:config` do Nextcloud é `theming:config <key> <value>` — **uma chave por execução**. Múltiplos pares causam:

- erro de validação OCC (exit code != 0), ou
- aplicação só do primeiro par (resto silenciosamente ignorado).

### Ação recomendada

1. Reproduzir em ambiente dev: enviar `PUT /customers/{slug}/occ/branding` com 3 campos e verificar quais persistem via OCC `theming:config <key>` direto.
2. Refatorar `setBranding` para iterar — uma chamada SSH por campo, com tratamento de falha parcial (rollback ou reporta quais aplicaram). Ou: enviar para o upstream um verbo `branding apply --payload-stdin` que aceite o blob inteiro (atualmente não existe).

---

## P-11 — `OccController::runOcc` mapeia qualquer `exit_code=1` para HTTP 404 (MEDIUM)

- **Severidade**: MEDIUM.
- **Arquivo**: `app/Http/Controllers/Api/OccController.php` linhas 137–157.

### Problema

```php
} catch (SshRemoteException $e) {
    if ($e->remoteExitCode === 1) {
        return response()->json(['error' => 'not_found'], 404);
    }
    return response()->json([
        'error' => 'upstream_error',
        'exit_code' => $e->remoteExitCode,
    ], 502);
}
```

OCC retorna exit code 1 para **qualquer erro genérico** (validation, permission denied, app já habilitado, quota fora do formato, etc.), não só "not found". O cliente recebe 404 e interpreta como recurso inexistente, mascarando bugs e dificultando suporte.

### Ação recomendada

- Parsear `stderr` do `SshRemoteException` (já carrega `parsedJson`/`stderr`) e mapear para HTTP semanticamente correto:
  - validação → 422 com mensagem
  - permission denied → 403
  - not found real → 404
- Manter 502 com `exit_code` + `stderr_excerpt` como fallback quando não der para classificar.
- Adicionar testes de contrato que cobrem cada exit code → status HTTP.

---

## P-12 — Audit log só registra sucesso em OCC; falhas não são auditadas (MEDIUM)

- **Severidade**: MEDIUM (compliance/forensics).
- **Arquivo**: `app/Http/Controllers/Api/OccController.php` linhas 137–172.

### Problema

`runOcc` só cria registro em `audit_logs` se a chamada OCC retornar sucesso. Todos os caminhos `catch` retornam direto sem auditar. Consequência: se um operador tentar 100 operações OCC e 99 falharem, **nenhuma evidência fica no banco**.

`LifecycleAsyncAction` tem comportamento diferente (ver linhas 137–145 dela), o que torna a auditoria **inconsistente** entre módulos.

### Ação recomendada

- Padronizar política de audit log: **sempre auditar tentativa**, com status (`success`/`failed`) e payload sanitizado + exit code/erro quando aplicável.
- Adicionar campo `result_status` na tabela `audit_logs` (provavelmente já existe — verificar schema).
- Documentar em `docs/SETUP-DECISIONS.md` (nova Decision) qual é o padrão de auditoria do projeto.

---

## P-13 — Rotas OCC quota têm shadowing latente entre estáticas e `{username}` (MEDIUM)

- **Severidade**: MEDIUM.
- **Arquivo**: `routes/api.php` linhas 36–40.

### Problema

```php
Route::put('quota/default', [OccController::class, 'setQuotaDefault']);
Route::put('quota/all', [OccController::class, 'setQuotaAll']);
Route::get('quota/audit', [OccController::class, 'quotaAudit']);
Route::get('quota/options', [OccController::class, 'quotaOptions']);
Route::put('quota/{username}', [OccController::class, 'setQuota']);
```

Não há `->where('username', '...')` constrangendo o parâmetro. Como o Nextcloud aceita usernames como `default`, `all`, etc., um usuário legítimo com esses nomes ficaria **inacessível** via `PUT quota/{username}` — a request sempre cairia em `setQuotaDefault`/`setQuotaAll`.

`GET quota/audit` e `GET quota/options` estão protegidos por método (PUT vs GET), mas `default` e `all` colidem direto.

### Ação recomendada

- Adicionar constraint regex: `->where('username', '^(?!default$|all$|audit$|options$)[a-zA-Z0-9._-]+$')`.
- Ou refatorar paths para eliminar ambiguidade: `PUT /occ/users/{username}/quota` vs `PUT /occ/quota/default`.

---

## P-14 — Não existe passthrough OCC genérico exposto (INFO/POSSÍVEL GAP)

- **Severidade**: INFO (decisão de design, possível gap funcional).
- **Origem**: análise de `OccController` e `routes/api.php`.

### Observação

`OccPassthroughService::exec($customer, $subcmd, $args)` aceita **qualquer** OCC subcmd e o transporta via `nextcloud-manage <slug> occ-exec <subcmd>`. Mas o `OccController` só expõe uma lista curada (quota, branding, maintenance, files-rescan, app:enable).

Pode ser intencional (segurança: só permite operações conhecidas) ou pode ser um gap (operador não consegue rodar OCC arbitrário em emergência via API; só via SSH manual).

### Pontos a decidir

1. Existe necessidade operacional para um endpoint `POST /customers/{slug}/occ/exec` com lista de subcmds permitidos por role do operador?
2. Se sim: precisa de allowlist por role + auditoria reforçada + rate limit.
3. Se não: documentar explicitamente em `docs/SETUP-DECISIONS.md` que o passthrough é intencionalmente curado.

---

## P-15 — Diagnóstico errado sobre OCC: é allowlist de subcmd, não "flag stripping" (CRITICAL)

> ✅ **Triado em 2026-05-23** → `docs/ISSUES.md` **ISSUE-011** (postmortem). `/fix` ainda não agendado: depende de decisão prévia sobre P-17 (manter endpoints com 403 honesto vs. despublicar vs. coordenar expansão de allowlist upstream).

- **Severidade**: CRITICAL (entendimento errado da causa raiz; afeta P-09, P-10 e roadmap de fix).
- **Origem**: testes dinâmicos contra `deployer.mework360.com.br/api` em 2026-05-21.
- **Evidência**: matriz empírica abaixo.

### Matriz empírica de subcmds OCC testados

| Subcmd OCC | Tem flag `--`? | Resultado | Tipo |
|---|---|---|---|
| `user:list` | não | ✅ HTTP 200, exit 0 | leitura |
| `app:enable calendar` | não | ✅ HTTP 200, exit 0 | mutação |
| `files:scan admin` | não | ✅ HTTP 200, exit 0 | mutação pesada |
| `maintenance:mode on` (positional) | não | ❌ HTTP 502, exit_code 16 | mutação |
| `maintenance:mode off` (positional) | não | ❌ HTTP 502, exit_code 16 | mutação |
| `user:setting admin files quota 5 GB` | não | ❌ HTTP 502, exit_code 16 | mutação |
| `config:app:set files default_quota 3 GB` | não | ❌ HTTP 502, exit_code 16 | mutação |
| `theming:config name "X"` (1 par) | não | ❌ HTTP 502, exit_code 16 | mutação |

### Conclusão técnica

A teoria que o código adotou está incorreta. Os comentários do `OccController` afirmam:

> "user:setting --all requires --all flag which upstream dispatch strips."
>
> "config:app:set requires --value flag which upstream dispatch strips (POSITIONAL filter bug). Workaround: pass value as 3rd positional; OCC 25+ accepts both forms."
>
> "maintenance:mode --on/--off flags are stripped by upstream dispatch. Workaround: pass 'on'/'off' as positional."

**Esses workarounds não funcionam — e nunca funcionaram contra esse upstream.** Tanto positional (`maintenance:mode on`) quanto flags canônicas (`maintenance:mode --on`) falham com exit 16 — a causa é allowlist, não stripping. A API e o `OccPanel` usam `--on`/`--off` (argv canônico OCC; ver REQUIREMENTS §6.6).

A explicação coerente é **allowlist de subcmds no `nextcloud-manage <client> occ-exec`** no `nextcloud-saas-manager`:

- ✅ Permitidos (deduzidos por teste): `user:list`, `user:add`, `user:resetpassword`, `app:enable`, `files:scan` (e provavelmente `app:disable`, `app:list`).
- ❌ Bloqueados (exit 16): `user:setting`, `config:app:set`, `theming:config`, `maintenance:mode`.

### Consequência

- Os comentários `// upstream dispatch strips OCC --flags` em quatro lugares do `OccController` são **falsos**. Quem mexer no código baseado neles vai investigar o lugar errado.
- O roadmap "Pending upstream fix in `nextcloud-manage dispatch.sh`: pass non-global `--flags` to occ-exec" também está mirando no alvo errado. O fix upstream necessário é **expandir a allowlist de subcmds permitidos em `occ-exec`** (ou refatorar o gateway para passar pelo `occ` direto em vez do wrapper `occ-exec`).

### Ação recomendada

1. **Imediato**: corrigir comentários do `OccController` para refletir a causa real (allowlist, não flag stripping).
2. **Curto prazo**: pedir ao mantenedor do `nextcloud-saas-manager` a lista oficial de subcmds permitidos em `occ-exec` e adicionar à `SSH API Reference §4.11`.
3. **Curto prazo**: documentar em `docs/SETUP-DECISIONS.md` que esta restrição existe.
4. **Médio prazo**: decidir entre (a) ampliar allowlist upstream para incluir `user:setting`, `config:app:set`, `theming:config`, `maintenance:mode`, ou (b) remover os endpoints da API que dependem desses subcmds em vez de devolver 501/502 que mais confundem do que ajudam.

---

## P-16 — Exit code `16` retornado pelo upstream não está documentado na referência (HIGH)

- **Severidade**: HIGH.
- **Origem**: testes dinâmicos.

### Problema

A `SSH API Reference §8` lista códigos de erro do `nextcloud-manage`: `1, 3, 5, 99, 100, 101, 124`. **Não há `16`**. Mas todas as mutações OCC bloqueadas retornaram `exit_code: 16` (vindo do `nextcloud-manage` para a API).

Sem documentação:

- Cliente não sabe se 16 é retryable, validation, permission, ou allowlist hit.
- `OccController::runOcc` (linha 146–154) cai no `default → 502 upstream_error` que apaga qualquer semântica.
- Suporte e debug ficam adivinhando.

### Hipótese

Exit code 16 do **`occ` do Nextcloud** (não do wrapper `nextcloud-manage`) é o código padrão de Symfony Console para `INVALID_OPTION`/`COMMAND_NOT_FOUND` em algumas versões. Pode estar vazando direto do `occ` sem mapeamento no wrapper. OU pode ser código próprio do `occ-exec` para "subcmd não está na allowlist".

### Ação recomendada

1. Pedir documentação oficial de exit code 16 ao mantenedor do upstream.
2. No mínimo, mapear `exit_code = 16` em `OccController::runOcc` para um HTTP 403 (`occ_subcmd_not_allowed`) com mensagem clara, em vez de 502 genérico.
3. Adicionar à `docs/SSH API Reference §8` assim que confirmado.

---

## P-17 — Endpoints OCC quota/branding/maintenance permanentemente quebrados em dev (HIGH)

- **Severidade**: HIGH (capacidade prometida na API que **nunca funciona**).
- **Origem**: testes dinâmicos.

### Endpoints publicados em `routes/api.php` que não funcionam contra o upstream atual

| Endpoint | Status real testado | Motivo (P-15) |
|---|---|---|
| `PUT /customers/{slug}/occ/quota/default` | **502 exit_code 16** | `config:app:set` fora da allowlist |
| `PUT /customers/{slug}/occ/quota/all` | 501 hardcoded | `user:setting` fora da allowlist |
| `PUT /customers/{slug}/occ/quota/{username}` | **502 exit_code 16** | `user:setting` fora da allowlist |
| `PUT /customers/{slug}/occ/branding` | **502 exit_code 16** | `theming:config` fora da allowlist |
| `POST /customers/{slug}/occ/maintenance` | **502 exit_code 16** | `maintenance:mode` fora da allowlist |
| `POST /customers/{slug}/occ/files-rescan` (sem `?username=`) | 501 hardcoded | `files:scan --all` (mas com user funciona) |

### Endpoints OCC que **funcionam**

| Endpoint | OCC interno |
|---|---|
| `GET /customers/{slug}/occ/quota/options` | sem SSH (lista estática) |
| `GET /customers/{slug}/occ/quota/audit` | `user:list` |
| `POST /customers/{slug}/occ/files-rescan?username=<u>` | `files:scan <user>` |
| `POST /customers/{slug}/occ/apps/{appId}/enable` | `app:enable <app>` |

### Consequência

- Mais da metade dos endpoints OCC do `OccController` está com publicação enganosa: cliente recebe 502 sem entender que **não é problema dele** — é a allowlist upstream que precisa expandir.
- Documentação OpenAPI declara esses endpoints como funcionais.
- Operadores não conseguem aplicar quota, branding ou maintenance mode via API. Operação manual via SSH continua sendo necessária para tarefas de governança básica.

### Ação recomendada

Decidir entre três caminhos (precisa do PMO):

- **A — Bloquear merge até upstream ampliar allowlist**: pedir `nextcloud-saas-manager` a expansão da allowlist do `occ-exec` para incluir os subcmds quebrados. Mais correto, mais lento.
- **B — Despublicar endpoints quebrados**: remover de `routes/api.php` e da OpenAPI tudo que não funciona, manter só o que está testado. Honesto e rápido.
- **C — Implementar via outro caminho upstream**: `nextcloud-manage` pode ter verbos não-`occ-exec` para esses casos (`branding apply`, `maintenance enable/disable`, `quota set --user`). Investigar `--help` do upstream antes de qualquer fix.

---

## P-18 — Não há proxy OCS na API; capability inteira do Nextcloud inacessível (HIGH, gap funcional)

- **Severidade**: HIGH (gap funcional — capability nativa do Nextcloud completamente ausente).
- **Origem**: análise de código + testes dinâmicos em 2026-05-21.

### Contexto

O Nextcloud expõe três APIs distintas:

| API | Tipo | Onde fica | Acesso |
|---|---|---|---|
| OCC | CLI | Dentro do container | Via `nextcloud-manage <slug> occ-exec` (já mapeado, ver P-15) |
| **OCS (Open Collaboration Services)** | HTTP REST | Endpoint público no domínio do tenant | `https://meuframe.dev.mework360.com.br/ocs/v1.php/...` |
| WebDAV | HTTP | Endpoint público no domínio do tenant | `https://meuframe.dev.mework360.com.br/remote.php/dav/...` |

OCS é o **caminho oficial** do Nextcloud para várias operações que via OCC nem existem:

- **Provisioning API**: criar/editar/deletar users, groups, group membership (sem precisar do admin no container).
- **Sharing API**: criar links públicos, shares por user/group, federated shares.
- **Federated Cloud API**: identity propagation entre instâncias.
- **Push notifications, Talk API, Calendar API, Activity API, etc.**

### Evidência

- `Grep` em `routes/` e `app/` por `ocs|cloud/users|provisioning`: **zero matches funcionais** (única ocorrência é palavra "occ" em comentário).
- Tentativas de variantes (`/customers/{slug}/ocs/...`, `/ocs/...`, `/customers/{slug}/ocs/exec`): todas **HTTP 404**.
- Mesmo assim, OCS **está rodando** no tenant: `GET https://meuframe.dev.mework360.com.br/ocs/v2.php/cloud/capabilities?format=json` retorna 200 com `Nextcloud 33.0.3`.
- `OCS-APIRequest: true` é honrado pelo Nextcloud, `OCS-APIRequest: false` é rejeitado com 401 — comportamento padrão OCS funcionando.

### Consequência

- Operações como **criar share de arquivo**, **gerenciar federação**, **disparar notificação push**, **listar atividades** são impossíveis via API hoje.
- Para criar usuário, a API tenta `nextcloud-manage user create` async (que está quebrado, P-01) em vez de `OCS POST /cloud/users` (que funcionaria com a senha do admin).
- A `SSH API Reference §10 linha 677-678` lista `POST /users` e `PUT /users/{u}/password` mapeados via OCC `user:add`/`user:resetpassword`. Não há menção a OCS — pode ter sido decisão consciente ou esquecimento.

### Ação recomendada

Discutir em triagem três caminhos possíveis (não-exclusivos):

- **A — Implementar proxy OCS no deployer**: novo endpoint tipo `POST /customers/{slug}/ocs` que recebe `{method, path, body}` e proxia para `https://<slug>.dev.mework360.com.br/ocs/...`. Auth gerenciada via App Password do admin do tenant (armazenada cifrada na API).
- **B — Adapter OCS no `UpstreamGateway`**: como parte da refatoração P-02, ter dois adapters (`SshNextcloudManagerGateway` e `OcsHttpGateway`) com o gateway escolhendo o caminho certo por verbo. Ex: `gateway.createUser()` decide entre `occ-exec user:add` (via SSH) e `OCS POST /cloud/users` (via HTTP).
- **C — Decisão explícita de não suportar OCS**: registrar em `docs/SETUP-DECISIONS.md` o motivo (segurança? complexidade?) e remover OCS do escopo. Honesto, evita falsa expectativa.

---

## P-19 — 404 da API retorna página HTML completa do Laravel em vez de JSON (HIGH, leak + DX)

> ✅ **TRIADO em 2026-05-23 23:55 UTC-3** → **ISSUE-012** (Fix Brief aprovado). Fix isolado em `bootstrap/app.php` via `shouldRenderJsonWhen`; estimativa < 1 dia. Sem dependências, sem upstream. Sprint a alocar (candidato a fast-track).

- **Severidade**: HIGH (information leak + DX).
- **Origem**: testes dinâmicos em 2026-05-21.

### Problema

Ao bater em rotas inexistentes sob `/api/...` **sem** enviar `Accept: application/json`, o servidor retorna HTML. Quando o mesmo request envia `Accept: application/json`, a resposta já é JSON correta — então o bug é específico do caminho onde o cliente omite o header (curl simples, `fetch` sem options, SDKs HTTP minimalistas):

```html
HTTP 404
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Not Found</title>
        <style>/*! normalize.css v8.0.1 ... */ ... centenas de KB de CSS ...</style>
        ...
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 ...">
            <h1>404</h1>
            <div>Not Found</div>
        </div>
    </body>
</html>
```

Problemas múltiplos:

1. **Information leak**: HTML revela stack (Laravel + Tailwind + normalize.css padrão) — útil para fingerprinting de atacante.
2. **DX ruim**: cliente HTTP/SDK espera JSON sob `/api/*` e recebe HTML — quebra parsers e mascara o erro real.
3. **Banda desperdiçada**: ~30 KB de HTML para responder "rota não existe" em endpoint de API.
4. **Inconsistência**: outros erros da API (`409`, `422`, `502`, `503`) retornam JSON corretamente **independentemente** do `Accept` enviado. Só `404 de rota inexistente` é sensível ao header e cai no template HTML quando ele está ausente.

### Causa

Falta um handler de fallback no `bootstrap/app.php` (Laravel 12) que force JSON sob `/api/*` **independentemente** do `Accept`. O Laravel padrão usa o `Accept` para escolher entre `renderHtmlResponse` e `renderJsonResponse` no handler de `NotFoundHttpException` — e clientes HTTP/SDKs frequentemente não enviam `Accept: application/json` por padrão.

### Ação recomendada

1. Em `bootstrap/app.php` (Laravel 12 estilo), adicionar:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(function (Request $request) {
        return $request->is('api/*') || $request->expectsJson();
    });
})
```

2. Customizar a resposta de `NotFoundHttpException` para `{"error":"route_not_found"}` com status 404.

3. Cobrir com teste: `GET /api/rota-inexistente` → 404 + `Content-Type: application/json` + body JSON.

4. Validar que isso não conflita com `user_rules` "nunca exponha stack traces em respostas de API" — provavelmente já existe finding sobre isso em `FINDINGS.md`.

---

## P-20 — Endpoint reset de senha de usuário está ausente apesar de listado na referência §10 (HIGH)

- **Severidade**: HIGH (capability mapeada na referência mas não implementada).
- **Origem**: análise de código.

### Problema

A `SSH API Reference §10 linha 678` lista explicitamente:

```
PUT /api/v1/tenants/{client}/users/{u}/password → occ-exec user:resetpassword <u> --payload-stdin --json
```

Mas no código atual:

- `routes/api.php` **não tem** rota para reset de senha (grep por `resetpassword`/`password` retornou zero matches úteis).
- `CustomerLifecycleController` tem `createUser`/`deleteUser`/`createGroup`/`deleteGroup`/etc, **mas não tem** `resetUserPassword`.

### Consequência

- Operadores não conseguem resetar senha de usuário em tenant via API — precisam de SSH manual.
- Sem reset de senha, **não é possível autenticar OCS direto no tenant** com Basic Auth para experimentar a Provisioning API (impacta P-18).
- Embora `user:resetpassword` **provavelmente esteja na allowlist** do `occ-exec` upstream (referência §4.11 linha 389 lista junto com `user:add`), a API não expõe isso.

### Ação recomendada

Implementar `PUT /customers/{slug}/users/{username}/password`:

- Validar `username` (mesmo regex de `deleteUser`).
- Receber `{password: string}` no body com validação de complexidade.
- Chamar `OccPassthroughService::exec($customer, 'user:resetpassword', [$username], json_encode(['password' => ...]))`.
- Tratar exit codes (22 = senha fraca → 422; 1 + stderr "user not found" → 404).
- Mapear no audit log como `occ_user_password_reset`.

Implementação é **trivial** (~20 linhas) e desbloqueia: (a) operações de suporte, (b) auth OCS para experimentar P-18, (c) reduz dependência de SSH manual.

---

## P-21 — Callback `state=success` do `create` é prematuro; tenant não está realmente pronto (CRITICAL — causa raiz de P-01)

> ✅ **Triado em 2026-05-23** → `ISSUE-010` (`docs/ISSUES.md`) + `QA-DYN-021` (`docs/FINDINGS.md`). Próximo passo: `/qa debug — ISSUE-010`.

- **Severidade**: CRITICAL — quebra qualquer fluxo de onboarding automatizado.
- **Origem**: análise correlacional dos jobs em queue (2026-05-21).

### Problema

O upstream `nextcloud-saas-manager` envia callback `state=success` para `/api/jobs/hook` ao final do job `create` (provision), mas **o tenant Nextcloud ainda NÃO está funcionalmente pronto** quando esse callback chega. Há uma janela de **~10 minutos pós-callback** em que operações dependentes do backend de usuários (`user:add`, `user:remove`) falham silenciosamente.

A própria `SSH API Reference §4.1` admite que o provisionamento dura **5–15 minutos** e envolve passos que o callback não espera completar:

```
7. Configura Redis, memcache, trusted proxies, Collabora, Talk, AppAPI
8. Instala apps: richdocuments calendar contacts mail deck forms notes tasks
   groupfolders photos activity spreed app_api notify_push
9. Atualiza allowlists dos serviços compartilhados
```

A hipótese mais provável é que o callback é emitido depois do passo 6 (Nextcloud core install + admin criado), mas antes de 7–9 (configuração completa + 14 apps instalados + allowlists). Por isso `groups:create` e `apps:enable` funcionam logo após (ambos toleram pendências de configuração), mas `user:add` falha (depende de subsistema completamente estável).

### Evidência

Matriz Δt × resultado documentada em P-01 (revisão 2):

- Δt < 10 min → 5/5 falharam (`user:add`)
- Δt > 30 min → 8/8 sucederam

### Consequência

- Qualquer fluxo programático de onboarding tipo `provision → criar usuários iniciais → criar grupos → atribuir membros → habilitar apps` **vai falhar** se executado sequencialmente respondendo aos callbacks da API. O cliente recebe `state=success` do provision e, ao tentar criar usuário em seguida, recebe `state=failed` sem motivo.
- Não há documentação na referência ou na API que avise sobre essa janela.
- Onboarding manual humano (operador esperando 30+ min entre passos) funciona, mascarando o bug para quem testa manualmente.

### Ação recomendada

Discutir em triagem três caminhos (podem ser combinados):

- **A — Corrigir o callback upstream** (correto, mais lento): pedir ao `nextcloud-saas-manager` que só emita `provision success` depois de passos 7–9 e de um teste de health funcional (ex: `occ status` retornando 0, ou `user:list` respondendo). Esse é o fix de causa raiz.
- **B — API faz readiness check ativo antes de aceitar OCC/lifecycle** (defensivo): após receber `provision success`, a API marca o tenant como `provisioning_finishing`. Endpoints dependentes (`users/*`, `users:setting`) retornam **HTTP 503 + `Retry-After: 60`** até que um probe periódico (`occ-exec status` ou `user:list`) confirme readiness. Aí marca como `active`.
- **C — Cliente faz retry com backoff** (paliativo): documentar a janela na OpenAPI/README com `Retry-After`. Pior dos três; transfere o problema para o consumidor.

Opção B é a mais alinhada com o desacoplamento P-02 (API protege cliente de detalhes do upstream).

### Pontos a validar para confirmar o caminho

1. Pegar `qa-full-*` (que falhou) e tentar de novo agora (deve estar há horas pronto). Se passar, comprova a janela.
2. Provisionar tenant novo, esperar 1 min, tentar `users:create`. Se falhar, reproduz. Aguardar 30 min, tentar de novo. Se passar, fecha o caso.
3. Verificar logs do upstream (`/opt/nextcloud-customers/jobs/<job_id>/output.log`) para o passo em que ele marca `success` no callback.

---

## P-22 — Não há orquestração de onboarding multi-passo; cliente precisa coordenar provision + setup inicial manualmente (HIGH, gap de produto)

- **Severidade**: HIGH (gap de produto + UX de integração ruim).
- **Origem**: discussão arquitetural durante análise (2026-05-21 22:35 UTC-3).
- **Relacionado**: P-01 (sintoma), P-21 (causa upstream), P-02 (acoplamento arquitetural).

### Problema

A API hoje expõe **operações atômicas** (`POST /customers` para provisionar, `POST /customers/{slug}/users` para criar usuário, `POST /customers/{slug}/groups` para grupos, etc.), mas **não expõe orquestração**. O cliente precisa:

1. Chamar `POST /customers` → recebe `job_id` do provision.
2. Polling em `GET /queue/{job_id}` até `state=success`.
3. **Aguardar tempo desconhecido** para o tenant ficar realmente pronto (ver P-21 — janela de readiness não exposta).
4. Chamar `POST /customers/{slug}/users` para cada usuário inicial → cada um gera um `job_id`.
5. Polling em cada um.
6. Chamar `POST /customers/{slug}/groups` para grupos.
7. Polling.
8. Eventualmente `POST /customers/{slug}/groups/{g}/users` para atribuir membros (mas isso retorna **501 blocked_on_upstream** hoje).
9. Chamar `POST /customers/{slug}/apps/enable` se quiser apps customizados.

Sem nada disso encapsulado, o cliente:

- Implementa lógica de orquestração no lado dele (que cada cliente vai implementar diferente).
- Tem que conhecer P-21 (a janela de readiness) para não esbarrar nos failed silenciosos.
- Tem que tratar falhas parciais (provision OK, mas user create falhou — rollback? deixar tenant órfão?).

### Padrão proposto (saga de onboarding)

Endpoint único de alto nível que encapsula todo o fluxo:

```
POST /customers/onboarding
{
  "tenant": {
    "slug": "acme",
    "domain": "acme.dev.mework360.com.br"
  },
  "admin": {
    "username": "admin.acme",
    "password": "<senha>",
    "email": "admin@acme.com"
  },
  "initial_users": [
    {"username": "alice", "password": "...", "email": "alice@acme.com", "groups": ["marketing"]},
    {"username": "bob",   "password": "...", "email": "bob@acme.com",   "groups": ["dev"]}
  ],
  "groups": ["marketing", "dev", "rh"],
  "apps_enabled": ["calendar", "deck"],
  "branding": { "name": "ACME Cloud", "color": "#003366" }
}

→ 202 { "onboarding_id": "<uuid>", "status_url": "/customers/onboarding/<uuid>" }
```

E um endpoint de status que expõe **cada passo da saga**:

```
GET /customers/onboarding/{onboarding_id}
→ 200 {
  "onboarding_id": "<uuid>",
  "tenant_slug": "acme",
  "state": "in_progress" | "success" | "partial_failure" | "failed",
  "steps": [
    {"name": "provision",       "state": "success", "job_id": "...", "started_at": ..., "finished_at": ...},
    {"name": "readiness_check", "state": "success", "checks_passed": ["occ_status", "user_list_responding"], "duration_ms": 480000},
    {"name": "create_groups",   "state": "success", "items": [{"name":"marketing","state":"success"}, ...]},
    {"name": "create_users",    "state": "in_progress", "items": [{"username":"alice","state":"success"}, {"username":"bob","state":"queued"}]},
    {"name": "assign_members",  "state": "pending"},
    {"name": "enable_apps",     "state": "pending"},
    {"name": "set_branding",    "state": "pending"}
  ]
}
```

### Propriedades necessárias dessa saga

1. **Sequenciamento dependente**: cada passo só executa após o anterior terminar com sucesso. Saga aguarda P-21 (readiness check ativo) entre `provision` e `create_users`.
2. **Idempotência**: enviar o mesmo `POST /customers/onboarding` 2x não cria dois tenants — usa idempotency key como já existe em `LifecycleAsyncAction`.
3. **Tratamento de falha parcial**: se `create_users alice` falhar, o cliente vê isso em `status.steps[].items[]`. Política de rollback configurável (continuar ou parar no primeiro erro).
4. **Sem necessidade de polling múltiplo**: o cliente faz polling em **um único** `onboarding_id` em vez de N `job_id`.
5. **Auditoria unificada**: um único registro `onboarding_initiated` na audit log com todos os IDs derivados.

### Por que isso é melhor que o cliente orquestrar

- **Encapsula P-21**: readiness check fica dentro da API, transparente.
- **Encapsula P-18/P-20**: se eventualmente entrar OCS ou reset password, o onboarding orquestra naturalmente.
- **Reduz superfície de erro**: cada cliente externo (frontend admin, integração comercial, terraform provider eventual) usa a mesma orquestração testada.
- **Espelha o vocabulário real do negócio**: "fazer onboarding de cliente" é um conceito de produto; "executar 8 jobs SSH em sequência com timing certo" é detalhe de implementação.

### Trade-offs e pontos a decidir em triagem

- **Síncrono vs assíncrono**: provavelmente assíncrono mesmo (5–15 min só de provision). Mas precisa de SSE/websocket ou polling. Sugestão: polling em `GET /customers/onboarding/{id}` com `ETag`/`Last-Modified` para reduzir custo.
- **Onde mora a saga**: Laravel Queue (jobs Laravel encadeados via `chain`) ou state machine própria (ex: `winzou/state-machine` ou tabela `onboardings` com transitions explícitas). Decisão arquitetural a tomar.
- **Backward compat**: manter endpoints atômicos (`POST /customers`, `POST /users` etc.) para uso individual e operações de manutenção. O `POST /onboarding` é **adicional**, não substitui.
- **Escopo da v1**: começar pequeno — tenant + admin + grupos + users + apps. Branding/OCS ficam para v2.

### Ação recomendada

Isso é uma **feature de produto**, não um bug fix. Pela regra `00-no-cowboy-coding`:

1. Levar para `/triagem` como `FEATURE`.
2. Se aprovado, ir para `BACKLOG.md` com prioridade alta (alinhamento com P-21 — a saga é o consumidor natural do readiness check).
3. Para o Architect (Decision a registrar): definir state machine vs Laravel chain, formato do contrato `POST /onboarding`, política de rollback em falha parcial.
4. Implementação em sprint dedicada — não é trivial (estimativa grosseira: 1 sprint só pro happy path + 1 para rollback/idempotência/observabilidade).

---

## Próximos passos

### 1. Decisões de produto/arquitetura (PMO + Architect)

- **Priorizar P-21 vs P-22**: corrigir causa raiz (readiness check) primeiro ou ir direto para a saga? Recomendação: ambos, mas P-21 desbloqueia P-22.
- **Decidir caminho de P-17** (5 endpoints OCC quebrados): bloquear merge / despublicar / outro verbo upstream? Depende de P-15 ser confirmado com mantenedor do `nextcloud-saas-manager`.
- **Decidir escopo de P-18** (proxy OCS): produto precisa de Sharing, Federation, Push, Talk? Se sim → épico grande.
- **Decidir P-14**: passthrough OCC genérico é gap ou decisão consciente?

### 2. Triagem pendente (caminhar item-a-item)

Pela regra `00-no-cowboy-coding`, cada item deve ser classificado via `/triagem` antes de virar trabalho:

- **CRITICAL** (2 itens, urgência máxima): P-21, P-15
- **HIGH (bug)** (8 itens): P-01, P-05, P-10, P-16, P-17, P-19, P-20, P-22
- **HIGH (arquitetural/feature)** (2 itens): P-02, P-18
- **MEDIUM** (5 itens): P-03, P-04, P-07, P-11, P-12, P-13
- **INFO/LOW** (3 itens): P-06, P-08 (já consolidado), P-14
- **Superseded**: P-09 (deprecated, ver P-15)

### 3. Investigação técnica imediata (read-only, não-cowboy)

Algumas verificações pontuais para fechar evidência antes de triagem:

- **P-05**: capturar 1 payload raw de webhook em produção/dev para confirmar se `exit_code`/`summary` vêm do upstream ou se a API descarta.
- **P-15**: validar com mantenedor do `nextcloud-saas-manager` qual é a allowlist oficial de `occ-exec`.
- **P-21**: reproduzir provisionando um tenant novo e tentando `users:create` a +1min, +5min, +15min, +30min para mapear curva de readiness.

### 4. Validações de CI antes do próximo merge

- Migrar `composer audit`, Semgrep e Trivy para GitHub Actions (P-06) — skill `ci-automations` cobre.
- Garantir que P-15/P-17/P-19 sejam pegos por testes de contrato (`UpstreamContractTest`).

### 5. Promoção formal para `FINDINGS.md` / `ISSUES.md`

Após triagem, mover cada item P-* para o registro apropriado (a maior parte são `FINDINGS.md` por afetar projeto/quality; P-22 e P-18 podem ir para `BACKLOG.md` como features).

---

## Histórico de revisões

- **2026-05-21 17:00 UTC-3**: criação inicial com P-01 a P-08 (auditoria + testes iniciais).
- **2026-05-21 18:00 UTC-3**: adição de P-09 a P-14 (análise OccController).
- **2026-05-21 19:00 UTC-3**: adição de P-15 a P-17 (testes empíricos OCC mutativos).
- **2026-05-21 19:25 UTC-3**: P-01 revisão 1 — rebaixado CRITICAL → HIGH (8 sucessos no histórico).
- **2026-05-21 19:30 UTC-3**: adição de P-18 a P-20 (estudo OCS).
- **2026-05-21 20:30 UTC-3**: inventário completo de 24 endpoints, P-19 visto em GET /queue/{id}.
- **2026-05-21 20:55 UTC-3**: P-01 revisão 2 + criação de P-21 (causa raiz: readiness).
- **2026-05-21 22:35 UTC-3**: adição de P-22 (saga de onboarding como solução de produto).
- **2026-05-22 01:45 UTC-3**: **revisão item-a-item completa** — sumário executivo, P-09 SUPERSEDED, P-10 bloqueado, P-03/P-04/P-06 rebaixados, P-01/P-05 reescritos para refletir evidências de P-21.
- **2026-05-23 23:35 UTC-3**: `/triagem` formaliza P-09 como **SUPERSEDED por P-15**. Sem ação independente; correção dos comentários do `OccController` já entregue em ISSUE-011 (changelog 2026-05-23). Mantido como histórico — nenhuma entrada nova em `ISSUES.md` ou task em ROADMAP.
- **2026-05-23 23:36 UTC-3**: `/triagem` formaliza P-01 como **subsumed por P-21 / ISSUE-010 (mitigação) + P-22 (resolução definitiva)**. Sem fix independente — mitigação já entregue na Sprint F8 (readiness gate, changelog 2026-05-23 `sprint/F8-readiness-gate-iss010`); resolução de produto pela saga de onboarding (P-22) permanece em aberto. Sem nova entrada em `ISSUES.md`.
- **2026-05-23 23:55 UTC-3**: `/triagem` promove P-19 → **ISSUE-012** (HIGH). Bug independente, fix isolado em `bootstrap/app.php` via `shouldRenderJsonWhen` (handler de exceções Laravel 12); estimativa < 1 dia, sem dependências upstream. Critério de aceite e teste de regressão registrados em `docs/ISSUES.md#issue-012`.
