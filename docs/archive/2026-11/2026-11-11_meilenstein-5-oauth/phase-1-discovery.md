# Phase 1 — Tabellen, Discovery, 401-Hinweis, ADR

## Kontext

- `README.md` → „Kontrakt“: Konfiguration, Tabellen, Discovery.
- `docs/decisions/017-mcp-tokens-und-scopes.md`, `020-anmeldung-des-inhabers.md`, `001-pfade-auf-gemeinsamer-domain.md` (Discovery an der Wurzel).
- Code: `app/index.php` (Routen), `app/src/Http/Router.php`, `app/src/Http/Response.php`, `app/src/Mcp/McpController.php` (Block „`$auth === null`“ mit dem 401), `app/config.php`, `app/src/Db/Migrator.php`, `app/migrations/010_token.sql` (Stil der Migrationen).
- Routing ist schon da: `web-root/.htaccess` schreibt `^\.well-known/oauth-(protected-resource|authorization-server)(/.*)?$` auf die Brücke um, `dev-router.php` leitet `/.well-known/oauth-` weiter. `Request::path()` liefert den Originalpfad aus `REQUEST_URI`.
- Fehlerklassen geprüft: keine einschlägig (Vault hat für PHP, Strato und MySQL keinen Abschnitt „Fehlerklassen“).

## AK der Phase

1. `php bin/dispatch migrate` legt `oauth_clients` und `oauth_codes` exakt nach Kontrakt an; ein zweiter Lauf ändert nichts.
2. `config.php` trägt den Block `oauth` nach Kontrakt.
3. `curl -s http://localhost:8080/.well-known/oauth-protected-resource/dispatch/mcp` und `…/oauth-protected-resource` liefern dasselbe JSON nach Kontrakt mit `resource` = `http://localhost:8080/dispatch/mcp`; `…/oauth-authorization-server` liefert das AS-Dokument mit allen Feldern aus dem Kontrakt; alle mit `Cache-Control: public, max-age=300`. Andere Methode → 405.
4. `curl -si -X POST http://localhost:8080/dispatch/mcp` ohne `Authorization` → 401 mit `WWW-Authenticate: Bearer resource_metadata="http://localhost:8080/.well-known/oauth-protected-resource/dispatch/mcp", scope="blog:read blog:write"`; mit einem falschen Token zusätzlich `error="invalid_token"` als erstem Parameter. Ein gültiges `dmcp_`-Token arbeitet unverändert.
5. ADR `docs/decisions/024-oauth-fuer-claude-apps.md` (Kontext / Optionen / Entscheidung / Konsequenzen) hält fest: CIMD statt DCR mit Allowlist `claude.ai`, undurchsichtige Tokens `doa_`/`dor_` in `tokens` statt JWT (widerrufbar, „Trennen“ wirkt sofort), Laufzeiten, Rotation mit Kulanz und Zweig-Regel, Zustimmung als statische Seite mit JS-Login, Rückfall-Client nur per Kommandozeile (Entscheidung des Inhabers), Code im Ordner `src/OAuth/`.

## Checkliste

- [x] `app/migrations/014_oauth.sql`
- [x] `config.php`: Block `oauth`
- [x] `app/src/OAuth/Discovery.php` (reine Funktionen, `BASE` aus `$config['base_url']` bzw. dem Schlüssel, den `config.php` für `DISPATCH_BASE_URL` nutzt — vorher nachsehen), `app/src/OAuth/OAuthError.php`
- [x] `app/src/OAuth/DiscoveryController.php`, drei GET-Routen in `app/index.php` (vor der MCP-Route)
- [x] `McpController`: 401 mit `Discovery::challengeHeader($config, $hatteToken ? 'invalid_token' : null)`
- [x] ADR-024; `docs/decisions/README.md` (Index) ergänzen
- [x] Doku: `docs/routes.md` (Discovery-Zeilen), `docs/models.md` (beide Tabellen), `docs/code-map.md` (Zeile `src/OAuth/` konkretisieren, `app/index.php`-Zeile: OAuth-Pfade sind da)

## Report-Back

Status: complete. Migration 014 läuft (zweiter Lauf „Nichts zu tun“); Discovery-JSON, 405 bei POST und 401-Kopf (mit und ohne `invalid_token`) lokal per curl geprüft. Der bisherige `realm="dispatch"` im 401 ist durch den Kontrakt-Kopf ersetzt.
