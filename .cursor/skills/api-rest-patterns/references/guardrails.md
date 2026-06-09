# Guardrails — api-rest-patterns

> Universal: `capabilities/guardrails.md`

## Iron Law

**NENHUM endpoint novo sem FormRequest + mapeamento exceção→HTTP + teste de feature.** Controller fino; domínio na Action.

## Anti-rationalization

| Desculpa | Realidade |
|----------|-----------|
| "É só um JSON simples, sem FormRequest" | Validação inconsistente vira 500 ou mass assignment |
| "Envelope `{ success, data }` é mais claro" | Contrato do projeto é `{ error }` / Resource — OpenAPI diverge (ISSUE-021) |
| "502 para tudo do SSH" | Exit 16 OCC → **403**; blocked upstream → **501** |
| "Controller com 20 linhas de lógica é ok" | Viola camada; impede teste unitário da Action |
| "OpenAPI depois" | Consumidores externos e QA usam `docs/openapi.yaml` como contrato |

## Red flags

- `any` ou `mixed` em controller/DTO → PARE
- Stack trace ou `$e->getMessage()` na resposta JSON → PARE
- `BlockedOnUpstreamException` mapeado para 500 → PARE. Usar 501
- Path param sem regex/length antes do dispatch → PARE
- Audit log com senha/token → PARE
- Novo endpoint sem entrada em `error-codes.md` → PARE

## Verification checklist (novo endpoint)

- [ ] Rota em `routes/api.php` com middleware/throttle corretos
- [ ] `FormRequest` ou validação inline documentada
- [ ] Controller `final` + `strict_types`
- [ ] Exceções mapeadas (tabela `references/error-codes.md`)
- [ ] Teste feature (happy + erro principal)
- [ ] `docs/openapi.yaml` atualizado se rota pública
- [ ] Sem lógica de SSH no controller (delegar Action + `ssh-orchestrator`)

## Skill boundary

| Faz | Não faz |
|-----|---------|
| Padrões REST, JSON, HTTP | SSH argv (`ssh-orchestrator`) |
| FormRequest, Resource, throttle | Webhook HMAC (`webhook-receiver`) |
| Mapeamento exceção→status | Tradução cmd (`vocabulary-translator`) |
| Deploy/smoke (`me360-deployer`) | — |
