# Architecture Decision Records — DISPATCH

Nummeriert, chronologisch. Eine akzeptierte ADR wird nicht mehr umgeschrieben — eine spätere Kurskorrektur bekommt eine neue ADR, die auf die alte verweist.

| # | Titel | Status |
|---|---|---|
| 001 | [Pfade auf der gemeinsamen Domain](001-pfade-auf-gemeinsamer-domain.md) | Akzeptiert |
| 002 | [Programmcode im Webroot, gesperrt; Zugangsdaten in `.env`](002-code-im-webroot-gesperrt.md) | Akzeptiert |
| 003 | [Die Wurzel-`.htaccess` gehört DISPATCH](003-wurzel-htaccess.md) | Akzeptiert |
| 004 | [Keine automatisierten Tests](004-keine-automatisierten-tests.md) | Akzeptiert |
| 005 | [Deploy per Skript, nur eigene Ordner, mit Nachprüfung](005-deploy-skript.md) | Akzeptiert |
| 006 | [Lokale Entwicklung: Dev-Router und eigene Env-Datei](006-lokale-entwicklung-dev-router.md) | Akzeptiert |
| 007 | [Strato-Probe: Diagnose-Route und Platzhalter über den Builder](007-diagnose-route-und-platzhalter-builder.md) | Akzeptiert |
| 008 | [Zuschnitt Meilenstein 1 und lokale Umgebung](008-zuschnitt-meilenstein-1-lokale-umgebung.md) | Akzeptiert |
| 009 | [Akteur, Inhaber-Rechte und Änderungsvorschläge](009-akteur-inhaber-rechte-aenderungsvorschlaege.md) | Akzeptiert |
| 010 | [Sanitizer mit HTML5-Parser und Whitelist](010-sanitizer-html5-parser-whitelist.md) | Akzeptiert |
| 011 | [Medien: HTTP-Client mit SSRF-Schutz und Bildvarianten](011-medien-http-client-ssrf-bildvarianten.md) | Akzeptiert |
| 012 | [Artikel-Statusmaschine und Live-Lesepfad](012-artikel-statusmaschine-live-lesepfad.md) | Akzeptiert |
| 013 | [Aufbau der öffentlichen Website und verbindlicher Entwurf](013-website-aufbau-und-entwurf.md) | Akzeptiert |
| 014 | [Builder: Vollbau, Sperre, Manifest, Markdown](014-builder-vollbau-manifest.md) | Akzeptiert |
| 015 | [Theme: Budgets und Skriptmodule](015-theme-budgets-skriptmodule.md) | Akzeptiert |
| 016 | [MCP-Protokollschicht und Registry](016-mcp-protokollschicht-und-registry.md) | Akzeptiert |
| 017 | [MCP-Tokens, Scopes und Wege zum Anlegen](017-mcp-tokens-und-scopes.md) | Akzeptiert |
| 018 | [MCP-Werkzeuge und Agentenrechte](018-mcp-werkzeuge-und-agentenrechte.md) | Akzeptiert |
| 019 | [Reaktionen „Mehr davon / Weniger“](019-reaktionen-mehr-davon-weniger.md) | Akzeptiert |
| 020 | [Anmeldung des Inhabers](020-anmeldung-des-inhabers.md) | Akzeptiert |
| 021 | [Redaktions-API](021-redaktions-api.md) | Akzeptiert |
| 022 | [Zähl-Aufruf der Statistik](022-zaehl-aufruf.md) | Akzeptiert |
| 023 | [Redaktionsmodus im Browser](023-redaktionsmodus-im-browser.md) | Akzeptiert |
| 024 | [OAuth 2.1 für die Claude-Apps](024-oauth-fuer-claude-apps.md) | Akzeptiert, Allowlist ersetzt durch 025, Refresh-Regel durch 027 |
| 025 | [Jede App per CIMD zulassen](025-jede-app-per-cimd.md) | Akzeptiert |
| 026 | [Kategorien anlegen und löschen nur durch den Inhaber](026-kategorien-anlegen-loeschen-durch-inhaber.md) | Akzeptiert |
| 027 | [Refresh-Token für jede App](027-refresh-token-fuer-jede-app.md) | Akzeptiert |
| 028 | [Zeitversetztes Veröffentlichen ohne Cron](028-zeitversetztes-veroeffentlichen-ohne-cron.md) | Akzeptiert |
| 029 | [Feed und Sitemap über die Brücke](029-feed-und-sitemap-ueber-die-bruecke.md) | Akzeptiert |
