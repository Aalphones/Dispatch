# 030 — Diagramme als Server-SVG

**Status:** Akzeptiert (2026-11-13) · ergänzt ADR-010, ADR-015 und ADR-032

## Kontext

Artikel brauchen Diagramme (17 Typen von Linie bis Karte). Agenten dürfen weder JavaScript noch SVG noch CSS liefern (ADR-010), die Content-Security-Policy erlaubt keine Inline-Skripte und keine Inline-Stile, und Artikelseiten bleiben klein (ADR-015). Ein Diagramm muss auch ohne Skript lesbar sein, im Druck, in hellem und dunklem Theme.

## Entscheidung

- **Die Datentabelle ist die Quelle.** Der Agent schreibt `figure.chart` mit Typ, Titel und einer Tabelle; der Server liest sie (`ChartData`), die Formprüfung meldet Schieflagen als `component_invalid`, der Renderer erzeugt Figur, SVG, Legende und die Tabelle als Textalternative.
- **Der Server zeichnet das SVG** (`ChartRenderer`, `ChartComplexPlots`, Layouts für Sankey und Baumkarte), keine Browser-Bibliothek. Gründe: CSP, Ladegröße, Funktion ohne Skript, feste Palette mit Druckmustern (`r1–r8`).
- **Interaktion nur auf vorgerechnetem Markup.** `diagramme.js` schaltet Attribute und Klassen um (Hinweis, Auswahl, Dimmen, Zoom) und rechnet nie neu. Maße nur über `element.style`.
- **Nicht jedes Diagramm ist interaktiv.** Nur Linie, Heatmap, Kalender, Sankey, Baumkarte, Blasen und Karte (`ChartRenderer::INTERACTIVE_TYPES`), und nur wenn der Agent `data-interactive="true"` setzt; die Formprüfung warnt sonst. Die übrigen Typen bleiben statisch.
- **Legenden-Knöpfe entstehen erst im Browser** (`button[aria-pressed]` aus den Legendeneinträgen), damit ohne Skript keine toten Knöpfe stehen.
- **Karten** (`de` Bundesländer, `welt` Länder) stammen aus Natural Earth (gemeinfrei) und liegen vorgerechnet in `app/data/karten/`, erzeugt einmalig mit `bin/karten-bauen.php`.
- **Eigene Nachlade-Dateien:** `diagramme.css` lädt jeder Artikel mit Diagrammen (Builder hängt sie bei `diagramme === true` an `meta['styles']`), `diagramme.js` nur bei `.diagramm[data-interaktiv]` (`loadComponents()` in `artikel.js`). `Assets::THEME_FILES` führt die Schlüssel `diagramme` und `diagrammestil`; `<html>` trägt `data-diagramme`/`data-diagrammestil`.
- **Größengrenzen:** `diagramme.css` ≤ 9 000 Byte, `diagramme.js` ≤ 13 500 (geplant 9 000, gemessen 13 252), `artikel.js` ≤ 13 000 (vorher 12 500, gemessen 12 736), Kartendateien je ≤ 60 000.

## Verworfen

- **SVG oder JavaScript vom Agenten:** nicht prüfbar, bricht CSP und Sanitizer-Modell.
- **Chart-Bibliothek im Browser:** zu groß, braucht Skript für jedes Diagramm, Inline-Stile kollidieren mit der CSP.
- **Alle Typen interaktiv:** bei Balken, Säulen, Fläche, Kreis, Netz, Waffel und Wasserfall bringt Interaktion nichts, was die Tabelle nicht schon liefert.

## Folgen

- Ein neuer Diagramm-Typ braucht vier Stellen: Datenleser (`ChartData`), Formprüfung, Renderer und Eintrag im Style-Guide; bei Interaktion zusätzlich `INTERACTIVE_TYPES` und `diagramme.js`.
- Die Grenzen oben sind Obergrenzen je Datei; wer sie überschreitet, ändert zuerst dieses ADR.
