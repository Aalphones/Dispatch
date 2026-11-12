# Feuilleton – Konzept für eine MCP-first Blog-Engine

Oct 31, 2026 · @Sascha

## Zielbild und Prämissen

Feuilleton ist eine persönliche Blog-Engine in PHP, die wie ein Verlag arbeitet, nicht wie eine Redaktion. Ein KI-Agent in Claude Code recherchiert, prüft und schreibt; über einen MCP-Server reicht er fertige Artikel ein. Derselbe Server steht per OAuth auch in der Claude-App bereit, auch auf Android, etwa um unterwegs Themen einzureichen. Die App prüft nur die Form und legt sie dir zur Freigabe vor. Die öffentliche Website besteht ausschließlich aus statischen Dateien, die PHP im Hintergrund erzeugt. Ein Login per JavaScript direkt auf der Website zeigt dir alles, auch Unveröffentlichtes. Öffentlich wird etwas erst, wenn du es direkt am Eintrag freigibst.

Ein Artikel entsteht auf einem von zwei Wegen:

- **Geplant:** Eine wiederkehrende Claude-Aufgabe wählt anhand der hinterlegten Interessen ein Thema, recherchiert, schreibt und legt den Artikel zur Freigabe vor.
- **Auf Zuruf:** Du reichst ein Thema in die Queue ein, mit Text, Links, Fotos, Videos oder Audio. Der nächste Agentenlauf nimmt es zuerst und recherchiert es in der Tiefe.

**Leitprinzipien**

1. **MCP-first.** Jede fachliche Funktion gibt es zuerst als MCP-Tool. Redaktions-API und MCP rufen dieselben Service-Klassen auf. Bewusste Ausnahmen, nur für dich: Login, Token-Verwaltung, die OAuth-Zustimmung und die Freigabe.
2. **Verlag, nicht Redaktion.** Recherche, Faktenprüfung und Bildauswahl erledigt der Einreicher in Claude Code. Die App verlangt die Pflichtangaben (Quellen, Belege, Bildlizenzen) in der richtigen Form, prüft aber keine Inhalte. Für die Qualität ist der Einreicher verantwortlich; die letzte Kontrolle hast du mit der Freigabe.
3. **Statisch ausliefern.** Besucher bekommen nur fertige Dateien; PHP sieht von ihnen höchstens den anonymen Zähl-Aufruf der Statistik. Seiten rendert PHP nur, wenn sich etwas ändert. Das bringt Tempo, gute SEO-Werte und eine minimale Angriffsfläche.
4. **Nichts geht ohne Freigabe online.** Das gilt für Artikel und für alles andere Öffentliche: Bildnachweise, Pflichtseiten, Kategorienamen, Blogtitel.
5. **Shared-Hosting-tauglich.** Keine Hintergrundprozesse, kein Node, keine Composer-Abhängigkeiten zur Laufzeit, kein Server-Cron. Die Zeitsteuerung übernimmt Claude.
6. **Ein Konto, Eingaben gelten als unsicher.** Es gibt genau ein Konto, dessen Zugangsdaten beim Deployment gesetzt werden. Eingereichtes HTML filtert ein Whitelist-Sanitizer, jede Änderung erzeugt eine Revision, Zugriffsrechte sind nach Scopes getrennt, und kein MCP-Token kann etwas veröffentlichen.

**Nicht in Version 1:** mehrere Autor:innen, Kommentare, Newsletter, Bezahlschranke, zeitversetztes Veröffentlichen, weitere Konten.

## Architekturüberblick

Das System hat genau einen PHP-Einstiegspunkt (`_app.php`) für drei Schnittstellen: MCP, OAuth und die Redaktions-API. Alle drei rufen dieselbe Domänenschicht auf. Eine Admin-Oberfläche gibt es nicht; Login, OAuth-Zustimmung und Redaktionsfunktionen stecken als JavaScript in der statischen Website. Nach jeder Freigabe baut der Site-Builder die Website neu und schreibt sie als statische Dateien in den Document Root.

&#91;embedded content: Architektur · MCP, OAuth, Redaktions-API, statische Ausgabe\]

Agent und Redaktionsmodus sprechen mit PHP. Besucher bekommen nur die Dateien, die der Builder nach jeder Änderung in `public/` schreibt.

| Komponente | Erreichbar unter | Aufgabe |
| --- | --- | --- |
| MCP-Server | `POST /mcp` | JSON-RPC über Streamable HTTP, Tool-Registry, Bearer-Token (OAuth oder MCP-Token) mit Scope-Prüfung pro Tool |
| OAuth-Server | `/oauth/*`, `/.well-known/*` | Discovery, Client-Metadaten (CIMD), Autorisierung mit Zustimmung über deinen Login, Token-Ausgabe |
| Redaktions-API | `/api/*` | JSON-API für den Redaktionsmodus: Login per JWT, OAuth-Zustimmung, Vorschau, Freigabe, Tokens, Queue-Uploads |
| Redaktionsmodus | `site.js` → `redaktion.js` | Login, Zustimmung und Funktionen nach der Anmeldung, im Browser; wird nur bei Bedarf geladen |
| Domänen-Services | – | Posts, Sources, Media, Queue, Categories, Documents, Settings, Änderungsvorschläge, Stats |
| Sanitizer und Formprüfung | – | HTML-Whitelist beim Speichern, Pflichtangaben vor Einreichen und Freigabe |
| Site-Builder | – | Rendert nur freigegebene Stände, kopiert verwendete Medien, schreibt Feed, Sitemap, Suchindex |
| Speicher | außerhalb des Document Root | MySQL-Datenbank bei Strato; Medien-Originale und Build-Manifest in storage/ |
| Öffentliche Website | `/`, `/artikel/…` | Reine HTML-, CSS- und JS-Dateien, ausgeliefert von Apache |
| Statistik-Zähler | POST /api/hit | Anonymer Zähl-Aufruf der Besucher:innen, antwortet sofort mit 204 |

Ausgehend gibt es zwei Verbindungen: Beim Medienimport lädt der Server die angegebene Datei, und bei der OAuth-Anmeldung lädt er das Client-Metadatendokument von Claude. Beide laufen über einen HTTP-Client mit SSRF-Schutz (siehe Sicherheitsabschnitt). Recherche findet ausschließlich beim Einreicher statt.

## Betrieb auf Strato Shared Hosting

Der Blog läuft auf einer eigenen Subdomain (z. B. `blog.example.de`), deren Ziel in der Strato-Domainverwaltung auf den Ordner `public/` zeigt. Code und Daten liegen darüber und sind damit nicht per Web erreichbar. Die Subdomain ist nötig, weil die OAuth-Discovery unter `/.well-known/` an der Domain-Wurzel liegen muss; außerdem trennt sie das Login-Cookie sauber von anderen Projekten.

```
feuilleton/                 Webspace-Ordner
├── app/                    PHP-Code (ohne Framework)
│   ├── config.php          base_url, Pfade, Formvorgaben
│   ├── src/                Mcp/, Auth/, Content/, Site/, Api/, Util/
│   ├── templates/          PHP-Templates der Website
│   ├── theme/              style.css, site.js, redaktion.js, redaktion.css
│   └── defaults/           Startinhalte: Richtlinien, Präferenzen, Impressum …
├── storage/                privat: media/, logs/
└── public/                 Document Root der Subdomain
    ├── .htaccess           Rewrites, Header, Caching
    ├── _app.php            einziger PHP-Einstieg
    └── …                   generiert: index.html, artikel/, thema/, media/, assets/ …
```

**`.htaccess`, die Kernregeln**

- Nur `/mcp`, `/api/*`, `/oauth/*` und `/.well-known/oauth-*` werden auf `_app.php` umgeschrieben; alles andere liefert Apache direkt aus.
- Den `Authorization`-Header an PHP durchreichen: `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`. PHP als CGI/FastCGI verliert ihn sonst, und jede MCP- und API-Anfrage scheitert mit 401. PHP liest zusätzlich `REDIRECT_HTTP_AUTHORIZATION`.
- Zugangsdaten per `SetEnv` (siehe Weitere Festlegungen). Apache liefert `.htaccess` nie aus.
- `ErrorDocument 404 /404.html`, `Options -Indexes`, `DirectoryIndex index.html`.
- Sicherheits-Header: `X-Content-Type-Options`, `Referrer-Policy` und eine Content-Security-Policy mit `script-src 'self'` und `connect-src 'self'`, ohne Inline-Skripte und ohne Fremdskripte (Ausnahme: `frame-src` für youtube-nocookie.com und player.vimeo.com).
- Lange Cache-Zeiten für `/assets/` und `/media/` (Dateien tragen Versions-Hashes), kurze für HTML.

**Technische Annahmen und Prüfpunkte vor dem Start**

| Prüfpunkt | Warum | Wie prüfen |
| --- | --- | --- |
| PHP 8.3 aktiv | Strato bietet 8.0 bis 8.3 an; 8.1+ wird vorausgesetzt | Kunden-Login → PHP-Version einstellen |
| `pdo_mysql` und eine MySQL-Datenbank | Datenbank aus dem Strato-Paket | Kunden-Login → Datenbankverwaltung; Verbindung im Testskript |
| `curl`, `openssl`, `fileinfo`, `mbstring`, `intl`, `dom` | Medienimport, Tokens, MIME-Erkennung, Texte, Sanitizer | `info.php` |
| GD mit WebP, optional EXIF | Bildvarianten, Drehung nach EXIF | `info.php`, Abschnitt gd |
| Authorization-Header kommt an | MCP- und API-Anmeldung | Testskript gibt den Header zurück |
| `SetEnv` in `.htaccess` wirkt | Zugangsdaten per Umgebungsvariable | Testskript liest `getenv('FEUILLETON_USER')` 🟡 |
| Ausgehendes HTTPS | Medienimport per URL, Abruf der Client-Metadaten von Claude | Testskript lädt eine externe Datei |
| `upload_max_filesize`, `post_max_size`, `memory_limit`, `max_execution_time` | Video-Uploads, Bildverarbeitung, Rebuild | `info.php`; anpassen per `.user.ini`, falls Strato das zulässt 🟡 |
| SSL-Zertifikat für die Subdomain | OAuth, Login und Tokens nur über HTTPS | Kunden-Login → SSL-Verwaltung |

**Weitere Festlegungen**

- **Zugangsdaten per Umgebungsvariable:** `FEUILLETON_USER`, `FEUILLETON_PASSWORD_HASH` und `FEUILLETON_JWT_SECRET` (mindestens 32 Zufallsbytes). Gespeichert wird nur der Passwort-Hash, nie das Passwort; die CLI erzeugt ihn mit `bin/feuilleton hash-password`. Gesetzt per `SetEnv` in der `.htaccess`. Wirkt das bei Strato nicht 🟡, liest die App dieselben Werte aus einer `.env` außerhalb des Document Root.
- **MySQL-Datenbank von Strato.** Angelegt in der Strato-Datenbankverwaltung, Zugriff über PDO (`pdo_mysql`) mit `utf8mb4` und InnoDB, Transaktionen für alles, was mehrere Tabellen ändert. Zugangsdaten per Umgebungsvariable: `FEUILLETON_DB_HOST`, `FEUILLETON_DB_NAME`, `FEUILLETON_DB_USER`, `FEUILLETON_DB_PASSWORD`. Keine Datenbankdatei im Dateisystem.
- **Kein Server-Cron.** Die Zeitsteuerung liegt bei Claude, der Build läuft synchron in der Anfrage, die eine Freigabe auslöst. Ein Rebuild für einige hundert Artikel liegt im Sekundenbereich.
- **Kein Streaming.** Der MCP-Server antwortet immer mit `application/json`; Server-Sent Events sind nicht nötig und auf Shared Hosting oft unzuverlässig.
- **Deployment** per SFTP; mit SSH-fähigem Paket optional per `git pull`. Updates überschreiben `app/` und `public/_app.php`, nie `storage/`, `config.php` oder die Zugangsdaten.
- **Backup:** Gesichert werden die Datenbank (Strato-Backup oder Dump per mysqldump) und `storage/media/`. Damit ist alles wiederherstellbar; `public/` entsteht per Rebuild neu.

## Datenmodell

Die Datenbank liegt in MySQL bei Strato (InnoDB, `utf8mb4`) und hat 20 Tabellen. Migrationen sind nummerierte SQL-Dateien, ihr Stand steht in einer Tabelle `schema_migrations`. Medien liegen als Dateien unter `storage/media/{uid}/`, die Datenbank hält nur Metadaten. Zeitstempel als `DATETIME` in UTC, Listen und Snapshots als `JSON`-Spalten.

| Tabelle | Zweck | Wichtige Felder |
| --- | --- | --- |
| `posts` | Artikel | `slug` (eindeutig), `title`, `subtitle`, `kicker`, `excerpt`, `content_html` (bereinigt), `status` (draft, review, published, archived), `tags`, `layout`, `accent`, `cover_media_id`, `seo_title`, `seo_description`, `origin` (scheduled, queue, manual), `queue_item_id`, `editor_notes` (internes Rechercheprotokoll), `review_note` (deine Anmerkung bei Rückgabe), `ai_assisted`, `word_count`, `revision` (Arbeitsstand), `published_revision` (Live-Stand, nur per Freigabe gesetzt), `published_at` |
| `post_revisions` | Versionsstände, eine davon ist die freigegebene Live-Fassung | `post_id`, `revision`, `snapshot` (vollständiges JSON inkl. Quellen und Kategorien), `actor` |
| `post_categories` | Kategorien eines Artikels | `post_id`, `category_id`, `is_primary` (genau eine Hauptkategorie, höchstens 3 insgesamt) |
| `sources` | Quellen je Artikel | `ref` (z. B. `destatis-2026`, eindeutig je Artikel), `position`, `kind`, `title`, `url`, `publisher`, `author`, `published`, `accessed`, `note` |
| `media` | Medienbibliothek | `uid` (`m` + 10 Hex-Zeichen), `kind` (image, video, audio, document), `mime`, `sha256` (Dubletten), `width`, `height`, `variants`, `alt`, `caption`, `credit`, `license`, `license_url`, `source_url`, `origin` (import, upload, owner) |
| `queue_items` | Themenaufträge | `title`, `brief`, `links`, `status` (open, claimed, review, done, failed, cancelled), `priority` (1 hoch bis 3), `depth` (standard, deep), `category_id`, `claimed_by`, `claim_expires`, `post_id`, `result_note` |
| `queue_media` | Anhänge eines Auftrags | `queue_item_id`, `media_id`, `note` |
| `categories` | Kategorien: Ressorts der Website und Interessengebiete des Agenten | `slug`, `name`, `description`, `parent_id`, `position`, `interest` (0 bis 5, 0 = nie von selbst), `keywords`, `avoid`, `active` |
| `documents` | Editierbare Texte | `key` (editorial\_guidelines, preferences, about, imprint, privacy), `title`, `content` (Markdown), `public_slug` |
| `document_revisions` | Versionsstand der Dokumente | `doc_key`, `content`, `note`, `actor` |
| `changes` | Änderungsvorschläge für öffentlich sichtbare Daten | `entity` (document, media, category, setting), `entity_key`, `payload` (neue Werte), `status` (pending, applied, rejected), `created_by`, `decided_at` |
| `settings` | Einstellungen als Schlüssel/Wert | siehe Liste unten |
| `oauth_clients` | Bekannte OAuth-Clients | `client_id` (CIMD-URL oder selbst angelegt), `secret_hash` (nur bei selbst angelegten), `name`, `redirect_uris`, `kind` (cimd, manual), `metadata`, `fetched_at`, `last_used_at` |
| `oauth_codes` | Laufende Anmeldungen und Autorisierungscodes | `request_id`, `code_hash`, `client_id`, `redirect_uri`, `challenge` (PKCE), `scope`, `resource`, `state`, `status` (pending, approved, used), `expires_at` |
| `tokens` | Alle Bearer- und Refresh-Tokens | `token_hash` (SHA-256), `kind` (mcp, oauth\_access, oauth\_refresh, owner\_refresh), `name`, `client_id`, `scope`, `family`, `expires_at`, `revoked`, `last_used_at` |
| `stats_daily` | Tageswerte je Seite | `day`, `path`, `post_id`, `views`, `visitors`, `engaged_seconds`, `reads_half`, `reads_full` |
| `stats_dimensions` | Aufschlüsselungen je Tag | `day`, `path`, `dimension` (referrer, utm, device, outbound, search), `value`, `count`, `extra` (z. B. Trefferzahl einer Suche) |
| `stats_visitors` | Entdoppeln am laufenden Tag | `day`, `visitor_hash`, `hits_last_hour`; wird täglich geleert |
| `audit_log` | Wer hat wann was geändert | `at`, `actor` (z. B. `oauth:Claude`, `mcp:claude-code`, `owner`), `action`, `target`, `details` |
| `login_attempts` | Bremse gegen Passwort-Raten | `ip`, `at` |

**Einstellungen (`settings`)** mit Startwerten, in drei Gruppen:

- **Öffentlich, Änderung per MCP nur als Vorschlag:** `site_title`, `site_tagline`, `site_description`, `author_name`, `author_url`, `accent` (#b3261e), `ai_disclosure` (ja), `ai_disclosure_text`.
- **Intern, per MCP direkt änderbar:** `language` (de), `locale` (de\_DE), `posts_per_page` (12), `queue_claim_minutes` (180).
- **Nur über deinen Login, nie per MCP:** die Formvorgaben `min_sources` (3), `min_sources_deep` (5), `min_words` (600), `min_words_deep` (1200), `require_cover` (ja), `require_image_license` (ja), dazu `publish_mode` (review) und `stats_enabled` (ja). Bis es einen Einstellungsdialog gibt, stehen sie in `config.php`.

Zugangsdaten und JWT-Schlüssel kommen aus Umgebungsvariablen, nicht aus der Datenbank. Intern gespeichert wird nur `last_build_at`.

**Medien-Ablage:** `storage/media/{uid}/original.{ext}` plus Bildvarianten `{breite}.webp` und `{breite}.jpg` (bzw. `.png` bei Transparenz) in den Breiten 480, 800, 1200, 1600 und 2000 px. In `public/media/{uid}/` landen beim Build nur Dateien, die ein veröffentlichter Artikel verwendet. Private Queue-Fotos werden so nie versehentlich öffentlich.

## MCP-Server

Der Server spricht Streamable HTTP unter `POST /mcp`, ist zustandslos und unterstützt zwei Protokoll-Generationen: die aktuelle Revision 2026-07-28 und ältere Clients mit `initialize`-Handshake. Er antwortet immer mit `application/json`; `GET` und `DELETE` auf `/mcp` beantwortet er mit 405.

### Protokoll

- **Modern (2026-07-28):** Kein Handshake, keine Session. Jede Anfrage trägt `_meta["io.modelcontextprotocol/protocolVersion"]` und die Header `MCP-Protocol-Version`, `Mcp-Method` und bei `tools/call`, `resources/read`, `prompts/get` zusätzlich `Mcp-Name`. Weichen Header und Body voneinander ab, antwortet der Server mit HTTP 400 und einem JSON-RPC-Fehler. `server/discover` liefert `supportedVersions`, `capabilities`, `serverInfo` und `instructions`. Listen und `resources/read` tragen `ttlMs` (300000) und `cacheScope: "private"`.
- **Legacy (2025-11-25, 2025-06-18, 2025-03-26):** `initialize` handelt die Version aus (angefragte Version, sonst 2025-11-25). Es wird keine `Mcp-Session-Id` vergeben.
- **Erkennung:** Methode `initialize` = Legacy. Version 2026-07-28 im Header oder in `_meta` = modern. Unbekannte Version = HTTP 400 mit der Liste der unterstützten Versionen.
- **Notifications** (ohne `id`) werden mit 202 ohne Body quittiert.
- **Fehler:** Protokollfehler nur bei kaputten Anfragen. Fachliche Fehler kommen als Tool-Ergebnis mit `isError: true` und einem Text, der sagt, wie es richtig geht, z. B. „Unbekanntes Thema „kunst“. Verfügbar: wissenschaft, technik …“.
- **Ergebnisse:** ein Textblock mit kompaktem JSON, bei Bildern zusätzlich Bild-Blöcke (höchstens 6 je Antwort, höchstens 1024 px Kantenlänge). So sieht der Agent Queue-Fotos und Bilder aus der Medienbibliothek selbst.
- **Annotationen:** Jedes Tool hat `title` sowie `readOnlyHint`, `destructiveHint`, `idempotentHint` und `openWorldHint`. Lesende Tools laufen dadurch in Claude ohne Rückfrage.
- **Rechte:** `tools/list` zeigt nur Tools, die der Token-Scope erlaubt. Fehlt ein Scope bei `tools/call`, folgt HTTP 403 mit `WWW-Authenticate: Bearer error="insufficient_scope", scope="…"`.

### Server-Anweisungen

Diese `instructions` liefert der Server bei `initialize` und `server/discover` mit:

```
Feuilleton ist das Redaktionssystem eines persönlichen Blogs. Beginne jede
Redaktionsaufgabe mit get_editorial_brief und folge dem dort beschriebenen
Workflow. Die Redaktionsrichtlinien sind verbindlich. Artikel entstehen als
Entwurf (create_post) mit Quellen und Belegen im Text; submit_post prüft sie
und legt sie zur Freigabe vor. Veröffentlichen kann nur der Mensch. Bilder
nur aus der Medienbibliothek und nur mit Urheber, Lizenz und Quelle.
```

### Tool-Katalog

| Gruppe | Tool | Zweck | Scope |
| --- | --- | --- | --- |
| Kontext | `get_editorial_brief(mode)` | Startpunkt jedes Laufs, `mode` = auto, scheduled oder queue: Richtlinien, Präferenzen, Kategorien mit Rotationswert, Formvorgaben, letzte 30 Artikel, Leserinteresse (Top-Artikel und -Kategorien, Suchen ohne Treffer), Queue-Status, zurückgegebene Entwürfe, Schritt-für-Schritt-Workflow für den Modus | read |
| Kontext | `get_style_guide()` | HTML-Komponenten, Beleg- und Mediensyntax, Layouts, Akzentfarben | read |
| Kontext | `list_documents()`, `get_document(key)` | Richtlinien, Präferenzen und öffentliche Seiten lesen | read |
| Kontext | `update_document(key, content, note)` | Interne Dokumente direkt ändern (mit Revision); öffentliche Seiten werden zum Änderungsvorschlag | admin |
| Kontext | `list_categories(include_inactive)` | Kategorienbaum mit Gewicht, Artikelzahl, letztem Artikel | read |
| Kontext | `save_category(…)` | Kategorie anlegen oder ändern: parent, interest (0 bis 5), keywords, avoid, active direkt; Name und Beschreibung einer öffentlichen Kategorie als Änderungsvorschlag | admin |
| Kontext | `get_site_info()`, `update_settings(settings)` | Einstellungen und Kennzahlen lesen bzw. ändern; öffentliche Werte als Vorschlag, geschützte Werte gar nicht | read / admin |
| Kontext | `list_changes(status)` | Eigene Änderungsvorschläge und ihren Status sehen | read |
| Queue | `list_queue(status)` | Aufträge nach Status | read |
| Queue | `add_to_queue(title, brief, links, attachments, priority, depth, category)` | Thema einreichen; Anhänge als URL, Base64 oder vorhandene Medien-UID, je mit `own_material` | write |
| Queue | `claim_queue_item(id?)` | Nächsten (oder bestimmten) Auftrag für 180 Minuten reservieren; liefert Briefing, Links, Anhänge, Fotos als Bild-Blöcke, signierte Links für Video und Audio | write |
| Queue | `complete_queue_item(id, post_id, note)` | Auftrag abschließen (passiert bei deiner Freigabe automatisch) | write |
| Queue | `release_queue_item(id, status, note)` | Zurückgeben (open) oder begründet abbrechen (failed, cancelled) | write |
| Artikel | `list_posts(status, category, tag, year, limit, offset)` | Artikel auflisten und filtern | read |
| Artikel | `search_posts(query)` | Volltextsuche für Dubletten-Check und interne Verlinkung | read |
| Artikel | `get_post(id or slug, include_html)` | Vollständiger Artikel mit Quellen, Kategorien und Vorschau-Link | read |
| Artikel | `create_post(…)` | Entwurf anlegen; Antwort: ID, Vorschau-Link, Sanitizer-Hinweise, Ergebnis der Formprüfung | write |
| Artikel | `update_post(id, …)` | Teiländerung mit Revision; bei veröffentlichten Artikeln entsteht ein neuer Arbeitsstand, die Live-Fassung bleibt bis zur Freigabe | write |
| Artikel | `validate_post(id)` | Formprüfung ohne Einreichen: Fehler, Warnungen, Kennzahlen | read |
| Artikel | `submit_post(id)` | Formprüfung und Einreichen zur Freigabe; bei Fehlern die Fehlerliste statt Einreichung | write |
| Artikel | `withdraw_post(id)`, `delete_post(id, confirm)` | Einreichung zurückziehen; nur Entwürfe löschen, mit `confirm: true` | write |
| Artikel | `list_post_revisions(id)`, `restore_post_revision(revision_id)` | Versionen ansehen und wiederherstellen | read / write |
| Medien | `import_media_from_url(url, alt, caption, credit, license, license_url, source_url)` | Datei serverseitig von einer URL laden; Urheber, Lizenz und Quelle gibt der Einreicher mit | write |
| Medien | `upload_media(data_base64, filename, alt, …)` | Eigene Diagramme oder Grafiken als PNG, JPEG oder WebP | write |
| Medien | `update_media(uid, …)`, `list_media(kind, query)`, `get_media(uid, view)` | Metadaten pflegen (bei Medien in veröffentlichten Artikeln als Änderungsvorschlag), Bibliothek durchsuchen, Bild ansehen | write / read |
| Statistik | `get_stats(from, to, group_by, filter)` | Tageswerte, gruppiert nach day, post, category, referrer, device, outbound oder search | read |
| Statistik | `get_post_stats(id or slug, from, to)` | Aufrufe, Besucher:innen, Lesezeit, Lesetiefe, Herkunft und Klicks eines Artikels | read |
| Website | `rebuild_site()` | Komplett neu bauen aus den freigegebenen Ständen, z. B. nach einem Theme-Update | write |

**Bewusst kein Tool:** Freigeben, Änderungsvorschläge übernehmen, öffentliche Artikel zurückziehen, MCP-Tokens anlegen und Formvorgaben ändern. Das geht nur eingeloggt über die Redaktions-API (siehe Redaktionsmodus im Frontend).

**Felder von `create_post`:** `title` (Pflicht), `excerpt` (Pflicht, 120 bis 160 Zeichen), `content_html` (Pflicht), `categories` (Pflicht, Liste von Slugs, die erste ist die Hauptkategorie, höchstens 3), `subtitle`, `kicker`, `tags` (höchstens 12), `layout` (standard, feature, longform, briefing), `accent` (Hex), `cover_media` (UID), `sources` (Liste mit `ref`, `title`, `url`, `publisher`, `author`, `published`, `accessed`, `kind`, `note`), `seo_title`, `seo_description`, `slug`, `editor_notes`, `origin`, `queue_item_id`. `update_post` nimmt dieselben Felder optional; `sources` und `categories` ersetzen die Liste vollständig. Der Slug veröffentlichter Artikel bleibt fest.

**Rotationswert im Briefing:** Gewicht der Kategorie (0 bis 5) × Tage seit dem letzten Artikel darin, gedeckelt bei 90 Tagen; Kategorien ohne Artikel zählen als 60 Tage, Gewicht 0 schließt eine Kategorie aus. Der Agent bevorzugt hohe Werte, ohne daran gebunden zu sein.

### Resources und Prompts

- **Resources:** `feuilleton://documents/{key}` (Markdown), `feuilleton://style-guide`, Vorlage `feuilleton://posts/{id}`.
- **Prompts:** `redaktion_geplant` (geplanter Artikel), `redaktion_queue` (nächsten Auftrag abarbeiten), `thema_einreichen(thema)`. Die Prompts enthalten denselben Workflow wie das Briefing. Clients ohne Prompt-Unterstützung verlieren dadurch nichts.

## Authentifizierung und Sicherheit

Es gibt genau ein Konto: deins. Benutzername und Passwort-Hash kommen beim Deployment aus Umgebungsvariablen, eine Registrierung gibt es nicht. Dein Login liefert ein JWT für die Redaktions-API. Für MCP gibt es zwei Wege, die beide als `Authorization: Bearer …` ankommen: OAuth 2.1 für die Claude-Apps (Android, Web, Desktop) und Claude Code, oder ein festes MCP-Token für Claude Code und geplante Aufgaben.

### MCP-Tokens

- Anlegen nur eingeloggt im Dialog „Zugänge“ und nach erneuter Passworteingabe: Name, Scopes, optionales Ablaufdatum.
- Das Token (Präfix `fmcp_`, 256 Bit zufällig) wird genau einmal angezeigt, mit Kopierknopf und fertigem `claude mcp add`-Befehl. Gespeichert wird nur sein SHA-256-Hash.
- Jede Nutzung aktualisiert `last_used_at`; jedes Token ist einzeln widerrufbar.

### OAuth 2.1 für die Claude-Apps

1. **401 mit Hinweis:** Eine Anfrage ohne gültiges Token erhält `401` und `WWW-Authenticate: Bearer resource_metadata="https://blog.example.de/.well-known/oauth-protected-resource/mcp"`. Claude startet die Anmeldung nur nach einem echten 401.
2. **Protected Resource Metadata (RFC 9728)** unter `/.well-known/oauth-protected-resource/mcp` und an der Wurzel: `resource` = exakt `https://blog.example.de/mcp`, `authorization_servers` = `["https://blog.example.de"]`, `scopes_supported`, `bearer_methods_supported: ["header"]`.
3. **Authorization Server Metadata (RFC 8414)** unter `/.well-known/oauth-authorization-server`: Endpunkte für authorize, token und revoke, `code_challenge_methods_supported: ["S256"]`, `token_endpoint_auth_methods_supported: ["none", "client_secret_post"]`, `client_id_metadata_document_supported: true`, `authorization_response_iss_parameter_supported: true`, Scopes inkl. `offline_access`. Bewusst ohne `registration_endpoint`.
4. **Keine Registrierung, nur bekannte Clients:** Claude identifiziert sich über ein Client ID Metadata Document (CIMD). Die `client_id` ist eine HTTPS-URL; der Server lädt das Dokument, prüft, dass dessen `client_id` exakt der URL entspricht, und übernimmt Name und Redirect-URIs. Zugelassen ist jede App mit gültigem Dokument (ChatGPT, Claude, …); die Schranke ist die Zustimmung des Inhabers (ADR-025). Claude wählt CIMD, wenn die Metadaten `client_id_metadata_document_supported` und die Methode `none` anbieten; sonst fiele es auf die Registrierung (DCR) zurück, die es hier nicht gibt.
5. **Rückfalloption 🟡:** Klappt CIMD nicht, legst du im Dialog „Zugänge“ einen OAuth-Client an (Client-ID und Secret, Redirect `https://claude.ai/api/mcp/auth_callback`) und trägst beides beim Hinzufügen des Connectors unter „Erweiterte Einstellungen“ ein.
6. **Redirect-URIs:** exakter Abgleich. Die Claude-Apps nutzen `https://claude.ai/api/mcp/auth_callback`. Claude Code nutzt `http://localhost` bzw. `http://127.0.0.1` mit wechselndem Port; bei Loopback wird der Port deshalb nicht verglichen.
7. **Zustimmung auf dem Blog:** `/oauth/authorize` prüft die Anfrage (Client, Redirect-URI, PKCE mit S256, `resource` passend zur MCP-URL), speichert sie 10 Minuten und leitet auf die statische Seite `/anmelden/zustimmung/?anfrage=…` weiter. Dort meldest du dich mit dem normalen JS-Login an und siehst Client-Name, gut sichtbar den Ziel-Host, die Scopes als Checkboxen und bei Loopback-Clients einen Warnhinweis. „Erlauben“ ruft `POST /api/oauth/consent` mit deinem JWT auf; die API antwortet mit der Weiterleitungs-URL, das Skript navigiert dorthin. „Ablehnen“ leitet mit `error=access_denied` zurück.
8. **Code und Token:** Der Code gilt 5 Minuten und nur einmal. Die Weiterleitung trägt `code`, `state` und `iss`; seit 2026-07-28 müssen Clients `iss` prüfen. `POST /oauth/token` nimmt `application/x-www-form-urlencoded` und liefert ein Access-Token (1 Stunde) und ein Refresh-Token (60 Tage).
9. **Refresh mit Rotation:** Jede Erneuerung entwertet das alte Refresh-Token mit 30 Sekunden Kulanz; eine spätere Wiederverwendung widerruft die ganze Familie. Ungültige Tokens ergeben `invalid_grant`. Alle OAuth-Endpunkte antworten deutlich unter 10 Sekunden.

### Scopes

| Scope | Erlaubt |
| --- | --- |
| `blog:read` | Alles Lesende: Briefing, Dokumente, Artikel, Medien, Queue, eigene Vorschläge, Statistik |
| `blog:write` | Entwürfe, Einreichen zur Freigabe, Medien, Queue-Aufträge, Rebuild |
| `blog:admin` | Richtlinien, Präferenzen, Kategorien, Einstellungen; Öffentliches nur als Vorschlag, Formvorgaben nie |
| `offline_access` | Refresh-Token für OAuth-Clients |

Veröffentlichen hängt an keinem Scope. Das kann weder ein MCP- noch ein OAuth-Token, nur dein Login.

### Dein Login

- `FEUILLETON_USER` und `FEUILLETON_PASSWORD_HASH` aus der Umgebung. Passwort ändern heißt: Hash neu setzen. Kein Einrichtungsdialog, keine Registrierung.
- Login-Bremse: höchstens 10 Fehlversuche je IP in 15 Minuten.
- Access-JWT (HS256, 15 Minuten, Schlüssel `FEUILLETON_JWT_SECRET`) nur im Arbeitsspeicher. Refresh-Cookie mit `HttpOnly`, `Secure`, `SameSite=Strict` und `Path=/api/auth`, 30 Tage gültig, bei jeder Nutzung erneuert.
- **Rotation ohne Fehlalarm:** Auf einer statischen Website bedeutet jeder Seitenwechsel einen Refresh, und mehrere Tabs können dasselbe Refresh-Token gleichzeitig vorlegen. Deshalb bleibt ein abgelöstes Refresh-Token noch 30 Sekunden gültig. Erst eine Wiederverwendung danach gilt als Diebstahl und widerruft die ganze Familie. Zusätzlich teilen offene Tabs das aktuelle JWT per `BroadcastChannel`; ein Tab, der es so bekommt, spart sich den Refresh.
- CSRF: Die API liest das JWT nur aus dem `Authorization`-Header, Cookies zählen dort nicht. Der einzige Cookie-Endpunkt `/api/auth/refresh` prüft zusätzlich `Origin` und verlangt den Header `X-Feuilleton: 1`.
- „Auf allen Geräten abmelden“ widerruft alle Refresh-Tokens.
- Phase 2: TOTP als zweiter Faktor.

### Schutz vor bösartigen Inhalten

- **HTML-Whitelist beim Speichern:** erlaubt sind semantische Elemente (p, h2 bis h6, Listen, Tabellen, figure, aside, section, details, blockquote, Inline-Auszeichnung). Entfernt werden `script`, `style`, `iframe`, `img`, `video`, Formulare, SVG, Event-Attribute, `style`-Attribute und unbekannte Klassen. `h1` wird zu `h2`. Links nur `http`, `https`, `mailto`, relativ oder Anker. Der Einreicher erhält jede Entfernung als Hinweis zurück.
- **Medien nur aus der Bibliothek:** Bilder, Videos und Einbettungen gehen nur über `data-media` bzw. `data-embed`. SVG, HTML und XML werden als Upload abgelehnt. Bilder werden neu kodiert, dabei verschwinden EXIF- und GPS-Daten.
- **SSRF-Schutz** beim Medienimport und beim Abruf von Client-Metadaten: nur http(s), DNS-Auflösung und Sperre privater und reservierter Adressen, Redirects manuell mit Neuprüfung (höchstens 4), Größenlimit, Zeitlimit.
- **Nachvollziehbarkeit:** Jede Änderung erzeugt eine Revision und einen Eintrag im Protokoll mit Akteur.
- **Signierte Links** (HMAC-SHA-256, mit Ablaufzeit) für Vorschauen und private Medien. Damit öffnest du Entwürfe aus der Benachrichtigung auch ohne Login.
- **Gleiche Herkunft, strenge CSP:** Der Redaktionsmodus läuft auf der öffentlichen Domain. Eine XSS-Lücke dort wäre deshalb eine Lücke im Redaktionszugang. Gegenmittel: `script-src 'self'` ohne Inline-Skripte und Fremdskripte, Sanitizer für jedes Artikel-HTML, JWT nur im Arbeitsspeicher, erneute Passworteingabe für neue Tokens. Inline-Styles erlaubt die CSP nur für die Akzentfarbe.

## Redaktions-Workflows

Ein Agentenlauf in Claude Code erledigt genau einen Artikel. Er recherchiert, prüft und schreibt selbst und reicht das Ergebnis über MCP ein. Reihenfolge: zuerst von dir zurückgegebene Entwürfe, dann offene Queue-Aufträge, dann geplante Themen. Alle Wege enden in derselben Formprüfung. Das Briefing (`get_editorial_brief`) liefert dem Agenten die passenden Schritte als Text, sodass der Workflow im Blog gepflegt wird und nicht im Prompt der geplanten Aufgabe.

&#91;embedded content: Agentenlauf · 3 Themenquellen, 1 Prüfschleife, deine Freigabe\]

Das Briefing wählt genau einen Weg. Danach laufen alle durch dieselbe Prüfschleife, bis die Formprüfung keine Fehler mehr meldet.

### Geplanter Artikel

1. **Briefing lesen:** `get_editorial_brief(mode="auto")`. Das Briefing nennt den Weg: zurückgegebener Entwurf, Queue-Auftrag oder geplantes Thema.
2. **Thema wählen:** Kategorien mit hohem Rotationswert bevorzugen; die Leserstatistik (gefragte Kategorien, Suchen ohne Treffer) ist ein Hinweis, keine Vorgabe. Mit `search_posts` und der Liste der letzten Artikel Dubletten ausschließen. Einen aktuellen Aufhänger der letzten 14 Tage suchen: Studie, Entscheidung, Veröffentlichung, Jahrestag.
3. **Recherchieren und prüfen** mit den Werkzeugen von Claude Code (Websuche, Seitenabruf): mindestens `min_sources` Quellen, Primärquellen zuerst, zentrale Zahlen aus zwei unabhängigen Quellen. Verworfenes und Offenes in `editor_notes` festhalten.
4. **Bebildern:** frei lizenzierte Bilder selbst recherchieren (z. B. Wikimedia Commons) und per `import_media_from_url` oder `upload_media` übergeben, mit Urheber, Lizenz, Lizenz-Link, Herkunftsseite und Alt-Text. Eigene Diagramme als PNG per `upload_media`.
5. **Schreiben:** `create_post` mit Quellen, Belegen im Text, Titelbild, passendem Layout und Akzentfarbe.
6. **Form prüfen:** `validate_post` meldet fehlende oder unstimmige Pflichtangaben; mit `update_post` ergänzen, bis keine Fehler mehr bleiben. Die inhaltliche Prüfung hat der Agent vorher selbst erledigt.
7. **Zur Freigabe einreichen:** `submit_post`. Abschlussbericht im Lauf: Titel, Vorschau-Link, Zahl der Quellen, offene Punkte.

### Auftrag aus der Queue

1. **Thema einreichen** (du): eingeloggt im Dialog „Thema einreichen“, optimiert fürs Handy, mit Text, Links, Fotos, Videos, Audio, Priorität und Tiefe. Oder in einem beliebigen Claude-Chat: „Setz das auf meine Blog-Queue“, dann ruft Claude `add_to_queue` auf.
2. **Reservieren:** `claim_queue_item` sperrt den Auftrag für 180 Minuten gegen doppelte Bearbeitung. Abgelaufene Reservierungen fallen automatisch zurück.
3. **Material sichten:** Fotos kommen als Bild-Blöcke, der Agent sieht sie also. Für Video und Audio gibt es signierte Links und Metadaten. Ob der Agent sie auswerten kann, hängt vom Client ab 🟡; eine kurze Inhaltsangabe im Briefing hilft zuverlässig.
4. **Tief recherchieren:** Bei `depth = deep` gelten `min_sources_deep` und `min_words_deep`.
5. **Eigenes Material verwenden:** Anhänge mit `own_material` tragen als Urheber deinen Namen und als Lizenz „Eigenes Material“. Sie werden erst öffentlich, wenn ein freigegebener Artikel sie nutzt.
6. **Schreiben, prüfen, zur Freigabe einreichen** wie oben, mit `queue_item_id`. Der Auftrag wechselt auf `review` und ist mit deiner Freigabe erledigt.
7. **Abbrechen statt abschwächen:** Lässt sich das Thema nicht sauber belegen, ruft der Agent `release_queue_item(status="failed", note=…)` mit Begründung auf. Ein dünner Artikel landet nie in deiner Freigabe.

### Zurückgegebener Entwurf

1. **Anmerkung lesen:** Das Briefing liefert den Entwurf mit deiner Anmerkung aus `review_note`.
2. **Gezielt nachbessern:** nur das ändern, was die Anmerkung verlangt, bei Bedarf nachrecherchieren und Quellen ergänzen.
3. **Prüfen und erneut einreichen:** `update_post`, `validate_post`, `submit_post`. Der Abschlussbericht sagt, was sich geändert hat.

### Freigabe: eingeloggt alles, öffentlich nur Freigegebenes

Jeder Artikel hat zwei Stände: den Arbeitsstand, an dem Agent und du arbeiten, und den Live-Stand, aus dem der Builder die öffentliche Website baut. Nur deine Freigabe macht einen Arbeitsstand zum Live-Stand. Kein Agent und kein MCP-Token kann etwas öffentlich machen.

- **Einreichen (Agent):** `submit_post` führt die Formprüfung aus und setzt den Status auf `review`. Öffentlich ändert sich nichts.
- **Eingeloggt siehst du alles:** Der Redaktionsmodus blendet Unveröffentlichtes in die Website ein. Die Startseite zeigt „Wartet auf Freigabe“, Entwürfe öffnen sich unter `/vorschau/?id=…` im echten Layout.
- **Freigabe direkt am Eintrag:** Oben auf dem Artikel sitzt eine Leiste mit Status, Ergebnis der Formprüfung und den Knöpfen „Freigeben“, „Zurück an den Agenten“ (mit Anmerkung) und „Verwerfen“. Hat ein veröffentlichter Artikel einen neuen Arbeitsstand, zeigt die Leiste die Änderungen gegenüber der Live-Fassung (Text, Quellen, Bilder) und „Änderungen freigeben“.
- **Freigeben** führt die Formprüfung erneut aus, setzt `published_revision` auf den aktuellen Stand und beim ersten Mal `published_at`, schließt einen verknüpften Queue-Auftrag ab und baut die Website neu.
- **Zurück an den Agenten** setzt den Status auf `draft` und speichert deine Anmerkung in `review_note`. Der nächste Lauf arbeitet zurückgegebene Entwürfe zuerst ab.
- **Auch alles andere Öffentliche wartet auf dich:** Ändert der Agent die Nachweise eines Mediums, das ein veröffentlichter Artikel nutzt, eine öffentliche Seite (Über, Impressum, Datenschutz), Name oder Beschreibung einer Kategorie oder einen öffentlichen Einstellungswert, entsteht ein Änderungsvorschlag statt einer Änderung. Die Redaktionsleiste zeigt Vorschläge mit Vorher und Nachher; „Übernehmen“ wendet sie an und baut neu. Interne Daten wie Richtlinien, Präferenzen und Kategorie-Gewichte ändert der Agent direkt.
- **Zurückziehen** eines öffentlichen Artikels geht direkt am veröffentlichten Artikel.
- **Rechte:** Freigeben, Übernehmen und Zurückziehen sind keine MCP-Tools. Sie laufen über die Redaktions-API und verlangen ein JWT aus deinem Login.
- **Benachrichtigung:** Die geplante Aufgabe endet mit dem Vorschau-Link. Ist ihre Benachrichtigung aktiv, landet er auf deinem Handy. Die Redaktionsleiste zählt offene Freigaben und Vorschläge.

`publish_mode = auto` bleibt als Einstellung für später, ist standardmäßig aus und nur über die Redaktions-API änderbar, nie per MCP.

## Qualität: Richtlinien für den Einreicher, Formprüfung in der App

Für die inhaltliche Qualität ist der Einreicher verantwortlich, also der Agent in Claude Code. Die App liefert ihm die Richtlinien aus, verlangt die Pflichtangaben in der richtigen Form und prüft keine Inhalte. Die letzte Instanz bist du mit der Freigabe.

### Redaktionsrichtlinien (Startfassung)

Das Dokument `editorial_guidelines` wird bei der Installation angelegt und ist danach frei editierbar, per MCP und später auch im Redaktionsmodus. Inhalt der Startfassung:

1. **Haltung:** Sorgfalt vor Tempo. Nachricht und Meinung getrennt, Wertendes in `section.opinion`. Kritisierte kommen zu Wort. Unsicheres wird als unsicher benannt. Keine erfundenen Zitate, Zahlen, Studien oder Quellen.
2. **Recherche:** aktueller Aufhänger; Primärquellen vor Berichten über Quellen; Zwei-Quellen-Prinzip für Kernfakten und alle Zahlen; Erscheinungsdatum jeder Quelle prüfen; Finanzierung und Interessen offenlegen; Studien nach Stichprobe, Methode, Peer-Review und Kausalität lesen; Rechercheprotokoll in `editor_notes`.
3. **Quellenrangfolge:** Originaldokumente und Daten → begutachtete Studien → Qualitätsmedien mit Redaktion → Fachportale und Verbände → Blogs, Foren, Social Media (nur als Hinweis oder als Zitat einer Person).
4. **Belegen:** Beleg direkt hinter der Aussage. Zitate kurz, wörtlich, belegt, Übersetzungen gekennzeichnet. Zahlen mit Einheit, Bezugsgröße und Zeitraum.
5. **Bilder:** nur mit klarer Lizenz, bevorzugt Wikimedia Commons. Keine Symbolbilder, die Falsches nahelegen. KI-Bilder nur gekennzeichnet. Keine Privatpersonen ohne Einwilligung, keine Kinder, keine Paparazzi-Fotos.
6. **Aufbau:** Dachzeile, Titel bis etwa 70 Zeichen ohne Clickbait, Unterzeile, Teaser mit 120 bis 160 Zeichen, Einstieg mit den W-Fragen. Ab etwa 1000 Wörtern „Das Wichtigste in Kürze“. Zwischenüberschriften alle 250 bis 400 Wörter, am Ende eine Einordnung.
7. **Sprache:** klar, aktiv, kurze Sätze, Fachbegriffe erklärt, keine Floskeln und keine Superlative ohne Beleg.
8. **Korrekturen:** offen, mit `update`-Box und Datum, nie stillschweigend.

Das Dokument `preferences` beschreibt dich als Leser:in: Hintergrund, Vorwissen, Interessen, Ton, gewünschte Länge, No-Gos, Rhythmus. Es startet als Vorlage mit Platzhaltern.

### Quellen und Belege

- Jede Quelle ist ein strukturierter Datensatz mit `ref`, `kind`, `title`, `url`, `publisher`, `author`, `published`, `accessed`, `note`.
- `kind` unterscheidet: web, news, study, official, dataset, book, interview, video, podcast, social, other. Die Website zeigt die Art als kleines Etikett.
- Im Text steht `<a data-cite="ref"></a>`, mehrere mit `data-cite="s1,s3"`. Der Renderer macht daraus nummerierte Fußnoten mit Sprungmarken und Rücklinks.

### Bilder und Lizenzen

- **Angaben liefert der Einreicher:** Urheber, Lizenz mit Link, Herkunftsseite und Alt-Text kommen mit dem Upload oder Import. Die App prüft nur, dass sie vollständig sind, nicht ob sie stimmen.
- **Pflichtangaben je Medium:** `alt` (bei Bild und Video), `credit`, `license`, `source_url` (außer bei eigenem Material). Damit lassen sich die Namensnennungspflichten gängiger CC-Lizenzen erfüllen: Urheber, Lizenz mit Link, Quelle.
- **Ausgabe:** Jede Abbildung zeigt Bildunterschrift und Nachweiszeile. Am Artikelende stehen alle Bild- und Mediennachweise gesammelt.

### Formprüfung (Validator)

`validate_post`, `submit_post` und deine Freigabe führen dieselbe Prüfung aus. Sie prüft, ob die Pflichtangaben vollständig und in sich stimmig sind, nicht ob sie wahr sind. Fehler blockieren, Warnungen nicht.

| Prüfung | Regel | Ergebnis |
| --- | --- | --- |
| Titel | vorhanden; über 90 Zeichen | Fehler / Warnung |
| Teaser | vorhanden; außerhalb 70 bis 180 Zeichen | Fehler / Warnung |
| Länge | unter `min_words` (Tiefenrecherche: `min_words_deep`; Layout briefing: 40 %) | Fehler |
| Gliederung | über 700 Wörter ohne h2 | Warnung |
| Quellenzahl | unter `min_sources` bzw. `min_sources_deep` | Fehler |
| Unabhängigkeit | alle Quellen von einer Domain | Fehler |
| Quellendaten | URL fehlt (außer Buch, Gespräch, Sonstiges); Herausgeber fehlt | Fehler / Warnung |
| Belege im Text | kein einziger Beleg; Beleg auf unbekannte Quelle | Fehler |
| Ungenutzte Quellen | Quelle wird nirgends zitiert | Warnung |
| Medien | unbekannte UID; fehlende Pflichtangaben | Fehler |
| Titelbild | fehlt bei `require_cover` | Fehler |
| Kategorie | keine Kategorie oder mehr als 3 | Fehler |
| Layout | longform unter 1500 Wörtern | Warnung |
| Dubletten | Titel über 80 % ähnlich zu einem veröffentlichten Artikel | Warnung |

Die Antwort enthält zusätzlich Kennzahlen: Wörter, Lesezeit, Quellen, zitierte Quellen, Belege, Quell-Domains, Bilder, die geltenden Mindestwerte.

### Transparenz

Artikel mit `ai_assisted` tragen in der Kopfzeile das Etikett „KI-recherchiert“ und am Ende einen Transparenzkasten mit dem Text aus `ai_disclosure_text`. Selbst geschriebene Artikel setzen `ai_assisted = false`.

## Statisches Frontend und SEO

Die Website ist ein Zeitungs-Feuilleton im Browser: Serifenschrift, Papierton, feine Linien, eine Akzentfarbe. Für Besucher:innen lädt sie nichts von Dritten, setzt keine Cookies und kommt mit einer CSS- und einer JS-Datei aus. Den Redaktionsmodus lädt nur, wer sich anmeldet.

### Seitentypen

| Seite | Pfad | Inhalt |
| --- | --- | --- |
| Startseite | `/`, `/seite/2/` | Aufmacher (neuester Artikel, groß), darunter ein Raster weiterer Artikel, Blättern |
| Artikel | `/artikel/{slug}/` | Siehe Anatomie unten |
| Kategorie | `/kategorie/{slug}/`, Unterkategorie `/kategorie/{eltern}/{slug}/` | Beschreibung, Unterkategorien, Artikelraster, Blättern |
| Kategorien | `/kategorien/` | Alle Kategorien mit Beschreibung und Artikelzahl |
| Schlagwort | `/tag/{slug}/` | Artikelraster, Blättern |
| Schlagwörter | `/schlagwoerter/` | Alle Schlagwörter nach Häufigkeit |
| Archiv | `/archiv/`, `/archiv/{jahr}/` | Alle Artikel nach Jahr und Monat |
| Suche | `/suche/` | Volltextsuche mit Filtern |
| Seiten | `/ueber/`, `/impressum/`, `/datenschutz/` | Aus den Dokumenten, Markdown gerendert |
| Technisch | `/feed.xml`, `/sitemap.xml`, `/robots.txt`, `/404.html` | RSS mit Volltext, Sitemap mit Bildern, Crawler-Regeln, Fehlerseite |
| Vorschau | `/vorschau/?id=…` | Statische Hülle mit noindex; zeigt Entwürfe nach dem Login oder per signiertem Link |
| Zustimmung | `/anmelden/zustimmung/` | Statische Seite, auf der du einer Claude-App per Login den Zugriff erlaubst |

Der Kopf zeigt eine Leiste mit „Nr. {Artikelzahl}“, Unterzeile und Suchfeld, darunter den Blognamen groß und die Hauptkategorien als Navigation.

### Kategorien, Filter und Suche

- **Kategorien:** hierarchisch (Haupt- und Unterkategorien). Ein Artikel hat eine Hauptkategorie und bis zu zwei weitere. Die Hauptkategorie bestimmt Dachzeile, Brotkrumen und `articleSection`. Kategorien sind zugleich die Interessengebiete des Agenten (Gewicht 0 bis 5, 0 = nie von selbst).
- **Schlagwörter** ordnen quer zu den Kategorien, höchstens 12 pro Artikel.
- **Navigation:** Hauptkategorien im Kopf, Unterkategorien auf der Kategorieseite, Übersichten unter `/kategorien/` und `/schlagwoerter/`, Archiv nach Jahr und Monat.
- **Suchfeld** im Kopf jeder Seite; Enter führt zu `/suche/?q=…`.
- **Suche:** clientseitig über einen Index aus Titel, Unterzeile, Teaser, Zwischenüberschriften, Kategorien, Schlagwörtern und Artikeltext. Normalisiert: Kleinschreibung, Umlaute gefaltet, einfache deutsche Stammformen. Gewichtung: Titel vor Überschriften vor Teaser vor Text. Treffer zeigen einen Textausschnitt mit hervorgehobenem Begriff. Der Index lädt erst bei der ersten Suche; ab etwa 1000 Artikeln teilt der Builder ihn in Teile auf.
- **Filter** auf Suche und Archiv: Kategorie (inklusive Unterkategorien), Schlagwort, Jahr, Format (Layout), Sortierung nach Relevanz, neu oder alt. Die Filter stehen in der URL (`?q=…&kategorie=…&tag=…&jahr=…`), sind also teilbar und mit der Zurück-Taste bedienbar.
- **Ohne JavaScript** funktionieren Kategorie-, Schlagwort- und Archivseiten als statische Seiten; nur Volltextsuche und kombinierte Filter brauchen JS.

### Anatomie eines Artikels

1. Brotkrumen, Dachzeile, Titel (einzige h1), Unterzeile kursiv
2. Autorenzeile: Autor, Datum, „aktualisiert“, Lesezeit, Etiketten „KI-recherchiert“ und „{n} Quellen“
3. Titelbild mit Bildunterschrift und Nachweis
4. Bei longform ein Inhaltsverzeichnis: auf breiten Bildschirmen mitlaufend links, sonst aufklappbar
5. Text mit Komponenten, Fußnoten-Belegen, Abbildungen
6. Transparenzkasten, Quellenverzeichnis, Bild- und Mediennachweise, Schlagwörter, vorheriger und nächster Artikel
7. „Mehr dazu“: drei Artikel aus derselben Kategorie oder mit gemeinsamen Schlagwörtern

### Gestaltungsmittel für den Agenten

Abwechslung entsteht über drei Stellschrauben, nicht über freies CSS. Der Agent erhält alles per `get_style_guide` mit HTML-Beispielen.

- **Layout je Artikel:** `standard` (Titelbild über dem Text), `feature` (randloses Titelbild, großer Titel), `longform` (Inhaltsverzeichnis, Initiale), `briefing` (dichter Satz, kürzere Mindestlänge).
- **Akzentfarbe je Artikel** als Hex-Wert. Im Dark Mode hellt sie das CSS automatisch per `color-mix()` auf.
- **Komponenten-Klassen:** `lead`, `tldr`, `callout` (note, warning, key), `factbox`, `pullquote`, `stats`/`stat`, `timeline`, `steps`, `checklist`, `two-col` mit `pros`/`cons`, `compare` (Tabelle), `qa` (Interview), `definition`, `details` (aufklappbar), `opinion` (Meinung, automatisch gekennzeichnet), `update` (Korrektur), `sidenote`, `dropcap`. Bildlayouts über `data-layout`: inline, wide, full, left, right.
- **Medien-Platzhalter:** `<figure data-media="UID">` für Bild, Video und Audio; `<div class="gallery" data-media="UID1,UID2">` für Galerien; `<figure data-embed="youtube" data-id="…">` für Videos, die erst nach Klick laden.

### Bilder

- `<picture>` mit WebP- und JPEG-`srcset` in 480 bis 2000 px und passendem `sizes` je Layout.
- Feste `width` und `height` gegen Layoutsprünge, `loading="lazy"`. Das Titelbild lädt sofort mit `fetchpriority="high"`.
- Lightbox per `<dialog>` mit der größten Variante.

### SEO

- Saubere, sprechende URLs, `<link rel="canonical">`, `lang`, semantisches HTML.
- `title` aus `seo_title` oder Titel; `meta description` aus `seo_description` oder Teaser.
- OpenGraph und Twitter Cards inkl. Bildgröße und Alt-Text, `article:published_time`, `article:section`, `article:tag`.
- JSON-LD: `BlogPosting` mit `headline`, `description`, `datePublished`, `dateModified`, `author`, `publisher`, `wordCount`, `articleSection`, `keywords`, `citation` (alle Quellen als `CreativeWork`) und `image` als `ImageObject` mit `creditText`, `creator`, `license`, `acquireLicensePage`, `copyrightNotice`. Dazu `BreadcrumbList`; auf der Startseite `WebSite` mit `SearchAction`.
- `sitemap.xml` mit `lastmod` und Bild-Erweiterung, `robots.txt` mit Sitemap-Verweis und Sperre für `/api/`, `/mcp`, `/oauth/`, `/vorschau/` und `/anmelden/`.
- Suche, Impressum und Datenschutz auf `noindex`, Vorschau und Zustimmung auf `noindex,nofollow`.
- RSS 2.0 mit `content:encoded` (Volltext mit absoluten URLs), Kategorien und Titelbild als `enclosure`.

### Technik und Barrierefreiheit

- Systemschriften (z. B. Iowan Old Style, Palatino, Charter, Georgia), keine Webfonts. Zielgrößen: CSS unter 30 KB, öffentliches JS unter 5 KB, keine Bibliotheken.
- Dark Mode über `prefers-color-scheme`, `prefers-reduced-motion`, Druck-Stylesheet mit ausgeschriebenen Quell-URLs.
- Sprunglink zum Inhalt, sichtbare Fokusrahmen, Kontrast mindestens WCAG AA, alle Bilder mit Alt-Text.
- Das öffentliche JS ist optional: Zwei-Klick-Videos, Lightbox, Lesefortschritt, aktiver Abschnitt im Inhaltsverzeichnis, Suche, Zähl-Beacon und der Login-Auslöser. Ohne JS funktioniert alles außer Suche, Videos und Login.

### Build

- Bei jeder Freigabe baut der Builder die komplette Website neu: Artikel, Listen, Kategorien, Schlagwörter, Archiv, Suchindex, Feed, Sitemap, Seiten, dazu die Hüllen für Vorschau und OAuth-Zustimmung. Ein inkrementeller Build lohnt sich erst bei mehreren tausend Artikeln.
- Der Builder liest ausschließlich Live-Stände (`published_revision`). Arbeitsstände, Entwürfe und offene Vorschläge gelangen nie nach `public/`.
- Datenbanksperre (MySQL GET\_LOCK) gegen parallele Builds. Schreiben per temporärer Datei und `rename`, unveränderte Dateien werden nicht angefasst.
- Ein Manifest listet alle erzeugten Dateien. Was beim nächsten Build nicht mehr entsteht, wird gelöscht. `_app.php` und `.htaccess` stehen nie im Manifest und sind damit sicher.
- Medien werden nur kopiert, wenn ein veröffentlichter Artikel sie verwendet.
- Die Vorschau-API rendert Entwürfe mit denselben Templates, lädt Medien aber über signierte Links aus dem privaten Speicher.

## Zugriffsstatistik

Die Statistik zählt selbst: ohne Cookies, ohne Dritte und ohne gespeicherte IP-Adressen. Gespeichert werden nur Tageswerte, abrufbar per MCP und im Redaktionsmodus.

### Erfassung

- **Zähl-Aufruf:** `site.js` schickt pro Seitenaufruf per `navigator.sendBeacon` einen kleinen Datensatz an `POST /api/hit`: Pfad, Referrer-Domain, `utm_source`, Geräteklasse (mobil, Tablet, Desktop). Die API antwortet sofort mit 204. Das ist der einzige PHP-Aufruf normaler Besucher:innen.
- **Lesezeit und Lesetiefe:** Beim Verlassen der Seite (`visibilitychange`) folgt ein zweiter Beacon mit aktiver Lesezeit in Sekunden und der erreichten Tiefe (50 % und 100 % des Artikeltexts).
- **Ausgehende Klicks:** Zieldomain von Quellen- und anderen externen Links, gezählt pro Artikel.
- **Suchbegriffe:** Die Suche meldet Begriff und Trefferzahl, ohne Bezug zur Person. Suchen ohne Treffer sind ein Themenhinweis für den Agenten.
- **Eindeutige Besucher:innen pro Tag:** SHA-256 aus IP, User-Agent und einem Tagesschlüssel, der um Mitternacht verworfen wird. Der Hash entdoppelt nur innerhalb eines Tages, die Tabelle wird täglich geleert. Ein Wiedererkennen über Tage hinweg ist nicht möglich.
- **Nicht gezählt:** Bots (User-Agent-Liste, fehlender oder fremder `Origin`), dein eigener Browser (Merkzeichen des Logins), Vorschau- und Zustimmungsseiten, Aufrufe mit Global Privacy Control oder Do Not Track.
- **Ohne JavaScript** wird nicht gezählt; die Statistik nimmt das in Kauf.
- **Lastschutz:** höchstens 60 Aufrufe pro Tages-Hash und Stunde, darüber wird verworfen.

### Kennzahlen

| Kennzahl | Bedeutung | Auflösung |
| --- | --- | --- |
| Aufrufe | Seitenaufrufe | Tag × Pfad |
| Besucher:innen | eindeutig pro Tag, nicht über Tage summierbar | Tag × Pfad |
| Aktive Lesezeit | Sekunden mit sichtbarer, aktiver Seite | Tag × Artikel |
| Gelesen | Anteil, der 50 % bzw. das Ende des Textes erreicht | Tag × Artikel |
| Herkunft | Referrer-Domain, Suchmaschine, direkt, `utm_source` | Tag × Pfad |
| Geräteklasse | mobil, Tablet, Desktop | Tag |
| Ausgehende Klicks | Zieldomain | Tag × Artikel |
| Suchen | Begriff, Trefferzahl | Tag |

Länder bräuchten eine GeoIP-Datenbank und sind deshalb nicht in Version 1. Abrufe von `/feed.xml` sind statisch und werden nicht gezählt; dafür bräuchte es die Server-Logs von Strato 🟡.

### Abruf

- **MCP:** `get_stats(from, to, group_by, filter)` mit `group_by` = day, post, category, referrer, device, outbound oder search; `get_post_stats(id or slug, from, to)` für einen Artikel. Das Briefing enthält eine Kurzfassung: meistgelesene Artikel und Kategorien der letzten 30 Tage, Suchen ohne Treffer.
- **In der Claude-App** reicht dann eine Frage wie „Wie lief mein Blog diese Woche?“.
- **Redaktionsmodus:** Dialog „Statistik“ mit Verlauf, Top-Artikeln, Herkunft und Suchbegriffen. Auf jedem veröffentlichten Artikel zeigt die Leiste eine Zeile wie „1.240 Aufrufe · 58 % bis zum Ende gelesen“.
- **Aufbewahrung:** Tageswerte unbegrenzt, da ohne Personenbezug; Hashes nur für den laufenden Tag.

## Redaktionsmodus im Frontend

Es gibt keine eigene Admin-Oberfläche. Die statische Website enthält einen Login, der per JavaScript gegen die PHP-API authentifiziert und ein JWT erhält. Erst nach der Anmeldung lädt der Browser den Redaktionsmodus nach und blendet ihn direkt in die Seiten ein. Besucher:innen laden davon nichts und lösen außer dem anonymen Zähl-Aufruf keinen PHP-Aufruf aus.

### Login und Sitzung

1. **Einstieg:** ein unauffälliger Link „Anmelden“ in der Fußzeile sowie die Adresse `/#anmelden`. Beides öffnet einen `<dialog>` mit Benutzername und Passwort (später zusätzlich TOTP).
2. **Anmelden:** `POST /api/auth/login` prüft die Daten gegen `FEUILLETON_USER` und `FEUILLETON_PASSWORD_HASH` und liefert ein Access-JWT (15 Minuten) im JSON-Body. Dazu setzt der Server das Refresh-Cookie (Details im Sicherheitsabschnitt).
3. **Aufbewahrung:** Das Access-JWT liegt nur im Arbeitsspeicher der Seite, nie in `localStorage`. Jede API-Anfrage trägt `Authorization: Bearer <jwt>`.
4. **Seitenwechsel:** Erst fragt das Skript per `BroadcastChannel`, ob ein offener Tab ein gültiges JWT hat. Wenn nicht, holt `POST /api/auth/refresh` per Cookie ein neues. Das passiert nur, wenn in `localStorage` das harmlose Merkzeichen `feuilleton.angemeldet` steht. So löst der Login bei normalen Besucher:innen keinen PHP-Aufruf aus.
5. **Abmelden:** `POST /api/auth/logout` widerruft das Refresh-Token und löscht Cookie und Merkzeichen; `POST /api/auth/logout-all` meldet alle Geräte ab.
6. **JWT-Inhalt:** `iss`, `aud` (= `https://blog.example.de/api`), `sub` (owner), `iat`, `exp`, `jti`. Signiert mit HS256; der Algorithmus ist serverseitig fest, ein `alg` aus dem Token wird nie übernommen.
7. **Getrennte Welten:** Ein JWT gilt nur für `/api`, ein MCP-Token nur für `/mcp`. Auch OAuth-Tokens der Claude-Apps gelten nur für /mcp; keines erreicht eine Redaktionsfunktion.
8. **Erneute Bestätigung:** Ein MCP-Token anzulegen verlangt eine Passworteingabe, die höchstens 5 Minuten zurückliegt (`POST /api/auth/confirm`).
9. **Keine Einrichtung:** Die Zugangsdaten kommen aus der Umgebung. Die Installation bringt eine vorgebaute Start-`index.html` mit Login mit, damit es vor dem ersten Build eine Seite gibt.

### Funktionen nach dem Login (Version 1)

| Wo | Was | API |
| --- | --- | --- |
| Überall | Redaktionsleiste (mobil unten, sonst oben): Zähler offener Freigaben und Vorschläge, „Thema einreichen“, „Zugänge“, „Abmelden“ | `GET /api/overview` |
| Startseite | Leiste „Wartet auf Freigabe“: eingereichte und zurückgegebene Entwürfe mit Status-Etikett sowie Änderungsvorschläge mit Vorher und Nachher, „Übernehmen“ und „Ablehnen“ | `GET /api/posts?status=review,draft`, `GET /api/changes`, `POST /api/changes/{id}/apply`, `POST …/reject` |
| Vorschau `/vorschau/?id=…` | Entwurf im echten Layout; Freigabeleiste mit Ergebnis der Formprüfung, „Freigeben“, „Zurück an den Agenten“ (Anmerkung), „Verwerfen“ | `GET /api/posts/{id}/preview`, `POST …/approve`, `POST …/return`, `DELETE /api/posts/{id}` |
| Veröffentlichter Artikel | Bei neuem Arbeitsstand: Änderungsansicht gegenüber der Live-Fassung und „Änderungen freigeben“; immer „Zurückziehen“ | `GET /api/posts/by-slug/{slug}`, `POST …/approve`, `POST …/unpublish` |
| Dialog „Thema einreichen“ | Titel, Briefing, Links, Fotos, Videos, Audio mit Kamera-Upload, „Material ist von mir“, Priorität, Tiefe | `POST /api/queue` (multipart) |
| Dialog „Zugänge“ | MCP-Token anlegen (Name, Scopes, Ablauf) nach erneuter Passworteingabe, einmal anzeigen mit Kopierknopf und fertigem `claude mcp add`-Befehl; Tokens mit letzter Nutzung, widerrufen; verbundene Claude-Apps (OAuth) trennen; OAuth-Client für die Rückfalloption anlegen; „Auf allen Geräten abmelden“ | `POST /api/tokens`, `GET /api/tokens`, `DELETE /api/tokens/{id}`, `GET`/`POST`/`DELETE /api/oauth-clients`, `POST /api/auth/logout-all` |
| Dialog „Statistik“ | Verlauf, Top-Artikel, Herkunft, Suchbegriffe; auf veröffentlichten Artikeln eine Kennzahlenzeile in der Leiste | GET /api/stats, GET /api/stats/posts/{id} |

**Später:** Dialoge für Dokumente, Kategorien, Einstellungen, Medien und das Protokoll. Bis dahin erledigt Claude das über MCP, z. B. „Ändere meine Präferenzen: …“; Öffentliches landet dabei als Vorschlag in deiner Leiste. Die Formvorgaben stehen bis zum Einstellungsdialog in `config.php`.

### Technik

- `site.js` (öffentlich, unter 5 KB) enthält nur den Login-Auslöser, die Prüfung des Merkzeichens und das Nachladen. `redaktion.js` und `redaktion.css` lädt es erst nach erfolgreicher Anmeldung per `import()`.
- Keine Bibliothek, kein Build-Schritt: ES-Module, natives `<dialog>`, `fetch`, `FormData` für Uploads.
- `/vorschau/index.html` ist eine statische Hülle mit `noindex`. Sie holt das Artikel-HTML von der API, die mit denselben PHP-Templates rendert wie der Builder. Es gibt also nur eine Darstellung.
- Signierte Vorschau-Links aus den Agenten-Berichten (`/vorschau/?id=12&exp=…&sig=…`) funktionieren ohne Login, nur lesend. Nach der Anmeldung erscheinen dort die Freigabeknöpfe.
- Die API antwortet nur mit JSON (außer `…/preview`: HTML-Fragment). Fehler kommen als `{"error": "…", "message": "…"}` mit passendem HTTP-Status.

## Anbindung an Claude

Der Blog lässt sich auf zwei Wegen in Claude einbinden: als Connector in der Claude-App (Android, Web, Desktop) per OAuth und in Claude Code per OAuth oder MCP-Token. Recherche und Einreichen laufen typischerweise in Claude Code, interaktiv oder als geplante Aufgabe. Die App eignet sich zum Einreichen von Themen und zum Nachsehen unterwegs.

### Einrichten

1. **Claude-App, auch Android:** in Claude unter Einstellungen → Connectors einen eigenen Connector hinzufügen, URL `https://blog.example.de/mcp`. Claude leitet auf die Zustimmungsseite des Blogs; dort anmelden und Scopes bestätigen. Danach steht der Connector in allen Claude-Apps deines Kontos bereit.
2. **Claude Code mit OAuth:** `claude mcp add --transport http feuilleton https://blog.example.de/mcp`, danach in Claude Code `/mcp` aufrufen und anmelden.
3. **Claude Code mit festem Token:** auf dem Blog unter „Zugänge“ ein MCP-Token mit `blog:read` und `blog:write` anlegen (für Richtlinien und Kategorien zusätzlich `blog:admin`), dann `claude mcp add --transport http feuilleton https://blog.example.de/mcp --header "Authorization: Bearer fmcp_…"`.
4. **Cloud-Sitzungen und geplante Aufgaben:** eine `.mcp.json` im Redaktions-Repository mit `"headers": {"Authorization": "Bearer ${FEUILLETON_TOKEN}"}`. Das Token liegt als Umgebungsvariable in der Umgebung der Aufgabe, nicht im Repository 🟡.

### Geplante Aufgabe

Eine wiederkehrende Claude-Code-Aufgabe, z. B. montags, mittwochs und freitags um 7:40 Uhr, mit Websuche und dem Feuilleton-Server. Der Prompt bleibt bewusst kurz, weil der eigentliche Workflow im Briefing steckt und im Blog gepflegt wird:

```
Nutze den MCP-Server „feuilleton“. Rufe get_editorial_brief(mode="auto") auf
und folge dem dort beschriebenen Workflow vollständig: Recherche mit
Websuche, Quellen nach den Redaktionsrichtlinien, Bilder nur mit Lizenz,
validate_post, bis keine Fehler bleiben, dann submit_post.
Wenn sich das Thema nicht nach den Richtlinien belegen lässt, reiche nichts
ein. Bei Queue-Aufträgen rufst du dann release_queue_item mit Status
"failed" und Begründung auf.
Schließe mit einer kurzen Zusammenfassung: Titel, Vorschau-Link, Anzahl der
Quellen, offene Punkte.
```

Damit die Aufgabe ohne Rückfragen durchläuft, braucht sie die Erlaubnis für die Feuilleton-Tools, z. B. `mcp__feuilleton__*` unter `permissions.allow` in den Projekteinstellungen 🟡. Das ist unbedenklich, denn kein Token kann etwas veröffentlichen.

### Reihenfolge im Briefing

`get_editorial_brief(mode="auto")` entscheidet serverseitig, was dran ist, und liefert genau einen Workflow:

1. von dir zurückgegebene Entwürfe mit Anmerkung,
2. offene Queue-Aufträge nach Priorität und Alter,
3. ein geplanter Artikel nach Rotationswert.

### Themen unterwegs einreichen

- **Per Claude-App auf dem Handy:** „Setz auf meine Blog-Queue: Warum werden Bananen braun? Priorität hoch.“ Claude ruft `add_to_queue` auf. Links funktionieren immer; ob Claude angehängte Fotos als Base64 an ein Tool weiterreicht, hängt vom Client ab 🟡.
- **Auf dem Blog:** eingeloggt „Thema einreichen“ in der Redaktionsleiste, mit Kamera-Upload. Der verlässlichste Weg für Fotos und Videos.
- **Als Lesezeichen auf dem Homescreen:** `/#thema-einreichen` öffnet nach dem Login direkt den Dialog.
- **Per Claude Code:** „Setz auf meine Blog-Queue: …“ in einer Sitzung mit eingebundenem Feuilleton-Server.

## Recht und Datenschutz

Das System setzt die rechtlich heiklen Punkte technisch um. Die Inhalte der Pflichtseiten und die Frage, welche Pflichten für deinen Blog gelten, solltest du prüfen lassen 🟡. Dieser Abschnitt ist keine Rechtsberatung.

- **Impressum:** Seite `/impressum/` aus dem Dokument `imprint`. Die Vorlage nennt Angaben nach § 5 DDG und den Verantwortlichen nach § 18 Abs. 2 MStV, der bei journalistisch-redaktionellen Angeboten verlangt wird.
- **Datenschutz:** Die öffentliche Seite setzt keine Cookies und lädt nichts von Dritten. Die eigene Zugriffsstatistik speichert keine IP-Adressen, nur Tageswerte, und gehört in die Datenschutzerklärung. Ob die Zählung per JavaScript eine Einwilligung braucht (§ 25 TDDDG), ist umstritten 🟡; bitte prüfen lassen. Bleiben die Server-Logs bei Strato und Video-Einbettungen, die erst nach Klick laden und vorher auf die Datenübertragung hinweisen. Ein Cookie entsteht nur bei deinem Login: das Refresh-Token, beschränkt auf den Pfad /api/auth.
- **Bildrechte, das größte Abmahnrisiko:** Kein Bild ohne Lizenz, Urheber und Quelle, die Formprüfung erzwingt die Angaben, ihre Richtigkeit verantwortet der Einreicher. Die Nachweiszeile steht direkt am Bild und gesammelt am Artikelende, wie es CC-Lizenzen verlangen. Hotlinking fremder Bilder ist technisch ausgeschlossen.
- **Zitate:** Die Richtlinien erlauben nur kurze, belegte Zitate mit erkennbarem Zweck. Ganze Absätze fremder Texte sind tabu.
- **Persönlichkeitsrechte:** Keine Fotos von Privatpersonen ohne Einwilligung, keine Kinder. EXIF- und GPS-Daten eigener Fotos werden beim Import entfernt.
- **KI-Transparenz:** Die EU-KI-Verordnung sieht in Art. 50 Transparenzpflichten für KI-erzeugte Texte vor, die die Öffentlichkeit informieren sollen. Der Transparenzkasten ist deshalb standardmäßig an. Ob er rechtlich nötig ist, hängt unter anderem davon ab, ob ein Mensch redaktionell prüft 🟡; die menschliche Freigabe jedes Artikels kann hier eine Rolle spielen.
- **Korrekturen und Gegendarstellung:** Korrekturen erscheinen offen mit Datum. Ein Artikel lässt sich jederzeit zurückziehen, die Revisionen bleiben als Nachweis erhalten.

## Umsetzungsplan

Die Umsetzung läuft in sieben Phasen, jede mit einer prüfbaren Abnahme. Phase 0 klärt zuerst, was Strato tatsächlich kann. Ein fehlender Authorization-Header oder ein wirkungsloses `SetEnv` fiele sonst erst spät auf.

1. **Phase 0, Strato-Probe:** `config.php`, `.htaccess`, `_app.php` mit einer Diagnose-Route. Sie meldet PHP-Version, Erweiterungen, ob der Authorization-Header ankommt, ob `SetEnv`-Werte lesbar sind, ob die MySQL-Verbindung steht, ob ausgehendes HTTPS funktioniert und ob GD WebP kann.
   - Abnahme: alle Prüfungen grün auf dem echten Strato-Paket, danach Route entfernen.
2. **Phase 1, Kern:** Migrationen, Settings, Dokumente mit Startinhalten, Kategorien, Artikel mit Quellen, Revisionen und getrenntem Live-Stand, Änderungsvorschläge, Sanitizer, Formprüfung, Medien mit Varianten und URL-Import, Queue, Statistik-Tabellen, Audit-Log. Dazu eine CLI `bin/feuilleton` mit `hash-password`, `token:create` und `build`.
   - Abnahme: Tests für den Sanitizer (Liste bekannter XSS-Muster), jede Formregel, EXIF-Entfernung und Varianten, Queue-Reservierung bei zwei gleichzeitigen Agenten; der Builder ignoriert Arbeitsstände und offene Vorschläge.
3. **Phase 2, Builder und Theme:** alle Seitentypen inklusive Kategorien, Schlagwörter und Archiv, Suche mit Filtern, Feed, Sitemap, Manifest-Aufräumen.
   - Abnahme: Lighthouse mindestens 95 in allen vier Kategorien auf Startseite und Artikel; Rich-Results-Test erkennt Artikel und Brotkrumen; Feed valide; die Suche findet Begriffe im Fließtext, Filter funktionieren über die URL; Darstellung auf dem Handy geprüft, hell und dunkel.
4. **Phase 3, MCP-Server mit Tokens:** beide Protokoll-Generationen, alle Tools, Resources, Prompts, Scope-Prüfung. Tokens bis Phase 4 per CLI.
   - Abnahme: MCP Inspector und ein offizielles SDK listen alle Tools mit 2026-07-28 und mit 2025-11-25; ein kompletter Lauf in Claude Code von `add_to_queue` bis `submit_post`; öffentliche Änderungen per MCP landen als Vorschlag, Formvorgaben sind per MCP nicht änderbar; get\_stats liefert Tageswerte.
5. **Phase 4, Redaktions-API und Redaktionsmodus:** JWT-Login mit Refresh-Cookie, Vorschau, Freigabe mit Änderungsansicht, Vorschläge übernehmen, Zurückgeben, Zurückziehen, Thema einreichen, Zugänge, Zugriffsstatistik.
   - Abnahme: alle Funktionen auf dem Handy bedienbar; Besucher:innen lösen außer dem Zähl-Aufruf keinen PHP-Aufruf aus (Server-Log); `/api` weist MCP- und OAuth-Tokens ab, `/mcp` weist JWTs ab; manipulierte JWTs (`alg: none`, falscher Schlüssel, abgelaufen) werden abgelehnt; fünf Tabs gleichzeitig neu laden führt nicht zum Logout; die Statistik zählt weder Bots noch dich, und in der Datenbank steht keine IP-Adresse.
6. **Phase 5, OAuth 2.1:** Discovery, CIMD mit Allowlist, Zustimmungsseite mit JS-Login, Code mit PKCE, Token mit Rotation, Widerruf, Rückfall-Client.
   - Abnahme: Connector verbindet sich in der Claude-App auf Android und im Browser; Erneuerung funktioniert nach mehr als einer Stunde; Claude Code meldet sich per OAuth an; ein wiederverwendetes Refresh-Token widerruft die Familie; ein Client von fremdem Host wird abgewiesen.
7. **Phase 6, Probebetrieb:** geplante Claude-Code-Aufgabe einrichten, Artikel freigeben oder mit Anmerkung zurückgeben, Richtlinien und Präferenzen nachschärfen, Themen per Android-App einreichen.
   - Abnahme: zehn Artikel in Folge, die ohne inhaltliche Korrektur freigegeben werden.

**Später:** TOTP für den Login, Redaktionsdialoge für Dokumente, Kategorien, Einstellungen und Medien, Länder in der Statistik, zeitversetztes Veröffentlichen per Strato-Cron, Openverse als zweite Bildquelle, Newsletter, cookiefreie Statistik aus Server-Logs, verwandte Artikel per Embeddings.

### Hinweise für die Umsetzung mit Claude Code

- **Rahmen:** PHP 8.1+ (Ziel 8.3), `declare(strict_types=1)`, eigener PSR-4-Autoloader (`Feuilleton\` → `app/src/`), kein Framework, keine Laufzeit-Abhängigkeiten. JWT (HS256) selbst über hash\_hmac umgesetzt, mit fest eingestelltem Algorithmus. Frontend-JS als ES-Module ohne Build-Schritt. PHPUnit nur als Entwicklungs-Abhängigkeit. Bei Vorlagen aus anderen Projekten nur Muster übernehmen, die ohne Framework auskommen.
- **Schichten:** `_app.php` routet zu einem Controller je Schnittstelle (Mcp, OAuth, Api). Services kennen kein HTTP und werden von API, MCP und CLI gleichermaßen genutzt.
- **Tool-Registry:** Jedes Tool ist ein Eintrag mit Name, Titel, Beschreibung, Input-Schema, Annotationen, Scope und Handler. `tools/list`, die Scope-Prüfung und die Dokumentation entstehen daraus.
- **Tool-Beschreibungen sind Produkt:** Sie sagen, wann ein Tool dran ist, nennen ein Beispiel und das typische Folge-Tool. Fehlermeldungen sagen, wie es richtig geht.
- **Lokale Entwicklung:** `php -S localhost:8080 -t public dev-router.php`; der Dev-Router bildet die `.htaccess`-Regeln nach. Lokal ersetzt eine MySQL- oder MariaDB-Instanz (z. B. per Docker) die Strato-Datenbank. Eine Konfigurationsoption erlaubt lokal private IP-Adressen und Cookies ohne HTTPS.
- **End-to-End-Test:** ein Skript mit dem offiziellen MCP-SDK gegen den Dev-Server, inklusive OAuth-Ablauf mit PKCE und CIMD, Queue-Lauf, Einreichen und Freigabe über die API. Danach Screenshots der erzeugten Seiten per Playwright.
- **Startinhalte** (Richtlinien, Präferenzen, Style-Guide, Pflichtseiten) liegen als Markdown in `app/defaults/` und werden beim ersten Start in die Datenbank übernommen.

## Risiken und offene Entscheidungen

Das größte technische Risiko ist die Strato-Umgebung (Authorization-Header, `SetEnv`), Phase 0 klärt es. Inhaltliche Fehler liegen beim Einreicher; die App sichert nur die Form und deine Freigabe ab.

| Risiko | Folge | Gegenmaßnahme |
| --- | --- | --- |
| Authorization-Header erreicht PHP nicht | Jede MCP- und API-Anfrage scheitert mit 401 | `.htaccess`-Regel, Test in Phase 0; Rückfall auf den Header `X-Api-Key` für MCP-Tokens |
| `SetEnv` wirkt bei Strato nicht | Kein Login möglich | Test in Phase 0; Rückfall auf eine `.env` außerhalb des Document Root |
| Einreicher liefert falsche Fakten oder erfundene Quellen | Glaubwürdigkeit weg | Verantwortung liegt beim Einreicher; Richtlinien im Briefing, Quellen und Belege als Pflichtform, deine Freigabe vor jeder Veröffentlichung |
| Bild ohne Nutzungsrecht | Abmahnung | Lizenz-Pflichtfelder, kein Hotlinking, Blick auf die Nachweise bei der Freigabe |
| Prompt-Injection aus recherchierten Seiten | Schädliches HTML oder unerwünschte Aktionen | Sanitizer, Scopes, Löschen nur mit `confirm`, Revisionen, Audit-Log, Öffentliches nur per Freigabe |
| Strato-Limits bei großen Dateien | Abbrüche bei Upload oder Bildverarbeitung | Grenze 50 Megapixel, konfigurierbare Upload-Größe, große Videos lieber per YouTube oder Vimeo einbetten |
| Zwei Agentenläufe gleichzeitig | Doppelte Artikel | Reservierung mit Ablaufzeit, Datenbanksperre (GET\_LOCK) beim Build |
| MCP-Spezifikation ändert sich weiter | Clients verbinden sich nicht mehr | Protokollschicht getrennt von den Tools, mehrere Versionen parallel |
| Datenverlust | Artikel und Medien weg | Datenbankdatei und `storage/media/` regelmäßig sichern; `public/` ist reproduzierbar |
| XSS-Lücke auf der öffentlichen Seite | Fremder Code läuft im Redaktionsmodus mit | Strenge CSP ohne Inline-Skripte, Sanitizer, JWT nur im Arbeitsspeicher, erneute Passworteingabe für neue Tokens |
| Refresh-Cookie wird abgegriffen | Fremde Sitzung im Redaktionsmodus | `HttpOnly`, `SameSite=Strict`, Rotation mit Familienwiderruf, „Auf allen Geräten abmelden“ |
| MCP- oder OAuth-Token gelangt in falsche Hände | Fremde Entwürfe und Vorschläge | Nichts davon wird ohne Freigabe öffentlich; Token oder verbundene App im Dialog „Zugänge“ widerrufen, Ablaufdatum setzen |
| Claude-App meldet sich nicht per CIMD an 🟡 | Connector lässt sich nicht verbinden | Test in Phase 5; Rückfall auf einen selbst angelegten OAuth-Client unter „Erweiterte Einstellungen“ |
| Statistik zählt Bots mit | Verzerrte Zahlen | User-Agent-Liste, Origin-Prüfung, Zählung nur per JavaScript, Lastschutz pro Tages-Hash |
| Rechtslage der cookielosen Zählung unklar 🟡 | Abmahnrisiko | Keine IP, keine Cookies, Tages-Hash, GPC und DNT respektiert, Datenschutzerklärung; per stats\_enabled abschaltbar |

**Offene Entscheidungen**

- [ ] Subdomain und Name des Blogs
- [x] Kategorienbaum zum Start (Haupt- und Unterkategorien, Gewichte) — siehe `docs/PROJECT.md` → „Entschieden zum Start“
- [ ] Login-Einstieg: Link in der Fußzeile oder nur über `/#anmelden`
- [x] Rhythmus der geplanten Aufgabe: täglich
- [ ] Nur Deutsch oder auch Artikel auf Englisch
- [x] Mindestwerte bestätigen: 3 Quellen und 600 Wörter, bei Tiefenrecherche 5 Quellen und 1200 Wörter
- [x] Wortlaut des Transparenzhinweises: „KI-recherchiert, von Menschen geprüft und freigegeben.“
- [ ] Statistik später erweitern: Länder (GeoIP), Feed-Abrufe (Server-Logs)

## Quellen

- [Authentication for connectors](https://claude.com/docs/connectors/building/authentication.md), Claude-Dokumentation: Anmeldearten, CIMD und DCR, Callback-URLs, Token-Erneuerung, Zeitlimits
- [MCP 2026-07-28 Release Candidate](https://blog.modelcontextprotocol.io/posts/2026-07-28-release-candidate/), Model Context Protocol Blog: zustandsloser Kern, `server/discover`, Header, Caching, `iss`-Prüfung
- [The 2026-07-28 MCP Specification Becomes Stateless-First](https://azukiazusa.dev/en/blog/mcp-stateless), azukiazusa.dev: Antwortformat von `server/discover`, `_meta`-Schlüssel, `ttlMs` und `cacheScope`
- [Wie kann ich die PHP- und/oder Datenbank-Version herausfinden?](https://www.strato.de/faq/hosting/wie-kann-ich-die-php-und-oder-mysql-version-herausfinden/), STRATO FAQ: verfügbare PHP-Versionen 8.0 bis 8.3
