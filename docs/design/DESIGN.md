# Design System — meWork360 Deployer

> Fase 2 (Design) | Modo: PROTOTIPO + RETROFIT
> Engine: Stitch | Biblioteca: Livewire + Tailwind puro | Dark mode: Default (com Light opcional)

---

## 1. Identidade Visual

**Estilo**: High-Tech Professional (Minimalista, corporativo, técnico).
**Tom**: Engenharia de precisão, clareza, utilidade. O tom de voz é direto, informativo e sem jargões desnecessários, focado em resolver problemas operacionais com segurança.
**Público-alvo**: Operadores internos meWork360 (DevOps, SRE, Suporte N2). O design prioriza densidade de informação controlada e clareza de status para reduzir erros humanos.

### Referências visuais

| Referência | O que inspirou                                        | Link                       |
| ---------- | ----------------------------------------------------- | -------------------------- |
| Stitch UI  | Estrutura de layout, paleta dark M3, componentes base | `docs/design/refs/stitch/` |

### Tom de Voz e Microcopy

Para o painel admin interno (Marina, Rafael, Sofia), a comunicação deve ser:

- **Direta e técnica, mas não robótica**: "O webhook não respondeu em 60s" em vez de "Timeout 60000ms".
- **Foco em resolução**: Se um job falha, o erro deve sugerir a próxima ação (ex: "Verifique o log do job ou tente novamente").
- **Microcopy Operacional**:
  - Idempotency: "Aguarde, operação já em andamento..."
  - Webhook delay: "Aguardando confirmação do servidor..."
  - SSH timeout: "Conexão com o servidor falhou. O cluster pode estar offline."

---

## 2. Design Tokens

> **Nota**: O CSS completo com todos os tokens (dark e light) está em `docs/design/exports/tokens.css`. Abaixo, um resumo dos principais.

### 2.1 Cores (Resumo Dark Mode)

| Token                       | Valor                   | Uso                                        |
| --------------------------- | ----------------------- | ------------------------------------------ |
| `--color-background`        | `oklch(20.5% 0.04 260)` | Fundo principal da página                  |
| `--color-surface-container` | `oklch(24% 0.04 260)`   | Cards, painéis                             |
| `--color-primary`           | `oklch(82% 0.09 268)`   | Ação principal, links, botões              |
| `--color-secondary`         | `oklch(82% 0.03 268)`   | Ações secundárias, badges neutros          |
| `--color-tertiary`          | `oklch(80% 0.10 60)`    | Acentos quentes, badges de aviso (Warning) |
| `--color-error`             | `oklch(80% 0.10 25)`    | Erros, ações destrutivas                   |
| `--color-success`           | `oklch(82% 0.13 155)`   | Sucesso, operações concluídas (Híbrido)    |
| `--color-outline-variant`   | `oklch(34% 0.02 270)`   | Bordas, divisores                          |

### 2.2 Tipografia

| Propriedade            | Valor                          |
| ---------------------- | ------------------------------ |
| **Font family (sans)** | `Inter, system-ui, sans-serif` |
| **Font family (mono)** | `Fira Code, monospace`         |
| **Base size**          | `14px (0.875rem)`              |
| **Scale**              | Customizada (ver `tokens.css`) |

### 2.3 Espaçamento e Layout

| Contexto                | Valor          | Justificativa                              |
| ----------------------- | -------------- | ------------------------------------------ |
| **Unit**                | `4px`          | Base do grid                               |
| **Padding padrão**      | `16px (md)`    | Respiro interno de cards e células         |
| **Row height (tabela)** | `44px`         | Ajustado para WCAG touch target em desktop |
| **Sidebar width**       | `256px (w-64)` | Padrão de navegação lateral                |
| **Topbar height**       | `64px (h-16)`  | Barra superior                             |

### 2.4 Border Radius e Shadows

| Contexto            | Token            | Valor              |
| ------------------- | ---------------- | ------------------ |
| **Botões/Inputs**   | `--radius-md`    | `4px` (Sharp/Tech) |
| **Cards**           | `--radius-lg`    | `8px`              |
| **Avatares/Pills**  | `--radius-full`  | `9999px`           |
| **Shadow (Cards)**  | `--shadow`       | Elevação base      |
| **Shadow (Modais)** | `--shadow-modal` | Foco máximo        |

---

## 3. Biblioteca de Componentes

**Escolha**: Livewire + Tailwind puro + Componentes Customizados.
**Justificativa**: Fidelidade visual TOTAL ao protótipo Stitch entregue (que usa Tailwind puro com configuração customizada). O Filament 3 exigiria um override profundo dos tokens Material 3 e da estrutura de layout para chegar ao visual exato do Stitch. O Livewire permite construir as telas interativas (tabelas, modais, formulários) mantendo o HTML/CSS do Stitch como base.

### Componentes principais

| Componente       | Uso no projeto                             | Customização necessária                                                              |
| ---------------- | ------------------------------------------ | ------------------------------------------------------------------------------------ |
| **Button**       | Ações principais, secundárias, destrutivas | Variantes: primary, outline, ghost, destructive, icon-only.                          |
| **Input/Select** | Formulários, buscas, filtros               | Fundo escuro (`surface-container-lowest`), borda `outline-variant`, focus `primary`. |
| **Table**        | Listagem de customers, jobs, logs          | Zebra-striping opcional, hover row, sticky header (em logs longos).                  |
| **Status Badge** | Indicadores de estado (Active, Failed)     | Fundo 10% opacidade + texto 100% opacidade da cor semântica.                         |
| **Code Block**   | Logs, chaves, payloads                     | Fundo `terminal-bg` (#050b14), fonte mono, botão copy.                               |
| **Modal/Dialog** | Confirmações destrutivas (Remover)         | Backdrop blur, shadow-modal, foco retido.                                            |

---

## 4. Layout e Navegação

### Estrutura geral

```
+--------------------------------------------------+
| [TOPBAR]                                         |
| Logo    Busca (⌘K)                  Avatar  🔔   |
+--------------------------------------------------+
| [SIDEBAR]  | [CONTENT AREA]                      |
| Dashboard  | Título da Página          [Ação]    |
| Customers  | ┌─────────────────────────────────┐ |
| Fila       | │ Card / Tabela / Conteúdo        │ |
| Logs       | │                                 │ |
| Settings   | └─────────────────────────────────┘ |
+--------------------------------------------------+
```

### Responsividade

| Breakpoint              | Mudanças                                                         |
| ----------------------- | ---------------------------------------------------------------- |
| **Mobile (< 768px)**    | Sidebar oculta (menu hambúrguer), tabelas com scroll horizontal. |
| **Tablet (>= 768px)**   | Sidebar visível, grids ajustam para 2 colunas.                   |
| **Desktop (>= 1024px)** | Layout completo, grids com 3+ colunas.                           |

---

## 5. Mapa de Telas (18 Telas)

As 18 telas do REQUIREMENTS mapeadas contra o design system.

### Telas cobertas pelo Stitch (8)

1. **Dashboard Overview** (`/`) -> `dashboard_overview` + `dashboard_api_management`
2. **Fila de jobs** (`/queue`) -> `provisioning_queue`
3. **Detalhe do job** (`/queue/{id}`) -> `provisioning_logs`
4. **Audit log** (`/audit-log`) -> `logs_de_requisi_es`
5. **Cluster servers** (`/settings/cluster-servers`) -> Baseado em `configura_es_e_seguran_a`
6. **API keys** (`/api-keys`) -> `api_credentials` + `gerenciamento_de_credenciais` (Sprint 2)
7. **Settings security** (`/settings/security`) -> Baseado em `configura_es_e_seguran_a` (Sprint 2)

### Telas faltantes (10) - _Wireframes a serem gerados_

1. **Login** (`/login`)
2. **Lista de customers** (`/customers`)
3. **Provisionar customer** (`/customers/new`)
4. **Detalhe do customer** (`/customers/{slug}`)
5. **Gerir users do customer** (`/customers/{slug}/users`)
6. **Gerir groups do customer** (`/customers/{slug}/groups`)
7. **Gerir apps do customer** (`/customers/{slug}/apps`)
8. **Branding do customer** (`/customers/{slug}/branding`)
9. **Maintenance + ações destrutivas** (`/customers/{slug}/maintenance`)
10. **Operadores** (`/operators`)
11. **Perfil** (`/profile`)

---

## 6. Acessibilidade

| Requisito             | Implementação                                                                                |
| --------------------- | -------------------------------------------------------------------------------------------- |
| **Contraste WCAG AA** | Combinações texto/fundo validadas na paleta M3.                                              |
| **Touch targets**     | Linhas de tabela ajustadas para 44px (afrouxamento da densidade Stitch).                     |
| **Focus visible**     | Ring visível (`focus:ring-2 focus:ring-primary focus:outline-none`) em todos os interativos. |
| **Keyboard nav**      | Modais devem reter foco (trap); navegação por Tab funcional.                                 |
| **Color not sole**    | Status badges usam texto descritivo ("Failed") + ícone (opcional) + cor.                     |

---

## 7. Decisões de Design (ADRs)

### ADR-D001: Escolha da Biblioteca de Componentes

- **Contexto**: REQUIREMENTS pede Filament 3 ou Livewire. Stitch entregou HTML/Tailwind puro de alta qualidade.
- **Decisão**: Livewire + Tailwind puro (sem Filament).
- **Consequências**: Maior controle sobre o HTML/CSS para replicar o Stitch 1:1. Maior esforço inicial para criar componentes Livewire (tabelas, modais) comparado ao Filament.

### ADR-D002: Dark Mode Default com Light Opcional

- **Contexto**: Stitch entregou apenas Dark Mode.
- **Decisão**: Manter Dark Mode como padrão (adequado para painel técnico/SRE), mas gerar tokens Light Mode derivados da paleta M3 para suportar alternância futura.
- **Consequências**: CSS de tokens maior, mas prepara o sistema para usuários que preferem temas claros.

### ADR-D003: Paleta de Status Híbrida

- **Contexto**: Stitch usa lavanda para sucesso e laranja para warning, o que é ambíguo.
- **Decisão**: Adotar verde-cyan para sucesso (mantendo a harmonia fria do tema) e manter o laranja do Stitch para warning. Erro permanece coral.
- **Consequências**: Melhor usabilidade e reconhecimento imediato de status, sem quebrar a estética "high-tech".

### ADR-D004: Ajuste de Densidade

- **Contexto**: Tabelas do Stitch são muito densas (linhas de ~36px).
- **Decisão**: Afrouxar levemente as linhas para 44px (mínimo WCAG para touch targets).
- **Consequências**: Melhor usabilidade, especialmente em telas touch ou monitores de alta resolução.

### ADR-D005: Idioma da UI

- **Contexto**: Stitch entregou tudo em inglês. REQUIREMENTS exige pt-BR.
- **Decisão**: Traduzir todo o microcopy, labels e status para pt-BR durante a implementação.
- **Consequências**: Alinhamento com o requisito do projeto.

### ADR-D006: Identidade da Marca

- **Contexto**: Múltiplos nomes no Stitch (CloudAdmin, DevPortal, etc).
- **Decisão**: Unificar sob o nome "meWork360 Deployer".
- **Consequências**: Marca única e consistente em todo o painel.

---

## 8. UX Audit — 2026-07-05 (fricções de provisionamento + inconsistência visual)

> Modo: UX (`/designer ux`). Gatilho: sessão de operação real no LAB (provisionamento `pacoteste`, remoção de cluster legado, criação de usuários) revelou fricções de uso e desvio do design system. Achados abaixo alimentam `/pmo plan`; nenhuma task/ID de sprint foi definida aqui (fora do escopo do Designer).

### 8.1 Inconsistência de biblioteca visual

Duas gerações de tela coexistem no mesmo painel:

| Padrão | Telas | Exemplo |
|--------|-------|---------|
| **Tokens Tailwind semânticos** (correto, alinhado a este DESIGN.md) | `cluster-servers/index`, `cluster-servers/create`, dashboard | `bg-surface-container`, `text-on-surface`, `text-error` |
| **CSS inline com hex cru** (legado, pré-D006) | `customers/create`, `customers/show`, `customers/occ-panel` | `<style> .badge-active { background: #1c3a2f; color: #68d391; }` |

Efeito: manutenção duplicada de paleta, risco de drift de contraste (WCAG AA já validado só no lado tokenizado), e badges/tabelas com visual levemente diferente entre `/customers` e `/cluster-servers`.

**Recomendação**: migrar as 3 views de `customers/*` para as classes semânticas já usadas em `cluster-servers/index.blade.php` (mesmo componente de badge, tabela, card). Sem mudança de informação — só de implementação visual.

### 8.2 Fluxo de provisionamento sem feedback de progresso

- Página do customer não atualiza sozinha (`running` parado na tela até reload manual).
- `JobLogFetcher` já existe no backend mas só é consumido no fechamento do job — o operador não vê o log rodando.
- **Recomendação**: polling Livewire (`wire:poll.5s` na página `customers/show` enquanto `status` não é terminal) + trecho de log tail (últimas N linhas) no card "Jobs recentes".

### 8.3 `provisioning_finishing` é caixa-preta

- Readiness gate roda em background (`ProbeCustomerReadinessJob`, até 10 tentativas / 1200s) sem expor motivo de falha nem contagem de tentativas na UI.
- **Recomendação**: novo campo/computed no card de Detalhes — "Readiness: tentativa 3/10 — último erro: HTTP 404 em /login" — lido do `AuditLog` (`customer_readiness_timeout`) ou de um novo campo `last_probe_result` no customer.

### 8.4 Domínio aceita barra final (causou incidente real hoje)

- `Create.php` (`customers/create`) e a API não normalizam `domain` — um FQDN com `/` no final quebra a regra do Traefik (`Host(...)`), `trusted_domains` e o probe de readiness (404 silencioso).
- **Recomendação (UX + validação)**:
  - Normalizar no `updatedDomain()`/`submit()`: `rtrim(trim(strtolower($domain)), '/')` antes de validar e enviar.
  - Regex de validação explícita de FQDN (`/^[a-z0-9-]+(\.[a-z0-9-]+)+$/`, sem barra, sem protocolo).
  - Mesmo tratamento na API pública (`POST /v1/tenants`) — não confiar só no client.
  - Hint visual mostrando o domínio final "limpo" abaixo do campo antes de submeter (preview).

### 8.5 Sem UI para listar usuários ativos do tenant

- Usuário admin (criado no provisionamento) só é visível via SSH/`.credentials` no host — sem qualquer verificação no painel antes de tentar criar outro.
- Backend já suporta: `OccPassthroughService::exec($customer, 'user:list')` (mesmo verbo usado no readiness gate) — **não precisa de nova capability upstream**.
- **Recomendação**: nova sub-aba "Usuários" no Painel OCC exibindo tabela (`user:list --json`: username, email, quota, grupos) + botões inline de criar/deletar já existentes, eliminando a necessidade de "adivinhar" se um username está livre.

### 8.6 Erro de criação de usuário só aparece depois, escondido

- `createUser()` usa `LifecycleAsyncAction` (fluxo assíncrono) — a UI mostra "Usuário enfileirado" mesmo quando o Nextcloud vai rejeitar (ex.: `admin` já existe, senha fraca). O erro real só existe no `summary` do job, visível apenas na tabela "Jobs recentes" da página do customer (fora do Painel OCC).
- **Recomendação**:
  - Bloquear client-side o username `admin` (reservado) com hint explicativo.
  - Alinhar a validação de senha do form (hoje `>= 8`) com a política real do Nextcloud 33 neste ambiente (`>= 10`, rejeita comuns/vazadas) — mensagem de erro citando a política, não só o mínimo.
  - Depois de enfileirar, iniciar `wire:poll` curto no próprio Painel OCC até o job entrar em estado terminal, e mostrar o resultado real (sucesso/motivo da falha) inline — não só "enfileirado".

### 8.7 Cluster legado sem ação de remoção na UI

- Remover um `cluster_server` deprecado (ex.: `lab-upstream`) hoje exige SSH + `tinker` — sem exposição no `/cluster-servers`.
- **Recomendação**: botão "Remover" (soft-delete) na tabela de clusters, com guarda (bloquear se houver customers ativos vinculados) e confirmação por nome — mesmo padrão do modal de remoção de customer.

### 8.8 Prioridade sugerida (não vinculante — decisão de sprint cabe a `/pmo plan`)

1. **8.4** (trailing slash) — causa incidente real, correção pequena e isolada.
2. **8.5 + 8.6** (usuários) — mesmo fluxo, backend já pronto, maior redução de erro operacional.
3. **8.2 + 8.3** (feedback de progresso/readiness) — UX de espera, sem bloqueio funcional.
4. **8.1** (consistência visual) — retrofit incremental, sem urgência funcional.
5. **8.7** (remover cluster na UI) — conveniência operacional, baixa frequência de uso.
