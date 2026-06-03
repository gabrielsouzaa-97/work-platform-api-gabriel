# Agent Notes
<!-- Max 100 lines. Remove obsolete entries periodically. Last updated: 2026-06-02 -->

## Tried & Rejected
<!-- Approaches tried and rejected. Agent MUST consult before proposing. -->

## Constraints
<!-- Known technical limitations of this project. -->
- `git reset --hard` é bloqueado pelo hook `classify_operation` mesmo quando seguro (ex.: ramificar commit órfão de main após criar feature branch). Pedir ao usuário rodar manualmente quando necessário.

## Preferences
<!-- User preferences not covered by rules. -->

## Lessons Learned
<!-- Mistakes corrected, wrong approaches, gotchas discovered. -->
- [2026-05-20] Em `/pmo new` / `/pmo fix` ou qualquer fluxo que escreve docs+código com commit, criar feature branch (`<initials>/<tipo>/<descricao>`) ANTES do primeiro Write — `main` é protegida; apenas `chore: register CI failure` vai direto. Pattern de PR confirmado em PR #40, #42, #44. [U]

## Reminders
<!-- Future-sprint reminders, deferred work, things to check later. -->

## Project-Specific
<!-- Non-obvious conventions, patterns, or facts unique to this project. -->
- [2026-06-02] **PMO registro**: ISSUE-021 (OpenAPI envelope), ISSUE-022 (cross-repo deploy-scripts), ISSUE-023 (validação prod F10.3 + `failed_jobs`); FINDINGS `DOC-001`, `OPS-001`. ISSUE-013/009 atualizados com evidência SSH prod (1/5 jobs null em 7d). Upstream local: `../mework360-deploy-scripts`; meMail: `../../cursor/mework360_memail` (app NC, não API).
- [2026-06-02] **Beesy #189 (Windows)**: `/jarvis pipeline` e `pipeline.sh` devem rodar no **WSL2**, não no PowerShell nativo. Ver `docs/upgrades/PLAN-2026-06-02.md`.
- [2026-06-02] Hook local `.cursor/hooks/pmo-update.sh` é **customizado** (atualiza `ultimo_update` + `docs/CHANGELOG.md`). Não substituir pelo no-op global da Decision #194 sem revisar impacto.
- Suite de webhook completa: `docker compose exec -T app php artisan test --filter='WebhookPayload|WebhookHandler|VerifyWebhookHmac|WebhookReceive'` (46 testes, ~2s).
- Testes rodam dentro do container `app` via docker compose — comandos `php artisan` no host falham por falta de extensão/runtime PHP local.
- Padrão de commit em Sprint N retroativa: `docs(sprint-N{n}): planning output + implementation` cobrindo ISSUES.md + ROADMAP.md + .cursorsession + arquivos de código no mesmo commit (precedente: PR #44 / Sprint N2).
