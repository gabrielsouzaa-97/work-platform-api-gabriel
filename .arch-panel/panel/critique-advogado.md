### Crítica — Advogado do diabo / pragmatista

**(1) Falha mais grave:** o plano empilha **cinco fases** (port + DomainError + `/api/v1` + despublicar `/occ/*` + saga) como se fossem independentes, mas o valor de negócio (onboarding-api, WHMCS) só chega na **Fase D/E** — meses depois. Enquanto isso, **D-02** (allowlist upstream) continua bloqueando branding/quota: a v1 “estável” ainda expõe capabilities que falham no `work-platform-scripts`. O risco de prazo estourado é maior que o de drift OpenAPI.

**(2) Premissa frágil:** que consolidar “~52 chamadas” num `PlatformPort` é pré-requisito *antes* de provar valor. No código real são **~15 módulos** (`ProvisionCustomerAction`, `OccPassthroughService`, `CustomerReadinessProbe`, `AgentUpstreamGateway`…) com semânticas distintas (sync, async, occ, probe). A Fase A promete “zero mudança de asserts” ao mover `JobTypeTranslator` + `AgentTransportResolver` — refactor de alto risco em caminho crítico de provisionamento, não um extract-method seguro.

**(3) Melhoria concreta (mais barata e reversível):** **Sprint 0 (2 semanas)** — (a) sanitizar erros na borda atual (`DomainError` no exception handler, sem `subcmd`/`exit_code`); (b) congelar `/occ/*` fora do spec público; (c) corrigir DOC-001 alinhando spec ao código; (d) rotas `/v1/*` como **aliases finos** sobre `LifecycleAsyncAction`/`ProvisionCustomerAction` existentes. Medir migração de tráfego; só então extrair `PlatformPort` provado por **uma** capability (branding). Saga `/v1/onboarding` fica atrás de D-02 resolvido.

**(4) Severidade:** **alta** — over-engineering em port + duplo spec + Scramble/CI + saga antes de desbloquear integrações reais.
