# Architecture & workflow routing

## Two-repo model

| Repo | Role | Path típico |
|------|------|-------------|
| `mework360-deployer-api` | API REST + painel; SSH saída; webhook entrada | este repo |
| `mework360-deploy-scripts` | `nextcloud-manage`, worker Redis, tenants Nextcloud | `../mework360-deploy-scripts` |

**Regra de contrato** (`docs/REQUIREMENTS.md §8`): mudança de argv/webhook exige PR coordenado. Deploy **upstream primeiro**, API depois.

## Routing by intent

| Intenção do usuário | Começar em |
|---------------------|------------|
| Subir ambiente local | `local-stack.md` |
| Atualizar versão / deploy VM | `production-deploy.md` + `readiness-gates.md` |
| Criar customer / TN / provision | `provision-lifecycle.md` + gates R6–R8 |
| SaaS-01 vs SaaS-02 / Dev vs Prod independentes? | `environment-and-parity.md` § VPS upstream |
| Onde se criam TNs no Dev? / fábrica vs clientes | `environment-and-parity.md` |
| Fluxo de contratação em qual ambiente? | `provision-lifecycle.md` + `environment-and-parity.md` § VPS |
| Paridade local = Dev / meMail não abre | `environment-and-parity.md` + `local-stack.md` § Tier 3b |
| Testar perfil de create (canário) | `environment-and-parity.md` § canário + `post-create-runbook.md` |
| "Está pronto?" | `readiness-gates.md` (R1–R8) |
| Simular E2E no laptop (create real) | `local-stack.md` § Tier 2 |
| Stack NC local sem SSH | `local-stack.md` § Tier 3a (lab) ou 3b (FULL_LOCAL) |

## Simulation tiers (summary)

| Tier | Escopo | Provision real? |
|------|--------|-----------------|
| 0 | Testes com SSH mockado | Não |
| 1 | `docker compose` + seed fake cluster | Não |
| 2 | Tier 1 + cluster homolog (`dev.mework360.com.br`) + túnel webhook | **Sim** — `manage.sh create` no host Dev |
| 3a | `mework360-local-lab` — tenant limpo :9080 | Não |
| 3b | `mework360-local` FULL_LOCAL — stack Traefik/RC/Collabora | Não (scripts manuais; não é `create`) |
| 4 | Worker + shim local ou repro upstream completo | Futuro (ISSUE-022) |

## Serviços Docker locais

| Service | Container | Porta host |
|---------|-----------|------------|
| app | mework360-deployer-app | — |
| nginx | mework360-deployer-nginx | 8080 |
| database | mework360-deployer-db | 3306 |
| redis | mework360-deployer-redis | 6379 |
| worker | mework360-deployer-worker | — |
| mailpit | mework360-deployer-mailpit | 1025 / 8025 |

Volumes persistentes: `db_data`, `redis_data`, `mailpit_data`.

## Credenciais seed (dev)

| Item | Valor |
|------|-------|
| Painel | `http://localhost:8080` |
| Admin | `admin@mework360.local` / `password` |
| Cluster fake | `dev-cluster-local` — não usar para provision real |

## Produção conhecida

- Host: `deployer.mework360.com.br`
- Validação pendente: F10.3 / ISSUE-023

## Docs relacionados

- `docs/RUNBOOK.md`, `docs/INFRASTRUCTURE.md`, `docs/ISSUES.md` (ISSUE-022, ISSUE-023)
