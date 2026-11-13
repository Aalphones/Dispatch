# Phase 6 — Diagramme: statische Typen als SVG vom Server

Komplexität: standard · Modell: Sonnet · Voraussetzung: Phase 2 und 5 abgeschlossen

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans, Abschnitte „Kontrakt Diagramme“ und „Größengrenzen“.
- `app/src/Site/ChartData.php` (Phase 5), `app/src/Site/ContentRenderer.php` (`render()`, `select()`, Muster der `render…`-Methoden aus Phase 2).
- Dataviz-Skill: `references/choosing-a-form.md`, `color-formula.md`, `marks-and-anatomy.md`, `anti-patterns.md`, `scripts/validate_palette.js`.
- `docs/conventions/frontend.md` (Tokens, Breakpoints, Dunkelmodus, Druck, CSP: **kein `style`-Attribut**, Maße und Farben nur über SVG-Attribute und Klassen).
- Fehlerklassen (Vault) `frameworks/css-architecture.md` geprüft.

Verstanden, bevor geändert: Die Ausgabe des Renderers geht ohne weiteren Sanitizer-Lauf in die Seite — Beschriftungen nur über `textContent`/`setAttribute`. Der Server rechnet die Geometrie, das Diagramm ist ohne JavaScript vollständig; Hover-Hinweise entstehen ohne Skript über `<title>` je Marke. Farbe trägt nie allein die Identität (Legende, Direktbeschriftung, Muster für Kontrast/Druck).

## Typen dieser Phase (alle ohne Interaktion)

Balken (`bar`), Säulen (`column`), Linie (`line`, statisch; Interaktion Phase 8), Fläche (`area`), gestapelt (`stacked-bar`, `stacked-column`), Kreis/Ring (`pie`, `donut`), Netz (`radar`), Waffel (`waffle`), Wasserfall (`waterfall`), Heatmap (`heatmap`) und Kalender-Heatmap (`calendar`) — beide statisch; Hover/Werte-Anzeige in Phase 8.

## Ausgabe-Markup (verbindlich)

```
<figure class="diagramm" data-typ="balken" [data-interaktiv]>
  <div class="diagramm-flaeche"><svg role="img" aria-labelledby="dN-titel dN-beschreibung" viewBox="0 0 W H">
    <title id="dN-titel">…</title><desc id="dN-beschreibung">Ein Satz mit der Kernaussage der Zahlen</desc> …Marken…</svg></div>
  <ul class="diagramm-legende">…</ul>              <!-- ab 2 Reihen, sonst entfällt -->
  <details class="diagramm-tabelle"><summary>Zahlen als Tabelle</summary>TABELLE des Agenten</details>
  <figcaption>Text des Agenten</figcaption>
</figure>
```

`data-typ` deutsch: `balken`, `saeulen`, `linie`, `flaeche`, `balken-gestapelt`, `saeulen-gestapelt`, `kreis`, `ring`, `netz`, `waffel`, `wasserfall`, `heatmap`, `kalender`, `sankey`, `baumkarte`, `blasen`, `karte`. Reihenfarben als Klassen `r1`…`r8`, Wasserfall `plus`/`minus`/`summe`.

## Abnahmekriterien

1. Für jeden Typ dieser Phase erzeugt `render()` das Markup oben aus der Agenten-Tabelle; Tabelle bleibt unverändert in `details.diagramm-tabelle`, Figur ohne Daten (Fehler aus `ChartData`) entfällt mit `Log::error` wie in Phase 2.
2. Eine Zahlenachse (nie zwei), Null-Basislinie bei Balken/Säulen/Flächen, 2 px Abstand zwischen gestapelten Segmenten, abgerundete Datenenden (4 px) an der Basislinie; Achsenwerte über „schöne“ Schritte (1/2/5 × 10ⁿ), Zahlen mit Komma und `data-unit`.
3. Kreis/Ring höchstens 6 Teile, Beschriftung direkt am Teil mit Prozentwert; Waffel 10 × 10 mit gerundeten Anteilen, die zusammen 100 ergeben (Größte-Rest-Verfahren); Wasserfall: Anfangs-/Endsumme (`tr data-sum`) als `summe`, Anstieg/Rückgang mit Vorzeichen im Text („+12“, „−5“), nicht allein farbig; Radar höchstens 3 Reihen; Kalender-Heatmap ein Kalenderjahr, Wochen als Spalten, Montag oben.
4. `app/theme/diagramme.css` (Rohgröße ≤ 9 000 Byte) trägt die Palette als Eigenschaften, hell und dunkel getrennt gewählt: 8 kategoriale Farben in fester Reihenfolge, eine Einfarbskala (hell → dunkel) für Heatmap/Karte, ein Paar für Anstieg/Rückgang. **Validiert** mit `scripts/validate_palette.js` (hell und dunkel, Ergebnis in `FINDINGS.md`); Kontrast-WARN → Wert sichtbar beschriftet.
5. Sichtprüfung gegen den freigegebenen Entwurf (siehe Checkliste): alle Typen hell/dunkel bei 390, 860, 1366 px, ohne Beschriftungs-Überlagerung; Druckansicht schwarz-weiß lesbar (Muster oder Direktbeschriftung); Reduced Motion ohne Bewegung (diese Phase hat keine).
6. `render()` liefert `'diagramme' => bool`; Diagramme zählen nicht zu `bausteine`.
7. `php -l` grün.

## Checkliste

- [ ] **Entwurf zuerst** (`mode-design`): ein HTML-Entwurf als Artefakt unter `artifacts/entwurf-diagramme.html` (Ordner `_entwurf-diagramme/` für Skripte/Stile) mit allen Typen aus dieser und der nächsten Phase, Beispielzahlen erfunden; **Freigabe durch Sascha einholen, dann erst Renderer bauen** (wie bei den Bausteinen: Teil 1 wird Vorlage für `diagramme.css`).
- [ ] Neue Klasse `app/src/Site/ChartRenderer.php` (`final`, `render(array $data, string $type, string $unit, int $id): \Dom\Element` je Typ eine private Methode; gemeinsame Helfer `niceScale()`, `formatNumber()`, `legend()`, `table()`); `ContentRenderer::renderCharts()` ruft sie, Reihenfolge in `render()` vor `renderFigures()`.
- [ ] Zahlenformat und Einheit: `formatNumber(float $v, int $decimals)` mit Komma; `data-unit` als Suffix, nie in der Achse mehrfach wiederholt (Achsentitel).
- [ ] `app/theme/diagramme.css` nach Entwurf; Palette validieren und Ergebnis in `FINDINGS.md` festhalten.
- [ ] Prüfen: Scratchpad-Skript rendert je Typ ein Beispiel (auch einen fehlerhaften), schreibt HTML in eine Datei, die mit `diagramme.css` im Browser geöffnet wird; Screenshots hell/dunkel.
- [ ] Größe messen: `(Get-Item app/theme/diagramme.css).Length` ≤ 9 000; `php -l`.

## Report-Back
