# Style-Guide für Artikel-HTML

`content_html` ist ein HTML-Fragment ohne `<html>` und `<body>`. Der Server bereinigt es beim Speichern mit einer Whitelist (`sanitizer_notes` in der Antwort von `create_post` und `update_post` nennt, was entfernt wurde). Was hier nicht steht, wird entfernt.

## Erlaubte Elemente

`p`, `h2` bis `h6`, `ul`, `ol`, `li`, `dl`, `dt`, `dd`, `table`, `caption`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`, `figure`, `figcaption`, `aside`, `section`, `details`, `summary`, `blockquote`, `div`, `span`, `a`, `strong`, `em`, `b`, `i`, `u`, `s`, `sub`, `sup`, `small`, `mark`, `abbr`, `cite`, `q`, `code`, `pre`, `kbd`, `br`, `hr`, `time`.

- `<h1>` wird zu `<h2>`: Der Titel des Artikels steht im Feld `title`, nicht im Text. Gliedere mit `<h2>`, darunter `<h3>`.
- Entfernt samt Inhalt: `script`, `style`, `iframe`, `object`, `embed`, `form`, `input`, `button`, `svg`, `math`, `img`, `picture`, `video`, `audio`, `canvas` und weitere. Bilder, Videos und Audio kommen nur über die Medien-Syntax unten.
- Attribute: nur `href` und `title` an `a` (Schemata `http`, `https`, `mailto`), `title` an `abbr`, `colspan`, `rowspan` (1 bis 50) und `scope` an Tabellenzellen, `start` an `ol`, `datetime` an `time`, `open` an `details` sowie die Attribute der Medien- und Beleg-Syntax. `style`, `id`, `onclick` und alle anderen fallen weg.

## Klassen für Gestaltungsmittel

Erlaubt sind genau diese Klassen; jede andere wird entfernt.

`lead` (Einleitungsabsatz), `tldr` (Kasten „Kurz gesagt“ — der Titel kommt automatisch, `aside` oder `div`), `callout` mit `note`, `warning` oder `key`, `factbox`, `pullquote`, `stats` mit `stat`, `timeline`, `steps`, `checklist`, `two-col` mit `pros` und `cons`, `compare` (Tabelle), `qa` (Fragen und Antworten, als `dl` mit `dt`/`dd`), `definition`, `details`, `opinion` (Meinung, wird automatisch gekennzeichnet), `update` (Korrektur, wird automatisch gekennzeichnet), `sidenote` (Randnotiz, der Titel kommt automatisch), `dropcap` (Initiale), `gallery`.

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
- **Galerie:** `<div class="gallery" data-media="UID1,UID2,UID3"></div>`, höchstens 30 UIDs.
- **Einbettung** (lädt erst nach Klick): `<figure data-embed="youtube" data-id="VIDEO-ID"></figure>`; Anbieter `youtube` oder `vimeo`, `data-id` aus Buchstaben, Ziffern, `_` und `-`.
- Das Titelbild steht nicht im Text, sondern im Feld `cover_media_uid` und muss ein Bild sein.
- Unbekannte UIDs blockieren das Einreichen (`unknown_media`).

## Belege

Jede Quelle ist ein Eintrag in `sources` mit `ref` (Schlüssel, z. B. `s1`), `kind` (`web`, `news`, `study`, `official`, `dataset`, `book`, `interview`, `video`, `podcast`, `social`, `other`), `title`, `url`, `publisher`, `author`, `published` (JJJJ, JJJJ-MM oder JJJJ-MM-TT), `accessed` (JJJJ-MM-TT) und `note`. Pflicht sind `ref`, `kind` und `title`.

Im Text steht der Beleg direkt hinter der Aussage: `<a data-cite="s1"></a>`, mehrere mit `<a data-cite="s1,s3"></a>`. Der Renderer macht daraus nummerierte Fußnoten mit Sprungmarke zum Quellenverzeichnis.

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
- `longform_short`: Layout `longform` unter 1500 Wörtern.
- `source_publisher_missing`: Quelle ohne `publisher`.
- `uncited_source`: Quelle wird im Text nirgends zitiert.
- `possible_duplicate`: Titel zu über 80 % ähnlich zu einem veröffentlichten Artikel.
