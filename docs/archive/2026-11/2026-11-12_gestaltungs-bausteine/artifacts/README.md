# Entwürfe: Gestaltungs-Bausteine und Diagramme

## Entwurf 1: Gestaltungs-Bausteine für Artikel (freigegeben 2026-11-13)

Klickdummy für alle neuen Bausteine in einem Beispielartikel, gerendert mit dem echten Theme (`app/theme/style.css`) plus den künftigen Nachlade-Dateien.

### Dateien

| Datei | Zeigt |
|---|---|
| `entwurf-bausteine.html` | Beispielartikel mit allen Bausteinen in Ausgabe-Markup (so, wie der Renderer es erzeugen soll) |
| `_entwurf-bausteine/bausteine.css` | Teil 1 = Vorlage für `app/theme/bausteine.css` (Abschnitt „Lightbox: Blättern“ gehört in `style.css`); Teil 2 = nur Entwurfsleiste |
| `_entwurf-bausteine/bausteine.js` | Teil 1 = Vorlage für `app/theme/bausteine.js`; `bildansicht()` ist Vorlage für `setUpLightbox` in `artikel.js`; Teil 2 = nur Entwurfsleiste |
| `_entwurf-bausteine/bilder/` | Kopien der Demo-Bilder aus `bin/demo` (CC0, Farbflächen) |

### Bausteine im Entwurf

Galerie Mosaik, Karussell, Bildstreifen, bisherige Galerie · Bildansicht mit Vorschaubildern und Tastatur (alle Phasen) · Vorher/Nachher · Zeitstrahl · Bild mit Erklärpunkten · Wertung · Aufklappbarer Text. Reiter wurden am 2026-11-13 gestrichen. Zuordnung: Ausgabe-Markup → Phase 2, CSS/JS → Phase 3. Je Abschnitt zeigt ein Kasten „Wofür“ den Zweck; „Syntax für Agenten zeigen“ blendet das Markup ein, das Agenten schreiben.

### Ansehen

Datei direkt im Browser öffnen. Die Entwurfsleiste oben schaltet Thema (System/Hell/Dunkel), Bildmenge (Beispiel / 2 Bilder), „Ohne JavaScript“ und die Agenten-Syntax. Dieselben Schalter als URL-Parameter: `?thema=dunkel&menge=2&ohnejs&syntax`. Prüfhilfe: `?klick=<CSS-Selektor>` klickt nach dem Start ein Element an, z. B. ein Bild für die Bildansicht. Veröffentlichte Fassung: keine; die Kopie im Artefakte-Reiter des Verwalters ist dieselbe Datei.

### Platzhalter

Texte, Werte und Bilder sind erfunden; das Vorher-Bild ist dasselbe Bild grau gefiltert.

## Entwurf 2: Diagramme (Phase 6/7, zur Freigabe 2026-11-13)

Alle 17 Diagramm-Typen mit erfundenen Zahlen, als Server-SVG-Markup so, wie `ChartRenderer` es ausliefern soll. Teil 1 von `_entwurf-diagramme/diagramme.css` ist die Vorlage für `app/theme/diagramme.css`; `bauen.cjs` enthält Geometrie, Helfer (`niceScale`, `formatNumber`, `largestRemainder`, `legend`, `table`) und Markup je Typ als Vorlage für `ChartRenderer.php`.

| Datei | Zeigt |
|---|---|
| `entwurf-diagramme.html` | Seite mit Palette, allen 17 Typen, drei Grenzfällen (lange Namen, 40 Spalten, sechs Kreisteile) und den Fehlerfällen (Figur entfällt) |
| `_entwurf-diagramme/diagramme.css` | Teil 1 = Vorlage (Palette hell/dunkel, Marken, Legende, Druckmuster); Teil 2 = nur Entwurfsleiste |
| `_entwurf-diagramme/bauen.cjs` | Erzeugt die HTML-Seite; `node bauen.cjs [datei] [--einzeln]` (`--einzeln` bettet Stile und Skript ein) |
| `_entwurf-diagramme/entwurf.js` | Nur Entwurf: Leiste, Stufenanzeige, Baumkarte-Ebene wechseln |

Bedienung: Entwurfsleiste schaltet Thema und „Druckansicht (Muster statt Farbe)“ und blendet die Agenten-Syntax ein; URL-Parameter `?thema=dunkel&muster&syntax`, Baumkarte `?ebene=g1`. Baumkarte: Bereich anklicken zeigt die Posten, Klick zurück.

Palette (Validator `validate_palette.js`, gegen Seitenflächen hell `#f6f1e7`, dunkel `#17150f`): Reihenfarben 1–8 hell und dunkel bestanden (Helligkeit, Farbigkeit, Farbfehlsichtigkeit, Normalsicht); Kontrast unter 3:1 bei Orange, Aqua, Gelb, Magenta (hell) → Beschriftung, Legende und Tabelle immer vorhanden. Einfarbskala und Anstieg/Rückgang sind aus der Blau-/Rot-Stufenleiter der Dataviz-Vorlage abgeleitet; Kontrast der Beschriftung in den Flächen einzeln gerechnet (≥ 4,5:1, Text schwarz oder weiß je Fläche).

Im Entwurf anders als später: Karte als Kachelraster (Phase 7 zeichnet Umrisse aus Natural Earth); Sankey-Knoten mit 8 Einheiten Abstand (Plan nennt 2 px — für Beschriftungen).
