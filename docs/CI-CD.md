# CI/CD — mework360-deployer

> Gerado em: 2026-05-07
> Fase: 8.1 — CI/CD + Docker configurados (`/devops planejar`)
> Escopo atual: **CI apenas** (deploy manual no MVP)

---

## 1. Visão geral

| Item | Decisão |
|---|---|
| Plataforma CI | **GitHub Actions** (`.github/workflows/ci.yml`) |
| Estratégia | CI-only (PROTÓTIPO, MVP 4–6 semanas, sem staging real ainda) |
| Triggers | `push` em `main`/`develop` + `pull_request` para `main`/`develop` |
| Concurrency | Cancela runs antigos do mesmo branch (`cancel-in-progress`) |
| Permissões do workflow | `contents: read` (least privilege) |
| CD | **Não configurado** — provisionamento manual conforme `docs/INFRASTRUCTURE.md`. Reativar via `/devops planejar` quando staging existir. |

---

## 2. Jobs do pipeline

### 2.1 `lint` (Pint)
- Roda primeiro, gate para os demais.
- `composer install` com cache de `~/.composer/cache` e `vendor/`.
- `./vendor/bin/pint --test` (sem auto-fix; falha se diff).

### 2.2 `test` (Pest/PHPUnit)
- Depende de `lint`.
- Services efêmeros via Docker do runner:
  - `postgres:16-alpine` (DB `mework360_deployer_test`, user `mework360_deployer`, password `secret`).
  - `redis:7-alpine`.
- Healthcheck dos services com `pg_isready` e `redis-cli ping` antes de rodar testes.
- Steps: `composer install` → `cp .env.example .env` → `php artisan key:generate` → `php artisan migrate --force` → `php artisan test --parallel --recreate-databases`.
- **Sem hardcode de secrets de produção** — credenciais do BD de teste são públicas e descartáveis.

### 2.3 `security` (composer audit)
- Depende de `lint`.
- `composer audit --no-dev --locked --abandoned=report`.
- Falha se houver CVEs em dependências `require` (não em `require-dev`).
- **Por que é importante neste projeto**: a API depende de bibliotecas Laravel que processam payloads de webhook HMAC e expõem rotas autenticadas (Sanctum). Vulnerabilidades em deps podem comprometer a integridade da orquestração SSH.

> **Não há job `build`** porque Laravel não compila — `composer install --optimize-autoloader` já cobre o que precisa ficar pronto. O equivalente a "build" acontece dentro do `Dockerfile` (stage `build`).

---

## 3. Docker — desenvolvimento local

Stack idêntica à descrita em `docs/INFRASTRUCTURE.md` para reduzir paridade dev/prod:

| Serviço | Imagem | Porta host | Healthcheck |
|---|---|---|---|
| `app` | build local (target `development` do `Dockerfile`) | — (php-fpm na rede `mework360`) | `php-fpm -t` |
| `nginx` | `nginx:1.27-alpine` | `${NGINX_PORT:-8080}` → 80 | `wget /up` |
| `database` | `postgres:16-alpine` | `${DB_EXTERNAL_PORT:-5432}` → 5432 | `pg_isready` |
| `redis` | `redis:7-alpine` (com AOF + maxmemory 256MB + LRU) | `${REDIS_EXTERNAL_PORT:-6379}` → 6379 | `redis-cli ping` |
| `mailpit` | `axllent/mailpit:latest` | `${MAIL_EXTERNAL_PORT:-1025}` SMTP + `${MAILPIT_UI_PORT:-8025}` UI | `wget /readyz` |

### Comandos rápidos

```bash
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose ps
docker compose logs -f app
docker compose down            # preserva volumes (dados ficam)
```

> **Atenção**: `docker compose down -v` é bloqueado pelo hook `safety-guard.sh` (apaga `db_data`/`redis_data`).

---

## 4. Dockerfile (multi-stage)

| Stage | Propósito | Conteúdo extra |
|---|---|---|
| `base` | runtime mínimo PHP 8.3-fpm + extensões (`pdo_pgsql`, `redis`, `intl`, `zip`, `bcmath`, `gd`, `pcntl`, `opcache`) | `tini` para PID 1 correto |
| `development` | dev local | + `xdebug` (modo `off` por padrão; ativar via env `XDEBUG_MODE`) |
| `build` | preparar artefato | `composer install --no-dev --optimize-autoloader`; remove `tests/`, `docs/`, `layout/`, `.cursor`, `.github` |
| `production` | imagem final | usuário não-root `appuser` (uid 1000); `HEALTHCHECK` com `php artisan about --json`; opcache otimizado |

> O stage `production` **não** roda `php artisan config:cache` no build porque depende do `APP_KEY` em runtime. Caching de config/route/view deve ser feito no entrypoint do container produtivo (a definir quando montarmos o pipeline de deploy real).

---

## 5. Hooks Cursor

| Hook | Tipo | Função |
|---|---|---|
| `.cursor/hooks/safety-guard.sh` | `beforeShellExecution` | Bloqueia `rm -rf /`, `git push --force`, `DROP DATABASE`, `migrate:fresh --force`, `db:wipe`, `docker compose down -v`, `docker volume rm`, fork bombs, `mkfs`, escrita em `/dev/sd*`. Responde `{"permission":"deny"}` quando matchar. |
| `.cursor/hooks/pmo-update.sh` | `stop` | Atualiza `.cursorsession.ultimo_update` (UTC) a cada turno e adiciona linha em `docs/CHANGELOG.md` com o hash do último commit (deduplicada). |

Configuração em `.cursor/hooks.json` (versão 1). O `matcher` do `safety-guard` cobre os padrões mais comuns como pré-filtro; a checagem real é case-insensitive dentro do script.

---

## 6. Secrets — política

| Categoria | Onde fica em **dev** | Onde fica em **prod** |
|---|---|---|
| `APP_KEY` | `.env` (gerado por `php artisan key:generate`) | `.env` da VM (gerado uma vez, **fora** do repo) |
| `DB_PASSWORD` | `.env` (placeholder `secret`) | Gerado 32+ chars, injetado via `.env` da VM |
| SSH keys upstream (`ncsaas-api`) | **Não em `.env`** — gravadas em `cluster_servers.ssh_private_key_encrypted` via Laravel Encrypted Storage | Idem, criptografadas em DB com `APP_KEY` |
| Webhook secrets HMAC | **Não em `.env`** — `cluster_servers.webhook_secret_encrypted` | Idem |
| Credenciais S3 (backups) | Vazias no `.env.example` | Injetadas via `.env` da VM ou IAM role |
| Sentry DSN | Vazio | Via `.env` da VM (opcional) |

Regras absolutas:
1. `.env` está no `.gitignore` — nunca commitar.
2. Nenhum `ARG`/`ENV` com secret no `Dockerfile`.
3. Workflow CI não consome secrets de produção (banco de teste é descartável).
4. Quando habilitarmos CD, secrets de deploy (`DEPLOY_*`, `DEPLOY_KEY`) entram via **GitHub Environments** com required reviewers para `production`.

---

## 7. O que não foi configurado (intencional)

| Item | Motivo | Quando reabrir |
|---|---|---|
| Pipeline CD (deploy SSH/Docker) | Cenário PROTÓTIPO; staging não existe; provisionamento manual ainda | Quando houver staging real com SSH key dedicada do CI |
| `docker-compose.prod.yml` | Sem CD ainda; conviria gerar junto para evitar drift | Junto com CD |
| Sentry SDK no projeto | Stack ainda não scaffoldada; incluído como placeholder no `.env.example` | Sprint que entrega Auth + observabilidade |
| Workflow de release/changelog | MVP curto (4–6 semanas) — overhead não compensa agora | Pós-MVP, com versionamento semântico |
| Cobertura de cobertura (codecov) | Sem testes ainda; primeiro entregar a Sprint 1 | Quando 80% de cobertura virar gate (ver `60-testing.mdc`) |

---

## 8. Próximos passos sugeridos

1. **Sprint 1 — scaffold do Laravel** (`composer create-project laravel/laravel .` no diretório, mantendo os arquivos já gerados aqui).
2. Após scaffold: `docker compose up -d`, rodar `php artisan migrate`, validar pipeline CI no primeiro PR.
3. Quando a primeira VM de staging existir: `/devops planejar` novamente para gerar CD + `docker-compose.prod.yml` + `deploy.sh`/`rollback.sh`.

---

## Histórico

| Data | Versão | Alteração |
|---|---|---|
| 2026-05-07 | 0.1 | Versão inicial — CI-only, Docker dev, hooks Cursor (`/devops planejar`). |
