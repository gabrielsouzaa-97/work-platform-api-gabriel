**Nota:** o `PLANO v0` veio vazio; critico o objetivo em [`tests/arch-objetivo.md`](tests/arch-objetivo.md).

---

### Crítica — Advogado do diabo / pragmatista

**(1) Falha mais grave:** a premissa-raiz — “precisamos de ACL formal + `PlatformPort` + dois OpenAPI + saga `/v1/onboarding`” — trata **sintoma de contrato** (DOC-001, `occ_subcmd_not_allowed` vazando) como **refatoração estrutural**. O custo real explode em três frentes paralelas (spec externo, consolidação das ~52 chamadas, onboarding composto) enquanto D-02 (allowlist upstream) continua bloqueando branding/quota: o externo “estável” ainda prometerá capabilities indisponíveis.

**(2) Premissa frágil:** que o desacoplamento exige `PlatformPort` *antes* da prova de valor. Já existem `JobTypeTranslator`, `AgentTransportResolver`/`AgentUpstreamGateway` (N19) e actions async — o gargalo não é falta de interface, é **mapeamento de erro + drift OpenAPI + `/occ/*` público**.

**(3) Melhoria concreta (mais barata e reversível):** **Fase 0 de 2 semanas** — (a) tirar `/occ/*` do spec público e restringir a admin; (b) normalizar erros externos (`tenant_not_ready`, sem `exit_code`); (c) alinhar `openapi.yaml` ao código (DOC-001); (d) alias `/v1/*` sobre rotas lifecycle existentes. Só então decidir se `PlatformPort` paga o juro — prova mínima: **uma** capability (ex. branding), não saga completa.

**(4) Severidade:** **alta** — risco de meses em camadas novas sem entregar integração WHMCS/onboarding; over-engineering duplo-spec + saga como “prova do modelo”.
