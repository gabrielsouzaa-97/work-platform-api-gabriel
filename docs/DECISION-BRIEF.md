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

## Decision #ARCH-5 — Readiness gate pós-provision (`provisioning_finishing`)

- **Status**: accepted
- **Date**: 2026-05-23
- **Related skills**: webhook-receiver, ssh-orchestrator, vocabulary-translator
- **Supersedes**: —
- **Origem**: ISSUE-010 / QA-DYN-021 (P-21) → Sprint F8

### Context

O upstream `nextcloud-saas-manager` emite callback `provision success` após o passo ~6 da
`SSH API Reference §4.1` (core install + admin), enquanto passos 7–9 (Redis, Collabora,
14 apps, allowlists) continuam por ~5–15 minutos. A API propagava `Customer.status=active`
no webhook, enganando consumidores: `users:create`/`users:delete` falhavam silenciosamente
se disparados nos primeiros ~10 min (5/5 failed vs 8/8 success após 30 min — evidência
empírica 2026-05-21).

`groups:*` e `apps:*` toleram a janela; apenas operações no subsistema de usuários exigem
readiness completa.

### Alternatives considered

#### A. Corrigir callback upstream (causa raiz no `nextcloud-saas-manager`)
- **Pros**: Sinal de readiness correto na origem; `active` significa realmente pronto.
- **Cons**: Depende de deploy upstream; não protege clientes durante rollout ou regressões.

#### B. Readiness gate defensivo na API (escolhida)
- **Pros**: Desacopla cliente do timing upstream; fail-closed explícito (503 + Retry-After)
  em vez de 202→failed silencioso; probe reutiliza infra SSH existente.
- **Cons**: Novo estado `provisioning_finishing`; worker/queue para probe; webhook não
  marca `active` imediatamente (mudança de contrato observável).

#### C. Retry inteligente só em `users:create` (mitigação local)
- **Pros**: Patch mínimo no lifecycle.
- **Cons**: Não corrige sinal `active` enganoso; `users:delete` e futuros verbs user
  precisam do mesmo tratamento ad hoc.

#### D. Documentar janela e transferir ao consumidor
- **Pros**: Zero código.
- **Cons**: Pior DX; quebra onboarding automatizado; reproduz bug em todo cliente novo.

### Decision

**Alternativa B** — após webhook `provision success`:

1. `Customer.status` → `provisioning_finishing` (não `active`).
2. `ProbeCustomerReadinessJob` executa probe periódico via `occ-exec user:list` (exit 0 → `active`).
3. `LifecycleAsyncAction` bloqueia `users:create` e `users:delete` enquanto
   `status` ∈ `{provisioning, provisioning_finishing}` → `TenantNotReadyException` → HTTP 503.
4. `CustomerSyncService` não sobrescreve `provisioning_finishing` com `running→active` do upstream.

Issue upstream (alternativa A) recomendada em paralelo, não bloqueante.

### Rationale

1. **Fail-closed > fail-silent**: 503 com `Retry-After` permite retry determinístico; 202 seguido de `failed` sem `exit_code` impossibilita diagnóstico (P-05).
2. **Escopo mínimo do gate**: só `users:*` — evidência empírica mostrou que `groups`/`apps` funcionam na janela.
3. **Probe via SSH existente**: reutiliza `CustomerReadinessProbe` + `OccPassthroughService` pattern; sem dependência de novo contrato webhook upstream.

### Consequences

- Clientes devem tratar HTTP 503 `tenant_not_ready` em `POST/DELETE .../users` após provision.
- UI admin exibe badge `provisioning_finishing` distinto de `provisioning`.
- OpenAPI v2.2 documenta response `TenantNotReady` (503).
- Saga de onboarding (P-22) deve consumir este gate internamente — cliente da saga não vê a janela.

---

## Decision #ARCH-6 — OCC `exit_code 16` é allowlist do `occ-exec`, não "flag stripping"

- **Status**: accepted
- **Date**: 2026-05-23
- **Related skills**: arquiteto, ssh-orchestrator
- **Related issues**: ISSUE-011 (P-15), P-09 (superseded), P-10 (bloqueado), P-16, P-17
- **Supersedes**: comentários antigos em `OccController` que afirmavam "upstream `dispatch.sh` strips OCC `--flags`" e o erro `upstream_dispatch_limitation`.

### Context

A teoria embutida no código desde a Sprint D7 era que o upstream `nextcloud-manage dispatch.sh` filtrava flags `--xxx` ao chamar o `occ` no container. Quatro comentários no `OccController` (linhas 42, 56–60, 67, 95, 105–109) propagavam esse diagnóstico e justificavam workarounds positional + respostas 501/502 com `error: upstream_dispatch_limitation`.

Testes dinâmicos contra `deployer.mework360.com.br/api` em 2026-05-21 (matriz P-15) **refutaram empiricamente** a hipótese: `maintenance:mode on` em forma positional pura — sem nenhuma flag — também falha com `exit_code 16`. A causa real é uma **allowlist de subcmds em `nextcloud-manage <slug> occ-exec`** no upstream.

### Evidência

| Subcmd OCC | Tem flag? | Resultado | Tipo |
|---|---|---|---|
| `user:list`, `app:enable`, `files:scan <user>`, `user:add`, `user:resetpassword` | não | ✅ exit 0 | dentro da allowlist |
| `maintenance:mode on` (positional) | **não** | ❌ exit 16 | fora da allowlist — refuta "stripping" |
| `user:setting <args>` | não | ❌ exit 16 | fora da allowlist |
| `config:app:set files default_quota 3 GB` | não | ❌ exit 16 | fora da allowlist |
| `theming:config name "X"` | não | ❌ exit 16 | fora da allowlist |

### Alternatives considered

#### A. Manter diagnóstico errado e perseguir "fix" no `dispatch.sh` (status quo)
- **Pros**: Zero código.
- **Cons**: Knowledge debt CRITICAL; futuros mantenedores investigam o lugar errado; respostas 502 com mensagens enganosas.

#### B. Corrigir comentários + mapear `exit 16 → 403 occ_subcmd_not_allowed` (escolhida)
- **Pros**: Diagnóstico correto no código; cliente recebe 403 explícito (não 502 genérico) com `subcmd` no payload; reabilita visibilidade para Suporte; não muda contrato dos endpoints que já funcionam (`user:list`, `app:enable`, `files:scan <user>`); não exige decisão sobre P-17 nem upstream; baixo risco.
- **Cons**: Endpoints `quota/default`, `quota/{username}`, `branding`, `maintenance` continuam retornando erro (agora 403 honesto em vez de 502), até allowlist upstream expandir ou alternativa C/D.

#### C. Curto-circuitar endpoints bloqueados em 403 antes do SSH (pre-empt allowlist)
- **Pros**: Economiza round-trip SSH para subcmds reconhecidamente bloqueados.
- **Cons**: Exige manter allowlist literal no código; quebra automaticamente quando upstream expande allowlist (chamada que passaria a funcionar continua bloqueada localmente). Premature optimization sem fonte autoritativa.

#### D. Refatorar gateway para `occ` direto (bypass `occ-exec`) ou expor verbos de domínio (`branding set`, `quota default`, `maintenance toggle`)
- **Pros**: Resolve P-10/P-17 para sempre.
- **Cons**: Mudança grande no upstream; requer Architect + sprint dedicada; fora do escopo deste fix.

### Decision

**Alternativa B**:

1. Reescrever os 4 comentários falsos no `OccController` para citar `ISSUE-011` e a allowlist como causa.
2. Mapear `SshRemoteException::remoteExitCode === 16` em `OccController::runOcc` para `HTTP 403` com payload `{error: occ_subcmd_not_allowed, subcmd, exit_code: 16, detail}`.
3. Renomear erros 501 hardcoded de `upstream_dispatch_limitation` para `occ_subcmd_not_supported` (quota/all) e `occ_bulk_not_supported` (files-rescan sem username), com mensagens factualmente corretas.
4. Adicionar teste de regressão de texto em `OccControllerTest` que falha se `OccController.php` voltar a conter `strips OCC --flags` ou `upstream_dispatch_limitation`.
5. Não tocar em `OccPanel` (Livewire) nem em `openapi.yaml` neste fix mínimo — acompanhar em sprint dedicada se P-17 for endereçada.
6. **Follow-up (2026-05-23)**: `OccController::toggleMaintenance` alinhado com `OccPanel` — argv canônico `--on`/`--off` (não positional `on`/`off`; o workaround positional era herança do diagnóstico falso de P-09).

### Rationale

1. **Knowledge debt é CRITICAL no contexto AI-assisted**: comentários falsos induzem agentes (humanos e IAs) a planejar fixes errados; corrigir o diagnóstico tem impacto desproporcional ao tamanho do diff.
2. **Mapeamento honesto de erro**: 403 com `subcmd` permite Suporte/cliente identificar imediatamente o subcmd bloqueado sem ler logs upstream.
3. **Não pre-empt em código**: deixamos a chamada SSH ocorrer porque a allowlist é controle do upstream — quando expandir, a API continua funcionando sem deploy.
4. **Escopo mínimo**: P-10, P-17 e a coordenação com upstream são tratadas em sprints separadas; este fix não as bloqueia nem antecipa.

### Consequences

- Endpoints OCC mutativos bloqueados retornam HTTP 403 `occ_subcmd_not_allowed` (antes: 502 `upstream_error`).
- Cliente HTTP que tratava 502 deve passar a tratar 403 explicitamente para esses endpoints (mudança de contrato observável — documentar em `CHANGELOG`).
- `OpenAPI` permanece desatualizado para esses 403 — registrar como follow-up.
- Issue upstream (alternativa D) recomendada em paralelo, anexando matriz P-15 — não bloqueia este fix.

---
