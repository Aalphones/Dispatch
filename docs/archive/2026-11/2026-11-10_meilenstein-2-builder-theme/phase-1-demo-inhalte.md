# Phase 1 — Demo-Inhalte und Live-Lesepfad ergänzen

**Rating:** standard · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" (Seitendaten: `updated_at`)
- ADR-012 (Live-Lesepfad), ADR-002 (CLI läuft nur lokal)
- `bin/dispatch` (Befehls-Schalter, Fehler-Handler), `app/src/Domain/PostService.php` (`create`, `submit`, `approve`; Eingabefelder in `FIELDS`), `app/src/Domain/MediaService.php` (`store(string $bytes, array $meta, string $origin, Actor $actor)`), `app/src/Domain/CategoryService.php` (`create`), `app/src/Util/FormCheck.php` (welche Regeln `approve` blockieren), `app/src/Util/Sanitizer.php` (erlaubte Elemente, Klassen, `data-*`)
- `docs/models.md` → posts, media, categories
- Fehlerklassen geprüft: keine einschlägig

## Warum

Ohne veröffentlichte Artikel lässt sich keine Seite des Builders prüfen, und Freigaben gibt es vor Meilenstein 4 nur über Services. Ein lokaler Befehl legt einen realistischen Bestand an: alle Layouts, alle Bausteine, Bilder in allen Lagen, Unterkategorien, Schlagwörter.

## AK der Phase

1. `php bin/dispatch demo` auf leerer Datenbank (nach `migrate` und `seed`) legt 11 Kategorien (5 Haupt-, 6 Unterkategorien), 14 Artikel und deren Bilder an und gibt alle 14 frei; Ausgabe: je Artikel eine Zeile `slug`.
2. Ein zweiter Aufruf bricht mit `Es gibt schon Artikel. Demo nur auf leerer Datenbank (php bin/dispatch demo --force legt trotzdem an).` ab; `--force` legt einen zweiten Satz an (Slugs bekommen `-2`).
3. Jeder Demo-Artikel besteht die Formprüfung ohne Fehler (sonst bricht `approve` ab).
4. Abgedeckt sind: Layouts `standard` (8), `feature` (2), `longform` (2), `briefing` (2); mindestens ein `longform` enthält **jeden** Baustein aus der Sanitizer-Klassenliste, `<aside class="tldr">` am Anfang und nach jeder `<h2>`, eine `<aside class="sidenote">`, `figure data-media` in allen fünf `data-layout`-Werten, eine `div.gallery` mit 3 Bildern, `figure data-embed="youtube"`, mindestens 3 `data-cite`-Belege.
5. `published_at` der Demo-Artikel liegt über 40 Tage verteilt (neuester heute).
6. `PostRepository::livePosts()` liefert zusätzlich `updated_at` = `created_at` der Live-Revision.

## Checkliste

- [x] `PostRepository::livePosts()`: `SELECT r.snapshot, p.published_at, r.created_at AS updated_at …`; im Ergebnis `$snapshot['updated_at'] = $row['updated_at']`. PHPDoc ergänzen. Sonst nichts an der Methode ändern.
- [x] Ordner `bin/demo/` anlegen (wird nicht hochgeladen, `bin/` liegt nicht im Webroot): `bin/demo/demo.php` liefert `return ['categories' => [...], 'posts' => [...]];`, die Artikeltexte als `bin/demo/<slug>.html`.
  - Kategorien: Wissenschaft (Klima, Raumfahrt), Technik (KI, Sicherheit), Gesellschaft (Verkehr), Wirtschaft (Energie, Arbeit), Kultur — je mit Beschreibung (ein Satz) und `position`.
  - Je Artikel: `title`, `subtitle`, `kicker`, `excerpt` (70–180 Zeichen), `layout`, `accent` (bei 4 Artikeln gesetzt, sonst `null`), `tags` (2–5, aus einem Vorrat von 14 Schlagwörtern, damit sich Schlagwörter überschneiden), `categories` (eine `primary`, bei 3 Artikeln eine zweite), `sources` (3–6, mindestens zwei verschiedene Domains, `kind` gemischt), Datei des Textes, Zahl der Bilder im Text.
  - Texte: deutsch, sachlich, je ≥ 650 Wörter (`briefing` ≥ 650 ebenfalls, damit die Mindestlänge sicher hält), mit `<h2>`-Gliederung und `data-cite` auf vorhandene `ref`s. Platzhalter `{{bild1}}`, `{{bild2}}` … stehen dort, wo später die Medien-UID eingesetzt wird.
- [x] Bilder ohne Netz erzeugen: Funktion `demoBild(int $nr, string $farbe): string` in `bin/demo/demo.php` — GD, 1600 × 1000, diagonaler Verlauf aus zwei Farbtönen, darauf mit `imagestring` „DISPATCH Demo {nr}"; `imagejpeg` in einen Puffer (`ob_start`). Hochformat-Variante 1000 × 1250 für `data-layout="left"`/`"right"`.
- [x] Befehl `demo` in `bin/dispatch` (Hilfetext ergänzen): 
  1. Abbruch wie AK 2, wenn `SELECT COUNT(*) FROM posts` > 0 und kein `--force`.
  2. Kategorien per `CategoryService::create(..., Actor::owner())` (Eltern zuerst; `parent_id` aus der ID der Elternkategorie). Existiert der Slug (bei `--force`), vorhandene nehmen.
  3. Je Artikel: Titelbild + Textbilder per `MediaService::store($bytes, ['alt' => …, 'caption' => …, 'credit' => 'DISPATCH Demo', 'license' => 'CC0 1.0', 'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/'], 'owner', Actor::cli())`; Platzhalter im HTML durch die UIDs ersetzen; `PostService::create([... , 'cover_media_uid' => $uid, 'content_html' => $html], Actor::cli())`; `submit($id, Actor::cli())`; `approve($id, Actor::owner())`.
  4. Danach `published_at` verteilen: für den i-ten Artikel (0 = neuester) `UPDATE posts SET published_at = UTC_TIMESTAMP() - INTERVAL ? DAY WHERE id = ?` mit `i * 3` Tagen. Kommentar: „nur Demo: Freigaben am selben Tag würden die Sortierung nicht zeigen".
  5. Wirft `submit`/`approve` einen `DomainError`, die Fehlerliste (`details`) ausgeben und mit Exit 1 enden — der Demo-Text ist dann falsch, nicht die Formprüfung.
- [x] Doc: `docs/code-map.md` → Repo-Tabelle Zeile `bin/dispatch` um `demo` und `build` (ab Phase 2) ergänzen; Zeile `bin/demo/` (Demo-Daten, nur lokal).
- [x] Doc: `AGENTS.md` → „Lokal starten": nach `migrate` die Befehle `php bin/dispatch seed` und `php bin/dispatch demo` nennen.

## Report-Back

Belege (lokal, PHP 8.5, Datenbank `dispatch_dev`, die schon einen M1-Testartikel und eine Kategorie enthielt — daher `--force`):

1. AK 1: `demo --force` legte 11 Kategorien (5 Haupt-, 6 Unter-) und 14 Artikel an, gab alle frei, Ausgabe 14 Slug-Zeilen. Auf wirklich leerer Datenbank nicht gelaufen (kein Recht, eine Hilfsdatenbank anzulegen).
2. AK 2: zweiter Aufruf ohne `--force` bricht mit dem Wortlaut aus dem AK ab, Exit 1. Die `-2`-Slugs bei `--force` sind nicht gegengeprüft (hätten 14 weitere Artikel erzeugt); sie folgen aus `PostService::uniqueSlug`.
3. AK 3: `submit` und `approve` liefen für alle 14 ohne `DomainError`.
4. AK 4: 8 standard, 2 feature, 2 longform, 2 briefing; der Longform „Prüfstand für Sprachmodelle“ enthält alle 25 Klassen (Skript: keine fehlende), `figure data-media` in inline/wide/full/left/right, Galerie mit 3 Bildern, YouTube-Einbettung, 6 `data-cite`, `tldr` am Anfang und nach jeder der 5 `<h2>`.
5. AK 5: `published_at` im Abstand von 3 Tagen, ältester 39 Tage zurück, neuester heute.
6. AK 6: `livePosts()` liefert `updated_at` für alle Live-Artikel.

Abweichungen: (a) Der Plan nennt 7 Unterkategorien, das AK nennt 6; „Verkehr“ unter Gesellschaft entfiel, der Radweg-Artikel liegt direkt in Gesellschaft. (b) Die Textlängen liegen bei 638–880 Wörtern (Ziel 650; Minimum der Formprüfung 600); die zwei Briefings haben 389 und 463 (Minimum 240). (c) Quellen sind erfundene Beispiele auf `example.*`-Adressen; Slugs entstehen aus den Titeln und sind lang.
