# Crítica — Arquiteto (ETAPA 2)

## (1) Falha mais grave

O plano declara um ACL com fronteira de domínio, mas o `PlatformPort` proposto continua sendo um **catálogo de verbos NC disfarçados** (`setBranding`, `setQuota`, `enableApps`, `execOcc`, `probeReadiness`) — espelho 1:1 do `JobTypeTranslator` e do `nextcloud-manage`, não uma linguagem ubíqua de tenant lifecycle. O `execOcc` na interface do port **anula** a promessa de que só adapters conhecem OCC. O ACL vira fachada fina: a dor de acoplamento (~52 pontos) concentra-se num God Port, não desaparece. Conway: o mesmo monólito Laravel (módulos `Customers`, `Jobs`, `Agents`, `Core`) continuará evoluindo API v1, legado, `OccController` admin e adapters — três superfícies HTTP em paralelo sem fronteira organizacional que permita cadências independentes.

## (2) Premissa frágil

“O contrato externo não tem dependência de transporte” só se sustenta no transporte (SSH vs Agent). Semanticamente, as capabilities v1 ainda **herdam o grafo de dependências do upstream** (allowlist D-02, steps `db_created → containers_up → ready`). A saga replica esse vocabulário; trocar adapter na Fase 2 não desacopla o modelo de negócio do protocolo NC.

## (3) Melhoria concreta

Separar bounded contexts no código: módulo **Integration** (adapters + tradução argv/envelope, sem HTTP) com contrato interno orientado a **comandos de integração tipados** (`Tenant.Create`, `Branding.Apply`) e módulo **TenantLifecycle** com linguagem publicada para `/api/v1`. Remover `execOcc` do port; válvula admin chama adapter de integração diretamente. Long-running ops publicam eventos de domínio (`TenantProvisioningStarted`) em vez de `JobRef` genérico em todo método — o contexto Job deixa de ser dependência transversal.

## (4) Severidade

**Alta** — a topologia resolve vazamento HTTP e drift de spec, mas **move** o acoplamento NC para dentro do port sem redesenhar limites de domínio; risco alto de God Port + manutenção tripla (legado/v1/admin) por anos.
