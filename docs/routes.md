# Routen — DISPATCH

Alle Endpunkte hängen am einzigen PHP-Einstieg `/dispatch/` (Brücke → `app/index.php`, Router `Http\Router`). Platzhalter: `{id}` = Ziffern (kommt als int), `{slug}` = `[a-z0-9-]+`. Bekannter Pfad mit anderer Methode → 405 mit `Allow`, unbekannter Pfad → 404. Fehler immer `{"error","message"}`.

Auth: **JWT** = Access-Token des Inhabers (`Auth\ApiAuth::owner`), **JWT/Deploy** = zusätzlich das Deploy-Token (`ApiAuth::ownerOrDeploy`), **Deploy** = nur Deploy-Token, **Cookie** = Refresh-Cookie plus `X-Dispatch: 1`. Verbindlicher Kontrakt: `docs/planning/…meilenstein-4-redaktion/README.md`, Anmeldung: [ADR-020](decisions/020-anmeldung-des-inhabers.md).

| Methode | Pfad | Auth | Controller | Phase |
|---|---|---|---|---|
| POST | `/dispatch/mcp` | `dmcp_`- oder `doa_`-Token | `Mcp\McpController` (401 mit `WWW-Authenticate: Bearer [error="invalid_token", ]resource_metadata=…, scope="blog:read blog:write"` über `OAuth\Discovery::challengeHeader`) | M3, M5 1, M5 3 (`doa_` über `OAuthTokenIssuer::authenticate`, Akteur `oauth:<Client>`) |
| GET | `/.well-known/oauth-protected-resource[/dispatch/mcp]` | — | `OAuth\DiscoveryController::protectedResource` (RFC 9728, [ADR-024](decisions/024-oauth-fuer-claude-apps.md); `Cache-Control: public, max-age=300`) | M5 1 |
| GET | `/.well-known/oauth-authorization-server` | — | `OAuth\DiscoveryController::authorizationServer` (RFC 8414, CIMD-Hinweis, kein `registration_endpoint`) | M5 1 |
| GET | `/dispatch/oauth/authorize` | — | `OAuth\AuthorizeController::handle` (prüft Client über `ClientRegistry` und Rückadresse, legt Anfrage in `oauth_codes` an, 302 auf `/anmelden/zustimmung/?anfrage=…`; Fehler von Client/Rückadresse → `?fehler=…`, alle übrigen an die Rückadresse mit `error`, `state`, `iss`) | M5 2 |
| POST | `/dispatch/oauth/token` | Client | `OAuthTokenController::token` (`application/x-www-form-urlencoded`; `authorization_code` mit PKCE, `refresh_token` mit Rotation und Kulanzfenster; Fehler `{"error","error_description"}`, `Cache-Control: no-store`; Rückfall-Client mit `client_secret`) | M5 3 |
| POST | `/dispatch/oauth/revoke` | Client | `OAuthTokenController::revoke` (RFC 7009; widerruft die ganze Familie, immer 200) | M5 3 |
| GET | `/dispatch/api/oauth/requests/{slug}` | JWT | `ApiOAuthController::request` (Anfrage für die Zustimmungsseite; unbekannt 404, beantwortet/abgelaufen 410 `request_expired`) | M5 3 |
| POST | `/dispatch/api/oauth/consent` | JWT | `ApiOAuthController::consent` (`{request_id, decision: allow|deny, scopes}` → `{redirect}`; Audit `oauth.consent`/`oauth.deny`) | M5 3 |
| GET | `/dispatch/api/oauth/grants` | JWT | `ApiOAuthController::grants` (verbundene Apps je Familie) | M5 3 |
| DELETE | `/dispatch/api/oauth/grants/{slug}` | JWT | `ApiOAuthController::disconnect` (widerruft die Familie, Audit `oauth.disconnect`; 204) | M5 3 |
| POST | `/dispatch/api/oauth-clients` | Deploy | `OAuthClientController::create` (Body `{"name"}` → 201 mit `client_secret` einmalig; Audit `oauth_client.create`) | M5 4 |
| GET | `/dispatch/api/oauth-clients` | Deploy | `OAuthClientController::list` (aktive Rückfall-Clients ohne Geheimnis) | M5 4 |
| DELETE | `/dispatch/api/oauth-clients/{id}` | Deploy | `OAuthClientController::revoke` (Client und alle seine Tokens widerrufen, Audit `oauth_client.revoke`; 204) | M5 4 |
| POST | `/dispatch/api/deploy` | Deploy | `Api\DeployController` | M1 |
| POST | `/dispatch/api/categories` | Deploy | `Api\CategoryController` | M3 |
| POST | `/dispatch/api/tokens` | JWT/Deploy | `Api\TokenController::handle` (Deploy + `action` im Body = Kommandozeilenweg wie in M3; sonst neuer Zugang, 201 mit Klartext und `command`) | M3, 4 |
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
| GET | `/dispatch/api/preview-media?id&exp&sig&f=<uid>/<datei>` | Signatur (eine Stunde, von `PreviewLink::protectMedia` in das Vorschau-HTML geschrieben) | `Api\PreviewController::media` (Bilder unveröffentlichter Arbeitsstände; nur Varianten, die der Artikel nutzt) | 10 |
| POST | `/dispatch/api/posts/{id}/approve` | JWT | `Api\PostController::approve` (+ Neubau) | 2 |
| POST | `/dispatch/api/posts/{id}/return` | JWT | `Api\PostController::return` | 2 |
| POST | `/dispatch/api/posts/{id}/unpublish` | JWT | `Api\PostController::unpublish` (+ Neubau) | 2 |
| DELETE | `/dispatch/api/posts/{id}` | JWT | `Api\PostController::discard` | 2 |
| GET | `/dispatch/api/changes` | JWT | `Api\ChangeController::list` | 2 |
| POST | `/dispatch/api/changes/{id}/apply` | JWT | `Api\ChangeController::apply` (+ Neubau) | 2 |
| POST | `/dispatch/api/changes/{id}/reject` | JWT | `Api\ChangeController::reject` | 2 |

| POST | `/dispatch/api/queue` | JWT | `Api\QueueController::add` (multipart, `files[]`; abgelehnte Dateien in `rejected`; zu große Anfrage → 413) | 4 |
| GET | `/dispatch/api/tokens` | JWT | `Api\TokenController::list` (nicht widerrufene `mcp`-Zugänge) | 4 |
| DELETE | `/dispatch/api/tokens/{id}` | JWT/Deploy | `Api\TokenController::revokeById` | 4 |
| GET | `/dispatch/api/categories` | JWT | `Api\CategoryController::list` (mit `signals`, `suggestion`) | 4 |
| PATCH | `/dispatch/api/categories/{id}` | JWT | `Api\CategoryController::patch` (nur `interest`, `keywords`, `avoid`; sonst 400 `invalid_field`) | 4 |
| POST | `/dispatch/api/posts/{id}/reaction` | JWT | `Api\ReactionController::set` | 4 |
| GET | `/dispatch/api/reactions` | JWT | `Api\ReactionController::list` | 4 |
| GET | `/dispatch/api/stats?days=14` | JWT | `Api\StatsController::overview` | 4 |
| GET | `/dispatch/api/stats/posts/{id}?days=30` | JWT | `Api\StatsController::post` | 4 |

| POST | `/dispatch/api/hit` | Origin | `Api\HitController::handle` (Zähl-Beacon, [ADR-022](decisions/022-zaehl-aufruf.md); immer 204, verworfene Aufrufe zählen nichts) | 5 |

`GET posts/by-slug/{slug}` liefert jetzt `stats` (30 Tage, wie `stats/posts/{id}`).

Fachliche Fehler der Redaktions-API: 400 `invalid_value`/`invalid_json`, 404 `not_found`, sonst 409 mit dem Fehlercode und `details` ([ADR-021](decisions/021-redaktions-api.md)). Aktionen mit Neubau antworten mit `rebuilt` und `rebuild_error`.

Spätere Phasen tragen ihre Zeilen hier nach.
