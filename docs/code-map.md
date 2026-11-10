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
| `bin/dispatch` | — | CLI, nur lokal (`hash-password`, `migrate`, `seed`, `demo`; `build` ab Phase 2 von Meilenstein 2) |
| `bin/demo/` | — | Demo-Daten für `demo`: Kategorien, Artikelmetadaten, Bildgenerator (`demo.php`) und Artikeltexte (`<name>.html`); nur lokal, nicht im Webroot |
| `deploy.cmd`, `deploy.env.example`, `deploy-backup/` | — | Deploy (ADR-005); Vorlage für `deploy.env`; Sicherung der Wurzel-`.htaccess` (nicht im Git) |
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
| `src/Api/` | Redaktions-API-Controller, Deploy-Endpunkt (`DeployController`) |
| `src/Auth/` | Deploy-Token (`DeployToken`), JWT, Login-Bremse, MCP-Tokens, Scopes |
| `src/Db/` | PDO-Verbindung (`Connection`), `Migrator`, `Transaction::run`/`join` (nur DML) |
| `src/Domain/` | Services und Repositories je Domäne; `Actor` (Akteur), `DomainError` (fachlicher Fehler), `AuditRepository` (Protokoll); Einstellungen, Dokumente, Kategorien, Änderungsvorschläge (`SettingService`, `DocumentService`, `CategoryService`, `ChangeService` + Repositories); Medien (`MediaService`, `MediaRepository`); Artikel mit Quellen, Kategorien und Revisionen (`PostService`, `PostRepository`); Queue mit Reservierung (`QueueService`, `QueueRepository`); `Seeder` (Startinhalte) |
| `src/Site/` | `SiteData` (Live-Stand einmal pro Lauf geladen), `Builder` (`build()` mit `GET_LOCK`, Sammeln im Speicher, Schreiben, Medienkopie, Löschen über das Manifest; `assertWritable`), `Assets` (Theme-Dateien als `assets/<name>.<hash>.<ext>`), `Manifest`, `ContentRenderer` (Artikel-HTML → responsive `<picture>`, Galerien, Zwei-Klick-Einbettungen, Beleg-Fußnoten, h2-IDs, Kurz-gesagt-Titel; nur DOM-API), Template-Helfer in `functions.php` (`e`, `datum`, `seitenUrl`, `varianten`, `picture`); Suchindex, Feed, Sitemap folgen |
| `src/Util/` | `Env`, `Log`; `Sanitizer` + `SanitizeResult` (HTML-Whitelist, ADR-010); `HttpClient` + `HttpResponse` (einziger Weg nach außen, SSRF-Schutz, ADR-011); `ImageProcessor` + `ImageResult` (Bildvarianten, EXIF-Entfernung); `Text` (Slug, Wortzahl, Klartext); `Markdown` (Dokumentseiten → HTML, durch den Sanitizer); `FormCheck` + `FormCheckResult` (reine Formprüfung, ADR-012); Zeit folgt |
| `templates/` | PHP-Templates der Website: `layout.php` (Kopf, Fuß, Skripte) plus je Seitentyp eine Vorlage (`home.php`, `404.php`), die nur den Inhalt von `<main>` liefert |
| `theme/` | `style.css`, `site.js`, `suche.js`, `artikel.js` (Builder legt sie hash-benannt unter `assets/` ab) |
| `defaults/` | Startinhalte als Markdown: `editorial_guidelines`, `preferences`, `about`, `imprint`, `privacy` (`Domain/Seeder` legt sie an) |
| `migrations/` | nummerierte SQL-Dateien; 001–009: Einstellungen, Dokumente, Kategorien, Medien, Artikel mit Quellen, Queue, Änderungsvorschläge, Statistik, Audit (Spalten: [`models.md`](models.md)) |
| `storage/` | `manifest.json` (vom Builder geschriebene Dateien), `media/<uid>/` (`original.<ext>` und Varianten `<w>.webp` + `<w>.jpg`/`.png`), `logs/` — nur auf dem Server und lokal, nicht im Git |

## Faustregeln

- Fachliche Funktion? → zuerst `Domain/<X>Service.php`, dann als Tool in `Mcp/Tools/<X>Tools.php`; die API ruft denselben Service.
- Kennt die Funktion HTTP? → gehört in `Mcp/`, `Api/` oder `OAuth/`, nie in `Domain/`.
- Erzeugt etwas eine öffentliche Datei? → nur `Site/` schreibt in die Wurzel, und nur über das Manifest.
- Neue Seitenart? → `Builder::collect<Seitentyp>()` + `templates/<seitentyp>.php` + Pfad in `builder_top_level` (`config.php`).
- Öffentlich sichtbare Daten ändern ohne Login? → wird ein Änderungsvorschlag (`Domain/ChangeService.php`), keine direkte Änderung.
