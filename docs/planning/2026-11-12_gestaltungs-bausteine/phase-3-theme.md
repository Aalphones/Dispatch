# Phase 3 — Theme: bausteine.css, bausteine.js, Bildansicht

Komplexität: standard · Modell: Sonnet

## Kontext (vor dem Start lesen)

- `artifacts/README.md` und `artifacts/_entwurf-bausteine/bausteine.css` + `bausteine.js` — **Teil 1 ist die Vorlage**, Teil 2 (Entwurfsleiste, `.dummy`, `.d-*`, `[data-d-grau]`, Prüfhilfe `?klick=`) wird nicht übernommen.
- `app/theme/artikel.js` (`create()`, `largestSource()`, `setUpLightbox()`, Aufrufliste am Ende), `app/theme/style.css` (Abschnitt Lightbox `dialog.lightbox …`, Regeln `.timeline`, Galerie `.galerie`).
- `docs/conventions/frontend.md` (Tokens, Breakpoints 46rem/62rem, Reduced Motion, Druck, CSP: Maße nur per `element.style`).
- Fehlerklassen (Vault) `frameworks/css-architecture.md` → „Freiraum per Innenabstand“: in der Bildansicht keine Mindesthöhe über `padding` erzwingen (Vorlage löst das mit absolut gesetztem Bild).

Verstanden, bevor geändert: `setUpLightbox()` ist die einzige Bildansicht für alle Bilder (`.abb .foto`, `.galerie .foto`) und arbeitet per Ereignis-Delegation, damit sie auch für nachträglich eingefügten Vorschau-Inhalt greift — das muss die neue Fassung behalten. `largestSource()` liefert die größte WebP-Variante und ersetzt in der Vorlage die Stelle `quelle(foto)` für das große Bild (Vorschaubilder nehmen `currentSrc`). Die alten `.timeline`-Regeln in `style.css` stylen die bisherige Zeitleiste; nach Phase 2 gibt es im Ausgabe-Markup keine `.timeline` mehr, sie können weg (`.steps`, `.checklist` in derselben Zeile bleiben).

## Abnahmekriterien

1. `app/theme/bausteine.css` = Teil 1 der Entwurfs-CSS ohne den Abschnitt „Bildansicht“; Rohgröße ≤ 16 000 Byte (Kommentare kürzen ist erlaubt, Regeln nicht).
2. `app/theme/bausteine.js` = ES-Modul mit `export function start(root = document)`, das Karussell, Bildstreifen, Vorher/Nachher und Erklärpunkte unter `root` startet (Funktionen aus Teil 1, ohne `bildansicht()`); bereits gestartete Elemente (Klasse `bereit` bzw. `data-gestartet`) überspringt; Rohgröße ≤ 8 000 Byte.
3. `artikel.js`: `setUpLightbox()` durch die Vorlage `bildansicht()` ersetzt (deutsche Namen der Vorlage dürfen in englische des Bestands übertragen werden, Verhalten identisch); großes Bild über `largestSource()`; Tastatur-Zugang (tabIndex, `role="button"`, `aria-label`) für alle `.abb .foto, .galerie .foto`; Rohgröße ≤ 12 500 Byte.
4. `style.css`: Lightbox-Abschnitt durch den Abschnitt „Bildansicht“ der Entwurfs-CSS ersetzt (inkl. Handy-Regeln), alte `.timeline`-Regeln entfernt; Rohgröße ≤ 50 000 Byte.
5. Sichtprüfung im Browser gegen den Entwurf: alle Bausteine hell/dunkel bei 390, 860, 1366 px; Bildansicht in Mosaik, Karussell, Bildstreifen, Standard-Galerie und Einzelbild; Reduced Motion schaltet Puls und Bild-Einblendung ab; Druckansicht zeigt Karussell als Raster, Vorher/Nachher nebeneinander.

## Checkliste

- [x] `app/theme/bausteine.css` aus Teil 1 anlegen (Abschnitt „Bildansicht“ und dessen Handy-Regeln auslassen).
- [x] `app/theme/bausteine.js` aus Teil 1 anlegen: `schmal`, `neu`, `knopf`, `karussell`, `streifen`, `vergleich`, `erklaerbild`; `start(root)` statt `bausteineStarten()`; Doppelstart-Schutz je Element.
- [x] `artikel.js`: `setUpLightbox()` ersetzen, Aufruf am Ende beibehalten; vorhandenes `create()` statt `neu()` nutzen.
- [x] `style.css`: Lightbox-Abschnitt ersetzen, `.timeline`-Regeln entfernen (`.steps`, `.checklist` bleiben).
- [x] Größen messen: `(Get-Item app/theme/<datei>).Length` für alle vier Dateien.
- [ ] Sichtprüfung laut AK 5 (Seite aus Phase 4 oder `artifacts/entwurf-bausteine.html` mit den neuen Theme-Dateien).

## Report-Back

Status: complete (AK 1–4). Größen: bausteine.css 15 510, bausteine.js 6 856, artikel.js 12 334, style.css 49 967 Byte. Offen: AK 5 (Sichtprüfung im Browser) — private-Profil ohne Live-Lauf, geht in die Abnahme/Phase 4 (Seite mit Demo-Artikel). Abweichung: style.css musste knapp unter 50 000 Byte gekürzt werden (Basisregeln des alten Lightbox-Abschnitts zusammengezogen, `::backdrop`-Regel entfallen, da der Dialog den Bildschirm deckt). `artikel.js` markiert Bilder zusätzlich per MutationObserver (Vorschau-Inhalt).
