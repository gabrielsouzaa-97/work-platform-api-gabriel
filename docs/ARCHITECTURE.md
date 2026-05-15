# Arquitetura — mework360-deployer

> Gerado em: 2026-05-07
> Atualizado em: 2026-05-14
> Fase: 3 — Arquitetura de Solução
> Status: Aprovada
> Baseado em: docs/REQUIREMENTS.md

---

## 1. Visão Geral

**Tipo**: Monolito Modular (Modular Monolith)
**Justificativa**: O sistema orquestra múltiplas responsabilidades (Auth, Customers, Jobs, Cluster Servers, Audit). Separar em módulos lógicos dentro do Laravel mantém o código organizado sem a complexidade de microserviços, facilitando a manutenção e isolando as regras de negócio. O projeto não exige escala independente por componente no MVP.

---

## 2. Stack Tecnológica

| Camada | Tecnologia | Versão | Justificativa |
|--------|-----------|--------|---------------|
| Frontend | Laravel Livewire + Tailwind CSS | 3.x / 3.x | Decisão do design system para manter fidelidade total ao protótipo Stitch (sem Filament). Permite reatividade sem a complexidade de uma SPA separada. |
| Backend | Laravel | 12.x | Framework robusto, excelente para APIs REST e já escolhido como perfil base. |
| Banco de dados | MariaDB | 11.x | UUIDs nativos via `UUID()`, tipo `JSON`, índices `FULLTEXT` para buscas textuais. Migrado de PostgreSQL 16 em 2026-05-14 (E10). |
| ORM | Eloquent | - | Padrão do Laravel, com suporte excelente a migrations e factories. |
| Cache / Sessão | Redis | 7.x | Rápido, ideal para rate limiting, cache de traduções e controle de sessão. |
| Fila | Laravel Queue (Redis/DB) | - | Apenas para retry de envio de emails de notificação (os jobs Nextcloud rodam no upstream). |
| Storage | Local / Encrypted | - | Para armazenamento seguro de secrets (SSH keys, webhook secrets) via Laravel Encrypted Storage. |
| Auth | Laravel Session + Sanctum | - | Session para o painel admin; Sanctum para tokens Bearer da API externa (gerenciados via painel `/api-keys`). |
| Deploy | Docker (Laravel Sail) | - | Padroniza o ambiente de desenvolvimento e facilita o deploy multi-stage para produção. |

---

## 3. Estrutura de Pastas

### Backend & Frontend (Laravel)
```
mework360-deployer/
├── app/
│   ├── Modules/
│   │   ├── Auth/           # Login, Sessão, Operadores
│   │   ├── Customers/      # Réplica de customers, Provisionamento, OCC
│   │   ├── Jobs/           # Réplica de fila, Webhook receiver
│   │   ├── ClusterServers/ # Gestão de servidores upstream, SSH, Secrets
│   │   ├── Audit/          # Audit log (append-only)
│   │   └── Core/           # Tradutores (JobTypeTranslator, StateTranslator), SSH Client
│   ├── Http/
│   │   ├── Controllers/    # API REST (OpenAPI)
│   │   └── Livewire/       # Componentes do painel admin
├── resources/
│   ├── views/              # Blade templates (Livewire + Tailwind)
│   └── css/                # Tailwind (tokens.css)
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/                   # Documentação (REQUIREMENTS, DESIGN, ARCHITECTURE)
└── tests/
```

---

## 4. Diagrama de Módulos

```
[ Painel Livewire ]      [ Clientes API REST ]
         │                         │
         ▼                         ▼
+---------------------------------------------------+
|                  API / Controllers                |
+---------------------------------------------------+
         │                         │
         ▼                         ▼
+-----------------+       +-----------------+
| Mod: Customers  | ◄───► | Mod: Jobs       | ◄── Webhooks (HMAC)
+-----------------+       +-----------------+
         │                         │
         ▼                         ▼
+---------------------------------------------------+
| Mod: ClusterServers (Gestão de Servidores/Secrets)|
+---------------------------------------------------+
         │                         │
         ▼                         ▼
+---------------------------------------------------+
| Mod: Core (SSH Client, Tradutores de Vocabulário) |
+---------------------------------------------------+
         │
         ▼
[ nextcloud-saas-manager (Upstream via SSH) ]

* Mod: Audit observa e registra eventos de todos os módulos.
* Mod: Auth gerencia acesso ao Painel e API.
```

### 4.1 Assessment de Módulos

| Módulo | Complexidade | Risco | Flag | Depende de | Desbloqueia | Ordem |
|--------|-------------|-------|------|------------|-------------|-------|
| Core | 2 — SSH Client + Tradutores | 2 — Comunicação externa | foundation | — | Customers, Jobs, ClusterServers | Sprint D1 |
| ClusterServers | 2 — Encrypted Storage | 3 — Gestão de Secrets | complexo+foundation | Core | Customers, Jobs | Sprint D1 |
| Auth | 1 — Login padrão | 2 — Dados sensíveis | foundation | — | Todos (UI/API) | Sprint D1 |
| Audit | 1 — Append-only | 1 — Interno | foundation | — | Todos | Sprint D1 |
| Jobs | 3 — Webhook HMAC, Polling | 2 — Consistência de estado | complexo | ClusterServers, Core | Customers | Sprint D2 |
| Customers | 3 — Orquestração OCC/Provisioning | 3 — Operações destrutivas | complexo | Jobs, ClusterServers, Core | — | Sprint D2 |

**Sequência recomendada**: Iniciar pelos módulos de fundação (`Core`, `ClusterServers`, `Auth`, `Audit`) para estabelecer a infraestrutura de comunicação e segurança. Em seguida, implementar `Jobs` para garantir a recepção de webhooks, e por fim `Customers` que orquestra as operações complexas de provisionamento e OCC.

---

## 5. Decisões Técnicas (ADRs)

### ADR-001: Comunicação com Upstream via SSH + Webhooks

- **Status**: Aprovada
- **Contexto**: O sistema precisa orquestrar o provisionamento e gestão de instâncias Nextcloud em servidores remotos.
- **Decisão**: Manter SSH para comandos CLI e Webhooks HMAC-SHA256 para callbacks assíncronos.
- **Alternativas consideradas**:
  1. API REST no upstream — descartada porque exigiria reescrever o `nextcloud-saas-manager` que já expõe CLI e Webhooks.
  2. RPC — descartada pela mesma razão.
  3. Status quo (operação manual) — descartada pois o objetivo é automatizar e auditar.
- **Trade-offs aceitos**: Requer gestão cuidadosa de chaves SSH e parsing de JSON via stdout do SSH.
- **Condição de reversão**: Se o upstream evoluir para expor uma API REST nativa.
- **Consequências**: Mantém compatibilidade com a v12.0+ do upstream, mas adiciona complexidade na camada Core (SSH Client).

### ADR-002: Painel Admin em Livewire (sem Filament)

- **Status**: Aprovada
- **Contexto**: Necessidade de criar um painel unificado (CloudAdmin + DevPortal) com alta fidelidade ao protótipo Stitch.
- **Decisão**: Livewire 3 + Tailwind puro.
- **Alternativas consideradas**:
  1. Filament 3 — descartada porque o design system Stitch exige componentes e layouts muito específicos que seriam difíceis de customizar no Filament.
  2. Next.js (SPA) — descartada porque adicionaria complexidade de manter dois repositórios/stacks (PHP e TS) para um painel interno.
- **Trade-offs aceitos**: Maior esforço inicial para criar componentes UI do zero em comparação ao Filament.
- **Condição de reversão**: Se a complexidade da UI crescer a ponto de exigir uma SPA rica.
- **Consequências**: Fidelidade total ao design, stack unificada em PHP/Laravel.

### ADR-003: Armazenamento de Secrets (SSH Keys e Webhook Secrets)

- **Status**: Aprovada
- **Contexto**: Necessidade de armazenar credenciais sensíveis para acesso aos Cluster Servers.
- **Decisão**: Laravel Encrypted Storage (banco de dados com campos encriptados via `APP_KEY`).
- **Alternativas consideradas**:
  1. HashiCorp Vault — descartada por adicionar complexidade de infraestrutura desnecessária para o MVP.
  2. AWS Secrets Manager — descartada para evitar vendor lock-in no MVP.
- **Trade-offs aceitos**: Se a `APP_KEY` vazar junto com o banco, os secrets são comprometidos.
- **Condição de reversão**: Se o projeto escalar para múltiplos clusters e exigir rotação automatizada rigorosa ou certificações de segurança específicas.
- **Consequências**: Simplicidade operacional, atende aos requisitos de segurança do MVP.

---

## 6. Integrações

| Serviço | Propósito | Abordagem | Fallback |
|---------|-----------|-----------|----------|
| `nextcloud-saas-manager` | Execução de comandos (Provisionamento, OCC) | SSH (saída) com chave dedicada | Retries com backoff; marcar cluster como `unreachable` |
| `nextcloud-saas-manager` | Callbacks de jobs assíncronos | Webhook HMAC-SHA256 (entrada) | Polling SSH `nextcloud-manage job <id> status` após 60s |
| Email Service (SMTP) | Notificações de jobs e convites de operadores | API/SMTP padrão do Laravel | Fila local de retry (Laravel Queue) |

---

## 7. Segurança

### 7.1 Classificação de Dados

| Dado | Sensibilidade | Módulo responsável | Proteção |
|------|--------------|-------------------|----------|
| Senhas de Operadores | Alta | Auth | bcrypt/argon2, nunca em log |
| SSH Private Keys | Crítica | ClusterServers | Laravel Encrypted Storage |
| Webhook Secrets | Crítica | ClusterServers | Laravel Encrypted Storage |
| Dados de Customers (slug, domínio) | Média | Customers | Acesso restrito por role |
| Audit Logs | Alta | Audit | Append-only, retenção 12 meses |
| Senhas de Users (Nextcloud) | Alta | Customers | Passadas via `--payload-stdin` no SSH, nunca em argv |

### 7.2 Fronteiras de Confiança

| Origem | Destino | Protocolo | Auth | Validação | Rate Limit |
|--------|---------|-----------|------|-----------|------------|
| Internet (Operadores) | Painel Livewire | HTTPS | Laravel Session | Form Requests | 5 req/15min (Login) |
| Upstream Worker | Webhook Receiver | HTTPS | HMAC-SHA256 | Signature + IP Whitelist | 100 req/min |
| API Backend | Upstream CLI | SSH | SSH Private Key | - | - |
| Clientes API REST | API Backend | HTTPS | Bearer Token (Sanctum) | Form Requests | Sim |

### 7.3 Top 3 Vetores de Ataque

1. **Falsificação de Webhook (Tampering/Replay)** → Impacto: Alterar estado de jobs indevidamente → Mitigação: HMAC-SHA256 obrigatório, IP whitelist, replay protection (1h) no módulo `Jobs`.
2. **Vazamento de Secrets do Banco** → Impacto: Acesso root/SSH aos cluster servers → Mitigação: Laravel Encrypted Storage para SSH Keys e Webhook Secrets no módulo `ClusterServers`.
3. **Injeção de Comandos via Slug** → Impacto: Execução de código arbitrário no upstream via SSH → Mitigação: Validação rigorosa de slug (`^[a-z0-9-]+$`, sem `_`) antes de qualquer chamada SSH no módulo `Customers`.

### 7.4 Requisitos de Segurança por Módulo

| Módulo | Auth requerido? | Rate limit? | Input validation crítico? | Dados sensíveis? |
|--------|----------------|-------------|--------------------------|------------------|
| Auth | N/A | Sim (login) | Sim (credentials) | Sim (senhas) |
| Customers | Sim | Não | Sim (slugs, domínios) | Sim (senhas de users via stdin) |
| Jobs | Não (Webhook) | Sim (100/min) | Sim (HMAC-SHA256) | Não |
| ClusterServers | Sim (Admin) | Não | Sim (IPs, chaves) | Sim (SSH Keys, Secrets) |
| Audit | Sim (Admin) | Não | Não | Sim (Payloads sanitizados) |
| Core | N/A | N/A | Sim (Sanitização SSH) | Não |

---

## 8. Banco de Dados e Infraestrutura de Dados

> Gerado pelo Arquiteto de Dados (Fase 4)

### 8.1 BD Principal
- **Escolhido**: MariaDB 11 (migrado de PostgreSQL 16 em 2026-05-14 — E10)
- **Justificativa**: UUIDs nativos via `UUID()` sem extensão, tipo `JSON` para payloads sanitizados e logs de auditoria, índices `FULLTEXT` para buscas textuais. Health-check via `healthcheck.sh --connect --innodb_initialized`.

### 8.2 Tier de Infraestrutura
- **Tier**: 1 — Single Node
- **Justificativa**: Volume estimado para o MVP é extremamente baixo (até 50 customers ativos e 1 job simultâneo por cluster). Um node único é mais que suficiente e de fácil manutenção, reduzindo custo.
- **Componentes**:
  | Componente | Tecnologia | Config |
  |------------|-----------|--------|
  | BD Primary | MariaDB 11 | Default com backups diários |
  | Connection Pool | Built-in (Laravel) | Session mode |
  | Cache | Redis/Valkey standalone | 1 node |
  | Failover | Manual | Procedimento documentado de restore |

### 8.3 Composição
- **Nível**: 1 — Complementar
- **Bancos complementares**:
  | Banco | Propósito | Padrão de sync |
  |-------|-----------|---------------|
  | Redis/Valkey | Cache, sessões de operadores, rate limiting (webhooks), e filas de retry de email do Laravel | cache-aside, efêmero |
- **Justificativa**: O Redis resolve o gargalo de sessões na web e atende perfeitamente ao rate limiting exigido (100 req/min) pelo endpoint do webhook, mantendo a arquitetura simples.

### 8.4 Segurança de Dados
- **Conexão app-BD**: SSL/TLS obrigatório em produção.
- **Credenciais**: Usuário dedicado ao app (ex: `mework360_deployer_user`), senha gerada 32+ chars, rotação recomendada a cada 90 dias.
- **Encryption at rest**: Parcial (em nível de aplicação). As colunas `ssh_private_key_encrypted` e `webhook_secret_encrypted` utilizam Laravel Encrypted Storage via `APP_KEY`.
- **Backups**: `pg_dump` diário exportado para S3. PITR (Point-in-Time Recovery) não é crítico no MVP, pois as fontes de verdade de estados residem majoritariamente no upstream e o recovery de customers pode ser resincronizado via occ.

---

## 9. Observabilidade

- **Logs**: Estruturados (JSON), armazenados localmente e/ou enviados para serviço de logs centralizado.
- **Métricas**: Monitoramento básico de uptime e latência de webhooks.
- **Tracing**: Não aplicável no MVP.
- **Error tracking**: Sentry recomendado para captura de exceções do Laravel.

---

## 10. Decisões Técnicas por Módulo

### Core
**Anti-patterns a evitar:**
- Passar dados sensíveis (senhas) via argumentos de linha de comando (argv) no SSH.
- Hardcodar caminhos de scripts ou nomes de comandos.

**Decisões de implementação:**
- O cliente SSH deve suportar a passagem de payload via `stdin` (`--payload-stdin`) para dados sensíveis.
- `JobTypeTranslator` e `StateTranslator` devem ser injetados como serviços e possuir testes unitários rigorosos.

**Edge cases conhecidos:**
- Timeout de conexão SSH: Implementar retries com backoff exponencial.

**Integrações críticas:**
- Conecta-se diretamente ao `nextcloud-saas-manager` via SSH.

### ClusterServers
**Anti-patterns a evitar:**
- Armazenar secrets em texto plano no banco de dados.

**Decisões de implementação:**
- Utilizar `encrypted` casts do Eloquent para `ssh_private_key` e `webhook_secret`.
- Implementar health check periódico (cron) para atualizar o status do servidor.

**Edge cases conhecidos:**
- Rotação de webhook secret: Manter um grace period de 24h aceitando a versão antiga e a nova.

**Integrações críticas:**
- Fornece credenciais para o módulo `Core` (SSH) e `Jobs` (Webhooks).

### Customers
**Anti-patterns a evitar:**
- Tentar normalizar slugs inválidos (ex: substituir `_` por `-`). Rejeitar com 422 é mais seguro.

**Decisões de implementação:**
- Validação estrita de slug (`^[a-z0-9-]+$`, max 64 chars) em Form Requests.
- Operações de OCC síncronas devem ter timeout de 60s.

**Edge cases conhecidos:**
- Conflito de idempotência (`exit 3`): Tratar graciosamente mostrando o job existente.

**Integrações críticas:**
- Depende do `Core` para execução de comandos SSH.

### Jobs
**Anti-patterns a evitar:**
- Processar o mesmo webhook múltiplas vezes gerando side-effects repetidos.

**Decisões de implementação:**
- Validação HMAC-SHA256 obrigatória no middleware do webhook.
- Implementar lock otimista ou verificação de estado para garantir idempotência no processamento do webhook.

**Edge cases conhecidos:**
- Webhook chega atrasado (replay attack): Rejeitar se `finished_at` for mais antigo que 1 hora.

**Integrações críticas:**
- Recebe requisições do `nextcloud-saas-manager`.

### Auth
**Anti-patterns a evitar:**
- Criar rotas de registro público.

**Decisões de implementação:**
- Apenas usuários com role `admin` podem criar novos operadores.
- Envio de links de convite com tempo de expiração.

**Edge cases conhecidos:**
- Token de convite expirado: Permitir reenvio pelo admin.

**Integrações críticas:**
- N/A.

### Audit
**Anti-patterns a evitar:**
- Permitir edição ou exclusão de logs de auditoria pela aplicação.

**Decisões de implementação:**
- Tabela append-only.
- Payloads devem ser sanitizados (remover senhas, tokens) antes de serem serializados em JSON.

**Edge cases conhecidos:**
- Volume de dados: Garantir paginação eficiente e exportação em chunks para CSV.

**Integrações críticas:**
- Observa eventos de todos os outros módulos.

---

## 11. Mapa de Dependências

### Ordem de implementação (topológica)
1. **Core** (raiz — cliente SSH, tradutores de vocabulário)
2. **ClusterServers** (depende: Core) — gestão de servidores e secrets
3. **Auth** (raiz — gestão de operadores e sessão)
4. **Audit** (raiz — append-only, observa todos)
5. **Jobs** (depende: ClusterServers, Core) — webhook receiver, polling
6. **Customers** (depende: Jobs, ClusterServers, Core) — orquestração OCC e provisioning

### Matriz de impacto
| Módulo alterado | Impacta |
|-----------------|---------|
| Core | ClusterServers, Jobs, Customers |
| ClusterServers | Jobs, Customers |
| Auth | Todos (middleware/UI) |
| Audit | Nenhum (apenas observa) |
| Jobs | Customers |
| Customers | Nenhum (ponta da cadeia) |

---

## 12. Artefatos Gerados

- [x] `docs/ARCHITECTURE.md` (este arquivo — `/arquiteto planejar`)
- [x] Seção "Decisões Técnicas por Módulo" (alimenta planejador-tarefas → ROADMAP.md)
- [x] Seção 8 detalhada + `docs/DATABASE.md` (`/arquiteto dados`)
- [x] `docs/openapi.yaml` (atualizado para v2.0 com pattern de slug restrito)
- [x] `docs/db-schema.dbml` (gerado via `/arquiteto contratos`)
- [ ] `.cursor/rules/*.mdc` (`/arquiteto padroes`)
- [ ] `.cursor/skills/[projeto]/*` (`/arquiteto padroes`)
- [ ] `.github/workflows/ci.yml` + `.cursor/hooks/` + Docker (`/devops planejar`)
- [ ] `docs/INFRASTRUCTURE.md` (`/devops infra`)

---

## Histórico de Revisões

| Data | Versão | Alteração | Autor |
|------|--------|-----------|-------|
| 2026-05-07 | 0.1 | Proposta inicial e aprovação | Arquiteto de Soluções (IA) |
| 2026-05-07 | 0.2 | Adição do Mapa de Dependências e DBML | Analista de Sistemas (IA) |
| 2026-05-14 | 0.3 | E10: PostgreSQL→MariaDB 11; E1: manage.sh→nextcloud-manage; E13: Bearer tokens disponíveis no MVP via `/api-keys` | IA (D8 Polish) |
