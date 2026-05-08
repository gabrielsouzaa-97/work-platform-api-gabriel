#!/usr/bin/env bash
# PMO Update Hook (mework360-deployer)
# Atualiza .cursorsession.ultimo_update e apêndice em docs/CHANGELOG.md
# Triggered by: stop (fim de cada turno do agente)

set -euo pipefail

cd "$(dirname "$0")/../.." || exit 0

[ -f ".cursorsession" ] || exit 0
command -v jq >/dev/null 2>&1 || exit 0

TIMESTAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
TMP="$(mktemp)"

if jq --arg ts "$TIMESTAMP" '.ultimo_update = $ts' .cursorsession > "$TMP" 2>/dev/null; then
  mv "$TMP" .cursorsession
else
  rm -f "$TMP"
  exit 0
fi

if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
  LAST_MSG="$(git log --oneline -1 2>/dev/null | cut -d' ' -f2- || true)"
  LAST_HASH="$(git log --format='%h' -1 2>/dev/null || true)"
  if [ -n "$LAST_MSG" ] && [ -n "$LAST_HASH" ]; then
    mkdir -p docs
    if [ ! -f "docs/CHANGELOG.md" ]; then
      printf '# Changelog\n\nApêndice automático mantido pelo hook `pmo-update.sh`.\n\n' > docs/CHANGELOG.md
    fi
    DATE_LOCAL="$(date +"%Y-%m-%d %H:%M")"
    LINE="- **${DATE_LOCAL}** \`${LAST_HASH}\` — ${LAST_MSG}"
    if ! grep -qF -- "$LINE" docs/CHANGELOG.md 2>/dev/null; then
      printf '%s\n' "$LINE" >> docs/CHANGELOG.md
    fi
  fi
fi

exit 0
