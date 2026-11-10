# Meilenstein 2 — Builder und Theme

Ziel: Aus den Live-Ständen entsteht die komplette öffentliche Website als statische Dateien in der Wurzel von `quantum-canvas.de` — Startseite, Artikel, Kategorien, Schlagwort-Filter, Suche, Dokumentseiten, Feed, Sitemap, `robots.txt`, 404 — im abgestimmten Entwurf, hell und dunkel, auf Handy, Tablet und Desktop. Der Builder löscht Veraltetes über das Manifest und berührt die Nachbar-Anwendungen nie. Grundlage: `docs/PROJECT.md` → Meilenstein 2, ADR-013, ADR-014, ADR-015, Entwurf `docs/design/entwurf-website.html` (siehe `artifacts/README.md`).

Nicht in diesem Meilenstein: alles, was nach dem Login sichtbar ist (Meilenstein 4), Zähl-Aufruf der Statistik (Meilenstein 4), Hüllen für Vorschau (4) und Zustimmung (5), automatischer Neubau nach Freigabe (3/4).

## Phasen

| # | Phase | Rating | Status |
|---|---|---|---|
| 1 | [Demo-Inhalte und Live-Lesepfad ergänzen](phase-1-demo-inhalte.md) | standard | complete |
| 2 | [Builder-Kern: Sperre, Sammeln, Manifest, Medien, Layout](phase-2-builder-kern.md) | heikel | pending |
| 3 | [Inhalt aufbereiten: Medien, Belege, Inhaltsverzeichnis, Markdown](phase-3-inhalt-markdown.md) | heikel | pending |
| 4 | [Theme-CSS nach Entwurf](phase-4-theme-css.md) | standard | pending |
| 5 | [Seiten: Start, Artikel, Kategorien, Schlagwort, Suche, Dokumente, 404](phase-5-seiten.md) | standard | pending |
| 6 | [Skripte und Suchindex](phase-6-skripte-suche.md) | heikel | pending |
| 7 | [Feed, Sitemap, SEO, Deploy und Abschluss](phase-7-feed-seo-abschluss.md) | standard | pending |

Reihenfolge ist Pflicht.

## Kontrakt (gilt für alle Phasen)

**Adressen und Dateien** (relativ zur Wurzel `web_root`; jede Seite ist `…/index.html`):

| Seite | Datei | Seitenzahlen |
|---|---|---|
| Startseite | `index.html` | `seite/{n}/index.html` ab n = 2 |
| Artikel | `artikel/{slug}/index.html` | — |
| Kategorie | `kategorie/{slug}/index.html`, Unterkategorie `kategorie/{eltern}/{slug}/index.html` | `…/seite/{n}/index.html` |
| Alle Kategorien | `kategorien/index.html` | — |
| Schlagwort | `tag/{tagslug}/index.html` | `…/seite/{n}/index.html` |
| Suche | `suche/index.html` | — |
| Dokumente | `ueber/`, `impressum/`, `datenschutz/` (`documents.public_slug`) | — |
| Technisch | `404.html`, `feed.xml`, `sitemap.xml`, `robots.txt` | — |
| Assets | `assets/style.{h}.css`, `assets/site.{h}.js`, `assets/suche.{h}.js`, `assets/artikel.{h}.js`, `assets/suchindex.{h}.json` — `{h}` = erste 8 Zeichen von `sha1(Inhalt)` | — |
| Medien | `media/{uid}/{datei}` | — |

`{tagslug}` = `Util\Text::slugify($tagName)`. Seitengröße = Einstellung `posts_per_page` (Startseite: Aufmacher **plus** `posts_per_page` Karten auf Seite 1). Seite 1 hat nie `seite/1/`.

**`config.php`** bekommt `'builder_top_level' => ['index.html', '404.html', 'feed.xml', 'sitemap.xml', 'robots.txt', 'seite', 'artikel', 'kategorie', 'kategorien', 'tag', 'suche', 'ueber', 'impressum', 'datenschutz', 'assets', 'media']` (Phase 2). Ein Pfad außerhalb dieser Liste → `RuntimeException`.

**Seitendaten** — `Dispatch\Site\SiteData`, einmal pro Lauf geladen (`SiteData::load(PDO $pdo, array $config): self`), nur lesend, öffentliche `readonly`-Properties:

- `array $settings` — `SettingService::all()`
- `list<array> $posts` — neueste zuerst, je Eintrag (PostView): `id, slug, url, title, subtitle, kicker, excerpt, content_html, layout, accent, cover (MediaView|null), seo_title, seo_description, word_count, reading_minutes, ai_assisted, published_at, updated_at, primary_category (CategoryView|null), categories (list<CategoryView>), tags (list<{name, slug, url}>), sources (list wie Snapshot, plus `nr` ab 1)`
- `list<array> $categories` — nur aktive, sortiert nach `position`, `name`; je Eintrag (CategoryView): `id, slug, name, description, parent_id, parent_slug, url, children (list<CategoryView>), post_count, newest_post_id, image (MediaView|null)`
- `list<array> $tags` — `{name, slug, url, count}`, sortiert nach `count` absteigend, dann `name`
- `array $documents` — `public_slug → {title, markdown, url}`
- `array $media` — `uid → MediaView {uid, kind, ext, width, height, alt, caption, credit, license, license_url, source_url, variants, base_url}` für alle von Live-Artikeln genutzten Medien
- `int $issueNumber` — Zahl der Live-Artikel

**Templates** (`app/templates/`): jede Seitenvorlage liefert nur den Inhalt von `<main>`; `layout.php` umschließt ihn. `Builder::page(string $template, array $vars, array $meta): string` rendert beides. `$meta` = `{title, description, canonical, robots, bodyPage, ogType, ogImage (MediaView|null), jsonLd (list<array>), scripts (list<string>)}`. Helfer in `Site/functions.php` (Namespace `Dispatch\Site`): `e()`, `datum(string $utc, bool $kurz = false)`, `picture(array $media, string $sizes, bool $eager = false, string $class = '')`, `seitenUrl(string $basis, int $n)`.

**Andockpunkte für Meilenstein 4** (jetzt schon setzen, ohne Funktion): `<body data-seite="start|artikel|kategorie|kategorien|tag|suche|dokument|fehler">`; Artikelseite `<article class="artikel" data-post-id="{id}" data-post-slug="{slug}">`; jede Karte `<article class="karte" data-post-id="{id}">`; Kategorie-Karte `data-category-id="{id}"`; im Kopf `<div class="kopf-rechts">` mit dem Suchfeld als erstem Kind.

**Skriptmodule** (ADR-015): `<script type="module" src="/assets/site.{h}.js">` auf jeder Seite, `artikel.{h}.js` zusätzlich auf Artikelseiten. `<html data-suchindex="/assets/suchindex.{h}.json" data-suche="/assets/suche.{h}.js">` sagt `site.js`, was es nachladen soll. Kein Inline-Skript, kein Event-Attribut (CSP `script-src 'self'`).

**Suchindex** (`Site\SearchIndex`, JSON): `{"v":1,"kategorien":[{"s","n","u","e"}],"tags":[{"s","n","u","c"}],"artikel":[{"i","u","t","z","x","h","k","g","d","b","c"}]}` — `e` Eltern-Slug oder `null`; `c` Anzahl; je Artikel `t` Titel, `z` Unterzeile, `x` Teaser, `h` Zwischenüberschriften, `k` Kategorie-Slugs (inklusive Eltern), `g` Schlagwort-Slugs, `d` Datum `YYYY-MM-DD`, `b` URL der 480er-WebP des Titelbilds oder `null`, `c` Klartext des Artikels.

## Finale Abnahme (Gesamtergebnis)

1. `php bin/dispatch demo` und danach `php bin/dispatch build` laufen lokal grün; jede Seite aus der Adresstabelle existiert und öffnet im Browser ohne Konsolenfehler, hell und dunkel, in 390, 820 und 1400 px Breite.
2. Die Seiten entsprechen dem Entwurf in den Punkten, die die Phasen 4 und 5 als prüfbar aufzählen.
3. Ein Artikel, der zurückgezogen wird, verschwindet beim nächsten Build mit Seite, Suchindex-Eintrag, Feed- und Sitemap-Zeile; seine Medien werden gelöscht, wenn kein anderer Live-Artikel sie nutzt. Arbeitsstände und Entwürfe tauchen nirgends auf.
4. Ein zweiter Build ohne Änderung schreibt keine Datei neu (`written: 0`).
5. Lighthouse ≥ 95 in allen vier Kategorien auf Startseite und Artikel; der Rich-Results-Test erkennt Artikel und Brotkrumen; der Feed besteht den W3C-Feed-Validator; die Suche findet ein Wort aus dem Fließtext; `/suche/?tag=a&tag=b` filtert additiv.
6. Auf Strato: `deploy.cmd` grün, Startseite und ein Artikel live, `/card/`, `/scanner/capture` und `/dispatch-app/.env` (403) unverändert.

## Summary

## Files touched

## Commits

## Deviations from plan

## Follow-ups
