# Code-Map — DISPATCH

Feature → Ordner. Bewusst grob, keine Zeilennummern. Gerüst (Bootstrap, Http, Env, Log, Brücke, Dev-Router) steht seit Meilenstein 0; übrige Ordner entstehen mit ihren Meilensteinen.

## Namensschema (erst raten, dann suchen)

Eine Domäne `<x>` (post, source, media, queue, category, document, change, setting, stats, token) heißt in jeder Schicht gleich:

| Schicht | Datei |
|---|---|
| Fachlogik | `app/src/Domain/<X>Service.php` |
| Datenbankzugriff | `app/src/Domain/<X>Repository.php` |
| MCP-Tools | `app/src/Mcp/Tools/<X>Tools.php` |
| Redaktions-API | `app/src/Api/<X>Controller.php` |
| Migration | `app/migrations/NNN_<x>.sql` |
| Redaktionsmodus | `app/theme/redaktion/<x>.js` |

Seitentypen der öffentlichen Website: `app/templates/<seitentyp>.php` (home, article, category, tag, archive, search, page, preview, consent, 404).

## Repo → Server

| Im Repo | Auf dem Server (`www/`) | Inhalt |
|---|---|---|
| `app/` | `dispatch-app/` | gesamter PHP-Code, Templates, Theme-Quellen, Startinhalte, Migrationen, `.env`, `storage/` — gesperrt (ADR-002) |
| `bridge/` | `dispatch/` | einziger PHP-Einstieg: `index.php` + `.htaccess` |
| `web-root/.htaccess` | `.htaccess` | Wurzel-Regeln (ADR-003) |
| — | `index.html`, `artikel/`, `assets/`, `media/`, … | vom Builder erzeugt, nie hochgeladen |
| `bin/dispatch` | — | CLI, nur lokal (`hash-password`, `token:create`, `build`) |
| `deploy.cmd` | — | Deploy (ADR-005) |
| `dev-router.php`, `dev.env.example` | — | lokaler Server `php -S` mit Brücke und Sperre, Vorlage für `app/.env.local` (ADR-006) |

## Ordner in `app/`

| Ordner | Aufgabe |
|---|---|
| `bootstrap.php` | Autoloader, `.env` laden, Fehler-Handler; liefert die Konfiguration |
| `index.php` | Router: `/dispatch/mcp` → Mcp, `/dispatch/api/*` → Api, `/dispatch/oauth/*` und `/.well-known/oauth-*` → OAuth |
| `src/Http/` | `Request`, `Response` (JSON-Antworten, Fehlerformat) |
| `config.php` | Basis-URL, Pfade, Formvorgaben, reservierte Namen der obersten Ebene |
| `src/Mcp/` | Protokoll (beide Generationen), Tool-Registry, `Tools/` |
| `src/OAuth/` | Discovery, CIMD, Autorisierung, Token, Widerruf |
| `src/Api/` | Redaktions-API-Controller, Deploy-Endpunkt (`DeployController`), Diagnose (`DiagController`, nur bis Ende Meilenstein 0) |
| `src/Auth/` | Deploy-Token (`DeployToken`), JWT, Login-Bremse, MCP-Tokens, Scopes |
| `src/Db/` | PDO-Verbindung (`Connection`), Migrator |
| `src/Domain/` | Services und Repositories je Domäne |
| `src/Site/` | Builder, Manifest, Template-Helfer `e()`, Suchindex, Feed, Sitemap |
| `src/Util/` | Sanitizer, Formprüfung, HTTP-Client mit SSRF-Schutz, Bildvarianten, Zeit |
| `templates/` | PHP-Templates der Website (Meilenstein 0: `placeholder.php`, `404.php`) |
| `theme/` | `style.css`, `site.js`, `redaktion/` (nachgeladen); Meilenstein 0: `placeholder.css` |
| `defaults/` | Startinhalte als Markdown |
| `migrations/` | nummerierte SQL-Dateien |
| `storage/` | `manifest.json` (vom Builder geschriebene Dateien), `media/`, `logs/` — nur auf dem Server und lokal, nicht im Git |

## Faustregeln

- Fachliche Funktion? → zuerst `Domain/<X>Service.php`, dann als Tool in `Mcp/Tools/<X>Tools.php`; die API ruft denselben Service.
- Kennt die Funktion HTTP? → gehört in `Mcp/`, `Api/` oder `OAuth/`, nie in `Domain/`.
- Erzeugt etwas eine öffentliche Datei? → nur `Site/` schreibt in die Wurzel, und nur über das Manifest.
- Öffentlich sichtbare Daten ändern ohne Login? → wird ein Änderungsvorschlag (`Domain/ChangeService.php`), keine direkte Änderung.
