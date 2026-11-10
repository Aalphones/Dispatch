# Phase 3 — Inhalt aufbereiten: Medien, Belege, Inhaltsverzeichnis, Markdown

**Rating:** heikel (formt gespeichertes HTML um, das öffentlich ausgeliefert wird) · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" (MediaView, PostView `sources` mit `nr`)
- ADR-010 (Sanitizer, `Dom\HTMLDocument`), ADR-011 (Varianten), ADR-014 (Markdown)
- `app/src/Util/Sanitizer.php` → erlaubte Elemente, Klassen, `data-media`, `data-layout`, `data-embed`, `data-id`, `a data-cite`, `div.gallery data-media`
- `app/src/Util/FormCheck.php` → `mediaUids`, Muster `REFERENCE_PATTERN`
- Entwurf `docs/design/entwurf-website.html` → Ansicht „Artikel": `.abb` mit `data-layout`, `.foto`, `.galerie`, `figcaption .nachweis`, `sup a` (Belege), `.quellen li`, `.kurz` (= `.tldr`/`.sidenote`), `.iv`
- `docs/conventions/frontend.md` → Bilder als `<picture>`, feste Maße, `loading="lazy"`
- Fehlerklassen geprüft: keine einschlägig

## AK der Phase

1. `ContentRenderer::render(PostView $post, SiteData $data): array` liefert `['html' => string, 'toc' => list<{id, text}>, 'headings' => list<string>, 'text' => string]`.
2. Jedes `figure[data-media]` eines Bildes wird zu (Reihenfolge und Klassen exakt):
   `<figure class="abb" data-layout="{layout|inline}"><div class="foto"><picture><source type="image/webp" srcset="…480.webp 480w, …800.webp 800w, …" sizes="{sizes}"><img src="{größte JPEG/PNG-Variante ≤ 1200}" srcset="…jpg 480w, …" sizes="{sizes}" width="{w}" height="{h}" alt="{alt}" loading="lazy" decoding="async"></picture></div><figcaption>{vorhandene figcaption des Agenten, sonst media.caption} <span class="nachweis">Foto: {credit} · <a href="{license_url}" rel="license">{license}</a>{ · <a href="{source_url}">Quelle</a>}</span></figcaption></figure>`.
   `sizes` je Layout: inline `(max-width: 46rem) 100vw, 38rem` · left/right `(max-width: 46rem) 100vw, 18rem` · wide `(max-width: 62rem) 100vw, 53rem` · full `100vw`. `width`/`height` = Maße der größten Variante.
3. Video → `<figure class="abb" data-layout="…"><video controls preload="none" src="/media/{uid}/original.{ext}"></video><figcaption>…</figcaption></figure>`; Audio analog mit `<audio controls preload="none">`; Dokument → `<p class="dokument"><a href="/media/{uid}/original.{ext}">{caption oder "Dokument (PDF)"}</a></p>`.
4. `div.gallery[data-media="u1,u2,u3"]` → `<figure class="galerie" data-anzahl="3">` mit je einem `<div class="foto"><picture>…</picture></div>` (sizes `(max-width: 46rem) 50vw, 26rem`, erstes Bild `(max-width: 46rem) 100vw, 34rem`) und gemeinsamer `figcaption` mit allen Nachweisen.
5. `figure[data-embed][data-id]` → `<figure class="einbettung" data-embed="youtube|vimeo" data-id="{id}"><button type="button" class="einbettung-start">Video laden <small>(YouTube – erst nach Klick werden Daten übertragen)</small></button>{figcaption}</figure>`. Kein `<iframe>` im statischen HTML.
6. `a[data-cite="r1,r2"]` → Linktext bleibt (ohne `<a>`, wenn kein `href`), dahinter je Ref `<sup class="beleg"><a href="#quelle-{nr}" aria-label="Quelle {nr}">{nr}</a></sup>`. Unbekannter Ref → kein `sup` (Formprüfung verhindert das ohnehin).
7. Jede `h2` bekommt `id` = `Text::slugify(Text)`, bei Dopplung `-2`, `-3`; `toc` listet alle `h2` in Reihenfolge. `headings` = Text aller `h2`/`h3` (für den Suchindex).
8. `aside.tldr` / `div.tldr` bekommt als erstes Kind `<b class="kurz-titel">Kurz gesagt</b>`, `.sidenote` bekommt `<b class="kurz-titel">Randnotiz</b>`. Die Klassen der Agenten bleiben unverändert.
9. Ein Medium, das in `SiteData::$media` fehlt, wird ersatzlos entfernt und `Log::error` protokolliert; der Build läuft weiter.
10. `Util\Markdown::toHtml(string $md): string` setzt `#`–`####` (eine Ebene tiefer: `#` → `h2`), Absätze, `**fett**`, `*kursiv*`, `[Text](url)`, `- `/`* `/`1. `-Listen, `> `-Zitate und `---` um; alles andere bleibt Text (escaped). Ergebnis läuft durch `(new Sanitizer())->sanitize($html)->html`. Prüfung: Impressum-Startinhalt aus `app/defaults/imprint.md` rendert ohne verlorenen Text; `<script>alert(1)</script>` im Markdown erscheint escaped; `[x](javascript:alert(1))` verliert den Link.
11. `text` = Klartext des Artikels (Tags entfernt, Entities dekodiert, Leerraum zusammengefasst) für den Suchindex.

## Checkliste

- [ ] `Site/ContentRenderer.php` mit `Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html, LIBXML_NOERROR)` (wie der Sanitizer, ADR-010), Umbau in dieser Reihenfolge: Medien (Figuren, Galerien, Einbettungen) → Belege → Überschriften-IDs → Kurz-gesagt-Titel; Ausgabe `$body->innerHTML`. Neue Elemente nur per DOM-API (`createElement`, `setAttribute`), Attributwerte nie per String-Verkettung — so bleibt alles escaped.
- [ ] `Site/functions.php`: `picture(array $media, string $sizes, bool $eager = false, string $class = ''): string` — einzige Stelle, die `<picture>` als String baut (für Karten, Titelbilder, Kategorie-Karten in Phase 5); alle Werte durch `e()`. `$eager` → `loading="eager" fetchpriority="high"` statt `lazy`. Den `ContentRenderer` auf dieselbe Logik stützen (gemeinsame private Hilfsfunktion `varianten(array $media): array` → `['webp' => srcset, 'fallback' => srcset, 'src' => url, 'w' => int, 'h' => int]`).
- [ ] `Util/Markdown.php` (final, statisch, zeilenbasiert, ohne reguläre Ausdrücke mit Rückverweisen über Zeilen): Blöcke an Leerzeilen trennen; Inline-Ersetzung erst nach `htmlspecialchars`; Links nur `http`, `https`, `mailto`, relative `/…`.
- [ ] Prüfskript `bin/demo/pruefe-inhalt.php` (läuft nur lokal, wird mit den Demo-Werkzeugen committet): rendert alle Demo-Artikel, zählt je Artikel `figure.abb`, `figure.galerie`, `figure.einbettung`, `sup.beleg`, `h2[id]`, und meldet jedes verbliebene `data-media` oder `data-cite` als Fehler.
- [ ] Doc: `docs/code-map.md` → `src/Site/` um `ContentRenderer`, `src/Util/` um `Markdown` ergänzen.

## Report-Back

AK 1–11 belegt mit `php bin/demo/pruefe-inhalt.php` (14 Demo-Artikel, XSS-Fälle, Impressum). Abweichungen: HTML-Kommentare im Markdown werden entfernt (die Impressum-Vorlage enthält einen internen Hinweis, der sonst öffentlich stünde); `Text::plain()` neu, `wordCount` nutzt es; `varianten()` und `picture()` stehen als Funktionen in `functions.php`.
