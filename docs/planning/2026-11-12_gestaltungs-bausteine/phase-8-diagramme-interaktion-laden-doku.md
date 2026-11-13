# Phase 8 — Diagramme: Interaktion, Laden, Demo, Doku, ADR-030

Komplexität: standard · Modell: Sonnet · Voraussetzung: Phase 4 und 7 abgeschlossen

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans (Kontrakt Diagramme, Größengrenzen, „Laden“).
- Phase 4 (neuer `Assets`-Aufbau mit Schlüssel → [Datei, Endung], `meta['styles']`, `loadComponents()` in `artikel.js`) und `app/theme/bausteine.js` als Muster für ein Nachlade-Modul (`start(root)`, Doppelstart-Schutz).
- Phase 7 (Markup mit `data-knoten`, `data-von`, `data-nach`, `data-gruppe`, `data-region`, `hidden`-Ebenen).
- `docs/conventions/frontend.md`, `docs/decisions/015-theme-budgets-skriptmodule.md`.
- Dataviz-Skill: `references/interaction.md` (Tooltips, Treffer größer als Marke, Filter in einer Zeile).

Verstanden, bevor geändert: Interaktion nur an Diagrammen mit `data-interaktiv` (Agent setzt `data-interactive="true"`, Renderer reicht es nur für `INTERACTIVE_TYPES` durch). Alles andere bleibt bewusst statisch — „ein sauberer Balken ist ein sauberer Balken“. Maße nur per `element.style`/Attribute (CSP). Tastatur und Touch gleichwertig zur Maus.

## Abnahmekriterien

1. `app/theme/diagramme.js` = ES-Modul `export function start(root = document)` (Rohgröße ≤ 9 000 Byte), Doppelstart-Schutz je Figur (`data-gestartet`), je Typ:
   - **Linie**: Legendeneinträge werden `<button aria-pressed>`; Klick blendet Reihe aus/ein (`hidden` auf der Gruppe); mindestens eine Reihe bleibt sichtbar; Fadenkreuz mit Wertefeld folgt Zeiger/Pfeiltasten.
   - **Heatmap/Kalender**: Wertefeld bei Hover/Fokus/Tippen mit exaktem Wert und Beschriftung; Zellen per Pfeiltasten erreichbar.
   - **Sankey**: Klick/Enter auf Knoten oder Band hebt den zugehörigen Fluss hervor (Vorgänger und Nachfolger, übrige gedimmt über Klasse), erneuter Klick oder Esc hebt auf.
   - **Baumkarte**: Klick auf eine Gruppe blendet deren vorgerechnete Ebene ein; „← Zurück“-Knopf und Esc gehen eine Ebene hoch; Brotkrumen-Zeile nennt den Pfad.
   - **Blasen**: Kategorie-Chips (`button aria-pressed`) blenden Marken aus/ein; Hover/Fokus zeigt Wertefeld. (Zeitfilter nur, wenn die Tabelle eine Spalte `Jahr` hat — sonst keiner.)
   - **Karte**: Klick/Enter auf eine Region füllt `p.diagramm-detail` (aus der Tabellenzeile; Name, Wert, Einheit) und markiert die Region; Esc hebt auf.
2. Ohne JavaScript und ohne `data-interaktiv` ändert sich nichts gegenüber Phase 6/7. Reduced Motion: keine Übergänge. Fokus sichtbar. Wertefeld per `role="status"`-Zeile für Screenreader zusätzlich zum Hover-Hinweis.
3. Laden: `render()` liefert `'diagramme' => bool` und `'diagramme_interaktiv' => bool`; `Assets` legt `diagramme.<hash>.css` (`diagrammestil`) und `diagramme.<hash>.js` (`diagramme`) ab; `<html>` trägt `data-diagramme`/`data-diagrammestil`; der Builder hängt `diagrammestil` an `meta['styles']` nur bei Artikeln mit `diagramme === true`; `loadComponents()` lädt das Skript nur, wenn `.diagramm[data-interaktiv]` im Dokument steht, und hängt in der Vorschau die Stil-Datei an. Artikel ohne Diagramme laden keine der Dateien (Netzwerk-Tab).
4. Größengrenzen: `diagramme.js` ≤ 9 000, `diagramme.css` ≤ 9 000 (oder im README begründet angehoben), `artikel.js` ≤ 12 500 (Erweiterung von `loadComponents()` passt hinein, sonst Abweichung melden).
5. Demo: neuer Artikel `bin/demo/` (Thema frei wählbar, z. B. „Energie in Zahlen“, sachlich, mit erfundenen aber plausiblen Zahlen deutlich als Beispiel gekennzeichnet) enthält **alle 17 Typen**, davon interaktiv nur: Linie, Heatmap, Sankey, Baumkarte, Karte (Blasen-Filter an einem zweiten Beispiel); `php bin/dispatch demo` legt ihn an, `bin/demo/pruefe-inhalt.php` ohne Befund, Formprüfung ohne `component_invalid`.
6. Doku: ADR-030 (`docs/decisions/030-diagramme-als-server-svg.md`: Kontext / Optionen / Entscheidung / Konsequenzen — Tabelle als Datenquelle statt SVG/JS durch Agenten; Server-SVG statt Diagrammbibliothek im Browser (CSP, Ladegröße, Funktion ohne Skript); Interaktion nur auf vorgerechnetem Markup; nicht jedes Diagramm interaktiv; Karten aus Natural Earth; Größengrenzen); `frontend.md` (Budgets, zwei Dateien, Ladebedingung), `code-map.md` (Zeile „Diagramme“), `docs/glossary.md` (Begriffe Diagramm, Datentabelle), `docs/PROJECT.md` (Kartenquelle), README-Status.

## Checkliste

- [ ] `app/theme/diagramme.js` nach AK 1 (kleine gemeinsame Helfer `tooltip()`, `toggle()`, `markieren()`; Entwurfs-Skript als Vorlage für Verhalten).
- [ ] `ContentRenderer`: `diagramme_interaktiv`; `data-interaktiv` nur durchreichen, wenn Typ in `INTERACTIVE_TYPES`; Knöpfe der Legende/Chips als `<button type="button">` im Renderer-Markup (Ausgabe wird nicht erneut bereinigt).
- [ ] `Assets::THEME_FILES`, `layout.php`, `Builder::collectArticles()`, `artikel.js::loadComponents()` erweitern (Muster aus Phase 4).
- [ ] Demo-Artikel und `demo.php`/`pruefe-inhalt.php` anpassen.
- [ ] ADR-030, `frontend.md`, `code-map.md`, `glossary.md`, `PROJECT.md`.
- [ ] Größen messen, `php -l`, Site bauen (`php bin/dispatch build`), Demo im Browser: Tastatur, Touch (390 px), hell/dunkel, ohne JavaScript, Druck.
- [ ] README: Status, Summary, Files touched füllen.

## Report-Back
