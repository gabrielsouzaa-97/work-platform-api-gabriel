O corpo do “PLANO v0” veio vazio; a crítica abaixo usa [`docs/PLATFORM-V2-PLAN.md`](docs/PLATFORM-V2-PLAN.md) como referência.

---

**1. Falha mais grave:** o fluxo comercial (§2.5) é uma **saga distribuída** (WHMCS/Vindi → control plane → placement → PowerDNS → mail-api → agent → readiness R1–R8) **sem dono de consistência nem compensação**. O plano não menciona saga, outbox ou rollback parcial. Falha após `work-mail-api` ou `dns.zone.provision` deixa tenant, DNS e cobrança dessincronizados — troca SSH por risco operacional maior.

**2. Premissa frágil:** “outbound-only + OccAdapter único” **não desacopla domínios**. Na Fase 1 o agente continua sendo fachada do `manage.sh`; o `work-platform-api` concentra farm, mail, DNS, placement e billing num **hub monolítico**. Conway: 6+ repos (N17–N29) exigem coordenação que um operador `/rock` não substitui — o gargalo de mudança migra para o control plane.

**3. Melhoria concreta:** extrair bounded context **Provisioning** com agregado `ProvisioningOrder` (estado + idempotência ponta a ponta), fila/outbox própria e compensações explícitas; `work-platform-api` expõe API e consome eventos — **não** orquestra HTTP síncrono em cadeia no signup.

**Severidade:** **alta** — o plano resolve segurança de transporte (SSH inbound) mas **move** a complexidade para orquestração comercial multi-sistema sem fronteira de consistência definida.
