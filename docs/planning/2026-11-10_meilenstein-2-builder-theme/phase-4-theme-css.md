# Phase 4 — Theme-CSS nach Entwurf

**Rating:** standard · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- Entwurf `docs/design/entwurf-website.html` → der `<style>`-Block ist die **Quelle**; `artifacts/README.md` dieses Plans sagt, was davon Website ist und was nicht
- ADR-013, ADR-015 (Budget `style.css` ≤ 48 000 Byte, Bewegungs-Variablen)
- `docs/conventions/frontend.md` (Systemschriften, Dark Mode, `prefers-reduced-motion`, Druck, Fokusrahmen, Kontrast AA)
- Phase 3 → Klassen, die der `ContentRenderer` erzeugt (`abb`, `foto`, `galerie`, `einbettung`, `beleg`, `kurz-titel`, `dokument`)
- `app/src/Util/Sanitizer.php` → `ALLOWED_CLASSES` (Klassen der Agenten)
- Fehlerklassen (Vault `frameworks/css-architecture.md`): **Freiraum per Innenabstand reserviert, Liste quillt aus ihrem Feld** — betrifft die scrollbare `.seitenspalte` (Inhaltsverzeichnis, `max-height` + `overflow: auto`): keinen großen `padding-bottom` an ihr; Freiraum, wenn nötig, als `::after`. Prüfen per Fenster mit 600 px Höhe: die Spalte überlappt den Fuß nicht und hat keinen zweiten Rollbalken.

## Was aus dem Entwurf übernommen wird

**Übernehmen (umbenannt, wo links steht):**

| Entwurf | Website |
|---|---|
| Tokens (`:root`, Dunkel-Varianten, `@view-transition`, Bewegungs-Variablen, Schatten) | 1:1; Akzent aus Einstellung `accent` als `style="--accent:…"` am `<html>`, je Artikel `accent` am `<article>` |
| Grundlagen, `.sprung`, `.wrap`, `.klein`, `.ico`, `.fortschritt` | 1:1 |
| Kopf (`.leiste`, `.ausgabe`, `.kopf-rechts`, `.suche`, `.suche-feld`, `kbd`, `.taste`, `.suche-zu`, `.panel` + Inhalt, `.titelkopf`, `.nav`) | 1:1 — `.konto` **nicht** (Meilenstein 4) |
| `.chips`, `.chip` (+ `.tag`, `.kat`, `.aktiv`, `.zahl`, `.x`), `.badge` (`.ki`, `.quellen`, `.zeit`, `.fokus`) | 1:1 |
| Startseite (`.aufmacher`, `.dach`, `.teaser`, `.meta`, `.foto`, `.raster`, `.karte`, `.seiten` + Unterteile) | 1:1 |
| Artikel (`.krumen`, `.artikelkopf`, `.autor`, `.badges`, `.titelbild`, `.koerper`, `.seitenspalte`, `.iv` + `.marker`, `.text`, `.lead`, `.dropcap`, `.callout`, `.factbox`, `.pullquote`, `.stats`/`.stat`, `sup a`, `.abb` mit allen Layouts, `.galerie`, `.transparenz`, `.quellen`, `.abschnitt-titel`, `.vornach`, `.tags-unten`, `.iv-mobil`, `.kopf-tags`) | 1:1 |
| `.kurz` (Kasten „Kurz gesagt") | als `.tldr, .sidenote`; `.kurz b` → `.kurz-titel`; `.kurz.gesamt` → `.tldr:first-child` (der erste im Text); Randspalten-Regel im Block `min-width: 62.01rem` gilt für `.tldr, .sidenote` |
| `.tldr` des Entwurfs (Linien-Kasten) | entfällt |
| Fokusmodus (`.fokus-raus`, alle `body.fokus …`) | 1:1, ohne `.redkasten`, `.fab`, `.pult`, `.reaktion-unten` |
| Kategorien (`.seitenkopf`, `.katgrid`, `.katkarte` + alle Zustände, `@media (hover: none)`) | 1:1, ohne `.gewicht-inline` |
| Schnellfilter (`.filterleiste`, `.filterzeile`, `.ergebniszahl`, `.leeren`) | 1:1 |
| Fuß (`.fuss` + Teile) | 1:1 |
| Umbrüche `max-width: 62rem` und `max-width: 46rem` | 1:1, ohne Regeln für Redaktions-Klassen |
| `prefers-reduced-motion` | 1:1 |

**Nicht übernehmen (Meilenstein 4 bzw. nur Entwurf):** `.red*`, `.konto`, `.zaehler`, `[popover]`, `.menue`, `.wartet*`, `.wartekarte`, `.vorher-nachher`, `del`/`ins`-Färbung, `.status`, `.knopf*`, `.reaktion*`, `.kennzahl`, `.redkasten`, `.funken`, `.freigabe*`, `.pruef*`, `.vorschau-band`, `.anmerkung`, `.k-*`, `.kommentiert`, `.fab`, `.gewicht*`, `dialog`/Formular-Stile außer der Lightbox, `.liste`, `.balken`, `.kpi*`, `.int*`, `.signale`, `.toast`, `.ansicht`, `.pult`, `#geraet`, `.nur-schmal`-Hilfsregeln, die nur der Entwurf braucht.

**Neu (kein Entwurf, in der Formensprache der Entwurfs-Bausteine):**

- Lightbox: `dialog.lightbox` — randlos, Hintergrund `rgba(10,8,5,.92)`, Bild `max-width: 96vw; max-height: 90vh; object-fit: contain`, Schließen-Knopf oben rechts wie `.zu` im Entwurf, Bildunterschrift darunter in `--sans` .8rem hell.
- Einbettung: `.einbettung` = Seitenverhältnis 16/9, Hintergrund `--paper-2`, `.einbettung-start` mittig als runder Knopf in `--accent-ink` mit Dreieck (CSS `clip-path`), Text darunter `.8rem`; nach dem Laden füllt das `iframe` die Fläche.
- Bausteine ohne Entwurf, alle mit `border-radius: 10px` und Abständen wie `.factbox`: `.timeline` (senkrechte Linie links in `--rule`, Punkte in `--accent-ink`), `.steps` (nummerierte Liste mit runden Ziffern wie `.k-nr` im Entwurf), `.checklist` (Häkchen per `::before` in `--ok`), `.two-col` (zwei Spalten, auf ≤ 46rem untereinander; `.pros` Kopflinie `--ok`, `.cons` Kopflinie `--bad`), `.compare` (Tabelle mit Kopfzeile in `--paper-2`, waagerecht scrollbar auf dem Handy), `.qa` (Frage fett in `--serif`, Antwort eingerückt), `.definition` (Begriff in Kapitälchen, Kasten wie `.callout` ohne Farbstreifen), `details`/`.details` (Summary mit drehendem Pfeil wie `.iv-mobil`), `.opinion` (Kasten mit Etikett „Meinung" per `::before` in `--warn`), `.update` (Kasten mit Etikett „Korrektur" per `::before` in `--accent-ink`), `.callout.note|.warning|.key` (Streifenfarbe `--ink-3` | `--warn` | `--accent-ink`), `.dokument` (Link mit Dokument-Symbol per `::before`).
- Druck (`@media print`): Kopf, Fuß, Seitenspalte, `.iv-mobil`, Einbettungen und Skript-Knöpfe aus; `a[href^="http"]::after { content: " (" attr(href) ")"; }` in `.text` und `.quellen`; schwarz auf weiß; `.abb` nicht umbrechen (`break-inside: avoid`) — nur auf `.abb`, `.callout`, `.factbox`, nicht auf Tabellen oder Raster.

## AK der Phase

1. `app/theme/style.css` ≤ 48 000 Byte (`(Get-Item app/theme/style.css).Length`).
2. Demo-Startseite und Demo-Longform sehen in 1400, 820 und 390 px Breite, hell und dunkel, aus wie die entsprechenden Entwurfs-Ansichten. Prüfpunkte: Startseite — Aufmacher zweispaltig (≤ 46rem einspaltig), Raster 3/2/1-spaltig mit Trennlinien, auf dem Handy Liste mit quadratischem Bild links (6,5rem); Artikel — drei Spalten 12rem/38rem/12rem mit 3rem Abstand, „Kurz gesagt" rechts neben seinem Abschnitt, `wide` und Galerie beginnen unter dem „Kurz gesagt" und ragen nur nach rechts, `full` über die ganze Breite, `left`/`right` in der Textspalte; ≤ 62rem eine Spalte mit klebender `.iv-mobil` und Schlagwörtern unter dem Kopf; Fuß-Schlagwörter brechen auf dem Handy um.
3. Kein Element erzeugt waagerechtes Scrollen bei 390 px (Prüfung: `document.documentElement.scrollWidth === 390` in der Konsole).
4. Kontrast: `--ink-3` auf `--paper` und `--paper-2` ≥ 4,5 : 1 in hell und dunkel (Prüfung mit dem Kontrast-Werkzeug der Browser-Entwicklertools; bei Unterschreitung `--ink-3` abdunkeln bzw. aufhellen und in der Report-Back-Sektion festhalten).
5. Mit `prefers-reduced-motion: reduce` (Entwicklertools → Rendering) läuft keine Animation.
6. Druckvorschau eines Artikels: nur Text, Bilder, Quellen mit ausgeschriebenen Adressen.

## Checkliste

- [ ] `app/theme/style.css` aus dem Entwurf nach den Tabellen oben aufbauen; Abschnitts-Kommentare wie im Entwurf (`/* ===== … ===== */`) beibehalten, Entwurfs-Kommentare, die nur den Entwurf erklären, entfernen.
- [ ] Neue Bausteine, Lightbox, Einbettung, Druck ergänzen (Abschnitt „Neu" oben).
- [ ] Größe prüfen; bei Überschreitung zuerst doppelte Regeln zusammenfassen, dann Kommentare kürzen — keine Funktion streichen. Bleibt es darüber: in FINDINGS mit Größe festhalten, nicht still das Budget ändern.
- [ ] Doc: `docs/conventions/frontend.md` → Budget-Zeile auf ADR-015 umstellen (CSS ≤ 48 000 Byte, `site.js` ≤ 5 000, `suche.js` ≤ 9 000, `artikel.js` ≤ 7 000); Zeile „Gestaltung: `docs/design/entwurf-website.html` ist verbindlich (ADR-013)".

## Report-Back

**Ergebnis:** `app/theme/style.css` = 41 784 Byte (Budget 48 000, ok). Aufgebaut aus dem Entwurfs-`<style>` nach den Übernahme-Tabellen (ohne Redaktions-/Entwurfsteile), plus Bausteine, Lightbox, Einbettung, Druck. Klammern ausgeglichen. `.kurz` → `.tldr, .sidenote` / `.kurz-titel` / `.tldr:first-child`.
**Offen (nicht belegt):** AK 2–6 verlangen Browser-Sicht (Breiten 1400/820/390, Kontrast, Reduced-Motion, Druckvorschau, 600-px-Höhe bei `.seitenspalte`) — in dieser Sitzung nicht ausgeführt. `--ink-3` unverändert (Entwurfswerte): hell `#6f675b` auf `#f6f1e7`/`#efe7d8` und dunkel `#a39a8a` — Kontrast nicht gemessen. Einbettungs-/Lightbox-Selektoren (`dialog.lightbox`, `.einbettung-start`) folgen dem Plan; das Skript dazu kommt in Phase 6.
