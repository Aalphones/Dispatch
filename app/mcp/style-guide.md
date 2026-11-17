# Style-Guide für Artikel-HTML

`content_html` ist ein HTML-Fragment ohne `<html>` und `<body>`. Der Server bereinigt es beim Speichern mit einer Whitelist (`sanitizer_notes` in der Antwort von `create_post` und `update_post` nennt, was entfernt wurde). Was hier nicht steht, wird entfernt.

## Erlaubte Elemente

`p`, `h2` bis `h6`, `ul`, `ol`, `li`, `dl`, `dt`, `dd`, `table`, `caption`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`, `figure`, `figcaption`, `aside`, `section`, `details`, `summary`, `blockquote`, `div`, `span`, `a`, `strong`, `em`, `b`, `i`, `u`, `s`, `sub`, `sup`, `small`, `mark`, `abbr`, `cite`, `q`, `code`, `pre`, `kbd`, `br`, `hr`, `time`, `header`, `footer`, `hgroup`, `wbr`.

- `<h1>` wird zu `<h2>`: Der Titel des Artikels steht im Feld `title`, nicht im Text. Gliedere mit `<h2>`, darunter `<h3>`.
- `<style>` und `style="…"` sind erlaubt, aber gefiltert (siehe „Gestaltung mit CSS“).
- Entfernt samt Inhalt: `script`, `iframe`, `object`, `embed`, `form`, `input`, `button`, `svg`, `math`, `img`, `picture`, `video`, `audio`, `canvas` und weitere. Bilder, Videos und Audio kommen nur über die Medien-Syntax unten.
- Attribute: nur `href` und `title` an `a` (Schemata `http`, `https`, `mailto`), `title` an `abbr`, `colspan`, `rowspan` (1 bis 50) und `scope` an Tabellenzellen, `start` an `ol`, `datetime` an `time`, `open` an `details` sowie die Attribute der Medien- und Beleg-Syntax. `style` wird zu einer erzeugten Klasse (siehe „Gestaltung mit CSS“); `id`, `onclick` und alle anderen fallen weg.

## Klassen für Gestaltungsmittel

Erlaubt sind genau diese Klassen und eigene Klassen mit dem Präfix `x-` (siehe „Gestaltung mit CSS“); jede andere wird entfernt.

`lead` (Einleitungsabsatz), `tldr` (Kasten „Kurz gesagt“ — der Titel kommt automatisch, `aside` oder `div`), `callout` mit `note`, `warning` oder `key`, `factbox`, `pullquote` (Hervorhebung ohne Quelle), `zitat` (belegtes Zitat, siehe „Zitat“), `stats` mit `stat`, `timeline`, `steps`, `checklist`, `two-col` mit `pros` und `cons`, `compare` (Tabelle), `qa` (Fragen und Antworten, als `dl` mit `dt`/`dd`), `definition`, `details`, `opinion` (Meinung, wird automatisch gekennzeichnet), `update` (Korrektur, wird automatisch gekennzeichnet), `sidenote` (Randnotiz, der Titel kommt automatisch), `dropcap` (Initiale), `gallery`, sowie die Bausteine `before-after`, `hotspots`, `rating`, `accordion` und `highlight` (siehe „Bausteine“) sowie `chart` (Diagramm, siehe „Diagramme“).

## Zitat

Wörtliche Zitate mit Quelle als `figure` mit Klasse `zitat`. Der Text steht in `blockquote` (Attribut `cite` = Adresse der Quelle, nur `http`/`https`), die Quelle in `figcaption`: Person oder Institution in `<span class="zitat-wer">`, das Werk in `<cite>`, die Fundstelle als Text. Anführungszeichen setzt die Seite, nicht im Text schreiben; Auslassungen als `[…]`. Aus `cite` entsteht automatisch der Link „Quelle ansehen“.

```html
<figure class="zitat"><blockquote cite="https://www.gesetze-im-internet.de/bgb/__327e.html"><p>Die Ware ist frei von Sachmängeln, wenn sie […] den subjektiven Anforderungen entspricht.</p></blockquote><figcaption><span class="zitat-wer">Gesetzgeber</span>, <cite>Bürgerliches Gesetzbuch</cite>, § 327e Abs. 3 BGB</figcaption></figure>
```

Zitat ohne Quelle zum bloßen Hervorheben: `pullquote`.

Pflicht in jedem Artikel: ein `tldr` direkt nach dem Einstieg (Gesamt-Kasten) und eines am Ende jedes `<h2>`-Abschnitts, jeweils direkt im Text, nicht in `section` oder `div` verschachtelt. Fehlt eines, warnt die Formprüfung (`tldr_missing`).

Beispiele:

```html
<p class="lead">Ein Satz, der das Thema auf den Punkt bringt.</p>
<aside class="tldr"><p>Das Wichtigste in zwei Sätzen.</p></aside>
<div class="callout warning"><p>Achtung: …</p></div>
<div class="stats"><div class="stat"><b>42 %</b><span>Anteil …</span></div></div>
<ol class="steps"><li>Erster Schritt</li><li>Zweiter Schritt</li></ol>
<div class="two-col"><div class="pros"><p>Dafür …</p></div><div class="cons"><p>Dagegen …</p></div></div>
<div class="opinion"><p>Meine Einschätzung: …</p></div>
```

Nachrichtlich und wertend getrennt halten: Wertendes steht in `opinion`.

## Medien

Medien kommen aus der Medienbibliothek (`import_media_from_url`, `upload_media`) und werden über ihre UID eingebunden (`m` plus zehn Hexzeichen, z. B. `m3f9a01b2c4`).

- **Bild, Video, Audio:** `<figure data-media="UID" data-layout="wide"><figcaption>Bildunterschrift</figcaption></figure>`. `data-layout` ist optional: `inline` (Standard), `wide`, `full`, `left`, `right`. Die Nachweiszeile (Urheber, Lizenz) setzt der Renderer; ohne eigene `figcaption` nimmt er die Bildunterschrift des Mediums.
- **Galerie:** `<div class="gallery" data-media="UID1,UID2,UID3"></div>`, höchstens 30 UIDs. Optional `data-variant="mosaic"`, `"carousel"` oder `"strips"` (siehe „Bausteine“); ohne `data-variant` erscheint das bisherige Raster.
- **Einbettung** (lädt erst nach Klick): `<figure data-embed="youtube" data-id="VIDEO-ID"></figure>`; Anbieter `youtube` oder `vimeo`, `data-id` aus Buchstaben, Ziffern, `_` und `-`.
- **Social-Beitrag** (lädt erst nach Klick, kein Skript der Plattform): `<figure data-embed="x" data-url="https://x.com/NAME/status/ID"><figcaption>Text</figcaption></figure>`. Plattformen und Adressform (immer die lange https-Adresse des Beitrags, keine Kurzlinks): `x` → `x.com` oder `twitter.com` `/NAME/status/ID`; `instagram` → `/p/CODE/`, `/reel/CODE/`, `/tv/CODE/`; `facebook` → `/NAME/posts/ID`, `/NAME/videos/ID`, `/watch/?v=ID`, `/reel/ID`; `tiktok` → `/@NAME/video/ID`. Eine Adresse, die nicht passt, wird nur ein Link; die Formprüfung meldet es als `component_invalid`. Text, Bild und Video des Beitrags zeigt die Plattform selbst.
- Das Titelbild steht nicht im Text, sondern im Feld `cover_media_uid` und muss ein Bild sein.
- Unbekannte UIDs blockieren das Einreichen (`unknown_media`).

## Bausteine

Der Server erzeugt aus dieser Syntax das fertige Markup samt Bedienung; eigenes JavaScript oder SVG gibt es nicht; eigenes CSS nur wie unter „Gestaltung mit CSS“. Falsche Werte entfernt der Sanitizer, schief befüllte Bausteine meldet die Formprüfung (`component_invalid`). Jedes Bild öffnet auf der Artikelseite zusätzlich in einer Bildansicht mit Blättern; dafür ist nichts zu schreiben.

- **Galerie-Formen:** Mosaik zeigt eine Serie auf einen Blick, Karussell erzählt eine Abfolge Bild für Bild, Bildstreifen lädt zum Stöbern ein und spart Höhe. Karussell und Bildstreifen brauchen mindestens 2 Bilder, Bildstreifen höchstens 8.

  ```html
  <div class="gallery" data-variant="carousel" data-media="UID1,UID2,UID3"></div>
  ```

- **Vorher/Nachher:** zwei Aufnahmen derselben Stelle mit einem Regler vergleichen. `data-media` nennt genau zwei Bild-UIDs (alt, neu), `data-labels` zwei Beschriftungen bis 40 Zeichen, getrennt durch `|`; ohne sie stehen „Vorher“ und „Nachher“ da.

  ```html
  <figure class="before-after" data-media="UID_ALT,UID_NEU" data-labels="1985|2025"><figcaption>Gletscherzunge 1985 und 2025</figcaption></figure>
  ```

- **Erklärpunkte:** ein Bild an mehreren Stellen erklären, ohne Ortsangaben im Text. `data-x` und `data-y` sind Prozent von links bzw. oben (0 bis 100, höchstens eine Nachkommastelle), höchstens 12 Punkte.

  ```html
  <figure class="hotspots" data-media="UID"><ol><li data-x="22" data-y="35"><b>Titel</b> Text</li></ol><figcaption>Beschreibung</figcaption></figure>
  ```

- **Wertung:** eine Bewertung nach mehreren Kriterien vergleichbar machen. `data-max` ist 5, 10 oder 100 (Standard 10); `data-score` und `data-total` liegen zwischen 0 und 999 mit höchstens einer Nachkommastelle und dürfen `data-max` nicht überschreiten; ohne `data-total` entfällt die Gesamtzeile.

  ```html
  <ul class="rating" data-max="10" data-total="7.8"><li data-score="9">Datenqualität</li><li data-score="6.5">Nachvollziehbarkeit</li></ul>
  ```

- **Zeitstrahl:** Ereignisse mit Datum in Abfolge erzählen. Das Datum steht in `<time datetime="…">`, die Klasse `highlight` am `li` hebt wichtige Einträge hervor.

  ```html
  <ol class="timeline"><li class="highlight"><time datetime="1850">1850</time> <b>Höchststand</b> Die Zunge reicht bis …</li><li><time datetime="1920-05">Mai 1920</time> Erste Messung …</li></ol>
  ```

- **Aufklappbarer Text:** ergänzende Fragen und Details anbieten, ohne den Lesefluss zu unterbrechen; immer nur ein Eintrag ist offen.

  ```html
  <div class="accordion"><details><summary>Frage</summary><p>Antwort …</p></details><details><summary>Weitere Frage</summary><p>Antwort …</p></details></div>
  ```

## Diagramme

Du schreibst nur eine Datentabelle in eine `figure`; den Server zeichnet das Diagramm. SVG und Skript gibt es weiterhin nicht. Die Tabelle bleibt als „Zahlen als Tabelle“ erhalten und ist zugleich der Beleg für Lesende.

```html
<figure class="chart" data-type="bar" data-unit="%"><table><tr><th></th><th>Anteil</th></tr><tr><td>Solar</td><td>12,5</td></tr><tr><td>Wind</td><td>31</td></tr></table><figcaption>Anteil an der Stromerzeugung. Quelle: Behörde X, Stand 03/2026</figcaption></figure>
```

- **Regeln:** Die erste Zeile ist die Kopfzeile. Zahlen als `7.8` oder `7,8`, ohne Tausendertrenner und ohne Einheit in der Zelle; die Einheit steht in `data-unit` (bis 12 Zeichen, ohne `<`, `>`, `"`, `|`). **Quelle und Stand gehören in die `figcaption`.**
- **Ein sauberer Balken genügt.** Setze `data-interactive="true"` nur, wo Bedienung etwas erschließt (viele Reihen, dichte Daten, Verbindungen); nur Linie, Heatmap, Kalender, Sankey, Baumkarte, Blasen und Karte können interaktiv sein, alle anderen Typen bleiben statisch.

| Aussage | `data-type` | Tabelle (Kopfzeile → Zeilen) | Wofür |
|---|---|---|---|
| Größen vergleichen | `bar` (waagerecht), `column` (senkrecht) | leer, Reihe 1 … Reihe n → Beschriftung, Wert je Reihe (höchstens 8 Reihen, 40 Zeilen) | Rangfolge und Abstände; `bar` bei langen Beschriftungen |
| Verlauf über die Zeit | `line` (interaktiv möglich), `area` | wie oben (`line` bis 120 Zeilen) | Entwicklung und Trend; `area` betont das Volumen |
| Zusammensetzung nach Reihen | `stacked-bar`, `stacked-column` | wie oben | Gesamtgröße und Anteile zugleich |
| Anteile am Ganzen | `pie`, `donut`, `waffle` | Beschriftung, Wert → höchstens 6 Teile, nicht negativ | wenige Teile eines Ganzen; mehr als 6 → Rest zu „Sonstige“ |
| Profil über Dimensionen | `radar` | wie `bar`, höchstens 3 Reihen, 12 Achsen | Stärken und Schwächen im Vergleich |
| Wie sich ein Wert zusammensetzt | `waterfall` | Beschriftung, Wert (mit Vorzeichen); Summenzeilen mit `<tr data-sum="true">` | Schritte von Start zu Ergebnis |
| Muster in zwei Dimensionen | `heatmap` (interaktiv möglich) | leer, Spalte 1 … n → Zeilenname, Werte (höchstens 24 × 24) | Häufung und Ausreißer |
| Verlauf eines Jahres | `calendar` (interaktiv möglich) | Datum `JJJJ-MM-TT`, Wert (ein Kalenderjahr) | Saisonmuster, tägliche Werte |
| Flüsse zwischen Stationen | `sankey` (interaktiv möglich) | Von, Nach, Wert (höchstens 40 Verbindungen und 5 Spalten, keine Kreisläufe, nicht negativ) | Herkunft und Verbleib |
| Gewichte in Gruppen | `treemap` (interaktiv möglich) | Gruppe, Untergruppe, Wert (höchstens 60 Blätter und 8 Gruppen, nicht negativ) | Anteile in einer Hierarchie |
| Zusammenhang dreier Größen | `bubble` (interaktiv möglich) | Name, x, y, Größe, Kategorie (höchstens 60 Zeilen, 8 Kategorien; Größe nicht negativ; die Kopfzellen von x, y und Größe beschriften Achsen und Hinweise, also mit Einheit schreiben, z. B. „Einkommen in Tsd. €“) | Streuung mit Gewicht |
| Werte nach Region | `map` (interaktiv möglich) | Region, Wert, Name; `data-map="de"` (Codes `DE-BY` …) oder `data-map="welt"` (ISO-Alpha-2, z. B. `FR`; Kleinstaaten fehlen auf der Weltkarte); Regionen ohne Zeile erscheinen schraffiert als „keine Daten“ | räumliche Verteilung |

## Gestaltung mit CSS

Für Aufmacher und besondere Abschnitte (Verlauf, Raster, Animation) darfst du CSS mitschicken: als `<style>`-Block irgendwo im Text oder als `style="…"` an einem Element. Der Server prüft es, schreibt es als Datei neu und bindet sie nur an diesen Artikel. Im ausgelieferten HTML bleibt weder `<style>` noch `style=`; `sanitizer_notes` nennt, was entfernt wurde.

```html
<style>
.x-hero { display: grid; gap: 1rem; padding: 3rem 1.5rem; border-radius: 16px; color: #fff;
  background: linear-gradient(135deg, #256e64, #45a595); animation: x-rein .8s ease-out both; }
.x-hero h2 { font-family: var(--serif); font-size: clamp(1.8rem, 4vw, 3rem); margin: 0; }
@keyframes x-rein { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
@media (max-width: 40rem) { .x-hero { padding: 2rem 1rem; } }
</style>
<header class="x-hero"><h2>Titel des Aufmachers</h2><p>Kurzer Anreißer.</p></header>
```

- **Eigene Klassen** nur `x-` plus Kleinbuchstaben, Ziffern, `-` (`x-hero`). `id` gibt es nicht. Zusätzliche Elemente: `header`, `footer`, `hgroup`, `wbr`.
- **Selektoren** müssen mit einer `x-`-Klasse oder einer Klasse aus „Klassen für Gestaltungsmittel“ beginnen; danach sind Tags, Leerzeichen und `>` erlaubt (`.x-hero h2`, `.x-karte > p`). Für den ganzen Text setze einen Wrapper `<div class="x-seite">`. Nicht erlaubt: `body`, `html`, `:root`, `*` allein, `#id`, Attribut-Selektoren (außer `[open]`), `+` und `~`, Klassen des Seitengerüsts. Erlaubt: `:hover`, `:focus-visible`, `:first-child`, `:last-child`, `:nth-child()`, `:not()`, `:is()`, `:has()`, `::before`, `::after`.
- **Eigenschaften:** Farben, Hintergrund (Farbe und `linear-/radial-/conic-gradient`), Rahmen, Radius, Schatten, Abstände, Schrift (`font-family` nur `var(--serif)`, `var(--sans)`, `var(--mono)`, `serif`, `sans-serif`, `monospace`), Text, Listen, Flex, Grid, Spalten, Breite/Höhe, `aspect-ratio`, `opacity`, `filter`, `clip-path`, `transform`, `transition`, `animation` mit `@keyframes x-…`, `@media` (Breite, Höhe, `prefers-color-scheme`, `prefers-reduced-motion`, `print`). Die Seitenfarben als `var(--ink)`, `var(--paper)`, `var(--accent)` passen sich dem dunklen Modus an. Eigene Variablen nur `--x-…`.
- **Eingeschränkt:** `position` nur `relative`, `absolute`, `static`; `z-index` 0 bis 10; `content` nur Text; `display: none` ist verboten, damit nichts verschwindet.
- **Entfernt:** `url()` und jede Funktion außerhalb von Farben, Verläufen, `calc`/`min`/`max`/`clamp`/`var`, Transformationen und Filtern, `@import`, `@font-face`, Kommentare, `\`, `!important`, `position: fixed|sticky`, `pointer-events`, `visibility`. Höchstens 24 KB CSS je Artikel. Bilder bleiben Medien aus der Bibliothek; JavaScript gibt es nicht.
- Eine Animation soll ein Hingucker sein, kein Dauerlauf: höchstens ein bis zwei Sekunden, nicht endlos, mit `prefers-reduced-motion` abschaltbar.

## Belege

Jede Quelle ist ein Eintrag in `sources` mit `ref` (Schlüssel, z. B. `s1`), `kind` (`web`, `news`, `study`, `official`, `dataset`, `book`, `interview`, `video`, `podcast`, `social`, `other`), `title`, `url`, `publisher`, `author`, `published` (JJJJ, JJJJ-MM oder JJJJ-MM-TT), `accessed` (JJJJ-MM-TT) und `note`. Pflicht sind `ref`, `kind` und `title`.

Im Text steht der Beleg direkt hinter der Aussage: `<a data-cite="s1"></a>`, mehrere mit `<a data-cite="s1,s3"></a>`. Der Renderer macht daraus nummerierte Fußnoten mit Sprungmarke zum Quellenverzeichnis.

Setze zusätzlich aktive Links im Fließtext auf passende Originalquellen und weiterführende Informationen, mit aussagekräftigem Linktext: `<a href="https://example.org/bericht">Bericht der Behörde</a>`. Links mit `href` werden automatisch mit `target="_blank"` und `rel="noopener"` ausgegeben. Der `data-cite`-Beleg bleibt für das Quellenverzeichnis erforderlich.

## Layouts, Akzentfarbe, Kategorien

- `standard`: Titelbild über dem Text (Standard).
- `feature`: randloses, breites Titelbild und sehr großer Titel.
- `longform`: für Texte ab 1500 Wörtern; darunter warnt die Formprüfung (`longform_short`).
- `briefing`: dichter Satz mit quadratischem Titelbild neben der Titelzeile; die Mindestwortzahl sinkt auf 40 %.
- `accent`: Akzentfarbe des Artikels als `#rrggbb` (sechsstellig, z. B. `#1a73e8`); der Dark Mode hellt sie automatisch auf.
- `categories`: 1 bis 3 Slugs aus `list_categories`; der erste ist die Hauptkategorie.
- `ai_assisted`: Standard `true` (Kennzeichnung „KI-recherchiert“ und Transparenzkasten); selbst geschriebene Artikel setzen `false`.

## Pflichtangaben der Formprüfung

`validate_post` und `submit_post` prüfen dasselbe. Fehler blockieren das Einreichen, Warnungen nicht. Die Mindestwerte stehen im Briefing unter `form_rules` und in `form_check.metrics.minimums`.

Fehler:

- `title_missing`: Titel fehlt.
- `excerpt_missing`: Teaser (`excerpt`) fehlt.
- `too_short`: weniger Wörter als `min_words` (bei Tiefe `deep` `min_words_deep`, bei `briefing` 40 %).
- `too_few_sources`: weniger Quellen als `min_sources` (bei `deep` `min_sources_deep`).
- `single_domain`: alle Quellen mit Adresse liegen auf einer Domain.
- `source_url_missing`: Quelle ohne `url`, außer bei `book`, `interview`, `other`.
- `no_citations`: der Text enthält kein `data-cite`.
- `unknown_citation`: `data-cite` verweist auf einen `ref`, den es nicht gibt.
- `unknown_media`: unbekannte Medien-UID.
- `media_incomplete`: einem Medium fehlt `alt` (Bild, Video), `credit`, `license` oder `source_url` (nicht bei eigenem Material des Inhabers).
- `cover_missing`: kein Titelbild, wenn die Formvorgabe `require_cover` gilt.
- `category_count`: keine oder mehr als 3 Kategorien.
- `category_primary`: nicht genau eine Hauptkategorie.

Warnungen:

- `title_long`: Titel über 90 Zeichen.
- `excerpt_length`: Teaser außerhalb von 70 bis 180 Zeichen.
- `no_subheadings`: über 700 Wörter ohne `<h2>`.
- `tldr_missing`: ein Kasten „Kurz gesagt“ fehlt — vor der ersten `<h2>` (Gesamt-Kasten) oder in einem `<h2>`-Abschnitt; die Meldung nennt die Abschnitte.
- `component_invalid`: ein Baustein ist falsch befüllt — Vorher/Nachher ohne genau zwei bekannte Bilder, Erklärpunkte ohne Punkt mit `data-x`/`data-y` oder mit mehr als 12, Wertung ohne `data-score` oder über `data-max`, Galerie-Form „carousel“/„strips“ mit weniger als 2 Bildern, „strips“ mit mehr als 8; bei Diagrammen: Typ unbekannt, Tabelle fehlt oder passt nicht zum Typ, Zelle keine Zahl, negativer Wert bei Kreis, Waffel, Baumkarte, Sankey und Blasengröße, mehr als 6 Teile bei Kreis/Ring, Größengrenzen überschritten, `data-interactive` an einem Typ ohne Interaktion, Karte ohne `data-map` oder mit unbekanntem Regionscode; die Meldung nennt Baustein und Fehler.
- `longform_short`: Layout `longform` unter 1500 Wörtern.
- `source_publisher_missing`: Quelle ohne `publisher`.
- `uncited_source`: Quelle wird im Text nirgends zitiert.
- `possible_duplicate`: Titel zu über 80 % ähnlich zu einem veröffentlichten Artikel.
