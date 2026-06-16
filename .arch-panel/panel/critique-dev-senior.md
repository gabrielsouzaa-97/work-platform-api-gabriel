# Crítica — Desenvolvedor Sênior (ETAPA 2)

## (1) Falha/risco mais grave ignorado

A **Fase A** é tratada como refactor puro de baixo risco, mas o grep gate (`SshClientInterface`/`AgentUpstreamGateway` só em adapters) obriga refatorar **muito além** das quatro Actions citadas: `JobLogFetcher`, `CancelJobAction`, `CustomerSyncService`, comandos Artisan (`JobsPollStuckCommand`, `ClusterHealthCheckCommand`), Livewire (`OccPanel`, `ClusterServers\Index`) e testes de contrato upstream. São superfícies com semânticas distintas (sync OCC vs async job vs polling de logs). Consolidar tudo num `PlatformPort` único sem alterar comportamento exige **characterization tests** que o repositório não tem para todos esses caminhos — o risco de regressão silenciosa em produção é real, não mitigado por "100% dos testes verdes sem mudar asserts".

## (2) Premissa que não se sustenta

A Fase A promete cair de "~52 pontos" para **1 adapter** mantendo paridade total. No código, `ProvisionCustomerAction` (L143-162) embute regra de negócio de transporte (`stagingId === null` força SSH). Mover isso para factory/adapter **muda o contrato implícito** entre camadas; não é extração mecânica. Além disso, `execOcc(OccCommand)` no próprio `PlatformPort` contradiz "capabilities de domínio, nunca subcmd OCC" — o ACL vaza pelo método de escape que o plano institucionaliza.

## (3) Melhoria concreta

**Strangler incremental:** extrair primeiro um `PlatformPort` mínimo com 3-4 métodos já usados em produção (`createTenant`, `probeReadiness`, `setBranding`, `enableApps`), migrar só `ProvisionCustomerAction` + `LifecycleAsyncAction`, e **adiar** `OnboardingSaga` (Fase E) até métricas de adoção da v1. Incluir Livewire/console no roteiro explicitamente, não só no grep gate.

## (4) Severidade

**Alta** — subestimação de escopo da Fase A + saga async sem modelo de persistência/orquestração detalhado compromete prazo, DX (dois mundos legado/v1 indefinidamente) e confiança do time no refactor.
