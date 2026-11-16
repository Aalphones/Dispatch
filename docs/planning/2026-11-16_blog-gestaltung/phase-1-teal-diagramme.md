# Phase 1 — Teal und Diagramm-Palette (standard)

## Kontext (lesen)
`app/theme/style.css` (Zeilen 2–24: `:root`, Dark-Blöcke `prefers-color-scheme` und `.dunkel`), `app/theme/diagramme.css` (Zeilen 1–60), `app/src/Site/ChartRenderer.php` (bar/column ca. Z. 430–530, Klasse `r1` je Reihe), `app/src/Site/ChartComplexPlots.php` (`treemap`, Z. 205–233), `app/theme/bausteine.css`, `app/theme/redaktion/redaktion.css` (harte Farbwerte), `app/src/Site/Seo.php` (THEME_COLOR). Vault-Fehlerklassen CSS: Abschnitt `## Fehlerklassen` der Entity lesen, falls vorhanden.

## Entscheidungen
- Farbwelt (Vorgabe des Inhabers): Bisasam und die Dächer von Hogwarts — gedecktes Blaugrün (Patina), Grün, **keine Neonfarben**; Rot nur sehr vereinzelt als Akzent (wie die Augen). Richtwerte, in der Umsetzung gegen den Kontrast gerechnet: Teal-Patina `#2b7a6f`, Bulbengrün `#5f9e4a`, Tannengrün `#2c5f4f`, Seeschaum `#7fbfae`, Sand `#c9b98a`, Augenrot `#c8353a`.
- Ist-Stand: `--accent` ist `#b3261e` (Rot, nicht Orange). Neu `--accent` = Teal-Patina (hell, ≥ 4,5:1 auf `#f6f1e7`). Dunkel: `--accent-ink` ein aufgehelltes, gedecktes Teal (≥ 4,5:1 auf `#17150f`), kein `color-mix(…white)`. Kontraste per `node -e` rechnen, Ergebnis in den Commit-Text. Rot (`--bad`, `--minus`, Fehler/Warnung) bleibt Rot und ist der einzige Rot-Einsatz; keine roten Links oder Buttons mehr.
- Diagramm-Palette `--c1…--c8`: c1 = Teal-Patina, dann Bulbengrün, Seeschaum, Tannengrün, Sand, Augenrot (nur als c6 oder später, nie als erste Farbe), übrige mit Helligkeitsabstand. Teal/Grün sind für Farbschwache schwer zu trennen → Nachbarn müssen sich in der Helligkeit um ≥ 15 % unterscheiden (Rechnung), zusätzlich tragen die Druckmuster `mu1…mu8` und die Beschriftungen die Identität. `scripts/validate_palette.js` aus dem Kommentar existiert nicht im Repo; Rechnung ad hoc. Einfarbskala `--s1…--s5` und `--plus` werden Teal-Rampen.
- Einserien-Diagramme (bar, column): jede Kategorie bekommt `r(i+1)` statt `r1`. Mehrserien bleiben je Reihe eine Farbe.
- Baumkarte Ebene 1: Code färbt schon je Gruppe (`r` + Index, Z. 218). Erst im Browser reproduzieren (`php bin/dispatch demo`). Ist sie dort einfarbig, Ursache an der Stelle fixen (Verdacht: Klemmung auf `MAX_SERIES_COLORS`, oder Skript/CSS setzt `--f` falsch).

## AK
1. Kein `#b3261e` mehr im Theme. 2. Hell/Dunkel/Druck: Links, Fokusring, Buttons Teal. 3. Einserien-Balken zeigen ≥ 3 Farben, erste Teal. 4. Baumkarte Ebene 1: jede Gruppe eigene Farbe. 5. Druckmuster `mu1…mu8` unverändert gültig.

## Checkliste
- [ ] style.css Variablen (hell + beide Dark-Blöcke)
- [ ] diagramme.css Palette + Rampen (hell, dark, `.dunkel`)
- [ ] ChartRenderer: Einserien → Kategorie-Farbe
- [ ] Baumkarte reproduzieren, ggf. fixen
- [ ] redaktion.css + bausteine.css: harte Rot-Werte auf Variablen
- [ ] Doc: `docs/conventions/frontend.md` Farbwerte, ADR-030 Palette-Satz

## Report-Back
