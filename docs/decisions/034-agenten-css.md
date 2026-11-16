# 034 — HTML und CSS für Agenten

**Status:** Akzeptiert (2026-11-16) · lockert ADR-032 (kein freies CSS), ergänzt ADR-010 und ADR-014

## Kontext

Artikel sollen nicht nach Standard-Blog aussehen. Mit den festen Bausteinen aus ADR-032 kann ein Agent nur das Vorgegebene setzen; ein Hero-Block mit Verlauf, Raster oder Animation geht nicht. Die CSP `style-src 'self'` blockiert Inline-`<style>` und `style=""` im Browser, und sie soll bleiben.

## Entscheidung

- **Neue Klasse `Util/CssSanitizer`.** Eigener Zeichen-Leser, keine Regex-Filterei auf dem Rohtext. Er liest Selektoren und Werte, prüft jeden Baustein gegen Listen und schreibt das CSS neu; in die Ausgabe kommt nur, was er selbst aus geprüften Teilen setzt. Ein Lesefehler kostet höchstens Gestaltung, lässt nie etwas durch.
- **Eingang:** `<style>`-Blöcke und `style`-Attribute. Der Sanitizer entfernt beides aus dem Text. Attribute werden zu einer erzeugten Klasse `x-s<hash>`, ihre Regel landet im CSS. Das CSS steht im gespeicherten HTML in genau einem `<style>`-Block am Ende; `Text::plain` ignoriert ihn. Keine neue Spalte, keine eigene Revision.
- **Eigene Klassen** nur `x-[a-z0-9-]{1,40}`. Neue Tags `header`, `footer`, `hgroup`, `wbr`. Kein `id`, keine Attribute außer den bisherigen.
- **Selektoren:** Jeder wird zu `[data-post-slug="<slug>"] .text <selektor>`. Der Selektor muss mit einer `x-`-Klasse oder einer Baustein-Klasse beginnen; danach sind Tags, Leerzeichen und `>` erlaubt. `+` und `~` sind verboten. Erlaubt: `:hover :focus-visible :first-child :last-child :nth-child() :not() :is() :has() ::before ::after`, `[open]`. Damit erreicht kein Selektor den Quellenblock, den Kasten „Wie dieser Artikel entstand“, die Seitenleiste, Kopf oder Fuß: sie sind weder Nachkommen eines Agenten-Elements noch ihre Geschwister erreichbar.
- **Eigenschaften:** Whitelist (Farbe, Hintergrund, Rahmen, Abstand, Schrift, Raster/Flex, Größe, `transform`, `filter`, `clip-path`, `transition`, `animation`). Werte nur aus Zeichen, Zahlen, Schlüsselwörtern und erlaubten Funktionen (Farben, Verläufe, `calc/min/max/clamp/var`, Transformationen, Filter, `repeat/minmax`); jede andere Funktion fällt, also auch `url()`, `image-set()`, `src()`, `element()`. Schrift nur `var(--serif|--sans|--mono)`, `serif`, `sans-serif`, `monospace`. `position` nur `relative|absolute|static`, `z-index` 0–10, `content` nur Text, `display:none` verboten (Quellenangaben und Bausteine dürfen nicht versteckt werden). `@keyframes` und `@media` (Breite, Höhe, `prefers-color-scheme`, `prefers-reduced-motion`, `print`); Keyframe-Namen mit `x-`. Eigene Variablen nur `--x-*`.
- **Verboten, mit Hinweis entfernt:** `@import`, `@font-face` und jede weitere At-Regel, Kommentare, `\`, `<`, verschachtelte Regeln, `!important`. Größe höchstens 24 KB je Artikel.
- **Builder:** Der `ContentRenderer` nimmt den `<style>`-Block aus dem Text, prüft ihn erneut (mit dem Slug als Anker) und liefert ihn als `css`. Der Builder schreibt ihn als `assets/artikel-<slug>.<hash>.css`, trägt ihn ins Manifest ein und verlinkt ihn nur auf dieser Artikelseite, hinter den Theme-Stilen. Das Ausgabe-HTML enthält nie `<style>` oder `style=`. Ohne Slug im Muster `[a-z0-9-]` gibt es kein CSS.
- **Vorschau:** Sie schreibt keine Datei. Der Endpunkt `GET /dispatch/api/preview-css` (signiert wie `preview-media`, eine Stunde) liefert das CSS des Arbeitsstands; die Vorschau stellt ein `<link>` darauf vor den Text.
- **Hinweise:** `create_post` und `update_post` melden in `sanitizer_notes`, was entfernt wurde. `validate_post` prüft das gespeicherte CSS erneut (`css_removed`, falls Regeln seitdem strenger wurden) und warnt ab 80 % der Größe (`css_large`).
- **Unverändert:** `<script>` bleibt gesperrt, Bilder nur aus der Medienbibliothek, CSP bleibt `style-src 'self'`.

## Verworfen

- **`style-src 'unsafe-inline'`:** hebelt den Schutz für die ganze Domain aus.
- **Regex-Filter auf dem Rohtext:** Escapes, Kommentare und Groß-/Kleinschreibung umgehen ihn; die Ausgabe wäre ein Teil der Eingabe.
- **Selektoren ab `body`/`.text` frei:** Ein `section{…}` träfe den Quellenblock. Wer den ganzen Text gestalten will, setzt einen Wrapper `<div class="x-seite">`.
- **Eigene Spalte für das CSS:** Revisionen, Vorschau-Schnappschuss und Wiederherstellung müssten nachgezogen werden; der Block im HTML reist überall mit.

## Folgen

- Ein Agent kann Elemente überlagern (`position:absolute`, `transform`, `z-index` bis 10). Sichtbare Teile der Seite verdecken kann er damit theoretisch; der Inhaber sieht jeden Entwurf in der Vorschau, bevor er ihn freigibt. Bewusst akzeptiert.
- Neue Eigenschaften, Funktionen oder Selektoren brauchen einen Eintrag in den Listen von `CssSanitizer` und im Style-Guide.
- `Sanitizer::ALLOWED_ELEMENTS` und `ALLOWED_CLASSES` sind öffentlich: der CssSanitizer nutzt sie für erlaubte Tags und Baustein-Klassen.
- Kein Test-Framework (ADR-004): die geprüften Fälle stehen im Plan unter Report-Back.
