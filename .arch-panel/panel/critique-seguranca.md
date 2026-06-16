# Crítica — Segurança e Compliance (ETAPA 2)

## Falha mais grave ignorada

O plano trata segurança como **higiene de resposta** (esconder `subcmd`/`exit_code`) e despublicar `/occ/*`, mas **não define authn/authz para `/api/v1`**. Hoje, `routes/api.php` exige `auth:web,api-key` + `active.operator`; o guard `api-key` em `AppServiceProvider` autentica como **Operator inteiro** e **ignora `ApiKey.scopes`** (coluna existe, nunca é checada). Qualquer chave válida acessa **todos** os tenants — só `DELETE /customers` usa `can:provision-customers`. Abrir v1 para WHMCS/onboarding-api/parceiros sem **credenciais com escopo por tenant/capability** é IDOR sistêmico e violação LGPD (acesso transversal a dados de clientes). O `OnboardingSaga` com hash de `OnboardingSpec` (e-mail admin, branding) amplifica retenção de PII sem política de minimização nem trilha de auditoria por integrador.

## Premissa que não se sustenta

**"`/occ/*` rebaixado a admin/interno"** — na malha atual, API key de integração **não** é superfície admin; é o mesmo bearer que já provisiona e passa OCC. Manter rotas legadas "intactas" durante a migração **dobra** a superfície de ataque sem controles novos; `Deprecation`/`Sunset` não reduzem risco.

## Melhoria concreta (uma)

Introduzir **middleware dedicado da v1** (`VerifyExternalPrincipal`) com credenciais de serviço: escopos obrigatórios (`tenants:write`, `onboarding:run`) + **binding explícito tenant↔principal** (allowlist de slugs ou claim `tenant_id`), checado em **todo** ` /v1/tenants/{slug}/*` e `GET /v1/onboarding/{id}` **antes** do `PlatformPort`; registrar `actor_id`/`api_key_id`/`integrator` no `AuditLog` por capability. Critério de sucesso da Fase C: teste negativo provando que chave do parceiro A retorna `403` em tenant B.

## Severidade

**Alta**
