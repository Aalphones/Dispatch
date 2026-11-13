# Code-Map — DISPATCH

Feature → Ordner. Bewusst grob, keine Zeilennummern. Gerüst (Bootstrap, Http, Env, Log, Brücke, Dev-Router) steht seit Meilenstein 0; übrige Ordner entstehen mit ihren Meilensteinen.

## Namensschema (erst raten, dann suchen)

Eine Domäne `<x>` (post, source, media, queue, category, document, change, setting, stats, token, reaction, schedule) heißt in jeder Schicht gleich:

| Schicht | Datei |
|---|---|
| Fachlogik | `app/src/Domain/<X>Service.php` |
| Datenbankzugriff | `app/src/Domain/<X>Repository.php` |
| MCP-Tools | `app/src/Mcp/Tools/<X>Tools.php` |
| Redaktions-API | `app/src/Api/<X>Controller.php` |
| Migration | `app/migrations/NNN_<x>.sql` |
| Redaktionsmodus | `app/theme/redaktion/<x>.js` |

Seitentypen der öffentlichen Website: `app/templates/<seitentyp>.php` (home, article, category, categories, tag, search, preview, consent, document, 404) und `app/templates/partials/` (card, pagination, breadcrumbs); Aufbau je Seitentyp: `Builder::collect<Seitentyp>()`. Der Inhalt einer Artikelseite kommt nur aus `Builder::articleMain` (Bau und Vorschau, `Builder::previewMain`); `vorschau/index.html` ist eine leere Hülle (`collectPreview`), `theme/vorschau.js` füllt sie. Ebenso ist `anmelden/zustimmung/index.html` die Gast-Hülle der OAuth-Zustimmung (`collectConsent`, `consent.php`); `theme/redaktion/zustimmung.js` füllt sie nach dem Login.

## Repo → Server

| Im Repo | Auf dem Server (`www/`) | Inhalt |
|---|---|---|
| `app/` | `dispatch-app/` | gesamter PHP-Code, Templates, Theme-Quellen, Startinhalte, Migrationen, `.env`, `storage/` — gesperrt (ADR-002) |
| `bridge/` | `dispatch/` | einziger PHP-Einstieg: `index.php` + `.htaccess` |
| `web-root/.htaccess` | `.htaccess` | Wurzel-Regeln (ADR-003), leitet Feed und Sitemap an die Brücke (ADR-029) |
| — | `index.html`, `artikel/`, `assets/`, `media/`, … | vom Builder erzeugt, nie hochgeladen |
| `bin/dispatch` | — | CLI, lokal (`hash-password`, `migrate`, `seed`, `demo`, `build`, `token:create|list|revoke` und `oauth-client:create|list|revoke` und `category:create` auch mit `--remote` gegen den Server über `deploy.env`, `category:delete <slug> [--move-to=<slug>]` nur lokal, `reaction`) |
| `bin/demo/` | — | Demo-Daten für `demo`: Kategorien, Artikelmetadaten, Bildgenerator (`demo.php`) und Artikeltexte (`<name>.html`); nur lokal, nicht im Webroot |
| `deploy.cmd`, `deploy.env.example`, `deploy-backup/` | — | Deploy (ADR-005); Vorlage für `deploy.env`; Sicherung der Wurzel-`.htaccess` (nicht im Git) |
| `dev-router.php`, `dev.env.example` | — | lokaler Server `php -S` mit Brücke und Sperre, Vorlage für `app/.env.local` (ADR-006) |

## Ordner in `app/`

| Ordner | Aufgabe |
|---|---|
| `bootstrap.php` | Autoloader, `.env` laden, Fehler-Handler; liefert die Konfiguration |
| `index.php` | registriert alle Routen am `Http\Router`: `/dispatch/mcp` → Mcp, `/dispatch/api/*` → Api (Tabelle: [`routes.md`](routes.md)); ruft vor dem Router `ScheduleTrigger::run`; dazu die `/.well-known/oauth-*`-Discovery-Pfade und `/dispatch/oauth/{authorize,token,revoke}` |
| `src/Http/` | `Router` (Methode + Pfad mit `{id}`/`{slug}`, 405/404), `Request` (`body()`, `json()`, `cookie()`, `ip()`), `Response` (`json`, `raw`, `empty`, `error`, `cookie`) |
| `config.php` | Basis-URL, Pfade, Formvorgaben, reservierte Namen der obersten Ebene |
| `src/Mcp/` | `McpController` (Endpunkt, Dispatch, Services je Anfrage), `Protocol` (Versionserkennung, Header-Abgleich), `ProtocolError`, `Registry`/`Tool`/`ToolResult` (Tools, Scope- und Argumentprüfung), `Schema` (Hilfen für JSON-Schemas der Tools), `ImageBlocks` (Bild-Blöcke, höchstens 6, Kante ≤ 1024 px), `Resources` (`dispatch://`-Dokumente, Style-Guide, Artikel), `Prompts` (drei Redaktions-Prompts), `Tools/<X>Tools.php` (`BriefingTools`, `DocumentTools`, `CategoryTools`, `SettingTools`, `ChangeTools`, `QueueTools`, `PostTools`, `MediaTools`, `StatsTools`, `SiteTools`) |
| `mcp/` | Texte für Agenten als Dateien: `instructions.md`, `style-guide.md` (Artikel-HTML), `workflow-scheduled.md`, `workflow-queue.md`, `workflow-returned.md` (vom `get_editorial_brief` je nach Modus mitgeliefert) |
| `src/OAuth/` | OAuth 2.1 für die Claude-Apps (ADR-024): `Discovery` (Metadaten-Dokumente, `WWW-Authenticate`-Hinweis), `DiscoveryController`, `OAuthError`; `ClientRegistry` (CIMD mit Allowlist und Zwischenspeicher, Rückfall-Clients, Rückadressen-Prüfung inkl. Loopback), `OAuthRepository` (`oauth_clients`, `oauth_codes`), `AuthorizeController` (`/authorize`), `Redirect`; `Consent` (Anfrage beschreiben, erlauben/ablehnen), `TokenIssuer` (Code-Tausch, Refresh mit Rotation, Widerruf, `doa_`-Prüfung, verbundene Apps), `TokenController` (`/token`, `/revoke`) |
| `src/Api/` | Redaktions-API-Controller, Deploy-Endpunkt (`DeployController`), Token-Endpunkt für `--remote` (`TokenController`), Anmeldung des Inhabers (`AuthController`); Freigabe und Übersicht (ADR-021): `OverviewController`, `PostController`, `ChangeController`, `PreviewController`; Queue-Upload, Kategorien, Reaktionen, Statistik: `QueueController`, `CategoryController`, `ReactionController`, `StatsController`; Zähl-Beacon (ADR-022): `HitController`; Zugänge per Anmeldung in `TokenController`, Zustimmung und verbundene Apps (M5): `OAuthController`, Veröffentlichungsplan (ADR-028): `ScheduleController` (Plan lesen, Tageslimit), `ScheduleTrigger` (Fälligkeitsprüfung bei jeder Anfrage an die Brücke), `FeedController` (Feed und Sitemap durch die Brücke, ADR-029); dazu `Rebuild` (Neubau nach der Aktion) und `ApiErrors` (Fehler → Status) |
| `src/Auth/` | Deploy-Token (`DeployToken`), MCP-Anmeldung (`McpAuth`), Scopes (`Scopes`); Anmeldung des Inhabers (ADR-020): `Jwt` (HS256), `OwnerSession` (Login, Refresh-Rotation, Abmelden), `LoginThrottle` (Login-Bremse), `ApiAuth` (`owner`, `ownerOrDeploy` für die API), `PreviewLink` (signierte Vorschau-Links) |
| `src/Db/` | PDO-Verbindung (`Connection`), `Migrator`, `Transaction::run`/`join` (nur DML), `Like` (LIKE-Muster mit wörtlichem `%` und `_`) |
| `src/Domain/` | Services und Repositories je Domäne; `Actor` (Akteur), `DomainError` (fachlicher Fehler), `AuditRepository` (Protokoll); Einstellungen, Dokumente, Kategorien, Änderungsvorschläge (`SettingService`, `DocumentService`, `CategoryService`, `ChangeService` + Repositories); Medien (`MediaService`, `MediaRepository`); Artikel mit Quellen, Kategorien und Revisionen (`PostService`, `PostRepository`); Queue mit Reservierung (`QueueService`, `QueueRepository`); MCP-Tokens (`TokenService`, `TokenRepository`); Reaktionen „Mehr davon / Weniger“ (`ReactionService`, `ReactionRepository`); Zugriffsstatistik lesen (`StatsService`) und zählen (`StatsRecorder`, Salt-Datei `storage/stats/salt.json`), beides über `StatsRepository`; Kennzahlen der Website (`SiteInfoService`); Briefing mit Rotationswert (`BriefingService`, `BriefingRepository`); Veröffentlichungsplan (ADR-028): `ScheduleService` (Termine, Plan, `releaseDue`), `ScheduleRepository`, `ScheduleClock` (`storage/schedule/next.json`); `Seeder` (Startinhalte) |
| `src/Site/` | `SiteData` (Live-Stand einmal pro Lauf geladen), `Builder` (`build()` mit `GET_LOCK`, Sammeln im Speicher, Schreiben, Medienkopie, Löschen über das Manifest; `assertWritable`), `Assets` (Theme-Dateien als `assets/<name>.<hash>.<ext>`), `Manifest`, `ContentRenderer` (Artikel-HTML → responsive `<picture>`, Galerien, Zwei-Klick-Einbettungen, Beleg-Fußnoten, h2-IDs, Kurz-gesagt-Titel; nur DOM-API), Template-Helfer in `functions.php` (`e`, `datum`, `seitenUrl`, `varianten`, `picture`); `SearchIndex` (JSON-Suchindex nach Kontrakt, Ablage `assets/suchindex.<hash>.json`); `Seo` (Theme-Farbe, OpenGraph, JSON-LD, Vorschaubild `shareImage`), `Feed` (RSS 2.0, `absolut()` in `functions.php`), `Sitemap`; `robots.txt` entsteht in `Builder::collectTechnical()` |
| `src/Util/` | `Env`, `Log`; `Sanitizer` + `SanitizeResult` (HTML-Whitelist, ADR-010); `HttpClient` + `HttpResponse` (einziger Weg nach außen, SSRF-Schutz, ADR-011); `ImageProcessor` + `ImageResult` (Bildvarianten, EXIF-Entfernung); `Text` (Slug, Wortzahl, Klartext); `Markdown` (Dokumentseiten → HTML, durch den Sanitizer); `FormCheck` + `FormCheckResult` (reine Formprüfung, ADR-012); `Diff` (Block- und Wortvergleich für die Änderungsansicht, ADR-021); Zeit folgt |
| `templates/` | PHP-Templates der Website: `layout.php` (Kopf, Fuß, Skripte) plus je Seitentyp eine Vorlage (`home.php`, `404.php`), die nur den Inhalt von `<main>` liefert |
| `theme/` | `style.css`, `site.js` (Kürzel, Nachladen, `--nav-h`, lädt `zaehler.js`), `suche.js` (Vorschläge + Suchseite, lädt den Suchindex, meldet Begriffe als `dispatch:suche`), `zaehler.js` (Zähl-Beacons, ADR-022), `artikel.js` (Inhaltsverzeichnis, Fokus, Lightbox, Videos), `vorschau.js` (füllt die Vorschau-Hülle); Builder legt sie hash-benannt unter `assets/` ab. Login-Dialog und Laden des Redaktionsmodus stehen in `site.js` |
| `theme/redaktion/` | Redaktionsmodus, nur nach Login geladen (ADR-023): `schedule.js` (Freigeben mit Zeitpunkt, „Geplant“ auf der Startseite, Dialog „Veröffentlichungsplan“, Leiste geplanter Artikel), `start.js` (`start(token?)`), `sitzung.js` (Token, BroadcastChannel, Refresh, Abmelden), `api.js` (`api`, `upload` mit Fortschritt), `ui.js` (`el`, `toast`, `dialog`, `rahmen`, `info`, `icon`, Fehlerzeilen), `konto.js` (Konto-Knopf, Menü, Knopf „Thema einreichen“, lädt die Dialoge erst beim Öffnen), `post.js` (Vorschau-Seite: Freigabe-Leiste, Freigeben, Zurückgeben, Verwerfen, Änderungsansicht; Startseite: „Wartet auf dich“, Aufmacher; Artikelseite: Redaktionskasten, Zurückziehen, neue Fassung), `kommentar.js` (Kommentarmodus, Anmerkungen an Stellen), `change.js` (Vorschlagskarten), `reaction.js` (Mehr davon / Weniger, gemeinsamer Zustand), `stats.js` (Kennzahl, Zahlen, Funken, Statistik-Dialog), `category.js` (Interesse 0–5 auf der Kategorien-Seite, Interessen-Dialog mit „Neue Kategorie“, „Umbenennen“ und „Löschen“ samt Ersatz-Auswahl), `queue.js` (Dialog „Thema einreichen“), `token.js` (Dialog „Zugänge für Agenten“ mit den Gruppen „Zugänge mit Token“ und „Verbundene Apps“, „Neuer Zugang“), `zustimmung.js` (Zustimmungsseite: Anfrage lesen, Rechte wählen, Erlauben/Ablehnen, Abweisungs-Zustände), `redaktion.css`; `start.js` wählt je `data-seite` (`start`, `artikel`, `kategorien`, `kategorie`, `tag`, `vorschau`, `zustimmung`) das Modul, eingefügte Elemente tragen `data-red` und fallen beim Abmelden weg; Builder legt den ganzen Ordner als `assets/redaktion-<hash>/` ab (`Assets`) |
| `defaults/` | Startinhalte als Markdown: `editorial_guidelines`, `preferences`, `about`, `imprint`, `privacy` (`Domain/Seeder` legt sie an) |
| `migrations/` | nummerierte SQL-Dateien; 001–013: Einstellungen, Dokumente, Kategorien, Medien, Artikel mit Quellen, Queue, Änderungsvorschläge, Statistik, Audit, Tokens, Reaktionen, Login-Fehlversuche, Besucher-Pfade der Statistik (Spalten: [`models.md`](models.md)) |
| `storage/` | `manifest.json` (vom Builder geschriebene Dateien), `media/<uid>/` (`original.<ext>` und Varianten `<w>.webp` + `<w>.jpg`/`.png`), `logs/`, `schedule/next.json` (nächste Fälligkeitsprüfung, ADR-028) — nur auf dem Server und lokal, nicht im Git |

## Faustregeln

- Fachliche Funktion? → zuerst `Domain/<X>Service.php`, dann als Tool in `Mcp/Tools/<X>Tools.php`; die API ruft denselben Service.
- Kennt die Funktion HTTP? → gehört in `Mcp/`, `Api/` oder `OAuth/`, nie in `Domain/`.
- Erzeugt etwas eine öffentliche Datei? → nur `Site/` schreibt in die Wurzel, und nur über das Manifest.
- Neue Seitenart? → `Builder::collect<Seitentyp>()` + `templates/<seitentyp>.php` + Pfad in `builder_top_level` (`config.php`).
- Öffentlich sichtbare Daten ändern ohne Login? → wird ein Änderungsvorschlag (`Domain/ChangeService.php`), keine direkte Änderung.
