# Phase 2 — Builder-Kern: Sperre, Sammeln, Manifest, Medien, Layout

**Rating:** heikel (löscht Dateien in einer Wurzel, die drei Anwendungen teilen) · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" (Adresstabelle, `builder_top_level`, Seitendaten, Templates, Andockpunkte)
- ADR-001, ADR-003, ADR-007, ADR-014; AGENTS.md → Critical Rules 1–4
- `app/src/Site/Builder.php` (heute: `buildPlaceholder`, `render`, `writeFile`, `assertWritable`), `app/src/Site/Manifest.php`, `app/src/Site/functions.php`, `app/src/Api/DeployController.php`, `bin/dispatch`, `app/config.php`
- `app/src/Domain/PostRepository.php` (`livePosts`), `CategoryRepository::all`, `SettingService::all`, `DocumentService::list`, `MediaRepository::findManyByUid`, `Util\FormCheck::mediaUids`, `Util\Text::slugify`
- `docs/conventions/php.md`, `docs/conventions/frontend.md`, `docs/conventions/dod.md`
- Entwurf `docs/design/entwurf-website.html` → Kopf (`header`), Fuß (`footer`)
- Fehlerklassen geprüft: keine einschlägig

## Verstanden, bevor ersetzt wird

- `Builder::buildPlaceholder()` + `templates/placeholder.php` + `theme/placeholder.css` zeigen seit Meilenstein 0 eine Platzhalter-Startseite, damit `/` nicht auf `/card/` weiterleitet (ADR-007). Ersatz: `build()` erzeugt die echte Startseite. Die alte `assets/placeholder.<hash>.css` steht im Manifest und wird beim ersten Vollbau gelöscht — genau dafür ist das Manifest da.
- `templates/404.php` bleibt als Datei, wird aber auf `layout.php` umgestellt (Phase 5 füllt den Inhalt).

## AK der Phase

1. `php bin/dispatch build` gibt `geschrieben: n, unverändert: m, gelöscht: k, Dauer: x ms` aus; direkt ein zweites Mal: `geschrieben: 0`.
2. Zwei gleichzeitige Builds (zweites Terminal, solange der erste in einer künstlichen Pause steht — Pause nur für den Test einbauen und wieder entfernen) → der zweite wartet; mit Zeitlimit 1 statt 30 endet er mit `build_busy`. Danach ist die Sperre frei (dritter Lauf grün).
3. `assertWritable` lehnt ab (je ein Aufruf in einem Wegwerf-Skript, Ergebnis im Report): `card/x`, `api/x`, `dispatch-app/x`, `.htaccess`, `../x`, `/abs`, `C:/x`, `unbekannt/x`, `` (leer). Erlaubt: `index.html`, `artikel/a/index.html`, `media/m0123456789/480.webp`.
4. Eine von Hand angelegte Datei `artikel/fremd.txt` (nicht im Manifest) überlebt jeden Build; eine Datei, die im alten Manifest stand und nicht mehr entsteht, wird gelöscht, ihr leerer Ordner auch.
5. Nur Varianten-Dateien von Medien, die ein Live-Artikel nutzt, liegen unter `media/`; kein `original.*` eines Bildes.
6. Startseite und 404 rendern mit `layout.php` (Kopf mit Ausgabe-Nummer, Suchfeld-Platz, Name, Kategorie-Leiste; Fuß mit häufigen Schlagwörtern und Pflichtseiten) — noch ohne Theme-CSS-Feinschliff (Phase 4).
7. Der Deploy-Endpunkt ruft `build()`; `buildPlaceholder`, `placeholder.php`, `placeholder.css` sind entfernt.

## Checkliste

- [ ] `config.php`: `builder_top_level` wie im Kontrakt; Kommentar „ADR-014: der Builder schreibt nur unter diesen Namen".
- [ ] `Site/SiteData.php` (final, readonly Properties wie im Kontrakt), `static load(PDO $pdo, array $config): self`:
  - `$posts` aus `(new PostRepository($pdo))->livePosts()`; je Snapshot PostView bauen: `url = '/artikel/' . slug . '/'`, `reading_minutes = max(1, (int) ceil(word_count / 200))`, `tags` → `{name, slug: Text::slugify(name), url: '/tag/' . slug . '/'}`, Kategorien aus `snapshot.categories` über `slug` auf die CategoryView abbilden (inaktive oder fehlende überspringen), `primary_category` = die mit `primary: true`, `sources` mit `nr` (1, 2, …) in Snapshot-Reihenfolge.
  - `$categories` aus `CategoryRepository::all(false)`; `url` = `/kategorie/{slug}/` bzw. `/kategorie/{eltern}/{slug}/`; `children` nach `position`, `name`; `post_count` = Zahl der Live-Artikel mit dieser Kategorie **oder** einer ihrer Unterkategorien (jeder Artikel einmal); `newest_post_id` und `image` (= `cover` dieses Artikels) entsprechend; ohne Artikel `null`.
  - `$tags`: über alle Live-Artikel zählen, Schlüssel ist der Slug, Anzeigename = erste Schreibweise.
  - `$documents`: aus `DocumentService::list()` nur Einträge mit `public_slug`; `list()` liefert **keinen** `content` → je Eintrag `DocumentService::get($docKey)` für den Markdown-Text; `url = '/' . public_slug . '/'`.
  - `$media`: UIDs = Vereinigung von `FormCheck::mediaUids($snapshot)` über alle Live-Artikel; `MediaRepository::findManyByUid`; MediaView mit `base_url = '/media/' . uid . '/'`.
  - `$issueNumber = count($posts)`.
- [ ] `Site/Builder.php` neu ordnen (Konstruktor `(array $config, PDO $pdo)`):
  - `build(): array` → `['written' => int, 'unchanged' => int, 'deleted' => int, 'ms' => int]`. Ablauf strikt nach ADR-014: Sperre holen (`SELECT GET_LOCK(?, ?)` mit `'dispatch_build'`, Timeout aus Konstante `LOCK_TIMEOUT = 30`; Ergebnis ≠ 1 → `DomainError('build_busy', 'Ein anderer Build läuft gerade. Versuche es in einer Minute erneut.')`); `try` { `SiteData::load`; `$files = $this->collect($data)` (Pfad → Inhalt); Medien-Kopien sammeln (`$copies`: Ziel → Quelle); Manifest alt lesen; Manifest = alt ∪ neu schreiben; Dateien schreiben; Kopien ausführen; Veraltetes löschen; Manifest = neu schreiben } `finally` { `SELECT RELEASE_LOCK(?)` }.
  - `collect(SiteData $data): array` — in dieser Phase: Assets (`Site\Assets::collect`), `index.html` (`templates/home.php`, vorläufig nur Aufmacher-Titel und Liste der Titel), `404.html`. Spätere Phasen hängen hier ihre Seitentypen an, je eine private Methode `collect<Seitentyp>()`.
  - `writeFile(string $relative, string $content): bool` — wie heute, aber vorher: existiert die Datei und ist ihr Inhalt identisch (`hash_equals(sha1_file(...), sha1($content))`), nichts tun und `false` liefern.
  - `assertWritable`: bestehende Prüfungen **plus** erstes Segment muss (case-sensitiv) in `builder_top_level` stehen.
  - `deleteStale(array $old, array $new): int` — für jeden Pfad in `$old \ $new`: `assertWritable` (bei Ablehnung: `Log::error` und überspringen, nie löschen), `is_file` → `unlink`. Danach je Pfad die Elternordner bis unterhalb des ersten Segments entfernen, solange leer (`rmdir` nur, wenn `scandir` nur `.`/`..` liefert). Den Ordner des ersten Segments selbst (`artikel/`) nicht entfernen.
  - Medien kopieren: für jede UID in `$data->media`: Bilder → jede Datei aus `variants[*].files`, sonst `original.<ext>`; Quelle `storage_dir/media/<uid>/<datei>`, Ziel `media/<uid>/<datei>`. Existiert das Ziel mit gleicher Größe, überspringen (Medien-Dateien ändern sich nie, ADR-011). Sonst `copy` auf temporäre Datei im Zielordner, dann `rename`. Ziele zählen zum neuen Manifest.
  - `page(string $template, array $vars, array $meta): string` — rendert die Vorlage, dann `layout.php` mit `$content`, `$meta`, `$site` (SiteData), `$assets`.
- [ ] `Site/Assets.php`: `collect(array $config): array` liest `theme/style.css`, `theme/site.js`, `theme/suche.js`, `theme/artikel.js` (fehlende Dateien in dieser Phase als leere Datei behandeln), bildet `assets/<name>.<sha1-8>.<ext>`; liefert `['files' => Pfad → Inhalt, 'urls' => ['style' => '/assets/…', 'site' => …, 'suche' => …, 'artikel' => …, 'suchindex' => (in Phase 6)]]`. Leere Theme-Dateien `theme/style.css`, `theme/site.js`, `theme/suche.js`, `theme/artikel.js` anlegen.
- [ ] `Site/functions.php`: `datum(string $utc, bool $kurz = false): string` — `IntlDateFormatter('de_DE', …, 'Europe/Berlin', null, $kurz ? 'd. MMM' : 'd. MMMM y')`; `seitenUrl(string $basis, int $n)` → `$n === 1 ? $basis : $basis . 'seite/' . $n . '/'`. (`picture()` folgt in Phase 3.)
- [ ] `templates/layout.php` nach Entwurf, Struktur exakt: `<!doctype html><html lang="de" data-suchindex="…" data-suche="…">` · `<head>` (charset, viewport, `<title>`, description, canonical, robots, `<link rel="stylesheet">`, `<link rel="alternate" type="application/rss+xml" href="/feed.xml">`, Favicon später; JSON-LD/OG folgen in Phase 7) · `<body data-seite="…">` · `.fortschritt` · Sprunglink · `<header>`: `.leiste` (`.ausgabe-zeile` mit „Ausgabe Nr. {issueNumber}" und Datum heute, `.kopf-rechts` mit `.suche`-Formular `<form action="/suche/" role="search">` + `<input name="q" id="suchfeld" placeholder="Suchen …">` + `<kbd class="taste">Strg K</kbd>` + leerem `.panel`), `.titelkopf` mit `site_title` als Link auf `/`, `<nav class="nav">` mit den Hauptkategorien (ohne Eltern, höchstens 6, nach `position`) und „Alle Kategorien" (`/kategorien/`); `aria-current="page"` auf der aktiven Kategorie · `<main id="inhalt">{content}</main>` · `<footer class="fuss">` mit den 10 häufigsten Schlagwörtern als `.chip.tag` (mit Zahl) und Spalte DISPATCH (Über, Alle Kategorien, RSS-Feed, Impressum, Datenschutz), unten `site_title · site_tagline` · `<script type="module" src="{site}">` und weitere aus `$meta['scripts']`.
- [ ] `DeployController`: Schritt `bauen` → `(new Builder($this->config, $pdo))->build()`; Antwortfeld `built` = Ergebnis-Array.
- [ ] `bin/dispatch`: Befehl `build` (Hilfetext), Ausgabe wie AK 1.
- [ ] Entfernen: `buildPlaceholder`, `templates/placeholder.php`, `theme/placeholder.css`.
- [ ] Doc: `docs/code-map.md` → Zeile `src/Site/` (SiteData, Builder, Assets, Manifest, Helfer), `templates/` (layout + Seitentypen), `theme/` (style.css, site.js, suche.js, artikel.js); Abschnitt „Faustregeln" um „Neue Seitenart → `Builder::collect<Seitentyp>()` + `templates/<seitentyp>.php` + Pfad in `builder_top_level`".
- [ ] Doc: ADR-007 → Konsequenzen-Zeile „Platzhalter-Builder in Meilenstein 2 durch `build()` ersetzt (ADR-014)".

## Report-Back
