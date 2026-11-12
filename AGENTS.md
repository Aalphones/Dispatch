# AGENTS.md — DISPATCH

🚧 Aktive Arbeit → STATE.md

> Wird jede Session geladen. Kurz halten — Details leben in `docs/`.

**DISPATCH — Your agents. Your publication.** Persönliche Blog-Engine in PHP: Agenten recherchieren und reichen per MCP ein, der Inhaber gibt frei, Besucher bekommen nur statische Dateien. Läuft auf der Wurzel von `https://quantum-canvas.de/`, neben CardMaker und CamScanner.

Kontext, Scope, Abweichungen vom Konzept, Meilensteine: [`docs/PROJECT.md`](docs/PROJECT.md). Fachkonzept: [`docs/design/konzept-blog-engine.md`](docs/design/konzept-blog-engine.md). Vokabular: [`docs/glossary.md`](docs/glossary.md).

---

## Code finden — erst hier, dann suchen

[`docs/code-map.md`](docs/code-map.md) zeigt Namensschema, Repo → Server und Faustregeln. Erst dort nachsehen, dann gezielt öffnen.

---

## Stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.5, kein Framework, kein Composer |
| Datenbank | MySQL (Strato), PDO |
| Website | statisches HTML aus PHP-Templates, Vanilla-JS-ES-Module |
| Hosting | Strato Shared Hosting, ein SFTP-Zugang auf `www/` |
| Deploy | `deploy.cmd` + WinSCP ([ADR-005](docs/decisions/005-deploy-skript.md)) |
| CI | GitHub Actions, PHP-Syntaxprüfung |

---

## Lokal starten

Voraussetzung lokal: PHP 8.5 (Erweiterungen `curl, fileinfo, gd, intl, mbstring, exif, openssl, pdo_mysql`) und MySQL 8.4 mit Datenbank `dispatch_dev` ([ADR-008](docs/decisions/008-zuschnitt-meilenstein-1-lokale-umgebung.md)). `dev.env.example` nach `app/.env.local` kopieren und ausfüllen ([ADR-006](docs/decisions/006-lokale-entwicklung-dev-router.md)); für die Anmeldung des Inhabers ([ADR-020](docs/decisions/020-anmeldung-des-inhabers.md)) gehören `DISPATCH_OWNER_USER`, `DISPATCH_OWNER_PASSWORD_HASH` (`echo <passwort>| php bin/dispatch hash-password`) und `DISPATCH_JWT_SECRET` (`php -r "echo bin2hex(random_bytes(32));"`) hinein, lokal zusätzlich `DISPATCH_INSECURE_COOKIES='1'`. Dann `php bin/dispatch migrate`, `php bin/dispatch seed`, `php bin/dispatch demo` (14 freigegebene Beispielartikel, nur auf leerer Datenbank), `php bin/dispatch build` (schreibt die Website nach `DISPATCH_WEB_ROOT`, lokal `public/`) und `php -S localhost:8080 -t public dev-router.php`. Für den MCP-Server: `php bin/dispatch token:create <name>` (Klartext erscheint einmalig; Scopes `--scopes=blog:read,blog:write,blog:admin`, Standard ohne `blog:admin` = 30 von 33 Tools), dann `claude mcp add --transport http dispatch http://localhost:8080/dispatch/mcp --header "Authorization: Bearer <Token>"`; per OAuth statt Token ohne `--header` hinzufügen und in Claude Code `/mcp` → `dispatch` → anmelden (lokal braucht PHP für den CIMD-Abruf `-d curl.cainfo=<ca-bundle.crt>`). `claude mcp add` überschreibt nicht — vorher `claude mcp remove dispatch`; der lokale Eintrag gilt je Ordner, in dem `claude` gestartet wird. Auf Strato: `php bin/dispatch token:create <name> --remote` und die Adresse `https://quantum-canvas.de/dispatch/mcp`; eine erste Kategorie dort: `php bin/dispatch category:create <slug> <Name> --remote`. Notfall-Weg für Claude-Apps ohne CIMD: `php bin/dispatch oauth-client:create <name> --remote` legt einen Rückfall-Client an (Client-ID und Geheimnis einmalig, in Claude unter „Erweiterte Einstellungen“ eintragen); `oauth-client:list|revoke` verwalten ihn.

---

## Conventions Index

| Topic | File |
|---|---|
| PHP | [`docs/conventions/php.md`](docs/conventions/php.md) |
| Frontend (Templates, CSS, JS) | [`docs/conventions/frontend.md`](docs/conventions/frontend.md) |
| Linting | [`docs/conventions/linting.md`](docs/conventions/linting.md) |
| Commits | [`docs/conventions/commits.md`](docs/conventions/commits.md) |
| Definition of Done | [`docs/conventions/dod.md`](docs/conventions/dod.md) |

---

## Critical Rules

1. **Nachbar-Anwendungen nie anfassen.** `/card/`, `/api/`, `/backend/` (CardMaker) und `/scanner/` (CamScanner) liegen im selben Webroot. Kein Abgleich mit Löschen auf die Wurzel; der Builder löscht nur, was in seinem Manifest steht, und schreibt nie in reservierte Namen ([ADR-001](docs/decisions/001-pfade-auf-gemeinsamer-domain.md)).
2. **Die Wurzel-`.htaccess` vererbt sich in alle Nachbarordner.** Global nur HTTPS, `-Indexes`, `DirectoryIndex`; CSP, Header, `ErrorDocument` und Caching nur im Block, der die Nachbarn ausnimmt ([ADR-003](docs/decisions/003-wurzel-htaccess.md)).
3. **PHP-Endpunkte nur unter `/dispatch/`** (dazu `/.well-known/oauth-*`). `/api/` gehört CardMaker.
4. **`dispatch-app/` ist gesperrt und enthält `.env`.** Nach jedem Deploy muss `/dispatch-app/.env` mit 403 antworten ([ADR-002](docs/decisions/002-code-im-webroot-gesperrt.md)).
5. **Nichts geht ohne Freigabe des Inhabers online.** Kein MCP- oder OAuth-Token kann veröffentlichen, Vorschläge übernehmen oder Formvorgaben ändern.
6. **`docs/PROJECT.md` und die ADRs schlagen das Fachkonzept**, wo beide sich widersprechen.

---

## Wo du mehr findest

- **Architektur-Entscheidungen:** [`docs/decisions/`](docs/decisions/)
- **Aktive Planung:** [`docs/planning/`](docs/planning/) · **Archiv:** [`docs/archive/`](docs/archive/)
- **Datenmodell:** [`docs/models.md`](docs/models.md) (Quelle: die Migrationen); Fachkonzept → „Datenmodell"
- **Projektwissen und Lücken:** [`docs/knowledge/`](docs/knowledge/) · **Projekt-Memory:** [`docs/memory/memory-index.md`](docs/memory/memory-index.md)
