# Phase 6 — Strato, Abnahme mit echten Clients, Doku

Rating: standard

Status: complete

## Kontext (vorher lesen)

- `README.md` dieses Plans → „Finale Abnahme“.
- `docs/conventions/dod.md`, `docs/conventions/commits.md`, `deploy.cmd` (Ablauf), ADR-005, ADR-017.
- `docs/knowledge/INDEX.md` (Strato-Messwerte: `Authorization` kommt an).
- Fachkonzept → „Anbindung an Claude“ → „Einrichten“ (Punkt 3).
- Fehlerklassen geprüft (Vault): keine einschlägig.

## AK der Phase

1. Lokal: Finale Abnahme Punkte 1, 2, 4, 5 belegt (Inspector mit beiden Versionen; Scope- und Token-Fälle; Vorschläge und `not_changeable`; Reaktion im Briefing, `get_stats`).
2. `deploy.cmd` grün; die Deploy-Route hat `010` und `011` migriert (Antwort des Deploy-Endpunkts zeigt sie).
3. `php bin/dispatch token:create claude-code --remote` legt auf Strato ein Token an; `claude mcp add --transport http dispatch https://quantum-canvas.de/dispatch/mcp --header "Authorization: Bearer …"`; `/mcp` in Claude Code zeigt den Server verbunden mit 33 Tools.
4. Finale Abnahme Punkt 3 (Lauf von `add_to_queue` bis `submit_post`) gegen Strato in Claude Code durchgespielt; Artikel-ID, Auftrag-ID und Status im Report-Back. Der Testartikel bleibt in `review` (Freigabe gibt es erst mit Meilenstein 4) — im Report-Back als offener Rest vermerken.
5. Finale Abnahme Punkt 6: `/card/`, `/scanner/capture` 200 wie vorher, `/dispatch-app/.env` 403, `GET /dispatch/mcp` 405, `POST /dispatch/mcp` ohne Token 401.

## Checkliste

- [x] Lokale Abnahme (AK 1); Ergebnisse je Punkt im Report-Back.
- [x] Deploy, Token remote anlegen, Claude Code verbinden (AK 2, 3). Das Token nie in Repo, Plan, Report oder Commit schreiben.
- [x] Lauf in Claude Code (AK 4).
- [x] Nachbarn und Sperren prüfen (AK 5).
- [x] `AGENTS.md` → „Lokal starten“: Token anlegen (`php bin/dispatch token:create <name>`) und `claude mcp add …` gegen `http://localhost:8080/dispatch/mcp`.
- [x] `docs/PROJECT.md`: Meilenstein 3 „(erledigt)“ mit Plan-Pfad; Abweichungstabelle um ADR-017 (Tokens per CLI und `--remote`), ADR-018 (kein `complete_queue_item`, Agenten-Rechte, kein Vorschau-Link), ADR-019 (Reaktion je Artikel) ergänzen; Zeile „CLI mit `token:create`“ aktualisieren.
- [x] `docs/knowledge/INDEX.md`: Messwerte MCP auf Strato (Antwortzeit `tools/list`, Ergebnis des Inspector-Laufs, Claude-Code-Version).
- [x] `docs/knowledge/GAPS.md`: offene Punkte aus `FINDINGS.md`, die nicht erledigt sind.
- [x] Plan abschließen nach `mode-implementing` (Bottom-Sektionen der README, Archiv, `STATE.md`).

## Report-Back

Ergebnis je Punkt (2026-11-10/11):

- AK 1 lokal: Protokoll 2026-07-28 und 2025-11-25 listen je 33 Tools, 3 Prompts, 6 Resources, 1 Vorlage (per `node`-Skript, kein Inspector). `blog:read`-Token sieht 17 Tools; `create_post` damit → HTTP 403, Fehlercode -32003 mit `required_scope`. Ohne, falsches und widerrufenes Token → 401. `update_settings` und `update_document` ergeben Änderungsvorschläge (`applied: []`); `min_sources` → `not_changeable`. Reaktion „more“ steht im Briefing; `get_stats` liefert Tageswerte aus von Hand eingefügten Zeilen. Testdaten und -tokens danach entfernt/widerrufen.
- AK 2: `deploy.cmd app` grün, Antwort des Deploy-Endpunkts: `migrations: [010_token.sql, 011_reaction.sql]`.
- AK 3: Token auf Strato (ID 1, `blog:read blog:write`), `claude mcp add`, Claude Code 2.1.322 zeigt `dispatch` verbunden — **30 Tools**, nicht 33: die drei Tools mit `blog:admin` fehlen im Standard-Token (gewollt, geringste Rechte). Mit `blog:admin` wären es 33.
- AK 4: Lauf in Claude Code (Sonnet, headless `claude -p`) gegen Strato: Artikel-ID 1, Auftrag-ID 1; Artikel `review`, Auftrag `review`, nichts öffentlich. Offener Rest: Der Testartikel bleibt in `review` (Freigabe erst mit Meilenstein 4).
- AK 5: `/card/` 200, `/scanner/capture` 200, `/dispatch-app/.env` 403, `GET /dispatch/mcp` 405, `POST /dispatch/mcp` ohne Token 401.

Abweichungen: (1) Der Rauchtest in `deploy.cmd` erwartete 401 für `GET /dispatch/mcp`; jetzt GET → 405 und POST → 401. (2) Strato hatte keine Kategorie, `submit_post` scheiterte an `category_count`; Agenten können keine direkt anlegen. Neu: `php bin/dispatch category:create <slug> <Name> [--remote]` und Route `POST /dispatch/api/categories` (Deploy-Token, handelt als Inhaber); Kategorie `testlauf` (ID 1) auf Strato angelegt. (3) Der Endpunkt `categories` und der Befehl wurden nur auf Strato geprüft, lokal startete MySQL nicht (Datenverzeichnis unbekannt).
