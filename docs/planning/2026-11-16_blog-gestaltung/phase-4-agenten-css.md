# Phase 4 — HTML und CSS für Agenten (heikel, Security)

## Kontext (lesen)
`app/src/Util/Sanitizer.php` komplett (`style` steht in DROPPED_ELEMENTS, Klassen-Whitelist), `app/src/Site/Builder.php` (`articleMain`, `previewMain`, Assets, Manifest), `app/src/Site/Assets.php`, `app/templates/layout.php` + `article.php`, `app/mcp/style-guide.md`, ADR-010, ADR-002, ADR-014. Die CSP `style-src 'self'` bleibt: Inline-`<style>` und `style=""` würde der Browser blockieren, deshalb schreibt der Builder Dateien.

## Entscheidungen (ADR-034)
- Neue Klasse `Util/CssSanitizer`: eigener Tokenizer, keine Regex-Filterei; liefert bereinigtes CSS und Hinweisliste.
- Eingang: `<style>`-Blöcke und `style`-Attribute. Attribute werden zu generierten Klassen `x-s<hash>` umgeschrieben und als Regel ins Artikel-CSS gelegt. Eigene Klassen nur `/^x-[a-z0-9-]{1,40}$/`; zusätzliche Tags `header`, `footer`, `hgroup`, `wbr`. Kein `id`.
- Selektoren: jeder wird mit `[data-artikel="<slug>"] ` vorangestellt (Wrapper im Template). Entfernt werden `html`, `body`, `:root`, `*` allein, `data-red` und Attribut-Selektoren (außer `[open]`). Erlaubt: Tags, `.x-…`, Kernklassen, Kombinatoren, `:hover :focus-visible :nth-child :first-child :last-child :not :is :has ::before ::after`.
- Eigenschaften-Whitelist: Farben, Hintergrund (nur Farbe und `*-gradient()`), Rahmen, Radius, Schatten, Abstände, Schrift (nur `var(--serif|--sans|--mono)`, `serif`, `sans-serif`, `monospace`), Text-/Listen-/Flex-/Grid-/Spalten-Eigenschaften, Breite/Höhe (min/max), `aspect-ratio`, `opacity`, `filter`, `clip-path`, `transform`, `transition`, `animation` samt `@keyframes`, `@media` (Breite, `prefers-color-scheme`, `print`), Variablen `--x-*`. `position` nur `relative|absolute|static`, `z-index` 0–10, `content` nur Text.
- Verboten, mit Hinweis entfernt: `url()`, `image-set`, `@import`, `@font-face`, `expression`, `behavior`, `-moz-binding`, `position:fixed|sticky`, `pointer-events`, `visibility`, `display:none` auf Kern-Bausteinen (Quellenangaben dürfen nicht versteckt werden), Kommentare, `\`-Escapes in Werten. Größe ≤ 24 KB je Artikel.
- Builder: schreibt bei Bedarf `assets/artikel-<slug>.<hash>.css` (ins Manifest) und hängt ein `<link rel="stylesheet">` nur an diese Artikelseite; die Vorschau bekommt dasselbe CSS. `<script>` bleibt gesperrt. Bilder weiter nur aus der Medienbibliothek.
- Verworfen: `style-src 'unsafe-inline'` — hebelt den Schutz für die ganze Domain aus.

## AK
1. Beispiel-Artikel mit Hero-Block (Gradient, Grid, Animation) rendert gestaltet im Bau und in der Vorschau. 2. Von Hand per CLI geprüft (ADR-004, kein Framework): `url(x)`, `@import`, `body{}`, `position:fixed`, `[data-red]{}`, `<script>`, `onclick` werden entfernt, Hinweis erscheint in `validate_post`. 3. Ausgabe-HTML enthält kein `style=` und kein `<style>`. 4. CSS-Datei steht im Manifest und verschwindet mit dem Artikel. 5. Redaktionsleiste und Quellenblock bleiben sichtbar.

## Checkliste
- [ ] CssSanitizer
- [ ] Sanitizer-Anbindung (`style`, `x-`-Klassen, Tags)
- [ ] Builder + Assets + Manifest
- [ ] Template-Wrapper `data-artikel`
- [ ] Vorschau-Pfad
- [ ] `validate_post`-Hinweise
- [ ] style-guide.md „Gestaltung mit CSS“
- [ ] ADR-034
- [ ] code-map

## Report-Back
