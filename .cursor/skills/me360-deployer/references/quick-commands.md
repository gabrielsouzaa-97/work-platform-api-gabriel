# Quick commands

> Sempre via container no Windows: `docker compose exec app` (PHP local no host falha).

## Stack

```bash
docker compose up -d
docker compose ps
docker compose logs --tail=50 app
```

## Health

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/up
docker compose exec -T app php artisan about
```

## Testes (gate R1)

```bash
docker compose exec -T app php artisan test --parallel
```

## Migrations

```bash
docker compose exec -T app php artisan migrate --status
docker compose exec -T app php artisan migrate --force
```

## Produção (após APP_KEY no .env)

```bash
docker compose build --target production
docker compose up -d
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
```

## Admin extra

```bash
docker compose exec app php artisan operators:create-admin
```

## Contract test (homolog)

```bash
RUN_UPSTREAM_CONTRACT=1 \
UPSTREAM_CONTRACT_CLUSTER_ID=<uuid> \
UPSTREAM_CONTRACT_CUSTOMER_SLUG=<slug> \
docker compose exec -T app php artisan test --testsuite=Contract
```

## Ordem de deploy (versão coordenada)

1. Upstream se contrato mudou
2. `UpstreamContractTest` se `CMD_TO_CLI_ARGV` mudou
3. API: pull → build production → migrate → config:cache
4. Restart worker upstream se webhook secret rotacionou
5. Checklist ISSUE-023
