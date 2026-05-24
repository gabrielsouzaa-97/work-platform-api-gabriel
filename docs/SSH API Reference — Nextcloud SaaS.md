# SSH API Reference — meWork360 Deployer Scripts

> **Para IAs implementando uma REST API:** Este documento descreve a interface completa do
> `nextcloud-manage` (alias de `/opt/nextcloud-customers/scripts/manage.sh`) para que você
> possa criar endpoints REST que invocam os scripts via SSH e traduzem a resposta para JSON.

---

## 1. Visão Geral da Arquitetura

### Fluxo completo com o shim

```
REST API (seu projeto)
    │
    ├── Canal A: ncsaas-api (chave Ed25519 shim)
    │     SSH como usuário ncsaas-api
    │     ▼
    │   ┌─────────────────────────────────────────────────────────────┐
    │   │ Servidor 177.104.164.187                                     │
    │   │                                                             │
    │   │  sshd (ForceCommand)                                        │
    │   │    └─► /usr/local/bin/ncsaas-api-shim   ← portão de segurança
    │   │              │  valida, audita, rejeita injeção             │
    │   │              │                                              │
    │   │              └─► sudo -n nextcloud-manage <args>            │
    │   │                        │                                    │
    │   │              ┌─────────┴─────────┐                          │
    │   │              │                   │                          │
    │   │         Modo síncrono      Modo --async                     │
    │   │         executa agora       enfileira no Redis DB 16        │
    │   │         retorna resultado   retorna { job_id }              │
    │   │                                   │                         │
    │   │                     Worker daemon (systemd)                  │
    │   │                     processa jobs em background              │
    │   │                     logs em /opt/nextcloud-customers/jobs/   │
    │   └─────────────────────────────────────────────────────────────┘
    │
    └── Canal B: ncsaas-sftp (chave Ed25519 SFTP — Feature O.5)
          SSH/SCP como usuário ncsaas-sftp
          ▼
        ┌─────────────────────────────────────────────────────────────┐
        │  sshd (ForceCommand internal-sftp)                          │
        │    ChrootDirectory /opt/nextcloud-customers/inbox           │
        │    → apenas upload de arquivos de branding                  │
        │    → sem shell, sem exec, sem TCP forwarding                │
        └─────────────────────────────────────────────────────────────┘
```

### Infraestrutura no servidor

| Componente | Localização |
|---|---|
| Script principal | `/opt/nextcloud-customers/scripts/manage.sh` |
| Symlink de invocação | `/usr/local/bin/nextcloud-manage` |
| **Shim SSH (Canal A)** | `/usr/local/bin/ncsaas-api-shim` |
| Usuário SSH da API (Canal A) | `ncsaas-api` (sistema, com shell `/bin/bash` para ForceCommand) |
| Chaves autorizadas Canal A | `/home/ncsaas-api/.ssh/authorized_keys` |
| Config sshd Canal A | `/etc/ssh/sshd_config.d/50-ncsaas-api.conf` |
| Regra sudo | `/etc/sudoers.d/ncsaas-api` |
| **Usuário SFTP (Canal B — Feature O.5)** | `ncsaas-sftp` (sistema, `nologin`) |
| Chaves autorizadas Canal B | `/etc/ssh/ncsaas-sftp-authorized_keys` |
| Config sshd Canal B | `/etc/ssh/sshd_config.d/51-ncsaas-api-sftp.conf` |
| Inbox SFTP (raiz do chroot) | `/opt/nextcloud-customers/inbox` (`root:root 0755`) |
| Subdirs de staging | `/opt/nextcloud-customers/inbox/<staging-id>/` (`ncsaas-sftp:ncsaas-sftp 0700`) |
| Libs | `/opt/nextcloud-customers/scripts/lib/` |
| Diretório dos tenants | `/opt/nextcloud-customers/<client>/` |
| Logs dos jobs | `/opt/nextcloud-customers/jobs/<job_id>/output.log` |
| Staging consumido | `/opt/nextcloud-customers/jobs/<job_id>/staging/` |
| Fila Redis | `redis DB 16`, prefixo `nc:` |
| Metadados inbox Redis | `nc:inbox:<staging-id>` (TTL 24h) |
| Serviços compartilhados | `/opt/shared-services/` |

---

## 1.5 O `ncsaas-api-shim` — Portão de Segurança SSH

### O que é

O `ncsaas-api-shim` é o único programa que o usuário `ncsaas-api` pode executar no servidor.
Ele fica entre a sua API REST e o `nextcloud-manage`, garantindo que nenhum comando arbitrário
possa ser executado mesmo que a chave SSH seja comprometida.

Sem ele, qualquer pessoa com a chave SSH teria um shell root completo no servidor. Com ele, só
é possível chamar `nextcloud-manage` com verbos da allowlist.

### Arquitetura de três camadas

```
Camada 1 — authorized_keys
  command="/usr/local/bin/ncsaas-api-shim",no-pty,...
  → SSH já redireciona QUALQUER tentativa de login para o shim
  → Mesmo que o sshd falhe em aplicar ForceCommand, a chave bloqueia

Camada 2 — sshd ForceCommand (/etc/ssh/sshd_config.d/50-ncsaas-api.conf)
  ForceCommand /usr/local/bin/ncsaas-api-shim
  → Reforço redundante: mesmo sem command= no authorized_keys, sshd força o shim

Camada 3 — sudo restrito (/etc/sudoers.d/ncsaas-api)
  ncsaas-api ALL=(root) NOPASSWD: /usr/local/bin/nextcloud-manage
  → O shim só consegue elevar para root chamando ESTE binário
  → Qualquer outro sudo retorna "not allowed"
```

### O que o shim faz em ordem

```
1. Captura SSH_ORIGINAL_COMMAND (o que sua API enviou)
2. Sanitiza para log (mascara --password=VALUE → --password=***)
3. Grava audit log NDJSON no journald (tag: ncsaas-api-ssh)
4. Valida segurança — rejeita se:
   a. Comando vazio             → exit 100 (tentativa de shell interativo)
   b. Metacaracteres de shell   → exit 100 (tentativa de injeção: ; | & $ ` \)
   c. argv[0] ≠ nextcloud-manage → exit 101
   d. --password em argumentos  → exit 5   (senha deve ir por stdin)
   e. -- como primeiro argumento → exit 100 (bypass de allowlist)
   f. Verbo/cmd não na allowlist → exit 101
5. Grava "accept" no audit log
6. exec sudo -n nextcloud-manage <args>  ← nunca volta ao shim
```

### Verbos permitidos pelo shim

**Top-level (sem client):**
```
list, shared-status, worker, job, health, upgrade-harp
```

**Legacy posicional** (`<client> <domain> <cmd>`):
```
create, backup, restore, stop, start, update, remove, status, credentials
```

**Namespaces** (`<client> <namespace> <verb>`):
```
user   → create, remove, modify
group  → create, remove, modify
apps   → enable, disable
occ-exec → subcmd validado internamente pelo script
```

Qualquer outro verbo retorna:
```json
{"error":"cmd_not_allowed","cmd":"<tentativa>"}
```

### Como invocar via SSH usando o shim

**Antes (direto como root — sem shim):**
```bash
ssh root@177.104.164.187 "nextcloud-manage empresa _ status --json"
```

**Agora (via usuário ncsaas-api + shim — recomendado para a API):**
```bash
ssh ncsaas-api@177.104.164.187 "nextcloud-manage empresa _ status --json"
```

O comando que sua API envia é exatamente o mesmo. A diferença é o usuário SSH e o nível de
segurança no servidor.

### Como instalar no servidor

```bash
# 1. Criar usuário sem shell
useradd -r -m -d /home/ncsaas-api -s /usr/sbin/nologin ncsaas-api

# 2. Criar authorized_keys com a chave pública da sua API
install -d -m 0700 -o ncsaas-api -g ncsaas-api /home/ncsaas-api/.ssh
echo 'command="/usr/local/bin/ncsaas-api-shim",no-pty,no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-user-rc ssh-ed25519 AAAA...chave... api-prod-2026' \
  > /home/ncsaas-api/.ssh/authorized_keys
chmod 600 /home/ncsaas-api/.ssh/authorized_keys

# 3. Instalar configuração sshd
cp ssh/50-ncsaas-api.sshd.conf /etc/ssh/sshd_config.d/50-ncsaas-api.conf
systemctl reload ssh

# 4. Instalar sudoers
cp ssh/ncsaas-api.sudoers /etc/sudoers.d/ncsaas-api
chmod 0440 /etc/sudoers.d/ncsaas-api
visudo -c  # validar antes de usar

# 5. Instalar o shim
cp scripts/ncsaas-api-shim /usr/local/bin/ncsaas-api-shim
chmod 755 /usr/local/bin/ncsaas-api-shim
```

### Audit log — como monitorar

Toda invocação gera uma linha NDJSON no journald:

```bash
# Ver em tempo real
journalctl -t ncsaas-api-ssh -f

# Filtrar rejeições
journalctl -t ncsaas-api-ssh -o json | jq 'select(.MESSAGE | contains("reject"))'
```

Formato de cada linha:
```json
{"event":"invoke",  "key_id":"SHA256:abc...", "client_ip":"203.0.113.5", "command":"nextcloud-manage empresa _ status --json"}
{"event":"accept",  "key_id":"SHA256:abc...", "client_ip":"203.0.113.5", "command":"nextcloud-manage empresa _ status --json"}
{"event":"reject",  "reason":"metachar",      "key_id":"SHA256:abc...", "client_ip":"203.0.113.5", "command":"nextcloud-manage empresa _ status; rm -rf /"}
```

O campo `key_id` é o fingerprint SHA256 da chave SSH usada. Use-o para rotação de chaves —
confirme no log que a chave antiga parou de aparecer antes de removê-la do `authorized_keys`.

### Rotação de chave SSH

```bash
# 1. Adicionar nova chave (mantém a antiga ativa durante a transição)
echo 'command="...",no-pty,... ssh-ed25519 AAAA...nova-chave... api-prod-2027' \
  >> /home/ncsaas-api/.ssh/authorized_keys

# 2. Atualizar a API para usar a nova chave
# 3. Confirmar no journald que a chave antiga parou de aparecer
journalctl -t ncsaas-api-ssh | grep "SHA256:fingerprint-da-chave-antiga"

# 4. Remover a linha da chave antiga do authorized_keys
```

### Configurações de segurança do sshd aplicadas

| Diretiva | Valor | Por que |
|---|---|---|
| `ForceCommand` | `ncsaas-api-shim` | Impede shell mesmo com chave válida |
| `PermitTTY` | `no` | Sem shell interativo |
| `AllowTcpForwarding` | `no` | Sem tunelamento de portas |
| `PermitTunnel` | `no` | Sem VPN via SSH |
| `AllowAgentForwarding` | `no` | Sem encadeamento de chaves |
| `PasswordAuthentication` | `no` | Só chave pública |
| `MaxSessions` | `4` | Limita paralelismo |
| `LoginGraceTime` | `15s` | Defesa contra lentidão proposital |
| `ClientAliveInterval` | `30s` | Detecta conexões penduradas |

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

| Flag | Tipo | Descrição |
|---|---|---|
| `--json` | boolean | Saída estruturada JSON (obrigatório para API) |
| `--async` | boolean | Enfileirar job em vez de executar sync |
| `--dry-run` | boolean | Simular sem efeitos colaterais |
| `--idempotency-key=<uuid>` | string | UUID v4 lowercase para deduplicação (janela 24h) |
| `--callback=<url>` | string | URL HTTPS para webhook ao completar job |
| `--staging-id=<uuid>` | string | ID de staging para inbox SCP (feature avançada) |
| `--confirm=<client>` | string | Confirmação alternativa ao `--force` (para remove) |
| `--force` | boolean | Pular confirmação interativa (remove) |
| `--payload-stdin` | boolean | Ler payload JSON de stdin (senhas via occ-exec) |
| `--strict` | boolean | Falha em warnings de validação |
| `--apps=<csv>` | string | Instalar apps adicionais no create |
| `--full-apps` | boolean | Instalar suite completa de apps no create |
| `--backup-first` | boolean | Fazer backup antes de remove |

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
  "args_json": ["nextcloud-manage","empresa","empresa.mework360.com.br","create"],
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
*Não suporta `--json` ainda — parsear com grep se necessário.*

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
    {"name": "docker", "status": "ok", "message": "daemon running", "duration_ms": 45},
    {"name": "redis",  "status": "ok", "message": "PONG",          "duration_ms": 12},
    {"name": "db",     "status": "ok", "message": "connected",     "duration_ms": 28}
  ],
  "summary": {"ok": 3, "warn": 0, "fail": 0}
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
*Sem suporte a `--json` — parsear por linha se necessário.*

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
{"schema_version":"1","operation":"upgrade-harp","client":"empresa","status":"updated"}
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

| Estado | Descrição |
|---|---|
| `queued` | Aguardando na fila Redis |
| `running` | Sendo executado pelo worker |
| `done` | Concluído com sucesso |
| `failed` | Falhou (ver `exit_code` e logs) |
| `cancelled` | Cancelado antes de iniciar |

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
  "args_json": ["nextcloud-manage","empresa","empresa.mework360.com.br","create"],
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
{"schema_version":"1","job_id":"...","state":"cancelled"}
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

### O problema que resolve

Em uma REST API que chama scripts via SSH, falhas de rede podem ocorrer **após** o servidor ter
enfileirado o job mas **antes** de você receber a resposta. Sem idempotência, um retry criaria um
segundo job duplicado — e você teria dois `create` para o mesmo tenant, por exemplo.

O `--idempotency-key` resolve isso: você gera uma chave única **no lado da API** antes de fazer a
chamada. Se a chamada cair e você tentar de novo com a mesma chave, o servidor reconhece a
duplicata e devolve o job original em vez de criar um novo.

### Como funciona internamente

```
Chamada 1 (chave K, args A)
  → SHA256(args A) = hash H
  → Redis: SET nc:idem:K  "<job_id_1>:<hash H>"  NX EX 86400
  → NX: chave não existe → gravou → retorna "new"
  → Enfileira job_id_1
  → Resposta: { job_id: job_id_1, state: "queued" }

Chamada 2 (chave K, args A)  ← retry após timeout de rede
  → SHA256(args A) = hash H  (mesmos args, mesma hash)
  → Redis: SET nc:idem:K ... NX → falhou (já existe)
  → GET nc:idem:K → "<job_id_1>:<hash H>"
  → hash bate → retorna "same:job_id_1"
  → Resposta: { job_id: job_id_1, state: "queued", idempotent: true }
             ↑ mesmo job, sem criar novo

Chamada 3 (chave K, args B)  ← args diferentes, bug na API
  → SHA256(args B) = hash H2 ≠ H
  → hash não bate → retorna "conflict"
  → exit 3, erro: { error: "idempotency_conflict" }
```

O dado no Redis é `nc:idem:<uuid>` com TTL de **86.400 segundos (24 horas)**.

Caso especial: se o job original já expirou do Redis (TTL de jobs terminados é 7 dias, mas pode
ter sido limpo manualmente) mas a idem key ainda existe dentro das 24h, o servidor limpa a idem
key e cria um novo job normalmente — sem erro.

### Uso correto na sua API

```python
import uuid

# Gerar a chave UMA vez, antes de tentar a chamada
# Associar ao recurso que está sendo criado (ex: gravar no seu banco antes do SSH)
idempotency_key = str(uuid.uuid4())

# Tentar com retry
for attempt in range(3):
    try:
        result = ssh_call(
            f"nextcloud-manage empresa empresa.mework360.com.br create --async --json"
            f" --idempotency-key={idempotency_key}"
        )
        break
    except SSHTimeoutError:
        continue  # retry com a MESMA chave → servidor deduplica automaticamente
```

### Regras da chave

| Regra | Detalhe |
|---|---|
| Formato | UUID v4 **lowercase** obrigatório: `^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$` |
| Geração | Sempre no **cliente** (sua API), antes de invocar SSH |
| Escopo | Por operação (uma chave por tentativa de criar/remover/etc.) |
| Reutilização | **Proibida** com args diferentes → erro `idempotency_conflict` (exit 3) |
| TTL | 24 horas no Redis |
| Opcional | Sem a flag, cada chamada cria um job novo independentemente |

### Respostas possíveis

**Primeira chamada (nova):**
```json
{
  "schema_version": "1",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "state": "queued",
  "cmd": "create",
  "client": "empresa",
  "queued_at": "2026-05-13T04:00:00Z"
}
```

**Retry com mesma chave + mesmos args:**
```json
{
  "schema_version": "1",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "state": "running",
  "cmd": "create",
  "client": "empresa",
  "queued_at": "2026-05-13T04:00:00Z",
  "idempotent": true
}
```
Note: `state` reflete o estado atual do job original (pode ser `queued`, `running` ou `done`).

**Mesma chave + args diferentes (erro):**
```json
{
  "schema_version": "1",
  "error": "idempotency_conflict",
  "message": "idempotency-key ja usada com args diferentes",
  "retry_after": 30
}
```
Exit code: `3`

**Chave com formato inválido:**
```json
{
  "schema_version": "1",
  "error": "invalid_idempotency_key",
  "message": "idempotency-key deve ser UUID v4 lowercase"
}
```
Exit code: `5`

### Quando usar

| Situação | Usar? |
|---|---|
| `create --async` via API pública | **Sim** — obrigatório em produção |
| `remove --async` via API | **Sim** — evita double-remove |
| `backup --async` agendado | Opcional — backups duplicados são inofensivos |
| `job status` / `job logs` | **Não** — são leituras, sem efeito colateral |
| Chamadas de desenvolvimento/teste manual | Não necessário |

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

| Código | Exit | Descrição |
|---|---|---|
| `requires_root` | 1 | Script deve ser executado como root |
| `client_not_found` | 1 | Tenant não existe |
| `client_already_exists` | 1 | Tenant já existe (create) |
| `unknown_command` | 1 | Comando não reconhecido |
| `redis_unavailable` | 1 | Redis inacessível |
| `job_not_found` | 1 | Job ID não existe no Redis |
| `job_not_cancellable` | 5 | Job não está em estado `queued` |
| `async_not_supported_for_cmd` | 5 | Comando não suporta --async |
| `confirm_required` | 5 | Remove sem --force |
| `idempotency_conflict` | 3 | Key usada com args diferentes |
| `invalid_idempotency_key` | 5 | Key não é UUID v4 lowercase |
| `password_in_argv_forbidden` | 5 | Senha passada em argumento |
| `payload_stdin_required` | 5 | occ-exec requer --payload-stdin |
| `not_implemented_yet` | 99 | Namespace/verb não implementado ainda |

---

## 9. Validações de Entrada

| Campo | Regex | Notas |
|---|---|---|
| `client` | `^[a-z0-9-]{1,64}$` | Sem underscore, sem maiúsculas |
| `domain` | FQDN padrão | `^[a-z0-9][...]+\.[a-z0-9]+$`, max 253 chars |
| `job_id` / `idempotency-key` | UUID v4 lowercase | `^[0-9a-f]{8}-...-4[0-9a-f]{3}-[89ab]...` |
| `callback` URL | `^https://` | IPs RFC 1918 rejeitados (SSRF defense) |
| Senha | — | **Nunca em argv**, sempre via `--payload-stdin` |

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

| Cenário | Exit SSH | Ação recomendada |
|---|---|---|
| Sucesso | 0 | Retornar 200/201/202 |
| `client_not_found` | 1 | Retornar 404 |
| `client_already_exists` | 1 | Retornar 409 Conflict |
| `confirm_required` / validação | 5 | Retornar 422 Unprocessable |
| `idempotency_conflict` | 3 | Retornar 409 com job existente |
| `not_implemented_yet` | 99 | Retornar 501 Not Implemented |
| Timeout SSH (> 30min) | — | Retornar 504 Gateway Timeout |
| Falha na conexão SSH | — | Retornar 503 Service Unavailable |

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

---

## 16. SCP Staging — Feature O.5

> Permite enviar arquivos de branding (logo, background) via SFTP antes de provisionar um tenant.
> Usa um **usuário SSH dedicado** (`ncsaas-sftp`) separado do gateway de comandos (`ncsaas-api`).

### Arquitetura de dois canais SSH

```
REST API
  │
  ├── Canal 1: ncsaas-api (chave shim)
  │     → ForceCommand ncsaas-api-shim → nextcloud-manage
  │     → Usado para TODOS os comandos (create, list, job, etc.)
  │
  └── Canal 2: ncsaas-sftp (chave SFTP)
        → ForceCommand internal-sftp
        → ChrootDirectory /opt/nextcloud-customers/inbox
        → Apenas upload de arquivos (logo.png, background.jpg)
```

### Infraestrutura no servidor

| Componente | Localização |
|---|---|
| **Usuário SSH da API (Canal A)** | `ncsaas-api` (sistema, shell `/bin/bash` para ForceCommand) |
| Chaves autorizadas Canal A | `/home/ncsaas-api/.ssh/authorized_keys` |
| Config sshd Canal A | `/etc/ssh/sshd_config.d/50-ncsaas-api.conf` |
| **Usuário SFTP (Canal B)** | `ncsaas-sftp` (sistema, `nologin`) |
| Chaves autorizadas Canal B | `/etc/ssh/ncsaas-sftp-authorized_keys` |
| Config sshd Canal B | `/etc/ssh/sshd_config.d/51-ncsaas-api-sftp.conf` |
| Inbox SFTP (raiz do chroot) | `/opt/nextcloud-customers/inbox` (`root:root 0755`) |
| Subdirs de staging | `/opt/nextcloud-customers/inbox/<staging-id>/` (`ncsaas-sftp:ncsaas-sftp 0700`) |
| Metadados Redis | `nc:inbox:<staging-id>` (TTL 24h) |

### Fluxo passo a passo

```bash
# Passo 1 — API REST gera staging_id (UUID v4)
staging_id=$(python3 -c "import uuid; print(uuid.uuid4())")

# Passo 2 — API REST cria subdir de staging via ncsaas-api (Canal A)
# nextcloud-manage _ _ inbox-init cria /opt/nextcloud-customers/inbox/<uuid>/
# (ncsaas-sftp:ncsaas-sftp 0700) + popula nc:inbox:<uuid> no Redis.
# ⚠ inbox-init está pendente de implementação (Sprint F8 / S2).
#   Workaround temporário (admin): sudo install -d -o ncsaas-sftp -g ncsaas-sftp -m 0700 \
#     /opt/nextcloud-customers/inbox/${staging_id}
ssh -i ~/.ssh/ncsaas-api-key ncsaas-api@177.104.164.187 \
  "nextcloud-manage _ _ inbox-init --staging-id=${staging_id}"

# Passo 3 — API REST envia arquivo via SCP (Canal B — chave ncsaas-sftp)
# O path no SCP é relativo ao chroot /opt/nextcloud-customers/inbox
scp -i ~/.ssh/ncsaas-sftp-key \
    logo.png \
    ncsaas-sftp@177.104.164.187:/${staging_id}/logo.png

# (opcional) Enviar background
scp -i ~/.ssh/ncsaas-sftp-key \
    background.jpg \
    ncsaas-sftp@177.104.164.187:/${staging_id}/background.jpg

# Passo 4 — API REST provisiona tenant com branding (Canal A)
output=$(ssh -i ~/.ssh/ncsaas-api-key ncsaas-api@177.104.164.187 \
  "nextcloud-manage acme acme.mework360.com.br create \
   --async --json \
   --staging-id=${staging_id} \
   --idempotency-key=$(python3 -c 'import uuid; print(uuid.uuid4())') \
   --callback=https://minha-api.com/webhooks/nc")
job_id=$(echo "$output" | jq -r '.job_id')
```

> **Chaves SSH**: Canal A (`ncsaas-api`) e Canal B (`ncsaas-sftp`) **usam chaves diferentes**. Não é possível usar a chave do shim para SFTP nem vice-versa — o sshd impõe `ForceCommand` diferente para cada usuário.

### Limites e TTL

| Parâmetro | Valor |
|---|---|
| Tamanho máximo por arquivo | 5 MB |
| Tamanho total do staging dir | 10 MB |
| TTL do staging (GC automático) | 24 horas |
| Arquivos aceitos (logo) | `logo.*` (qualquer extensão) |
| Arquivos aceitos (background) | `background.*` (qualquer extensão) |

### Ciclo de vida dos arquivos

```
Upload         Consumo               GC (após 24h se não consumido)
──────────     ────────────────────  ─────────────────────────────
inbox/<uuid>/  →  jobs/<job_id>/     →  removido por nextcloud-saas-jobs-gc.timer
  logo.png          staging/
                    logo.png
```

### Segurança

| Vetor | Mitigação |
|---|---|
| Shell access via ncsaas-sftp | `ForceCommand internal-sftp` — sem shell |
| Acesso fora do inbox | `ChrootDirectory /opt/nextcloud-customers/inbox` |
| Password auth | `PasswordAuthentication no` |
| TCP forwarding | `AllowTcpForwarding no` |
| Path traversal no staging_id | Validação UUID v4 em `parse_global_flags` + `inbox_staging_consume` |
| Arquivo muito grande | Limite 5 MB/arquivo, 10 MB/total em `inbox_staging_consume` |
| Chave comprometida | Kill-switch: `> /etc/ssh/ncsaas-sftp-authorized_keys` |

### Chaves SSH — configuração da API consumidora

```bash
# Gerar chave SFTP (separada da chave shim)
ssh-keygen -t ed25519 -C "ncsaas-sftp-prod-$(date +%Y)" -f ~/.ssh/ncsaas-sftp-key

# Instalar no servidor
sudo sh -c "cat ~/.ssh/ncsaas-sftp-key.pub >> /etc/ssh/ncsaas-sftp-authorized_keys"

# Verificar SFTP
sftp -i ~/.ssh/ncsaas-sftp-key ncsaas-sftp@177.104.164.187
# sftp> ls         → lista diretórios de staging existentes
# sftp> cd <uuid>/ → acessa subdir de staging
# sftp> put logo.png /<uuid>/logo.png
```

