# Phase 1 — Gerüst, Brücke, Sperre, lokaler Dev-Server

**Rating:** standard · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- `AGENTS.md`, `docs/code-map.md`, `docs/conventions/php.md`, `docs/conventions/dod.md`
- `docs/decisions/001-…`, `002-code-im-webroot-gesperrt.md`
- README dieses Plans → „Kontrakt" (Fehlerformat, `.env`-Schlüssel, Platzhalter für `/dispatch/mcp`)
- Vorlage für Brücke und Sperre (nur lesen, fremdes Repo): `C:\Users\sasch\develop\CardMaker\api-bridge\.htaccess`, `…\api-bridge\index.php`, `…\backend\.htaccess`, Fehler-Handler in `…\backend\public\index.php` (Zeilen mit `set_error_handler`, `set_exception_handler`, `register_shutdown_function`)
- Fehlerklassen: `~/.claude/knowledge/topics/strato-shared-hosting.md` (Fallen-Tabelle) und `~/.claude/knowledge/systeme/strato-shared-hosting.md` → „Der Aufbau, der funktioniert", „PHP-Version". Eine Vault-Entity für PHP gibt es nicht.

## Entscheidungen (fest, nicht neu aufrollen)

- Kein Router-Framework: `app/index.php` vergleicht Methode und **rohen** Pfad aus `REQUEST_URI` (ohne Query) mit einer `match`-Tabelle. Keine Kürzung um das Skriptverzeichnis — Routen tragen das volle Präfix `/dispatch/…`.
- Neue Ordner `app/src/Http/` (Request, Response) und — ab Phase 3 — `app/src/Db/`; beide in `code-map.md` nachtragen.
- Lokale Entwicklung liest `app/.env.local`, nie `app/.env`, weil `deploy.cmd` `app/.env` mit den Server-Werten überschreibt. Umschalten über die Umgebungsvariable `DISPATCH_ENV_FILE`, die nur der Dev-Router setzt. Festgehalten in ADR-006.

## Dateien

| Datei | Inhalt |
|---|---|
| `app/bootstrap.php` | `declare(strict_types=1)`; `error_reporting(E_ALL)`, `display_errors=0`; PSR-4-Autoloader `Dispatch\` → `app/src/` per `spl_autoload_register`; `require __DIR__ . '/src/Site/functions.php'` erst ab Phase 3 (dann ergänzen); `Env::load(getenv('DISPATCH_ENV_FILE') ?: __DIR__ . '/.env')`; `set_error_handler`, der zuerst `if ((error_reporting() & $severity) === 0) { return false; }` prüft und sonst `ErrorException` wirft; `set_exception_handler` → `Log::error(...)` + `Response::error('server_error', 'Interner Serverfehler.', 500)`; `register_shutdown_function` für fatale Fehler (Typen wie im CardMaker-Vorbild) → Log + 500, falls `!headers_sent()`; gibt `require __DIR__ . '/config.php'` zurück. |
| `app/config.php` | `return [...]` mit `base_url` (`Env::get('DISPATCH_BASE_URL')`), `app_dir` (`__DIR__`), `storage_dir` (`__DIR__ . '/storage'`), `web_root` (`Env::get('DISPATCH_WEB_ROOT') ?: dirname(__DIR__)`), `reserved_top_level` = `['card', 'api', 'backend', 'scanner', 'dispatch', 'dispatch-app', '.htaccess', '.well-known']` (ADR-001). |
| `app/index.php` | `$config = require __DIR__ . '/bootstrap.php'`; `$request = Request::fromGlobals()`; `match (true)`: Pfad `/dispatch/mcp` → `Response::error('unauthorized', 'MCP folgt in Meilenstein 3.', 401, ['WWW-Authenticate' => 'Bearer'])`; sonst `Response::error('not_found', 'Diesen Pfad gibt es hier nicht.', 404)`. Phase 3 ergänzt Routen. |
| `app/src/Http/Request.php` | `final class Request`: `fromGlobals()`, `method(): string`, `path(): string` (`parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`, Fallback `/`), `header(string $name): ?string` (liest `HTTP_<NAME>`), `bearerToken(): ?string` (aus `HTTP_AUTHORIZATION`, dann `REDIRECT_HTTP_AUTHORIZATION`; nur bei Präfix `Bearer `). |
| `app/src/Http/Response.php` | `final class Response`: `json(array $body, int $status = 200, array $headers = []): never` — setzt Status, `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, Zusatz-Header, gibt `json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)` aus, `exit`. `error(string $code, string $message, int $status, array $headers = []): never` → `json(['error' => $code, 'message' => $message], …)`. |
| `app/src/Util/Env.php` | `final class Env`: `load(string $path): void` — fehlt die Datei, still nichts; je Zeile: leer oder mit `#` beginnend überspringen, an erstem `=` teilen, Schlüssel trimmen, Wert trimmen; steht der Wert in einfachen Anführungszeichen, diese entfernen und den Inhalt **unverändert** übernehmen (ein `#` darin bleibt). Speichert in einem statischen Array, kein `putenv`. `get(string $key, string $default = ''): string`. |
| `app/src/Util/Log.php` | `final class Log`: `error(string $message, array $context = []): void` — hängt eine JSON-Zeile `{time (UTC, ISO 8601), level, message, context}` an `<storage_dir>/logs/app.log`, legt `logs/` bei Bedarf an (`mkdir(..., 0750, true)`). Kommentar oben: nie Passwörter, Tokens oder `.env`-Werte loggen. Pfad über `dirname(__DIR__, 2) . '/storage/logs'`. |
| `app/.htaccess` | `Options -Indexes` plus `Require all denied` / `Deny from all` in `IfModule`-Weichen, wörtlich nach CardMaker `backend/.htaccess`; Kommentar: Brücke bindet per Dateizugriff ein, Sperre betrifft das nicht. |
| `bridge/index.php` | `declare(strict_types=1);` + Kommentar (landet auf dem Server als `www/dispatch/index.php`, ADR-002) + `require __DIR__ . '/../dispatch-app/index.php';` |
| `bridge/.htaccess` | `Options -Indexes`; in `<IfModule mod_rewrite.c>`: `RewriteEngine On`, Authorization-Weitergabe (`RewriteCond %{HTTP:Authorization} .` + `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`, Kommentar warum), `RewriteCond %{REQUEST_FILENAME} !-f`, `RewriteRule ^ index.php [L]`. |
| `dev-router.php` (Repo-Wurzel, wird nicht hochgeladen) | Pfad aus `REQUEST_URI`; beginnt er mit `/dispatch/` oder `/.well-known/oauth-` → `putenv('DISPATCH_ENV_FILE=' . __DIR__ . '/app/.env.local')`, `require __DIR__ . '/app/index.php'`, `return true`; beginnt er mit `/dispatch-app/` → `http_response_code(403)`, `return true` (bildet die Sperre nach); sonst `return false` (statische Datei aus `public/`). Kopfkommentar mit Startbefehl `php -S localhost:8080 -t public dev-router.php`. |
| `dev.env.example` (Repo-Wurzel) | Vorlage für `app/.env.local`: alle Schlüssel aus dem Kontrakt plus `DISPATCH_WEB_ROOT=` (absoluter Pfad zum lokalen `public/`), Werte in einfachen Anführungszeichen, Kommentar „kopieren nach app/.env.local". |
| `.gitignore` | `app/.env.local` ergänzen. |
| `.github/workflows/ci.yml` | `git ls-files` zusätzlich um `'dev-router.php'` ergänzen. |

## Abnahme (Phase)

Lokal mit `C:\Users\sasch\develop\.tools\php\php.exe` (8.5.9), Ordner `public/` anlegen, `app/.env.local` aus `dev.env.example`:

- [x] `php -l` über jede neue `.php`-Datei: keine Fehler.
- [x] `curl -i http://localhost:8080/dispatch/mcp` → 401, JSON `{"error":"unauthorized",…}`, Header `WWW-Authenticate: Bearer`, `Cache-Control: no-store`.
- [x] `curl -i http://localhost:8080/dispatch/gibt-es-nicht` → 404 JSON `not_found`.
- [x] `curl -i http://localhost:8080/dispatch-app/.env` → 403.
- [x] Ausnahme-Pfad: vorübergehend `throw new RuntimeException('probe');` als erste Zeile nach dem Bootstrap in `app/index.php` → Anfrage liefert 500 JSON `server_error`, `app/storage/logs/app.log` hat eine Zeile mit `probe`. Danach Zeile wieder entfernen.
- [x] Env-Parser: Skript im Scratchpad lädt eine Testdatei mit `X='a#b c'` und `Y=plain` → `Env::get('X') === 'a#b c'`, `Env::get('Y') === 'plain'`.

## Checkliste

- [x] Dateien laut Tabelle anlegen
- [x] Abnahme fahren, Ergebnisse in „Report-Back"
- [x] ADR-006 „Lokale Entwicklung: Dev-Router und eigene Env-Datei" (Kontext: `deploy.cmd` überschreibt `app/.env`; Optionen: gemeinsame Datei / `DISPATCH_ENV_FILE` + `.env.local`; Entscheidung; Konsequenz: Dev-Router ist der einzige Setzer) + Zeile in `docs/decisions/README.md`
- [x] `docs/code-map.md`: Zeile „Noch kein Code" anpassen; `bootstrap.php`, `src/Http/`, `dev-router.php`, `dev.env.example` eintragen
- [x] `AGENTS.md` bzw. `README.md`: Abschnitt „Lokal starten" mit Startbefehl (eine Zeile + Verweis auf `dev.env.example`)

## Report-Back
Alle sechs Abnahmepunkte mit `php.exe` 8.5.9 und `php -S localhost:8080 -t public dev-router.php` durchgespielt: `php -l` über alle neuen Dateien ohne Fehler · `/dispatch/mcp` → 401 JSON `unauthorized`, `WWW-Authenticate: Bearer`, `Cache-Control: no-store` · `/dispatch/gibt-es-nicht` → 404 `not_found` · `/dispatch-app/.env` → 403 · temporäres `throw` → 500 `server_error`, Logzeile mit `probe` (Zeile wieder entfernt) · Env-Parser: `X='a#b c'` und `Y=plain` korrekt. Nicht geprüft: die echte Brücke und Sperre (`.htaccess`) — dafür gibt es lokal keinen Apache; Nachweis folgt in Phase 5.
