# 032 — Gestaltungs-Bausteine für Artikel

**Status:** Akzeptiert (2026-11-13) · ergänzt ADR-010 und ADR-015

## Kontext

Agenten schreiben Artikel als HTML, das der Sanitizer auf eine feste Liste von Elementen und Klassen beschränkt (ADR-010). Für Galerien in mehreren Formen, Vorher/Nachher, Erklärpunkte auf Bildern, Wertungen, Zeitstrahl und aufklappbaren Text gab es keine Mittel. Eigenes CSS, JavaScript oder SVG von Agenten bleibt ausgeschlossen.

## Entscheidung

- **Feste Bausteine statt freiem CSS/JS:** Der Agent schreibt eine kleine, vorgegebene Syntax; der Sanitizer lässt nur deren Klassen und Attribute durch, die Formprüfung meldet schief befüllte Bausteine als `component_invalid`, der Renderer erzeugt das fertige Markup.
- **Englische Agenten-Syntax, deutsche Ausgabe:** `gallery`, `before-after`, `hotspots`, `rating`, `timeline`, `accordion` im gespeicherten HTML; `galerie`, `vergleich`, `erklaerbild`, `wertung`, `zeitstrahl`, `akkordeon` auf der Seite (wie bisher `gallery` → `galerie`).
- **Eigene Nachlade-Dateien:** `bausteine.css` und `bausteine.js` wachsen getrennt von `style.css` und `artikel.js`. Der Renderer meldet `bausteine: true`; nur dann trägt die Artikelseite den Stylesheet-Link, und `artikel.js` lädt `bausteine.js` nur bei Bausteinen mit Bedienung (Karussell, Streifen, Vorher/Nachher, Erklärpunkte). Artikel ohne Bausteine laden keine der beiden Dateien. In der Vorschau hängt `artikel.js` den Stylesheet-Link selbst an.
- **Größengrenzen:** `bausteine.js` ≤ 8 000 Byte, `bausteine.css` ≤ 16 000, `artikel.js` ≤ 12 500 (vorher 7 000), `style.css` ≤ 50 000 (vorher 48 000).
- **Die Bildansicht bleibt in `artikel.js`**, weil sie jedes Bild betrifft, nicht nur Bausteine; sie blättert jetzt innerhalb einer Galerie und zeigt Vorschaubilder.
- **Der Zeitstrahl baut auf der bestehenden `timeline`-Syntax auf;** neu ist nur die Klasse `highlight` am Eintrag.
- **Schlüssel der Theme-Adressen:** `Assets::THEME_FILES` führt je Eintrag `[Datei, Endung]`, damit `bausteine.js` und `bausteine.css` getrennte Schlüssel (`bausteine`, `bausteinestil`) haben.

## Verworfen

- **Freies CSS/JS für Agenten:** nicht prüfbar, bricht die CSP und das Sicherheitsmodell des Sanitizers.
- **Alles in `style.css`/`artikel.js`:** jeder Artikel bezahlt für Bausteine, die er nicht nutzt.
- **Reiter als Baustein:** zu wenig Nutzen gegenüber dem aufklappbaren Text (entschieden 2026-11-13).

## Folgen

- Neue Bausteine brauchen drei Stellen: Sanitizer-Regel, Renderer, Eintrag im Style-Guide für Agenten (`app/mcp/style-guide.md`).
- Die Grenzen oben sind Obergrenzen je Datei; wer sie überschreitet, ändert zuerst dieses ADR.
