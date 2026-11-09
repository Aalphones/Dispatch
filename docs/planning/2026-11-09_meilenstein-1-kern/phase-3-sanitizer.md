# Phase 3 — Sanitizer

**Rating:** heikel (Security: XSS auf derselben Domain wie der Redaktionsmodus) · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt"
- Konzept → „Schutz vor bösartigen Inhalten" (HTML-Whitelist), „Gestaltungsmittel für den Agenten" (Komponenten-Klassen, `data-media`, `data-embed`, `data-layout`), „Quellen und Belege" (`data-cite`)
- `docs/conventions/php.md` → Critical Rule 3, `docs/conventions/dod.md` (Fallliste für Sicherheitspunkte)
- PHP-Handbuch `Dom\HTMLDocument::createFromString`, `Dom\HTMLDocument::saveHtml` (PHP 8.4+, HTML5-Parser)
- Fehlerklassen: keine einschlägig über die README hinaus

## Entscheidungen (fest)

- **Parser:** `Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NOERROR)` — der HTML5-Parser liest wie ein Browser und schließt damit die Lücke zwischen „was der Sanitizer sieht" und „was der Browser rendert" (Mutation-XSS). Kein `DOMDocument::loadHTML` (libxml, HTML4). Ausgabe: `saveHtml($child)` für jedes Kind von `body`, verkettet.
- **Durchlauf:** Baum rekursiv, Kinder vorher in ein Array kopieren (die Live-Liste ändert sich beim Entfernen). Je Element in dieser Reihenfolge: Name klein → `h1` zu `h2` umbenennen (neues Element, Kinder und Attribute übertragen) → Regel für den Namen → Attribute filtern → Kinder.
- **Behalten:** `p, h2, h3, h4, h5, h6, ul, ol, li, dl, dt, dd, table, caption, thead, tbody, tfoot, tr, th, td, figure, figcaption, aside, section, details, summary, blockquote, div, span, a, strong, em, b, i, u, s, sub, sup, small, mark, abbr, cite, q, code, pre, kbd, br, hr, time`.
- **Samt Inhalt entfernen:** `script, style, iframe, frame, frameset, object, embed, applet, form, input, button, select, option, textarea, svg, math, template, noscript, img, picture, video, audio, source, track, canvas, link, meta, base, head, title`.
- **Alle übrigen Elemente:** auspacken — Element weg, Kinder bleiben (und werden weiter geprüft).
- **Kommentare, CDATA, Processing Instructions:** entfernen, ohne Hinweis.
- **Attribute** (alles andere fliegt, `on*` und `style` immer):

| Element | Erlaubt |
|---|---|
| alle behaltenen | `class` (gefiltert, s. u.) |
| `a` | `href` (geprüft), `title`, `data-cite` (Muster `^[A-Za-z0-9][A-Za-z0-9._-]{0,59}(,[A-Za-z0-9][A-Za-z0-9._-]{0,59})*$`) |
| `figure` | `data-media` (Muster `^m[0-9a-f]{10}$`), `data-layout` (`inline\|wide\|full\|left\|right`), `data-embed` (`youtube\|vimeo`), `data-id` (`^[A-Za-z0-9_-]{1,64}$`) |
| `div` | `data-media` (Muster `^m[0-9a-f]{10}(,m[0-9a-f]{10}){0,29}$`, nur mit Klasse `gallery`), `data-layout` |
| `th`, `td` | `colspan`, `rowspan` (Ganzzahl 1–50) |
| `th` | `scope` (`row\|col\|rowgroup\|colgroup`) |
| `ol` | `start` (Ganzzahl) |
| `time` | `datetime` (`^\d{4}(-\d{2}(-\d{2}(T[\d:]{5,8}(Z\|[+-]\d{2}:\d{2})?)?)?)?$`) |
| `abbr` | `title` |
| `details` | `open` (leer) |

  Ein Wert, der sein Muster verfehlt, entfernt nur das Attribut.
- **Klassen:** nur `lead, tldr, callout, note, warning, key, factbox, pullquote, stats, stat, timeline, steps, checklist, two-col, pros, cons, compare, qa, definition, details, opinion, update, sidenote, dropcap, gallery`; unbekannte einzeln streichen, leeres `class` ganz entfernen.
- **`href`:** Wert trimmen. Zur Schema-Erkennung eine Prüfkopie ohne alle Zeichen `\x00-\x20` und `\x7F` bilden. Beginnt sie mit `//` → verboten. Passt sie auf `^[A-Za-z][A-Za-z0-9+.-]*:` → nur `http`, `https`, `mailto` (Groß/klein egal) erlaubt. Sonst relativ (`/…`, `#…`, `pfad`) → erlaubt. Verboten → Attribut entfernen. Der gespeicherte Wert ist der getrimmte Originalwert (der HTML5-Parser hat Entities bereits aufgelöst, die Prüfung sieht also `java&#x0A;script:` als `java\nscript:`).
- **Hinweise** (`removed`): deutsche Sätze, je Art einmal pro Element-/Attributname gezählt, z. B. „3× Element <script> samt Inhalt entfernt", „Element <h1> zu <h2> umgewandelt", „Attribut onclick an <div> entfernt", „Link mit unerlaubtem Ziel entfernt (javascript:)", „Klasse x-evil entfernt". Reihenfolge: erstes Auftreten.
- **Idempotent:** `sanitize(sanitize($x)->html)->html === sanitize($x)->html`, und der zweite Lauf liefert keine Hinweise.
- **Leere Eingabe** → `''`, keine Hinweise.
- Festgehalten in ADR-010 „Sanitizer mit HTML5-Parser und Whitelist".

## Dateien

| Datei | Inhalt |
|---|---|
| `app/src/Util/Sanitizer.php` | `final class Sanitizer`: Konstanten für die Listen oben; `sanitize(string $html): SanitizeResult` |
| `app/src/Util/SanitizeResult.php` | `final readonly class SanitizeResult(string $html, array $removed)` — `removed` ist `list<string>` |

## Abnahme (Phase) — XSS-Fallliste

Scratchpad-Skript ruft `sanitize()` für jeden Fall und prüft die Ausgabe. **Für jeden Fall gilt zusätzlich:** Ausgabe enthält (Groß/klein egal) kein `<script`, kein `<iframe`, kein `<svg`, kein `<img`, kein `<style`, kein Attribut ` on…=`, kein `style=`, kein `javascript:`, `vbscript:`, `data:` in einem Attribut.

- [ ] 1 `<script>alert(1)</script><p>ok</p>` → `<p>ok</p>`
- [ ] 2 `<img src=x onerror=alert(1)>` → leer
- [ ] 3 `<svg onload=alert(1)><circle/></svg>` → leer
- [ ] 4 `<a href="javascript:alert(1)">x</a>` → `<a>x</a>`
- [ ] 5 `<a href="JaVaScRiPt:alert(1)">x</a>` → `<a>x</a>`
- [ ] 6 `<a href="java&#x0A;script:alert(1)">x</a>` → `<a>x</a>`
- [ ] 7 `<a href=" &#14; javascript:alert(1)">x</a>` → `<a>x</a>`
- [ ] 8 `<a href="data:text/html,<script>alert(1)</script>">x</a>` → `<a>x</a>`
- [ ] 9 `<a href="vbscript:msgbox(1)">x</a>` → `<a>x</a>`
- [ ] 10 `<a href="//evil.example/">x</a>` → `<a>x</a>`
- [ ] 11 `<iframe src="https://evil.example"></iframe>` → leer
- [ ] 12 `<object data="x"></object><embed src="x">` → leer
- [ ] 13 `<form action="https://evil.example"><input name=a></form>` → leer
- [ ] 14 `<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>` → kein `img`, kein `style`
- [ ] 15 `<noscript><p title="</noscript><img src=x onerror=alert(1)>">` → kein `img`
- [ ] 16 `<p style="background:url(javascript:alert(1))">x</p>` → `<p>x</p>`
- [ ] 17 `<div onclick="alert(1)">x</div>` → `<div>x</div>`
- [ ] 18 `<details open ontoggle=alert(1)><summary>s</summary></details>` → `<details open=""><summary>s</summary></details>`
- [ ] 19 `<template><script>alert(1)</script></template>` → leer
- [ ] 20 `<!--<script>alert(1)</script>-->` → leer
- [ ] 21 `<base href="https://evil.example/"><meta http-equiv="refresh" content="0;url=https://evil.example">` → leer
- [ ] 22 `<table background="javascript:alert(1)"><tr><td>x</td></tr></table>` → Tabelle ohne `background`
- [ ] 23 `<scr<script>ipt>alert(1)</script>` → kein ausführbares `<script`
- [ ] 24 `<figure data-media="javascript:alert(1)"></figure>` → `<figure></figure>`
- [ ] 25 `<a href="https://example.org" target="_blank" rel="opener">x</a>` → nur `href` bleibt
- [ ] 26 `<x-widget onclick=alert(1)><p>Text</p></x-widget>` → `<p>Text</p>`

**Erlaubtes bleibt erhalten:**

- [ ] 27 `<h1>T</h1>` → `<h2>T</h2>` mit Hinweis
- [ ] 28 `<aside class="callout warning x-evil"><p>v</p></aside>` → `class="callout warning"`, Hinweis zu `x-evil`
- [ ] 29 `<p>Satz<a data-cite="destatis-2026,s3"></a></p>` → unverändert
- [ ] 30 `<figure data-media="m0123456789" data-layout="wide"><figcaption>c</figcaption></figure>` → unverändert
- [ ] 31 `<div class="gallery" data-media="m0123456789,mabcdefabcd"></div>` → unverändert
- [ ] 32 `<figure data-embed="youtube" data-id="dQw4w9WgXcQ"></figure>` → unverändert
- [ ] 33 `<a href="mailto:a@b.de">m</a>`, `<a href="#s1">a</a>`, `<a href="/artikel/x/">r</a>` → unverändert
- [ ] 34 Tabelle mit `<th scope="col" colspan="2">` → unverändert; `colspan="999"` → entfernt
- [ ] 35 Idempotenz: für alle Fälle 1–34 liefert ein zweiter Lauf dieselbe Ausgabe ohne Hinweise
- [ ] 36 Umlaute und `&amp;`/`&lt;` im Text überstehen den Lauf unverändert (`<p>Größe &lt; 5 &amp; mehr</p>`)

## Checkliste

- [ ] Dateien laut Tabelle
- [ ] Fallliste fahren, Ein- und Ausgabe je Fall im Report-Back (Pflicht nach `dod.md`)
- [ ] ADR-010 „Sanitizer mit HTML5-Parser und Whitelist" (Alternative `DOMDocument`/libxml verworfen: anderer Parser als der Browser; Liste der Regeln steht im Code, nicht im ADR) + Zeile in `docs/decisions/README.md`
- [ ] `docs/code-map.md`: `Util/Sanitizer`, `Util/SanitizeResult`

## Report-Back
