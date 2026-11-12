# DISPATCH

**Your agents. Your publication.**

DISPATCH ist eine persönliche Blog-Engine in PHP, die wie ein Verlag arbeitet: KI-Agenten in Claude Code recherchieren, schreiben und reichen Artikel über einen MCP-Server ein. Die Engine prüft nur die Form und legt jeden Artikel zur Freigabe vor. Erst nach der Freigabe erzeugt PHP daraus statische Seiten — Besucher bekommen ausschließlich fertige HTML-, CSS- und JS-Dateien.

Läuft auf Strato Shared Hosting unter `https://quantum-canvas.de/`, ohne Framework, ohne Composer, ohne Hintergrundprozesse.

Projekt-Kontext und Architektur: [`AGENTS.md`](AGENTS.md) → [`docs/PROJECT.md`](docs/PROJECT.md).

## Stand

Projektgerüst und Entscheidungen stehen, Code entsteht ab Meilenstein 0 (Strato-Probe und Deploy-Skript).

## Quickstart

### Voraussetzungen

- PHP 8.5 (portabel genügt) mit `pdo_mysql`, `mbstring`, `intl`, `dom`, `fileinfo`, `gd`, `curl`, `openssl`
- MySQL oder MariaDB lokal
- Für den Deploy: [WinSCP](https://winscp.net) als portables Paket (`WinSCP.com`) und `curl` (ab Windows 10 enthalten)

### Lokal starten

```
php -S localhost:8080 -t public dev-router.php
```

Der Dev-Router bildet die `.htaccess`-Regeln nach; der Builder schreibt die Website nach `public/`.

### Connector einrichten

- **KI-App mit Connector-Anmeldung (Claude, ChatGPT, …):** Connector hinzufügen mit der Adresse `https://quantum-canvas.de/dispatch/mcp`, dann in der Zustimmungsseite des Blogs (Anmeldung nötig) den angezeigten Absender prüfen und Zugriff erlauben. Jede App mit Client-Metadatendokument ist zugelassen (ADR-025); Verbundene Apps stehen im Dialog „Zugänge“ und lassen sich dort trennen.
- **Lokale Modelle und Apps ohne Anmeldung:** festes Token wie bei Claude Code (unten) als `Authorization: Bearer <Token>` eintragen.
- **Claude Code per Anmeldung:** `claude mcp add --transport http dispatch https://quantum-canvas.de/dispatch/mcp`, dann `/mcp` → `dispatch` → anmelden.
- **Claude Code per festem Token:** `php bin/dispatch token:create <name> --scopes=blog:read,blog:write,blog:admin --remote`, dann `claude mcp add --transport http dispatch <Adresse> --header "Authorization: Bearer <Token>"`. Nur ein Eintrag je Name und Ordner; vorher `claude mcp remove dispatch`.
- **Notfall ohne CIMD:** `php bin/dispatch oauth-client:create <name> --remote`, Client-ID und Geheimnis in der Claude-App unter „Erweiterte Einstellungen“ eintragen.

### Deployen

1. `deploy.env.example` nach `deploy.env` kopieren und ausfüllen (landet nicht im Git). Den SFTP-Fingerabdruck und den Erzeugungsbefehl für den Deploy-Token erklärt die Vorlage.
2. Für die Anmeldung auf der Website drei Werte in `deploy.env` eintragen (ohne sie antwortet die Anmeldung mit 503 `not_configured`):
   - `DISPATCH_OWNER_USER` — Login-Name, frei wählbar, ohne `'`.
   - `DISPATCH_OWNER_PASSWORD_HASH` — bcrypt-Hash des Passworts, nie das Passwort selbst. Erzeugen: `echo <passwort>| php bin/dispatch hash-password`. Den Hash mit allen `$` unverändert und ohne Anführungszeichen einfügen.
   - `DISPATCH_JWT_SECRET` — mindestens 64 Hex-Zeichen. Erzeugen: `php -r "echo bin2hex(random_bytes(32));"`.

   Lokal dieselben Namen in `app/.env.local` (Vorlage `dev.env.example`, dort zusätzlich `DISPATCH_INSECURE_COOKIES=1`).
3. `deploy.cmd` doppelklicken — oder `deploy.cmd app` (Code und Brücke) bzw. `deploy.cmd root` (nur Wurzel-`.htaccess`) für Teile.

Vor dem Überschreiben sichert das Skript die vorhandene Wurzel-`.htaccess` nach `deploy-backup\root.htaccess`.

Das Skript prüft, lädt nur die eigenen Ordner hoch, führt Migrationen und Rebuild aus und prüft danach, dass Blog, Sperre und Nachbar-Anwendungen antworten ([ADR-005](docs/decisions/005-deploy-skript.md)).
