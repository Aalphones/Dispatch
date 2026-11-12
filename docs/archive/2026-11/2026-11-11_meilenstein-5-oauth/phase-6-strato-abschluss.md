# Phase 6 — Strato, Abnahme mit Claude-App und Claude Code, Doku

## Kontext

- `README.md` → „Finale AK“.
- `~/.claude/knowledge/topics/strato-shared-hosting.md` (vor dem Deploy lesen), ADR-005 (Deploy), `deploy.cmd`, `deploy.env`.
- `docs/knowledge/INDEX.md` (gemessene Strato-Werte: ausgehendes HTTPS, Authorization-Kopf).
- Archiv Meilenstein 4, Phase 10 (Ablauf einer Strato-Abnahme; Lehre: Login-Bremse zuletzt prüfen).
- Fehlerklassen geprüft: keine einschlägig.

## AK der Phase

1. Deploy mit `deploy.cmd`; Migration 014 läuft über den Deploy-Endpunkt; Neubau schreibt `anmelden/zustimmung/index.html`. Nachbarn `/card/`, `/scanner/` erreichbar, `/dispatch-app/.env` → 403.
2. `curl` gegen Strato: beide Discovery-Dokumente mit `https://quantum-canvas.de`-Werten; `POST /dispatch/mcp` ohne Token → 401 mit `resource_metadata`.
3. Finale AK 1–7 auf Strato vom Inhaber in der Claude-App (Android und Browser) und in Claude Code bestätigt; AK 3 (über eine Stunde) und AK 4 (Wiederverwendung, per `curl` mit einem eigens erzeugten Refresh-Token) belegt. Die tatsächliche `client_id` der Claude-App aus dem Log (`oauth_clients`) notieren.
4. Finale AK 8: Rückfall-Client per `--remote` anlegen, Token-Tausch per `curl`, danach widerrufen.
5. Doku: `docs/PROJECT.md` (Meilenstein 5 erledigt, Verweis aufs Archiv; Abweichungstabelle: Rückfall-Client nur Kommandozeile, Zustimmung nach eigenem Entwurf, Tokens undurchsichtig), `README.md` des Repos (Connector einrichten), `AGENTS.md` „Lokal starten“ (OAuth mit Claude Code), `docs/knowledge/INDEX.md` (gemessene Werte: `client_id` der Claude-App, Antwortzeiten von `/authorize` und `/token`).
6. Plan archivieren nach `docs/archive/2026-11/2026-11-11_meilenstein-5-oauth/`, `STATE.md` → nächster Schritt Meilenstein 6.

## Checkliste

- [x] Deploy, Rauchtest (2026-11-12: Migration 014 gelaufen, Rauchtest grün, Discovery-Dokumente und 401-Kopf mit `https://quantum-canvas.de`, `/anmelden/zustimmung/` 200, `/card/`, `/scanner/` 200, `/dispatch-app/.env` 403)
- [~] Abnahme mit dem Inhaber: Claude-App Android verbunden; Browser und Claude Code per OAuth nicht abgenommen (Entscheidung 2026-11-12)
- [ ] `curl`-Belege: fremder Host belegt (302 auf `?fehler=unbekannte_app`, 0,36 s); Rückfall-Client angelegt, Token-Endpunkt lehnt falsches Geheimnis ab (401 `invalid_client`), richtiges Geheimnis + falscher Code → `invalid_grant`, nach Widerruf `invalid_client`. Offen: Refresh/Wiederverwendung (braucht echtes Refresh-Token aus einer Zustimmung des Inhabers), voller Token-Tausch des Rückfall-Clients
- [x] Doku, Archiv, `STATE.md` (2026-11-12; Abnahme von AK 2–4, 6 und Browser als offene Lücke im Archiv-README)

## Report-Back
