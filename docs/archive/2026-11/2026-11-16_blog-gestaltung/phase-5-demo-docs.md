# Phase 5 — Demo, Style-Guide, Docs (mechanisch)

## Kontext
`bin/demo/` (Muster der Demo-Artikel), `docs/code-map.md`, `docs/decisions/README.md`, `STATE.md`.

## Checkliste
- [x] Demo-Artikel „Gestaltung“: Zitat (inkl. Rechtszitat), vier Social-Einbettungen, CSS-Hero
- [x] style-guide.md abgleichen (je neue Syntax ein Beispiel)
- [x] code-map.md, frontend.md, ADR-Index (zusätzlich routes.md: `preview-css`)
- [x] AK-Abgleich am Code (finale AK der README), archivieren, STATE.md
- [x] Deploy-Hinweis: `.htaccess` (Phase 3) wirkt erst nach dem Deploy

## Report-Back
Demo-Artikel geprüft: Sanitizer entfernt nichts, 2 Zitate, 4 Embeds, 1 Style-Block. AK 5 mit 7 bösen/guten Eingaben geprüft (url, @import, fixed, body-Selektor, script, style-Attribut: alle entfernt und gemeldet). Teal-Token und CSP `frame-src` im Code vorhanden.
