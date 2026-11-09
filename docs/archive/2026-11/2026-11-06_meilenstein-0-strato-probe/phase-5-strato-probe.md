# Phase 5 — Probe auf Strato, Messwerte festhalten, Diagnose entfernen

**Rating:** standard · **Status:** complete

## Kontext (vor dem ersten Schritt lesen)

- README dieses Plans → „Kontrakt", „Finale Abnahme", „Voraussetzungen beim Inhaber"
- Report-Back von Phase 3 (falls der Migrator lokal nicht geprüft werden konnte, hier nachholen)
- `docs/conventions/dod.md`, `docs/knowledge/INDEX.md`, `docs/PROJECT.md` → „Offene Fragen", „Stack"
- Fehlerklassen: `~/.claude/knowledge/topics/strato-shared-hosting.md` → Zeile „aus einem `200` auf `/.env` schließen" (Status **und** Inhalt prüfen, Gegencheck mit erfundenem Pfad)
- Zugangsdaten: `deploy.env` **nie** ausgeben, nicht `cat`/`Get-Content` auf die ganze Datei. Den Token für Handaufrufe per PowerShell in eine Variable lesen (`(Select-String -Path deploy.env -Pattern '^DISPATCH_DEPLOY_TOKEN=').Line.Split('=',2)[1]`) und nur die Antwort zeigen.

## Schritte

0. **Voraussetzungen belegen:** `Test-Path deploy.env` ist `True`; in `C:\Users\sasch\develop\CardMaker\deploy.env` gibt es keine aktive Zeile `^REMOTE_ROOT_PATH` (Grep, Anzahl 0 — Stand bei Planung: 0). Fehlt etwas → 🔴 an den Inhaber, nicht weiter.
1. **Vorher-Abzug:** `curl -sI` auf `https://quantum-canvas.de/card/` und `https://quantum-canvas.de/scanner/capture`, Header-Blöcke im Scratchpad sichern (Vergleichsbasis).
2. **Erster Deploy:** `cmd /c "echo. | .\deploy.cmd"`. Grün → weiter. Schritt 6 mit 401 → Diagnose (Schritt 3) mit **nur** `X-Dispatch-Token` aufrufen; zeigt `authorization_header` alles `false`, ist der Header-Verlust belegt → in FINDINGS eintragen, 🔴 an den Inhaber (Rückfall-Header für Deploy und spätere MCP-Tokens entscheiden). Anderer Fehler → Ursache aus Antwort bzw. `deploy-backup\root.htaccess` bewerten; bricht die Wurzel, sofort `deploy-backup\root.htaccess` per WinSCP zurückspielen.
3. **Diagnose:** `GET https://quantum-canvas.de/dispatch/api/diag` mit `Authorization: Bearer` **und** `X-Dispatch-Token`; JSON im Scratchpad sichern. Jede Prüfung mit `ok: false` → FINDINGS-Eintrag + 🔴, Route bleibt dann bis zur Klärung stehen.
4. **Wirkung der Wurzel-`.htaccess`** per `curl` belegen:
   - `http://quantum-canvas.de/` → 301 auf `https://`
   - `https://quantum-canvas.de/` → 200, Platzhalter-HTML, Header `Content-Security-Policy` vorhanden, `Cache-Control: public, max-age=300`
   - `/assets/placeholder.<hash>.css` → 200, `Cache-Control: …immutable`
   - `/gibt-es-nicht-<zufall>` → 404 mit DISPATCH-404-Seite
   - `/.well-known/oauth-authorization-server` → 404 JSON `not_found` der Brücke (belegt die Umschreibung)
   - `/dispatch-app/.env` und `/dispatch-app/config.php` → 403, Antworttext ist eine Apache-Fehlerseite, nicht der Platzhalter
   - `/card/`, `/scanner/capture` → 200, **ohne** `Content-Security-Policy` des Blogs; Header-Block gleich dem Vorher-Abzug aus Schritt 1 (Abweichungen nur bei `Date`, `ETag`, Längen)
5. **Messwerte festhalten:** `docs/knowledge/INDEX.md` neuer Eintrag „Strato-Messwerte (Datum)": PHP-Version und SAPI, Erweiterungen, GD/WebP, EXIF, Authorization-Header (welche Quelle), MySQL-Version, ausgehendes HTTPS, Limits, `open_basedir`, Schreibrecht Wurzel/Storage. `docs/PROJECT.md`: Offene Frage „Upload-Grenzen" entfernen (Werte stehen jetzt in INDEX), im Stack „Phase 0 bestätigt sie" durch die gemessene Version ersetzen.
6. **Diagnose entfernen:** `app/src/Api/DiagController.php` löschen, Route aus `app/index.php` entfernen, Kontrakt-Zeile in der README als „entfernt in Phase 5" markieren. `cmd /c "echo. | .\deploy.cmd app"` → grün; `GET /dispatch/api/diag` → 404.
7. **CardMaker nachziehen** (ADR-003 → Konsequenzen): vorher `git -C C:\Users\sasch\develop\CardMaker status --short` — nicht leer → 🔴, nichts anfassen. Leer → in `www-root/.htaccess` den Kopfkommentar auf „wird nicht mehr hochgeladen, Zuständigkeit bei DISPATCH (`web-root/.htaccess`, DISPATCH ADR-003)" ändern und in `docs/decisions/020-gemeinsamer-webroot-mit-pfaden.md` einen Nachtrag mit Datum ergänzen (eine akzeptierte ADR nicht umschreiben, nur Nachtrag anhängen); Commit nach CardMakers Konventionen.

## Abnahme (Phase)

- [ ] Finale Abnahme der README Punkt 1–5 einzeln belegt (Adresse, Status, relevanter Header-/Antwortauszug ohne Zugangsdaten)
- [ ] Alle Diagnose-Prüfungen `ok: true` (oder offene Punkte als 🔴 beim Inhaber)
- [ ] Diagnose-Route antwortet 404

## Checkliste

- [ ] Schritte 0–7
- [ ] `docs/code-map.md`: `DiagController` wieder austragen
- [ ] README: Status aller Phasen, Bottom-Sektionen füllen; Plan nach `docs/archive/2026-11/` (Ablauf `mode-implementing`)

## Report-Back
