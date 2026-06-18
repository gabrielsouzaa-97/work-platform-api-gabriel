#!/usr/bin/env bash
# Grep gate: SshClientInterface / AgentUpstreamGateway must stay inside Integration adapters.
# Any hit outside documented allowlist fails CI (strict — N33.8).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PATTERN='SshClientInterface|AgentUpstreamGateway'

ALLOW_PREFIXES=(
  'app/Modules/Integration/Adapters/'
  'tests/'
)

ALLOW_FILES=(
  'app/Providers/AppServiceProvider.php'
  'app/Modules/Core/Ssh/SshClientInterface.php'
  'app/Modules/Core/Ssh/SshClient.php'
  'app/Modules/Agents/Services/AgentUpstreamGateway.php'
)

is_allowed() {
  local file="$1"
  local entry

  for entry in "${ALLOW_FILES[@]}"; do
    if [[ "$file" == "$entry" ]]; then
      return 0
    fi
  done

  for entry in "${ALLOW_PREFIXES[@]}"; do
    if [[ "$file" == "$entry"* ]]; then
      return 0
    fi
  done

  return 1
}

mapfile -t HITS < <(
  rg -l --glob '*.php' "$PATTERN" app tests 2>/dev/null \
    | sort -u
)

FAIL_FILES=()

for file in "${HITS[@]}"; do
  if is_allowed "$file"; then
    continue
  fi

  FAIL_FILES+=("$file")
done

echo '=== grep-gate-adapters (strict) ==='
echo "Pattern: ${PATTERN}"
echo
echo 'Allowlist (prefixes):'
printf '  - %s\n' "${ALLOW_PREFIXES[@]}"
echo 'Allowlist (files):'
printf '  - %s\n' "${ALLOW_FILES[@]}"
echo

if ((${#FAIL_FILES[@]} > 0)); then
  echo "FAIL — unexpected usage outside Integration/Adapters (${#FAIL_FILES[@]} file(s)):"
  printf '  - %s\n' "${FAIL_FILES[@]}"
  echo
  echo 'Migrate to PlatformPort adapters or extend the documented allowlist.'
  exit 1
fi

echo 'Gate passed — no violations.'
exit 0
