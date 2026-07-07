# Resumo de trabalho — work-platform-api (deployer)

**Data:** 07/07/2026  
**Repositório:** `work-platform-api-gabriel`  
**Contexto:** validação do fluxo E2E local **contrata-o → onboarding-api → work-platform v1**

---

## Objetivo

Permitir testar ponta a ponta o provisionamento de tenants em ambiente local, sem depender de um cluster Nextcloud real, e preparar a coleta de dados necessária para apontar o deployer para produção.

---

## Problemas encontrados e resolvidos

| Problema | Impacto | Solução aplicada |
|----------|---------|------------------|
| Cluster SSH indisponível no ambiente local | Provisionamento falhava com `503 cluster_unreachable` | Driver SSH simulado (`SSH_DRIVER=fake`) |
| Readiness gate exigia cluster real | Tenant não avançava para `active` após provision | Bypass automático do gate quando o driver é `fake` |
| Painel `/customers` com botão travado | Botão "Ressincronizar" ficava desabilitado incorretamente | Removida propriedade Alpine `$syncing` conflitante com Livewire |
| Navegador forçava HTTPS em `localhost` | `ERR_SSL_PROTOCOL_ERROR` no dev local | Ajuste no nginx Docker: HSTS e `HTTPS on` removidos em ambiente HTTP local |
| Conflito de assets Vite (porta 5173) | 404 de CSS/JS no painel admin | Removido arquivo `public/hot` que apontava para outro projeto na mesma porta |

---

## Entregas técnicas

### 1. Mock SSH para desenvolvimento local

Implementado um transporte SSH simulado que emula o comportamento do `nextcloud-manage` sem conectar a um servidor real:

- **`FakeSshClient`** — responde a comandos de provisionamento, jobs e OCC de forma determinística
- **`SimulateFakeJobWebhookJob`** — dispara webhook `job.finished` após comandos assíncronos, fechando o ciclo de provisionamento
- **Configuração** via `.env` (`SSH_DRIVER=fake`, `SSH_FAKE_*`) documentada em `.env.example`

> **Importante:** o driver `fake` é exclusivo para desenvolvimento local. Em produção continua-se usando `SSH_DRIVER=phpseclib3` com cluster real.

### 2. Checklist para cluster de produção

Criado runbook operacional em `docs/runbooks/cluster-prod-coleta-checklist.md` com:

- Dados a coletar no servidor do cluster (SSH, chaves, webhook secret)
- Dados a configurar no painel deployer (Cluster Servers)
- Variáveis de ambiente para onboarding-api
- Checklist de validação pós-configuração

### 3. Correções no painel admin

- Tela de clientes (`/customers`): botão de ressincronização funcional com feedback via `wire:loading`
- Remoção de código morto (`$syncing`) no componente Livewire

### 4. Ajustes de infraestrutura local

- Nginx Docker: headers e parâmetros FastCGI corrigidos para não forçar HTTPS em ambiente HTTP local

### 5. Script auxiliar de desenvolvimento

- `scripts/dev-create-onboarding-api-key.php` — facilita geração de API key para integração com onboarding-api no ambiente local

---

## Resultado

O fluxo E2E local foi validado com sucesso:

- Customer de teste provisionado e com status **`active`**
- Job de provisionamento concluído (`completed`)
- Painel admin operacional em ambiente Docker local

---

## Status atual

| Item | Status |
|------|--------|
| Código funcional | Implementado no working tree |
| Commit / PR | **Pendente** — alterações ainda não commitadas |
| Deploy produção | **Não afetado** — mock SSH não entra em prod |
| Coleta cluster prod | Checklist pronto; execução pendente (ação humana/infra) |

---

## Próximos passos sugeridos

1. **Revisar e commitar** as alterações (mock SSH + fixes de UI/nginx + runbook)
2. **Executar checklist** de coleta no cluster de produção real
3. **Configurar cluster prod** no painel deployer com credenciais reais
4. **Repetir smoke E2E** apontando onboarding-api para o deployer de produção (sem `SSH_DRIVER=fake`)

---

## Escopo fora deste resumo

As correções nos repositórios **onboarding-api** e **contrata-o** (DTOs, paginação da API comercial, mapper v1) foram tratadas em sessão paralela e não fazem parte deste documento.
