# Phase 6 — Skripte und Suchindex

**Rating:** heikel (Suche mit Normalisierung und Gewichtung; DOM-Ausgabe aus Daten ohne XSS) · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" (Skriptmodule, Suchindex-Format, `data-suchindex`, `data-suche`)
- ADR-015 (Module, Budgets), ADR-013 (additive Filter über `/suche/?tag=…`)
- Entwurf `docs/design/entwurf-website.html` → `<script>`-Block: zeigt das **Verhalten** (Inline-Suche mit Abschnitten Kategorien / Schlagwörter / Artikel, Pfeiltasten, Esc, Strg/⌘+K und `/`, Vollbild-Suche auf dem Handy, Filter hinzufügen/entfernen, Marker im Inhaltsverzeichnis, Fokusmodus, Ausblenden der Seitenspalte neben `full`-Bildern). Der Entwurfs-Code ist **nicht** zu kopieren (Demo-Daten, `innerHTML` mit Daten); Teile ohne Entsprechung: `zeige`, Login, Reaktionen, Kommentare, Gewicht, Geräte-Vorschau, Toast.
- Konzept → „Kategorien, Filter und Suche" (Normalisierung, Gewichtung, Textausschnitt mit Hervorhebung)
- `docs/conventions/frontend.md` (kein Inline-Skript, ohne JS alles außer Suche/Videos nutzbar)
- Fehlerklassen geprüft: keine einschlägig

## Entscheidungen (fest)

- **Ausgabe ins DOM nur per `textContent`, `createElement`, `setAttribute`** — nie `innerHTML` mit Daten aus dem Index. Hervorhebung: Text in drei Knoten zerlegen (vor, `<mark>`, nach).
- **Normalisierung** (`norm`): Kleinschreibung, `ä→a, ö→o, ü→u, ß→ss` (für Groß-/Kleinschreibung beider Seiten gleich), übrige Akzente per `normalize('NFD')` entfernen, alles außer Buchstaben/Ziffern → Leerzeichen. **Stamm** (`stamm`) für Wörter ≥ 5 Zeichen: Endungen in dieser Reihenfolge einmal abschneiden: `ungen, ung, ern, en, er, es, e, n, s`.
- **Treffer und Rang:** Anfrage in Wörter teilen, jedes Wort stammen; ein Artikel trifft, wenn **jedes** Anfragewort als Präfix eines Wortes in Titel, Unterzeile, Teaser, Überschriften oder Text vorkommt. Punkte je Anfragewort: Titel 10, Überschriften 5, Unterzeile/Teaser 3, Text 1 (höchster Wert je Wort zählt). Sortierung: Punkte, dann Datum neu → alt. Sortier-Chips „Relevanz / Neueste / Älteste".
- **Textausschnitt:** 140 Zeichen um den ersten Treffer im Text (an Wortgrenzen), Treffer hervorgehoben; ohne Text-Treffer der Teaser.
- **Vorschläge im Kopf:** höchstens 4 Kategorien, 6 Schlagwörter, 4 Artikel; bei leerem Feld „Zuletzt erschienen" (4 neueste) und die 6 häufigsten Schlagwörter. Enter ohne Auswahl → `/suche/?q=…`.
- **Suchseite `/suche/`:** liest `q`, `tag` (mehrfach), `kategorie`, `sort` aus der URL; schreibt sie per `history.replaceState` zurück (teilbar, Zurück-Taste bleibt nutzbar über `pushState` bei jeder Filteränderung). Kategorie-Filter schließt Unterkategorien ein. Ergebnis als Karten (gleiches Markup wie `partials/card.php`, Bild = `b`), 12 je Seite mit `.seiten`.
- **Suchbegriffe ohne Treffer** werden in Meilenstein 4 gemeldet (Statistik) — jetzt nicht.

## AK der Phase

1. Byte-Größen: `site.js` ≤ 5 000, `suche.js` ≤ 9 000, `artikel.js` ≤ 7 000.
2. Suche findet ein Wort, das nur im Fließtext eines Demo-Artikels steht; „Messungen" findet Artikel mit „Messung"; „gemessen" findet sie nicht (Grenze des einfachen Stamms, so gewollt); „uber" findet „Über".
3. `/suche/?tag=ozean&tag=messdaten` zeigt nur Artikel mit beiden Schlagwörtern; Hinzufügen/Entfernen ändert URL und Liste; Zurück-Taste stellt den vorigen Filter wieder her.
4. Strg/⌘+K und `/` fokussieren die Suche (nicht, wenn gerade in ein Feld getippt wird); Pfeiltasten wählen, Enter öffnet, Esc schließt; auf ≤ 46rem wird die Suche zur Vollbild-Ebene mit „Abbrechen".
5. Ein Index-Eintrag mit `<img src=x onerror=alert(1)>` im Titel (von Hand in eine kopierte Index-Datei geschrieben, Datei danach löschen) erscheint als Text, nichts wird ausgeführt.
6. Artikel: Marker im Inhaltsverzeichnis folgt dem gelesenen Abschnitt; `.iv-mobil` zeigt den aktuellen Abschnittsnamen; Fokusmodus per Knopf und Taste F, Esc und Knopf „Fokus beenden" schließen ihn, Vollbild wird mit verlassen; Klick auf ein `.abb`-Bild öffnet die Lightbox mit der größten Variante (Esc, Klick daneben schließt); `.einbettung-start` ersetzt sich durch `iframe` (`https://www.youtube-nocookie.com/embed/{id}?autoplay=1` bzw. `https://player.vimeo.com/video/{id}?autoplay=1`, `allow="autoplay; fullscreen; picture-in-picture"`, `title` aus der Bildunterschrift); neben `full`-Bildern blendet sich die Seitenspalte aus.
7. Keine Konsolenfehler auf allen Seitentypen, auch nicht ohne `IntersectionObserver`/`startViewTransition` (Funktion ist dann einfach aus).

## Checkliste

- [x] `Site/SearchIndex.php`: `build(SiteData $data, array $rendered): string` (JSON nach Kontrakt; `$rendered` = Ergebnisse des `ContentRenderer` je Post-ID für `h` und `c`); `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`. Builder: Index in `collect()` nach den Artikeln erzeugen, als `assets/suchindex.{h}.json` ablegen, URL in `$assets['suchindex']` und `layout.php` (`data-suchindex`).
- [x] `theme/site.js`: Tastenkürzel; beim ersten `focus` auf `#suchfeld` (oder Kürzel) `import(document.documentElement.dataset.suche)` und `start(feld)` des Moduls aufrufen; `ResizeObserver` auf `.nav` → `--nav-h`; Plattform-Text der `.taste` (`⌘ K` auf Apple, sonst `Strg K`).
- [x] `theme/suche.js` (exportiert `start(feld)` und `seite()`): Index einmal per `fetch` laden und vorbereiten (normalisierte Felder je Artikel cachen); Vorschau-Panel; auf `/suche/` (`document.body.dataset.seite === 'suche'`) `seite()` ausführen. `site.js` ruft auf der Suchseite `seite()` sofort.
- [x] `theme/artikel.js`: Inhaltsverzeichnis (IntersectionObserver auf `.text h2[id]`, `rootMargin: '-15% 0px -70% 0px'`, Marker per `transform`), Fokusmodus (`body.fokus`, `requestFullscreen` mit `.catch`, Esc, `fullscreenchange`, Maus-Sichtbarkeit wie Entwurf), Lightbox (ein `<dialog class="lightbox">` per Skript anlegen; größte Variante aus dem `srcset` der `source[type="image/webp"]`), Einbettungen, Seitenspalte neben `full`-Bildern.
- [x] Doc: `docs/code-map.md` → `theme/`-Zeile mit den drei Modulen und `Site/SearchIndex`.

## Report-Back
