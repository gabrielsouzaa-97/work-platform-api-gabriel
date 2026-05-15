# Runbook Operacional — mework360-deployer

> Versão: 1.0 (Sprint D8)
> Público-alvo: DevOps/SRE (Marina, Rafael) + Suporte N2 (Sofia)

Este runbook cobre os procedimentos operacionais mais comuns no MVP. Para provisionamento inicial da VM, veja `docs/INFRASTRUCTURE.md §6`.

---

## Índice

1. [Deploy em staging](#1-deploy-em-staging)
2. [Rotação de webhook secret](#2-rotação-de-webhook-secret)
3. [Forçar sincronização de customers](#3-forçar-sincronização-de-customers)
4. [Gerenciar cluster_servers](#4-gerenciar-cluster_servers)
5. [Monitorar fila de jobs](#5-monitorar-fila-de-jobs)
6. [Purge de audit log (LGPD)](#6-purge-de-audit-log-lgpd)
7. [Rollback de migration](#7-rollback-de-migration)
8. [Diagnóstico de problemas comuns](#8-diagnóstico-de-problemas-comuns)

---

## 1. Deploy em staging

### Pré-condições

- [ ] VM `mework360-deployer-vm` provisionada (veja `docs/INFRASTRUCTURE.md §6`)
- [ ] Docker e Docker Compose instalados na VM
- [ ] Acesso SSH à VM configurado
- [ ] Pipeline CI passando (`main` branch verde)

### Passos

```bash
# 1. SSH na VM de staging
ssh operador@<IP-VM-STAGING>

# 2. Clonar ou atualizar o repositório
git clone <repo> /opt/mework360-deployer
# OU, se já existir:
cd /opt/mework360-deployer && git pull origin main

# 3. Configurar .env (primeira vez)
cp .env.example .env
nano .env
# Preencher obrigatoriamente:
#   APP_KEY=           (gerar no próximo passo)
#   APP_ENV=staging
#   APP_DEBUG=false
#   APP_URL=https://painel-staging.mework360.com
#   DB_PASSWORD=<senha forte 32+ chars>
#   DB_HOST=database   (ou IP externo se BD separado)

# 4. Gerar APP_KEY
docker compose run --rm app php artisan key:generate --show
# Cole o valor gerado no APP_KEY do .env

# 5. Build da imagem de produção
docker compose build --target production

# 6. Subir os containers
docker compose up -d

# 7. Aguardar healthchecks (app + db + redis)
docker compose ps
# Todos devem estar "healthy" em ~30s

# 8. Rodar migrations
docker compose exec app php artisan migrate --force

# 9. Rodar seeders (apenas primeira vez)
docker compose exec app php artisan db:seed --force

# 10. Verificar logs
docker compose logs --tail=50 app
```

### Validação pós-deploy (smoke test)

```bash
# Health check da aplicação
curl https://painel-staging.mework360.com/up

# Login no painel via browser
# → https://painel-staging.mework360.com

# Teste de conectividade SSH com upstream (via painel admin)
# Painel → Cluster Servers → [cluster] → Testar Conexão
```

### Rollback rápido

```bash
cd /opt/mework360-deployer
git checkout <tag-anterior>
docker compose build --target production
docker compose up -d
docker compose exec app php artisan migrate:rollback --step=1
```

---

## 2. Rotação de webhook secret

O upstream (`nextcloud-saas-manager`) precisa ser atualizado com o novo secret **antes** de expirar o antigo. O sistema suporta grace period de 24h via `webhook_secret_history`.

### Via painel (recomendado)

1. Acesse `Cluster Servers` → selecione o cluster → `Ações` → `Rotacionar Webhook Secret`
2. O painel gera um novo secret, armazena como active e mantém o anterior válido por 24h
3. Copie o novo secret mostrado na tela (é exibido **uma única vez**)
4. No servidor upstream (`nextcloud-saas-manager`), atualize o secret:

```bash
# No servidor upstream
sudo nextcloud-manage config set webhook_secret "<novo-secret>"
sudo systemctl restart nextcloud-worker   # recarrega configuração
```

5. Aguarde 24h para o secret antigo expirar automaticamente (cron `clean:expired-webhook-secrets` diário às 03:00)

### Verificação

```bash
# Na VM da API — verificar que webhooks estão chegando corretamente
docker compose exec app php artisan tinker
>>> App\Models\AuditLog::where('action', 'webhook_received')->latest()->first()
```

Se `webhook_invalid_signature` aparecer nos logs após a rotação, o upstream ainda está usando o secret antigo — aguarde a propagação ou repita o passo 4.

---

## 3. Forçar sincronização de customers

O sync diário roda às 03:00 via scheduler. Para executar manualmente:

```bash
# Via container
docker compose exec app php artisan customers:sync

# Com output verbose
docker compose exec app php artisan customers:sync -v

# Dry-run (sem escrita no BD) — rodar diretamente pelo tinker se necessário
docker compose exec app php artisan tinker
>>> app(App\Modules\Customers\Services\CustomerSyncService::class)
...
```

### O que o sync faz

1. Conecta ao upstream via SSH e executa `nextcloud-manage list`
2. Compara a lista com a réplica local (`customers` table)
3. Insere novos, atualiza divergentes (status/domain), marca removed os que sumiram
4. Registra cada divergência no `audit_log` (ação `customer_sync_*`)

### Diagnóstico de sync com falha

```bash
# Ver último resultado
docker compose exec app php artisan tinker
>>> App\Models\AuditLog::where('action', 'like', 'customer_sync%')->latest()->limit(10)->get(['action','payload','created_at'])

# Ver logs do SSH client
docker compose exec app tail -n 100 storage/logs/sshclient.log
```

---

## 4. Gerenciar cluster_servers

### Adicionar novo cluster

1. Painel → `Cluster Servers` → `Novo Cluster`
2. Preencher: nome, host SSH (`ssh_host`), porta, usuário SSH (`ncsaas-api`)
3. Colar a chave SSH privada (formato PEM) — será criptografada no banco via `APP_KEY`
4. Configurar o webhook secret inicial
5. Clicar em `Testar Conexão` — deve retornar sucesso antes de salvar

### Testar conectividade SSH manualmente

```bash
docker compose exec app php artisan tinker

>>> $cluster = App\Models\ClusterServer::first();
>>> app(App\Modules\Core\Ssh\SshClientInterface::class)
...     ->run($cluster, 'nextcloud-manage', ['version'], null, 10)
...     ->stdout
```

### Desativar cluster (sem remover)

```bash
docker compose exec app php artisan tinker
>>> App\Models\ClusterServer::find('<uuid>')->update(['status' => 'unreachable'])
```

---

## 5. Monitorar fila de jobs

### Ver estado atual

```bash
docker compose exec app php artisan tinker

# Contagem por estado
>>> App\Models\Job::selectRaw('state, count(*) as n')->groupBy('state')->pluck('n','state')

# Jobs stuck (running > 5 min sem callback)
>>> App\Models\Job::where('state','running')
...     ->whereNull('callback_received_at')
...     ->where('queued_at','<',now()->subMinutes(5))
...     ->get(['job_id','customer_slug','cmd_canonical','queued_at'])
```

### Forçar poll de stuck jobs

```bash
docker compose exec app php artisan jobs:poll-stuck
```

### Cancelar job manualmente

```bash
# Via API REST (autenticado)
curl -X POST https://painel-staging.mework360.com/api/queue/<job_id>/cancel \
  -H "Cookie: <session-cookie>"

# Via tinker (emergência)
docker compose exec app php artisan tinker
>>> $job = App\Models\Job::find('<job_id>');
>>> app(App\Modules\Jobs\Actions\CancelJobAction::class)->execute($job, null)
```

---

## 6. Purge de audit log (LGPD)

O purge mensal roda automaticamente todo dia 1 às 03:30 (`audit:purge`). Para execução manual:

```bash
# Dry-run — ver quantos registros seriam removidos
docker compose exec app php artisan audit:purge --dry-run

# Execução real (padrão: retencao 12 meses)
docker compose exec app php artisan audit:purge

# Retenção customizada (ex: 6 meses para testes)
docker compose exec app php artisan audit:purge --retention-months=6

# Limitar quantidade processada por execução
docker compose exec app php artisan audit:purge --max=50000
```

> O comando é idempotente e seguro para re-execução. Usa `chunkById(1000)` + `whereIn` para minimizar lock contention.

---

## 7. Rollback de migration

```bash
# Rollback da última migration
docker compose exec app php artisan migrate:rollback

# Rollback de N steps
docker compose exec app php artisan migrate:rollback --step=3

# Ver histórico de migrations
docker compose exec app php artisan migrate:status
```

> Nunca usar `migrate:fresh` ou `migrate:reset` em staging/produção — o hook `safety-guard.sh` bloqueia automaticamente.

---

## 8. Diagnóstico de problemas comuns

### Webhook não recebido (job stuck em `queued`)

1. Verificar logs de webhook do upstream
2. Verificar se o IP do upstream está correto em `cluster_servers.ssh_host`
3. Verificar header `X-Cluster-Server-Id` nos logs:

```bash
docker compose exec app grep "webhook" storage/logs/security.log | tail -20
```

4. Forçar poll manual:

```bash
docker compose exec app php artisan jobs:poll-stuck
```

### Erro SSH `Connection refused`

```bash
# Testar conectividade de rede
docker compose exec app nc -zv <ssh_host> 22

# Verificar status do cluster no painel
# → Cluster Servers → [cluster] → Testar Conexão

# Ver logs do SSH client
docker compose exec app tail -50 storage/logs/sshclient.log
```

### 503 `cluster_unreachable` na API

O cluster está com `status != 'active'`. Verificar:

```bash
docker compose exec app php artisan tinker
>>> App\Models\ClusterServer::get(['id','name','status','ssh_host'])
```

Se o cluster estiver `unreachable`, o cron `cluster:health-check` tentará reativar nas próximas execuções. Para forçar:

```bash
docker compose exec app php artisan cluster:health-check
```

### Container `app` não sobe (healthcheck failing)

```bash
docker compose logs app | tail -50
docker compose exec app php artisan about
docker compose exec app php -v
```

Verificar se `APP_KEY` está preenchido no `.env`.

### Resetar sessões de todos os operadores (emergência)

```bash
docker compose exec app php artisan tinker
>>> Illuminate\Support\Facades\DB::table('sessions')->delete()
```

---

## Contatos

| Responsável | Papel | Quando acionar |
|-------------|-------|---------------|
| Marina (DevOps/SRE) | Infra, Docker, SSH | Falha de container, SSH inacessível |
| Rafael (DevOps/SRE) | Deploy, CI/CD, Jobs | Pipeline quebrado, jobs stuck massivo |
| Sofia (Suporte N2) | Operações, Customers | Problemas de provisioning, quota, OCC |

---

*Última atualização: 2026-05-14 (Sprint D8 + correção E1: manage.sh → nextcloud-manage)*
