# Error Codes Reference

Tabela canônica de todos os códigos de erro usados na API. O campo `error` é sempre
snake_case e representa a causa de negócio — não o status HTTP.

## Erros de Conflito (409)

| `error` | HTTP | Exceção de origem | Campos extras | Quando usar |
|---------|------|-------------------|---------------|-------------|
| `idempotency_conflict` | 409 | `IdempotencyConflictException` | `existing_job_id` | Job idêntico já existe (mesmo `args_hash`). O cliente pode fazer polling com o `existing_job_id`. |
| `state_conflict` | 409 | `StateConflictException` | `diff` | Customer está em estado incompatível com a operação solicitada (ex: provisioning em andamento). |
| `already_in_progress` | 409 | `RemoveInProgressException` | — | Remoção do customer já está em andamento ou foi completada. |
| `already_exists` | 409 | `SshRemoteException` (exit 4) | — | Recurso já existe no upstream (ex: usuário, grupo). |

## Erros de Validação (422)

| `error` | HTTP | Origem | Campos extras | Quando usar |
|---------|------|--------|---------------|-------------|
| `confirm_slug_mismatch` | 422 | `ConfirmationMismatchException` | — | O campo `confirm_slug` não bate com o slug do customer. |
| `invalid_username` | 422 | Validação inline no controller | — | Path param `{username}` falha no regex `^[a-zA-Z0-9._-]+$` ou > 64 chars. |
| `invalid_group_name` | 422 | Validação inline no controller | — | Path param `{group}` falha no regex `^[a-zA-Z0-9._\- ]+$` ou > 256 chars. |
| `invalid_app_id` | 422 | Validação inline no controller | — | Path param `{appId}` falha no regex `^[a-z0-9_]+$`. |
| `validation_failed` | 422 | `SshRemoteException` (exit 22) | `message` | Upstream rejeitou dados (ex: senha não atende requisitos do Nextcloud). |
| `invalid_state` | 422 | `\DomainException` (cancel job) | `message` | Job não pode ser cancelado no estado atual. |
| `occ_bulk_not_supported` | 501 | Validação inline no controller | `detail` | Operação bulk sem username não é suportada (ex: `files:scan --all`). |
| — (formato Laravel) | 422 | `FormRequest` falha | `message`, `errors` | Campos do body não passam nas regras do FormRequest. |

## Erros de Gateway/Upstream (502/503/504)

| `error` | HTTP | Exceção de origem | Campos extras | Headers | Quando usar |
|---------|------|-------------------|---------------|---------|-------------|
| `cluster_unreachable` | 503 | `ClusterUnreachableException` | — | `Retry-After: 60` | Cluster inativo ou SSH não abre conexão. |
| `tenant_not_ready` | 503 | `TenantNotReadyException` | `status` | `Retry-After: {n}` | Customer ainda em estado que não permite lifecycle (ex: `provisioning`). |
| `upstream_error` | 502 | `SshRemoteException` (exit genérico) | `exit_code` | — | Upstream executou mas retornou erro não mapeado. |
| `invalid_upstream_response` | 502 | `\RuntimeException` (OCC) | `message` | — | Resposta do upstream não é JSON válido ou tem formato inesperado. |
| `occ_timeout` | 504 | `SshTimeoutException` (OCC) | — | — | Timeout na chamada OCC síncrona (> 60s). |
| `lifecycle_timeout` | 504 | `SshTimeoutException` (lifecycle) | — | — | Timeout em chamada async (raro — SSH `--async` retorna rápido). |

## Erros de Autorização e Não-Permitido (403/501)

| `error` | HTTP | Origem | Campos extras | Quando usar |
|---------|------|--------|---------------|-------------|
| `occ_subcmd_not_allowed` | 403 | `SshRemoteException` (exit 16) | `subcmd`, `exit_code: 16`, `detail` | Subcmd OCC está fora da allowlist do `nextcloud-saas-manager occ-exec`. Não é erro transitório — é restrição permanente do upstream. |
| `not_implemented_yet` | 501 | `BlockedOnUpstreamException` | `cmd`, `reason` | Verb existe na API mas o equivalente upstream ainda não foi implementado nos deployer-scripts (ex: `groups:add`, `groups:remove`). |
| `occ_subcmd_not_supported` | 501 | Lógico no controller | `detail` | Operação OCC que nunca será suportada pela arquitetura atual (ex: `user:setting --all`). |

## Erros de Rota (404/405)

Gerados pelo `bootstrap/app.php` para qualquer rota `/api/*`:

| `error` | HTTP | Campos extras |
|---------|------|---------------|
| `route_not_found` | 404 | `path`, `method` |
| `method_not_allowed` | 405 | `path`, `method` |

Exemplo de resposta:
```json
{
    "error": "route_not_found",
    "path": "/api/customers/foo/baz",
    "method": "POST"
}
```

## Erros de Recurso Não Encontrado (404)

| `error` | HTTP | Origem | Quando usar |
|---------|------|--------|-------------|
| `not_found` | 404 | `SshRemoteException` (exit 1) | Recurso não existe no upstream (ex: usuário a ser deletado). |
| — (ModelNotFoundException) | 404 | `findOrFail()` + handler Laravel | Job ID não existe em `GET /queue/{id}`. |

## Guia rápido: qual `error` usar?

```
Conflito de negócio repetível?     → 409  (idempotency_conflict, state_conflict)
Dados inválidos do cliente?        → 422  (validation_*, invalid_*, confirm_*)
Cluster/SSH fora do ar?            → 503  (cluster_unreachable, tenant_not_ready)
SSH retornou erro não mapeado?     → 502  (upstream_error)
SSH demorou demais?                → 504  (occ_timeout, lifecycle_timeout)
Upstream bloqueou o subcmd?        → 403  (occ_subcmd_not_allowed — exit 16)
Feature pendente no deployer?      → 501  (not_implemented_yet)
Rota não existe?                   → 404  (route_not_found via bootstrap)
```

## Headers especiais

| Header | Quando incluir |
|--------|----------------|
| `Retry-After: 60` | Sempre em `cluster_unreachable` (503) |
| `Retry-After: {n}` | Sempre em `tenant_not_ready` (503), usando `$e->retryAfterSeconds` |
