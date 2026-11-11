# Phase 2 — Clients (CIMD, Rückfall-Client) und Autorisierung

## Kontext

- `README.md` → „Kontrakt“: Konfiguration, Tokens und Codes, Scopes, „Weiterleitungen aus `/authorize`“, „Rückadressen“.
- ADR-024 (Phase 1), `docs/decisions/011-medien-http-client-ssrf-bildvarianten.md` (einziger Weg nach außen).
- Code: `app/src/Util/HttpClient.php` (`get($url, $maxBytes)`, wirft `DomainError` `fetch_failed`/`too_many_redirects`/abgelehnte Adresse; Konfigurationsschlüssel `connect_timeout`, `timeout`, `max_redirects`, `allow_private`), `app/src/Db/Transaction.php`, `app/src/Domain/AuditRepository.php`, `app/src/Auth/Scopes.php` (`parse`), `app/src/Http/Request.php`, `app/src/Http/Response.php`.
- Claude Code's CIMD (abgerufen 2026-11-11): `https://claude.ai/oauth/claude-code-client-metadata` → `{"client_id":"https://claude.ai/oauth/claude-code-client-metadata","client_name":"Claude Code","client_uri":"https://claude.ai","redirect_uris":["http://localhost/callback","http://127.0.0.1/callback"],"grant_types":["authorization_code","refresh_token"],"response_types":["code"],"token_endpoint_auth_method":"none"}`. Die Claude-App (Web, Android, Desktop) nutzt die Rückadresse `https://claude.ai/api/mcp/auth_callback`; die Adresse ihres CIMD ist nicht dokumentiert, der Host ist `claude.ai` (Allowlist auf Host, nicht auf URL).
- Fehlerklassen geprüft: keine einschlägig.

## AK der Phase

1. **`ClientRegistry::resolve($clientId)`**
   - `client_id` beginnt mit `https://`: Host (Kleinbuchstaben) muss exakt in `oauth.cimd_hosts` stehen, sonst `OAuthError('unbekannte_app')`. Liegt eine Zeile `kind = cimd` mit `fetched_at` jünger als `cimd_cache_seconds` vor → die nehmen. Sonst abrufen mit eigenem `HttpClient(array_merge($config['http'], $config['oauth']['http']))`, höchstens `cimd_max_bytes`; JSON muss ein Objekt sein, `client_id` exakt gleich der URL, `redirect_uris` nicht leere Liste von Strings, `client_name` String (fehlt → Host). Gültig → Zeile anlegen oder aktualisieren (`name`, `redirect_uris`, `metadata` = ganzes Dokument, `fetched_at`). Abruf scheitert und eine ältere Zeile existiert → die ältere nehmen und `Log::warning`; keine Zeile → `OAuthError('app_nicht_erreichbar')`. Ungültiges Dokument → `unbekannte_app`.
   - `client_id` beginnt mit `dcl_`: Zeile `kind = manual`, `revoked = 0`, sonst `unbekannte_app`.
   - Alles andere → `unbekannte_app`.
   - Rückgabe `['client_id','kind','name','host','redirect_uris'=>list,'secret_hash'=>?string]`; `host` = Host der `client_id`-URL, bei `manual` `"von dir angelegt"`.
2. **`ClientRegistry::redirectAllowed($client, $uri)`** nach „Rückadressen“ im Kontrakt (Port nur bei Loopback ignorieren; `localhost`, `127.0.0.1`, `[::1]`; nur `http`).
3. **`ClientRegistry::createManual($name, Actor)`**, `listManual()`, `revokeManual($id, Actor)` nach Kontrakt (Name wie Token-Namen: `^[a-z0-9][a-z0-9-]{0,39}$`, eindeutig unter nicht widerrufenen; `redirect_uris` = `oauth.manual_redirect_uris`; Widerruf setzt `revoked = 1` und widerruft alle `tokens` mit dieser `client_id`); Audit `oauth_client.create|revoke`. Die Routen kommen in Phase 4.
4. **`GET /dispatch/oauth/authorize`** (`AuthorizeController`), Reihenfolge:
   1. Veraltete `oauth_codes`-Zeilen löschen (`expires_at` älter als 1 Tag).
   2. `resolve(client_id)`; `redirect_uri` fehlt oder nicht erlaubt → `falsche_rueckadresse`. Diese Fehler: 302 auf `BASE/anmelden/zustimmung/?fehler=<code>` (bei `unbekannte_app` mit `&host=<Host der client_id>`, falls eine URL vorliegt), Audit nicht nötig, `Log::info` mit `client_id`.
   3. Ab hier Fehler an die Rückadresse (Kontrakt): `response_type` ≠ `code` → `unsupported_response_type`; `code_challenge` fehlt oder passt nicht auf `^[A-Za-z0-9_-]{43,128}$`, oder `code_challenge_method` ≠ `S256` → `invalid_request`; `resource` gesetzt und ≠ RESOURCE → `invalid_target`; Scope-Liste nach Kontrakt, unbekannt → `invalid_scope`.
   4. Zeile `pending` mit `request_id` = `bin2hex(random_bytes(16))`, `expires_at` = jetzt + `request_seconds`, `scope` = angefragte Liste inkl. `offline_access`; 302 auf `BASE/anmelden/zustimmung/?anfrage=<request_id>`.
   - Antwortzeit ohne CIMD-Abruf unter 1 s; mit Abruf begrenzt durch `timeout` 4 s.
5. Probe lokal: `curl -si "http://localhost:8080/dispatch/oauth/authorize?response_type=code&client_id=https%3A%2F%2Fclaude.ai%2Foauth%2Fclaude-code-client-metadata&redirect_uri=http%3A%2F%2Flocalhost%3A4711%2Fcallback&code_challenge=<43 Zeichen>&code_challenge_method=S256&state=x&scope=blog%3Aread%20offline_access"` → 302 auf die Zustimmungsseite, Zeile in `oauth_codes`, Client-Zeile in `oauth_clients`. Dieselbe Anfrage mit `client_id=https://evil.example/x` → 302 auf `?fehler=unbekannte_app&host=evil.example`, keine Weiterleitung an `localhost:4711`. Ohne `code_challenge` → 302 auf `http://localhost:4711/callback?error=invalid_request&…&state=x&iss=…`.

## Checkliste

- [ ] `app/src/OAuth/OAuthRepository.php` (Clients: finden, anlegen/aktualisieren, Liste manual, widerrufen, `touch`; Codes: anlegen, nach `request_id`, nach `code_hash`, Status setzen, Aufräumen)
- [ ] `app/src/OAuth/ClientRegistry.php`
- [ ] `app/src/OAuth/AuthorizeController.php`, Route `GET /dispatch/oauth/authorize`
- [ ] Hilfsfunktion „Rückadresse mit Parametern“ (`?` oder `&`, `http_build_query`) an einer Stelle, z. B. `OAuth\Redirect::with(string $uri, array $params): string`
- [ ] Doku: `docs/routes.md`, `docs/code-map.md`

## Report-Back
