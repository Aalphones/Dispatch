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

| GET | `/dispatch/api/overview` | JWT | `Api\OverviewController::show` | 2 |
| GET | `/dispatch/api/posts?status=review,draft` | JWT | `Api\PostController::list` | 2 |
| GET | `/dispatch/api/posts/by-slug/{slug}` | JWT | `Api\PostController::bySlug` | 2 |
| GET | `/dispatch/api/posts/{id}/preview` | JWT | `Api\PreviewController::byLogin` | 2 |
| GET | `/dispatch/api/posts/{id}/diff` | JWT | `Api\PostController::diff` (`Util\Diff`) | 3 |
| GET | `/dispatch/api/preview?id&exp&sig` | Signatur | `Api\PreviewController::bySignature` | 2 |
| POST | `/dispatch/api/posts/{id}/approve` | JWT | `Api\PostController::approve` (+ Neubau) | 2 |
| POST | `/dispatch/api/posts/{id}/return` | JWT | `Api\PostController::return` | 2 |
| POST | `/dispatch/api/posts/{id}/unpublish` | JWT | `Api\PostController::unpublish` (+ Neubau) | 2 |
| DELETE | `/dispatch/api/posts/{id}` | JWT | `Api\PostController::discard` | 2 |
| GET | `/dispatch/api/changes` | JWT | `Api\ChangeController::list` | 2 |
| POST | `/dispatch/api/changes/{id}/apply` | JWT | `Api\ChangeController::apply` (+ Neubau) | 2 |
| POST | `/dispatch/api/changes/{id}/reject` | JWT | `Api\ChangeController::reject` | 2 |

Fachliche Fehler der Redaktions-API: 400 `invalid_value`/`invalid_json`, 404 `not_found`, sonst 409 mit dem Fehlercode und `details` ([ADR-021](decisions/021-redaktions-api.md)). Aktionen mit Neubau antworten mit `rebuilt` und `rebuild_error`.

Spätere Phasen tragen ihre Zeilen hier nach.
