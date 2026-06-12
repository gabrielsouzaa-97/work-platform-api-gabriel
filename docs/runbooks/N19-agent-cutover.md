# N19 — Cutover transporte SSH → agente (Fase 1)

> Piloto: `tenant.create` e `tenant.remove` via farm agent; lifecycle (users/groups/apps) permanece SSH.

## Pré-requisitos

- Sprint N17: daemon `work-platform-agent` instalado na fazenda piloto
- Sprint N18: `farm_agents` registrado + token; `AGENT_TRANSPORT_ENABLED=false` em produção até validação
- `nextcloud-manage` no PATH da fazenda (`NEXTCLOUD_MANAGE_PATH`, default `/usr/local/bin/nextcloud-manage`)

## Habilitar piloto

1. Registrar agente no painel (`/api/farm-agents`) vinculado ao `cluster_server_id` da fazenda
2. Instalar unit systemd do agent com `FARM_ID`, `CONTROL_PLANE_URL`, `AGENT_TOKEN`, TLS
3. Validar heartbeat: `GET /api/agent/v1/commands` retorna 204 ou comandos; eventos atualizam `last_seen_at`
4. Smoke: `agent.ping` via enqueue no registry
5. Definir `AGENT_TRANSPORT_ENABLED=true` **somente** no ambiente piloto
6. Provisionar tenant sem branding SFTP (>256KB) — create deve enfileirar `tenant.create` e retornar `job_id`
7. Remover tenant piloto — `tenant.remove` via agente

## Rollback SSH

1. `AGENT_TRANSPORT_ENABLED=false` no `.env` do control plane
2. `php artisan config:clear` (ou reload do container app)
3. Confirmar que `ProvisionCustomerAction` / `RemoveCustomerAction` voltam a chamar `SshClient::runAsync`
4. Agente pode permanecer online (heartbeats) — apenas deixa de ser selecionado pelo `AgentTransportResolver`

## Limitações N19

- Branding com SFTP staging (`--staging-id`) **ainda exige SSH** (inbox + Canal B)
- Operações lifecycle (`users:*`, `groups:*`, `apps:*`) continuam SSH até N20+
- Webhook HMAC continua disparado pelo `nextcloud-manage` local na fazenda (`--callback` preservado)

## Verificação

```bash
# Control plane — flag
grep AGENT_TRANSPORT_ENABLED .env

# CI
gh run list --repo SoftwareBeesy/work-platform-api --branch main --limit 1
gh run list --repo SoftwareBeesy/work-platform-agent --branch main --limit 1
```
