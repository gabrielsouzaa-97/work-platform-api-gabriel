# Issues — mework360-deployer

> Fonte de verdade para enhancements, melhorias e change requests. Bugs e findings de segurança → docs/FINDINGS.md.

## Índice

| ID | Tipo | Título | Módulo | Prioridade | Status |
|----|------|--------|--------|------------|--------|
| ISSUE-001 | change_request | Per-job webhook_token na callback URL | Jobs, Core | HIGH | open |
| ISSUE-002 | postmortem | Webhook 401 — worker upstream não recarregou novo secret | ClusterServers, Webhook | HIGH | mitigated (upstream PR pendente) |
| ISSUE-003 | postmortem | Webhook 422 — vocabulário `finished` não mapeado + dedupe persistia em falha | Jobs, Core, Webhook | HIGH | fixed in API (upstream issue #15 aberta) |
| ISSUE-004 | change_request | Webhook receiver aceita `event=job.started` (callbacks de transição) + dedupe per `(job_id, event)` | Jobs, Core, Webhook | HIGH | implemented |
| ISSUE-005 | change_request | Webhook receiver loga payload em nível debug quando APP_ENV=local | Jobs, Webhook | LOW | implemented |
| ISSUE-006 | postmortem | Lifecycle async envia vocabulário canônico-API ao upstream (`users:create` em vez de `user-create`) + duplica `--async --json` | Customers, Core/Ssh | HIGH | open (Fix Brief aprovado) |
| ISSUE-007 | change_request | E2E browser coverage via Dusk/Playwright para Livewire (cobre wire:submit/click real, MeRC ribbon do bug QA-F5-019) | DevOps, Livewire | MEDIUM | open (backlog — sprint N-UI dedicada) |
| ISSUE-008 | change_request | Fluxo de "Esqueci a senha" para operadores (broker nativo Laravel) | Auth | MEDIUM | open |
| ISSUE-009 | change_request | Logs de Job ausentes na tela `queue/{jobId}` — popular `jobs.summary` via SSH pull pós-`job.finished` | Jobs, Core/Ssh, Webhook | HIGH | open |

---

## ISSUE-001 — Sincronizar webhook secret com o upstream via SSH

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-05-18
- **Revisado em**: 2026-05-18 (2ª revisão de design)
- **Solicitante**: operador (dev)
- **Módulos afetados**: `app/Http/Livewire/ClusterServers/`, `app/Modules/ClusterServers/Actions/`

### Descrição

O `webhook_secret` gerado pela API (e armazenado criptografado em `cluster_servers.webhook_secret_encrypted`) nunca é comunicado ao upstream `nextcloud-saas-manager`. O upstream usa esse secret para assinar os callbacks HMAC-SHA256 — sem sincronização, a validação de HMAC jamais funcionará sem configuração manual.

**Design:** Sempre que um ClusterServer for criado ou o webhook secret for rotacionado, chamar o comando SSH `nextcloud-manage config set-webhook-secret --payload-stdin` passando o secret plain via stdin (JSON). O upstream armazena o secret e passa a assinar os webhooks com ele.

Nota: `webhook_secret` e `webhook_token` são o mesmo conceito neste sistema.

### Critério de aceite

- Criar ClusterServer → chama SSH `config set-webhook-secret`; se SSH falhar, cluster fica com `status='error'` e Livewire exibe erro (sem redirect)
- Rotacionar secret → chama SSH `config set-webhook-secret` com novo secret; se SSH falhar, grace period garante continuidade + log de segurança + audit
- Secret passado via `--payload-stdin` (nunca como arg CLI, per regra do ssh-orchestrator)
- `SyncWebhookSecretAction` encapsula o SSH call — reutilizado em Create e Rotate
- 225+ testes passando; CI verde

---

## ISSUE-002 — Webhook 401 quando worker upstream não recarrega secret novo

- **Tipo**: postmortem
- **Prioridade**: HIGH
- **Status**: mitigated (upstream PR pendente)
- **Registrado em**: 2026-05-20
- **Cluster afetado**: `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`)
- **Módulos afetados**: `app/Modules/ClusterServers/Actions/SyncWebhookSecretAction.php` (sem alteração nesta API), `mework360-deployer-scripts/scripts/lib/config_admin.sh` (fix upstream)

### Sintoma

Após criação do cluster `homolog` em 2026-05-20 00:18:21 UTC com `SyncWebhookSecretAction` (PR #26 já em produção), todo callback do upstream retornava 401 `invalid_signature`:

```
mework360-deployer-nginx | "POST /api/jobs/hook?cluster=119d74df-... HTTP/1.0" 401
```

`audit_logs.action='webhook_invalid_signature'` confirmou HMAC mismatch (não era `unknown_cluster` nem replay).

### Causa raiz

O comando SSH `nextcloud-manage config set-webhook-secret --payload-stdin` (executado pelo `SyncWebhookSecretAction` no upstream) escrevia o novo secret em `/opt/shared-services/secrets/worker_callback_secret` (exit 0 ✓), mas o **worker daemon** (`nextcloud-saas-worker.service`) lê o secret via `LoadCredential` do systemd:

```ini
LoadCredential=callback_secret:/opt/shared-services/secrets/worker_callback_secret
```

Essa diretiva faz uma cópia **congelada** do arquivo em `/run/credentials/<service>/callback_secret` na partida do serviço. Como `_read_callback_secret()` no `worker.sh` sempre encontra `$CREDENTIALS_DIRECTORY` setado quando rodando via systemd, ele lê da cópia congelada — não do arquivo atualizado. Resultado: worker continua assinando callbacks com o secret carregado no boot anterior, enquanto a API espera o secret novo.

### Mitigação imediata (aplicada 2026-05-20 00:58:17 UTC)

```bash
ssh mecloud360@dev.mework360.com.br "sudo systemctl restart nextcloud-saas-worker"
```

Validado por comparação SHA-256: secret no banco da API e secret carregado pelo worker (em `/run/credentials/nextcloud-saas-worker.service/callback_secret`) agora idênticos (`af87c327...ce90`).

### Fix duradouro (em `mework360-deployer-scripts`)

Branch `rr/fix/webhook-secret-reload-worker` modifica `cmd_config_set_webhook_secret` (em `scripts/lib/config_admin.sh`) para executar `systemctl try-restart nextcloud-saas-worker` ao final, reportando o resultado em `worker_reload` (`restarted` / `skipped` / `failed`). Após merge + deploy do upstream, qualquer rotação subsequente vai funcionar sem intervenção manual.

### Lições aprendidas

1. **Operações que mutam configuração consumida por daemons devem incluir o reload do consumidor.** O upstream sabia disso — a mensagem dizia "reinicie o worker" — mas delegava ao caller, que não tinha como saber que isso era obrigatório (era só uma string em JSON).
2. **`systemd LoadCredential` não tem hot-reload nativo.** Sempre que um serviço usa `LoadCredential`, o produtor do credential precisa garantir o restart. Documentar essa constraint em qualquer ADR que defina credenciais via systemd.
3. **Validação E2E pós-criação não cobriu callback HMAC.** O happy path foi `criar cluster → status=active → smoke-test SSH ping`. Faltou um teste que dispara um job real e valida que o callback chega 204. Considerar feature de smoke-test pós-criação que faça um job dummy e valide o webhook round-trip.

### Critério de aceite (fix duradouro)

- PR no `mework360-deployer-scripts` mergeado e deployed em produção
- Próxima rotação de secret via UI da API: webhook chega 204 no primeiro disparo, sem restart manual
- Output JSON do `set-webhook-secret` inclui `worker_reload="restarted"`

---

## ISSUE-003 — Webhook 422 + dedupe-em-falha mascara jobs travados em queued

- **Tipo**: postmortem
- **Prioridade**: HIGH
- **Status**: fixed in API (upstream issue #15 aberta)
- **Registrado em**: 2026-05-20
- **Cluster afetado**: `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`)
- **Jobs travados (reprocessados manualmente)**: `9b200bcb-0ce9-478b-9ca8-a63d05237afd`, `98f44c15-4dde-47a2-8305-41a9db9ef320`, `18c6d4d4-6dc6-489f-bcd3-f2347ffd589c`
- **Módulos afetados**: `app/Modules/Core/Translators/StateTranslator.php`, `app/Http/Middleware/VerifyWebhookHmac.php`

### Sintoma

Logo após o ISSUE-002 ser mitigado (HMAC voltou a bater), os webhooks passaram a chegar mas o painel não atualizava o estado dos jobs. Logs:

```
01:43:13 +0000 "POST /api/jobs/hook?cluster=119d74df-... HTTP/1.0" 422 0
01:43:18 +0000 "POST /api/jobs/hook?cluster=119d74df-... HTTP/1.0" 204 0
```

Body 0 no 422 (não `{"error":"..."}`) — vinha do controller, não do middleware. O 204 imediatamente depois era enganoso: vinha do dedupe do middleware.

### Causa raiz (dois bugs convergindo)

**Bug A — Vocabulário desalinhado**: `worker.sh` (upstream) emite `state="finished"` no callback HMAC quando `exit_code=0`. Mas `StateTranslator::MAP` na nossa API só conhecia `'done' => 'success'` (per docstring `nextcloud-manage §5.2`). Resultado: `UnknownStateException` → controller responde `response('', 422)`.

**Bug B — Dedupe persistido antes do controller**: `VerifyWebhookHmac` chamava `Cache::put($dedupeKey, true, ...)` ANTES de `$next($request)`. Quando o controller falhava (com 422 do bug A, ou qualquer 4xx/5xx no futuro), o retry seguinte do upstream batia o cache e recebia 204 fake — silenciando o problema. O job ficava preso para sempre no estado anterior (queued/running) na nossa API, mesmo o upstream tendo terminado.

A combinação dos dois bugs é insidiosa: o operador via webhooks "chegando" (logs com 204) mas jobs nunca atualizando — sem nenhum 4xx visível após a primeira tentativa.

### Mitigação imediata + fix permanente

1. **Reprocessamento manual dos 3 jobs travados** (2026-05-20 ~01:55 UTC): consultado o estado canônico no Redis upstream (db=15, `nc:jobs:<id>`), atualizado manualmente o banco da API com `state=success|failed`, `exit_code`, `finished_at`, `customer.status`, e `audit_logs.action='webhook_received_manual_replay'` para rastreabilidade.

2. **Fix A** em `app/Modules/Core/Translators/StateTranslator.php`: adicionado `'finished' => 'success'` no MAP, mantendo `'done' => 'success'` por compatibilidade. Comentário documenta a discrepância docstring vs impl real do upstream.

3. **Fix B** em `app/Http/Middleware/VerifyWebhookHmac.php`: dedupe key agora é persistida APENAS quando `$response->getStatusCode() < 300`. Em qualquer 4xx/5xx, o cache fica vazio para que o retry do upstream possa fazer uma nova tentativa real.

4. **Issue upstream**: [`SoftwareBeesy/mework360-deployer-scripts#15`](https://github.com/SoftwareBeesy/mework360-deployer-scripts/issues/15) — alinhar docs com impl real (define `finished` ou ajustar worker.sh para emitir `done`).

### Critério de aceite

- ✓ `StateTranslator` aceita `finished` e `done` (testes em `tests/Unit/Core/StateTranslatorTest.php`)
- ✓ Dedupe não persiste em respostas 4xx/5xx (regression test em `tests/Feature/Api/WebhookReceiveTest.php`: "controller falha com 422 NÃO seta dedupe — retry com payload corrigido sucede")
- ✓ 244/244 testes passando localmente
- ✓ 3 jobs travados reprocessados manualmente; `customer.status` propagado
- ☐ Próximo callback orgânico com `state=finished` chega 204 e atualiza o job (validação pós-deploy)

### Lições aprendidas

1. **Idempotência baseada em "request received" é diferente de "request processed"**. A semântica do dedupe deve refletir "este job já foi processado com sucesso" — caso contrário, falhas transitórias do consumidor e bugs do produtor ficam silenciados em retries que recebem 204.
2. **Documentação de contratos não é fonte-de-verdade automática.** O `StateTranslator` foi codificado a partir de uma docstring (`§5.2: queued, running, done, failed, cancelled`) que não estava alinhada com a implementação real do worker. Sugestão: adicionar teste end-to-end no upstream que valida exatamente o vocabulário emitido por `_fire_callback`, OU gerar o `MAP` automaticamente a partir de um arquivo de contrato compartilhado entre os dois repositórios.
3. **Falsos 204 são tóxicos.** Mais perigoso que um 5xx visível, porque o operador acredita que está tudo OK. Considerar adicionar telemetria que alerta quando jobs ficam em `queued/running` por mais de N minutos sem callback de transição (independente do que o webhook receiver retornou).

---

## ISSUE-004 — Webhook receiver aceita `event=job.started` + dedupe per `(job_id, event)`

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: implemented
- **Registrado em**: 2026-05-20
- **Solicitante**: upstream sprint (mework360-deployer-scripts — expansão aditiva schema_version="1")
- **Módulos afetados**: `app/Modules/Jobs/Dto/WebhookPayload.php`, `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Http/Middleware/VerifyWebhookHmac.php`, `tests/Feature/Api/WebhookReceiveTest.php`

### Contexto

A sprint do `mework360-deployer-scripts` introduziu callbacks de transição: o worker passa a emitir **dois** webhooks por job — um `job.started` (na transição queued→running) e um `job.finished` (na transição running→terminal). Antes, só havia um callback terminal. A mudança é aditiva: `schema_version` permanece `"1"`, mas o payload ganha o campo `event` e o enum de `state` ganha `running`.

### Mudanças no contrato (vindas do upstream)

1. `event ∈ {"job.started", "job.finished"}` no payload (antes inexistente).
2. `state` passa a aceitar `"running"` (antes só `done|finished|failed|cancelled`).
3. `finished_at`, `exit_code` e `duration_ms` **ausentes** quando `event=job.started`.
4. Workers reiniciados podem reenviar `(job_id, job.started)` — o consumer precisa deduplicar por `(job_id, event)`, não apenas por `job_id`.

### Decisões de implementação

- **`WebhookPayload` DTO**: `event` opcional na entrada (default `job.finished` para retro-compatibilidade com workers pré-expansão); `startedAt`, `finishedAt`, `exitCode`, `durationMs` nulláveis; `ts` usado como fallback para `started_at`/`finished_at` dependendo do evento (como o upstream já faz em `_fire_callback`).
- **Dedupe per evento**: chave passa de `webhook_processed:{job_id}` para `webhook_processed:{job_id}:{event}`. O dedupe continua persistido apenas em respostas `< 300` (mantém o fix do ISSUE-003).
- **Replay window**: trocado o ancoramento de `finished_at` para `ts` (sempre presente nos callbacks de transição), com `finished_at` como fallback para workers legados. O fallback para `now()` foi mantido apenas como último recurso, para não desabilitar silenciosamente a janela de replay.
- **`WebhookHandler` ramifica por evento**:
  - `job.started`: seta `state=running` e `started_at` (apenas se ainda nulo); **não** atualiza `finished_at`, `exit_code` ou `customer.status`.
  - `job.finished`: comportamento atual completo (estado terminal, `finished_at`, `exit_code`, propagação Customer).
- **Guarda contra regressão de estado terminal**: se um `job.started` chega DEPOIS de um `job.finished` (out-of-order causado por retry tardio do upstream), o handler retorna 204 silenciosamente em vez de reverter para `running`.
- **Validação de `event`**: valores desconhecidos retornam 422 `invalid_event` no middleware (antes do dedupe), evitando chave de cache com evento espúrio.

### Critério de aceite

- ✓ Payload com `event=job.started` + `state=running` → 204 + job atualizado para `running` + `started_at` setado, sem mexer em `finished_at`/`exit_code`/`customer.status`.
- ✓ Payload com `event=job.finished` → fluxo terminal completo (igual ao anterior).
- ✓ Sequência `(job_id, job.started)` seguida de `(job_id, job.finished)` → ambos 204 + job converge para `success`.
- ✓ Retry de `(job_id, job.started)` após worker restart → 204 idempotente, `started_at` original preservado.
- ✓ Payload sem campo `event` (legacy worker) → 204 tratado como `job.finished`.
- ✓ Payload com `event` desconhecido → 422 `invalid_event`, sem persistir dedupe.
- ✓ Out-of-order (`job.started` chegando após terminal) → 204 sem regredir o estado.
- ✓ 250/250 testes passando (244 anteriores + 6 novos cenários de webhook).

### Observações operacionais

- A coluna `duration_ms` **não** foi adicionada ao modelo `Job` neste change request — o campo é tolerado e registrado no `AuditLog.payload` para forense, mas não consumido no domínio. Se virar requisito de UX (ex.: exibir duração em `/queue/{job_id}`), uma migration aditiva pode ser criada sem impacto na lógica do receiver.
- Backwards compatibility: o release pode ser deployed **antes** do upstream começar a emitir `event` — payloads legados (sem `event`) continuam funcionando exatamente como antes.

---

## ISSUE-005 — Webhook receiver loga payload em nível debug quando APP_ENV=local

- **Tipo**: change_request
- **Prioridade**: LOW
- **Status**: implemented
- **Registrado em**: 2026-05-20
- **Solicitante**: operador (dev)
- **Módulos afetados**: `app/Http/Middleware/VerifyWebhookHmac.php`, `tests/Feature/Middleware/VerifyWebhookHmacTest.php`

### Descrição

Postmortems ISSUE-002 e ISSUE-003 expuseram um gap de observabilidade: quando o webhook receiver rejeita ou silencia callbacks (replay, dedupe, state desconhecido), o desenvolvedor não vê o payload bruto — só AuditLog estruturado. Investigar "por que esse job não atualizou?" exigia tcpdump ou middleware ad-hoc.

**Design:** `Log::debug('webhook.payload_received', [...])` em `VerifyWebhookHmac` guardado por `app()->environment('local')` — gate mais restritivo que `APP_DEBUG`. Nunca dispara em staging (mesmo com `APP_DEBUG=true`) nem em produção. Posicionado após HMAC + struct + event-enum (só loga payload autêntico) e ANTES de replay/dedupe (loga inclusive os rejeitados como duplicados/replay — exatamente os casos mais úteis para forense).

### Critério de aceite

- ✓ `APP_ENV=local` → `Log::debug('webhook.payload_received', {cluster_server_id, ip, event, payload})`
- ✓ `APP_ENV=testing|staging|production` → nenhum log debug emitido
- ✓ Falha do canal de log não converte HTTP em 500 (try/catch + report seguindo padrão de `securityLog()`)
- ✓ NÃO loga: rate-limit (429), unknown_cluster (401), invalid_signature (401), invalid_payload (422) — esses já têm AuditLog próprio
- ✓ Testes pareados local/testing em `VerifyWebhookHmacTest`
- ✓ 46/46 testes da suite de webhook passando

### Observações de segurança

- Payload do webhook NÃO contém segredo (HMAC signature está no header `X-Signature`, não no body)
- Gate por `APP_ENV=local` é mais restritivo que `APP_DEBUG` — staging com debug ativo NÃO loga
- SEC-N1-008 trata do PEM em `/livewire/update`, não deste endpoint

---

## ISSUE-006 — Lifecycle async manda vocabulário canônico-API ao upstream + duplica `--async --json`

- **Tipo**: postmortem
- **Prioridade**: HIGH
- **Status**: open (Fix Brief aprovado — aguarda `/fix` → Sprint F)
- **Registrado em**: 2026-05-20
- **Reportado por**: `/qa debug` sobre log de produção do cluster `homolog` (`119d74df-9011-4c0f-a6bf-ad03f84af10d`, host `dev.mework360.com.br`)
- **Módulos afetados**: `app/Modules/Customers/Actions/LifecycleAsyncAction.php`, `app/Modules/Core/Translators/JobTypeTranslator.php`, `app/Modules/Core/Ssh/SshClient.php`, `tests/Feature/Customers/LifecycleTest.php`, `tests/Unit/Core/JobTypeTranslatorTest.php`

### Sintoma

Tentativa de criar usuário pelo OccPanel (Livewire) ou pelo `POST /api/customers/{c}/users` falha com upstream retornando `exit 101 / cmd_not_allowed`. Log:

```
local.DEBUG: SSH command executed {
  "command":"nextcloud-manage teste5 users:create joao.silva joao.silva@example.com --async --json --idempotency-key=d212a0b4-08a7-44dc-93ab-a1e35c973b35 --callback=https://deployer.mework360.com.br/api/jobs/hook?cluster=119d74df-... --payload-stdin --async --json",
  "exit_code":101,
  "stdout":"{\"error\":\"cmd_not_allowed\",\"cmd\":\"joao.silva\"}"
}
```

A feature inteira de lifecycle async de usuários/grupos/apps (`OccPanel::createUser/deleteUser/createGroup/deleteGroup/addUserToGroup`, `CustomerLifecycleController::createUser/deleteUser/createGroup/deleteGroup/addUserToGroup/removeUserFromGroup/enableApps/disableApps`) está quebrada.

### Causa raiz

Dois bugs interagindo — um arquitetural, um mecânico.

**Bug A — terceiro vocabulário (CLI argv upstream) sem tradutor** (arquitetural)

O sistema tem três vocabulários distintos que precisam de tradução, mas só dois estão implementados:

| Vocabulário | Onde vive | Exemplo | Tradutor |
|---|---|---|---|
| API canônica (`cmd`) | `Job.cmd_canonical`, `IdempotencyKey.cmd`, AuditLog | `users:create` | — (é o vocabulário "raiz") |
| `job_type` | `Job.job_type`, webhook payloads | `user_create` | `JobTypeTranslator::cmdToJobType()` |
| **CLI argv upstream** | argv passado ao `nextcloud-manage` | **`user create`** (namespace hierárquico `user` + verb `create`, per `SSH API Reference §3.3`; §14 lista `user-create` com hífen mas isso é inconsistência da doc — o real é com espaço, confirmado via SSH em `mecloud360@MECloud360-NextCloud-SaaS-01`: `nextcloud-manage teste5 user create --async` → `{"error":"missing_username","message":"user create requer <username>"}`) | **AUSENTE** |

`LifecycleAsyncAction::execute()` injeta o `$cmd` canônico (`users:create`) diretamente no argv:

```php
$sshArgs = array_merge(
    [$customer->slug, $cmd],   // ← $cmd vai cru para o argv
    $args,
    ['--async', '--json', "--idempotency-key={$idempotencyKey}", "--callback={$callbackUrl}"],
);
```

Como `users:create` não é verb async upstream válido (per §14 só aceita `user-create`, `user-remove`, `user-modify`, `group-create`, `group-remove`, `group-modify`, `apps-enable`, `apps-disable`), o parser do upstream sobe um nível e interpreta o próximo token (`joao.silva`) como subcomando → `cmd_not_allowed`.

O `docs/ROADMAP.md` linha 2421 chegou a antecipar isso (`...explode(' ', $cmd)` para cmds multi-palavra), mas a implementação descartou o split e o tradutor argv nunca foi criado.

**Bug B — `--async --json` duplicado no argv** (mecânico)

- `LifecycleAsyncAction::execute()` linhas 70-72 adicionam `'--async', '--json'`.
- `SshClient::runAsync()` linha 69 também faz `array_merge($args, ['--async', '--json'])`.

`ProvisionCustomerAction` (linha 112) e `RemoveCustomerAction` (linha 55) seguem o contrato correto e têm comentário "runAsync appends --async --json automatically". `LifecycleAsyncAction` quebrou esse contrato e ninguém percebeu porque o upstream falhava antes no Bug A.

**Por que os testes não pegaram**

`tests/Feature/Customers/LifecycleTest.php:59,155,179,337,373,410` asserta `in_array('users:create', $args, true)` — ou seja, valida exatamente o vocabulário canônico-API estar no argv, justamente o comportamento bugado. Nenhum teste compara argv contra o que o upstream realmente aceita.

### Critério de aceite

- Criar/deletar usuário via OccPanel ou `POST/DELETE /api/customers/{c}/users` resulta em job upstream enfileirado (`exit 0` + `job_id`), não `cmd_not_allowed`
- Criar/deletar grupo, adicionar/remover usuário do grupo, enable/disable apps idem
- `JobTypeTranslator` ganha método `cmdToCliArgv(string $cmd): array<string>` cobrindo os 8 verbs async lifecycle + verbs estruturais (`create`/`remove`/etc se aplicável)
- `LifecycleAsyncAction` usa o tradutor (substituindo `[$customer->slug, $cmd]` por `[$customer->slug, ...$translator->cmdToCliArgv($cmd)]`) **e** remove o `'--async', '--json'` manual (delegado a `SshClient::runAsync`)
- Assinatura argv upstream confirmada por teste manual contra `dev.mework360.com.br` (cluster `homolog`) antes de codificar o mapping
- Testes de `LifecycleTest` reescritos para asserir o argv **upstream-correto** (`user-create`, etc.) e ausência de `--async --json` duplicado
- `JobTypeTranslatorTest` ganha cobertura dos pares cmd→argv
- `docs/SETUP-DECISIONS.md` registra a decisão sobre o terceiro vocabulário
- `.cursor/skills/vocabulary-translator/SKILL.md` documenta o terceiro vocabulário e seu tradutor
- 230+ testes passando; CI verde

### Decisões aprovadas (via Fix Brief)

1. **Tradutor**: expandir `JobTypeTranslator` com `cmdToCliArgv()` (sem criar classe nova).
2. **Assinatura upstream**: capturar via SSH (`ncsaas-api@dev.mework360.com.br`) antes de codificar mapping.
3. **Email/groups em `createUser`**: decidir após confirmar assinatura upstream — Sprint F gather.

### Descobertas via SSH (`mecloud360@MECloud360-NextCloud-SaaS-01`, upstream v12.3.0)

`nextcloud-manage --help` confirma sintaxe hierárquica (`§3.3` da SSH API Reference é a verdadeira; `§14` está desatualizada):

```
nextcloud-manage <cliente> user   create|remove|modify [--async] [--payload-stdin]
nextcloud-manage <cliente> group  create|remove|modify [--async]
nextcloud-manage <cliente> apps   enable|disable [--async]
nextcloud-manage <cliente> occ-exec <subcmd> [args]
```

**Mapping consolidado (FINAL):**

| API canônica | CLI argv upstream | Status | Args/flags |
|---|---|---|---|
| `users:create` | `['user', 'create']` | ✅ pronto | `<username>` positional + `--payload-stdin` `{password, email?, groups?}` |
| `users:delete` | `['user', 'remove']` | ✅ pronto | `<username>` positional (NÃO `user delete`) |
| `groups:create` | `['group', 'create']` | ✅ pronto | `<groupname>` positional |
| `groups:delete` | `['group', 'remove']` | ✅ pronto | `<groupname>` positional (NÃO `group delete`) |
| `groups:add` | **— bloqueado upstream —** | ❌ não existe | retornar 501 até upstream D3/D4 entregar |
| `groups:remove` | **— bloqueado upstream —** | ❌ não existe | retornar 501 até upstream D3/D4 entregar |
| `apps:enable` | `['apps', 'enable']` | ✅ pronto | `<apps_csv>` positional (CSV nativo!) |
| `apps:disable` | `['apps', 'disable']` | ✅ pronto | `<apps_csv>` positional (CSV nativo!) |

### Design points descobertos no probing

**DP1 — `group modify` NÃO faz membership; é rename**

`group modify <groupname> <action> [new_name]` — o campo `new_name` no `args_json` retornado pelo probing denuncia o propósito real (renomear grupo). O upstream aceitou strings arbitrárias como `action` (até `--add-user` virou string posicional) e descartou args extras silenciosamente, criando jobs "queued" que iriam falhar na execução real do worker. **Conclusão**: `groups:add`/`groups:remove` ficam blocked-on-upstream — a API deve retornar `501 not_implemented` explícito.

**DP2 — `apps enable/disable` aceita CSV nativo**

Assinatura: `apps enable <apps_csv>`. O código atual `CustomerLifecycleController::dispatchMulti()` faz loop disparando N jobs (um por app), gerando N round-trips SSH + N rows em `jobs`/`idempotency_keys` e perdendo atomicidade. Sprint F deve consolidar em **um único job** passando o CSV.

**DP3 — `user create` exige `--payload-stdin` (sem positional após username)**

Probing confirma: nenhum positional além de `<username>` é aceito; email/groups precisam ir no JSON do stdin junto com password. Hoje `OccPanel::createUser` e `CustomerLifecycleController::createUser` passam `email` como segundo positional e `--group=X` como flag — falham silenciosamente. Schema do stdin a padronizar: `{password, email?, groups: string[]?}` (validar com upstream se aceita keys além de `password`).

### Riscos descobertos

1. **Upstream em desenvolvimento (D3/D4)** — vários verbs retornam `not_implemented_yet`. Coordenar com `mework360-deployer-scripts` para implementação dos verbs de membership (`group add-user`/`remove-user`) ou definir contrato alternativo.

2. **Documentação upstream desatualizada** — `SSH API Reference §14` lista `user-create` (hífen) que não existe. O real é `user create` (espaço/namespace hierárquico per §3.3). Abrir issue no `mework360-deployer-scripts` para alinhar.

3. **Testes mockam `SshClientInterface` com asserções simétricas ao bug** — `LifecycleTest.php` valida `in_array('users:create', $args)` (argv canônico-API). Os mocks nunca compararam contra contrato upstream real. Sprint F precisa de pelo menos um teste de **contrato/integração** (com flag de skip em CI) que dispare SSH real e valide `exit 0 + job_id`.

### Próximo passo

Executar `/fix` para criar Sprint F com TDD + auditoria HIGH no delta. Escopo recomendado da Sprint F:

- **F1**: Implementar `JobTypeTranslator::cmdToCliArgv()` com mapping fechado acima + exceção `BlockedOnUpstreamException` para `groups:add`/`groups:remove`.
- **F2**: Refatorar `LifecycleAsyncAction::execute()` — usar tradutor + remover `--async/--json` manual (delegação a `SshClient::runAsync`).
- **F3**: Atualizar `CustomerLifecycleController` — `groups:add`/`groups:remove` retornam 501 explícito; `apps:enable`/`apps:disable` consolidam em job único com CSV; `createUser` move email/groups para stdin payload.
- **F4**: Espelhar mudanças no `OccPanel` (Livewire).
- **F5**: Reescrever asserções de teste para argv upstream-correto + adicionar testes de pares cmd→argv no tradutor.
- **F6**: Atualizar `docs/SETUP-DECISIONS.md` (decisão sobre 3º vocabulário) e `.cursor/skills/vocabulary-translator/SKILL.md`.
- **F7** (opcional, atrás de flag): teste de contrato SSH real disparando 1 verb de cada categoria contra cluster `homolog`.

Executor deve usar modelo diferente do diagnosticador (model diversity per framework rule).

---

## ISSUE-007 — E2E browser coverage via Dusk/Playwright

- **Tipo**: change_request
- **Prioridade**: MEDIUM
- **Status**: open (backlog — sprint N-UI dedicada)
- **Registrado em**: 2026-05-20
- **Origem**: spillover de F5 R2 — finding `QA-F5-019` apontou que cobertura de UI Livewire via `Livewire::test()` não exercita o navegador real (HTML parsing, JS Alpine, eventos `wire:submit`/`wire:click`). F5.11 corrigiu o bug em camada de same-path (`wire:model` + `set('userPasswordPlain')` em testes), mas continua faltando proteção contra divergências futuras blade↔componente que só apareçam em browser real.
- **Solicitante**: auditor-qa R2 (gemini-3.1-pro) + auditor-senior R2 (claude-4.6-sonnet-medium-thinking)

### Descrição

A stack de testes do projeto cobre:
- **Unit** (Pest): translators, slug rule, value objects.
- **Feature/HTTP** (Pest + Laravel TestCase): controllers via `$this->get/post`, autorização via Gate, validação.
- **Feature/Livewire** (Pest + Livewire\Livewire): componentes via `Livewire::test()->set()->call()->assert*()`.
- **Contract** (Pest, opt-in `RUN_UPSTREAM_CONTRACT=1`): SSH real contra cluster `homolog`.

Falta uma camada **browser real** (Dusk ou Playwright) para:
1. Validar que `wire:model` e `wire:submit` populam o payload Livewire conforme a view renderizada (não apenas conforme assumimos no teste Livewire).
2. Pegar divergências HTML/CSS/JS que `Livewire::test()` por design não enxerga (ex.: input com `type="password"` sem `wire:model` — exatamente o cenário do bug `QA-F5-019`).
3. Cobrir interações Alpine.js, modais, navegação multi-página (login → dashboard → painel → ação).

### Critério de aceite (proposta para sprint dedicada)

- Instalar `laravel/dusk` (Chrome) **ou** Playwright (Node) — decidir após avaliar custo do container browser no CI/dev.
- 1 teste E2E happy-path por área crítica:
  - Auth: login + redirect ao dashboard.
  - Customers: criar + ver na listagem.
  - **OccPanel/createUser**: digitar senha no campo + click no "Criar Usuário" → job enfileirado e mensagem de sucesso visível (regressão guard sobre `QA-F5-019`).
  - ApiKeys: criar + copiar via clipboard.
  - Operators: criar via convite + aceitar com URL assinada.
- Setup CI: container browser separado (Selenium standalone-chrome ou Playwright official image) com lifecycle apenas para a job `e2e`; não bloquear a job `test`.
- Documentar em `docs/TESTING.md` quando rodar E2E (pre-release vs PR vs branch protected).

### Riscos / não-decisões

- **Custo de manutenção**: testes E2E são frágeis (CSS selectors mudam, animações causam flakiness). Restringir a happy paths críticos; nunca espelhar cobertura unit/feature em E2E.
- **Custo de CI**: container browser adiciona ~30-60s ao pipeline; manter job opcional em PRs e obrigatória em releases.
- **Decisão Dusk vs Playwright**: Dusk integra mais limpo com Laravel (factories + database transactions), Playwright tem melhor DX e suporte cross-browser (Firefox/Safari). Decidir na sprint.

### Próximo passo

Não há próximo passo imediato — esta issue fica em backlog até decisão de roadmap para uma sprint N-UI dedicada (não bloquear sprints F/N atuais).


---

## ISSUE-008 — Fluxo de "Esqueci a senha" para operadores

- **Tipo**: change_request
- **Prioridade**: MEDIUM
- **Status**: open
- **Registrado em**: 2026-05-21
- **Solicitante**: `/qa debug` (operador)
- **Módulos afetados**: `app/Http/Livewire/Auth/`, `routes/web.php`, `resources/views/livewire/auth/`, `resources/views/emails/`, `config/auth.php`

### Descrição

A tela `/login` (`resources/views/livewire/auth/login.blade.php`) não oferece link "Esqueci minha senha". Verificado por `grep password.request|forgot|recuperar|esqueci`: nenhuma rota, Livewire component ou mailable de password reset existe no código. Apenas a senha trocada manualmente (via `/profile/password`) ou via convite (`AcceptInvite`) está implementada.

### Critério de aceite

- Adicionar rotas `password.request` (GET form), `password.email` (POST submit), `password.reset` (GET form com token), `password.update` (POST submit) — todas dentro do grupo `guest` em `routes/web.php`.
- Criar Livewire `Auth/ForgotPassword` (form com `email`) e `Auth/ResetPassword` (form com `email`, `password`, `password_confirmation`, `token`).
- Usar `Illuminate\Support\Facades\Password` (broker padrão sobre tabela `password_reset_tokens` + provider `operators` já configurado em `config/auth.php`).
- Mailable `OperatorPasswordResetMail` com URL assinada (template em `resources/views/emails/`).
- Link "Esqueci minha senha" em `login.blade.php` abaixo do botão "Entrar".
- Auditar via `AuditLog` (`action=password_reset_requested`, `action=password_reset_completed`).
- Rate-limit `password.email` (3 tentativas / 15 min por IP+email).

### Riscos / decisões

- **Enumeração de e-mail**: usar resposta genérica ("se o e-mail existir, enviaremos instruções") independentemente do resultado de `Password::sendResetLink()`.
- **Operadores `status != active`**: bloquear silenciosamente o envio (mesma resposta genérica), logar em audit como `password_reset_blocked`.

### Próximo passo

Aguardar `/fix` para gerar Sprint F com TDD (Pest Feature tests cobrindo happy path + rate limit + invalid token).

---

## ISSUE-009 — Logs de Job ausentes na tela `queue/{jobId}`

- **Tipo**: change_request
- **Prioridade**: HIGH
- **Status**: open
- **Registrado em**: 2026-05-21
- **Solicitante**: `/qa debug` (operador)
- **Módulos afetados**: `app/Modules/Jobs/Services/WebhookHandler.php`, `app/Modules/Core/Ssh/SshClient.php`, `app/Modules/Jobs/Services/` (novo), `app/Http/Livewire/Jobs/Show.php`

### Descrição

A view `resources/views/livewire/jobs/show.blade.php` renderiza `$logLines` a partir de `Job::$summary` (cast JSON). Confirmado:

- `app/Http/Livewire/Jobs/Show.php::parsedLogLines()` retorna `[]` quando `$job->summary` é vazio.
- `app/Modules/Jobs/Services/WebhookHandler.php` (callback `job.started`/`job.finished`) **nunca** atribui `summary`. Só toca `state`, `started_at`, `finished_at`, `exit_code`, `callback_received_at`.
- `app/Modules/Jobs/Dto/WebhookPayload::fromArray()` sequer lê campo `summary`/`log_tail`/`stdout` — o contrato upstream não envia logs no callback.

Resultado: 100% dos jobs exibem "Nenhum log disponível." em produção/staging.

### Design escolhido

**Pull SSH pós-`job.finished`**: após o `applyFinishedEvent()` persistir o estado terminal, executar via `SshClient` o comando `nextcloud-manage job <job_id> logs --json` no cluster do job, parsear `stdout` e persistir em `jobs.summary` (array JSON). Decisão tomada para evitar dependência de PR upstream e desacoplar logging do canal de callback.

### Critério de aceite

- Novo serviço `App\Modules\Jobs\Services\JobLogFetcher` injetando `SshClientInterface`:
  - método `fetch(Job $job, ClusterServer $cluster): array` retorna lista de linhas (sem nulls/vazios).
  - timeout configurável (`config('services.ssh.log_fetch_timeout_seconds', 15)`).
  - tolera comando ausente / exit_code != 0 → não falha o webhook, só loga em `Log::warning()` com `job_id` e `cluster_id`.
- `WebhookHandler::applyFinishedEvent()` chama `JobLogFetcher` dentro da transação **após** o `update()` do estado terminal, persistindo `summary`. Em estados não-terminais (`running`), não fetcha.
- Idempotência: se `summary` já estiver populado, pular fetch (proteção contra retry de webhook).
- Comando SSH alvo: `nextcloud-manage job <job_id> logs --json`. Esperar JSON array de strings ou objeto `{"lines": [...]}` (ajustar parser conforme contrato real do upstream — validar antes da implementação executando contra cluster `homolog`).
- Audit: incluir `log_lines_count` no payload da entry `webhook_received` em `applyFinishedEvent()`.
- Pest Feature test: webhook `job.finished` → `summary` populada com fixture do `SshClient` mockado.
- Pest Contract test (opt-in `RUN_UPSTREAM_CONTRACT=1`): comando SSH real em cluster `homolog` retorna formato esperado.

### Riscos / decisões

- **Custo SSH por job**: cada `job.finished` agora abre/usa conexão pooled. Latência adicional ~200-800ms — aceitável pois o callback já é assíncrono. Monitorar via `Log::info('job.log_fetch.duration_ms')`.
- **Falha do `nextcloud-manage job logs`**: NÃO deve marcar o job como `failed` — só falha o enriquecimento. View já tolera `summary` vazia.
- **Contrato upstream pendente**: se o subcomando `job <id> logs --json` não existir ainda no `nextcloud-manage`, abrir PR upstream em paralelo (acoplar a ISSUE-001/006).
- **Vazamento de secrets**: logs do upstream podem conter linhas com tokens/senhas. Aplicar sanitização similar a `payload_sanitized` antes de persistir (regex sobre `password=`, `token=`, `--password-stdin`).

### Próximo passo

Aguardar `/fix` para gerar Sprint F (TDD obrigatório, auditor diferente do implementador conforme Decision #119).
