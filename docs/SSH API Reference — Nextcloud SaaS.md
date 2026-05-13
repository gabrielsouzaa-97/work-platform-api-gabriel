# SSH API Reference — Nextcloud SaaS Manager

> **Para IAs implementando uma REST API:** Este documento descreve a interface completa do
> `nextcloud-manage` (alias de `/opt/nextcloud-customers/scripts/manage.sh`) para que você
> possa criar endpoints REST que invocam os scripts via SSH e traduzem a resposta para JSON.

---

## 1. Visão Geral da Arquitetura

```
REST API (seu projeto)
    │
    │  SSH (root@177.104.164.187)
    ▼
nextcloud-manage <args> [--json] [--async] [flags globais]
    │
    ├── Modo síncrono  → executa e retorna resultado imediatamente
    └── Modo assíncrono → enfileira no Redis (DB 16) → retorna job_id
             │
             └── Worker daemon (systemd: nextcloud-saas-worker)
                  processa jobs em background
                  grava logs em /opt/nextcloud-customers/jobs/<job_id>/output.log
```

### Infraestrutura no servidor

| Componente              | Localização                                         |
| ----------------------- | --------------------------------------------------- |
| Script principal        | `/opt/nextcloud-customers/scripts/manage.sh`        |
| Symlink de invocação    | `/usr/local/bin/nextcloud-manage`                   |
| Libs                    | `/opt/nextcloud-customers/scripts/lib/`             |
| Diretório dos tenants   | `/opt/nextcloud-customers/<client>/`                |
| Logs dos jobs           | `/opt/nextcloud-customers/jobs/<job_id>/output.log` |
| Fila Redis              | `redis DB 16`, prefixo `nc:`                        |
| Serviços compartilhados | `/opt/shared-services/`                             |

---

## 2. Invocação SSH

### Padrão básico

```bash
ssh root@177.104.164.187 "nextcloud-manage <args> [flags]"
```

### Com JSON estruturado (sempre usar na API)

```bash
ssh root@177.104.164.187 "nextcloud-manage <args> --json"
```

### Com payload via stdin (para senhas — nunca em argv)

```bash
echo '{"password":"s3cr3t"}' | ssh root@177.104.164.187 \
  "nextcloud-manage <client> occ-exec user:add <username> --payload-stdin --json"
```

### Capturar exit code

```bash
output=$(ssh root@177.104.164.187 "nextcloud-manage <args> --json"; echo "EXIT:$?")
json="${output%EXIT:*}"
exit_code="${output##*EXIT:}"
```

---

## 3. Assinatura dos Comandos

### 3.1 Sintaxe geral (legado posicional)

```
nextcloud-manage <client> <domain|_> <cmd> [flags]
```

- `<client>` — nome do tenant: `^[a-z0-9-]{1,64}$` (sem underscore)
- `<domain|_>` — FQDN do tenant para `create`/`restore`; `_` para os demais
- `<cmd>` — comando a executar (lista na seção 4)
- `[flags]` — flags globais (seção 3.2)

### 3.2 Flags globais

| Flag                       | Tipo    | Descrição                                          |
| -------------------------- | ------- | -------------------------------------------------- |
| `--json`                   | boolean | Saída estruturada JSON (obrigatório para API)      |
| `--async`                  | boolean | Enfileirar job em vez de executar sync             |
| `--dry-run`                | boolean | Simular sem efeitos colaterais                     |
| `--idempotency-key=<uuid>` | string  | UUID v4 lowercase para deduplicação (janela 24h)   |
| `--callback=<url>`         | string  | URL HTTPS para webhook ao completar job            |
| `--staging-id=<uuid>`      | string  | ID de staging para inbox SCP (feature avançada)    |
| `--confirm=<client>`       | string  | Confirmação alternativa ao `--force` (para remove) |
| `--force`                  | boolean | Pular confirmação interativa (remove)              |
| `--payload-stdin`          | boolean | Ler payload JSON de stdin (senhas via occ-exec)    |
| `--strict`                 | boolean | Falha em warnings de validação                     |
| `--apps=<csv>`             | string  | Instalar apps adicionais no create                 |
| `--full-apps`              | boolean | Instalar suite completa de apps no create          |
| `--backup-first`           | boolean | Fazer backup antes de remove                       |

### 3.3 Sintaxe hierárquica (namespaces)

```
nextcloud-manage <client> <namespace> <verb> [args] [flags]
```

Namespaces disponíveis: `user`, `group`, `apps`, `occ-exec`

### 3.4 Sintaxe de introspection

```
nextcloud-manage worker status [--json]
nextcloud-manage worker stats [--by-cmd] [--by-client] [--json]
nextcloud-manage job <job_id> status [--json]
nextcloud-manage job <job_id> logs
nextcloud-manage job <job_id> cancel [--json]
nextcloud-manage job list [--state=<s>] [--client=<c>] [--cmd=<c>] [--limit=N] [--offset=N] [--json]
nextcloud-manage list
nextcloud-manage shared-status
nextcloud-manage health [--json]
```

---

## 4. Referência Completa de Comandos

### 4.1 `create` — Provisionar novo tenant

```bash
nextcloud-manage <client> <domain> create [--async] [--json]
```

**Parâmetros:**

- `client` — nome único do tenant (slug)
- `domain` — FQDN completo (ex: `empresa.mework360.com.br`)

**O que faz:**

1. Valida DNS (avisa se não aponta para o servidor)
2. Gera senhas aleatórias (DB, admin, Redis DB isolado)
3. Cria banco MariaDB isolado
4. Gera `docker-compose.yml` e `.env` em `/opt/nextcloud-customers/<client>/`
5. Sobe containers (`app`, `cron`, `harp`)
6. Aguarda Nextcloud estar pronto (timeout 180s)
7. Configura Redis, memcache, trusted proxies, Collabora, Talk, AppAPI
8. Instala apps: `richdocuments calendar contacts mail deck forms notes tasks groupfolders photos activity spreed app_api notify_push`
9. Atualiza allowlists dos serviços compartilhados

**Duração típica:** 5–15 minutos → **usar `--async` obrigatoriamente**

**Saída sync (--json):**

```
# Saída livre (logs de progresso) — não parsear
# Ao final: "Instância '<client>' criada com sucesso!"
```

**Saída async (--json):**

```json
{
    "schema_version": "1",
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "state": "queued",
    "cmd": "create",
    "client": "empresa",
    "domain": "empresa.mework360.com.br",
    "args_json": [
        "nextcloud-manage",
        "empresa",
        "empresa.mework360.com.br",
        "create"
    ],
    "queued_at": "2026-05-13T04:00:00Z"
}
```

**Exit codes:**

- `0` — sucesso (sync) ou enfileirado (async)
- `1` — erro geral (instância já existe, falha de setup)
- `3` — conflito de idempotência (`idempotency_conflict`)
- `5` — validação falhou

---

### 4.2 `remove` — Remover tenant permanentemente

```bash
nextcloud-manage <client> _ remove [--force] [--async] [--backup-first] [--json]
```

**O que faz:**

1. Para e remove containers (`docker compose down -v`)
2. Dropa banco MariaDB e usuário
3. Remove diretório `/opt/nextcloud-customers/<client>/`
4. Atualiza allowlists dos serviços compartilhados

**⚠️ Irreversível** — dados perdidos permanentemente.

**Flags especiais:**

- `--force` — obrigatório em modo não-interativo (API/async)
- `--backup-first` + `--async` — faz backup local antes de remover (job composto)

**Saída async (--json):**

```json
{
    "schema_version": "1",
    "job_id": "...",
    "state": "queued",
    "cmd": "remove",
    "client": "empresa",
    "queued_at": "2026-05-13T04:00:00Z"
}
```

**Exit codes:**

- `0` — sucesso
- `1` — instância não encontrada
- `5` — confirmação faltando (use `--force`)

---

### 4.3 `status` — Status dos containers do tenant

```bash
nextcloud-manage <client> _ status
```

**Saída:** texto livre com status de cada container (running/exited/not found).
_Não suporta `--json` ainda — parsear com grep se necessário._

**Exit codes:** `0` sempre (mesmo se container parado)

---

### 4.4 `credentials` — Credenciais do tenant

```bash
nextcloud-manage <client> _ credentials
```

**Saída:** texto com URL, usuário admin, senha, dados do banco.

```
=== Credenciais da Instância: empresa ===
URL: https://empresa.mework360.com.br
Usuário: admin
Senha: <gerada>
Database: nextcloud_empresa
Database user: nc_empresa
Database password: <gerada>
Redis DB: 5
```

**⚠️ Sensível** — só expor via endpoint autenticado na sua API.

---

### 4.5 `backup` — Backup local (tar.gz)

```bash
nextcloud-manage <client> _ backup [--async] [--json]
```

**Saída em `/opt/nextcloud-customers/backups/<client>_<timestamp>.tar.gz`**

---

### 4.6 `restore` — Restaurar de backup

```bash
nextcloud-manage <client> <backup_path> restore [--async] [--json]
```

- `backup_path` — caminho absoluto do arquivo `.tar.gz` no servidor

---

### 4.7 `stop` / `start` / `update`

```bash
nextcloud-manage <client> _ stop   [--async] [--json]
nextcloud-manage <client> _ start  [--async] [--json]
nextcloud-manage <client> _ update [--async] [--json]
```

`update` faz backup automático, atualiza imagem Docker e roda migrações.

---

### 4.8 `backup-offsite` — Backup remoto via Restic

```bash
nextcloud-manage <client> _ backup-offsite [--dry-run] [--json]
```

**Saída JSON (--json):**

```json
{
    "schema_version": "1",
    "operation": "backup_offsite",
    "client": "empresa",
    "status": "ok",
    "snapshot_id": "abc123",
    "files_new": 42,
    "bytes_added": 104857600,
    "duration_s": 38
}
```

**Exit codes:**

- `0` — backup concluído
- `1` — erro geral
- `12` — secrets de offsite não configurados no servidor

---

### 4.9 `health` — Health check consolidado

```bash
nextcloud-manage health [--json]
```

**Saída JSON:**

```json
{
    "schema_version": "1",
    "checks": [
        {
            "name": "docker",
            "status": "ok",
            "message": "daemon running",
            "duration_ms": 45
        },
        {
            "name": "redis",
            "status": "ok",
            "message": "PONG",
            "duration_ms": 12
        },
        {
            "name": "db",
            "status": "ok",
            "message": "connected",
            "duration_ms": 28
        }
    ],
    "summary": { "ok": 3, "warn": 0, "fail": 0 }
}
```

**Exit codes:**

- `0` — todos ok
- `1` — há warnings
- `2` — há failures

---

### 4.10 `list` — Listar todos os tenants

```bash
nextcloud-manage list
```

**Saída:** tabela texto (nome, domínio, status).
_Sem suporte a `--json` — parsear por linha se necessário._

---

### 4.11 `occ-exec` — Executar comando occ no tenant

```bash
nextcloud-manage <client> occ-exec <subcmd> [args] [--json]
# Para comandos com senha:
echo '{"password":"s3cr3t"}' | nextcloud-manage <client> occ-exec user:add <username> --payload-stdin --json
```

**Subcmds suportados (com --payload-stdin):** `user:add`, `user:resetpassword`

**⚠️ Senhas NUNCA em argv** — sempre via `--payload-stdin` com JSON `{"password":"..."}`.

**Exit codes:**

- `0` — sucesso
- `5` — validação (falta `--payload-stdin`, payload inválido)

---

### 4.12 `upgrade-harp` — Migrar tenant para socket-proxy

```bash
nextcloud-manage <client> _ upgrade-harp [--dry-run] [--json]
```

**Saída JSON:**

```json
{
    "schema_version": "1",
    "operation": "upgrade-harp",
    "client": "empresa",
    "status": "updated"
}
```

---

## 5. Sistema de Jobs Assíncronos

### 5.1 Ciclo de vida de um job

```
[queued] → [running] → [done]
                    ↘ [failed]
[queued] → [cancelled]
```

### 5.2 Estados possíveis

| Estado      | Descrição                       |
| ----------- | ------------------------------- |
| `queued`    | Aguardando na fila Redis        |
| `running`   | Sendo executado pelo worker     |
| `done`      | Concluído com sucesso           |
| `failed`    | Falhou (ver `exit_code` e logs) |
| `cancelled` | Cancelado antes de iniciar      |

### 5.3 Consultar status de um job

```bash
ssh root@177.104.164.187 "nextcloud-manage job <job_id> status --json"
```

**Saída:**

```json
{
    "schema_version": "1",
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "state": "done",
    "cmd": "create",
    "client": "empresa",
    "domain": "empresa.mework360.com.br",
    "args_json": [
        "nextcloud-manage",
        "empresa",
        "empresa.mework360.com.br",
        "create"
    ],
    "queued_at": "2026-05-13T04:00:00Z",
    "started_at": "2026-05-13T04:00:05Z",
    "finished_at": "2026-05-13T04:12:33Z",
    "exit_code": 0
}
```

### 5.4 Consultar logs de um job

```bash
ssh root@177.104.164.187 "nextcloud-manage job <job_id> logs"
```

**Saída:** texto livre com todo o output do job (stdout + stderr).

### 5.5 Cancelar um job

```bash
ssh root@177.104.164.187 "nextcloud-manage job <job_id> cancel --json"
```

Só funciona se `state == queued`. Se já `running`, retorna erro.

**Saída:**

```json
{ "schema_version": "1", "job_id": "...", "state": "cancelled" }
```

### 5.6 Listar jobs

```bash
ssh root@177.104.164.187 "nextcloud-manage job list --json [--state=queued] [--client=empresa] [--cmd=create] [--limit=20] [--offset=0]"
```

**Saída:** array JSON de objetos de job.

### 5.7 Status do worker

```bash
ssh root@177.104.164.187 "nextcloud-manage worker status --json"
```

**Saída:**

```json
{
    "schema_version": "1",
    "queue_depth": 2,
    "current_job": "550e8400-...",
    "worker_pid": 12345,
    "uptime_s": 3600
}
```

### 5.8 Estratégia de polling recomendada (para sua API)

```
POST /tenants              → enfileirar create --async → retornar { job_id }
GET  /jobs/{id}            → consultar status
GET  /jobs/{id}/logs       → consultar logs
DELETE /tenants/{client}   → enfileirar remove --async → retornar { job_id }
```

**Polling exponencial sugerido:**

```
t=0s   → primeiro check (geralmente ainda queued)
t=10s  → segundo check
t=30s  → terceiro
t=60s  → a cada 60s até done/failed (create leva 5–15min)
```

---

## 6. Idempotência

Usar `--idempotency-key=<uuid-v4>` para garantir que chamadas duplicadas não criem jobs duplicados.

```bash
ssh root@177.104.164.187 \
  "nextcloud-manage empresa empresa.mework360.com.br create --async --json \
   --idempotency-key=6ba7b810-9dad-11d1-80b4-00c04fd430c8"
```

**Comportamento:**

- Primeira chamada: cria job → retorna `{"job_id":"...","state":"queued"}`
- Segunda chamada (mesma key + mesmos args): retorna o job existente com `"idempotent":true`
- Segunda chamada (mesma key + args diferentes): retorna erro `idempotency_conflict` (exit 3)

**Janela:** 24 horas por padrão.

---

## 7. Webhooks (Callbacks)

```bash
ssh root@177.104.164.187 \
  "nextcloud-manage empresa _ backup --async --json \
   --callback=https://api.suaprojeto.com/webhooks/nc-jobs"
```

O worker fará `POST` na URL com o payload do job quando concluir (done/failed).

**Payload do callback:**

```json
{
    "job_id": "...",
    "state": "done",
    "cmd": "backup",
    "client": "empresa",
    "exit_code": 0,
    "finished_at": "2026-05-13T04:12:33Z"
}
```

**Requisito:** URL deve ser `https://` (IPs RFC 1918 rejeitados por segurança).

---

## 8. Formato de Erros

Todos os erros com `--json` retornam:

```json
{
    "schema_version": "1",
    "error": "<código>",
    "message": "<descrição legível>",
    "retry_after": 30
}
```

### Códigos de erro frequentes

| Código                        | Exit | Descrição                             |
| ----------------------------- | ---- | ------------------------------------- |
| `requires_root`               | 1    | Script deve ser executado como root   |
| `client_not_found`            | 1    | Tenant não existe                     |
| `client_already_exists`       | 1    | Tenant já existe (create)             |
| `unknown_command`             | 1    | Comando não reconhecido               |
| `redis_unavailable`           | 1    | Redis inacessível                     |
| `job_not_found`               | 1    | Job ID não existe no Redis            |
| `job_not_cancellable`         | 5    | Job não está em estado `queued`       |
| `async_not_supported_for_cmd` | 5    | Comando não suporta --async           |
| `confirm_required`            | 5    | Remove sem --force                    |
| `idempotency_conflict`        | 3    | Key usada com args diferentes         |
| `invalid_idempotency_key`     | 5    | Key não é UUID v4 lowercase           |
| `password_in_argv_forbidden`  | 5    | Senha passada em argumento            |
| `payload_stdin_required`      | 5    | occ-exec requer --payload-stdin       |
| `not_implemented_yet`         | 99   | Namespace/verb não implementado ainda |

---

## 9. Validações de Entrada

| Campo                        | Regex               | Notas                                           |
| ---------------------------- | ------------------- | ----------------------------------------------- |
| `client`                     | `^[a-z0-9-]{1,64}$` | Sem underscore, sem maiúsculas                  |
| `domain`                     | FQDN padrão         | `^[a-z0-9][...]+\.[a-z0-9]+$`, max 253 chars    |
| `job_id` / `idempotency-key` | UUID v4 lowercase   | `^[0-9a-f]{8}-...-4[0-9a-f]{3}-[89ab]...`       |
| `callback` URL               | `^https://`         | IPs RFC 1918 rejeitados (SSRF defense)          |
| Senha                        | —                   | **Nunca em argv**, sempre via `--payload-stdin` |

---

## 10. Mapeamento REST → SSH Sugerido

```
POST   /api/v1/tenants                 → create --async --json --idempotency-key=<uuid>
GET    /api/v1/tenants                 → list (parsear texto)
GET    /api/v1/tenants/{client}        → status (parsear texto)
DELETE /api/v1/tenants/{client}        → remove --force --async --json

GET    /api/v1/tenants/{client}/credentials → credentials (parsear texto, endpoint protegido)
POST   /api/v1/tenants/{client}/start       → start --async --json
POST   /api/v1/tenants/{client}/stop        → stop --async --json
POST   /api/v1/tenants/{client}/backup      → backup --async --json
POST   /api/v1/tenants/{client}/backup-offsite → backup-offsite --json
POST   /api/v1/tenants/{client}/restore     → restore <backup_path> --async --json

POST   /api/v1/tenants/{client}/users       → occ-exec user:add <username> --payload-stdin --json
PUT    /api/v1/tenants/{client}/users/{u}/password → occ-exec user:resetpassword <u> --payload-stdin --json

GET    /api/v1/jobs/{job_id}           → job <id> status --json
GET    /api/v1/jobs/{job_id}/logs      → job <id> logs
DELETE /api/v1/jobs/{job_id}           → job <id> cancel --json
GET    /api/v1/jobs                    → job list --json [filters]

GET    /api/v1/health                  → health --json
GET    /api/v1/worker/status           → worker status --json
GET    /api/v1/worker/stats            → worker stats --json
```

---

## 11. Tratamento de Erros SSH na API

| Cenário                        | Exit SSH | Ação recomendada                 |
| ------------------------------ | -------- | -------------------------------- |
| Sucesso                        | 0        | Retornar 200/201/202             |
| `client_not_found`             | 1        | Retornar 404                     |
| `client_already_exists`        | 1        | Retornar 409 Conflict            |
| `confirm_required` / validação | 5        | Retornar 422 Unprocessable       |
| `idempotency_conflict`         | 3        | Retornar 409 com job existente   |
| `not_implemented_yet`          | 99       | Retornar 501 Not Implemented     |
| Timeout SSH (> 30min)          | —        | Retornar 504 Gateway Timeout     |
| Falha na conexão SSH           | —        | Retornar 503 Service Unavailable |

---

## 12. Segurança

- O script **exige root** no servidor. Usar chave SSH dedicada para a API com `authorized_keys` restrito
- **Senhas nunca em argumentos SSH** — usar `--payload-stdin`
- Os logs de jobs sanitizam automaticamente valores de secrets (substituídos por `***`)
- Validar `client` e `domain` no lado da API **antes** de invocar SSH (mesmas regras da seção 9)
- O servidor rejeita callbacks para IPs RFC 1918 (proteção SSRF)

---

## 13. Estrutura do Tenant no Servidor

Após `create`, o servidor terá:

```
/opt/nextcloud-customers/<client>/
├── .env              # CLIENT_NAME, DOMAIN, REDIS_DB (sem secrets)
├── .credentials      # credenciais completas (chmod 600)
├── docker-compose.yml
├── app/              # volume Nextcloud
└── harp-certs/       # certificados HaRP
```

---

## 14. Comandos Async Permitidos

Só estes comandos suportam `--async`:

```
create, remove, backup, restore, update, stop, start,
user-create, user-remove, user-modify,
group-create, group-remove, group-modify,
apps-enable, apps-disable,
create-extended, remove-extended
```

`occ-exec`, `status`, `credentials`, `list`, `health`, `worker status`, `job *` são sempre síncronos.

---

## 15. Exemplo Completo — Provisionar Tenant

```bash
# 1. Enfileirar criação
output=$(ssh root@177.104.164.187 \
  "nextcloud-manage empresa empresa.mework360.com.br create --async --json \
   --idempotency-key=$(uuidgen | tr '[:upper:]' '[:lower:]') \
   --callback=https://minha-api.com/webhooks/nc"
)
job_id=$(echo "$output" | jq -r '.job_id')

# 2. Polling até done/failed
while true; do
  status=$(ssh root@177.104.164.187 "nextcloud-manage job $job_id status --json")
  state=$(echo "$status" | jq -r '.state')
  case "$state" in
    done)    echo "Sucesso!"; break ;;
    failed)  echo "Falhou! Exit: $(echo $status | jq '.exit_code')"; break ;;
    queued|running) sleep 30 ;;
    *)       echo "Estado desconhecido: $state"; break ;;
  esac
done

# 3. Buscar credenciais
creds=$(ssh root@177.104.164.187 "nextcloud-manage empresa _ credentials")
```
