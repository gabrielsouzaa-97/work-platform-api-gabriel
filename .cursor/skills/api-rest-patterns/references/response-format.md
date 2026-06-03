# Formato de Resposta

Código é a verdade — `docs/openapi.yaml` pode documentar `{ success, data }`; o código **não** usa.

## Erros (exceto Webhook)

```json
{ "error": "machine_code_snake_case" }
```

Exemplos com campos extras:

```json
{ "error": "idempotency_conflict", "existing_job_id": "uuid" }
{ "error": "state_conflict", "diff": {} }
{ "error": "upstream_error", "exit_code": 3 }
{ "error": "tenant_not_ready", "status": "provisioning" }
{ "error": "not_implemented_yet", "cmd": "groups:add", "reason": "..." }
{ "error": "occ_subcmd_not_allowed", "subcmd": "...", "exit_code": 16, "detail": "..." }
```

Validação `FormRequest` falha → formato padrão Laravel (`message` + `errors`), não o envelope acima.

Tabela completa de codes → [error-codes.md](error-codes.md).

## Sucesso

**JsonResource** (estruturado):

```php
return new CustomerResource($customer);
return JobResource::collection($paginated);
```

**JSON manual**:

```php
return response()->json(['job_id' => $job->job_id], 202);
return response()->json($result);              // OCC passthrough
return response()->noContent();                // 204 cancel / webhook
```

## Mapeamento rápido exceção → HTTP

| Exceção | HTTP | `error` |
|---------|------|---------|
| `IdempotencyConflictException` | 409 | `idempotency_conflict` |
| `StateConflictException` | 409 | `state_conflict` |
| `ClusterUnreachableException` | 503 | `cluster_unreachable` + `Retry-After: 60` |
| `TenantNotReadyException` | 503 | `tenant_not_ready` + `Retry-After` |
| `SshTimeoutException` | 504 | `occ_timeout` / `lifecycle_timeout` |
| `SshRemoteException` exit 16 | 403 | `occ_subcmd_not_allowed` |
| `SshRemoteException` exit 4 | 409 | `already_exists` |
| `SshRemoteException` outros | 502 | `upstream_error` |
| `BlockedOnUpstreamException` | 501 | `not_implemented_yet` |

Helper compartilhado e ordem de `catch` → [controller-patterns.md](controller-patterns.md).
