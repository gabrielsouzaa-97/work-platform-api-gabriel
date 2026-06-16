#!/usr/bin/env bash
#
# arch-panel.sh — "Arquiteto planejar" com painel adversarial de subagentes.
#
# Protocolo (sem gate de eficiência — painel completo):
#
#        ┌─────────────────────────────────────────────────────────────┐
#        │  1. OPUS escreve o plano de arquitetura v0  (read-only/plan)  │
#        └───────────────────────────┬─────────────────────────────────┘
#                                     │ v0.md
#          ┌──────────┬──────────┬────┴─────┬──────────┬──────────┐
#          ▼          ▼          ▼          ▼          ▼          (paralelo)
#     ┌────────┐ ┌────────┐ ┌─────────┐ ┌──────┐ ┌──────────┐
#     │Arquiteto│ │Dev Sr. │ │Segurança│ │ SRE  │ │ Advogado │   2. CRÍTICOS
#     │(Composer)│ │(Comp.) │ │ (Comp.) │ │(Comp)│ │  (Comp.) │      Composer
#     └────┬────┘ └───┬────┘ └────┬────┘ └──┬───┘ └────┬─────┘
#          └──────────┴──────────┴──────────┴──────────┘
#                                     │ critique-*.md
#        ┌───────────────────────────┴─────────────────────────────────┐
#        │  3. OPUS sintetiza o plano FINAL revisando sobre as críticas  │
#        └───────────────────────────────────────────────────────────────┘
#                                     │ final.md
#
# Uso:
#   ./arch-panel.sh "Migrar o módulo de billing para serviço próprio"
#   ./arch-panel.sh -f objetivo.md
#   OPUS_MODEL=... COMPOSER_MODEL=... OUTDIR=... ./arch-panel.sh "..."
#
# Rode a partir da RAIZ do projeto que quer analisar (usa o cwd como workspace).
# Requer: cursor-agent no PATH e CURSOR_API_KEY exportada (ou login no app).

set -euo pipefail

OPUS_MODEL="${OPUS_MODEL:-claude-opus-4-8-thinking-high}"
COMPOSER_MODEL="${COMPOSER_MODEL:-composer-2.5}"
OUTDIR="${OUTDIR:-.arch-panel/$(date +%Y%m%d-%H%M%S)}"

# ── argumentos ────────────────────────────────────────────────────────────
if [[ $# -eq 0 ]]; then
  echo "uso: $0 \"<objetivo de arquitetura>\"   |   $0 -f <arquivo.md>" >&2
  exit 1
fi
if [[ "${1:-}" == "-f" ]]; then
  [[ -f "${2:-}" ]] || { echo "arquivo não encontrado: ${2:-}" >&2; exit 1; }
  OBJETIVO="$(cat "$2")"
else
  OBJETIVO="$*"
fi

command -v cursor-agent >/dev/null 2>&1 || { echo "cursor-agent não está no PATH" >&2; exit 1; }
mkdir -p "$OUTDIR"

# ── helper: roda um agente headless, read-only (plan mode) ─────────────────
# run <model> <outfile> <prompt>
run() {
  local model="$1" out="$2" prompt="$3"
  cursor-agent -p --plan --trust --model "$model" --output-format text "$prompt" >"$out" 2>"${out%.md}.err" \
    || { echo "  ✗ falhou ($model) — ver ${out%.md}.err" >&2; return 1; }
}

log() { printf '\033[1;36m%s\033[0m\n' "$*"; }

log "═══ Arch Panel ═══  Opus=$OPUS_MODEL  Composer=$COMPOSER_MODEL"
log "Objetivo: ${OBJETIVO:0:80}..."
log "Saída em: $OUTDIR"
echo

# ── ETAPA 1: Opus escreve o plano v0 ───────────────────────────────────────
log "[1/3] Opus escrevendo o plano v0 (analisando o projeto)…"
run "$OPUS_MODEL" "$OUTDIR/v0.md" \
"Você é um arquiteto de software sênior. Analise o projeto neste diretório (estrutura, stack, dependências, decisões existentes) e produza um PLANO DE ARQUITETURA para o objetivo abaixo.

OBJETIVO:
$OBJETIVO

Entregue: contexto atual relevante, abordagem recomendada (decidida, não um menu), trade-offs, riscos principais, e um roteiro em fases com critérios de sucesso mensuráveis. Seja específico ao código real que você encontrar. Não edite nenhum arquivo."

V0="$(cat "$OUTDIR/v0.md")"
log "  ✓ v0.md ($(wc -w <"$OUTDIR/v0.md" | tr -d ' ') palavras)"
echo

# ── ETAPA 2: críticos Composer em paralelo ─────────────────────────────────
ROLES=(arquiteto dev-senior seguranca sre advogado)
ROLE_DESC=(
"Arquiteto de software. Foco: limites de domínio (bounded contexts), acoplamento, Conway, se a topologia proposta resolve a dor real ou só move complexidade."
"Desenvolvedor sênior. Foco: viabilidade de implementação, dívida técnica, esforço subestimado, impacto no fluxo de trabalho diário e na DX do time."
"Especialista em Segurança e Compliance. Foco: superfície de ataque, authn/authz, dados sensíveis, multi-tenancy, requisitos SOC2/LGPD/GDPR, auditoria."
"Engenheiro de SRE/Operações. Foco: observabilidade, modos de falha, rollback, custo operacional, on-call, migração de dados, deploy/ordering."
"Advogado do diabo / pragmatista. Foco: a premissa-raiz se sustenta? Qual a alternativa mais barata e reversível? O que vai estourar prazo? Onde isso é over-engineering?"
)

log "[2/3] ${#ROLES[@]} críticos Composer atacando o v0 (paralelo)…"
pids=()
for i in "${!ROLES[@]}"; do
  role="${ROLES[$i]}"; desc="${ROLE_DESC[$i]}"
  run "$COMPOSER_MODEL" "$OUTDIR/critique-$role.md" \
"Você é um crítico especializado. PAPEL: $desc

Critique o plano de arquitetura abaixo SOMENTE pela sua especialidade. Seja rigoroso e específico: aponte (1) a falha ou risco mais grave que o plano ignora, (2) qualquer premissa que não se sustenta, (3) UMA melhoria concreta, (4) severidade (baixa/média/alta). Máx 250 palavras. Não edite arquivos.

=== PLANO v0 ===
$V0" &
  pids+=($!)
done
fail=0
for p in "${pids[@]}"; do wait "$p" || fail=1; done
for i in "${!ROLES[@]}"; do
  role="${ROLES[$i]}"
  [[ -s "$OUTDIR/critique-$role.md" ]] && log "  ✓ critique-$role.md" || log "  ✗ critique-$role.md vazio"
done
[[ $fail -eq 0 ]] || log "  ⚠ algum crítico falhou — síntese seguirá com o que existe"
echo

# ── ETAPA 3: Opus sintetiza o plano final ──────────────────────────────────
CRITIQUES=""
for i in "${!ROLES[@]}"; do
  role="${ROLES[$i]}"
  if [[ -s "$OUTDIR/critique-$role.md" ]]; then
    CRITIQUES+="

### Crítica — $role
$(cat "$OUTDIR/critique-$role.md")"
  fi
done

log "[3/3] Opus sintetizando o plano FINAL sobre as críticas…"
run "$OPUS_MODEL" "$OUTDIR/final.md" \
"Você é o arquiteto sênior que escreveu o plano v0 abaixo. ${#ROLES[@]} especialistas o criticaram. Revise para um PLANO FINAL.

Para cada crítica: incorpore, refute (com motivo) ou registre como risco aceito. Comece com um bloco 'O QUE MUDOU vs. v0' listando as mudanças concretas. Mantenha a decisão final clara e mensurável. Não edite arquivos.

=== PLANO v0 ===
$V0

=== CRÍTICAS DOS ESPECIALISTAS ===
$CRITIQUES"

log "  ✓ final.md ($(wc -w <"$OUTDIR/final.md" | tr -d ' ') palavras)"
echo
log "═══ Concluído ═══"
echo "  Plano v0:     $OUTDIR/v0.md"
echo "  Críticas:     $OUTDIR/critique-*.md"
echo "  Plano FINAL:  $OUTDIR/final.md"