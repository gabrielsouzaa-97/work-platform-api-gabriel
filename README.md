# mework360-deployer

API orquestradora + painel administrativo interno meWork360. Expõe uma interface REST e Livewire para operadores provisionarem e gerenciarem instâncias Nextcloud no [`nextcloud-saas-manager`](../nextcloud-saas-manager) via SSH.

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 12 (PHP 8.2) |
| Frontend | Livewire 3 + Tailwind CSS |
| Banco | MariaDB 11 |
| Cache / Sessão | Redis 7 |
| Container | Docker (multi-stage) + Nginx |
| CI | GitHub Actions |

## Pré-requisitos (desenvolvimento)

- Docker 24+ e Docker Compose v2
- PHP 8.2+ e Composer (para rodar fora do container)
- Git

## Setup local

```bash
git clone <repo> mework360-deployer-api
cd mework360-deployer-api

cp .env.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Painel disponível em `http://localhost:8080`.

Credencial inicial criada pelo seeder — veja `database/seeders/DatabaseSeeder.php`.

> O hook `safety-guard.sh` bloqueia `docker compose down -v` para proteger os volumes de dados. Use `docker compose down` (sem `-v`) para parar sem apagar dados.

## Testes

```bash
# Dentro do container
docker compose exec app php artisan test --parallel

# Fora do container (requer .env de teste)
php artisan test --parallel
```

O CI roda `lint → test → security` a cada push/PR. Veja `.github/workflows/ci.yml`.

## Módulos

| Módulo | Responsabilidade |
|--------|-----------------|
| `Core` | SSH client, translators (JobType, State), exceções base |
| `Auth` | Login, convite de operadores, roles (admin / operador / suporte) |
| `ClusterServers` | CRUD de cluster_servers, rotação de webhook secret |
| `Customers` | Provisionar, remover, sincronizar customers (réplica local) |
| `Jobs` | Fila local (réplica), poll de stuck jobs, cancelamento |
| `Audit` | Audit log LGPD (retenção 12 meses via `audit:purge`) |
| `ApiKeys` | Geração, listagem e revogação de Bearer tokens (tela `/api-keys`) |

## Comandos artisan relevantes

| Comando | Frequência | Descrição |
|---------|-----------|-----------|
| `customers:sync` | Diária 03:00 | Sincroniza réplica local com upstream |
| `jobs:poll-stuck` | A cada 5 min | Poll via SSH de jobs sem callback > 60s |
| `cluster:health-check` | A cada 5 min | Verifica conectividade SSH dos clusters |
| `clean:expired-webhook-secrets` | Diária 03:00 | Remove secrets expirados da tabela `webhook_secret_history` |
| `audit:purge` | Mensal dia 1 03:30 | Remove audit_logs > 12 meses (LGPD) |

## Documentação

| Doc | Conteúdo |
|-----|----------|
| `docs/ARCHITECTURE.md` | Decisões arquiteturais, estrutura de módulos |
| `docs/DATABASE.md` | Schema, índices, decisões de modelagem |
| `docs/INFRASTRUCTURE.md` | Topologia, specs, checklist de provisionamento |
| `docs/CI-CD.md` | Pipeline CI, Docker, política de secrets |
| `docs/RUNBOOK.md` | Procedimentos operacionais (rotate secret, sync, deploy) |
| `docs/openapi.yaml` | Contrato REST v2.0 (33 operações) |
| `docs/REQUIREMENTS.md` | Requisitos funcionais F1–F10 |
| `docs/FINDINGS.md` | Achados de auditoria e status de correção |

## Variáveis de ambiente essenciais

| Variável | Descrição |
|----------|-----------|
| `APP_KEY` | Chave de criptografia Laravel (obrigatória; gerar com `php artisan key:generate`) |
| `APP_URL` | URL pública da aplicação (usada no callback do webhook) |
| `DB_*` | Conexão MariaDB |
| `REDIS_HOST` | Conexão Redis (cache, sessão, rate limit) |
| `WEBHOOK_REPLAY_WINDOW_SECONDS` | Janela anti-replay do webhook (padrão: 3600s) |
| `SSH_COMMAND_TIMEOUT_SECONDS` | Timeout de comandos SSH (padrão: 60s) |

Ver `.env.example` para a lista completa.

## Segurança

- Autenticação: sessão Laravel + roles (`admin`, `operador`, `suporte`); Bearer tokens via Sanctum (gerenciados no painel em `/api-keys`)
- Webhook: HMAC-SHA256 + IP whitelist + replay protection por `job_id` (1h TTL)
- SSH keys e webhook secrets: armazenados criptografados no banco via `APP_KEY`
- Audit log: todas as ações críticas registradas, retenção LGPD 12 meses
- Rate limit: login (5 tentativas / 15 min), API (120 req/min), webhook (100/min)

## Licença

Uso interno meWork360 — não distribuir.
