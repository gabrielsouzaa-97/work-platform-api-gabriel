# Environment taxonomy & Dev parity

> Consolidado em 2026-06-09 a partir de sessões de diagnóstico local + alinhamento de vocabulário (TN factory vs clientes em uso).

## Vocabulário (evitar confusão)

| Termo que o time usa | Significa na prática | Não confundir com |
|----------------------|----------------------|-------------------|
| **Ambiente Dev / homolog** | Host upstream `dev.mework360.com.br` | Painel `deployer.mework360.com.br` |
| **Ambiente que cria TNs** | Mesmo host upstream (`manage.sh` + worker) | Segundo host “só fábrica” no Dev — **não existe** |
| **Produção (fábrica)** | Host upstream futuro/atual onde `create` roda sem clientes reais ainda | TNs de clientes já em uso no mesmo host |
| **Produção (clientes)** | TNs com usuários finais em operação | Teste de `create` com slug `qa-*` no host fábrica |
| **deployer-api** | Control plane (API + painel + webhook) | Nextcloud tenant |

**Regra:** um **cluster** no painel = um **host upstream** SSH. Esse host é **fábrica e runtime ao mesmo tempo**: cada `create` adiciona `/opt/nextcloud-customers/<slug>/` no mesmo servidor (ex.: `dev-app` + `qa-canary-01` coexistem).

## Topologia Dev (audit 2026-06-09)

```text
deployer.mework360.com.br     → mework360-deployer-api (Docker VM)
        │ SSH
        ▼
dev.mework360.com.br          → fábrica + TNs homolog (ÚNICO host Dev)
  ├── nextcloud-manage v12.3.0 + nextcloud-saas-worker
  ├── shared-services (MariaDB, Redis, Collabora, Talk stack…)
  └── /opt/nextcloud-customers/<slug>/   (app, cron, harp; nginx/push conforme host)

cloud.mework360.com.br        → Roundcube shared (NÃO é TN por cliente)
```

- Cluster homolog cadastrado: `119d74df-9011-4c0f-a6bf-ad03f84af10d` @ `dev.mework360.com.br`
- TN referência homolog: `dev-app` — meMail 1.5.0, `me360_theme` 1.6.13, `externalLocation=https://dev.mework360.com.br/roundcube`
- Gap audit: `/opt/shared-services/custom_apps/` **ausente** no homolog — N4 pode sincar apps da árvore do tenant, não do path documentado

## Ambientes na máquina do desenvolvedor

| Ambiente | Repo | URL típica | Login | O que simula |
|----------|------|------------|-------|--------------|
| **deployer-api local** | `mework360-deployer-api` | `http://localhost:8080` | `admin@mework360.local` / `password` | Painel/API (Tier 1); cluster seed **fake** |
| **mework360-local FULL_LOCAL** | `cursor/mework360-local` | `https://cloud.mework360.local` | `admin` + `NC_ADMIN_PASSWORD` do `.env` | Stack NC+RC+Collabora; **não** passa por `manage.sh` |
| **mework360-local-lab** | `mework360-local-lab` | `http://localhost:9080` | `admin` / `admin` | Tenant limpo UI; **não** é `create` real |
| **Tier 2 (laptop → homolog)** | deployer-api + cluster real | painel local | operador seed | `create` **real** no `dev.mework360.com.br` |

**Não existe hoje** um script único `simulate-client-create.sh` que replique `manage.sh create` + pós-create localmente. Está espalhado em `new-tenant.sh`, `bootstrap-nc.sh`, `configure-full-local.sh`, `apply-me360-theme.sh`.

## O que `manage.sh create` entrega vs “TN pronto”

| Entregue no `create` | Exige pós-create manual |
|----------------------|---------------------------|
| DB + compose + containers tenant | `mework360_memail` → `externalLocation` |
| Apps store (richdocuments, spreed, mail, deck…) | `occ app:disable mail` (política meWork360) |
| N4: `mework360_memail` + `me360_theme` enabled | Plugins/sessão Roundcube shared |
| `theme => me360` em config | Branding logo/background (stdin/SFTP) |
| OCC Redis, Collabora, Talk, HaRP | Validar meMail iframe + Collabora smoke |

Runbook: `post-create-runbook.md`. **HTTP 202 da API ≠ tenant pronto para demo.**

## Estratégia: perfil de provisionamento + TN canário

Válida no **host fábrica** (sem clientes em uso ou com slug descartável):

1. **Baseline (pin do perfil)** — gravar antes de mudar:
   - `nextcloud-manage version` / git tag `mework360-deploy-scripts`
   - commit/versão de `mework360_memail`, `mework360_theme`, `me360_theme`
   - versão do worker (`systemctl status nextcloud-saas-worker`)
2. **Aplicar melhorias no perfil** — bump `manage.sh` e/ou custom apps no host
3. **TN canário** — `nextcloud-manage qa-canary-YYYYMMDD <domain> create --async` (via API Tier 2 ou SSH)
4. **Checklist paridade** — apps, tema, login, meMail, Collabora (ver § abaixo)
5. **Decisão** — OK → perfil vira default para novos TNs; NOK → `git checkout` baseline + `remove qa-*`

### Risco por tipo de mudança (host fábrica sem tráfego)

| Ação | Isolamento | Risco |
|------|------------|-------|
| `create` slug novo | Alto (DB/compose próprios) | **Baixo** |
| `remove` slug teste | Alto | Baixo |
| Rollback git `manage.sh` / custom apps | Global para próximos `create` | Baixo se pin documentado |
| `custom-apps update` | Propaga a **todos** TNs do host | **Médio** |
| Mudança shared-services / RC Plus | Host inteiro | **Médio** |

**Não** usar `custom-apps update` em massa antes de validar em **um** TN canário.

## Checklist paridade local ↔ Dev (pós-create)

Use após `create` canário ou após simulação local completa:

- [ ] `occ app:list` → `mework360_memail`, `me360_theme` enabled
- [ ] `config.php` → `'theme' => 'me360'`
- [ ] `occ config:app:get mework360_memail externalLocation` (Dev: `https://dev.mework360.com.br/roundcube`; local FULL_LOCAL: `https://cloud.mework360.local/roundcube/`)
- [ ] Login NC + dashboard com branding me360
- [ ] `/apps/mework360_memail/` abre (não página em branco / 503 update)
- [ ] `/roundcube/` HTTP 200
- [ ] Collabora abre documento (`office.*`)
- [ ] (Opcional Dev) Talk `spreed` — omitido no FULL_LOCAL v1
- [ ] App store `mail` desabilitado se política prod

## mework360-local FULL_LOCAL (`FULL_LOCAL=1`)

Sibling repo: `cursor/mework360-local` (não confundir com `mework360-local-lab`).

```text
Traefik + mkcert
  cloud.mework360.local     → mework360-app (NC + meMail + tema)
  cloud.../roundcube        → roundcube-app-1 (Plus local)
  office.mework360.local    → shared-collabora
```

Scripts: `up-full-local.sh`, `bootstrap-nc.sh`, `configure-full-local.sh`, `apply-me360-theme.sh`, `new-tenant.sh` (tenant extra sem meMail automático).

### Gaps vs Dev real

| Dev / upstream | FULL_LOCAL v1 |
|----------------|---------------|
| `manage.sh create` | manual / scripts parciais |
| Talk + signaling | omitido |
| HaRP, nginx, push por tenant | omitido (só app+cron) |
| RC shared cloud | RC local same-origin |
| Webhook + probe `active` | ausente |
| `custom_apps_sync` N4 servidor | bind-mount repos locais |

## Armadilhas Windows (bind-mount)

Compose em `mework360-local/tenants/mework360/docker-compose.yml` resolve paths relativos a `cursor/`:

| Mount esperado | Path relativo | Problema observado |
|----------------|---------------|-------------------|
| meMail app | `../../../mework360_memail/app/mework360_memail` | Pasta **vazia** se código só está em worktree ou `cursorwindows/` |
| Tema | `../../../mework360_theme` → `themes/me360` | Idem — assets reais podem estar em `cursorwindows/mework360_theme` |
| me360_theme app | `../../../nc-upgrade-sim/app-me360_theme` | Path pode não existir no Windows |

**Sintomas:**

| Sintoma | Causa provável |
|---------|----------------|
| meMail HTTP 200, body vazio | Mount `mework360_memail` vazio |
| NC HTTP 503, `#core-updater` | `installed_version` DB > `info.xml` do app montado |
| NC 503 / crash | `me360_theme` enabled + `themes/me360/config/*` ausente |
| `occ` falha “diretório não gravável” | bind-mount `data/` no Docker Desktop Windows (uid 1000 vs www-data) — usar SQL (`_sql_memail_config.sh`, `_enable_me360_theme_db.sh`) |

**Correção durável:** symlink ou ajustar compose para paths reais; copiar para paths esperados é hotfix, não perfil.

## Versões de referência (2026-06-09)

| Componente | Homolog `dev-app` | Local FULL_LOCAL (após hotfix) |
|------------|-------------------|--------------------------------|
| Nextcloud | 33.0.4.1 | 33.0.4.1 |
| mework360_memail | 1.5.0 | 1.4.24 (worktree baseline; alinhar antes de comparar) |
| me360_theme | 1.6.13 | depende do mount |
| manage.sh | v12.3.0 | N/A local |

Sempre pinar versões no checklist canário — drift entre DB `installed_version` e `info.xml` bloqueia o NC.

## Roteamento de intenção

| Pergunta do usuário | Ler primeiro |
|---------------------|--------------|
| “Onde criam TNs no Dev?” | Este doc § Vocabulário + Topologia Dev |
| “Como simular create local?” | `local-stack.md` § Tier 3b + este doc § Gaps |
| “Posso testar create no host fábrica?” | Este doc § Estratégia canário |
| “meMail não abre local” | Este doc § Armadilhas Windows |
| “Igual Dev após provision?” | `post-create-runbook.md` + checklist § paridade |
