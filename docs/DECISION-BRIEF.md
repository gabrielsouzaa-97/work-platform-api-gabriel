# Decision Brief

> Append-only ADRs (Architecture Decision Records) do projeto.
> Gerenciado pela capability `decision-brief`.
> Veja `~/.cursor/skills/capabilities/decision-brief/decision-brief.md` para regras.

---

## Decision #ARCH-1 — Comunicação com Upstream via SSH + Webhooks

- **Status**: accepted
- **Date**: 2026-05-07
- **Related skills**: arquiteto
- **Supersedes**: —

### Context

O sistema precisa orquestrar o provisionamento e gestão de instâncias Nextcloud em servidores remotos. O sistema upstream (`nextcloud-saas-manager`) já expõe uma CLI assíncrona (via `manage.sh`) e envia callbacks via webhook.

### Alternatives considered

#### API REST nativa no upstream
- **Pros**: Comunicação padrão HTTP, fácil de consumir, sem necessidade de gerenciar chaves SSH.
- **Cons**: Exigiria reescrever ou expandir significativamente o `nextcloud-saas-manager` que hoje é baseado em Bash scripts.

#### Comunicação via SSH + Webhooks HMAC-SHA256
- **Pros**: Aproveita a infraestrutura existente do upstream, evita reescrita, e o webhook garante assincronicidade sem bloquear a API.
- **Cons**: Requer gestão cuidadosa de chaves SSH, parsing de JSON via stdout do SSH e validação rigorosa de HMAC.

### Decision

Manter SSH para comandos CLI e Webhooks HMAC-SHA256 para callbacks assíncronos.

### Rationale

Aproveitar a infraestrutura existente do `nextcloud-saas-manager` é o caminho mais rápido e seguro para o MVP, evitando reescrever um sistema que já funciona e está testado.

### Consequences

Mantém compatibilidade com a v12.0+ do upstream, mas adiciona complexidade na camada Core (SSH Client) da API Laravel.

---

## Decision #ARCH-2 — Painel Admin em Livewire (sem Filament)

- **Status**: accepted
- **Date**: 2026-05-07
- **Related skills**: arquiteto, designer
- **Supersedes**: —

### Context

Necessidade de criar um painel unificado (CloudAdmin + DevPortal) com alta fidelidade ao protótipo Stitch (High-Tech Professional, dark mode).

### Alternatives considered

#### Filament 3
- **Pros**: Desenvolvimento extremamente rápido de painéis admin, muitos componentes prontos.
- **Cons**: O design system Stitch exige componentes e layouts muito específicos que seriam difíceis de customizar no Filament, que tem um design opinionado.

#### Next.js (SPA separada)
- **Pros**: Separação clara entre frontend e backend, ecossistema rico de componentes React.
- **Cons**: Adicionaria complexidade de manter dois repositórios/stacks (PHP e TS) para um painel interno, aumentando o custo de manutenção.

#### Livewire 3 + Tailwind puro
- **Pros**: Permite fidelidade total ao design Stitch, reatividade sem SPA, e mantém a stack unificada em PHP/Laravel.
- **Cons**: Maior esforço inicial para criar componentes UI do zero em comparação ao Filament.

### Decision

Livewire 3 + Tailwind puro.

### Rationale

A fidelidade ao design Stitch foi definida como prioridade na fase de design. Livewire oferece o melhor equilíbrio entre fidelidade visual e simplicidade de stack (apenas Laravel).

### Consequences

Fidelidade total ao design, stack unificada em PHP/Laravel, porém com maior esforço inicial na construção da UI.

---

## Decision #ARCH-3 — Armazenamento de Secrets (SSH Keys e Webhook Secrets)

- **Status**: accepted
- **Date**: 2026-05-07
- **Related skills**: arquiteto
- **Supersedes**: —

### Context

A API precisa armazenar credenciais sensíveis (chaves privadas SSH e segredos de webhook) para acesso aos Cluster Servers upstream.

### Alternatives considered

#### HashiCorp Vault / AWS Secrets Manager
- **Pros**: Segurança enterprise, rotação automatizada, auditoria de acesso nativa.
- **Cons**: Adiciona complexidade de infraestrutura desnecessária para o MVP, além de potencial vendor lock-in.

#### Laravel Encrypted Storage
- **Pros**: Simplicidade operacional, atende aos requisitos de segurança do MVP, não requer infraestrutura externa.
- **Cons**: Se a `APP_KEY` vazar junto com o banco de dados, os secrets são comprometidos.

### Decision

Laravel Encrypted Storage (banco de dados com campos encriptados via `APP_KEY`).

### Rationale

Para o MVP, a simplicidade operacional é crucial. O Laravel Encrypted Storage oferece um nível de segurança adequado sem a sobrecarga de gerenciar um serviço de secrets externo.

### Consequences

Simplicidade operacional. Requer cuidado extra na gestão da `APP_KEY` em produção.

---

## Decision #ARCH-4 — Três vocabulários, um único tradutor (`JobTypeTranslator`)

- **Status**: accepted
- **Date**: 2026-05-20
- **Related skills**: vocabulary-translator, ssh-orchestrator
- **Supersedes**: —
- **Origem**: ISSUE-006 (postmortem HIGH) → Sprint F5

### Context

O orquestrador opera com **três vocabulários distintos** para descrever a mesma operação assíncrona:

| # | Nome | Onde aparece | Exemplo |
|---|------|--------------|---------|
| 1 | `cmd_canonical` (interno) | `Job.cmd_canonical`, `IdempotencyKey.cmd`, `AuditLog`, payload de controllers/Livewire | `users:create` |
| 2 | `job_type` | webhook payloads recebidos do upstream, enum persistido | `user_create` |
| 3 | **CLI argv upstream** | argv passado ao binário `nextcloud-manage` via SSH | `['user', 'create']` |

Antes da Sprint F5, `JobTypeTranslator` cobria apenas a tradução bidirecional `cmd_canonical ↔ job_type`. A tradução `cmd_canonical → CLI argv upstream` **não existia**: `LifecycleAsyncAction::execute()` injetava o `cmd` cru no argv, fazendo o upstream receber `users:create` (verb desconhecido) e responder `cmd_not_allowed`. Bug observado em produção em 2026-05-20.

A documentação SSH (`docs/SSH API Reference §3.3`) confirma que o upstream usa namespace hierárquico (`user create`, `group remove`, `apps enable`), não a forma flat com hífen ou dois-pontos.

### Alternatives considered

#### A. Classe separada `CliArgvTranslator`
- **Pros**: SRP estrito, módulo `Translators` ganha 1 classe por vocabulário (`State`, `JobType`, `CliArgv`).
- **Cons**: Espalha conhecimento sobre a mesma origem (`cmd_canonical`) em 2 arquivos. Risco de drift de mapping (esquecer de atualizar um lado ao adicionar verb).

#### B. Estender `JobTypeTranslator` com `cmdToCliArgv()`
- **Pros**: Um único ponto de verdade para "tudo que se traduz a partir de `cmd_canonical`". Adicionar novo verb requer editar 2 constantes no mesmo arquivo (lado a lado, difícil esquecer). Footprint pequeno (mapping é constante PHP, não classe).
- **Cons**: Nome da classe (`JobTypeTranslator`) fica levemente desalinhado com o escopo (agora também faz argv). Mitigação: docblock do arquivo explicita os 3 vocabulários.

### Decision

**Alternativa B** — `JobTypeTranslator` ganha o método `cmdToCliArgv(string $cmd): array<string>` que retorna os tokens do argv upstream. Verbs não-implementados no upstream (`groups:add`, `groups:remove`) lançam `BlockedOnUpstreamException` (controllers traduzem para HTTP 501).

### Rationale

1. **Coesão por origem** > coesão por destino: todas as traduções partem de `cmd_canonical`. Manter o mapping no mesmo arquivo reduz drift.
2. **Custo de adicionar verb** cai de 3 lugares (`CMD_TO_JOB_TYPE`, `JOB_TYPE_TO_CMD`, hipotética `CliArgvTranslator::MAP`) para 3 lugares no mesmo arquivo — visíveis em uma única tela.
3. **Footprint mínimo**: a sprint F5 já é cirúrgica; criar nova classe + interface + binding seria over-engineering para um mapping de 12 entradas.

### Consequences

- `JobTypeTranslator` é o "ponto de verdade" do verbo. Qualquer novo verb (e.g. `users:rename`) precisa entrar nas 3 constantes simultaneamente.
- `BlockedOnUpstreamException` é o sinal canônico de "verb existe na API mas não no upstream" (gera HTTP 501, NÃO 500). Difere de `UnknownVerbException` (verb desconhecido em todo lugar → bug do caller).
- A skill `.cursor/skills/vocabulary-translator/SKILL.md` lista os 3 vocabulários (não 2) e cita o gap como histórico.
- Quando `mework360-deployer-scripts` D3/D4 entregar `group add-user`/`remove-user`, basta adicionar ao `CMD_TO_CLI_ARGV` e remover de `BLOCKED_ON_UPSTREAM` — controllers/Livewire não mudam.

---
