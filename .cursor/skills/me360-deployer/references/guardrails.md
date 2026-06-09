# Guardrails — me360-deployer

> Universal tables: `capabilities/guardrails.md`. Este arquivo cobre operações de deploy, infra e provisionamento.

## Iron Law

**NENHUMA declaração de "pronto" sem evidência dos gates R1–R8.** Opinião não substitui `docker compose ps`, `/up`, ou job E2E.

## Anti-rationalization

| Desculpa | Realidade |
|----------|-----------|
| "CI passou ontem, pode deployar" | R3/R5/R8 são do ambiente alvo **agora** — CI não prova VM nem webhook |
| "Só mudei o painel, upstream não importa" | Se release inclui argv/webhook indiretamente, ISSUE-022 ainda aplica |
| "Homolog é igual produção" | `APP_URL`, secrets, versão do worker e firewall diferem — revalidar R6–R8 |
| "Produção = clientes em uso" vs "host fábrica" | `create` no host fábrica sem tráfego é canário válido; `custom-apps update` ainda afeta todos os TNs do host |
| "Dev tem ambiente só para criar TN" | **Não** — `dev.mework360.com.br` é fábrica + runtime; ver `environment-and-parity.md` |
| "mework360-local = mesmo que create" | FULL_LOCAL não passa por `manage.sh`; tenant persistente pode ter drift |
| "Provision falhou mas API está ok" | Provision é o critério do usuário — job stuck = NOT READY |
| "Local sem túnel deve funcionar" | Webhook exige R7 — sem ngrok/HTTPS jobs ficam `queued` para sempre |
| "docker compose up basta" | Sem migrate/seed/APP_KEY o painel quebra (419, login, SSH fake) |
| "Posso usar dev-cluster-local para provision" | Seeder usa chave fake — R6 falha ou SSH erro enganoso |

## Red flags (PARE e reavalie)

### Deploy / versão

- Propor deploy com migrations pendentes não revisadas → PARE. `migrate:status`.
- `config:cache` sem confirmar `APP_KEY` → PARE. Causa 419 em todos os POSTs.
- Deploy API antes do upstream com contrato novo → PARE. ISSUE-022 ordem invertida.
- Sugerir `docker compose down -v` para "limpar" → PARE. Hook bloqueia; perda de dados.
- Colar chave SSH ou webhook secret no chat/commit → PARE. Usar painel/`.env` local.

### Provision

- Declarar customer ativo só pelo HTTP 202 → PARE. Aguardar webhook + probe (R8).
- Ignorar `tenant_not_ready` 503 após provision → PARE. Comportamento esperado (F8).
- `APP_URL=http://localhost` sem túnel e usuário quer E2E → PARE. Explicar R7.

### Readiness

- Responder "sim, está pronto" com menos de R3+R5 verificados → PARE.
- Omitir falhas parciais (ex.: 1/5 jobs sem summary) → PARE. Reportar NOT READY + próximo passo.

## Verification checklists

### Pre-deploy (API)

- [ ] R1: testes passando no container
- [ ] R2: upstream alinhado se contrato mudou
- [ ] Diff de migrations revisado
- [ ] `.env` produção não commitado
- [ ] Rollback tag/SHA identificado

### Post-deploy (API)

- [ ] R3: containers healthy
- [ ] R4: migrate OK; config:cache só com APP_KEY
- [ ] R5: `/up` 200
- [ ] R8: job async + summary + UI logs (ISSUE-023)

### Pre-provision (local ou prod)

- [ ] R6: Testar Conexão SSH no cluster real
- [ ] R7: APP_URL alcançável pelo upstream
- [ ] Cluster `status=active`
- [ ] Slug válido (`App\Rules\Slug`)

### Post-provision

- [ ] Job terminal (`success` ou `failed` explícito)
- [ ] Customer `provisioning_finishing` → `active` após probe
- [ ] `users:create` só após `active`

## Stop conditions

| Condição | Ação |
|----------|------|
| 3 tentativas de deploy sem R5 verde | Escalar ao usuário; não repetir mesmo comando |
| Webhook 401 após rotate | Upstream worker restart (ISSUE-002) |
| SSH exit 16 em OCC | Não é deploy — ver `vocabulary-translator` / ISSUE-011 |
| Tier 3 pedido mas sem repo scripts | Documentar gap; oferecer Tier 2 |

## Skill boundary

| Faz | Não faz |
|-----|---------|
| Orquestrar deploy, gates, smoke | Implementar endpoints (`api-rest-patterns`) |
| Validar ordem cross-repo | Alterar `JobTypeTranslator` (`vocabulary-translator`) |
| Guiar provision operacional | Implementar HMAC (`webhook-receiver`) |
| Documentar compose local | Modificar `.env` / docker-compose sem avisar usuário |
| Apontar deps (memail, RC, theme) | Deploy Roundcube / `externalLocation` / deck kits (repos irmãos) |

**Iron law cross-repo:** provision “completo” para o negócio ≠ só API verde. Tenant com meMail sem `externalLocation` ou RC desatualizado = **NOT READY** para cliente final — ver `ecosystem-map.md`.

## Anti-rationalization (ecosystem)

| Desculpa | Realidade |
|----------|-----------|
| "API provisionou, cliente pode usar mail" | meMail precisa RC shared + config pós-create |
| "create instalou tudo" | Roundcube e patches deck/RC são ops separados |
| "Li o README da API, sei o sistema" | Motor real está em `manage.sh` + worker + shared-services |
| "Não preciso olhar deploy-scripts" | ISSUE-022 inteiro vive no gap API ↔ scripts |
