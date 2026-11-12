# Meilenstein 5 — OAuth 2.1 für die Claude-Apps

Ziel: Der Inhaber verbindet DISPATCH als Connector in der Claude-App (Android, Web, Desktop) und in Claude Code per Anmeldung statt per festem Token. Claude weist sich über ein Client-Metadatendokument (CIMD) von `claude.ai` aus, der Inhaber erlaubt den Zugriff auf einer Zustimmungsseite des Blogs, Claude bekommt ein Access-Token (1 Stunde) und ein rotierendes Refresh-Token (60 Tage). Verbundene Apps erscheinen im Dialog „Zugänge“ und lassen sich trennen. Notfall-Weg ohne CIMD: ein von Hand angelegter OAuth-Client, nur über die Kommandozeile. Grundlage: `docs/PROJECT.md` → Meilenstein 5; Fachkonzept `docs/design/konzept-blog-engine.md` → „OAuth 2.1 für die Claude-Apps“, „Scopes“; Claude-Doku „Authentication for connectors“ (Stand 2026-11-11, Fakten unten im Kontrakt übernommen); ADR-017, 020, 021, 023.

Nicht in diesem Meilenstein: Registrierung (DCR), `private_key_jwt`, Oberfläche für Rückfall-Clients, TOTP, Probebetrieb mit geplanter Aufgabe (Meilenstein 6).

## Phasen

| # | Phase | Rating | Status |
|---|---|---|---|
| 1 | [Tabellen, Discovery, 401-Hinweis, ADR](phase-1-discovery.md) | standard | complete |
| 2 | [Clients (CIMD, Rückfall-Client) und Autorisierung](phase-2-autorisierung.md) | heikel | complete |
| 3 | [Zustimmungs-API, Token-Endpunkt, Widerruf, MCP-Anmeldung](phase-3-token.md) | heikel | complete |
| 4 | [Kommandozeile für Rückfall-Clients](phase-4-cli.md) | mechanisch | complete |
| 5 | [Zustimmungsseite und „Verbundene Apps“](phase-5-oberflaeche.md) | standard | complete |
| 6 | [Strato, Abnahme mit Claude-App und Claude Code, Doku](phase-6-strato-abschluss.md) | standard | complete |

Reihenfolge ist Pflicht. ADR dieses Meilensteins: **024** OAuth 2.1 für die Claude-Apps (Phase 1, aus diesem Kontrakt). Entwurf der Oberfläche: `artifacts/entwurf-zustimmung.html` (verbindlich für Phase 5, siehe `artifacts/README.md`).

Lokale Umgebung: wie `STATE.md` (MySQL über `mysqld` mit Kurzpfaden, PHP 8.5 aus WinGet, `DISPATCH_ENV_FILE=app/.env.local`, `php -S localhost:8080 -t public dev-router.php`). Claude Code kann lokal gegen `http://localhost:8080/dispatch/mcp` getestet werden; die Claude-App erreicht nur Strato.

## Kontrakt (gilt für alle Phasen)

`BASE` = `DISPATCH_BASE_URL` ohne Schrägstrich am Ende (Strato `https://quantum-canvas.de`, lokal `http://localhost:8080`). `RESOURCE` = `BASE/dispatch/mcp`. Zeiten in der Datenbank UTC wie bisher (`UTC_TIMESTAMP()`).

### Dateien und Namen

| Was | Datei |
|---|---|
| Migration | `app/migrations/014_oauth.sql` (Tabellen `oauth_clients`, `oauth_codes`) |
| Discovery-Dokumente | `app/src/OAuth/Discovery.php` (`protectedResource(array $config): array`, `authorizationServer(array $config): array`, `challengeHeader(array $config, ?string $error): string`) |
| Fehler | `app/src/OAuth/OAuthError.php` (Exception mit `error`, `description`, `status`) |
| Datenbankzugriff | `app/src/OAuth/OAuthRepository.php` (`oauth_clients`, `oauth_codes`); OAuth-Tokens in `app/src/Domain/TokenRepository.php` (gleiche Tabelle `tokens`) |
| Clients | `app/src/OAuth/ClientRegistry.php` (`resolve(string $clientId): array`, `redirectAllowed(array $client, string $uri): bool`, `createManual`, `listManual`, `revokeManual`) |
| Autorisierung | `app/src/OAuth/AuthorizeController.php` (`GET /dispatch/oauth/authorize`) |
| Zustimmung | `app/src/OAuth/Consent.php` (`describe(string $requestId): array`, `decide(string $requestId, bool $allow, array $scopes, Actor $actor): string` → Weiterleitungs-URL) |
| Tokens | `app/src/OAuth/TokenIssuer.php` (`exchangeCode`, `refresh`, `revoke`, `authenticate`, `grants`, `disconnect`) |
| Token- und Widerrufs-Endpunkt | `app/src/OAuth/TokenController.php` (`token`, `revoke`) |
| Discovery-Endpunkte | `app/src/OAuth/DiscoveryController.php` |
| Redaktions-API | `app/src/Api/OAuthController.php` (Anfrage lesen, Zustimmung, verbundene Apps), `app/src/Api/OAuthClientController.php` (Rückfall-Clients, nur Deploy-Token) |
| MCP-Anmeldung | `app/src/Auth/McpAuth.php` (erweitert), `app/src/Mcp/McpController.php` (401-Kopf) |
| Kommandozeile | `bin/dispatch` (`oauth-client:create|list|revoke`, je mit `--remote`) |
| Zustimmungsseite | `app/templates/consent.php`, `Builder::collectConsent()` → `anmelden/zustimmung/index.html`, `app/theme/redaktion/zustimmung.js` |
| Verbundene Apps | `app/theme/redaktion/token.js` (erweitert), `app/theme/redaktion/redaktion.css` |
| Konfiguration | `app/config.php` → neuer Block `oauth` (s. u.), `builder_top_level` um `anmelden` |

### Konfiguration (`app/config.php`, Block `oauth`)

```php
'oauth' => [
    'cimd_hosts' => ['claude.ai'],          // Allowlist für CIMD-client_id (exakter Host)
    'cimd_max_bytes' => 65536,
    'cimd_cache_seconds' => 86400,
    'http' => ['connect_timeout' => 3, 'timeout' => 4, 'max_redirects' => 0], // eigener HttpClient für CIMD; Claude wartet höchstens 10 s
    'request_seconds' => 600,               // Anfrage bis zur Zustimmung
    'code_seconds' => 300,
    'access_seconds' => 3600,
    'refresh_seconds' => 5184000,           // 60 Tage, bei jeder Erneuerung neu
    'grace_seconds' => 30,
    'default_scopes' => ['blog:read', 'blog:write'],
    'manual_redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
],
```

Die übrigen Schlüssel des bestehenden `http`-Blocks (falls `HttpClient` mehr erwartet) werden für den CIMD-Client per `array_merge($config['http'], $config['oauth']['http'])` übernommen.

### Tabellen (`014_oauth.sql`)

```sql
CREATE TABLE oauth_clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(500) NOT NULL UNIQUE,
    kind ENUM('cimd', 'manual') NOT NULL,
    name VARCHAR(120) NOT NULL,
    secret_hash CHAR(64) NULL,
    redirect_uris JSON NOT NULL,
    metadata JSON NULL,
    fetched_at DATETIME NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id CHAR(32) NOT NULL UNIQUE,
    code_hash CHAR(64) NULL UNIQUE,
    client_id VARCHAR(500) NOT NULL,
    redirect_uri VARCHAR(500) NOT NULL,
    challenge VARCHAR(128) NOT NULL,
    scope VARCHAR(200) NOT NULL,
    resource VARCHAR(300) NULL,
    state VARCHAR(500) NULL,
    status ENUM('pending', 'approved', 'denied', 'used') NOT NULL DEFAULT 'pending',
    family CHAR(32) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_oauth_codes_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`tokens` bleibt unverändert (ADR-017 hat die Spalten vorgesehen): Access-Token `kind = 'oauth_access'`, Refresh-Token `kind = 'oauth_refresh'`, `name` = Client-Name, `client_id`, `scope` (Leerzeichen-getrennt, ohne `offline_access`), `family` = 32 Hex je Zustimmung.

### Tokens und Codes

| Was | Klartext | Gespeichert | Gültig |
|---|---|---|---|
| Anfrage-ID | 32 Hex | Klartext `request_id` | 600 s, `status = pending` |
| Code | 64 Hex | `code_hash` = SHA-256 | 300 s, einmal |
| Access-Token | `doa_` + 64 Hex (68 Zeichen) | `tokens.token_hash` | 3600 s |
| Refresh-Token | `dor_` + 64 Hex (68 Zeichen) | `tokens.token_hash` | 60 Tage ab Ausgabe; nur bei `offline_access` |
| Rückfall-Client | ID `dcl_` + 32 Hex, Geheimnis `dcs_` + 64 Hex | `client_id` Klartext, `secret_hash` = SHA-256 | bis widerrufen |

### Scopes

`blog:read`, `blog:write`, `blog:admin` wie ADR-017, dazu `offline_access` (nur Steuerung des Refresh-Tokens, wird nicht gespeichert). Fehlt `scope` in der Anfrage → `default_scopes` plus `offline_access`. Ein unbekannter Wert → Fehler `invalid_scope`. Die Zustimmungsseite zeigt nur angefragte `blog:*`-Scopes; `blog:admin` ist vorab **nicht** angehakt, die anderen schon. Erteilt wird die vom Inhaber gewählte Teilmenge.

### Discovery

- `GET /.well-known/oauth-protected-resource` und `GET /.well-known/oauth-protected-resource/dispatch/mcp` → identisch: `{"resource": RESOURCE, "authorization_servers": [BASE], "scopes_supported": ["blog:read","blog:write","blog:admin"], "bearer_methods_supported": ["header"], "resource_name": "DISPATCH"}`.
- `GET /.well-known/oauth-authorization-server` → `{"issuer": BASE, "authorization_endpoint": BASE/dispatch/oauth/authorize, "token_endpoint": BASE/dispatch/oauth/token, "revocation_endpoint": BASE/dispatch/oauth/revoke, "response_types_supported": ["code"], "grant_types_supported": ["authorization_code","refresh_token"], "code_challenge_methods_supported": ["S256"], "token_endpoint_auth_methods_supported": ["none","client_secret_post"], "revocation_endpoint_auth_methods_supported": ["none","client_secret_post"], "scopes_supported": ["blog:read","blog:write","blog:admin","offline_access"], "client_id_metadata_document_supported": true, "authorization_response_iss_parameter_supported": true}`. Kein `registration_endpoint`.
- Alle drei: `Content-Type: application/json`, `Cache-Control: public, max-age=300`, ohne Datenbankzugriff.
- **401 am MCP-Endpunkt** (kein oder ungültiges Token): `WWW-Authenticate: Bearer error="invalid_token", resource_metadata="BASE/.well-known/oauth-protected-resource/dispatch/mcp", scope="blog:read blog:write"`; ohne `Authorization`-Kopf ohne `error="invalid_token"`. Claude startet die Anmeldung nur bei echtem 401 und erneuert auf 401.

### Endpunkte

| Methode | Pfad | Auth | Phase | Wirkung |
|---|---|---|---|---|
| GET | `/.well-known/oauth-protected-resource[/dispatch/mcp]` | — | 1 | s. o. |
| GET | `/.well-known/oauth-authorization-server` | — | 1 | s. o. |
| GET | `/dispatch/oauth/authorize` | — | 2 | prüft, legt Anfrage an, 302 auf `BASE/anmelden/zustimmung/?anfrage=<request_id>` |
| GET | `/dispatch/api/oauth/requests/{slug}` | JWT | 3 | `{"request_id","client":{"name","host","kind"},"redirect_host","loopback":bool,"scopes":[…],"offline":bool,"expires_in":int}`; unbekannt → 404 `not_found`; nicht mehr `pending` oder abgelaufen → 410 `request_expired` |
| POST | `/dispatch/api/oauth/consent` | JWT | 3 | Body `{"request_id","decision":"allow"|"deny","scopes":[…]}` → 200 `{"redirect"}`; `allow` ohne Scope oder mit nicht angefragtem Scope → 400 `invalid_value`; 410 wie oben |
| POST | `/dispatch/oauth/token` | Client | 3 | `application/x-www-form-urlencoded`; `grant_type=authorization_code|refresh_token` |
| POST | `/dispatch/oauth/revoke` | Client | 3 | RFC 7009, immer 200 |
| GET | `/dispatch/api/oauth/grants` | JWT | 3 | `{"grants":[{"family","client_name","client_host","scopes":[…],"connected_at","last_used_at"}]}` |
| DELETE | `/dispatch/api/oauth/grants/{slug}` | JWT | 3 | widerruft alle Tokens der Familie; 204; unbekannt → 404 |
| POST | `/dispatch/api/oauth-clients` | Deploy | 4 | Body `{"name"}` → 201 `{"id","client_id","client_secret","name","redirect_uris"}` |
| GET | `/dispatch/api/oauth-clients` | Deploy | 4 | `{"clients":[{"id","client_id","name","created_at","last_used_at"}]}` (nur `manual`, nicht widerrufen) |
| DELETE | `/dispatch/api/oauth-clients/{id}` | Deploy | 4 | Client `revoked = 1`, alle Tokens mit dieser `client_id` widerrufen; 204 |

OAuth-Endpunkte (`/dispatch/oauth/*`) antworten im OAuth-Format `{"error","error_description"}` mit `Cache-Control: no-store`; die Redaktions-API bleibt beim Format `{"error","message"}` (ADR-021). `{slug}` deckt 32-Hex-Werte ab (`[a-z0-9-]+`).

### Weiterleitungen aus `/authorize`

Erst Client und `redirect_uri` prüfen; scheitert eines, wird **nie** dorthin weitergeleitet, sondern auf `BASE/anmelden/zustimmung/?fehler=<code>[&host=<host>]` mit `code` ∈ `unbekannte_app` (Host nicht in der Allowlist, unbekannter oder widerrufener Rückfall-Client, Dokument ungültig), `app_nicht_erreichbar` (CIMD nicht abrufbar und nichts im Zwischenspeicher), `falsche_rueckadresse`. Alle späteren Fehler gehen an `redirect_uri` mit `error`, `error_description`, `state` (falls gesetzt) und `iss` = BASE. Erfolg (Zustimmung) → `redirect_uri?code=…&state=…&iss=BASE`; Ablehnen → `error=access_denied`. Vorhandene Query-Parameter der `redirect_uri` bleiben erhalten (`&` statt `?`).

### Rückadressen

Exakter Vergleich gegen `redirect_uris` des Clients. Ausnahme Loopback: Ist die registrierte Adresse `http://localhost/…`, `http://127.0.0.1/…` oder `http://[::1]/…`, gilt die angefragte als passend, wenn Schema, Host, Pfad und Query gleich sind — der Port wird ignoriert (Claude Code nutzt `http://localhost:<Port>/callback`, sein CIMD `https://claude.ai/oauth/claude-code-client-metadata` nennt `http://localhost/callback` und `http://127.0.0.1/callback`). `loopback = true` in der Anfrage-Beschreibung, wenn die Rückadresse so ein Host ist.

### Refresh mit Rotation

Wie die Anmeldung des Inhabers (ADR-020), angepasst auf OAuth: aktives Refresh-Token → altes `revoked = 1`, `expires_at = jetzt + 30 s`, neues Paar derselben Familie. Abgelöstes Token im Kulanzfenster → neues Paar ausgeben und **alle anderen** nicht widerrufenen Refresh-Tokens der Familie sofort widerrufen (es bleibt genau ein gültiger Zweig). Abgelöstes Token nach der Kulanz → ganze Familie widerrufen (Access und Refresh), Audit `oauth.reuse`, `invalid_grant`. Unbekannt, abgelaufen, falscher Client → `invalid_grant`.

### Akteur und Audit

MCP-Aufrufe mit OAuth-Token laufen als `Actor::oauth(<Client-Name>)` (vorhanden), Protokoll `oauth:Claude`. Audit-Aktionen: `oauth.consent` (target `client:<client_id>`, details Scopes), `oauth.deny`, `oauth.token` (Code eingelöst), `oauth.reuse`, `oauth.disconnect`, `oauth.revoke`, `oauth_client.create`, `oauth_client.revoke`. Keine Tokens oder Codes im Klartext ins Protokoll oder Log.

### Getrennte Welten

`/dispatch/mcp` nimmt `dmcp_` und `doa_`. `/dispatch/api/*` nimmt nur JWTs (und das Deploy-Token, wo genannt); `doa_`, `dor_` → 401. OAuth-Tokens erreichen keine Redaktionsfunktion. Veröffentlichen hängt an keinem Scope (ADR-009).

## Finale AK (Gesamtergebnis)

1. Die Claude-App auf Android und im Browser verbindet sich mit `https://quantum-canvas.de/dispatch/mcp` über die Zustimmungsseite und kann danach Tools aufrufen (z. B. Briefing lesen, Thema einreichen).
2. Claude Code meldet sich per `/mcp` an (lokal und gegen Strato), ohne festes Token.
3. Nach mehr als einer Stunde funktioniert der Connector weiter (Refresh mit Rotation).
4. Ein wiederverwendetes Refresh-Token (nach 30 s) widerruft die ganze Familie; die App muss sich neu verbinden.
5. Ein Client von fremdem Host wird abgewiesen, ohne Weiterleitung an seine Rückadresse.
6. Verbundene Apps stehen im Dialog „Zugänge“ und lassen sich trennen; danach antwortet `/dispatch/mcp` mit 401.
7. Feste MCP-Tokens funktionieren unverändert; `/dispatch/api` weist `doa_`-Tokens ab.
8. Ein Rückfall-Client lässt sich per `php bin/dispatch oauth-client:create <name> --remote` anlegen und in der Claude-App unter „Erweiterte Einstellungen“ verwenden (Abnahme: Anlegen und Token-Tausch mit `curl`; Verbinden in der App nur, falls CIMD scheitert).

## Summary

OAuth 2.1 mit CIMD, Zustimmungsseite, rotierendem Refresh-Token, Verbundene Apps und Rückfall-Client per Kommandozeile; deployt auf Strato (Migration 014, Rauchtest grün), mit der Claude-App auf Android abgenommen.

## Files touched

`app/src/OAuth/*`, `app/src/Api/OAuth*Controller.php`, `app/src/Auth/McpAuth.php`, `app/src/Mcp/McpController.php`, `app/migrations/014_oauth.sql`, `app/templates/consent.php`, `app/theme/redaktion/{zustimmung,token}.js`, `bin/dispatch`, ADR-024, Doku.

## Commits

11f2ee4 (Phase 1), Phase 2, 25ef5d0 (Phase 3), 5e4279f (Phase 4), 9e8ce36 (Phase 5), Phase 6 Doku.

## Deviations from plan

Abnahme verkürzt auf Android (Entscheidung des Inhabers 2026-11-12): Claude Code wurde per festem Token statt per OAuth verbunden.

## Follow-ups

- Offen, nicht abgenommen: Finale AK 2 (Claude Code per `/mcp`-Anmeldung), AK 3 (Connector nach über einer Stunde), AK 4 (Wiederverwendung eines Refresh-Tokens widerruft die Familie, gegen Strato), AK 6 (Trennen in „Verbundene Apps“ auf Strato, danach 401), Browser-Abnahme der Claude-App, voller Token-Tausch des Rückfall-Clients gegen Strato.
- Belegt gegen Strato: AK 5 (fremder Host), AK 7 (feste Tokens), AK 8 teilweise (Client anlegen, falsches Geheimnis, falscher Code, Widerruf).
- `client_id` der Claude-App nicht notiert.
