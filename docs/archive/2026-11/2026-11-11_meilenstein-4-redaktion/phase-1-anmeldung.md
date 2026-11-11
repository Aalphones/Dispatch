# Phase 1 — Anmeldung des Inhabers, Router, API-Anmeldung

## Kontext

Lesen vor dem Bau:

- `README.md` dieses Plans → „Kontrakt“: „Umgebung“, „Anmeldung“, Zeilen der Phase 1 in „Endpunkte“.
- `docs/conventions/php.md`, `docs/conventions/dod.md`, `docs/decisions/017-mcp-tokens-und-scopes.md`, `docs/decisions/009-akteur-inhaber-rechte-aenderungsvorschlaege.md`.
- Code: `app/index.php` (heutiger Router), `app/src/Http/Request.php`, `app/src/Http/Response.php`, `app/src/Auth/DeployToken.php`, `app/src/Auth/McpAuth.php`, `app/src/Domain/TokenRepository.php`, `app/src/Domain/AuditRepository.php`, `app/src/Domain/Actor.php`, `app/src/Util/Env.php`, `app/migrations/010_token.sql`, `deploy.cmd` (Abschnitt `[3/7] app\.env schreiben`), `dev.env.example`, `dev-router.php`.
- Windows-Falle für `deploy.cmd`: Werte mit `!` brechen die verzögerte Erweiterung; bcrypt-Hashes enthalten `$`, `/`, `.` — prüfen, dass der Hash unverändert in `app\.env` ankommt (Inhalt nach dem Schreiben mit dem Original vergleichen).
- Fehlerklassen (Vault) geprüft: keine einschlägig für PHP-Auth; CSS-Fehlerklasse betrifft erst Phase 7.

## Schritt 0 — lokale Datenbank

MySQL 8.4 lokal starten (Datenbank `dispatch_dev`, ADR-008). Gelingt das nicht in einer Runde: anhalten, Lage in `FINDINGS.md` notieren, Inhaber fragen. Danach `php bin/dispatch migrate` grün.

## AK der Phase

1. `php bin/dispatch migrate` legt `login_attempts` an (Migration 012) und Migration 013 (`stats_visitor_paths`, Spalten in Phase 5) — 013 wird schon hier angelegt, damit Phase 5 keine Migration nachreicht: `day` DATE, `visitor_hash` CHAR(64), `path` VARCHAR(300), PK (`day`, `visitor_hash`, `path`).
2. `Http\Router`: `add(string $method, string $pattern, callable $handler)`, `dispatch(Request): never`. Platzhalter `{id}` und `{slug}`; Handler bekommt die Werte als Array (`['id' => 12]`, `id` als int). Bekannter Pfad, andere Methode → 405 mit `Allow` (alle erlaubten Methoden, kommagetrennt). Unbekannt → 404 wie heute. `app/index.php` registriert die drei bestehenden Routen und `/dispatch/mcp` über den Router; ihr Verhalten bleibt gleich (Gegenprobe: `token:list --remote`, `category:create --remote` gegen localhost, MCP `tools/list`).
3. `Auth\Jwt::encode(array $claims): string` und `Jwt::decode(string $jwt): ?array` nach Kontrakt. Gegenproben in einem Wegwerf-Skript im Scratchpad: gültig → Claims; `alg: none` mit leerer Signatur → `null`; mit anderem Schlüssel signiert → `null`; `exp` in der Vergangenheit → `null`; falsches `aud` → `null`; vier statt drei Teile → `null`.
4. `POST /dispatch/api/auth/login` nach Kontrakt; Cookie-Attribute im Antwort-Header sichtbar (`curl -i`). Falscher Name und falsches Passwort geben dieselbe Meldung.
5. Login-Bremse: 10 Fehlversuche, der 11. Aufruf (auch mit richtigem Passwort) → 429 mit `Retry-After: 900`. `SELECT * FROM login_attempts` zeigt nur Hashes.
6. Refresh-Rotation nach Kontrakt, alle vier Zustände einzeln geprüft (Kulanzfenster per zweitem `curl` mit demselben Cookie binnen 30 s → 200 ohne `Set-Cookie`; nach 31 s → 401 `session_revoked`, danach ist auch das neueste Token der Familie widerrufen).
7. Refresh ohne `X-Dispatch: 1` oder mit fremdem `Origin` → 403 `forbidden_origin`.
8. `logout` widerruft und löscht das Cookie (`Max-Age=0`); `logout-all` widerruft alle `owner_refresh`-Tokens.
9. `ApiAuth::owner` lehnt ab: kein Header, `dmcp_`-Token, ungültiges JWT → 401 `unauthorized` mit `WWW-Authenticate: Bearer realm="dispatch-api"`. `ApiAuth::ownerOrDeploy` nimmt zusätzlich das Deploy-Token (`DeployToken`, Vergleich mit `hash_equals`).
10. Fehlt eine Pflicht-Umgebungsvariable → `auth/*` antwortet 503 `not_configured`.
11. Audit-Einträge nach Kontrakt vorhanden.

## Checkliste

- [x] Schritt 0: MySQL lokal lauffähig, `migrate` grün
- [x] `app/migrations/012_login_attempt.sql`, `app/migrations/013_stats_visitor_path.sql`
- [x] `app/src/Http/Router.php`; `app/index.php` auf den Router umstellen
- [x] `Request`: `cookie(string $name): ?string`, `ip(): string` (`REMOTE_ADDR`, nie Weiterleitungs-Header — Strato steht nicht hinter einem eigenen Proxy), `json(): array` (Body als Objekt, sonst `DomainError('invalid_json')` → 400)
- [x] `Response`: `cookie(string $name, string $value, int $maxAge, string $path)` setzt den Cookie per `setcookie` mit Optionen-Array (`httponly`, `secure` nach Umgebung, `samesite` = `Strict`)
- [x] `app/src/Auth/Jwt.php`
- [x] `app/src/Auth/LoginThrottle.php` (`assertAllowed(string $ip)`, `recordFailure(string $ip)`, `prune()`)
- [x] `TokenRepository` erweitern: `insertOwnerRefresh(hash, family, expiresAt)`, `findByHash(hash, kind)` (ohne Gültigkeitsfilter), `revokeOne(id, expiresAt)`, `revokeFamily(family)`, `revokeAllOwnerRefresh()`
- [x] `app/src/Auth/OwnerSession.php` (`login`, `refresh`, `logout`, `logoutAll`); Rückgaben enthalten das neue Cookie oder `null`
- [x] `app/src/Auth/ApiAuth.php`
- [x] `app/src/Api/AuthController.php` (vier Handler)
- [x] `deploy.cmd`: drei neue Pflichtwerte (`call :needValue`) und in `app\.env` schreiben; `deploy.env.example` und `dev.env.example` (dort zusätzlich `DISPATCH_INSECURE_COOKIES=1`) ergänzen, ohne echte Werte
- [x] `dev-router.php`: prüfen, dass `Set-Cookie` und `Origin` durchgereicht werden (der eingebaute Server tut das; nur bestätigen)
- [x] ADR-020 „Anmeldung des Inhabers“: JWT selbst gebaut mit festem Algorithmus; Refresh über die Tabelle `tokens` ohne neue Spalten (Kulanz = `revoked = 1` mit `expires_at` in der Zukunft); Login-Bremse mit IP-Hash statt IP; keine erneute Passworteingabe vor dem Anlegen eines Zugangs (Entscheidung des Inhabers; Schutz gegen ein abgegriffenes JWT bleibt die strenge CSP und die kurze Laufzeit von 15 Minuten). Verworfen: Sitzungen in PHP-Sessions (Cookie für jede API-Anfrage, CSRF-Fläche), eigene Spalte `replaced_at` (Migration für einen Sonderfall)
- [x] Doku: `docs/routes.md` neu (Tabelle Methode · Pfad · Auth · Controller · Phase), `docs/models.md` (012, 013, `owner_refresh`-Nutzung), `docs/code-map.md` (`Http/Router`, neue `Auth/`-Klassen), AGENTS.md „Lokal starten“ (drei neue Variablen, `hash-password`)

## Report-Back

**Status: complete.** Schritt 0: `mysqld` lief nach dem Neustart nicht; mit dem Kurzpfad (`C:\PROGRA~1\MySQL\MYSQLS~1.4`) als `--basedir` startet er aus `Start-Process` (Leerzeichen im Pfad brachen das Argument). `migrate` hat 012 und 013 angelegt.

Gegenproben gegen `php -S localhost:8080` und im Scratchpad: JWT (alle sechs Fälle plus falsches `iss`/`sub`, `HS512`), Login-Fehlermeldung identisch, Cookie-Attribute, Bremse (11. Aufruf 429, `login_attempts` nur Hashes), Refresh in allen vier Zuständen, 403 ohne `X-Dispatch` / mit fremdem oder fehlendem `Origin`, Abmelden, Überall abmelden, `ApiAuth` (kein Header, `dmcp_`, kaputtes JWT → 401 mit `WWW-Authenticate`; `ownerOrDeploy` nimmt das Deploy-Token), 503 `not_configured`, Router (405 mit `Allow`, 404), alte Routen (`tokens`, `categories`, `deploy`) und MCP `tools/list` unverändert. Hash-Weg durch `deploy.cmd`: Hash und Schlüssel kommen unverändert in `.env` an.

Abweichungen: `TokenRepository::revokeOne(id, graceSeconds)` nimmt Sekunden bis zum Ablauf statt eines Zeitstempels (Vergleich bleibt in SQL, php.md). `--remote`-Gegenprobe nicht gegen Strato gelaufen (deploy.env zeigt dorthin); stattdessen dieselben Endpunkte per curl auf localhost. Ein Refresh-Token, das nach dem Abmelden vorgelegt wird, ergibt `session_revoked` (Wiederverwendung laut Kontrakt, `expires_at = jetzt`), nicht `session_expired`.

Nicht geprüft: `deploy.cmd` gegen Strato (Phase 10).
