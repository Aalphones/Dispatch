# Routen — DISPATCH

Alle Endpunkte hängen am einzigen PHP-Einstieg `/dispatch/` (Brücke → `app/index.php`, Router `Http\Router`). Platzhalter: `{id}` = Ziffern (kommt als int), `{slug}` = `[a-z0-9-]+`. Bekannter Pfad mit anderer Methode → 405 mit `Allow`, unbekannter Pfad → 404. Fehler immer `{"error","message"}`.

Auth: **JWT** = Access-Token des Inhabers (`Auth\ApiAuth::owner`), **JWT/Deploy** = zusätzlich das Deploy-Token (`ApiAuth::ownerOrDeploy`), **Deploy** = nur Deploy-Token, **Cookie** = Refresh-Cookie plus `X-Dispatch: 1`. Verbindlicher Kontrakt: `docs/planning/…meilenstein-4-redaktion/README.md`, Anmeldung: [ADR-020](decisions/020-anmeldung-des-inhabers.md).

| Methode | Pfad | Auth | Controller | Phase |
|---|---|---|---|---|
| POST | `/dispatch/mcp` | `dmcp_`-Token | `Mcp\McpController` | M3 |
| POST | `/dispatch/api/deploy` | Deploy | `Api\DeployController` | M1 |
| POST | `/dispatch/api/categories` | Deploy | `Api\CategoryController` | M3 |
| POST | `/dispatch/api/tokens` | Deploy | `Api\TokenController` | M3 |
| POST | `/dispatch/api/auth/login` | — (Login-Bremse) | `Api\AuthController::login` | 1 |
| POST | `/dispatch/api/auth/refresh` | Cookie + `Origin` | `Api\AuthController::refresh` | 1 |
| POST | `/dispatch/api/auth/logout` | Cookie | `Api\AuthController::logout` | 1 |
| POST | `/dispatch/api/auth/logout-all` | JWT | `Api\AuthController::logoutAll` | 1 |

Spätere Phasen tragen ihre Zeilen hier nach.
