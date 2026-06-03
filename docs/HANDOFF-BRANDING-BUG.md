# Handoff — Bug de Branding (Logo/Background) não aplicado em provisionamento

**Data:** 2026-05-26
**Investigação por:** Carlos + AI (sessão Cursor)
**Servidor inspecionado:** `deployer.mework360.com.br` (produção)
**Upstream investigado:** `nextcloud-saas-manager` ([repo](https://github.com/defensystechbr/nextcloud-saas-manager))

---

## 1. Sintoma

Provisionamentos via painel do `mework360-deployer-api` retornam `success / exit_code=0`, mas o **logo e background customizados nunca aparecem** no Nextcloud do cliente final.

Reportado pelo usuário durante uso normal do painel.

---

## 2. Root cause (confiança ALTA — confirmado por leitura de código)

O script Bash do upstream `feature_o_ext.sh::cmd_create_post_extended()` **só aplica branding quando recebe `--staging-id` (caminho SFTP)**. O caminho inline via `--payload-stdin` (`logo_data_url` / `background_data_url`) está **documentado em `CONTRACTS.md` mas nunca foi implementado**.

### Arquivo: `scripts/lib/feature_o_ext.sh`

```bash
# --staging-id: aplicar branding do staging dir (logo, background)
local staging_id="${PARSED_FLAGS[staging_id]:-}"
if [[ -n "$staging_id" ]]; then    # ← SÓ entra aqui se vier via SCP
  # ... aplica logo via theming:config
fi
```

**Não há `if [[ "${PARSED_FLAGS[payload_stdin]:-}" ]]; then` lendo branding inline.**

### Contrato documentado (não cumprido)

`docs/CONTRACTS.md` do upstream diz:
> Branding inline via `--payload-stdin {"branding": {"logo_data_url": "data:image/png;base64,...", "background_data_url": "..."}}` para anexos ≤256KB

Note que o contrato espera wrapper `branding`, **mas o `mework360-deployer-api` está enviando sem wrapper** (`{"logo_data_url": "..."}`). Mesmo se o upstream lesse o stdin, a chave estaria errada.

### Bug bônus — erro silencioso

Quando o caminho SFTP é usado, o comando OCC usa `|| true`:

```bash
docker exec -u www-data "$container" php occ theming:config logo /tmp/_branding_logo 2>/dev/null || true
```

Qualquer falha em aplicar branding **é mascarada**. O job retorna `exit_code=0` mesmo se `theming:config` falhar.

---

## 3. Evidências coletadas

### 3.1 Logs de `provision.ssh_dispatch` (lado API — está correto)

3 provisionamentos de teste em 25/mai:

| slug | has_logo | has_payload_stdin_flag | has_staging_id | stdin_bytes | logo_filesize |
|---|---|---|---|---|---|
| emanuellynerling55fc1b | true | true | false | 18713 | 13812 |
| maicon24c508 | true | true | false | 18713 | 13812 |
| gabrielteste2b19ca2 | true | true | false | 7599 | 5569 |

API leu o logo, codificou em base64, enviou via `--payload-stdin`. Caminho inline (correto pelo contrato).

### 3.2 Logs do upstream durante o `create`

Em todos os 3 provisionamentos, o log do upstream contém **a string literal** do Nextcloud:

```
- Cache logo dimension to fix size in emails on Outlook
     - Theming is not used to provide a logo
```

E **nunca menciona** `theming:config`, `theming:setlogo`, ou qualquer comando OCC relacionado a branding.

### 3.3 Estado dos jobs

```
state: success | exit_code: 0
callback_received: 2026-05-25 14:57:58
```

Webhook callback retornou sucesso. Lado API não tinha como saber que branding falhou.

### 3.4 Bug adicional — `group:adduser` falha silenciosamente

Nos jobs de `user-create`, observamos:

```json
{
  "error": "occ_command_failed",
  "message": "occ exited with code 1",
  "subcommand": "group:adduser",
  "exit_code": 1,
  "stdout": "group not found"
}
```

Mesmo assim o job retorna `state: success / exit_code: 0`. **Erro silencioso adicional**.

---

## 4. Tentativa de workaround (Opção A) — FALHOU

Tentamos forçar a API a usar sempre o caminho SFTP (que é o único que o upstream consegue processar):

```php
// app/Modules/Customers/Actions/ProvisionCustomerAction.php, linha 70-72
$useSftp = ($payload->logoPath || $payload->backgroundPath); // sempre SFTP
```

### Resultado: HTTP 502 em ~2 segundos

```
2026/05/26 05:42:52 [warn] *200 a client request body is buffered to a temporary file
172.18.0.1 - - [26/May/2026:05:42:54 +0000] "POST /api/customers HTTP/1.0" 502 98
```

PHP-FPM retornou 502 sem logar nada no Laravel — o que indica:
- Exceção fatal não capturada no fluxo SFTP, OU
- O comando `nextcloud-manage <slug> inbox init <staging-id>` é rejeitado pelo `ncsaas-api-shim` no upstream, OR
- Algum limite no `SshConnectionPool` quando há múltiplos canais (Canal A + Canal B SFTP)

**Reversão feita imediatamente.** Sistema voltou ao estado anterior (provisionamento funcionando, mas sem logo).

---

## 5. Recomendações de correção

### 5.1 Correção definitiva — no upstream `nextcloud-saas-manager`

**Local:** `scripts/lib/feature_o_ext.sh::cmd_create_post_extended()`

1. Adicionar leitura do `--payload-stdin` e processar `branding.logo_data_url` / `branding.background_data_url`
2. Aceitar tanto `{"branding": {...}}` (contrato) quanto `{...}` (formato que API envia hoje) — backward compat
3. **Remover os `2>/dev/null || true`** dos comandos `theming:config` para parar de mascarar falhas
4. Adicionar testes no `tests/integration/test_feature_o.bats` cobrindo o caminho inline

**Pseudocódigo:**

```bash
# Ler stdin se --payload-stdin estiver setado
local payload_json=""
if [[ "${PARSED_FLAGS[payload_stdin]:-}" == "1" ]]; then
  payload_json=$(_read_payload_stdin)
fi

# Aceitar ambos os shapes: {"branding": {...}} e {...}
local logo_data_url
logo_data_url=$(echo "$payload_json" | jq -r '.branding.logo_data_url // .logo_data_url // empty')

if [[ -n "$logo_data_url" && "$_container_running" == true ]]; then
  # decodificar base64 e aplicar
  local logo_tmp
  logo_tmp=$(mktemp /tmp/_branding_logo_XXXXXX)
  echo "$logo_data_url" | sed 's|^data:image/[^;]*;base64,||' | base64 -d > "$logo_tmp"
  docker cp "$logo_tmp" "${container}:/tmp/_branding_logo"
  docker exec -u www-data "$container" php occ theming:config logo /tmp/_branding_logo
  # SEM || true — falha deve ser reportada
  rm -f "$logo_tmp"
fi
```

### 5.2 Correção complementar — no `mework360-deployer-api`

**Local:** `app/Modules/Customers/Actions/ProvisionCustomerAction.php`

1. Envolver `logo_data_url` / `background_data_url` no wrapper `branding` (alinhamento com o contrato):

```php
if ($payload->logoPath) {
    $stdin['branding']['logo_data_url'] = 'data:image/png;base64,'.base64_encode(...);
}
if ($payload->backgroundPath) {
    $stdin['branding']['background_data_url'] = 'data:image/png;base64,'.base64_encode(...);
}
```

2. Investigar o erro 502 que aconteceu na Opção A — provavelmente exposição de bug pré-existente no fluxo SFTP que nunca foi exercitado em produção com arquivos pequenos. Pode estar no `SshClient::inboxInit` ou `sftpUpload`.

### 5.3 Bug adicional — erro silencioso de grupo

O `group:adduser` falhar com "group not found" e mesmo assim o job retornar `exit_code=0` é um problema separado:

- **Local:** lógica do `user-create` (provavelmente `cmd_user_create` no `feature_o.sh` do upstream)
- **Esperado:** se algum passo do user-create falha, o job deveria retornar `failed` ou pelo menos um `warnings` no summary
- **Investigação necessária:** auditar todos os steps do `user-create` para garantir captura de falhas parciais

---

## 5.4 Discussão arquitetural levantada na sessão — Orquestração Saga

Durante a investigação surgiu uma observação arquitetural relevante (não é bug, é **melhoria**):

**Hoje:** endpoints são fire-and-forget — cada chamada da API gera 1 job assíncrono no upstream. O caller é responsável por:
- Saber a ordem correta entre operações dependentes
- Esperar webhook de conclusão entre chamadas
- Implementar retry/backoff próprio

**Problema observado:** se o caller dispara `POST /api/customers` (criar tenant) e logo em seguida `POST /api/customers/x/users` (criar usuário), a segunda chamada **falha em cascata** porque o tenant ainda não existe (~2min de processamento).

**Sugestão para discussão futura:**
- Adicionar Laravel Queue local com `WorkflowOrchestrator` que:
  - Aceita comandos compostos (ex: "provisionar cliente completo com user/group/branding")
  - Quebra em sub-steps com dependências declaradas
  - Processa respeitando ordem, com retry/backoff por step
  - Webhook único agregado no final

**Benefícios:**
- Bulk import sem race conditions
- Integrações externas mais resilientes
- Painel pode mostrar progresso unificado

**Não bloqueante para o branding** — registrado aqui apenas como insight da sessão. Avaliar quando passar para uso intenso por integrações externas ou bulk import.

---

## 6. Achados secundários da sessão (não relacionados a branding)

Durante a investigação, descobrimos outros problemas em produção que merecem registro:

| Problema | Severidade | Status |
|---|---|---|
| `APP_ENV=local` em produção (vazava stack traces) | **CRITICAL** | ✅ Corrigido nesta sessão |
| `APP_DEBUG=true` em produção | **CRITICAL** | ✅ Corrigido nesta sessão |
| Servidor estava 5 commits atrás de `origin/main` | MEDIUM | ✅ Corrigido nesta sessão |
| Arquivo `ProvisionCustomerAction.php` editado direto no servidor (PR #78 não chegou via pull) | MEDIUM | ✅ Corrigido nesta sessão |
| SSH key do servidor para o GitHub estava ausente (deploy quebrado) | MEDIUM | ✅ Corrigido nesta sessão (Deploy Key adicionada) |
| Email SMTP `mta1.beesy.com.br:486` recusando conexão | LOW | ⚠️ Pendente — fora do escopo |
| Coluna `remember_token` faltando na tabela `operators` (erro em login) | MEDIUM | ⚠️ Pendente — investigar migration |
| Tabela `failed_jobs` não existe (erro em `queue:failed`) | LOW | ⚠️ Pendente — migration faltando |

---

## 7. Próximas ações — Fluxo formal (Beesy framework)

> **Importante:** este documento NÃO substitui o fluxo de triagem do projeto. Use como insumo para os comandos abaixo.

### 7.1 No upstream (`nextcloud-saas-manager`) — dev responsável

```bash
# 1. Triagem do bug principal
/triagem — Branding inline (logo_data_url/background_data_url) nao implementado em cmd_create_post_extended; documentado em CONTRACTS.md §3.9.0 mas codigo so processa via --staging-id

# 2. Registrar findings adicionais
/qa findings
# Adicionar:
# - BUG-A (HIGH): cmd_create_post_extended nao le --payload-stdin para branding inline
# - BUG-B (HIGH): group:adduser falha silenciosamente em user-create (exit_code=0 com group not found)
# - BUG-C (MEDIUM): 2>/dev/null || true em theming:config mascara falhas

# 3. Criar sprint F-fix
/pmo fix
```

**Escopo da sprint sugerida:**
- Task 1: Implementar leitura de `--payload-stdin` em `cmd_create_post_extended` para `logo_data_url` / `background_data_url`
- Task 2: Aceitar tanto `{"branding": {...}}` quanto `{...}` (backward compat com API atual)
- Task 3: Decodificar base64 e aplicar via `docker cp` + `occ theming:config logo|background`
- Task 4: Remover `2>/dev/null || true` dos comandos de theming
- Task 5: Adicionar testes em `tests/integration/test_feature_o.bats` cobrindo o caminho inline
- Task 6: Atualizar `CONTRACTS.md` se houver divergência de schema

### 7.2 No `mework360-deployer-api` — Carlos responsável

```bash
# 1. Triagem dos achados secundarios
/triagem — Multiplos findings descobertos em sessao de diagnostico do branding (.env producao, payload shape, 502 no fluxo SFTP, migrations faltantes)

# 2. Registrar findings
/qa findings

# 3. Criar sprint F-fix
/pmo fix
```

**Escopo sugerido:**
- Task 1: Alinhar payload com contrato — envolver `logo_data_url` / `background_data_url` em wrapper `branding` (depende do upstream aceitar ambos shapes)
- Task 2: Investigar 502 quando `$useSftp = true` — provavelmente exceção em `SshClient::inboxInit` ou `SshConnectionPool` reusando conexão
- Task 3: Validar fix end-to-end via Dusk/Playwright (provisionar com logo → assert logo no Nextcloud do cliente)
- Task 4: Aplicar migrations faltantes em produção (criar `failed_jobs`, adicionar coluna `remember_token` em `operators`)
- Task 5: Investigar SMTP `mta1.beesy.com.br:486` recusando conexão
- Task 6 (opcional): Pipeline de deploy automatizado para evitar edição manual no servidor

### 7.3 Sequenciamento

O fix da API (7.2 Task 1) **depende** do upstream (7.1 Task 1) — primeiro o upstream precisa aceitar o novo formato. Sugestão de ordem:

1. Upstream: implementa + deploy + smoke test manual via SSH
2. API: alinha payload + testes E2E
3. Validação conjunta em ambiente de staging

---

## 8. Arquivos de referência

- API: `app/Modules/Customers/Actions/ProvisionCustomerAction.php` linhas 67-107 (lógica de SFTP vs inline)
- API: `app/Modules/Core/Ssh/SshClient.php` linhas 31-74 (run/runAsync)
- Upstream: `scripts/lib/feature_o_ext.sh` (cmd_create_post_extended)
- Upstream: `docs/CONTRACTS.md` (especificação que não foi cumprida)
- Upstream: `docs/ROADMAP.md` linha do item 3.5 (definição original do feature)
- Backup `.env` original: `/opt/mework360-deployer-api/.env.bkp-20260526-050811`
- Backup `ProvisionCustomerAction.php`: `/tmp/ProvisionCustomerAction.php.original` (no servidor)
