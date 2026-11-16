# Blog-Gestaltung: Teal, Zitate, Social-Einbettungen, Agenten-CSS

Auftrag des Inhabers (2026-11-16). Ziel: Artikel sehen nicht mehr nach Standard-Blog aus.

| Phase | Inhalt | Rating | Status |
|---|---|---|---|
| 1 | Teal als Primärfarbe, Diagramm-Palette, mehrfarbige Diagramme | standard | complete |
| 2 | Zitat-Kasten (`figure.zitat`, formal korrektes `blockquote`/`cite`) | standard | complete |
| 3 | Social-Einbettungen X, Instagram, Facebook, TikTok (Zwei-Klick, iframe) | heikel | complete |
| 4 | HTML + CSS für Agenten (CSS-Filter, Builder schreibt Artikel-CSS) | heikel | complete |
| 5 | Demo-Artikel, Style-Guide, Docs-Abgleich | mechanisch | pending |

## Kontrakt
- Agent liefert `figure.zitat > blockquote[cite] > p` + `figcaption` mit `<cite>`.
- Agent liefert `<figure data-embed="x|instagram|facebook|tiktok" data-url="https://…">` (+ optional `figcaption`).
- Agent liefert `<style>`-Blöcke und `style="…"`; der Sanitizer lässt nur gefiltertes CSS durch, der Builder schreibt es als `assets/artikel-<slug>.<hash>.css` (CSP `style-src 'self'` bleibt unverändert, kein Inline-CSS im Ausgabe-HTML). Eigene Klassen nur mit Präfix `x-`.
- ADRs: 033 (Social-Einbettungen), 034 (Agenten-CSS). Geprüft: `docs/planning/` war leer, keine reservierten Nummern.

## Finale AK
1. Primärfarbe überall gedecktes Teal (Bisasam/Hogwarts-Dächer, kein Neon), Grün als Zweitfarbe, Rot nur als seltener Akzent; Kontrast Text/Link ≥ 4,5:1 auf Papier hell und dunkel.
2. Jedes Diagramm: erste Farbe Teal, Einserien-Balken/-Säulen mehrfarbig, Baumkarte Ebene 1 mehrfarbig.
3. Zitat-Kasten mit Quelle, Quellen-Link aus `cite`, im Druck und ohne Skript lesbar.
4. Je Plattform ein Beitrag (Text, Bild, Video) ohne Skript der Plattform auf unserer Seite, erst nach Klick geladen; Fallback-Link.
5. Agent kann eine Artikelseite per CSS sichtbar gestalten; `url()`, `@import`, `position:fixed`, Selektoren außerhalb des Artikels und `<script>` kommen nie durch; der Sanitizer meldet, was entfernt wurde.
6. Keine automatisierten Tests (ADR-004); AK-Abgleich am Code am Plan-Ende.

## Summary / Files touched / Commits / Deviations / Follow-ups

(beim Archivieren füllen)
