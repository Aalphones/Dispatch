# 002 — Programmcode im Webroot, gesperrt; Zugangsdaten in `.env`

**Status:** Akzeptiert (2026-11-06)

## Kontext

Das Konzept legt `app/` und `storage/` über den Document Root. Der einzige SFTP-Zugang sieht aber nur `www/`, den ausgelieferten Ordner. CardMaker steht vor derselben Lage und löst sie mit einem gesperrten Ordner im Webroot plus einer Brücke, die den Code per Dateizugriff einbindet — das läuft auf diesem Paket.

Zugangsdaten wollte das Konzept per `SetEnv` in der `.htaccess` setzen, mit `.env` als Rückfall.

## Entscheidung

- Code, Templates, Theme-Quellen, Migrationen, `storage/` (Medien-Originale, Logs) und `.env` liegen unter `www/dispatch-app/`. Dessen `.htaccess` sperrt alles mit `Require all denied`.
- Die Brücke `www/dispatch/index.php` bindet `../dispatch-app/index.php` ein und reicht den `Authorization`-Header durch.
- Zugangsdaten stehen ausschließlich in `www/dispatch-app/.env`. `deploy.cmd` erzeugt sie aus `deploy.env`, Werte in einfachen Anführungszeichen. Kein `SetEnv`: die Wurzel-`.htaccess` vererbt sich in die Nachbarordner, und Geheimnisse gehören nicht in eine Datei, die mehrere Anwendungen betrifft.
- `storage/` wird beim Deploy nie abgeglichen, nur beim ersten Mal angelegt.

## Konsequenzen

- Der Schutz hängt an einer Datei. Nach jedem Deploy prüft das Skript, dass `/dispatch-app/.env` und `/dispatch-app/config.php` mit 403 antworten (ADR-005). Beim Bewerten auf den Status achten, nicht nur auf „kein Fehler".
- Wer später einen Zugang oberhalb von `www/` bekommt, verschiebt `dispatch-app/` dorthin und passt nur die eine Zeile in der Brücke an.
