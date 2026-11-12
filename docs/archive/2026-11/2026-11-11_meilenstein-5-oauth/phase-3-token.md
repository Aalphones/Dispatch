# Phase 3 — Zustimmungs-API, Token-Endpunkt, Widerruf, MCP-Anmeldung

## Kontext

- `README.md` → „Kontrakt“: Tokens und Codes, Scopes, Endpunkte, „Refresh mit Rotation“, Akteur und Audit, Getrennte Welten.
- ADR-024, ADR-020 (Rotation des Inhaber-Refresh als Vorbild), ADR-021 (Fehlerformat der Redaktions-API).
- Code: `app/src/Auth/OwnerSession.php` (`refresh`: Rotation mit Kulanz, Familienwiderruf — Muster), `app/src/Domain/TokenRepository.php` (`findByHash`, `revokeOne`, `revokeFamily`, `touch`), `app/src/Auth/McpAuth.php`, `app/src/Auth/ApiAuth.php` (`owner`), `app/src/Mcp/Registry.php` (Zeile mit `Actor::mcp($auth['name'])`), `app/src/Mcp/McpController.php`, `app/src/Api/TokenController.php` (Muster eines JWT-Controllers), `app/src/Api/ApiErrors.php`, `app/src/Http/Request.php` (`form()` liest `$_POST`, also auch `x-www-form-urlencoded`).
- Phase 2: `ClientRegistry`, `OAuthRepository`, `OAuth\Redirect::with`.
- Fehlerklassen geprüft: keine einschlägig.

## AK der Phase

1. **`GET /dispatch/api/oauth/requests/{slug}`** (JWT) nach Kontrakt: `client` aus `ClientRegistry` (aus der gespeicherten Zeile, kein neuer Abruf), `redirect_host` = Host plus `:Port`, falls gesetzt, `scopes` = angefragte `blog:*`, `offline` = `offline_access` angefragt, `expires_in` = Sekunden bis `expires_at`.
2. **`POST /dispatch/api/oauth/consent`** (JWT) über `Consent::decide`: `allow` → gewählte Scopes ⊆ angefragte `blog:*`, mindestens einer; Code `bin2hex(random_bytes(32))`, `code_hash`, `status = approved`, `scope` = gewählte Scopes (+ `offline_access`, falls angefragt), `family` = neue 32 Hex, `expires_at` = jetzt + `code_seconds`; `redirect` = Rückadresse mit `code`, `state`, `iss`. `deny` → `status = denied`, `redirect` mit `error=access_denied`, `state`, `iss`. Audit `oauth.consent`/`oauth.deny` mit Akteur `owner`. Alles in einer Transaktion; nur aus `pending` heraus (zweites Absenden → 410).
3. **`POST /dispatch/oauth/token`**, `grant_type=authorization_code`: Pflicht `code`, `redirect_uri` (exakt die gespeicherte), `client_id` (gleich der gespeicherten), `code_verifier` (`rtrim(strtr(base64_encode(hash('sha256', $v, true)), '+/', '-_'), '=')` muss mit `hash_equals` der `challenge` entsprechen); `resource`, falls gesetzt, = RESOURCE. Client `manual` → `client_secret` (Formularfeld) per `hash_equals(secret_hash, hash('sha256', …))`, sonst 401 `invalid_client`. Code nicht `approved` oder abgelaufen → `invalid_grant`; Code mit `status = used` → zusätzlich alle Tokens seiner `family` widerrufen. Erfolg: `status = used`, Access-Token (+ Refresh-Token, falls `offline_access`) in der Familie, Antwort `{"access_token","token_type":"Bearer","expires_in":3600,"scope":"…"[,"refresh_token"]}`, `Cache-Control: no-store`, Audit `oauth.token`, `oauth_clients.last_used_at`.
4. **`grant_type=refresh_token`**: `refresh_token`, `client_id` (gleich dem des Tokens; `manual` mit Geheimnis), optional `scope` ⊆ bisherige (sonst `invalid_scope`); Rotation exakt nach Kontrakt. Fehler `{"error","error_description"}` mit 400 (`invalid_request`, `invalid_grant`, `invalid_scope`, `unsupported_grant_type`) bzw. 401 (`invalid_client`). Falscher `Content-Type` (nicht form-urlencoded) → 400 `invalid_request`.
5. **`POST /dispatch/oauth/revoke`** (RFC 7009): Formular `token`, optional `token_type_hint`, `client_id`; gehört das Token zum Client → ganze Familie widerrufen, Audit `oauth.revoke`; immer 200 mit leerem Body.
6. **MCP-Anmeldung:** `McpAuth::authenticate` nimmt zusätzlich `doa_` (68 Zeichen) über `TokenIssuer::authenticate` (kind `oauth_access`, nicht widerrufen, nicht abgelaufen, Client nicht widerrufen; `touch`). Rückgabe erweitert um `'kind' => 'mcp'|'oauth'`; `Registry` baut `Actor::oauth($auth['name'])` bei `oauth`, sonst wie bisher. Abgelaufenes Access-Token → 401 mit `error="invalid_token"` (Phase-1-Kopf), damit Claude erneuert.
7. **Verbundene Apps:** `GET /dispatch/api/oauth/grants` listet je Familie mit mindestens einem nicht widerrufenen, nicht abgelaufenen Token (Access oder Refresh): `client_name` (`tokens.name`), `client_host`, `scopes` (des jüngsten Tokens), `connected_at` = frühestes `created_at` der Familie, `last_used_at` = spätestes der Familie. `DELETE …/grants/{slug}` widerruft die Familie, Audit `oauth.disconnect`.
8. **Getrennte Welten:** `/dispatch/api/overview` mit `Authorization: Bearer doa_…` → 401 (Gegenprobe). `GET /dispatch/api/tokens` listet weiter nur `kind = mcp`.
9. **Probe mit `curl`** (lokal, Ablauf in den Report-Back): authorize (Phase 2) → Zustimmung per API mit JWT → Code-Tausch mit `code_verifier` → `tools/list` an `/dispatch/mcp` mit dem Access-Token → Refresh → altes Refresh-Token nach 31 s erneut → `invalid_grant` und die Familie ist widerrufen (MCP-Aufruf mit dem jüngsten Access-Token → 401).

## Checkliste

- [x] `TokenRepository`: `insertOAuth(kind, hash, name, clientId, scope, family, lifetime)`, `findOAuthByHash`, `listGrants`, `revokeFamilyNow`, `revokeByClientId` (bestehende Methoden wiederverwenden, wo sie passen)
- [x] `app/src/OAuth/Consent.php`, `app/src/OAuth/TokenIssuer.php`, `app/src/OAuth/TokenController.php`
- [x] `app/src/Api/OAuthController.php` (`request`, `consent`, `grants`, `disconnect`)
- [x] `McpAuth`, `Registry` (Akteur)
- [x] Routen in `app/index.php`
- [x] Doku: `docs/routes.md`, `docs/code-map.md`, `docs/glossary.md` (Begriffe „Verbundene App“, „Zustimmung“, „Rückfall-Client“, „Familie“)

## Report-Back

Phase 3 fertig, lokal (PHP 8.5, MySQL, Rückfall-Client) per Node-Skript gegen `localhost:8080` geprüft; keine Befunde offen.

- Zustimmung: `requests/{slug}` liefert Client, Host, `redirect_host`, Scopes, `expires_in`; `consent` allow/deny → `redirect` mit `code`/`state`/`iss` bzw. `access_denied`; zweites Absenden und Beschreiben danach → 410; fremder oder fehlender Scope → 400.
- Token-Endpunkt: falsches Geheimnis → 401 `invalid_client`; falscher Verifier oder falsche `redirect_uri` → `invalid_grant`; falsche `resource` → `invalid_target`; JSON statt Formular → `invalid_request`; Tausch → Access (68 Zeichen, `doa_`) plus Refresh, `Cache-Control: no-store`. Code-Replay → `invalid_grant` und Familie widerrufen.
- Refresh: Rotation, Scope-Erweiterung → `invalid_scope`; altes Token im Kulanzfenster → neues Paar; nach 31 s → `invalid_grant`, Familie tot (MCP mit jüngstem Access → 401).
- MCP: `doa_` am `/dispatch/mcp` (30 Tools bei read+write, 17 bei nur read), Akteur `oauth:<Client>`; `/dispatch/api/overview` und `/dispatch/api/tokens` mit `doa_` → 401.
- Widerruf: fremde `client_id` → 200, nichts passiert; eigener → Familie tot; `DELETE grants/{slug}` → 204, danach 401 am MCP; unbekannt → 404; `grants` listet nur lebende Familien.
- Entscheidung: `invalid_client` wird ohne `WWW-Authenticate` gesendet (kein Basic). Im Kulanz-Zweig wird das zuvor rotierte Refresh-Token sofort widerrufen; ein Client, der danach genau dieses Token benutzt, löst die Wiederverwendungs-Erkennung aus (Familie tot) — folgt dem Kontrakt.
- Nicht getestet: CIMD-Client im Token-Weg (Client kommt aus der gespeicherten Zeile wie beim Rückfall-Client, Phase 2 prüfte den Abruf), Strato.
