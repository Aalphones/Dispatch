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

### Deployen

1. `deploy.env.example` nach `deploy.env` kopieren und ausfüllen (landet nicht im Git). Den SFTP-Fingerabdruck und den Erzeugungsbefehl für den Deploy-Token erklärt die Vorlage.
2. `deploy.cmd` doppelklicken — oder `deploy.cmd app` (Code und Brücke) bzw. `deploy.cmd root` (nur Wurzel-`.htaccess`) für Teile.

Vor dem Überschreiben sichert das Skript die vorhandene Wurzel-`.htaccess` nach `deploy-backup\root.htaccess`.

Das Skript prüft, lädt nur die eigenen Ordner hoch, führt Migrationen und Rebuild aus und prüft danach, dass Blog, Sperre und Nachbar-Anwendungen antworten ([ADR-005](docs/decisions/005-deploy-skript.md)).
