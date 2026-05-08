#!/usr/bin/env bash
# Safety Guard Hook (mework360-deployer)
# Bloqueia comandos destrutivos antes da execução do shell.
# Triggered by: beforeShellExecution
# Inputs (stdin JSON): { "command": "<cmd>", ... }
# Output (stdout JSON): { "permission": "allow"|"deny", "user_message": "..." }

set -euo pipefail

emit_deny() {
  local msg="$1"
  if command -v jq >/dev/null 2>&1; then
    jq -nc --arg m "$msg" '{permission:"deny", user_message:$m}'
  else
    printf '{"permission":"deny","user_message":%s}\n' "\"${msg//\"/\\\"}\""
  fi
  exit 0
}

emit_allow() {
  echo '{"permission":"allow"}'
  exit 0
}

PAYLOAD="$(cat || true)"
COMMAND=""

if command -v jq >/dev/null 2>&1; then
  COMMAND="$(printf '%s' "$PAYLOAD" | jq -r '.command // empty' 2>/dev/null || true)"
else
  COMMAND="$PAYLOAD"
fi

if [ -z "$COMMAND" ]; then
  emit_allow
fi

LOWER="$(printf '%s' "$COMMAND" | tr '[:upper:]' '[:lower:]')"

case "$LOWER" in
  *"rm -rf /"*|*"rm -rf /*"*|*"rm -rf ~"*|*"rm -rf \$home"*)
    emit_deny "Bloqueado: rm -rf de raiz/home detectado." ;;
  *"git push --force"*|*"git push -f"*|*"git push origin main --force"*)
    emit_deny "Bloqueado: force push. Faça revert/PR ao invés disso." ;;
  *"drop database"*|*"drop table"*)
    emit_deny "Bloqueado: DROP DATABASE/TABLE manual. Use migration." ;;
  *"truncate "*)
    emit_deny "Bloqueado: TRUNCATE manual. Use migration ou seeder controlado." ;;
  *"mkfs"*|*"> /dev/sd"*|*">/dev/sd"*|*"dd if="*" of=/dev/"*)
    emit_deny "Bloqueado: comando que pode destruir disco." ;;
  *"migrate:fresh --force"*|*"migrate:fresh -f"*|*"migrate:fresh --no-interaction"*)
    emit_deny "Bloqueado: migrate:fresh forçado. Use migrate:rollback + migrate em dev." ;;
  *"db:wipe"*)
    emit_deny "Bloqueado: db:wipe destrói o schema. Confirme manualmente." ;;
  *"docker compose down -v"*|*"docker-compose down -v"*)
    emit_deny "Bloqueado: down -v remove volumes (dados do BD). Use docker compose down (sem -v)." ;;
  *"docker volume rm"*|*"docker volume prune"*)
    emit_deny "Bloqueado: remoção de volumes Docker (dados podem estar nos volumes db_data/redis_data)." ;;
  *":(){ :|:& };:"*|*":(){"*)
    emit_deny "Bloqueado: padrão de fork bomb." ;;
esac

emit_allow
