# Checklist: Adicionar Novo Endpoint

Copie e preencha este checklist a cada novo endpoint. Não pule etapas.

```
Endpoint: [METHOD] /api/...
Modo: [ ] Async lifecycle  [ ] Sync OCC  [ ] Provision/Remove  [ ] Read-only
Data: ___________

## Checklist

### 1. Rota
- [ ] Adicionada em `routes/api.php` no grupo middleware correto
- [ ] Nome da rota definido (ex: `api.lifecycle.users.create`)
- [ ] Método HTTP semântico (POST criar, DELETE remover, PUT substituir, GET ler)

### 2. FormRequest
- [ ] Criado em `app/Http/Requests/[Grupo]/NomeRequest.php`
- [ ] `declare(strict_types=1)`
- [ ] `authorize()` com check de role (`admin`, `operador` ou `suporte`)
- [ ] `rules()` com tipos explícitos e regex corretos para o domínio
- [ ] `prepareForValidation()` adicionado se há aliases legados
- [ ] `messages()` com mensagens PT-BR para regras com regex

### 3. DTO (se payload complexo)
- [ ] Criado em `app/Modules/{Modulo}/Dto/NomePayload.php`
- [ ] Classe `readonly` com `final`
- [ ] Factory method estático `fromRequest(Request $request): self`
- [ ] Nenhum dado sensível exposto publicamente sem necessidade

### 4. Action ou Service
- [ ] **Async** → Action em `app/Modules/{Modulo}/Actions/NomeAction.php`
- [ ] **Sync** → Service existente (`OccPassthroughService`) ou novo em `app/Modules/{Modulo}/Services/`
- [ ] Action/Service não conhece HTTP — lança exceção de domínio, não JsonResponse
- [ ] Idempotency key gerada e persistida na Action (se mutável)

### 5. Exceções de Domínio
- [ ] Exceções específicas do caso criadas em `app/Modules/{Modulo}/Exceptions/`
- [ ] Exceções estendem `\RuntimeException` ou `\DomainException` (nunca `\Exception` genérica)
- [ ] Reutilizadas exceções existentes quando o caso de erro já existe

### 6. Controller
- [ ] `declare(strict_types=1)` + `final class`
- [ ] Magro: apenas orquestra FormRequest → DTO → Action/Service → resposta
- [ ] Cada exceção de domínio mapeada para error code + HTTP status correto
- [ ] Exceções compartilhadas extraídas em helper privado (`mapXxxException`)
- [ ] Validação de path params feita inline antes do dispatch (regex + length)
- [ ] Resposta de sucesso no status correto para o modo (201/202/200/204)
- [ ] Nenhum stack trace exposto

### 7. Resource (se resposta estruturada)
- [ ] Criado em `app/Http/Resources/NomeResource.php`
- [ ] `declare(strict_types=1)` + `final class extends JsonResource`
- [ ] Campos em snake_case, datas em ISO 8601 (`->toIso8601String()`)
- [ ] Sem campos sensíveis expostos

### 8. Audit Log (se operação mutável)
- [ ] OCC sync → `AuditLog::create()` no controller após sucesso
- [ ] Lifecycle/Provision async → `AuditLog::create()` na Action
- [ ] `action` em snake_case descritivo (ex: `occ_set_quota`, `user_created`)
- [ ] `payload` sanitizado (sem senhas, tokens, dados sensíveis)

### 9. Testes Pest
- [ ] Feature test em `tests/Feature/Api/` ou `tests/Feature/{Modulo}/`
- [ ] Cobre: happy path, 422 (validação), 409 (conflito), 503 (cluster), 502 (SSH)
- [ ] Mock de `SshClientInterface` — nunca SSH real em testes
- [ ] Assert de side effects: DB, AuditLog, Job criado
- [ ] Contract test em `tests/Contract/` se endpoint tem contrato com upstream

### 10. OpenAPI / Scramble
- [ ] Endpoint adicionado em `docs/openapi.yaml`
- [ ] Todos os error codes documentados com exemplos de resposta
- [ ] Rodar `php artisan scramble:export` e conferir divergências
```

---

## Grupo de Middleware Correto

| Tipo de endpoint | Middleware |
|-----------------|------------|
| Webhook receiver | `VerifyWebhookHmac::class` (sem auth de operador) |
| Todos os outros | `['auth:web,api-key', 'active.operator', 'throttle:120,1']` |

## Localização dos arquivos por grupo

| Grupo | Pasta FormRequest | Controller |
|-------|-------------------|------------|
| Customers (provision/remove) | `app/Http/Requests/` | `CustomerController` |
| Lifecycle (users/groups/apps) | `app/Http/Requests/Lifecycle/` | `CustomerLifecycleController` |
| OCC passthrough | `app/Http/Requests/Occ/` | `OccController` |
| Jobs (queue) | — (validação inline) | `JobController` |
| Webhook | — (middleware faz tudo) | `WebhookController` |

## Status HTTP de sucesso por modo

| Modo | Status | Body |
|------|--------|------|
| Provision (cria recurso persistido) | 201 | `CustomerResource` |
| Async job enfileirado | 202 | `{ "job_id": "uuid" }` |
| OCC sync passthrough | 200 | JSON do upstream |
| Read-only (lista/detalhe) | 200 | `JobResource` ou `JobResource::collection()` |
| Cancel / Webhook | 204 | vazio |
| Opções estáticas | 200 | `{ "options": [...] }` |

## Dicas para cada modo

### Async Lifecycle

1. O controller valida path params inline se não há FormRequest
2. Chama `LifecycleAsyncAction::execute($customer, $cmd, $args, $stdinPayload, $actor)`
3. Captura exceções na ordem: `TenantNotReadyException`, `BlockedOnUpstreamException`, `SshRemoteException` (por exit code), `\Throwable` → `mapLifecycleException()`
4. Retorna `202 { job_id }`

### Sync OCC

1. Toda lógica de execução e mapeamento fica em `runOccExec()`
2. Audit log é escrito DENTRO de `runOccExec()` após sucesso
3. Exit code 16 → sempre 403 `occ_subcmd_not_allowed` (não tentar workaround)
4. Exit code 1 → 404 `not_found`

### Provision / Remove

1. Buscar ghost customer (soft-deleted) antes de criar payload: suporte a re-provision
2. DTO recebe o ghost para resolver branding paths existentes
3. `StorageConflict` diferente de `IdempotencyConflict`: state_conflict tem `diff`, idempotency tem `existing_job_id`
