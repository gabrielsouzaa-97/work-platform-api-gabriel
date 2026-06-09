# Post-create runbook — tenant pronto para o cliente

> Executar **após** webhook `job.finished` success + customer `active` (readiness probe OK).
> A API/deploy-scripts **não** automatizam estes passos hoje.

## Pré-requisitos

- [ ] Job `create` terminal `success` na API (`/queue/{job_id}`)
- [ ] Customer `status=active` (não `provisioning_finishing`)
- [ ] SSH ao host upstream (`dev.mework360.com.br` homolog / produção equivalente)
- [ ] Slug do tenant: `<slug>`

## 1. Validar apps instalados no tenant

```bash
# Substituir <slug>-app pelo container real (ex.: dev-app em homolog único)
sudo docker exec -u www-data <slug>-app php occ app:list | grep -E 'mework360_memail|me360_theme|^  - mail'
```

Esperado no stack meWork360:

| App | Papel |
|-----|--------|
| `mework360_memail` | meMail (UI) |
| `me360_theme` | Tema ME360 |
| `mail` | App store — **desabilitar em prod alinhado** (ver §5) |

Se `mework360_memail` ausente: upstream `custom-apps` / N4 não aplicado — ver `ecosystem-map.md`.

## 2. meMail — `externalLocation` (obrigatório)

Sem isso o iframe do Roundcube **não carrega**.

### Homolog (same-origin via proxy NC)

```bash
sudo docker exec -u www-data <slug>-app php occ config:app:set mework360_memail \
  externalLocation --value="https://dev.mework360.com.br/roundcube"
```

Referência dev já configurada: `https://dev.mework360.com.br/roundcube` (audit 2026-06-09).

### Produção (cloud)

```bash
sudo docker exec -u www-data <slug>-app php occ config:app:set mework360_memail \
  externalLocation --value="https://cloud.mework360.com.br/roundcube/"
```

Ajustar se nginx do tenant expõe `/roundcube/` como reverse-proxy (ver `mework360_memail/docs/ARQUITETURA.md`).

### Validar

```bash
sudo docker exec -u www-data <slug>-app php occ config:app:get mework360_memail externalLocation
```

## 3. meMail — demais configs recomendadas

Migrar de tenant legado se aplicável (`FINDINGS.md` memail — não só `emailAddressChoice`):

```bash
# Exemplos — ajustar valores
sudo docker exec -u www-data <slug>-app php occ config:app:set mework360_memail emailAddressChoice --value="multiProfile"
sudo docker exec -u www-data <slug>-app php occ config:app:set mework360_memail forceSSO --value="yes"
```

Lista completa: `mework360_memail` → `Config::SETTINGS` / `docs/FINDINGS.md`.

## 4. Roundcube (host compartilhado — **não** por tenant)

Roundcube **não** é criado no `create`. Verificar saúde do RC compartilhado:

| Ambiente | SSH | Container |
|----------|-----|-----------|
| Dev | `mecloud360@dev.mework360.com.br` | `roundcube-app-1` (se existir no host) |
| Prod | `mecloud360@cloud.mework360.com.br` | `roundcube-app-1` |

Scripts memail (sessão/cookies):

```bash
# No repo mework360_memail
bash scripts/apply-rc-session-fixes-all.sh
```

Deploy plugins kit (`mework360-roundcube`):

```bash
# Ver mework360_memail/scripts/patch-rc-*.sh e DEPLOY.md
```

## 5. Política mail store vs meMail

Após validar meMail, em ambientes alinhados a prod:

```bash
sudo docker exec -u www-data <slug>-app php occ app:disable mail
```

`manage.sh create` instala **ambos** por padrão — política prod desabilita `mail` store.

## 6. Tema ME360

```bash
# config.php do tenant — se theme não ativo
sudo docker exec -u www-data <slug>-app php occ maintenance:theme:update
```

Tema ativado via `'theme' => 'me360'` em `config/config.php` (gerado no create).

## 7. Smoke test cliente (manual)

1. Login NC no domínio do tenant
2. Abrir app **Mail** (meMail)
3. Inbox carrega sem tela de login RC duplicada
4. Criar usuário via API `users:create` — só após customer `active`

## 8. Registrar na API

- [ ] Audit: provision + primeiro login operador
- [ ] ISSUE-023: `jobs.summary` populado no job `create`
- [ ] Atualizar checklist em `readiness-gates.md` R8

## Referências

| Doc | Repo |
|-----|------|
| `docs/DEPLOY.md` | `mework360_memail` |
| `docs/ARQUITETURA.md` | `mework360_memail` |
| `docs/CONTRACTS.md` § create | `mework360-deploy-scripts` |
| `provision-lifecycle.md` | skill me360-deployer |

## Gaps conhecidos (ISSUE-022)

- Branding no create: falhas upstream mascaradas (`|| true`)
- Webhook `summary`/`log_tail`: worker pode não emitir — usar `JobLogFetcher`
- `custom_apps` dir ausente em alguns hosts — N4 pode estar incompleto no servidor
