# Phase 5 — Seiten: Start, Artikel, Kategorien, Schlagwort, Suche, Dokumente, 404

**Rating:** standard · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" (Adresstabelle, Seitendaten, Templates, Andockpunkte)
- Entwurf `docs/design/entwurf-website.html` → Ansichten „Startseite", „Artikel", „Kategorien", „Schlagwort-Filter" (Markup ist Vorlage: Klassen und Verschachtelung übernehmen, Entwurfs-Daten durch Seitendaten ersetzen)
- Phase 2 → `Builder::collect…`, `page()`, `layout.php`; Phase 3 → `ContentRenderer`, `picture()`; Phase 4 → Klassen
- ADR-013 (kein Archiv, Seitenzahlen, Schlagwort-Seiten, Kategorie-Bild, Ausgabe-Nummer)
- `docs/conventions/frontend.md` (genau eine `h1`, Alt-Texte, Escaping mit `e()`)
- Fehlerklassen geprüft: keine einschlägig

## Seiten (je Template eine Datei, je Seitentyp eine `Builder::collect<Seitentyp>()`)

| Template | Inhalt nach Entwurf | `data-seite` | `robots` |
|---|---|---|---|
| `home.php` | Seite 1: `.aufmacher` (neuester Artikel, Titelbild `picture(…, '(max-width: 46rem) 100vw, 42rem', true)`, Dachzeile = `kicker` oder Hauptkategorie, `h2`, Teaser, Meta „{datum} · {n} Min. Lesezeit · {n} Quellen"), darunter `.raster` mit `posts_per_page` Karten, `.seiten`. Seite ≥ 2: sichtbarer `.seitenkopf` mit `h1` „Ältere Artikel – Seite {n}", dann Raster + Seiten. | `start` | `index,follow` |
| `article.php` | `<article class="artikel wrap" data-post-id data-post-slug style="--accent:{accent}">`: `.krumen` (Start › Hauptkategorie-Eltern › Hauptkategorie), `.artikelkopf` (Dachzeile, `h1`, Unterzeile, `.autor` mit `author_name`, „{datum}", „aktualisiert {datum}" nur wenn `updated_at`-Tag ≠ `published_at`-Tag), `.badges` (KI-recherchiert nur bei `ai_assisted`, „{n} Quellen" als Link `#quellen`, Lesezeit, Fokus-Knopf), `.titelbild` (eager), `.koerper` mit `.seitenspalte` (Inhalt nur bei ≥ 2 Einträgen in `toc`; Schlagwörter), `.text` (`.iv-mobil` mit gleichem Inhalt, `.kopf-tags`, gerendertes HTML, `.transparenz` nur bei `ai_assisted` mit `ai_disclosure_text`, `.quellen` mit `<li id="quelle-{nr}">` je Quelle: Autor, „Titel" verlinkt, Herausgeber, Datum, „abgerufen {accessed}", `.tags-unten`, `.vornach`), dritte Spalte leer; danach „Mehr dazu": 3 Artikel — zuerst gleiche Hauptkategorie, dann meiste gemeinsame Schlagwörter, dann neueste; nie der Artikel selbst. `scripts` = `[artikel]`. | `artikel` | `index,follow` |
| `category.php` | Kein eigener Entwurf — aus Entwurfs-Bausteinen: Kopf als `.katkarte.gross` über die volle Breite (Bild = Kategorie-Bild, `h1` statt `h2`, Beschreibung, Artikelzahl, ohne Hover-Pfeil), darunter Unterkategorien als `.chip.kat`, dann `.raster` + `.seiten`. Krumen: Start › (Eltern ›) Kategorie. | `kategorie` | `index,follow` |
| `categories.php` | `.seitenkopf` („Übersicht", `h1` „Alle Kategorien", „{n} Ressorts · {n} Unterkategorien · {n} Artikel"), `.katgrid`: Hauptkategorien als `.katkarte` — die ersten zwei (nach `position`) `.gross`, Rest normal; Unterkategorien mit Zahl in `.unterkat`, „Neuester: {Titel}". Hauptkategorien ohne Artikel erscheinen mit Verlauf statt Bild. | `kategorien` | `index,follow` |
| `tag.php` | `.filterleiste` mit „Gefiltert nach" (aktives Schlagwort als `.chip.tag.aktiv`, Link `/`) und „Hinzufügen" (die 8 Schlagwörter, die in diesen Artikeln am häufigsten mitvorkommen, als Links `/suche/?tag={dieser}&tag={anderer}` mit Zahl), `.seitenkopf` mit `h1` „#{Name}", `.ergebniszahl`, `.raster`, `.seiten`. | `tag` | `index,follow` |
| `search.php` | Statische Hülle: `.filterleiste` (leer, füllt `suche.js`), `.seitenkopf` mit `h1` „Suche", `<noscript>`-Hinweis „Die Suche braucht JavaScript. Ohne JavaScript findest du Artikel über die Kategorien." mit Link, leeres `.raster`, leere `.seiten`. | `suche` | `noindex,follow` |
| `document.php` | `.seitenkopf` mit `h1` = Dokumenttitel, darunter eine Spalte `.text` (38rem, zentriert) mit `Markdown::toHtml`. | `dokument` | Über `index`, Impressum/Datenschutz `noindex` |
| `404.php` | `.seitenkopf` „Seite nicht gefunden", ein Satz, Links zur Startseite und zu „Alle Kategorien", darunter die 3 neuesten Artikel als `.raster`. | `fehler` | `noindex` |

**Karte** (`templates/partials/card.php`, von allen Rastern genutzt): `<article class="karte" data-post-id>` · `a.foto` (`tabindex="-1" aria-hidden="true"`, `picture(…, '(max-width: 46rem) 6.5rem, (max-width: 62rem) 50vw, 24rem')`) · `.klein.dach` · `h3 > a` · `.teaser` · `.meta` „{datum kurz} · {n} Min.". Ohne Titelbild entfällt `a.foto` (kommt durch die Formprüfung nicht vor, aber nicht abstürzen).

**Seitenleiste** (`templates/partials/pagination.php`, Parameter `$basis`, `$seite`, `$gesamt`): wie Entwurf `.seiten` — „← Neuere" (`aria-disabled` auf Seite 1, dann `<span>` statt `<a>`), Nummernliste (bei mehr als 7 Seiten: 1, …, aktuelle ±1, …, letzte), `.stand` „Seite x von y", „Ältere →". Nur wenn `gesamt > 1`.

## AK der Phase

1. Alle Seiten der Adresstabelle entstehen aus den Demo-Daten; Seitenzahlen stimmen (14 Demo-Artikel bei `posts_per_page = 12` → Startseite 1 Aufmacher + 12, `seite/2/` mit 1 Artikel).
2. Jede Seite hat genau eine `h1` (Startseite Seite 1: `site_title` im Kopf als `<h1 class="name">` nur dort; sonst `<p class="name">`), jedes `img` ein `alt` (Kartenbilder `alt=""`, weil der Titel daneben steht), alle Ausgaben laufen durch `e()` außer dem gerenderten Artikel- und Dokument-HTML.
3. Die Andockpunkte aus dem Kontrakt stehen im HTML (Stichprobe per Quelltext).
4. Ohne JavaScript (Entwicklertools → JavaScript deaktivieren) funktionieren Start, Seitenzahlen, Artikel (inklusive Inhaltsverzeichnis-Sprünge per Anker), Kategorien, Schlagwort-Seiten, Dokumente.
5. Kategorie-Einzelseite, Dokumentseiten und 404 sind nach den Bausteinen oben gebaut (kein eigener Entwurf, ADR-013).
6. Ein Artikel mit `layout = feature` zeigt das Titelbild randlos (`.titelbild` über `100vw` wie `.abb[data-layout="full"]`) und einen größeren Titel; `briefing` zeigt kein Titelbild über der Spalte, sondern rechts klein neben dem Kopf (≤ 46rem: darüber); `longform` setzt `.dropcap` auf den ersten Absatz, falls der Agent es nicht tat.

7. Leerer Bestand (keine Live-Artikel, so startet Strato): Startseite zeigt im `.seitenkopf` „Noch keine Artikel erschienen.", keine Seitenzahlen, Fuß ohne Schlagwort-Spalte, „Alle Kategorien" zeigt Kategorien mit Verlauf und „0 Artikel"; kein PHP-Fehler im Build. Prüfung: lokale Datenbank leeren (`TRUNCATE` der Artikel-Tabellen in einer Kopie) oder alle Demo-Artikel per `PostService::unpublish` zurückziehen, dann `build`.

## Checkliste

- [ ] Partials `card.php`, `pagination.php`, `breadcrumbs.php` (Liste von `{name, url}`) in `templates/partials/`.
- [ ] Templates aus der Tabelle; Builder-Methoden `collectHome`, `collectArticles`, `collectCategories` (Einzel- und Übersichtsseite), `collectTags`, `collectSearch`, `collectDocuments`, `collectNotFound`.
- [ ] Layout-Varianten `feature`/`briefing`/`longform` als Klasse am `<article>` (`artikel layout-{layout}`) und CSS-Ergänzung in `style.css` (Abschnitt „Layouts").
- [ ] Doc: `docs/code-map.md` → Zeile „Seitentypen der öffentlichen Website" auf die tatsächlichen Template-Namen aktualisieren (home, article, category, categories, tag, search, document, 404, partials/).

## Report-Back
