# Guardrails — vocabulary-translator

> Universal: `capabilities/guardrails.md`

## Iron Law

**TRÊS vocabulários, UM tradutor (`JobTypeTranslator`).** Novo verb = atualizar `CMD_TO_JOB_TYPE`, `JOB_TYPE_TO_CMD` e `CMD_TO_CLI_ARGV` na mesma alteração.

## Anti-rationalization

| Desculpa | Realidade |
|----------|-----------|
| "Upstream aceita users:create direto" | Causa-raiz ISSUE-006 — argv canônico ≠ CLI |
| "Só preciso mapear job_type, argv depois" | Drift silencioso até produção |
| "Normalizar slug com underscore" | Rejeitar 422 — contrato restrito |
| "Blocked verb retorna 500 temporário" | Sempre 501 + `BlockedOnUpstreamException` |
| "State finished e done são iguais" | `StateTranslator` mapeia ambos; não inventar state no DB |

## Red flags

- `cmd_canonical` passado direto ao `SshClient` → PARE
- Atualizar só 1 das 3 constantes → PARE
- `--async` duplicado no argv do caller → PARE
- Novo verb sem teste roundtrip unitário → PARE
- Slug inválido "corrigido" no service → PARE

## Verification checklist (novo verb ou state)

- [ ] Entrada nas 3 constantes `JobTypeTranslator`
- [ ] `StateTranslator` se novo state upstream
- [ ] Testes unitários: roundtrip + unknown + blocked
- [ ] `UpstreamContractTest` opt-in se argv mudou
- [ ] `docs/openapi.yaml` se expõe novo cmd
- [ ] Decision brief se breaking (ARCH-4 pattern)

## Skill boundary

| Faz | Não faz |
|-----|---------|
| Slug, cmd, job_type, argv, state | Executar SSH (`ssh-orchestrator`) |
| `BlockedOnUpstreamException` | HTTP mapping (`api-rest-patterns`) |
| Contrato bidirecional cmd↔job_type | Validar HMAC webhook (`webhook-receiver`) |
