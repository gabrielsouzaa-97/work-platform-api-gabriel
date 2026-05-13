<!-- FINDINGS-INDEX
FINDINGS-INDEX -->


# Findings — mework360-deployer

> Fonte de verdade para findings de QA, auditoria e validação.

## Estatísticas

| Sprint | CRITICAL | HIGH | MEDIUM | LOW | Pendentes | Corrigidos | Validados |
|--------|----------|------|--------|-----|-----------|------------|-----------|
| D1 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| D2 | 0 | 0 | 0 | 1 | 0 | 1 | 0 |
| D3 | 0 | 2 | 7 | 1 | 1 | 3 | 6 |
| D4 | 0 | 2 | 4 | 3 | 4 | 3 | 2 |
| D5 | 0 | 0 | 1 | 3 | 0 | 4 | 0 |

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
- **Status**: Corrigido
- **Arquivo**: `database/migrations/2026_05_08_164612_fix_sessions_user_id_uuid.php`
- **Descrição**: A migration trocava `sessions.user_id` com `dropColumn()` + recriação sem truncar a tabela primeiro, deixando sessões BIGINT "fantasmas" que `deactivate()` não conseguia remover por `user_id`.
- **Correção**: `DB::table('sessions')->truncate()` adicionado no início do `up()` e do `down()`. BIGINT → UUID é incompatível por definição; todas as sessões existentes são invalidadas explicitamente no deploy. O `active.operator` middleware garante que qualquer sessão residual seja bloqueada no próximo request. Comentário na migration documenta o comportamento esperado.

### D3-F008 — MEDIUM — Teste de aceite não comprova autenticação final explicitamente

- **Sprint**: D3
- **Severidade**: MEDIUM
- **Status**: Corrigido
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: O teste validava ativação e redirect após senha, mas não usava `assertAuthenticatedAs` para comprovar sessão autenticada com o operador aceito.
- **Correção**: `$this->assertAuthenticatedAs($operator)` adicionado ao final do teste "accept invite with valid signed URL activates operator and logs in", garantindo que a sessão está autenticada com o operador recém-ativado.

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
- **Status**: Corrigido
- **Arquivo**: `tests/Feature/Operators/CreateTest.php`
- **Descrição**: A suíte validava `signedUrl` no mailable, mas não renderizava o HTML para garantir que o link aparece no corpo entregue.
- **Correção**: Novo teste "invite email HTML contains the signed URL in the rendered body" usa `$mailable->render()` para renderizar o HTML e verifica presença da `$signedUrl`, do texto "Ativar minha conta" e do nome do operador — protegendo contra quebras no template `emails/operator-invite.blade.php`.

---

### D4-F001 — HIGH — SshClient::executeCommand — payloadStdin escrito após exec() retornar (latente F3)

- **Sprint**: D4
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Arquivo**: `app/Modules/Core/Ssh/SshClient.php` (método `executeCommand`)
- **Descrição**: `$ssh->exec($command)` bloqueia até o comando remoto concluir. Somente após retornar é que `$ssh->write($payloadStdin)` era chamado — quando o canal já estava fechado. O `payloadStdin` nunca chegava ao processo remoto.
- **Correção**: Adicionado método privado `pipeStdin()` que constrói `printf %s <payload_escapado> | <comando>`. Em `executeCommand()`, quando `$payloadStdin !== null`, o piping é feito ANTES de `exec()`. O `logExecution()` continua recebendo o comando limpo (sem payload) para não vazar segredos nos logs. Removido `$ssh->write()` — nunca mais chamado. Dois novos testes adicionados em `SshClientTest.php`: verificação de que o payload aparece no comando passado ao `exec()` e de que `write()` não é invocado (`shouldNotReceive`).

---

### D4-F002 — MEDIUM — Teste D3-F010 falha — asserção verifica URL não-escapada vs HTML com `&amp;`

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Status**: Corrigido
- **Arquivo**: `tests/Feature/Operators/CreateTest.php` (linha 208), `resources/views/emails/operator-invite.blade.php`
- **Descrição**: O teste adicionado pelo D3-F010 chama `->toContain($signedUrl)` onde `$signedUrl` contém `&` entre query params. O template usa `{{ $signedUrl }}` (Blade HTML-escaping), que persiste `&amp;` no HTML renderizado. O teste falha porque procura `&token=` mas encontra `&amp;token=`. O email funciona corretamente em clientes de email (que decodificam `&amp;` → `&`), confirmado em Fase 2 via Mailpit — o link "Ativar minha conta" abre a página correta.
- **Impacto**: Suite CI falha em 1 teste (D3-F010 closure), gerando falso positivo.
- **Ação necessária**: Corrigir a asserção no teste para `->toContain(e($signedUrl))` ou `->toContain(htmlspecialchars($signedUrl, ENT_QUOTES))`. O template está correto — `{{ }}` com HTML-escaping é o comportamento esperado do Blade.

**Cenários mínimos sugeridos:**
- [ ] Happy path: HTML renderizado contém `htmlspecialchars($signedUrl)` (URL com `&amp;`)
- [ ] Edge case: URL sem parâmetros extras (sem `&`) → `{{ $signedUrl }}` == `{!! $signedUrl !!}` → teste passa em ambos os casos

---

### D4-F003 — HIGH — 8 testes Feature/ClusterServers/StoreTest falham (D4.1 não implementado)

- **Sprint**: D4
- **Severidade**: HIGH
- **Tipo**: product_bug
- **Status**: Validado
- **Arquivo**: `tests/Feature/ClusterServers/StoreTest.php`, `routes/web.php`
- **Descrição**: Os testes da Sprint D4 foram escritos em TDD (correto) mas as implementações não existem ainda: rota `cluster-servers.index` não registrada, Livewire components `ClusterServers\{Index,Create,Edit}` ausentes. Gate da Sprint D4 exige que todos esses testes passem. Erros: `RouteNotFoundException` e `ComponentNotFoundException`.
- **Impacto**: Gate da D4 bloqueado. 8/9 falhas na suíte (9 total na suite, 8 neste arquivo).
- **Ação necessária**: Implementar D4.1 (CRUD Livewire ClusterServers + rotas). Ver mini design doc no ROADMAP.md seção "4.1 — Module ClusterServers CRUD".

**Cenários pendentes (budget 8 testes, todos failing):**
- [ ] admin acessa index e vê clusters listados
- [ ] operador comum recebe 403 em GET /cluster-servers
- [ ] admin cria cluster_server com PEM válido → redireciona e persiste no DB
- [ ] PEM inválido retorna erro de validação
- [ ] operador comum não consegue salvar via Livewire (gate bloqueia)
- [ ] admin edita nome do cluster_server → persiste no DB
- [ ] ClusterServer listado em Index tem botões de ação (Test, Rotate, Edit)
- [ ] webhook_secret_encrypted é gerado server-side na criação

---

### D4-F004 — MEDIUM — No docker-compose dev, nenhum queue worker em execução → convites não enviados automaticamente

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: environment
- **Status**: Pendente
- **URL**: http://localhost:8080/operators/create
- **Ação**: Admin cria operador → clica "Enviar convite"
- **Esperado**: Email de convite entregue ao Mailpit automaticamente em poucos segundos
- **Obtido**: Email fica na fila Redis sem processamento. Mailpit mostra "No messages". Requer execução manual de `php artisan queue:work --once` para processar.
- **Arquivo**: `docker-compose.yml`, `app/Mail/OperatorInviteMail.php`
- **Descrição**: `OperatorInviteMail` implementa `ShouldQueue`. O `docker-compose.yml` define `QUEUE_CONNECTION: ${QUEUE_CONNECTION:-database}` como padrão, mas o ambiente em execução usa `QUEUE_CONNECTION=redis` (provavelmente via `.env`). Não há serviço `worker` no docker-compose que processe a fila automaticamente.
- **Ação necessária**: Adicionar serviço `worker` ao `docker-compose.yml` (`php artisan queue:work --tries=3`) ou mudar `QUEUE_CONNECTION` para `sync` no `.env.example` para desenvolvimento.

---

### D4-F006 — MEDIUM — Toast de rotação exibe timestamp incorreto (`valid_from - 1s` em vez de expiry do secret anterior)

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: product_bug
- **Status**: Corrigido
- **Arquivo**: `app/Http/Livewire/ClusterServers/Index.php` (método `rotateSecret`)
- **Descrição**: O cálculo `$new->valid_from->subSeconds(1)` retornava ≈ `now() - 1 segundo` como data limite do grace period. `$new` é o registro **novo** (válido de agora em diante), portanto `valid_from - 1s` é praticamente o instante atual, não quando a versão anterior expira (`now() + 24h`). O toast exibia uma data no passado como se o grace period já tivesse expirado.
- **Correção**: Substituído por `$new->valid_from->copy()->addHours(config('services.webhook.grace_period_hours', 24))`, calculando corretamente o fim do grace period da versão anterior.

---

### D4-F007 — LOW — `ClusterHealthCheckCommand` usa `whereNotNull('id')` redundante

- **Sprint**: D4
- **Severidade**: LOW
- **Tipo**: code_smell
- **Status**: Corrigido
- **Arquivo**: `app/Console/Commands/ClusterHealthCheckCommand.php`
- **Descrição**: `ClusterServer::whereNotNull('id')->get()` — a condição `id IS NOT NULL` é sempre verdadeira para registros existentes e não expressa a intenção real. Com o global scope de `SoftDeletes`, o Eloquent já exclui registros soft-deletados automaticamente. O código era funcionalmente equivalente a `ClusterServer::all()` mas mais confuso.
- **Correção**: Substituído por `ClusterServer::all()`.

---

### D4-F008 — MEDIUM — PEM da chave SSH em propriedade Livewire síncrona (security debt vs executor_prompt)

- **Sprint**: D4
- **Severidade**: MEDIUM
- **Tipo**: security_debt
- **Status**: Pendente
- **Arquivo**: `app/Http/Livewire/ClusterServers/Create.php`
- **Descrição**: O executor_prompt especificava `WithFileUploads` + `TemporaryUploadedFile` para o PEM, evitando que a chave privada trafegasse como propriedade Livewire. A implementação usa `public string $ssh_private_key = ''` com `wire:model`, o que significa que o PEM completo é serializado no snapshot do componente Livewire (cookie encriptado ou sessão server-side) e reenviado em cada request do ciclo de vida do componente. Em ambientes com `APP_DEBUG=true` ou com ferramentas de observabilidade que registram request bodies, o PEM pode ser exposto.
- **Ação necessária**: Migrar para `WithFileUploads` + `TemporaryUploadedFile` conforme especificado no executor_prompt, ou garantir que `APP_DEBUG=false` em produção e que nenhum middleware/proxy registra Livewire request bodies com o PEM.
- **Impacto**: Em produção com debug desligado, risco direto é baixo (Livewire criptografa o snapshot). Risco aumenta em staging/dev ou com ferramentas de log/APM mal configuradas.

---

### D4-F009 — LOW — Rotate webhook secret não registra ação específica no AuditLog

- **Sprint**: D4
- **Severidade**: LOW
- **Tipo**: compliance_gap
- **Status**: Pendente
- **Arquivo**: `app/Observers/ClusterServerObserver.php`, `app/Modules/ClusterServers/Actions/RotateWebhookSecretAction.php`
- **Descrição**: O mini design doc da tarefa 4.3 especifica `acao=rotate_webhook_secret` no AuditLog. A implementação atual depende do observer genérico: quando `RotateWebhookSecretAction` chama `$cluster->update([...])`, o observer registra `cluster_server.update` (não `cluster_server.rotate_webhook_secret`). O registro existe mas com semântica genérica — não é possível filtrar por tipo de operação "rotate" no painel de audit.
- **Ação necessária**: Adicionar `AuditLog::create([..., 'action' => 'cluster_server.rotate_webhook_secret', ...])` explicitamente no `RotateWebhookSecretAction::execute()`, ou no método Livewire `rotateSecret()` após a ação concluir.
- **Impacto**: Trail de auditoria existe, mas não é semanticamente preciso para operações de rotação de segredo. Impacto LGPD baixo (a operação está registrada), impacto operacional baixo (filtro por "rotate" não funciona).

---

### D4-F005 — LOW — Mensagens de validação e páginas de erro em inglês (app em pt-BR)- **Sprint**: D4
- **Severidade**: LOW
- **Tipo**: product_bug
- **Status**: Pendente
- **URL**: http://localhost:8080/operators/create, http://localhost:8080/operators
- **Ação**: Submeter formulário vazio; acessar rota protegida sem permissão
- **Esperado**: Mensagens em pt-BR ("O campo nome é obrigatório.", "Ação não autorizada.")
- **Obtido**: Inglês — "The name field is required.", "This action is unauthorized."
- **Arquivo**: `config/app.php` (locale), `resources/lang/` (não existe)
- **Descrição**: Laravel usa locale `en` por padrão. As mensagens de validação e erros HTTP aparecem em inglês apesar do sistema ser pt-BR. Arquivos de lang pt-BR não foram publicados (`php artisan lang:publish` não executado).
- **Ação necessária**: Executar `composer require laravel-lang/lang` (ou `php artisan lang:publish`) e definir `'locale' => 'pt_BR'` em `config/app.php`.

---

### D5-F001 — MEDIUM — WebhookPayload campos obrigatórios não validados antes do fromArray()

- **Sprint**: D5
- **Severidade**: MEDIUM
- **Tipo**: security_gap
- **Status**: Corrigido
- **Arquivo**: `app/Http/Middleware/VerifyWebhookHmac.php`
- **Descrição**: O middleware validava apenas `finished_at` do payload antes de passar ao controller. Se o payload chegasse sem `job_id`, `state`, `cmd` ou `client`, `WebhookPayload::fromArray()` lançaria `Undefined array key` resultando em HTTP 500 ao invés de 422.
- **Correção**: Middleware agora valida todos os campos obrigatórios (`job_id`, `state`, `cmd`, `client`, `finished_at`) retornando 422 `invalid_payload` se qualquer um estiver ausente.

---

### D5-F002 — LOW — WebhookHandler criava AuditLog duplicado no idempotent path

- **Sprint**: D5
- **Severidade**: LOW
- **Tipo**: logic_gap
- **Status**: Corrigido
- **Arquivo**: `app/Modules/Jobs/Services/WebhookHandler.php`
- **Descrição**: Quando o mesmo webhook chegava duas vezes para o mesmo job_id+state, a segunda chamada executava a transação completa e criava um segundo `AuditLog` entry `webhook_received`. O estado permanecia correto mas o audit log ficava poluído.
- **Correção**: Early return quando `$job->state === $canonical` — o no-op idempotente agora é silencioso (sem AuditLog duplicado).

---

### D5-F003 — LOW — Rate limit no webhook não gera log de segurança

- **Sprint**: D5
- **Severidade**: LOW
- **Tipo**: observability_gap
- **Status**: Corrigido inline
- **Arquivo**: `app/Http/Middleware/VerifyWebhookHmac.php`
- **Descrição**: Tentativas de flood que atingem o rate limit (100 req/min/IP) retornavam 429 silenciosamente, sem entrada no canal de segurança. Ataques volumétricos ficavam invisíveis para monitoramento.
- **Correção**: Adicionado `Log::channel('security')->warning('webhook.rate_limit', ['ip' => $ip])` no path de rate limit.

---

### D5-F004 — LOW — customerFilter aceita LIKE wildcards sem escape

- **Sprint**: D5
- **Severidade**: LOW
- **Tipo**: logic_gap
- **Status**: Corrigido
- **Arquivo**: `app/Http/Controllers/Api/JobController.php`
- **Descrição**: O filtro `customer` nos endpoints `GET /api/queue` e `GET /queue` (Livewire) interpolava a string diretamente em `LIKE "%{$c}%"`. Um valor como `%` retornava todos os jobs; `_` atuava como wildcard de caractere único. Não é injection (query builder usa prepared statements) mas produz resultados inesperados.
- **Correção**: Aplicado `addcslashes($c, '%_')` antes da interpolação no LIKE.

---
