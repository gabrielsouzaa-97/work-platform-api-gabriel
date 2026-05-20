# Issues — mework360-deployer

> Fonte de verdade para enhancements, melhorias e change requests. Bugs e findings de segurança → docs/FINDINGS.md.

## Índice

| ID | Tipo | Título | Módulo | Prioridade | Status |
|----|------|--------|--------|------------|--------|
| ISSUE-001 | change_request | Per-job webhook_token na callback URL | Jobs, Core | HIGH | open |
| ISSUE-002 | postmortem | Webhook 401 — worker upstream não recarregou novo secret | ClusterServers, Webhook | HIGH | mitigated (upstream PR pendente) |
| ISSUE-003 | postmortem | Webhook 422 — vocabulário `finished` não mapeado + dedupe persistia em falha | Jobs, Core, Webhook | HIGH | fixed in API (upstream issue #15 aberta) |
| ISSUE-004 | change_request | Webhook receiver aceita `event=job.started` (callbacks de transição) + dedupe per `(job_id, event)` | Jobs, Core, Webhook | HIGH | implemented |

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
