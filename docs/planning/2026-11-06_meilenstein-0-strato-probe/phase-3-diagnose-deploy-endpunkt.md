# Phase 3 — Diagnose-Route, Deploy-Endpunkt, Platzhalter-Builder

**Rating:** heikel (Token-Prüfung, Schreiben in die geteilte Wurzel) · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" (Pfade, Antwortformen, Deploy-Token, `.env`-Schlüssel) — verbindlich
- Phase 1 → „Dateien" (Request, Response, Env, Log, config.php existieren)
- `docs/decisions/001-…` (reservierte Namen, Builder löscht nur Manifest-Dateien), `005-deploy-skript.md` (Nachlauf), `docs/conventions/php.md`, `docs/conventions/frontend.md`, `docs/conventions/dod.md`
- Konzept → „Betrieb auf Strato Shared Hosting" → Tabelle „Technische Annahmen und Prüfpunkte" (was die Diagnose misst)
- Fehlerklassen: `~/.claude/knowledge/topics/strato-shared-hosting.md` → Zeilen „Migrations-Runner als CLI-Skript" und „`CREATE TABLE` in eine PDO-Transaktion"; `…/systeme/strato-shared-hosting.md` → „PHP-Version"

## Entscheidungen (fest)

- **Deploy-Ablauf:** Token prüfen → Datenbank öffnen → Migrationen → Platzhalter bauen. Jeder Schritt in eigenem `try`; Fehler → `Log::error` mit Schritt und Ausnahme-Text, Antwort 500 `deploy_failed` mit Satz „Schritt <migrationen|bauen|datenbank> fehlgeschlagen, Details im Server-Log." — nie den Ausnahme-Text in der Antwort (kann Host oder Benutzer enthalten).
- **Migrator ohne Transaktion:** DDL committet in MySQL implizit. Jede SQL-Datei wird an `/;\s*(\r?\n|$)/` in Einzelanweisungen geteilt, leere Teile verworfen, jede mit `exec()` ausgeführt — kein Mehrfach-`exec`, weil MySQL dabei Fehler nach der ersten Anweisung verschluckt. Danach `INSERT` in die Registry (Prepared Statement). Meilenstein 0 hat keine Migrationsdatei; die Registry-Tabelle selbst ist die Probe.
- **Platzhalter kommt vom Builder**, nicht per Upload: ADR-005 lädt nichts in die Wurzel außer der `.htaccess`. Der Builder schreibt nur relativ zu `web_root`, verweigert Pfade mit `..`, absolute Pfade und jeden ersten Pfadteil aus `reserved_top_level`, schreibt atomar (temporäre Datei im Zielordner, dann `rename`) und trägt jede Datei ins Manifest. **Löschen kommt erst in Meilenstein 2** — eine veraltete `placeholder.<hash>.css` bleibt liegen, das ist harmlos.
- **Diagnose gibt nie Werte aus `.env` aus**, nur ob ein Schlüssel gesetzt ist.
- Festgehalten in ADR-007.

## Dateien

| Datei | Inhalt |
|---|---|
| `app/src/Auth/DeployToken.php` | `final class DeployToken`: `static matches(Request $request, string $expected): bool` — `$expected === ''` → `false`; Kandidat = `$request->bearerToken() ?? $request->header('X-Dispatch-Token')`; `null` → `false`; sonst `hash_equals($expected, $candidate)`. |
| `app/src/Db/Connection.php` | `final class Connection`: `static open(): PDO` — DSN `mysql:host=…;dbname=…;charset=utf8mb4` aus `Env`; Optionen `ERRMODE_EXCEPTION`, `DEFAULT_FETCH_MODE => FETCH_ASSOC`, `EMULATE_PREPARES => false`; danach `exec("SET time_zone = '+00:00'")`. |
| `app/src/Db/Migrator.php` | `final class Migrator(PDO $pdo, string $directory)`: `run(): array` — `CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`; bereits angewandte Namen lesen; `glob($directory . '/*.sql')`, `sort`; je neue Datei: teilen und ausführen (s. o.), dann `INSERT INTO schema_migrations (filename, applied_at) VALUES (?, UTC_TIMESTAMP())`; gibt Liste der neu angewandten Dateinamen zurück. |
| `app/migrations/.gitkeep` | leer, damit der Ordner existiert |
| `app/src/Site/functions.php` | `namespace Dispatch\Site; function e(string $value): string` → `htmlspecialchars($value, ENT_QUOTES \| ENT_SUBSTITUTE \| ENT_HTML5, 'UTF-8')`. In `bootstrap.php` per `require` einbinden. |
| `app/src/Site/Manifest.php` | `final class Manifest(string $file)`: `read(): array` (Liste relativer Pfade, leer wenn Datei fehlt), `write(array $files): void` (JSON `{"built_at": "<UTC ISO>", "files": [...]}`, atomar). Datei: `<storage_dir>/manifest.json`. |
| `app/src/Site/Builder.php` | `final class Builder(array $config)`: `buildPlaceholder(): array` — CSS aus `app/theme/placeholder.css` lesen, Name `assets/placeholder.<erste 8 Zeichen sha1>.css`; `index.html` aus `templates/placeholder.php`, `404.html` aus `templates/404.php` rendern (`render(string $template, array $vars): string` per `ob_start` + `include`, Variable `$css` = absoluter Pfad `/assets/…`); alle drei mit `writeFile` schreiben; Manifest schreiben; Liste zurückgeben. `public writeFile(string $relative, string $content): void` mit den Schutzregeln oben, wirft `RuntimeException` bei Verstoß; legt Unterordner per `mkdir(..., 0755, true)` an. |
| `app/templates/placeholder.php` | HTML5, `lang="en"`, `<meta charset="utf-8">`, Viewport, `<title>DISPATCH</title>`, `<meta name="description" content="Your agents. Your publication.">`, `<meta name="robots" content="noindex">`, `<link rel="stylesheet" href="<?= e($css) ?>">`; `<main>` mit `<h1>DISPATCH</h1>` und `<p>Your agents. Your publication.</p>`. Kein Skript, kein Inline-Style. |
| `app/templates/404.php` | gleiches Gerüst, `<title>Not found · DISPATCH</title>`, `<h1>Not found</h1>`, Link `<a href="/">DISPATCH</a>`. |
| `app/theme/placeholder.css` | Systemschriften, zentrierter Block, `prefers-color-scheme: dark`, sichtbarer Fokusrahmen für den Link, Kontrast ≥ WCAG AA; unter 2 KB. |
| `app/src/Api/DeployController.php` | `final class DeployController(array $config)`: `handle(Request $r): never` — Ablauf s. o.; Erfolg → `Response::json(['migrations' => …, 'built' => …])`. |
| `app/src/Api/DiagController.php` | `final class DiagController(array $config)`: `handle(Request $r): never` — Token wie Deploy; Prüfungen s. u.; Antwort `{"all_ok": …, "checks": {…}}`. |
| `app/index.php` | Routen ergänzen: `POST /dispatch/api/deploy` → DeployController, `GET /dispatch/api/diag` → DiagController; bekannter Pfad mit falscher Methode → 405 `method_not_allowed`. |

### Prüfungen der Diagnose (`checks`)

| Name | `value` | `ok` wenn |
|---|---|---|
| `php_version` | `PHP_VERSION` | `version_compare(PHP_VERSION, '8.5.0', '>=')` |
| `sapi` | `PHP_SAPI` | immer (Info) |
| `extensions` | Objekt `name → bool` für `pdo_mysql, curl, openssl, fileinfo, mbstring, intl, dom, gd, exif` | alle außer `exif` geladen |
| `gd_webp` | bool | `function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false)` |
| `authorization_header` | Objekt: `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, `getallheaders` (je bool, ob vorhanden) | einer davon `true` — nur aussagekräftig, wenn die Anfrage einen `Authorization`-Header schickte |
| `env` | Objekt `schlüssel → bool (gesetzt)` für die sechs Server-Schlüssel | alle `true` |
| `mysql` | `SELECT VERSION()` und InnoDB-Unterstützung aus `information_schema.ENGINES` | Verbindung steht und InnoDB `YES`/`DEFAULT` |
| `outbound_https` | HTTP-Status oder `curl_error` für `GET https://example.com/` (Timeout 10 s) | Status 200 |
| `limits` | `upload_max_filesize, post_max_size, memory_limit, max_execution_time, user_ini.filename` | immer (Info) |
| `open_basedir` | `ini_get('open_basedir')` | immer (Info) |
| `web_root` | `realpath($_SERVER['DOCUMENT_ROOT'])` und `realpath(web_root)` | beide gleich (belegt, dass der Builder in den ausgelieferten Ordner schreibt) |
| `web_root_writable` | bool | Testdatei `.dispatch-write-probe` in `web_root` schreiben und löschen gelingt |
| `storage_writable` | bool | dasselbe in `<storage_dir>/logs` |

## Abnahme (Phase)

Lokal per Dev-Router (`app/.env.local` mit Test-Token `local-test-token`). Stand Planung: auf dieser Maschine läuft weder MySQL/MariaDB noch Docker. Liegt kein lokales MySQL/MariaDB vor, schlägt der Datenbank-Schritt fehl — dann die übrigen Punkte fahren und die Migrator-Probe in Phase 5 nachholen (in Report-Back vermerken).

- [ ] `php -l` über alle neuen Dateien.
- [ ] `POST /dispatch/api/deploy` ohne Token → 401; mit falschem Token → 401; `GET` → 405.
- [ ] Ohne lokale Datenbank: mit Token → 500 `deploy_failed` „Schritt datenbank …"; Builder dann per Scratchpad-Skript direkt (`bootstrap.php` laden, `(new Builder($config))->buildPlaceholder()`) prüfen — es gelten die Datei-Punkte der nächsten Zeile.
- [ ] Mit Token per `Authorization: Bearer` (nur mit lokaler Datenbank) → 200, `public/index.html`, `public/404.html`, `public/assets/placeholder.<hash>.css` existieren, `app/storage/manifest.json` listet sie; zweiter Lauf → `migrations: []`, gleiche Dateien.
- [ ] Mit Token per `X-Dispatch-Token` → ebenfalls 200.
- [ ] `http://localhost:8080/` zeigt den Platzhalter im Browser; im HTML kein `<script>`, kein `style=`.
- [ ] `GET /dispatch/api/diag` mit Token → 200, alle Prüfungen vorhanden; die Antwort enthält keinen Wert aus `app/.env.local` (Suche im Antworttext nach Token und DB-Passwort → kein Treffer).
- [ ] `Builder::writeFile` verweigert per Scratchpad-Skript: `card/x.html`, `../x.html`, `dispatch/x`, `/etc/x` (jeweils `RuntimeException`); `artikel/x.html` geht durch (danach löschen).
- [ ] Ausgelöster Fehler (z. B. falsches DB-Passwort in `.env.local`) → 500 `deploy_failed` ohne Passwort, Host oder Benutzer in der Antwort; Details in `app.log`.

## Checkliste

- [ ] Dateien laut Tabelle anlegen, `bootstrap.php` um `functions.php` ergänzen
- [ ] Abnahme fahren, Ergebnisse in „Report-Back" (Fallliste für die Sicherheitspunkte nach `dod.md`)
- [ ] ADR-007 „Strato-Probe: Diagnose-Route und Platzhalter über den Builder" (Token aus zwei Headern und warum; Lebensdauer bis Ende Phase 5; Platzhalter per Deploy-Endpunkt statt Upload; Migrator ohne Transaktion, Anweisungen einzeln) + Zeile in `docs/decisions/README.md`
- [ ] `docs/code-map.md`: `src/Db/`, `src/Auth/DeployToken`, `src/Site/Builder`/`Manifest`, `templates/placeholder.php`, `theme/placeholder.css`, `storage/manifest.json` eintragen

## Report-Back
