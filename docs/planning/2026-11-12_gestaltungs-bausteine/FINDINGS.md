# Findings

Erkenntnisse während der Umsetzung, getaggt auf die Phase, die sie betreffen.

Format: `- [ ] → Phase N: <Erkenntnis>`

- [x] → Phase 6: Palette validiert (`validate_palette.js`, 8 Reihenfarben, Seitenflächen hell #f6f1e7 / dunkel #17150f): beide Modi bestanden (Helligkeit, Farbigkeit, Farbfehlsichtigkeit schlechtester Nachbar ΔE 9,1 hell / 8,4 dunkel, Normalsicht ≥ 19). Kontrast unter 3:1 hell bei Orange 2,84, Aqua 2,5, Gelb 1,92, Magenta 2,39 → Beschriftung, Legende und Tabelle immer vorhanden; dunkel alle ≥ 3:1. Farbfehlsichtigkeit unter 8 → Druckmuster und Direktbeschriftung als Zweitkodierung.
- [ ] → Phase 8: Die Druckmuster (`svg.muster`, ids mu1–mu8) stehen einmal je Artikel im ersten Diagramm; Phase 8 muss bei interaktiven Diagrammen und Karten (Phase 7) dieselben Klassen r1–r8 nutzen. Typen ohne Zeichnung (sankey, treemap, bubble, map) bleiben bis Phase 7 als Figur mit Tabelle stehen. Das Laden von diagramme.css (Flag `diagramme` aus render()) macht Phase 8.
- [x] → Phase 7: ChartRenderer::supports()/TYPE_NAMES und render()-match um die vier Typen erweitern; Klassen .gross (Schriftgrad 20) stehen in diagramme.css; Entwurf bauen.cjs enthält die Vorlagen sankey/baumkarte/blasen/karte.

- [ ] → Phase 8: Kennungen für Interaktion: Sankey-Knoten `data-knoten="k1…"`, Bänder `data-von`/`data-nach` (gleiche Kennungen); Baumkarte Ebene 1 `<g class="gruppe" data-gruppe="g1…">`, zweite Ebene `<g class="ebene" data-gruppe="g1…" hidden>`; Karte `path[data-region]` (Code), Regionen ohne Wert `.region-leer`. Tastaturbedienung (tabindex/role) fehlt noch — Phase 8 setzt sie nur bei data-interactive. Kartenmuster `#mu-leer` steht im Musterblock.
