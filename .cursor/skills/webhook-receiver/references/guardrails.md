# Guardrails — webhook-receiver

> Universal: `capabilities/guardrails.md`

## Iron Law

**NENHUM callback processado sem HMAC válido + cluster conhecido.** Resposta rápida (<500ms); processamento pesado em queue se necessário.

## Anti-rationalization

| Desculpa | Realidade |
|----------|-----------|
| "Confiar no IP basta, HMAC é redundante" | IP spoof/NAT quebra; HMAC é a garantia criptográfica |
| "Dedupe só no sucesso complica" | Dedupe em 401/422 mascarou bugs (ISSUE-003) |
| "Aceitar qualquer state evita 422" | `UnknownStateException` protege DB de poluição |
| "Logar payload completo em prod" | PII/secrets; usar audit sanitizado (ISSUE-015) |
| "job.started é opcional" | Worker upstream emite dois callbacks — ignorar quebra transição |

## Red flags

- Processar webhook sem `VerifyWebhookHmac` → PARE
- Retornar 500 para assinatura inválida (deve ser 401 + audit) → PARE
- Aceitar replay fora da janela (`WEBHOOK_REPLAY_WINDOW_SECONDS`) → PARE
- Promover job terminal → running (regressão out-of-order) → PARE
- Persistir dedupe key em resposta não-2xx → PARE

## Verification checklist (mudança no receiver)

- [ ] HMAC SHA-256 com secret ativo + grace history
- [ ] IP whitelist / cluster query param validado
- [ ] `event` ∈ {`job.started`, `job.finished`} quando presente
- [ ] Dedupe por `(job_id, event)` para started/finished
- [ ] `StateTranslator` para states upstream
- [ ] Testes: assinatura inválida, replay, duplicata, started+finished
- [ ] Resposta 204/200 conforme idempotência documentada

## Skill boundary

| Faz | Não faz |
|-----|---------|
| HMAC, replay, dedupe, handler | Disparar SSH (`ssh-orchestrator`) |
| Atualizar `Job` / `Customer` status | Traduzir argv (`vocabulary-translator`) |
| Audit `webhook_received` | Deploy (`me360-deployer`) |
