# Instruções de Correção — Playbook para o Dev

> **Audiência**: dev responsável por executar as correções dos problemas mapeados em `docs/PROBLEMAS-ENCONTRADOS.md`.
>
> **Framework**: Beesy (`~/.cursor/skills/framework/SKILL.md`). Todas as instruções abaixo respeitam:
> - `00-no-cowboy-coding`: nada vira código sem passar por `/triagem`
> - `phase-awareness`: implementação apenas via sprint ativo (`sprint_atual` preenchido em `.cursorsession`)
> - `subagent-naming`: sprints seguem AgentCoder (test-writer RED → implementer GREEN → verifier)
>
> **Este documento NÃO contém código pronto.** É um playbook operacional: passo a passo, na ordem certa.

---

## Como usar este playbook

1. **Abra um chat novo no Cursor** (ou continue o atual se for o mesmo dev).
2. **Confirme que o framework está ativo**: `.cursorsession` existe, contexto carregado.
3. **Execute os passos da ETAPA 1 EM ORDEM**, um por um. Para cada passo:
   - Copie/cole o comando `/triagem [...]`.
   - Aguarde a resposta do PMO (classificação + onde foi registrado).
   - Anote o ID do issue/finding gerado.
   - Passe ao próximo passo.
4. **Após todas as 22 triagens**, vá para a ETAPA 2 (decisões com PMO/Architect).
5. **Após as decisões**, execute as waves em ordem (ETAPA 3) — uma sprint por vez.

> ⚠️ **Não tente acelerar pulando para implementação antes da triagem terminar.** A regra `00-no-cowboy-coding` bloqueia. PMO classifica; depois `/pmo fix` ou `/pmo new` cria o trabalho real.

---

## ETAPA 1 — Triagem em sequência (22 passos)

Ordem otimizada para que itens **parentes/causa raiz** sejam triados antes de **filhos/sintomas** — assim o PMO já tem contexto ao classificar os dependentes.

> 💡 Cada `/triagem` abaixo é um comando único. Copie EXATAMENTE como está, incluindo o pointer para `docs/PROBLEMAS-ENCONTRADOS.md` que o PMO vai consultar.

---

### Passo 1 — P-21 [CRITICAL • bug arquitetural • causa raiz]

> Triar PRIMEIRO porque é causa raiz de P-01 e bloqueia P-22.

```
/triagem [P-21 CRITICAL] Callback `provision success` do upstream é prematuro: tenant ~10 min ainda em config interna (Redis/Collabora/14 apps) quando callback chega. Operações dependentes do backend de users (users:create, users:delete) falham silenciosamente nessa janela. Evidência empírica: 5/5 falhas em Δt<10min, 8/8 sucessos em Δt>30min. Detalhe completo em docs/PROBLEMAS-ENCONTRADOS.md (P-21).
```

**Esperado**: classificação BUG ARQUITETURAL / INVESTIGACAO → ISSUES.md + FINDINGS.md. Provável encaminhamento para Architect (`/arquiteto planejar`).

---

### Passo 2 — P-15 [CRITICAL • diagnóstico errado]

> Triar SEGUNDO porque é parente de P-09 (superseded), P-10 (bloqueado) e P-17.

```
/triagem [P-15 CRITICAL] OccController tem 4 comentários afirmando que upstream "strip OCC --flags" mas evidência empírica refuta: maintenance:mode positional puro também falha com exit 16. Causa real é allowlist de subcmd no occ-exec upstream. Subcmds permitidos: user:list, app:enable, files:scan, user:add, user:resetpassword. Bloqueados (exit 16): user:setting, config:app:set, theming:config, maintenance:mode. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-15).
```

**Esperado**: classificação BUG (correção de comentários é trivial, sprint F) + INVESTIGACAO upstream (D-01 a confirmar allowlist oficial com mantenedor).

---

### Passo 3 — P-09 [SUPERSEDED — apenas registrar]

> Passo curto. Só para o tracker saber que existe e foi anulado.

```
/triagem [P-09 SUPERSEDED por P-15] Diagnóstico original (flag stripping no dispatch.sh) foi refutado empiricamente. Nenhuma ação independente — corrigir comentários do código está dentro do P-15. Mantido como histórico em docs/PROBLEMAS-ENCONTRADOS.md (P-09).
```

**Esperado**: registrar como CLOSED/SUPERSEDED em ISSUES.md (ou simplesmente nota no PROBLEMAS-ENCONTRADOS.md sem entrada nova).

---

### Passo 4 — P-01 [HIGH • sintoma de P-21]

```
/triagem [P-01 HIGH] users:create apresenta falhas intermitentes ~38% no histórico (5/13 jobs). Causa raiz já identificada como sintoma de P-21 (janela de readiness do tenant pós-provision). Sem fix independente — será resolvido por P-21 ou P-22 (saga). Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-01).
```

**Esperado**: registrar como BUG dependente de P-21. Não cria sprint independente.

---

### Passo 5 — P-19 [HIGH • fix trivial • quick win]

```
/triagem [P-19 HIGH] Rotas /api/* inexistentes retornam HTML completo do Laravel (~30 KB CSS inline) em vez de JSON. Info leak (stack revealed) + DX ruim (clientes esperam JSON sob /api). Outros erros retornam JSON corretamente — só 404 NotFoundHttpException está quebrado. Fix: handler em bootstrap/app.php. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-19).
```

**Esperado**: classificação BUG → sprint F (item rápido, ideal para entrar na primeira sprint de fixes).

---

### Passo 6 — P-05 [HIGH • observabilidade]

```
/triagem [P-05 HIGH] Callbacks de webhook chegam com exit_code=null e summary=null em 100% dos jobs (verificado em 28 jobs / 5 verbos). Impede debugging remoto e mascara P-21/P-15. Hipóteses: (1) WebhookHandler descarta campos, (2) upstream não envia, (3) ambos. Precisa investigação read-only do payload raw ANTES da implementação. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-05).
```

**Esperado**: classificação INVESTIGACAO primeiro (capturar payload raw); depois BUG → sprint F.

---

### Passo 7 — P-17 [HIGH • depende de D-02]

```
/triagem [P-17 HIGH] 5 endpoints OCC publicados em routes/api.php retornam permanentemente 502 (exit 16) ou 501 hardcoded: quota/default, quota/all, quota/{username}, branding, maintenance. Causa: subcmds fora da allowlist occ-exec upstream (ver P-15). 3 caminhos possíveis (A pedir ampliação upstream / B despublicar / C usar outro verbo upstream) — decisão D-02 pendente. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-17).
```

**Esperado**: classificação BUG bloqueado por D-02 → fila aguardando decisão.

---

### Passo 8 — P-10 [HIGH • bloqueado por P-15]

```
/triagem [P-10 HIGH bloqueado] setBranding envia múltiplos pares chave/valor para theming:config (que aceita apenas 1 par por execução). Bug funcional latente confirmado por análise estática. Não validável empiricamente até allowlist upstream incluir theming:config (P-15). Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-10).
```

**Esperado**: classificação BUG bloqueado por P-15 → fila.

---

### Passo 9 — P-20 [HIGH • capability faltante • independente]

```
/triagem [P-20 HIGH] Endpoint PUT /customers/{slug}/users/{u}/password ausente, apesar de listado na SSH API Reference §10. Bloqueia operações de suporte (admin não consegue resetar senha via API). Implementação trivial (~20 linhas) usando OccPassthroughService::exec('user:resetpassword', ...). user:resetpassword já está na allowlist occ-exec confirmada. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-20).
```

**Esperado**: classificação MELHORIA ou FEATURE → sprint F pequena (independente).

---

### Passo 10 — P-22 [HIGH • FEATURE de produto • depende de P-21]

```
/triagem [P-22 HIGH feature] Não há orquestração de onboarding multi-passo. Cliente precisa coordenar provision + create users + create groups + apps manualmente, esbarrando em P-21 (readiness). Proposta: POST /customers/onboarding como saga única (provision → readiness check → groups → users → apps → branding) com idempotência, tratamento de falha parcial, status step-by-step. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-22).
```

**Esperado**: classificação FEATURE → BACKLOG.md com dependência declarada de P-21 → futura sprint N (épico, 2+ sprints).

---

### Passo 11 — P-18 [HIGH • depende de D-03]

```
/triagem [P-18 HIGH] Não há proxy OCS na API. Capabilities nativas do Nextcloud (Sharing API, Federation, Push notifications, Talk, Provisioning) inacessíveis programaticamente. OCS está funcional no tenant (testado em meuframe.dev.mework360.com.br/ocs/v2.php/cloud/capabilities → 200). Decisão D-03 pendente: implementar proxy completo / só Provisioning / não suportar. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-18).
```

**Esperado**: classificação FEATURE bloqueada por D-03 → fila.

---

### Passo 12 — P-16 [HIGH • dependência externa]

```
/triagem [P-16 HIGH] Exit code 16 retornado pelo upstream (nextcloud-manage) em todas as falhas de OCC mutativo não está documentado na SSH API Reference §8 (lista 1, 3, 5, 99, 100, 101, 124). Sem documentação, OccController::runOcc cai no default 502 e apaga semântica. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-16).
```

**Esperado**: classificação INVESTIGACAO externa (pedir doc ao mantenedor) + BUG menor (mapear exit 16 → HTTP 403 com mensagem). Aguarda D-01.

---

### Passo 13 — P-02 [HIGH • REFATOR ARQUITETURAL • épico]

> Triar agora, mas execução é um épico depois (Wave 4).

```
/triagem [P-02 HIGH refator] Vocabulário do upstream (argv/stdin schema/occ-exec vs namespace) vaza para CustomerLifecycleController, LifecycleAsyncAction e JobTypeTranslator. Acoplamento causa: P-01 (sintoma do upstream chega no controller), P-15/P-17 (comentários espalhados afirmam coisas erradas), P-04 (validação stdin ausente). Solução: porta UpstreamGateway com DTOs ricos + adapter SshNextcloudManagerGateway. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-02).
```

**Esperado**: classificação MELHORIA ARQUITETURAL → `/arquiteto planejar` → sprint **D** (Decision) → sequência de sprints **N** (refator).

---

### Passo 14 — P-03 [MEDIUM • parte do P-02]

```
/triagem [P-03 MEDIUM] Comentário em JobTypeTranslator afirma que SSH API Reference §14 é "stale doc" sem evidência registrada. P-21 mostrou que namespace user create async funciona empiricamente quando tenant está pronto — não é "errado", é "incompleto em rastreabilidade". Falta Decision documentando trade-off namespace async vs occ-exec sync. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-03).
```

**Esperado**: classificação MELHORIA → será absorvida pela Decision de P-02 (não vira sprint isolada).

---

### Passo 15 — P-04 [MEDIUM • parte do P-02]

```
/triagem [P-04 MEDIUM] LifecycleAsyncAction adiciona --payload-stdin sem validar se o verbo upstream aceita. Funciona hoje porque único verbo com stdin é users:create (e funciona em user create async — P-21 confirmou). Defensive coding faltante: SUPPORTS_STDIN_BY_VERB allowlist. Será absorvido por P-02 (UpstreamGateway). Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-04).
```

**Esperado**: classificação MELHORIA → absorvido por P-02.

---

### Passo 16 — P-07 [MEDIUM • contrato]

```
/triagem [P-07 MEDIUM] Drift entre docs/openapi.yaml e API real: DELETE /queue/{id} vs POST /queue/{id}/cancel; jobId integer vs UUID string; enum JobStatus pending/done vs queued/running/success/failed/cancelled; OCC endpoints divergem. Decidir fonte canônica (YAML manual com teste de drift vs Scramble com overrides). Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-07).
```

**Esperado**: classificação MELHORIA → sprint **D** (Decision sobre fonte canônica) → sprint **F** (alinhamento).

---

### Passo 17 — P-11 [MEDIUM • fix trivial]

```
/triagem [P-11 MEDIUM] OccController::runOcc mapeia qualquer exit_code=1 do upstream para HTTP 404 "not_found". OCC retorna 1 para validation, permission, app já habilitado, quota inválida, etc — não só not found. Cliente recebe 404 enganoso. Fix: parsear stderr e mapear semanticamente (validation→422, permission→403, not found→404). Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-11).
```

**Esperado**: classificação BUG → sprint F (quick win).

---

### Passo 18 — P-12 [MEDIUM • compliance]

```
/triagem [P-12 MEDIUM] OccController só registra audit_log em sucesso. Falhas retornam direto sem auditar — operador pode tentar 99 operações falhando e nenhuma evidência fica no banco. LifecycleAsyncAction tem comportamento diferente (audita sempre) — inconsistência. Padronizar policy: sempre auditar tentativa com result_status. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-12).
```

**Esperado**: classificação MELHORIA → sprint F.

---

### Passo 19 — P-13 [MEDIUM • fix trivial]

```
/triagem [P-13 MEDIUM] Rotas /occ/quota/default, /quota/all, /quota/{username} têm shadowing latente. Sem ->where() constraint, username "default" no Nextcloud fica inacessível via API (cai em setQuotaDefault). Fix: adicionar constraint regex excluindo nomes reservados, OU refatorar paths. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-13).
```

**Esperado**: classificação BUG → sprint F (quick win).

---

### Passo 20 — P-14 [INFO • decisão de design]

```
/triagem [P-14 INFO] OccPassthroughService aceita qualquer subcmd internamente, mas OccController só expõe lista curada. Decisão pendente: implementar POST /customers/{slug}/occ/exec com allowlist por role + audit reforçado + rate limit, OU documentar explicitamente que passthrough é intencionalmente curado. Decisão D-05 envolve security review. Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-14).
```

**Esperado**: classificação INVESTIGACAO ou MELHORIA → aguarda D-05.

---

### Passo 21 — P-06 [LOW • infra-dev]

```
/triagem [P-06 LOW] Scans composer audit/Semgrep/Trivy falharam intermitentemente no ambiente local Windows com espaço no path. Em rodadas posteriores funcionaram. Sugestão: migrar para GitHub Actions como gate de PR (skill ci-automations). Detalhe em docs/PROBLEMAS-ENCONTRADOS.md (P-06).
```

**Esperado**: classificação MELHORIA infra → DevOps task (não sprint da API).

---

### Passo 22 — P-08 [REFERÊNCIA — sem ação]

> Este item NÃO precisa de triagem: já está consolidado em `docs/FINDINGS.md` (21 findings da auditoria F5). Apenas confirme que está acessível.

**Verificação opcional**:
```
grep "F5" docs/FINDINGS.md | head -5
```

**Esperado**: vê os findings da auditoria F5 já registrados. Sem ação adicional.

---

### Checkpoint pós-ETAPA 1

Ao final dos 22 passos, você deve ter em `docs/ISSUES.md` (ou `docs/FINDINGS.md`):

- **2 CRITICAL** registrados: P-15, P-21
- **8 HIGH bugs** registrados: P-01, P-05, P-10, P-16, P-17, P-19, P-20, P-22 (este como feature)
- **2 HIGH arquiteturais**: P-02, P-18
- **5 MEDIUM**: P-03 (absorvido por P-02), P-04 (absorvido), P-07, P-11, P-12, P-13
- **1 INFO**: P-14
- **1 LOW**: P-06
- **1 SUPERSEDED**: P-09
- **1 já consolidado**: P-08

**Comando de validação**:
```
/pmo status
```

Deve listar as 22 entradas registradas pelo PMO.

---

## ETAPA 2 — Decisões PMO + Architect (5 decisões)

Antes de iniciar QUALQUER sprint de implementação, resolva estas 5 decisões. Cada uma vai virar uma entrada em `docs/SETUP-DECISIONS.md`.

| # | ID | Decisão | Quem decide | Bloqueia |
|---|---|---|---|---|
| 23 | D-01 | Allowlist oficial do `occ-exec` upstream | PMO + Architect + mantenedor upstream | P-15, P-17, P-10, P-16 |
| 24 | D-02 | Caminho para P-17 (ampliar / despublicar / outro verbo) | PMO + Architect | P-17 |
| 25 | D-03 | Escopo de proxy OCS (completo / só Provisioning / não suportar) | PMO + Architect | P-18 |
| 26 | D-04 | Caminho para P-21 (upstream / API readiness check / ambos) | PMO + Architect | P-21, P-22 |
| 27 | D-05 | Passthrough OCC genérico (implementar com allowlist / não suportar) | PMO + Security | P-14 |

**Como conduzir cada decisão**:

```
/arquiteto planejar [D-XX descrição]
```

Após a discussão, registrar em `docs/SETUP-DECISIONS.md` (Decision #N). Repetir para cada uma das 5.

---

## ETAPA 3 — Execução em waves

Após ETAPA 1 e 2 concluídas, execute as sprints na ordem das waves abaixo. **Uma sprint ativa por vez** (regra `phase-awareness`). Use `/pmo sprint` para iniciar cada uma; `/git` ao final.

A descrição detalhada de cada wave (arquivos no escopo, critérios de aceite, testes obrigatórios) está nas seções seguintes deste documento.

| Ordem | Sprint | Wave | Itens | Tipo |
|---|---|---|---|---|
| 1ª sprint | F-quick-wins | Wave 1 | P-19, P-11, P-12, P-13, P-15 (comentários) | F |
| 2ª sprint | F-observability | Wave 2 | P-05 | F (precedida de investigação) |
| 3ª sprint | F-reset-password | Wave 3 | P-20 | F |
| 4ª sprint | D-upstream-gateway | Wave 4 | P-02 (Decision) | **D** |
| 5ª+ sprints | N-upstream-gateway-impl | Wave 4 | P-02 implementação (2-3 sprints) | N |
| ~7ª sprint | N-readiness-check | Wave 5 | P-21 | N |
| ~8ª sprint | N-onboarding-saga-D | Wave 6 | P-22 (Decision) | D |
| ~9ª+ sprints | N-onboarding-saga | Wave 6 | P-22 implementação | N |
| Paralelo/Backlog | — | Wave 6 | P-18, P-17, P-10 (depois das decisões) | N |
| Externo | — | Wave 7 | P-06, P-07 | DevOps + Architect |

---

# Detalhe técnico por wave

> A partir daqui é referência. Use ao planejar cada sprint. Cada item já foi triado na ETAPA 1.

---

# WAVE 1 — Quick wins (sprint F, 1 sprint)

Items pequenos, baixo risco, alto valor. Podem ir todos numa única sprint F. Cada um vira uma task na sprint.

**Pré-requisito**: `/pmo fix` cada item para registrar em FINDINGS.md → ISSUES.md → sprint.

## F-01 — P-19 — 404 da API deve retornar JSON, não HTML

- **Triagem**: `/triagem [P-19] 404 de rota /api/* retorna HTML do Laravel em vez de JSON`
- **Classificação esperada**: BUG (HIGH — info leak + DX)
- **Workflow**: `/pmo fix P-19` → sprint F
- **Arquivos no escopo**: `bootstrap/app.php` (ou `app/Exceptions/Handler.php` se preferir), `tests/Feature/Api/NotFoundResponseTest.php` (novo)
- **Critério de aceite**:
  - `GET /api/qualquer-rota-inexistente` → HTTP 404 + `Content-Type: application/json`
  - Body = `{"error":"route_not_found"}` (ou equivalente padronizado)
  - Não retornar stack trace mesmo com `APP_DEBUG=true`
  - Não quebrar HTML em rotas web (`/`, Filament admin, etc.)
- **Testes obrigatórios** (test-writer RED primeiro):
  - 404 sob `/api/*` retorna JSON
  - 404 sob rota web continua retornando HTML
  - Header `Accept: text/html` em `/api/*` ainda retorna JSON (forçar)
- **Dependências**: nenhuma
- **Out of scope**: customizar handler para outros códigos (deixar para sprint F futura se necessário)

## F-02 — P-11 — `OccController::runOcc` mapeamento `exit_code=1 → 404` é incorreto

- **Triagem**: `/triagem [P-11] OccController mapeia qualquer exit_code 1 para HTTP 404`
- **Classificação esperada**: BUG (MEDIUM)
- **Workflow**: `/pmo fix P-11` → sprint F
- **Arquivos no escopo**: `app/Http/Controllers/Api/OccController.php` (linhas 137-157), `tests/Feature/Api/OccControllerTest.php`
- **Critério de aceite**:
  - Exit code 1 + stderr "not found" → HTTP 404
  - Exit code 1 + stderr de validação → HTTP 422
  - Exit code 1 + stderr de permission → HTTP 403
  - Outros casos → HTTP 502 com `exit_code` + `stderr_excerpt` no body
- **Testes obrigatórios**: matriz de exit code × stderr → HTTP esperado
- **Dependências**: nenhuma
- **Atenção**: precisa garantir que `SshRemoteException` carrega `stderr` (já carrega `parsedJson`)

## F-03 — P-12 — Audit log inconsistente entre OccController e LifecycleAsyncAction

- **Triagem**: `/triagem [P-12] OCC falhas não geram audit_log; LifecycleAsyncAction sim`
- **Classificação esperada**: MELHORIA (MEDIUM — compliance)
- **Workflow**: `/pmo fix P-12` → sprint F
- **Arquivos no escopo**: `app/Http/Controllers/Api/OccController.php`, `app/Modules/Customers/Actions/LifecycleAsyncAction.php`, possivelmente migration em `database/migrations/` se faltar campo `result_status`
- **Critério de aceite**:
  - Toda tentativa de operação OCC ou lifecycle gera registro em `audit_logs`
  - Registro tem `result_status: success|failed`
  - Em falha, inclui `error_summary` ou equivalente (sem expor stack trace)
  - Comportamento consistente entre OccController e LifecycleAsyncAction (mesmo policy)
- **Testes obrigatórios**: testar audit em sucesso E em cada caminho de catch
- **Dependências**: idealmente Architect aprova a policy antes (Decision)

## F-04 — P-13 — Route shadowing em `/occ/quota/{username}`

- **Triagem**: `/triagem [P-13] rota quota/{username} colide com quota/default e quota/all`
- **Classificação esperada**: BUG (MEDIUM)
- **Workflow**: `/pmo fix P-13` → sprint F (item pequeno)
- **Arquivos no escopo**: `routes/api.php` (linhas 36-40), `tests/Feature/Api/OccRoutingTest.php`
- **Critério de aceite**:
  - Username `default`, `all`, `audit`, `options` em Nextcloud é acessível via `PUT /quota/{username}`
  - Endpoints estáticos (`/quota/default`, `/quota/all`) continuam funcionando
  - Constraint regex explícito no `->where()`
- **Testes obrigatórios**: provar que `PUT /occ/quota/default` chama setQuotaDefault, mas `PUT /occ/quota/joao` chama setQuota com username=joao
- **Dependências**: nenhuma

## F-05 — P-15 (parcial) — Corrigir comentários do `OccController` que mentem sobre a causa

- **Triagem**: `/triagem [P-15] comentários do OccController afirmam flag stripping mas causa real é allowlist`
- **Classificação esperada**: BUG (CRITICAL, mas o fix dos comentários é trivial)
- **Workflow**: `/pmo sprint q [P-15 comentários]` (trivial — só comentários)
- **Arquivos no escopo**: `app/Http/Controllers/Api/OccController.php` (linhas 42, 56, 95, 105)
- **Critério de aceite**:
  - Comentários antigos ("upstream dispatch strips OCC --flags") removidos
  - Substituídos por "subcmd fora da allowlist do `occ-exec` upstream — ver P-15 e SSH API Reference §4.11"
  - Comentários referenciam a Decision do D-01 (quando disponível)
- **Testes obrigatórios**: nenhum (comentários)
- **Dependências**: idealmente D-01 já resolvido (referência da Decision), mas pode ser feito em paralelo

---

# WAVE 2 — Observabilidade (sprint F, 1 sprint)

## F-06 — P-05 — Persistir `exit_code` e `summary` dos callbacks de webhook

- **Triagem**: `/triagem [P-05] callbacks de webhook chegam com exit_code=null e summary=null em 100% dos jobs`
- **Classificação esperada**: BUG (HIGH — observabilidade)
- **Workflow**:
  - Antes da sprint: rodar **investigação read-only** (capturar payload raw do webhook em 1 job real, em log temporário ou via tcpdump em ambiente dev) para confirmar se o problema é (1) upstream não envia, (2) WebhookHandler descarta, ou (3) ambos.
  - Após investigação: `/pmo fix P-05` → sprint F
- **Arquivos no escopo**:
  - `app/Modules/Jobs/Services/WebhookHandler.php`
  - `app/Modules/Jobs/Dto/WebhookPayload.php`
  - `app/Http/Resources/JobResource.php`
  - migration `database/migrations/{timestamp}_add_exit_code_and_summary_to_jobs.php` (se colunas faltarem)
  - `tests/Feature/Webhook/WebhookHandlerTest.php`
- **Critério de aceite**:
  - Se hipótese 1 (API descarta): WebhookHandler persiste `exit_code` e `summary` quando presentes
  - Se hipótese 2 (upstream não envia): issue criada no `nextcloud-saas-manager` com payload esperado + paliativo na API (ex: derivar summary de stderr quando disponível)
  - `GET /queue/{id}` retorna `exit_code` e `summary` não-nulos para jobs novos
  - Painel admin (Filament) expõe callback raw recebido (facilita suporte)
- **Testes obrigatórios**: webhook com payload completo persiste todos os campos; webhook com campos faltantes não quebra
- **Dependências**: investigação read-only deve preceder

---

# WAVE 3 — Capability faltante (sprint F, 0.5 sprint)

## F-07 — P-20 — Implementar `PUT /customers/{slug}/users/{username}/password`

- **Triagem**: `/triagem [P-20] reset de senha de usuário ausente apesar de listado na SSH API Reference §10`
- **Classificação esperada**: MELHORIA ou FEATURE (HIGH)
- **Workflow**: `/pmo fix P-20` ou `/pmo new` (decidir baseado em se é "bug de spec não implementada" ou "feature nova")
- **Arquivos no escopo**:
  - `routes/api.php` (nova rota)
  - `app/Http/Controllers/Api/CustomerLifecycleController.php` (novo método `resetUserPassword`)
  - `app/Http/Requests/Lifecycle/ResetUserPasswordRequest.php` (novo)
  - `tests/Feature/Api/CustomerLifecycle/ResetUserPasswordTest.php` (novo)
- **Critério de aceite**:
  - `PUT /customers/{slug}/users/{username}/password` aceita body `{password: string}`
  - Valida username (mesmo regex de deleteUser)
  - Valida complexidade da senha (mesmo policy de createUser)
  - Chama `OccPassthroughService::exec($customer, 'user:resetpassword', [$username], json_encode(['password' => $pwd]))`
  - Tratamento de exit codes: 22 → 422 (senha fraca), 1 + "not found" → 404
  - Audit log como `occ_user_password_reset` (senha sanitizada)
  - Documentado em `docs/openapi.yaml`
- **Testes obrigatórios**: happy path, validação de username, validação de senha, user inexistente, password fraca
- **Dependências**: nenhuma (`user:resetpassword` está documentado como dentro da allowlist `occ-exec`)
- **Atenção**: pode descobrir intermitência similar a P-01/P-21 (test envolve user recém-criado). Documentar se ocorrer.

---

# WAVE 4 — Refator arquitetural (sprint N épico, 2-3 sprints)

Este é um épico. **Não pode ser uma sprint F**. Requer:

1. **Architect** (`/arquiteto planejar`) gera Decision em `docs/SETUP-DECISIONS.md`
2. Sprint **D** primeiro (Decision aprovada) → sprints **N** sequenciais
3. Coordenar com `coupling-analysis` e `modular-architecture` skills

## N-01 — P-02 + P-03 + P-04 — Introduzir `UpstreamGateway` (Ports & Adapters)

- **Triagem**: `/triagem [P-02] introduzir UpstreamGateway para isolar contrato HTTP do contrato SSH upstream`
- **Classificação esperada**: MELHORIA ARQUITETURAL (HIGH — refactor)
- **Workflow**:
  1. `/arquiteto planejar P-02` → desenho da interface `UpstreamGateway`, DTOs ricos, estratégia de migração
  2. `/arquiteto contratos` → contratos formais (interfaces PHP, DTOs, exceções)
  3. `/pmo new` → sprint **D** para a Decision arquitetural
  4. Sequência de sprints **N** para migrar verbo a verbo
- **Arquivos no escopo** (estimativa — Architect refina):
  - `app/Modules/Core/Upstream/UpstreamGateway.php` (interface — novo)
  - `app/Modules/Core/Upstream/SshNextcloudManagerGateway.php` (impl — novo)
  - `app/Modules/Core/Upstream/Commands/CreateUserCommand.php` (DTO — novo)
  - … (outros DTOs por verbo)
  - `app/Http/Controllers/Api/CustomerLifecycleController.php` (refator — usa gateway)
  - `app/Modules/Customers/Actions/LifecycleAsyncAction.php` (refator ou substituído)
  - `app/Modules/Core/Translators/JobTypeTranslator.php` (refator — separa cmd↔job_type da argv)
  - `tests/Contract/Upstream/SshNextcloudManagerGatewayTest.php` (novo — TESTES DE CONTRATO REAIS)
- **Critério de aceite por sub-sprint**:
  - Sub-sprint 1: interface `UpstreamGateway` + DTOs + adapter `SshNextcloudManagerGateway` cobrindo `createUser`/`deleteUser` (verbos com stdin)
  - Sub-sprint 2: cobertura de groups/apps
  - Sub-sprint 3: controllers refatorados para usar gateway
  - Sub-sprint 4 (opcional): translator quebrado em dois (cmd↔job_type fica; cmd→argv vai para o gateway)
  - **Em todas**: cobertura mínima 80% para o adapter; testes de contrato rodando contra mock estável que reflete upstream real
- **Validation gate por sub-sprint**: `/integrator` rodando após cada — garantir que controllers e Filament continuam funcionando.
- **Atenção**: P-03 e P-04 são REFINADOS por este épico (não viram tasks separadas). Quando o gateway existir, P-03 vira "registrar Decision sobre namespace vs occ-exec" e P-04 vira "adicionar SUPPORTS_STDIN_BY_VERB no adapter".
- **Risco**: refator grande pode introduzir regressões. Cobertura de teste antes de mexer é crítica.

---

# WAVE 5 — Causa raiz crítica (sprint N, depende de Wave 0)

## N-02 — P-21 — Readiness check do tenant

- **Triagem**: `/triagem [P-21] callback de provision é prematuro; users:create falha em janela de 10 min pós-provision`
- **Classificação esperada**: BUG ARQUITETURAL (CRITICAL)
- **Workflow**:
  1. **D-04 deve estar resolvido** (decisão entre opção A upstream / B API / A+B)
  2. Se B ou A+B: `/arquiteto planejar P-21` para desenhar o probe (intervalo, timeout, checks específicos)
  3. `/pmo new` → sprint N
- **Arquivos no escopo** (estimativa):
  - `app/Modules/Customers/Services/TenantReadinessChecker.php` (novo)
  - `app/Console/Commands/CheckTenantReadinessCommand.php` (novo — probe periódico)
  - `routes/console.php` (scheduler do probe)
  - `app/Models/Customer.php` (novo estado `provisioning_finishing` ou campo `is_ready`)
  - migration adicionando coluna `ready_at` em `customers`
  - middleware ou guard em endpoints lifecycle/occ que retorna 503 se tenant não pronto
  - `tests/Feature/Customers/ReadinessCheckTest.php`
- **Critério de aceite**:
  - Após receber `provision success`, tenant marcado como `not_ready`
  - Probe periódico testa readiness (mínimo: `occ-exec status` retornando 0 + `user:list` respondendo)
  - Tenant marcado `ready` quando probe passa N vezes seguidas
  - Endpoints `users/*` retornam `HTTP 503 + Retry-After: 60` enquanto tenant `not_ready`
  - Métrica: tempo médio de readiness exposto em `/queue/stats` ou endpoint dedicado
- **Testes obrigatórios**: readiness simulation, transição de estado, 503 em endpoints dependentes
- **Dependências**: D-04 resolvido, idealmente P-05 (observabilidade ajuda diagnóstico)

---

# WAVE 6 — Features de produto (sprints N épicos, 4-6 sprints)

## N-03 — P-22 — Saga de onboarding multi-passo

- **Triagem**: `/triagem [P-22] orquestração de onboarding (provision + setup inicial em fluxo único)`
- **Classificação esperada**: FEATURE (HIGH — produto)
- **Workflow**:
  1. **P-21 deve estar resolvido** (saga depende de readiness)
  2. **P-02 ideal mas não bloqueante** (saga pode ser construída sobre controllers atuais)
  3. `/arquiteto planejar P-22` — escolher state machine vs Laravel chain, definir contrato API
  4. Sprint **D** com Decision aprovada
  5. Sequência de sprints **N** (estimativa: 2 sprints — happy path + rollback/idempotência)
- **Arquivos no escopo** (estimativa — Architect refina):
  - `app/Http/Controllers/Api/CustomerOnboardingController.php` (novo)
  - `app/Http/Requests/Onboarding/CreateOnboardingRequest.php` (novo)
  - `app/Modules/Onboarding/Saga/OnboardingSaga.php` (novo)
  - `app/Modules/Onboarding/States/...` (state machine — novo)
  - `app/Models/Onboarding.php` (modelo — novo)
  - migration `database/migrations/{timestamp}_create_onboardings_table.php`
  - `routes/api.php` (novas rotas)
  - `tests/Feature/Api/OnboardingTest.php`
- **Critério de aceite** (resumo — ver P-22 para detalhe):
  - `POST /customers/onboarding` aceita payload completo (tenant + admin + users + groups + apps + branding)
  - Retorna `202 + onboarding_id` em < 2s
  - `GET /customers/onboarding/{id}` mostra progresso step-by-step
  - Idempotência por hash do payload
  - Tratamento de falha parcial documentado
- **Out of scope da v1**: branding (depende de P-10) e proxy OCS (P-18 — épico separado)
- **Dependências**: P-21 obrigatório; P-02 desejável

## N-04 — P-18 — Proxy OCS (depende de D-03)

- **Triagem**: somente se D-03 escolher opção A ou B
- **Workflow**: `/arquiteto planejar P-18` → sprint N épico (estimativa: 2-3 sprints)
- **Dependências**: D-03 resolvido com opção A ou B; idealmente P-02 (UpstreamGateway) para encaixar como segundo adapter

## N-05 — P-17 — Resolver os 5 endpoints OCC quebrados (depende de D-02)

- **Triagem**: depende da opção escolhida em D-02
- **Caminho A** (allowlist upstream ampliada): sprint F de validação — confirmar que funciona, ajustar testes
- **Caminho B** (despublicar): sprint F — remover rotas + remover da OpenAPI + comunicado
- **Caminho C** (outro verbo upstream): sprint N — refatorar `OccController` para chamar verbos não-`occ-exec`
- **Dependências**: D-02 resolvido

## N-06 — P-10 — `setBranding` envio de múltiplas chaves para `theming:config`

- **Bloqueado por P-15/D-01 sendo resolvido upstream**
- Quando desbloquear: sprint F pequena para refatorar para loop (uma chamada SSH por campo, com tratamento de falha parcial)

---

# WAVE 7 — Itens externos / não-código

Estes não viram sprint da API. São ações fora do escopo do dev backend.

| ID | Ação | Quem |
|---|---|---|
| **P-06** | Migrar `composer audit` / Semgrep / Trivy para GitHub Actions | DevOps (skill `ci-automations`) |
| **P-07** | Decidir fonte canônica de OpenAPI (YAML manual vs Scramble) + alinhar `docs/openapi.yaml` | Architect + dev — pode ser sprint **D** |
| **P-08** | Já consolidado em `docs/FINDINGS.md` (auditoria F5) — sem ação adicional aqui | — |
| **P-09** | SUPERSEDED por P-15 — sem ação direta; correção de comentários cobre em F-05 | — |
| **P-15** (parte upstream) | Validar allowlist com mantenedor + documentar resposta | PMO + Architect |
| **P-16** | Documentar exit_code 16 na SSH API Reference §8 (após D-01) | Architect |

---

# Validation gates entre waves

O framework Beesy roda `integrator` (capability) a cada 3 tasks da sprint para verificar coerência. Em adição, sugiro validation gates entre waves:

| Após wave | Validação obrigatória |
|---|---|
| Wave 0 | Todas as 5 Decisions registradas em `docs/SETUP-DECISIONS.md` |
| Wave 1 | `composer test` verde + cobertura sem regressão + manual smoke nos 5 quick wins |
| Wave 2 | Observabilidade: novo job dispatchado tem `exit_code` e `summary` não-nulos no `GET /queue/{id}` |
| Wave 3 | Reset password testado em ambiente dev com user real |
| Wave 4 | Cobertura ≥ 80% para gateway; controllers funcionam idênticos via `/integrator` |
| Wave 5 | Reprodução do P-21 não consegue mais reproduzir falha pós-provision |
| Wave 6 | E2E completo de onboarding em ambiente dev (do `POST /onboarding` até users ativos) |

---

# Estimativa de esforço (rough)

| Wave | Sprints | Pessoas-semana (1 dev) |
|---|---|---|
| 0 — decisões | 0 sprints (decisão) | 0.5 - 1 |
| 1 — quick wins | 1 sprint F | 1 - 1.5 |
| 2 — observabilidade | 1 sprint F | 1 |
| 3 — reset password | 0.5 sprint F | 0.5 |
| 4 — refator arquitetural | 2-3 sprints N | 4 - 6 |
| 5 — readiness check | 1-2 sprints N | 2 - 3 |
| 6 — saga + ocs + occ + branding | 4-6 sprints N | 6 - 10 |
| 7 — externos | tempo de fora | variável |
| **Total** | **9-13 sprints** | **15 - 23 semanas** |

---

# Apêndice — Regras críticas que o dev DEVE respeitar

(extraídas de `.cursor/rules/` e `~/.cursor/rules/`)

1. **`00-no-cowboy-coding`** — todo item novo passa por `/triagem` antes de implementar. Esta lista é o input da triagem, não substituto dela.
2. **`phase-awareness`** — implementação SÓ via sprint ativo (`sprint_atual` em `.cursorsession`). Antes da sprint: só docs, planejamento, briefs.
3. **`subagent-naming`** — sprint segue AgentCoder: `test-writer` (RED) → `implementer` (GREEN+REFACTOR) → `integrator` a cada 3 tasks → `verifier`. Commits no padrão `feat(sprint-Fx): …`.
4. **`grounding-protocol`** — antes de propor solução, ler `.context-snapshot.md` e `docs/SETUP-DECISIONS.md`. Não assumir comportamento upstream sem verificar (foi a causa de P-15).
5. **`10-general.mdc`** (projeto) — Conventional Commits em inglês; funções ≤ 30 linhas; tipos explícitos; nunca catch vazio; nunca > 5 arquivos sem confirmação do PMO.
6. **`user_rules`** — código em inglês, comunicação em PT-BR; nunca `any`; nunca hardcode secrets; um commit por mudança lógica.

---

_Documento gerado em 2026-05-22 01:50 UTC-3 como instrução de execução para `docs/PROBLEMAS-ENCONTRADOS.md`._
