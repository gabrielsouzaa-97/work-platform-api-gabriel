# work-platform-api — ambiente suite

Homologação canônica: **`https://lab.mework360.com.br`** (`lab-app` no servidor remoto).

Política completa: [NC-SUITE-POLICY.md](../work-platform-scripts/docs/NC-SUITE-POLICY.md)

- Sem lab Nextcloud local (`nc-suite-lab`, `docker exec lab-app` na máquina dev).
- Deploy de apps NC: repositórios `work-nc-*-fork` + `deploy/apply-lab.sh`.

## Homologação NC Suite (labwork)

| Item | Valor |
|------|-------|
| Host | `128.201.61.112` |
| URL base | `https://labwork.mework360.com.br` |
| Tenants | `<tenant>.labwork.mework360.com.br` |
| Papel | Homologação canônica (LAB) |

## Produção piloto image-mode (image-pilot)

Ambiente de **produção em piloto** — cluster greenfield NC Suite image-mode (Nextcloud **33.0.5-mw5**). Provisionamento upstream: `create --image-mode --suite-catalog`.

| Item | Valor |
|------|-------|
| Host | `128.201.61.120` |
| URL base | `https://cloud.image-pilot.mework360.com.br` |
| Tenants (piloto) | `<tenant>.image-pilot.mework360.com.br` |
| Papel | Produção (piloto image-mode) |

**Padrão pós-cutover (fase futura, ainda não em vigor):** tenants em `<tenant>.mework360.com.br` (ex.: `acme.mework360.com.br`). O domínio `*.image-pilot.mework360.com.br` permanece válido até o cutover de DNS/domínio final.
