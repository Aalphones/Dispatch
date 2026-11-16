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
- [x] style.css Variablen (hell + beide Dark-Blöcke)
- [x] diagramme.css Palette + Rampen (hell, dark, `.dunkel`)
- [x] ChartRenderer: Einserien → Kategorie-Farbe
- [x] Baumkarte reproduzieren, ggf. fixen
- [x] redaktion.css + bausteine.css: harte Rot-Werte auf Variablen
- [x] Doc: `docs/conventions/frontend.md` Farbwerte, ADR-030 Palette-Satz

## Report-Back
Status: complete (nicht committet).
- `--accent` `#256e64` (5,34:1 auf Papier, 4,89:1 auf paper-2); dunkel `--accent-ink` `#45a595` (6,14:1 auf `#17150f`), fest statt `color-mix`. Der Richtwert `#2b7a6f` erreicht nur 4,53:1 auf Papier und 4,15:1 auf paper-2, deshalb dunkler. `--bad` bleibt `#a3261e`.
- Palette hell `#2b7a6f #7aa84f #8ccbb9 #2c5f4f #c0a96c #c8353a #7c9ab8 #7f7f2e`; dunkel `#4aa595 #86b86a #2f6f62 #a6d6c6 #b59f66 #e06a6e #7f9fbd #b0b04e`; Rampen `--s1…--s5` und `--plus` Teal; `--t1…--t8` für beide Modi neu gesetzt. Nachbarn mit Helligkeitsverhältnis ≥ 1,15 gerechnet (kleinster Wert 1,18, c6/c7 dunkel).
- `ChartRenderer::markColor`: Einserien-Balken/-Säulen `r(Kategorie+1)`, Zyklus nach 8; Mehrserien unverändert. Geprüft per PHP-Render: `r1…r4`.
- Baumkarte Ebene 1: Der Render liefert je Gruppe `gruppe r1…rN`, CSS und JS setzen `--f` nicht um. Die Mehrfarbigkeit war im Markup schon vorhanden; im Browser nicht gegengeprüft (kein Browserlauf).
- Mitgezogen: Standardwert `accent` in `SettingService.php` und `docs/models.md`. Bestehende Datenbankwerte bleiben unverändert (der Standard greift nur für neue Installationen).
- `redaktion.css`/`bausteine.css`: keine harten Rottöne gefunden, nur Variablen; keine Änderung nötig.
- Druckmuster `mu1…mu8` unverändert; sie hängen an `.rN`.
- Nicht geprüft: Datenbankwert `accent` auf der laufenden Seite (🟡 falls dort noch `#b3261e` steht, bleibt der Artikel-Akzent rot).
