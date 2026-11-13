# Entwurf: Gestaltungs-Bausteine für Artikel

Klickdummy für alle neuen Bausteine in einem Beispielartikel, gerendert mit dem echten Theme (`app/theme/style.css`) plus den künftigen Nachlade-Dateien.

## Dateien

| Datei | Zeigt |
|---|---|
| `entwurf-bausteine.html` | Beispielartikel mit allen Bausteinen in Ausgabe-Markup (so, wie der Renderer es erzeugen soll) |
| `_entwurf-bausteine/bausteine.css` | Teil 1 = Vorlage für `app/theme/bausteine.css` (Abschnitt „Lightbox: Blättern“ gehört in `style.css`); Teil 2 = nur Entwurfsleiste |
| `_entwurf-bausteine/bausteine.js` | Teil 1 = Vorlage für `app/theme/bausteine.js`; `bildansicht()` ist Vorlage für `setUpLightbox` in `artikel.js`; Teil 2 = nur Entwurfsleiste |
| `_entwurf-bausteine/bilder/` | Kopien der Demo-Bilder aus `bin/demo` (CC0, Farbflächen) |

## Bausteine im Entwurf

Galerie Mosaik, Karussell, Bildstreifen, bisherige Galerie · Bildansicht mit Vorschaubildern und Tastatur (alle Phasen) · Vorher/Nachher · Zeitstrahl · Bild mit Erklärpunkten · Wertung · Aufklappbarer Text. Reiter wurden am 2026-11-13 gestrichen. Zuordnung: Ausgabe-Markup → Phase 2, CSS/JS → Phase 3. Je Abschnitt zeigt ein Kasten „Wofür“ den Zweck; „Syntax für Agenten zeigen“ blendet das Markup ein, das Agenten schreiben.

## Ansehen

Datei direkt im Browser öffnen. Die Entwurfsleiste oben schaltet Thema (System/Hell/Dunkel), Bildmenge (Beispiel / 2 Bilder), „Ohne JavaScript“ und die Agenten-Syntax. Dieselben Schalter als URL-Parameter: `?thema=dunkel&menge=2&ohnejs&syntax`. Prüfhilfe: `?klick=<CSS-Selektor>` klickt nach dem Start ein Element an, z. B. ein Bild für die Bildansicht. Veröffentlichte Fassung: keine; die Kopie im Artefakte-Reiter des Verwalters ist dieselbe Datei.

## Platzhalter

Texte, Werte und Bilder sind erfunden; das Vorher-Bild ist dasselbe Bild grau gefiltert.
