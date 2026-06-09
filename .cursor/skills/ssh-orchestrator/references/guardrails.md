# Guardrails — ssh-orchestrator

> Universal: `capabilities/guardrails.md`

## Iron Law

**NENHUMA senha ou payload sensível em argv.** Stdin via `--payload-stdin` sempre. **NUNCA** SSH real em testes automatizados.

## Anti-rationalization

| Desculpa | Realidade |
|----------|-----------|
| "Senha curta no argv é mais rápido" | Aparece em logs, audit, `ps` no upstream — violação de segurança |
| "Teste de integração precisa SSH real" | Usar `UpstreamContractTest` opt-in; suite CI mocka interface |
| "Retry em qualquer exit != 0" | Exit de negócio (3, 4, 16) não deve retentar — só `ConnectionException` |
| "Duplicar --async por garantia" | JSON envelope quebra no upstream (ISSUE-006) |
| "Escapeshellarg basta para branding com espaço" | Hop ForceCommand exige quoting correto (ISSUE-017) |

## Red flags

- `runAsync` sem idempotency key persistida antes → PARE
- `$payloadStdin` em `Log::debug` → PARE (pode conter senha Nextcloud)
- Chave SSH escrita em disco temporário → PARE (usar string em memória phpseclib)
- Teste Feature sem mock de `SshClientInterface` → PARE
- `cmd_canonical` no argv sem `cmdToCliArgv()` → PARE (`vocabulary-translator`)

## Verification checklist (chamada SSH mutável)

- [ ] Idempotency UUID v4 gravado antes do SSH
- [ ] Payload sensível só em stdin JSON
- [ ] `runAsync` não duplica `--async`/`--json`
- [ ] Exit codes mapeados (`references/error-handling.md` quando existir)
- [ ] `ClusterUnreachableException` se cluster inactive
- [ ] Testes unitários mockam interface

## Skill boundary

| Faz | Não faz |
|-----|---------|
| Idempotency, stdin, timeouts, retry | Mapeamento cmd↔argv (`vocabulary-translator`) |
| Interface `SshClient` | Validar webhook entrada (`webhook-receiver`) |
| Polling fallback | Deploy VM (`me360-deployer`) |
