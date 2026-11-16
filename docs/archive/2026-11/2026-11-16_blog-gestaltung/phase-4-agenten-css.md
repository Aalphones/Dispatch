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
- [x] CssSanitizer
- [x] Sanitizer-Anbindung (`style`, `x-`-Klassen, Tags)
- [x] Builder + Assets + Manifest
- [x] Template-Wrapper `data-artikel` (Abweichung: vorhandenes `data-post-slug`, siehe unten)
- [x] Vorschau-Pfad
- [x] `validate_post`-Hinweise
- [x] style-guide.md „Gestaltung mit CSS“
- [x] ADR-034
- [x] code-map

## Report-Back

Status: complete. Neu: `Util/CssSanitizer` + `CssResult`; geändert: `Sanitizer` (`<style>`/`style` → ein `<style>`-Block am Ende des gespeicherten HTML, `x-`-Klassen, Tags `header footer hgroup wbr`), `Text::plain` (ignoriert `<style>`), `ContentRenderer` (`extractCss`, Schlüssel `css`), `Builder` (`addArticleCss`, Datei `assets/artikel-<slug>.<hash>.css`, Link nur auf der Artikelseite), `PreviewLink::styleLink`, `PreviewController::css` + Route `/dispatch/api/preview-css`, `FormCheck` (`css_removed`, `css_large`), Style-Guide, ADR-034, code-map.

Abweichungen:
- Anker ist `[data-post-slug="<slug>"] .text` statt `[data-artikel="<slug>"]`: `<html>` trägt schon `data-artikel` (Asset-Adresse), und `article.php` hat `data-post-slug` bereits. Kein Template-Eingriff.
- Selektoren müssen mit `x-`- oder Baustein-Klasse beginnen, `+`/`~` verboten: sonst träfe `section{}` oder `.x-a ~ aside` den Quellenblock bzw. den Kasten „Wie dieser Artikel entstand“ (liegen als Geschwister in `.text`). Ein Wrapper `<div class="x-seite">` ersetzt globale Tag-Regeln.
- Hinweise zu Entferntem kommen aus `sanitizer_notes` von `create_post`/`update_post`; `validate_post` sieht nur das schon bereinigte CSS und prüft es erneut.
- CSS liegt im gespeicherten HTML (kein neues Feld), ausgeliefert wird es nie dort.

Von Hand geprüft (PHP-CLI, kein Framework): `url()` (auch groß, mit Leerraum, `image-set`, `src`, `element`, `filter:url`), `@import` (mit/ohne url), `@font-face`, `body/html/:root/*{}`, `position:fixed|sticky`, `[data-red]`, `+`/`~`, Tag-Selektor ohne Klasse, Klassen des Seitengerüsts (auch in `:not`/`:has`), `display:none`, `visibility`, `pointer-events`, `expression`/`behavior`/`-moz-binding`, `\`-Escapes, Kommentar-Trick `u/**/rl(`, `</style>`-Ausbruch, `<` in `content`, `<script>`, `onclick`, `style="…position:fixed…"`, fremde Klassen, `id`, Keyframes ohne `x-`, fremde Schrift, `z-index:9999`, fremde `@media`, Verschachtelung, überzählige `}`, offener Block, 90 000 `(` (37 ms), svg/template/noscript/math-Style, `data-post-slug` am Element, 48 KB CSS (gekappt). Alle entfernt und gemeldet; jeder Lauf idempotent. Hero-Beispiel (Gradient, Grid, Animation, `@media`) bleibt vollständig. `ContentRenderer`-Lauf: ausgeliefertes HTML ohne `<style>`, `style=` und `<script>`; CSS auf `[data-post-slug="…"] .text` begrenzt; ungültiger Slug → kein CSS.

Nicht geprüft (🟡): ein echter Bau mit Datenbank (schreibt in die Wurzel des Arbeitsbaums, bewusst nicht gestartet), also AK 4 (Manifest, Löschen mit dem Artikel) und AK 1 im Browser sowie der Vorschau-Endpunkt nur per PHP-Lint und Code-Lesung. Sichtprüfung von Hero, Redaktionsleiste und Quellenblock steht aus.
Restrisiko (ADR-034): `position:absolute`/`transform` können Seitenteile überlagern; der Inhaber sieht jeden Entwurf vorher in der Vorschau.
