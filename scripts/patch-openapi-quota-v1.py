#!/usr/bin/env python3
"""Insert N33.6 quota v1 path into openapi-external.yaml (idempotent)."""
from __future__ import annotations

from pathlib import Path

MARKER = "  /tenants/{slug}/users/{username}/quota:"
BLOCK = """  /tenants/{slug}/users/{username}/quota:
    parameters:
      - $ref: "#/components/parameters/TenantSlug"
      - name: username
        in: path
        required: true
        schema:
          type: string
    put:
      tags: [Users]
      summary: Definir quota de usuário
      description: |
        Aplica quota de arquivos via OCC `user:setting` através do **PlatformPort**
        (passthrough). Se o upstream bloquear o subcmd (allowlist D-02), retorna
        **404** `capability_not_available` com envelope DomainError (sem `subcmd`/`exit_code`).

        **Scope:** `users:write`.
      operationId: setUserQuota
      x-required-scope: users:write
      x-internal-mapping: OccPassthroughService (user:setting files quota)
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [quota]
              properties:
                quota:
                  type: string
                  example: 5GB
      responses:
        "200":
          description: Quota aplicada (resposta sync do upstream)
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/Envelope"
        "401":
          $ref: "#/components/responses/Unauthenticated"
        "403":
          $ref: "#/components/responses/ForbiddenScope"
        "404":
          description: Tenant não encontrado ou capability indisponível
          content:
            application/json:
              schema:
                $ref: "#/components/schemas/DomainError"
        "422":
          $ref: "#/components/responses/ValidationFailed"
        "429":
          $ref: "#/components/responses/RateLimited"
        "502":
          $ref: "#/components/responses/UpstreamUnavailable"
        "503":
          $ref: "#/components/responses/ClusterUnreachable"

"""

ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / "docs" / "openapi-external.yaml"


def main() -> None:
    text = TARGET.read_text(encoding="utf-8")
    if MARKER in text:
        print("openapi quota path already present — skip")
        return

    anchor = "  /jobs/{id}:"
    if anchor not in text:
        raise SystemExit(f"anchor not found: {anchor}")

    text = text.replace(anchor, BLOCK + anchor, 1)
    TARGET.write_text(text, encoding="utf-8")
    print("openapi quota path inserted")


if __name__ == "__main__":
    main()
