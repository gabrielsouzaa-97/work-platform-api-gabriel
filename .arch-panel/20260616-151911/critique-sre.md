**Nota:** o bloco `PLANO v0` veio vazio (`v0.md` também). A crítica abaixo usa o objetivo em [`tests/arch-objetivo.md`](tests/arch-objetivo.md) e o que o código/plano V2 já expõem.

---

### Crítica SRE/Operações

**(1) Falha mais grave ignorada:** a saga `POST /v1/onboarding` (tenant → admin → apps → branding) empilha jobs async finalizados por webhook HMAC (`/api/jobs/hook`) sobre SSH *e* Farm Agent, sem **reconciliação operacional**. Webhook perdido, atrasado, duplicado ou cluster com `webhook_allowed_ip` errado deixa saga/customer preso em `provisioning_finishing` — sem sweeper, DLQ, deadline/`deadline_at` enforced nem runbook de on-call. O plano fala em compensação, mas não em **como detectar e destravar** estado inconsistente em produção.

**(2) Premissa frágil:** “incremental sem big-bang” com `PlatformPort` dual (`SshPlatformAdapter` + `AgentPlatformAdapter`) assume coexistência segura, mas não define **ordering de deploy** (API vs `work-platform-scripts` vs agent por ring), **feature flag por cluster** nem **rollback** quando metade dos tenants migra de transporte. [`docs/PLATFORM-V2-PLAN.md`](docs/PLATFORM-V2-PLAN.md) já marca observabilidade 5/10 e opt-in — prometer onboarding composto antes de tracing/métricas cross-boundary (API → port → upstream → webhook) é operacionalmente otimista.

**(3) Melhoria concreta:** antes da saga pública, entregar **JobReconciliationWorker** (poll upstream/agent por `job_id`/`operation_id` stuck > N min), métricas + alertas (`job_stuck_seconds`, `webhook_delivery_lag`, `saga_step_duration_p99`), `correlation_id` end-to-end, e gate de deploy por anel com kill-switch que desliga onboarding sem derrubar `/v1` read-only.

**(4) Severidade:** **alta** — risco de tenants órfãos, fila de suporte e rollback impossível sob falha parcial de webhook/transporte.
