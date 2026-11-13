# Phase 2 — Renderer: Ausgabe-Markup der Bausteine

Komplexität: standard · Modell: Sonnet

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans, Abschnitt „Kontrakt“ — die Spalte „Ausgabe“ ist verbindlich, Klassen und Verschachtelung exakt so.
- `artifacts/entwurf-bausteine.html`: zeigt jedes Ausgabe-Markup vollständig (Abschnitte Galerien, Vorher/Nachher, Zeitstrahl, Erklärpunkte, Wertung, Häufige Fragen).
- `app/src/Site/ContentRenderer.php`: `render()`, `renderGalleries()`, `buildGallery()`, `renderFigures()`, `buildPicture()`, `buildCaption()`, `buildCredit()`, `select()`, `firstChildNamed()`, `unwrap()`, `dropMissing()`, Konstanten `SIZES_BY_LAYOUT`, `GALLERY_SIZES`.
- `app/src/Site/Builder.php` (Doc-Kommentar der Eigenschaft `$rendered`, Zeile ~40).
- Fehlerklassen (Vault) geprüft: keine einschlägig.

Verstanden, bevor geändert: `renderFigures()` verarbeitet **jede** `figure[data-media]`. Vorher/Nachher und Erklärpunkte tragen ebenfalls `data-media` — sie müssen vorher ersetzt werden, sonst macht `renderFigures()` daraus ein Einzelbild. Die Ausgabe des Renderers geht ohne weiteren Sanitizer-Lauf in die Seite; deshalb nie Rohwerte aus Attributen als HTML einsetzen, nur über `textContent`/`setAttribute`. Kein `style`-Attribut erzeugen (CSP).

## Abnahmekriterien

1. Für jede Zeile der Kontrakt-Tabelle erzeugt `render()` exakt das Ausgabe-Markup (Klassen, Reihenfolge, Attribute).
2. Galerien: `data-variante` nur bei gesetztem `data-variant` (`mosaic`→`mosaik`, `carousel`→`karussell`, `strips`→`streifen`); Karussell-Fotos in `div.spur`; jedes `.foto` jeder Galerie hat `data-unterschrift` (Format laut Kontrakt; fehlt alles, entfällt das Attribut).
3. Vorher/Nachher: fehlt eines der zwei Medien oder ist es kein Bild → Figur entfällt, `Log::error` wie in `renderGalleries()`. Bilder mit `SIZES_BY_LAYOUT['inline']`; Nachweise beider Bilder per `buildCredit($medium, 'Foto 1'|'Foto 2')` in `figcaption`, eigener `figcaption`-Text davor.
4. Erklärpunkte: ein Bild (`SIZES_BY_LAYOUT['inline']`), die `ol` wird `ol.erklaerbild-liste` und bleibt sonst unverändert (inkl. `data-x`/`data-y`); fehlt das Medium → wie 3.
5. Wertung: Zahlen mit Komma (`7.8` → `7,8`), `meter` mit `min="0"`, `max`, `value`, Fallback-Text „9 von 10“; `li` ohne gültiges `data-score` entfällt; ohne verbleibende `li` entfällt der Block.
6. Zeitstrahl: ein führendes `<time>` (erstes Kind-Element, davor nur Leerraum) wandert in `span.zeitstrahl-datum`, der Rest in `div.zeitstrahl-text` (führender Leerraum entfernt); ohne `<time>` bleibt `span.zeitstrahl-datum` leer; `class="highlight"` → `class="markiert"`. Gilt für bestehende Artikel mit `<ol class="timeline">` ebenso.
7. Aufklappbarer Text: Klasse `akkordeon`, direkte `details` bekommen `name="akkordeon-N"`, N je Artikel ab 1 hochgezählt.
8. `render()` liefert zusätzlich `'bausteine' => true`, sobald mindestens einer der Blöcke `.galerie[data-variante]`, `.vergleich`, `.erklaerbild`, `.wertung`, `.zeitstrahl`, `.akkordeon` erzeugt wurde, sonst `false`.
9. `php -l` grün.

## Checkliste

- [x] Eigenschaft `private bool $hasComponents` (zu Beginn von `render()` auf `false`) und `private int $accordionCount`.
- [x] `render()`: Reihenfolge `renderGalleries()` → `renderComparisons()` → `renderHotspots()` → `renderFigures()` → `renderEmbeds()` → `renderRatings()` → `renderTimelines()` → `renderAccordions()` → Rest wie bisher; Rückgabe um `'bausteine' => $this->hasComponents` ergänzen und den `@return`-Typ anpassen.
- [x] `buildGallery(array $images, string $variant)`: Variante aus `data-variant` übergeben (Mapping als Konstante `GALLERY_VARIANTS = ['mosaic' => 'mosaik', 'carousel' => 'karussell', 'strips' => 'streifen']`); `data-unterschrift` über neue Methode `photoCaption(array $medium): string`; beim Karussell `div.spur` anlegen und die Fotos dort anhängen.
- [x] `renderComparisons()`: `select('figure.before-after[data-media]')`, Labels aus `data-labels` (an `|` teilen, Standard `['Vorher', 'Nachher']`), Ausgabe laut Kontrakt.
- [x] `renderHotspots()`: `select('figure.hotspots[data-media]')`, Ausgabe laut Kontrakt.
- [x] `renderRatings()`: `select('ul.rating')`, Ausgabe laut Kontrakt; Zahlformat über `formatScore(string $value): string` (`str_replace('.', ',', …)`, `.0` weglassen).
- [x] `renderTimelines()`: `select('ol.timeline, ul.timeline')`, Ausgabe laut AK 6 (Element wird `ol`, Klasse `zeitstrahl`).
- [x] `renderAccordions()`: `select('div.accordion')`, Ausgabe laut AK 7.
- [x] Jede `render…`-Methode setzt bei erzeugtem Block `$this->hasComponents = true`.
- [x] Doc-Kommentar von `Builder::$rendered` um `bausteine: bool` ergänzen.
- [x] Prüfen: Skript im Scratchpad, das einen Beitrag mit allen Bausteinen (Medien aus der lokalen Datenbank) durch `ContentRenderer` schickt; Ausgabe gegen `artifacts/entwurf-bausteine.html` vergleichen.
- [x] `php -l app/src/Site/ContentRenderer.php app/src/Site/Builder.php`.

## Report-Back
- Renderer: alle sechs Bausteine + Galerie-Varianten wie im Kontrakt; `render()` liefert `bausteine`. Zusätzlich: `data-unterschrift` auch bei Galerien ohne Variante; Vorher/Nachher braucht genau zwei gültige Bilder.
- Prüfung: Skript mit synthetischen Medien (nicht aus der lokalen Datenbank) deckt alle Bausteine, Fehlfälle und `bausteine` true/false ab; Ausgabe entspricht dem Entwurf. `php -l` grün.
