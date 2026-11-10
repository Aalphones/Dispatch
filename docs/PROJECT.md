# DISPATCH — Kontext

> **Your agents. Your publication.**

Ausführliches Fachkonzept (Arbeitstitel „Feuilleton"): [`design/konzept-blog-engine.md`](design/konzept-blog-engine.md). Diese Datei und die ADRs unter [`decisions/`](decisions/) haben Vorrang — wo das Konzept abweicht, steht es unten unter „Abweichungen vom Konzept".

## Ziel & Vision

DISPATCH ist eine persönliche Blog-Engine in PHP, die wie ein Verlag arbeitet, nicht wie eine Redaktion. KI-Agenten in Claude Code recherchieren, prüfen und schreiben; über einen MCP-Server reichen sie fertige Artikel ein. Die App prüft nur die Form und legt jeden Artikel dem Inhaber zur Freigabe vor. Besucher bekommen ausschließlich statische Dateien, die PHP nach jeder Freigabe neu erzeugt.

## Scope

- MCP-Server (Streamable HTTP, zustandslos, Protokoll 2026-07-28 plus ältere Clients mit `initialize`) mit Tool-Katalog für Briefing, Queue, Artikel, Medien, Statistik und Rebuild.
- OAuth 2.1 mit CIMD für die Claude-Apps, dazu feste MCP-Tokens für Claude Code und geplante Aufgaben.
- Redaktions-API mit JWT-Login für genau ein Konto; Redaktionsmodus als nachgeladenes JavaScript direkt in der öffentlichen Website (Freigabe, Zurückgeben, Vorschläge, Thema einreichen, Zugänge, Statistik).
- Domänenschicht: Artikel mit Arbeits- und Live-Stand, Revisionen, Quellen, Kategorien, Medien mit Bildvarianten, Queue, Dokumente, Änderungsvorschläge, Einstellungen, Audit-Log.
- Sanitizer (HTML-Whitelist) und Formprüfung.
- Site-Builder: alle Seitentypen, Suche mit clientseitigem Index, Feed, Sitemap, Manifest-Aufräumen.
- Cookielose Zugriffsstatistik ohne gespeicherte IP-Adressen.
- Deploy-Skript für Strato, das die Nachbar-Anwendungen auf derselben Domain nicht berührt und nach dem Hochladen nachprüft.

## Nicht-Ziele

- Mehrere Autoren oder Konten, Registrierung, Kommentare, Newsletter, Bezahlschranke, zeitversetztes Veröffentlichen (Version 1).
- Eigene Admin-Oberfläche — der Redaktionsmodus lebt in der Website.
- Hintergrundprozesse, Server-Cron, Node auf dem Server, Composer-Abhängigkeiten.
- Automatisierte Tests ([ADR-004](decisions/004-keine-automatisierten-tests.md)).
- Inhaltliche Prüfung durch die App — Qualität verantwortet der Einreicher, die letzte Kontrolle ist die Freigabe.

## Stack

| Layer | Choice |
|---|---|
| Sprache | PHP 8.5, `declare(strict_types=1)` — dieselbe Version wie die Nachbar-Anwendung auf demselben Strato-Paket; auf Strato gemessen: 8.5.12 (`cgi-fcgi`), siehe `docs/knowledge/INDEX.md` |
| Framework | keins; eigener PSR-4-Autoloader (`Dispatch\` → `app/src/`) |
| Datenbank | MySQL bei Strato, PDO, `utf8mb4`, InnoDB |
| Öffentliche Website | statisches HTML aus PHP-Templates, ein CSS, ein kleines JS |
| Redaktionsmodus | Vanilla-JS als ES-Module, kein Build-Schritt, keine Bibliotheken |
| Deploy | `deploy.cmd` + portables WinSCP ([ADR-005](decisions/005-deploy-skript.md)) |
| CI | GitHub Actions: PHP-Syntaxprüfung |
| Lokale Entwicklung | `php -S` mit Dev-Router, MySQL/MariaDB lokal |

Kein Composer: Laufzeit-Abhängigkeiten schließt das Konzept aus, und ohne Tests (ADR-004) gibt es auch keine Entwicklungs-Abhängigkeiten. JWT entsteht mit PHP-Bordmitteln (`hash_hmac`, Algorithmus fest HS256).

## Constraints

- **Gemeinsame Domain:** `quantum-canvas.de` liefert einen Ordner `www/` aus, in dem schon CardMaker (`/card/`, `/api/`, `/backend/`) und CamScanner (`/scanner/`) laufen. Beide müssen erreichbar bleiben. DISPATCH belegt die Wurzel ([ADR-001](decisions/001-pfade-auf-gemeinsamer-domain.md)).
- **Ein SFTP-Zugang, der nur `www/` sieht.** Programmcode und Daten liegen deshalb im Webroot, gesperrt per `.htaccess` ([ADR-002](decisions/002-code-im-webroot-gesperrt.md)).
- **Keine Subdomain:** Strato stellt in diesem Paket für Subdomains kein Zertifikat aus.
- **Kein SSH, keine Kommandozeile auf dem Server:** Migration und Rebuild nach dem Deploy laufen über einen tokengeschützten Endpunkt.
- **Wurzel-`.htaccess` gehört DISPATCH** ([ADR-003](decisions/003-wurzel-htaccess.md)); ihre Regeln vererben sich in die Nachbarordner.
- Ein Konto, Zugangsdaten nur aus der Umgebung (`.env`), keine Registrierung.

## Abweichungen vom Konzept

| Konzept | DISPATCH |
|---|---|
| Name „Feuilleton", Namespace `Feuilleton\`, Variablen `FEUILLETON_*`, Token-Präfix `fmcp_`, Header `X-Feuilleton`, Merkzeichen `feuilleton.angemeldet` | „DISPATCH", `Dispatch\`, `DISPATCH_*`, `dmcp_`, `X-Dispatch`, `dispatch.angemeldet`; MCP-Servername `dispatch` |
| Eigene Subdomain, Document Root = `public/` | Wurzel von `quantum-canvas.de`, geteilt mit Nachbar-Anwendungen (ADR-001) |
| `/mcp`, `/api/*`, `/oauth/*` | `/dispatch/mcp`, `/dispatch/api/*`, `/dispatch/oauth/*`; nur `/.well-known/oauth-*` bleibt an der Wurzel (ADR-001) |
| Refresh-Cookie `Path=/api/auth`, JWT-`aud` `…/api` | `Path=/dispatch/api/auth`, `aud` = `https://quantum-canvas.de/dispatch/api` |
| `app/` und `storage/` über dem Document Root | `www/dispatch-app/`, komplett gesperrt (ADR-002) |
| Zugangsdaten per `SetEnv`, `.env` als Rückfall | nur `.env` in `www/dispatch-app/`, geschrieben vom Deploy (ADR-002); Phase 0 prüft `SetEnv` nicht mehr |
| `.htaccess` mit globaler CSP, `ErrorDocument`, Caching | Blog-Regeln nur in einem Block, der die Nachbarordner ausnimmt (ADR-003) |
| `robots.txt` sperrt `/api/`, `/mcp`, `/oauth/` | sperrt `/dispatch/`, `/dispatch-app/`, `/vorschau/`, `/anmelden/`, `/suche/` |
| Gestaltung nur beschrieben | verbindlicher Entwurf `docs/design/entwurf-website.html` (ADR-013) |
| Archiv `/archiv/`, Übersicht `/schlagwoerter/`, Filter Jahr und Format | entfallen; Seitenzahlen, Kategorien, Schlagwort-Seiten und Suche mit additiven Schlagwort-, Kategorie- und Sortierfiltern (ADR-013) |
| Login-Link in der Fußzeile | Knopf „Anmelden" oben rechts im Kopf plus `/#anmelden` (ADR-013) |
| Redaktionsleiste über der Seite | Redaktion in die Seite eingebaut: Konto-Menü, „Wartet auf dich", Redaktionskasten, Interesse an den Kategorie-Karten, schwebender Knopf „Thema einreichen" (ADR-013) |
| Dialoge für Kategorien erst „später" | Interessen-Dialog (Gewichte, Stichwörter) mit Meilenstein 4; dazu „Mehr davon / Weniger" an Artikeln und Anmerkungen an einzelnen Stellen der Vorschau (ADR-013) |
| CSS < 30 KB, ein `site.js` < 5 KB | `style.css` ≤ 48 000 Byte, `site.js` ≤ 5 000 plus nachgeladene `suche.js` und `artikel.js` (ADR-015) |
| Builder baut Hüllen für Vorschau und Zustimmung | Vorschau mit Meilenstein 4, Zustimmung mit Meilenstein 5 (ADR-013) |
| PHP 8.3, PHPUnit, Tests in den Abnahmen | PHP 8.5, keine automatisierten Tests, Abnahme per Handprüfung (ADR-004) |
| Deployment per SFTP oder `git pull` | `deploy.cmd` mit Nachprüfung (ADR-005) |
| `/` auf der Domain | bisher Weiterleitung nach `/card/`, künftig die Startseite des Blogs |
| Tabellen `tokens`, `login_attempts` und die OAuth-Tabellen schon im Kern | `tokens` → Meilenstein 3, `login_attempts` → Meilenstein 4, OAuth-Tabellen → Meilenstein 5; Meilenstein 1 legt nur Migrationen 001–009 an |
| CLI mit `token:create` | `bin/dispatch` ohne `token:create` (Tokens entstehen mit Meilenstein 3) |
| Formprüfung im Artikel-Service | reine Klasse `Util\FormCheck` ohne Datenbankzugriff (ADR-012) |

## Meilensteine

0. **Strato-Probe und Deploy-Skript:** Brücke, gesperrter Code-Ordner, Wurzel-`.htaccess`, Diagnose-Route (PHP-Version, Erweiterungen, Authorization-Header, MySQL, ausgehendes HTTPS, GD mit WebP), `deploy.cmd` mit Rauchtest der Nachbarn. Bis der Builder läuft (Meilenstein 2), zeigt `/` eine statische Platzhalter-Seite mit Name und Unterzeile; die Weiterleitung nach `/card/` entfällt schon jetzt.
1. **Kern (erledigt):** Migrationen, Domänen-Services, Sanitizer, Formprüfung, Medien, Queue, Statistik-Tabellen, Audit-Log, CLI `bin/dispatch`.
2. **Builder und Theme:** alle Seitentypen nach Entwurf, Suche mit additiven Filtern, Fokusmodus, Feed, Sitemap, Manifest-Aufräumen (Plan: `docs/planning/2026-11-10_meilenstein-2-builder-theme/`).
3. **MCP-Server mit Tokens.** Das Briefing enthält zusätzlich die „Mehr davon / Weniger"-Reaktionen des Inhabers.
4. **Redaktions-API und Redaktionsmodus** nach Entwurf, inklusive Interessen-Dialog, „Mehr davon / Weniger" und Anmerkungen an Stellen der Vorschau.
5. **OAuth 2.1** für die Claude-Apps.
6. **Probebetrieb** mit geplanter Claude-Aufgabe.

Abnahmen je Phase: Konzept → „Umsetzungsplan", ohne die dort genannten automatisierten Tests.

## Offene Fragen

- Kategorienbaum zum Start (Haupt- und Unterkategorien, Gewichte).
- Rhythmus der geplanten Aufgabe (Vorschlag: dreimal pro Woche).
- Mindestwerte bestätigen: 3 Quellen und 600 Wörter, bei Tiefenrecherche 5 Quellen und 1200 Wörter.
- Wortlaut des Transparenzhinweises.
