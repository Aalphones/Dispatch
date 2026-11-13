# Phase 1 — Agenten-Syntax: Sanitizer, Formprüfung, Style-Guide

Komplexität: standard · Modell: Sonnet

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans, Abschnitt „Kontrakt“ (Agenten-Syntax und Wertebereiche).
- `app/src/Util/Sanitizer.php`: `ALLOWED_CLASSES`, die Muster-Konstanten (`MEDIA_PATTERN`, `GALLERY_MEDIA_PATTERN`), `keptAttributeValue()` (match über „element attribut“, `$classes` = behaltene Klassen **desselben** Elements), `oneOf()`, `matching()`.
- `app/src/Util/FormCheck.php`: `check()`, `warning()`, `referencedValues()`; wie `ContentRenderer::render()` (Zeile mit `HTMLDocument::createFromString`) ein Fragment in ein DOM lädt.
- `app/mcp/style-guide.md` (Abschnitte „Klassen für Gestaltungsmittel“, „Medien“, „Warnungen“).
- `docs/decisions/010-*.md` (Sanitizer-Whitelist, warum `Dom\HTMLDocument`).
- Fehlerklassen (Vault) geprüft: keine einschlägig für PHP/DOM.

Verstanden, bevor geändert (Chesterton): `keptAttributeValue()` liefert `null` = Attribut entfernen; jede neue Zeile muss bei falschem Wert `null` liefern, nie den Rohwert. `'figure data-media'` erlaubt heute genau eine UID — Einzelbilder dürfen das weiter, nur `before-after` bekommt zwei.

## Abnahmekriterien

1. Jede Agenten-Syntax der Kontrakt-Tabelle übersteht `Sanitizer::sanitize()` unverändert (Klassen, Attribute, Werte).
2. Falsche Werte werden entfernt, mit Hinweis in `sanitizer_notes` wie bisher: `data-variant="grid"`, `data-x="120"`, `data-max="7"`, `data-score="abc"`, `data-labels` mit `<`, `data-media` mit zwei UIDs an einer Figur ohne `before-after`.
3. `FormCheck` meldet die Warnung `component_invalid` (Feld `content_html`) mit einem Satz, der Baustein und Fehler nennt, in diesen Fällen: `before-after` ohne genau zwei bekannte Bild-UIDs; `hotspots` ohne `li` mit gültigem `data-x` und `data-y` oder mit mehr als 12 `li`; `rating` ohne `li` mit `data-score` oder mit einem `data-score` über `data-max` (Standard 10); `gallery` mit `data-variant="carousel"` oder `"strips"` und weniger als 2 UIDs; `strips` mit mehr als 8 UIDs.
4. `style-guide.md` beschreibt alle Bausteine mit je einem Beispiel und die neue Warnung.
5. `php -l` grün für beide PHP-Dateien.

## Checkliste

- [ ] `Sanitizer::ALLOWED_CLASSES` um `before-after`, `hotspots`, `rating`, `accordion`, `highlight` ergänzen.
- [ ] Konstanten anlegen: `PAIR_MEDIA_PATTERN = '/^m[0-9a-f]{10},m[0-9a-f]{10}\z/'`, `LABELS_PATTERN = '/^[^|<>"]{1,40}\|[^|<>"]{1,40}\z/u'`, `COORDINATE_PATTERN = '/^(100|[0-9]{1,2})(\.[0-9])?\z/'`, `SCORE_PATTERN = '/^[0-9]{1,3}(\.[0-9])?\z/'`, `GALLERY_VARIANTS = ['mosaic', 'carousel', 'strips']`, `RATING_MAXIMA = ['5', '10', '100']`.
- [ ] `keptAttributeValue()` erweitern:
  - `'figure data-media'` → bei Klasse `before-after` `PAIR_MEDIA_PATTERN`, sonst wie bisher `MEDIA_PATTERN`.
  - `'figure data-labels'` → nur mit Klasse `before-after`, `LABELS_PATTERN`.
  - `'div data-variant'` → nur mit Klasse `gallery`, `oneOf(GALLERY_VARIANTS)`.
  - `'li data-x'`, `'li data-y'` → `COORDINATE_PATTERN` (ohne Klassenbedingung; das `li` kennt seine Liste nicht).
  - `'ul data-max'` → nur mit Klasse `rating`, `oneOf(RATING_MAXIMA)`.
  - `'ul data-total'` → nur mit Klasse `rating`, `SCORE_PATTERN`; `'li data-score'` → `SCORE_PATTERN`.
- [ ] `FormCheck`: private Methode `checkComponents(string $html, array $mediaByUid)` und Aufruf in `check()` nach `checkMedia()`. Fragment wie im Renderer laden (`\Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html, LIBXML_NOERROR, 'UTF-8')`), Bausteine per `querySelectorAll` finden, Fälle aus AK 3 prüfen, je Fund eine `component_invalid`-Warnung. Beispieltext: „Vorher/Nachher braucht genau zwei Bilder in data-media.“
- [ ] `app/mcp/style-guide.md`: Klassenliste ergänzen; neuer Abschnitt „Bausteine“ unter „Medien“ mit je einem Beispiel aus der Kontrakt-Tabelle (Galerie-Formen, Vorher/Nachher, Erklärpunkte, Wertung, Zeitstrahl mit `<time>` und `highlight`, Aufklappbarer Text) und je einem Satz „wofür“ (Text aus `artifacts/entwurf-bausteine.html`, Kästen „Wofür“); Galerie-Zeile um `data-variant` ergänzen; Warnung `component_invalid` in die Liste.
- [ ] Prüfen: kleines Skript im Scratchpad (nicht im Repo), das `(new Sanitizer())->sanitize()` mit allen Beispielen aus AK 1/2 aufruft und Ausgabe plus Hinweise ausgibt; Ergebnis gegen AK abgleichen.
- [ ] `php -l app/src/Util/Sanitizer.php app/src/Util/FormCheck.php`.

## Report-Back
