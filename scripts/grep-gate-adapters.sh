#!/usr/bin/env bash
# Grep gate: SshClientInterface / AgentUpstreamGateway must stay inside Integration adapters.
# Residual violations (ROADMAP N32) emit WARN; any other hit fails CI.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PATTERN='SshClientInterface|AgentUpstreamGateway'

# Allowed directories (prefix match under repo root)
ALLOW_PREFIXES=(
  'app/Modules/Integration/Adapters/'
  'tests/'
)

# Explicit allowlist: DI bindings, contracts, implementations
ALLOW_FILES=(
  'app/Providers/AppServiceProvider.php'
  'app/Modules/Core/Ssh/SshClientInterface.php'
  'app/Modules/Core/Ssh/SshClient.php'
  'app/Modules/Agents/Services/AgentUpstreamGateway.php'
)

# Residual direct-transport usage — WARN only (ROADMAP N32 residual grep gate)
# ProvisionCustomerAction: createTenant via port; branding SFTP (inboxInit/sftpUpload) still direct until N33
WARN_RESIDUAL=(
  'app/Modules/Customers/Actions/RemoveCustomerAction.php'
  'app/Modules/ClusterServers/Actions/SyncWebhookSecretAction.php'
  'app/Modules/Agents/Services/AgentEventHandler.php'
  'app/Modules/Customers/Actions/ProvisionCustomerAction.php'
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

is_warn_residual() {
  local file="$1"
  local entry

  for entry in "${WARN_RESIDUAL[@]}"; do
    if [[ "$file" == "$entry" ]]; then
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
WARN_FILES=()

for file in "${HITS[@]}"; do
  if is_allowed "$file"; then
    continue
  fi

  if is_warn_residual "$file"; then
    WARN_FILES+=("$file")
  else
    FAIL_FILES+=("$file")
  fi
done

echo '=== grep-gate-adapters ==='
echo "Pattern: ${PATTERN}"
echo
echo 'Allowlist (prefixes):'
printf '  - %s\n' "${ALLOW_PREFIXES[@]}"
echo 'Allowlist (files):'
printf '  - %s\n' "${ALLOW_FILES[@]}"
echo

if ((${#WARN_FILES[@]} > 0)); then
  echo "WARN — residual direct transport (${#WARN_FILES[@]} file(s)):"
  printf '  - %s\n' "${WARN_FILES[@]}"
  echo
fi

if ((${#FAIL_FILES[@]} > 0)); then
  echo "FAIL — unexpected usage outside Integration/Adapters (${#FAIL_FILES[@]} file(s)):"
  printf '  - %s\n' "${FAIL_FILES[@]}"
  echo
  echo 'Migrate to PlatformPort adapters or extend the documented allowlist.'
  exit 1
fi

if ((${#WARN_FILES[@]} > 0)); then
  echo 'Gate passed with residual warnings (tracked for N33 / fast-track).'
else
  echo 'Gate passed — no violations.'
fi

exit 0
