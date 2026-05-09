<!-- FINDINGS-INDEX
FINDINGS-INDEX -->

# Findings — mework360-deployer

> Fonte de verdade para findings de QA, auditoria e validação.

## Estatísticas

| Sprint | CRITICAL | HIGH | MEDIUM | LOW | Pendentes | Corrigidos | Validados |
|--------|----------|------|--------|-----|-----------|------------|-----------|
| D1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| D2 | 0 | 0 | 0 | 1 | 1 | 0 | 0 |
| D3 | 0 | 2 | 7 | 1 | 4 | 0 | 6 |

---

## Findings

Nenhum finding registrado para D1 na validação atual.

---

### D2-F001 — LOW — phpseclib/phpseclib não instalado (requer composer install manual)

- **Sprint**: D2
- **Severidade**: LOW
- **Status**: Corrigido
- **Arquivo**: `composer.json`
- **Descrição**: A dependência `phpseclib/phpseclib:^3.0` foi adicionada ao `composer.json` mas não pôde ser instalada automaticamente porque o shell tool está bloqueado pelo hook `./hooks/rtk-rewrite.sh` (retorna JSON inválido). Os testes do `SshClientTest.php` exigem que a classe `phpseclib3\Net\SSH2` esteja disponível via autoload.
- **Ação necessária**: Executar `composer install` (ou `docker compose exec app composer install`) no terminal do usuário antes de rodar os testes da Sprint D2.
- **Impacto**: Testes Feature/Core/SshClientTest falham até a dependência ser instalada. Restante dos testes (Unit/Core/{JobTypeTranslatorTest, StateTranslatorTest, SlugRuleTest}) não são afetados.

---

### D3-F001 — HIGH — Aceite de convite não revalida assinatura/expiração no submit Livewire

- **Sprint**: D3
- **Severidade**: HIGH
- **Status**: Validado
- **Arquivo**: `app/Http/Livewire/Auth/AcceptInvite.php`
- **Descrição**: A rota GET usa middleware `signed`, mas `acceptInvite()` ativa a conta sem validar assinatura/expiração novamente nem consultar um token persistido. O teste atual chama o componente diretamente com `Livewire::test(AcceptInvite::class, ['operator' => $operator])`, o que exercita ativação sem URL assinada.
- **Ação necessária**: Persistir convite com token/expiração server-side e consumir em transação, ou revalidar dados assinados no submit antes de ativar a conta.
- **Impacto**: Um formulário carregado antes da expiração pode ativar conta depois das 48h; testes não protegem o contrato real de convite assinado.
- **Correção**: Convites agora usam token persistido com hash + expiração server-side e consumo transacional; o submit revalida estado, token e TTL antes de ativar a conta.

### D3-F002 — HIGH — Operador desativado mantém acesso em sessão existente

- **Sprint**: D3
- **Severidade**: HIGH
- **Status**: Validado
- **Arquivo**: `app/Providers/AppServiceProvider.php`
- **Descrição**: O login bloqueia `status != active`, mas os gates verificam somente `role`. A ação `deactivate()` altera o status para `inactive`, sem invalidar sessões existentes nem bloquear autorização posterior de usuários já autenticados.
- **Ação necessária**: Centralizar bloqueio de usuários não ativos em middleware/gates/policies e invalidar sessões do operador ao desativar.
- **Impacto**: Um operador desativado pode continuar acessando rotas autenticadas até logout/expiração da sessão.
- **Correção**: Middleware autenticado bloqueia operadores não ativos, gates exigem status active e desativação remove sessões database do operador.

### D3-F003 — MEDIUM — Dashboard admin acessível a qualquer usuário autenticado

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `routes/web.php`
- **Descrição**: `/admin/dashboard` está protegido apenas por `auth`, sem `can:manage-operators` ou middleware de role admin.
- **Ação necessária**: Proteger a rota com gate/role admin e adicionar teste cobrindo `suporte`/`operador` com 403.
- **Impacto**: A fronteira de privilégios fica inconsistente e conteúdo admin futuro pode ser exposto a não-admins.
- **Correção**: `/admin/dashboard` agora exige gate admin (`manage-operators`) e possui teste de 403 para perfil suporte.

### D3-F004 — MEDIUM — Tabela `sessions.user_id` incompatível com UUID de operadores

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `database/migrations/2026_05_08_164611_create_sessions_table.php`
- **Descrição**: `sessions.user_id` usa `foreignId()`/BIGINT, enquanto `operators.id` é UUID string. Se `SESSION_DRIVER=database` for usado, sessões autenticadas tentarão gravar UUID em coluna numérica.
- **Ação necessária**: Trocar para coluna UUID/string ou documentar/remover o contrato de sessão em database, mantendo Redis como driver obrigatório.
- **Impacto**: Login e persistência de sessão podem quebrar em ambiente que use database sessions.
- **Correção**: Migration incremental troca `sessions.user_id` para UUID mantendo compatibilidade com `operators.id`.

### D3-F005 — MEDIUM — Gate de suporte para `/customers/create` não está exercitado

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `routes/web.php`
- **Descrição**: O gate da D3 exige que suporte receba 403 em `/customers/create` e não veja opções de provisionar/remover. A rota ainda não existe e a suíte cobre apenas bloqueio em `/operators`.
- **Ação necessária**: Adicionar teste/rota sentinela ou registrar explicitamente esse gate para D6, garantindo que suporte não veja ações destrutivas em `/customers` e receba 403 em criação.
- **Impacto**: A restrição crítica de operações destrutivas pode regredir na D6 sem alarme de teste.
- **Correção**: Rota sentinela `/customers/create` foi protegida por gate `provision-customers`; suporte recebe 403 em teste.

### D3-F006 — MEDIUM — Testes do convite não comprovam URL assinada real e TTL de 48h

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Validado
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: A suíte valida que o mailable foi enviado, mas não valida que o `signedUrl` está no email, aponta para a rota correta, tem assinatura válida antes de 48h e falha após expirar. O happy path também chama o componente diretamente, sem atravessar a URL real.
- **Ação necessária**: Capturar o mailable fake, validar/renderizar o link e testar o fluxo real GET link assinado -> definir senha -> autenticar -> redirecionar.
- **Impacto**: Quebras entre email, rota assinada, binding e componente Livewire podem passar despercebidas.
- **Correção**: Testes agora validam URL assinada real no mailable, presença do token, GET do link, TTL de 48h e recusa após expiração server-side.

### D3-F007 — MEDIUM — Migration de sessões descarta `user_id` existente

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Pendente
- **Arquivo**: `database/migrations/2026_05_08_164612_fix_sessions_user_id_uuid.php`
- **Descrição**: A migration troca `sessions.user_id` com `dropColumn()` + recriação, perdendo o vínculo `user_id` de sessões já existentes.
- **Ação necessária**: Antes de usar `SESSION_DRIVER=database` em ambiente com sessões reais, migrar preservando os valores quando possível ou documentar encerramento forçado de sessões durante o deploy.
- **Impacto**: Sessões pré-migration podem não ser removidas por `deactivate()`, embora o middleware `active.operator` ainda bloqueie no próximo request.

### D3-F008 — MEDIUM — Teste de aceite não comprova autenticação final explicitamente

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: O teste valida ativação e redirect após senha, mas não usa `assertAuthenticatedAs` para comprovar sessão autenticada com o operador aceito.
- **Ação necessária**: Adicionar asserção explícita de autenticação no fluxo de aceite do convite.
- **Impacto**: Uma regressão que ativa a conta e redireciona sem login real pode passar despercebida.

### D3-F009 — MEDIUM — Sentinela de autorização não cobre remoção de customers

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Pendente
- **Arquivo**: `routes/web.php`
- **Descrição**: A validação cobre `/customers/create`, mas ainda não há rota/policy/teste sentinela para remoção de customers.
- **Ação necessária**: Em D6, garantir rota/policy/teste para `customers.destroy` bloqueando suporte e outros perfis sem permissão destrutiva.
- **Impacto**: A restrição de ações destrutivas pode regredir quando remoção de customers for implementada.

### D3-F010 — LOW — Email de convite não é renderizado nos testes

- **Sprint**: D3
- **Severidade**: LOW
- **Status**: Pendente
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: A suíte valida `signedUrl` no mailable, mas não renderiza o HTML para garantir que o link aparece no corpo entregue.
- **Ação necessária**: Renderizar o mailable em teste e verificar presença da URL no HTML.
- **Impacto**: Uma quebra no template de email pode ocultar o link mesmo com o mailable contendo a URL correta.
