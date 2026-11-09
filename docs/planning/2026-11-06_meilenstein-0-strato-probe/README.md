# Meilenstein 0 — Strato-Probe und Deploy-Skript

Ziel: DISPATCH steht auf der Wurzel von `https://quantum-canvas.de/` mit Brücke, gesperrtem Code-Ordner und eigener Wurzel-`.htaccess`; eine Diagnose-Route hat auf dem echten Strato-Paket gemessen, was der Server kann; `deploy.cmd` lädt hoch, baut die Platzhalter-Startseite und prüft danach, dass Sperre und Nachbarn halten. Grundlage: `docs/PROJECT.md` → Meilenstein 0, ADR-001 bis ADR-005.

## Phasen

| # | Phase | Rating | Status |
|---|---|---|---|
| 1 | [Gerüst, Brücke, Sperre, lokaler Dev-Server](phase-1-geruest.md) | standard | complete |
| 2 | [Wurzel-`.htaccess`](phase-2-wurzel-htaccess.md) | heikel | complete |
| 3 | [Diagnose-Route, Deploy-Endpunkt, Platzhalter-Builder](phase-3-diagnose-deploy-endpunkt.md) | heikel | pending |
| 4 | [`deploy.cmd` mit Rauchtest](phase-4-deploy-skript.md) | standard | pending |
| 5 | [Probe auf Strato, Messwerte festhalten, Diagnose entfernen](phase-5-strato-probe.md) | standard | pending |

## Kontrakt (Phase 3 liefert, Phase 4 und 5 verbrauchen)

Alle Antworten der Brücke sind JSON mit `Content-Type: application/json; charset=utf-8`; Fehler als `{"error": "<code>", "message": "<Satz>"}`.

| Methode + Pfad | Schutz | Antwort |
|---|---|---|
| `POST /dispatch/api/deploy` | Deploy-Token (s. u.) | 200 `{"migrations": ["<datei>", …], "built": ["index.html", "404.html", "assets/placeholder.<hash8>.css"]}` · 401 `unauthorized` · 500 `deploy_failed` mit Satz, welcher Schritt scheiterte |
| `GET /dispatch/api/diag` | Deploy-Token | 200 `{"all_ok": bool, "checks": {"<name>": {"ok": bool, "value": …}}}` — nur bis Ende Phase 5 |
| `* /dispatch/mcp` | — | immer 401 `{"error":"unauthorized","message":"MCP folgt in Meilenstein 3."}` mit Header `WWW-Authenticate: Bearer` (Platzhalter bis Meilenstein 3, damit der Rauchtest prüfbar ist) |
| jeder andere Pfad unter `/dispatch/` und `/.well-known/oauth-*` | — | 404 `not_found` |

**Deploy-Token:** Wert `DISPATCH_DEPLOY_TOKEN` aus `.env`. Angenommen aus `Authorization: Bearer <token>` (gelesen aus `HTTP_AUTHORIZATION`, dann `REDIRECT_HTTP_AUTHORIZATION`) **oder** Header `X-Dispatch-Token`. Vergleich mit `hash_equals`. Leerer oder fehlender Wert in `.env` → jede Anfrage 401. Der zweite Header existiert, damit die Diagnose auch dann erreichbar ist, wenn Strato den `Authorization`-Header verschluckt.

**`.env`-Schlüssel (Meilenstein 0):** `DISPATCH_BASE_URL`, `DISPATCH_DB_HOST`, `DISPATCH_DB_NAME`, `DISPATCH_DB_USER`, `DISPATCH_DB_PASSWORD`, `DISPATCH_DEPLOY_TOKEN`; nur lokal zusätzlich `DISPATCH_WEB_ROOT`.

## Finale Abnahme (Gesamtergebnis)

1. `https://quantum-canvas.de/` zeigt die Platzhalter-Seite (Name „DISPATCH", Unterzeile „Your agents. Your publication."), Status 200, keine Weiterleitung nach `/card/`.
2. `/card/` und `/scanner/capture` antworten 200 und tragen dieselben Header wie vor dem ersten Deploy — insbesondere keine Content-Security-Policy des Blogs (sie würde die KI-Funktion von CamScanner brechen).
3. `/dispatch-app/.env` und `/dispatch-app/config.php` antworten 403; `/dispatch/mcp` ohne Token 401.
4. Ein Doppelklick auf `deploy.cmd` mit gefüllter `deploy.env` endet mit grünem Rauchtest.
5. Die Messwerte der Diagnose (PHP-Version, Erweiterungen, Authorization-Header, MySQL, ausgehendes HTTPS, GD/WebP, Limits, Schreibrecht auf die Wurzel) stehen in `docs/knowledge/INDEX.md`; die Diagnose-Route ist danach entfernt.

## Voraussetzungen beim Inhaber (vor Phase 5)

- Im Strato-Kundenbereich eine MySQL-Datenbank anlegen und PHP 8.5 für die Domain eingestellt lassen.
- `deploy.env` aus `deploy.env.example` füllen (entsteht in Phase 4), inklusive SFTP-Fingerabdruck und einem neuen Deploy-Token.

## Summary

## Files touched

## Commits

## Deviations from plan

## Follow-ups
