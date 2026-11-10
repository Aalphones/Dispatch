# Phase 6 — Strato, Abnahme mit echten Clients, Doku

Rating: standard

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

- [ ] Lokale Abnahme (AK 1); Ergebnisse je Punkt im Report-Back.
- [ ] Deploy, Token remote anlegen, Claude Code verbinden (AK 2, 3). Das Token nie in Repo, Plan, Report oder Commit schreiben.
- [ ] Lauf in Claude Code (AK 4).
- [ ] Nachbarn und Sperren prüfen (AK 5).
- [ ] `AGENTS.md` → „Lokal starten“: Token anlegen (`php bin/dispatch token:create <name>`) und `claude mcp add …` gegen `http://localhost:8080/dispatch/mcp`.
- [ ] `docs/PROJECT.md`: Meilenstein 3 „(erledigt)“ mit Plan-Pfad; Abweichungstabelle um ADR-017 (Tokens per CLI und `--remote`), ADR-018 (kein `complete_queue_item`, Agenten-Rechte, kein Vorschau-Link), ADR-019 (Reaktion je Artikel) ergänzen; Zeile „CLI mit `token:create`“ aktualisieren.
- [ ] `docs/knowledge/INDEX.md`: Messwerte MCP auf Strato (Antwortzeit `tools/list`, Ergebnis des Inspector-Laufs, Claude-Code-Version).
- [ ] `docs/knowledge/GAPS.md`: offene Punkte aus `FINDINGS.md`, die nicht erledigt sind.
- [ ] Plan abschließen nach `mode-implementing` (Bottom-Sektionen der README, Archiv, `STATE.md`).

## Report-Back
