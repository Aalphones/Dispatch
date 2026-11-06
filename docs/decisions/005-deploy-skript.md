# 005 — Deploy per Skript, nur eigene Ordner, mit Nachprüfung

**Status:** Akzeptiert (2026-11-06) · umgesetzt in Meilenstein 0

## Kontext

Kein SSH, kein Cron, kein Composer auf dem Server; hochgeladen wird per SFTP. Im selben Webroot liegen fremde Anwendungen, und der Builder schreibt die Website direkt in die Wurzel. Ein Abgleich mit Löschen auf die Wurzel würde Nachbarn und die gebaute Website wegräumen. Vorbild ist `deploy.cmd` von CardMaker, das auf demselben Paket läuft.

## Entscheidung

`deploy.cmd` im Repo-Root, per Doppelklick. Ziele: ohne Angabe alles, `app` nur Code und Brücke, `root` nur die Wurzel-`.htaccess`. Einstellungen aus `deploy.env` (nicht im Git), Vorlage `deploy.env.example`.

Ablauf, jeder Schritt bricht bei Fehler ab, bevor etwas hochgeladen wird:

1. **Konfiguration prüfen:** `deploy.env` vorhanden, alle Pflichtwerte gesetzt, `WinSCP.com` gefunden, bei SFTP ein Fingerabdruck (nie `*`).
2. **Lokal prüfen:** `php -l` über `app/`, `bridge/` und `bin/`, wenn `PHP_PATH` gesetzt ist.
3. **`app/.env` schreiben** aus `deploy.env`, Werte in einfachen Anführungszeichen.
4. **Vorlauf:** fehlende Zielordner anlegen (`dispatch/`, `dispatch-app/`, `dispatch-app/storage/…`), getrennt und ungeprüft, weil WinSCP beim Abgleich keine Ordner anlegt.
5. **Hochladen:** `synchronize remote -delete` ausschließlich auf `www/dispatch-app/` (ausgenommen `storage/`) und `www/dispatch/`. Die Wurzel-`.htaccess` per `put`. **Nie ein Abgleich auf die Wurzel selbst.**
6. **Nachlauf:** `curl` ruft `POST /dispatch/api/deploy` mit `DISPATCH_DEPLOY_TOKEN` auf; der Endpunkt führt offene Migrationen aus und baut die Website neu.
7. **Rauchtest per `curl`:** `/` antwortet 200, `/dispatch-app/.env` antwortet 403, `/dispatch/mcp` ohne Token antwortet 401, und jede Adresse aus `NEIGHBOR_URLS` (mindestens `/card/` und `/scanner/capture`) antwortet 200. Jeder Fehlschlag wird einzeln gemeldet, mit dem Hinweis, dass der Server eventuell nur halb aktualisiert ist.

Zugangsdaten gehen als eigene Schalter an WinSCP (`-username`, `-password`), nie in der Verbindungsadresse.

## Konsequenzen

- Ein grüner Lauf heißt: Code oben, Migrationen durch, Website gebaut, Sperre wirkt, Nachbarn erreichbar.
- `curl` ist ab Windows 10 Teil des Systems; sonst braucht das Skript keine weiteren Werkzeuge außer WinSCP und optional PHP.
- Der Deploy-Endpunkt ist eine bewusste Ausnahme vom Login: er nimmt nur `DISPATCH_DEPLOY_TOKEN`, vergleicht zeitkonstant und kann nichts veröffentlichen, was nicht schon freigegeben ist.
