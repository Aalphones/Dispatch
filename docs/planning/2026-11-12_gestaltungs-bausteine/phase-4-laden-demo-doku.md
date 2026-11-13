# Phase 4 — Laden, Demo-Artikel, Doku, ADR-029

Komplexität: standard · Modell: Sonnet

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans (Kontrakt, Abschnitt „Laden“, Größengrenzen).
- `app/src/Site/Assets.php` (`THEME_FILES`, `collect()`, `hashedPath()`), `app/templates/layout.php` (Attribute an `<html>`, Stylesheet-Link im Kopf, `$meta['scripts']`), `app/src/Site/Builder.php` (`collectArticles()`, `$this->rendered`).
- `app/theme/artikel.js`, `app/theme/vorschau.js` und `app/theme/redaktion/post.js` (beide importieren `artikel.js` nach dem Einfügen der Vorschau).
- `bin/demo/demo.php` (Eintrag `gletscherschmelze-alpen-messnetz`, Feld `bilder` = Liste „Hochformat ja/nein“ je `{{bildN}}`), `bin/demo/gletscherschmelze-alpen-messnetz.html`, `bin/demo/pruefe-inhalt.php`.
- `docs/conventions/frontend.md` (Budget-Zeile), `docs/decisions/015-theme-budgets-skriptmodule.md`, `docs/code-map.md`.
- Fehlerklassen (Vault) geprüft: keine einschlägig.

Verstanden, bevor geändert: `Assets::THEME_FILES` nutzt den Dateinamen als Schlüssel der Adressen (`$urls['artikel']`); `bausteine.js` und `bausteine.css` hätten denselben Schlüssel. `artikel.js` wird als Modul nur einmal ausgewertet, auch wenn die Vorschau es erneut importiert.

## Abnahmekriterien

1. `Assets` legt `assets/bausteine.<hash>.js` und `assets/bausteine.<hash>.css` ab; Adressen unter den Schlüsseln `bausteine` und `bausteinestil`.
2. `<html>` trägt `data-bausteine` und `data-bausteinestil`; Artikelseiten mit `rendered['bausteine'] === true` haben im Kopf nach `style.css` ein `<link rel="stylesheet">` auf `bausteinestil`, andere nicht.
3. `artikel.js` lädt per `import(dataset.bausteine)` und ruft `start()`, wenn `.galerie[data-variante="karussell"], .galerie[data-variante="streifen"], .vergleich, .erklaerbild` im Dokument steht; steht irgendein Baustein (`.galerie[data-variante], .vergleich, .erklaerbild, .wertung, .zeitstrahl, .akkordeon`) im Dokument und fehlt ein Link auf `dataset.bausteinestil`, hängt es ihn an (Vorschau). Artikel ohne Bausteine laden keine der beiden Dateien (Netzwerk-Tab).
4. Demo: `php bin/dispatch demo` legt den Gletscher-Artikel mit allen Bausteinen an (Mosaik-Galerie, Vorher/Nachher, Zeitstrahl mit `highlight`, Erklärpunkte, Wertung, Aufklappbarer Text), `php bin/demo/pruefe-inhalt.php` läuft ohne Befund, die Formprüfung meldet kein `component_invalid`.
5. Doku: `frontend.md` nennt die neuen Größengrenzen und die zwei neuen Dateien; ADR-029 liegt vor; `code-map.md` hat die Zeile „Gestaltungs-Bausteine“.

## Checkliste

- [ ] `Assets::THEME_FILES` auf `Schlüssel => [Datei, Endung]` umstellen (bestehende Einträge: Schlüssel = Datei) und `'bausteine' => ['bausteine', 'js']`, `'bausteinestil' => ['bausteine', 'css']` ergänzen; `collect()` anpassen.
- [ ] `layout.php`: `data-bausteine` und `data-bausteinestil` an `<html>`; nach dem `style`-Link `foreach ((array) ($meta['styles'] ?? []) as $style)` → `<link rel="stylesheet" href="…">`; `@var`-Kommentar um `styles` ergänzen.
- [ ] `Builder::collectArticles()`: `'styles' => ($this->rendered[$post['id']]['bausteine'] ?? false) ? [$this->assetUrls['bausteinestil']] : []`.
- [ ] `artikel.js`: Funktion `loadComponents()` laut AK 3, Aufruf am Ende der Aufrufliste.
- [ ] Demo-Artikel `bin/demo/gletscherschmelze-alpen-messnetz.html` um die Bausteine erweitern (je in einen passenden `h2`-Abschnitt, Texte sachlich passend, Pflicht-`tldr` je Abschnitt bleibt); `demo.php` → `bilder` auf so viele Einträge erweitern, wie `{{bildN}}` vorkommen (alle `false`).
- [ ] `docs/decisions/029-gestaltungs-bausteine.md` (Kontext / Optionen / Entscheidung / Konsequenzen): feste Bausteine statt freiem CSS/JS für Agenten; englische Agenten-Syntax, deutsche Ausgabe; eigene Nachlade-Dateien statt Wachstum von `artikel.js`/`style.css`; Größengrenzen aus dem README; Bildansicht bleibt in `artikel.js`, weil sie jedes Bild betrifft; Zeitstrahl baut auf der bestehenden `timeline`-Syntax auf; Reiter verworfen (2026-11-13).
- [ ] `docs/conventions/frontend.md`: Budget-Zeile auf die neuen Werte, `bausteine.js`/`bausteine.css` mit Ladebedingung ergänzen; Verweis ADR-029.
- [ ] `docs/code-map.md`: Zeile „Gestaltungs-Bausteine → `app/src/Util/Sanitizer.php`, `app/src/Util/FormCheck.php`, `app/src/Site/ContentRenderer.php`, `app/theme/bausteine.*`, `app/theme/artikel.js`“.
- [ ] Größen aller Theme-Dateien gegen die Grenzen messen; `php -l` für alle geänderten PHP-Dateien; Site bauen (`php bin/dispatch build`) und Demo-Artikel im Browser ansehen.
- [ ] README dieses Plans: Status, Summary, Files touched füllen.

## Report-Back
