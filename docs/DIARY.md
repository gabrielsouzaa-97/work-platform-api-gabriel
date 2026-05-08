# Sprint Diary — mework360-deployer

<!-- DIARY-INDEX -->
| Sprint | Modulos | Temas | Linhas |
|--------|---------|-------|--------|
| D1 | infra, database | scaffold, docker, migrations, models, pest | 14-80 |
<!-- /DIARY-INDEX -->

---

## Sprint D1 — Foundation

**Data**: 2026-05-08
**Status**: CONCLUIDA
**Tasks**: 6/6

### Entregas

- Laravel 12.58.0 scaffolded (composer create-project)
- 5 Docker services healthy: app (PHP 8.3-FPM), nginx, postgres16, redis7, mailpit
- 9 migrations: uuid extension + 8 application tables (operators, cluster_servers, customers, jobs, audit_logs, webhook_secret_history, idempotency_keys, api_keys)
- 8 Eloquent models with casts (encrypted for keys/secrets, array for JSONB)
- DatabaseSeeder: admin operator + dev cluster_server
- Pest 3.x installed + smoke test GET /up → 200 PASS

### Decisões Técnicas

1. **predis/predis em vez de phpredis**: PECL network bloqueado no ambiente Docker build. `predis/predis ^3.4` é a alternativa pura PHP suportada pelo Laravel. REDIS_CLIENT=predis no .env.
2. **Xdebug removido do Dockerfile dev**: PECL também inacessível. Comentário inline explica como instalar manualmente quando o ambiente permitir.
3. **vendor_data volume removido**: No Linux, o bind mount (`.:/var/www/html`) é suficiente. O volume nomeado causava `vendor/` vazia no container na primeira subida.
4. **QUEUE_CONNECTION=redis**: Evita conflito de nome com a tabela `jobs` da aplicação (Nextcloud jobs vs Laravel queue jobs). Queue Redis não precisa de migration.
5. **Test DB PostgreSQL**: phpunit.xml configurado para usar `mework360_deployer_test` (PostgreSQL) ao invés de SQLite in-memory, para compatibilidade com uuid-ossp extension e jsonb columns.
6. **DB::raw('uuid_generate_v4()')** como default em PKs UUID: Requer extensão uuid-ossp (habilitada na primeira migration). Alternativa pgcrypto descartada (não disponível em todas as images).

### Problemas Encontrados

- **git commit bloqueado**: Hook `rtk-rewrite.sh` do sistema Cursor retorna JSON inválido para comandos `git commit`. Commits não realizados durante a sprint. Workaround: acumular no branch, push único ao final.
- **nginx health check**: Primeira chamada `/up` retornava 500 antes do app estar totalmente aquecido. Resolvido com start_period=60s (já configurado). Depois de 7 minutos, todos os serviços ficaram healthy.

### Gate da Sprint

- [x] `docker-compose up` → 5 services healthy
- [x] `php artisan migrate` → 9 migrations sem erro
- [x] `php artisan migrate:rollback` → rollback na ordem inversa sem erro
- [x] `php artisan migrate:fresh --seed` → migrations + seeder ok
- [x] Pest smoke test `GET /up` → 200 PASS

### Próximos Passos (D2)

- Implementar SshClient (critica: true — Best-of-N)
- Implementar JobTypeTranslator e StateTranslator (15 verbs + 5 estados)
- Implementar SlugValidator (rejeita underscore com 422)
