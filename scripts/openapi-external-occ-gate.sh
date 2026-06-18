#!/usr/bin/env bash
# Fail CI if /occ paths appear in the external OpenAPI contract (ADR Fase 3).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SPEC="${ROOT}/docs/openapi-external.yaml"

if [[ ! -f "$SPEC" ]]; then
  echo "FAIL: missing ${SPEC}"
  exit 1
fi

if rg -n '/occ|/customers/\{[^}]+\}/occ' "$SPEC" 2>/dev/null; then
  echo "FAIL: openapi-external.yaml must not document /occ/* routes"
  exit 1
fi

echo "openapi-external-occ-gate: OK — no /occ paths in external spec"
