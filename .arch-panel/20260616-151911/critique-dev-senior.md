**Nota:** o `PLANO v0` veio vazio; a crítica usa [`tests/arch-objetivo.md`](tests/arch-objetivo.md) e [`docs/PLATFORM-V2-PLAN.md`](docs/PLATFORM-V2-PLAN.md).

---

**(1) Falha mais grave:** o plano empilha **três migrações estruturais em paralelo** — `PlatformPort` (consolidar ~52 chamadas OCC/SSH), contrato externo `/v1` com spec novo + CI de contrato, e saga `POST /v1/onboarding` — sem estimar o **custo de transição do código existente**. Hoje o time já navega `OccController`, `LifecycleAsyncAction`, `AgentTransportResolver` e drift OpenAPI (DOC-001). Adicionar `ExternalController → ApplicationService → PlatformPort → Adapter` antes de provar valor move o gargalo de “contrato quebrado” para “camada intermediária que ninguém sabe onde termina”: cada bug de provisionamento exige debug em 4 camadas + webhook + transporte dual.

**(2) Premissa frágil:** que consolidar as 52 chamadas é **incremental**. Na prática é refatoração transversal (Customers, Jobs, ClusterServers, Livewire, testes Pest) com feature freeze implícito. `JobTypeTranslator` e `AgentUpstreamGateway` já são embriões do port; o bloqueio real é **erro de domínio + allowlist D-02**, não falta de interface.

**(3) Melhoria concreta:** **Fase 0 (2 sprints, 1 módulo)** — (a) tirar `/occ/*` do OpenAPI público; (b) corrigir DOC-001 com contract tests nas rotas *existentes*; (c) extrair `CustomersPlatformAdapter` só para provision/remove/branding; (d) uma capability composta mínima (`POST /v1/branding` async), não saga completa. Medir: zero `occ_subcmd`/`exit_code` em respostas externas.

**(4) Severidade:** **alta** — risco de meses de refactor sem entrega comercial (N22) e DX degradada por dual-stack permanente.
