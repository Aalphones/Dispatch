# Phase 4 — `deploy.cmd` mit Rauchtest

**Rating:** standard · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- `docs/decisions/005-deploy-skript.md` — Ablauf, Ziele, Rauchtest (verbindlich)
- README dieses Plans → „Kontrakt" (Deploy-Endpunkt, `.env`-Schlüssel)
- Vorlage, Aufbau 1:1 übernehmen: `C:\Users\sasch\develop\CardMaker\deploy.cmd` (Einlesen vor `enabledelayedexpansion`, `:needValue`, `:writeSession`, getrennter Vorlauf mit `option batch continue`, Fehlertexte) und `…\CardMaker\deploy.env.example` (Kommentarstil, Fingerabdruck-Anleitung)
- Fehlerklassen: `~/.claude/knowledge/topics/windows-details.md` → Abschnitt „Batch: Zugangsdaten & Zeilenenden" (Reihenfolge der verzögerten Erweiterung, CRLF, Fehlerweg ohne `pause` prüfen); `~/.claude/knowledge/topics/strato-shared-hosting.md` → Zeilen zu `synchronize remote`, Schalter-Reihenfolge, Zugangsdaten als Schalter, `-hostkey=*`, Werte in einfachen Anführungszeichen

## Entscheidungen (fest)

- Ziele: ohne Angabe `all`, `app` (Code + Brücke + `.env` + Nachlauf), `root` (nur Wurzel-`.htaccess`). Rauchtest läuft bei jedem Ziel.
- Zielpfade leiten sich aus **einem** Wert ab: `REMOTE_WEB_ROOT` (Ordner, den der SFTP-Login als `www/` sieht, Vorgabe `/`) → `…dispatch/`, `…dispatch-app/`, `….htaccess`.
- **Sicherung vor dem Überschreiben:** Der Vorlauf holt die vorhandene Wurzel-`.htaccess` nach `deploy-backup\root.htaccess` (überschreibt die vorige Sicherung). So lässt sich ein Fehlgriff an der geteilten Datei per Hand zurückspielen.
- Deploy-Token und Zugangsdaten nur aus Zeichen `[A-Za-z0-9]` für den Token (Vorlage nennt den Erzeugungsbefehl); Passwörter beliebig außer `"` (wie CardMaker).
- `curl` ohne `-L`: eine Weiterleitung ist im Rauchtest ein Fehler, kein Erfolg.

## Dateien

| Datei | Inhalt |
|---|---|
| `deploy.cmd` | Schritte `[1/7]`…`[7/7]` laut ADR-005, Details unten. CRLF (per `.gitattributes`). |
| `deploy.env.example` | Abschnitte „Hochladen" (`WINSCP_PATH` mit Vorgabe `H:\Meine Ablage\U600_Software\WinSCP-6.5.1-Portable\WinSCP.com` — der Pfad, den CardMaker auf dieser Maschine nutzt, `SFTP_PROTOCOL`, `SFTP_HOST`, `SFTP_USER`, `SFTP_PASSWORD`, `SFTP_HOSTKEY`, `REMOTE_WEB_ROOT=/`), „Prüfen" (`PHP_PATH=C:\Users\sasch\develop\.tools\php\php.exe`, `PUBLIC_BASE_URL=https://quantum-canvas.de`, `NEIGHBOR_URLS=/card/ /scanner/capture`), „Wird nach app/.env geschrieben" (`DISPATCH_DB_HOST`, `DISPATCH_DB_NAME`, `DISPATCH_DB_USER`, `DISPATCH_DB_PASSWORD`, `DISPATCH_DEPLOY_TOKEN` mit Hinweis `php -r "echo bin2hex(random_bytes(32));"`). |
| `.gitignore` | `deploy-backup/` ergänzen. |

### Ablauf in `deploy.cmd`

1. **Konfiguration:** Ziel prüfen; `deploy.env` vorhanden; einlesen (`for /f "usebackq eol=# tokens=1,* delims=="`) **vor** `setlocal enabledelayedexpansion`; Pflichtwerte per `:needValue` (alle aus der Vorlage außer `PHP_PATH`, `SFTP_HOSTKEY`); `WinSCP.com` existiert; bei `sftp` ein `SFTP_HOSTKEY`, und der ist nicht `*`.
2. **Lokal prüfen** (nur wenn `PHP_PATH` gesetzt und vorhanden, sonst Hinweiszeile): `for /r` über `app\*.php`, `bridge\*.php` und — falls vorhanden — `bin\*`; jede Datei `"!PHP_PATH!" -l`; ein Fehler → Dateiname ausgeben, am Ende abbrechen „Es wird nichts hochgeladen."
3. **`app\.env` schreiben** (Ziele `all`, `app`): `DISPATCH_BASE_URL='!PUBLIC_BASE_URL!'` und die fünf `DISPATCH_*`-Werte, jeweils in einfachen Anführungszeichen, Kommentar warum.
4. **Vorlauf** (getrenntes WinSCP-Skript, `option batch continue`, Ausgabe verworfen): `mkdir` für `dispatch/`, `dispatch-app/`, `dispatch-app/storage/`, `dispatch-app/storage/logs/`, `dispatch-app/storage/media/`; `get "!REMOTE_WEB_ROOT!.htaccess" "deploy-backup\root.htaccess"` (vorher `if not exist deploy-backup mkdir deploy-backup`).
5. **Hochladen** (eigenes WinSCP-Skript, `option batch abort`): Ziel `all`/`app`: `synchronize remote -delete -filemask="|storage/;.env.local" "app" "!REMOTE_WEB_ROOT!dispatch-app/"` und `synchronize remote -delete "bridge" "!REMOTE_WEB_ROOT!dispatch/"`; Ziel `all`/`root`: `put "web-root\.htaccess" "!REMOTE_WEB_ROOT!.htaccess"`. Schalter vor den Verzeichnissen. Nie ein Abgleich auf `!REMOTE_WEB_ROOT!` selbst. WinSCP-Fehlercode ≠ 0 → Abbruch mit Hinweis „eventuell nur halb aktualisiert".
6. **Nachlauf** (Ziele `all`, `app`): `curl -s -o "%TEMP%\dispatch-deploy-%RANDOM%.json" -w "%%{http_code}" -X POST -H "Authorization: Bearer !DISPATCH_DEPLOY_TOKEN!" "!PUBLIC_BASE_URL!/dispatch/api/deploy"`, Status per `for /f` einlesen. ≠ 200 → Status und Antworttext ausgeben, bei 401 zusätzlich: „Kommt der Authorization-Header nicht an? Diagnose mit X-Dispatch-Token prüfen." → Abbruch. Temporäre Datei löschen.
7. **Rauchtest:** Unterprogramm `:expect <pfad> <status>` (curl `-s -o NUL -w "%%{http_code}"`, Vergleich, Fehlschlag einzeln melden, Zähler erhöhen): `/` 200, `/dispatch-app/.env` 403, `/dispatch-app/config.php` 403, `/dispatch/mcp` 401, jede Adresse aus `NEIGHBOR_URLS` 200. Zähler > 0 → „Rauchtest: <n> Fehlschläge — der Server ist eventuell nur halb aktualisiert." und Exit 1; sonst Zusammenfassung wie CardMaker `:done`.

## Abnahme (Phase)

Ohne Server prüfbar — jeweils per `cmd /c "echo. | .\deploy.cmd <ziel>"` aus PowerShell im Repo-Root:

- [ ] Ohne `deploy.env` → Meldung „Die Datei deploy.env fehlt", Exit 1, nichts hochgeladen.
- [ ] Mit Kopie der Vorlage (Pflichtwerte leer) → je fehlendem Wert eine Zeile, Exit 1.
- [ ] Unbekanntes Ziel `foo` → Meldung mit erlaubten Zielen.
- [ ] `SFTP_HOSTKEY=*` → Abbruch mit Erklärung.
- [ ] Vorübergehend eine Datei mit Syntaxfehler unter `app/` → Schritt 2 nennt sie und bricht ab, bevor `app\.env` geschrieben wird. Datei danach löschen, Probe-`deploy.env` löschen.
- [ ] `git ls-files --eol deploy.cmd` zeigt `w/crlf` nach dem Commit (bzw. `i/lf w/crlf`).

## Checkliste

- [ ] `deploy.cmd`, `deploy.env.example` anlegen, `.gitignore` ergänzen
- [ ] Abnahme fahren, Ergebnisse in „Report-Back"
- [ ] `docs/code-map.md` → Zeile `deploy.cmd` um `deploy.env.example`, `deploy-backup/` ergänzen
- [ ] `README.md`: Abschnitt „Deploy" (Ziele, `deploy.env` anlegen, Fingerabdruck holen — Verweis auf die Vorlage)

## Report-Back

Abnahme ohne Server bestanden: fehlende `deploy.env`, leere Pflichtwerte (je Wert eine Zeile), Ziel `foo`, `SFTP_HOSTKEY=*`, Syntaxfehler unter `app/` (Abbruch vor dem Schreiben von `app\.env`) — jeweils Exit 1. CRLF auf Platte geprüft; `git ls-files --eol` erst nach dem Commit aussagekräftig.

Ungeprüft bis Phase 5 (braucht Server): Vorlauf mit Sicherung, Upload, Nachlauf, Rauchtest-Schleife (`:expect`), Token-Zeichenprüfung mit gültigem Token lief nur im Lint-Test mit.
