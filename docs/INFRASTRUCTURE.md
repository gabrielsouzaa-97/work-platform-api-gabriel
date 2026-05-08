# Infrastructure — mework360-deployer

> Gerado em: 2026-05-07
> Fase: 8 — Infraestrutura
> Baseado em: docs/ARCHITECTURE.md + docs/DATABASE.md

## 1. Topologia de Servidores

### Diagrama

```text
┌─────────────────────────────────────────────────────────┐
│                      Proxmox Host                       │
│                                                         │
│  ┌───────────────────────────────────────────────────┐  │
│  │ mework360-deployer-vm (VM / LXC)                       │  │
│  │ Docker Host                                       │  │
│  │                                                   │  │
│  │  ┌──────────────┐  ┌──────────────┐ ┌──────────┐  │  │
│  │  │ app (Laravel)│  │ db (Postgres)│ │ redis    │  │  │
│  │  │ port 80/443  │  │ port 5432    │ │ port 6379│  │  │
│  │  └──────┬───────┘  └──────┬───────┘ └────┬─────┘  │  │
│  │         │                 │              │        │  │
│  └─────────┼─────────────────┼──────────────┼────────┘  │
└────────────┼─────────────────┼──────────────┼───────────┘
             │                 │              │
             ▼                 ▼              ▼
     [ SSH (saída) ]      [ Backups S3 ]  [ SMTP (saída) ]
             │
             ▼
[ nextcloud-saas-manager ]
(Servidor Remoto Upstream)
```

### Serviços

| Serviço | Tipo | Container/VM | IP | Porta |
|---------|------|-------------|-----|-------|
| mework360-deployer | Docker Host | mework360-deployer-vm | IP Interno / Público | 80, 443 |
| app | Docker Container | app | Rede Docker | 80, 443 |
| db | Docker Container | db | Rede Docker | 5432 |
| redis | Docker Container | redis | Rede Docker | 6379 |

## 2. Specs de Recursos

| Serviço | CPU (vCPU) | RAM (GB) | Disco (GB) | OS | Notas |
|---------|-----------|---------|-----------|-----|-------|
| mework360-deployer-vm | 4 | 8 | 80 SSD | Debian 12 / Ubuntu 24.04 | Hospeda todos os containers Docker |

### Regras de sizing
- **App (stateless):** Laravel consome pouca RAM base. 2GB dedicados ao container da aplicação são suficientes para o MVP (< 50 customers).
- **BD (Tier 1):** PostgreSQL 16. Alocar 2GB de RAM e 40GB de disco. O volume de dados é baixo (armazenamento de logs JSONB e metadados).
- **Cache:** Redis 7.x. Alocar 1GB de RAM. Usado apenas para sessões, rate limiting (100 req/min) e fila local de retry de e-mails.

## 3. Rede

### Layout de IPs

| Rede | CIDR | Propósito |
|------|------|-----------|
| vmbr0 | a definir | Rede da VM no Proxmox |
| docker_default | 172.x.x.x/24 | Rede interna isolada dos containers Docker |

### Portas expostas externamente

| Porta | Serviço | Protocolo | Observação |
|-------|---------|-----------|------------|
| 80 | app | HTTP | Redirecionamento para HTTPS |
| 443 | app | HTTPS | Painel Livewire e Webhook Receiver (API) |
| 22 | sshd | SSH | Acesso administrativo (restrito via VPN/IP) |

### Firewall Rules

| De | Para | Porta | Ação | Motivo |
|----|------|-------|------|--------|
| Internet | app | 443 | ALLOW | Acesso ao painel e recebimento de webhooks do upstream |
| Internet | app | 80 | ALLOW | Redirecionamento HTTP -> HTTPS |
| Admin / VPN | mework360-deployer-vm | 22 | ALLOW | Acesso SSH administrativo |
| mework360-deployer-vm | nextcloud-saas-manager | 22 | ALLOW | Conexão SSH de saída para orquestração |
| Qualquer | Qualquer | * | DENY | Default deny |

## 4. Storage e Backups

### S3 / Object Storage

| Bucket | Propósito | Retenção | Acesso |
|--------|-----------|----------|--------|
| mework360-deployer-backups | Dumps do BD PostgreSQL | 30 dias | mework360-deployer-vm |

### Backup Schedule

| O que | Frequência | Método | Destino | PITR |
|-------|-----------|--------|---------|------|
| BD completo | Diário 03:00 | pg_dump | S3 bucket | Não (tolerado no MVP) |
| Teste de restore | Mensal | Restore em container temporário | — | — |

> **Nota:** Os backups reais dos customers Nextcloud residem e são gerenciados pelo servidor upstream (`nextcloud-saas-manager`). A API apenas armazena metadados, logs de auditoria e configurações.

## 5. Segurança de Infraestrutura

### Checklist

- [ ] **Firewall:** Default deny, apenas portas 80, 443 e 22 abertas.
- [ ] **TLS:** HTTPS obrigatório em produção (Let's Encrypt ou certificado próprio).
- [ ] **SSH (VM):** Chave pública only, desabilitar root login (`PermitRootLogin no`), usar usuário não-privilegiado.
- [ ] **SSH (Upstream):** A chave SSH privada da API (`ncsaas-api`) deve ser protegida pelo Laravel Encrypted Storage. O usuário no upstream deve ter `sudoers` restrito apenas ao `manage.sh`.
- [ ] **Updates:** Automáticos para security patches (`unattended-upgrades`).
- [ ] **Secrets:** `APP_KEY`, credenciais de BD e S3 injetadas via `.env`, nunca commitadas.
- [ ] **Webhook Security:** Garantir que o IP do `nextcloud-saas-manager` esteja na whitelist do firewall ou da aplicação para a rota `/api/jobs/hook`.

## 6. Checklist de Provisionamento

> Guia passo a passo para o humano provisionar a infraestrutura.

### 6.1 VM / LXC Base

- [ ] Criar VM/LXC `mework360-deployer-vm` no Proxmox com 8 GB RAM, 4 vCPU, 80 GB disco, Debian 12.
- [ ] Configurar IP estático e Gateway.
- [ ] Atualizar pacotes: `apt update && apt upgrade -y`.
- [ ] Instalar Docker e Docker Compose: `curl -fsSL https://get.docker.com | sh`.
- [ ] Configurar UFW (Firewall):
  - `ufw default deny incoming`
  - `ufw allow 22/tcp`
  - `ufw allow 80/tcp`
  - `ufw allow 443/tcp`
  - `ufw enable`

### 6.2 Aplicação (Docker)

- [ ] Clonar o repositório da aplicação na VM.
- [ ] Copiar `.env.example` para `.env` e preencher as credenciais (DB, Redis, S3, SMTP).
- [ ] Gerar `APP_KEY`: `php artisan key:generate`.
- [ ] Subir os containers: `docker compose up -d`.
- [ ] Executar migrations: `docker compose exec app php artisan migrate --force`.
- [ ] Executar seeders iniciais (se houver): `docker compose exec app php artisan db:seed --force`.

### 6.3 SSL / HTTPS

- [ ] Configurar proxy reverso (Nginx/Caddy) no container da aplicação ou na VM host.
- [ ] Gerar certificado SSL via Let's Encrypt (ex: `certbot --nginx -d painel.mework360.com`).

### 6.4 Backups

- [ ] Configurar script de backup diário (cronjob) executando `pg_dump` do container `db` e enviando para o S3 via AWS CLI ou Rclone.
- [ ] Testar a execução do script de backup manualmente.

### 6.5 Validação Final

- [ ] Testar acesso ao painel via HTTPS no navegador.
- [ ] Testar conectividade de saída: `mework360-deployer-vm` consegue fazer SSH para o `nextcloud-saas-manager`?
- [ ] Testar conectividade de entrada: `nextcloud-saas-manager` consegue disparar um POST para `https://painel.mework360.com/api/jobs/hook`?
- [ ] Verificar logs dos containers: `docker compose logs -f`.
