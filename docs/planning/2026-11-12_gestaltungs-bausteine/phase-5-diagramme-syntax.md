# Phase 5 — Diagramme: Agenten-Syntax, Datenlesen, Formprüfung

Komplexität: standard · Modell: Sonnet · Voraussetzung: Phase 1 abgeschlossen (gleiche Dateien, gleiche Bauart)

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans, Abschnitt „Kontrakt Diagramme“ (Syntax, Tabellenform je Typ, Grenzen).
- Phase 1 dieses Plans und der dort erweiterte `app/src/Util/Sanitizer.php` (`keptAttributeValue()`, `oneOf()`, `ALLOWED_CLASSES`) und `app/src/Util/FormCheck.php` (`checkComponents()`).
- `app/mcp/style-guide.md` (Abschnitte „Klassen für Gestaltungsmittel“, „Warnungen“).
- Dataviz-Skill, `references/choosing-a-form.md` (welcher Typ für welche Aussage) — Grundlage für den Satz „Wofür“ je Typ im Style-Guide.
- Fehlerklassen (Vault) geprüft: keine einschlägig.

Verstanden, bevor geändert: Der Sanitizer wirft `svg` heraus (`DROPPED_ELEMENTS`) — Agenten schreiben nie SVG, nur eine Tabelle; das Diagramm zeichnet der Server (Phase 6/7). Die Tabelle ist zugleich Datenquelle, Fallback ohne JavaScript und Zahlenbeleg für Lesende. `keptAttributeValue()` liefert bei falschem Wert `null`, nie den Rohwert.

## Abnahmekriterien

1. `<figure class="chart" data-type="…">` mit `<table>` und optionalem `<figcaption>` übersteht `Sanitizer::sanitize()` unverändert; erlaubte Typen: `bar`, `column`, `line`, `area`, `stacked-bar`, `stacked-column`, `pie`, `donut`, `radar`, `waffle`, `waterfall`, `heatmap`, `calendar`, `sankey`, `treemap`, `bubble`, `map`.
2. Entfernt mit Hinweis in `sanitizer_notes`: `data-type="3d-pie"`, `data-unit` mit `<`, `"` oder `|` oder über 12 Zeichen, `data-map="mars"`, `data-interactive="yes"`, `data-sum="maybe"`.
3. Neue Klasse `ChartData` (`app/src/Site/ChartData.php`) liest die Tabelle einer `figure.chart` in eine Struktur je Typ (Spalten- und Zeilenlayout laut README-Kontrakt) und liefert bei Formfehlern eine Liste Fehlertexte statt einer Ausnahme. Zahlen: `7.8` und `7,8` gelten beide, Tausendertrenner und Einheiten in Zellen sind Fehler (Einheit gehört in `data-unit`).
4. `FormCheck` meldet `component_invalid` (Feld `content_html`) mit Satz, der Typ und Fehler nennt: Typ unbekannt oder Tabelle fehlt; Tabellenform passt nicht zum Typ (z. B. `sankey` ohne drei Spalten); Zelle nicht numerisch; negativer Wert bei `pie`, `donut`, `waffle`, `treemap`, `sankey`, `bubble` (Größe); mehr als 6 Teile bei `pie`/`donut` (Hinweis: Rest zu „Sonstige“ zusammenfassen); Größengrenzen aus dem README überschritten; `data-interactive` an einem Typ ohne Interaktion; `map` ohne `data-map` oder mit unbekanntem Regionscode.
5. `style-guide.md` beschreibt jeden Typ mit Tabellenbeispiel und einem Satz „wofür“ (Wann statt was; „ein sauberer Balken genügt“ als ausdrückliche Regel: Interaktion nur, wo sie etwas erschließt), nennt die Warnung `component_invalid` für Diagramme und die Lesepflicht „Quelle und Stand in `figcaption`“.
6. `php -l` grün für alle geänderten PHP-Dateien.

## Checkliste

- [ ] `Sanitizer::ALLOWED_CLASSES` um `chart` ergänzen. Konstanten `CHART_TYPES` (Liste aus AK 1), `CHART_MAPS = ['de', 'welt']`, `UNIT_PATTERN = '/^[^<>"|]{1,12}\z/u'`.
- [ ] `keptAttributeValue()`: `'figure data-type'` (nur mit Klasse `chart`, `oneOf(CHART_TYPES)`), `'figure data-unit'` (nur `chart`, `UNIT_PATTERN`), `'figure data-map'` (nur `chart`, `oneOf(CHART_MAPS)`), `'figure data-interactive'` (nur `chart`, `oneOf(['true'])`), `'tr data-sum'` (`oneOf(['true'])`).
- [ ] `app/src/Site/ChartData.php`: `final class ChartData`, statische Methode `fromFigure(\Dom\Element $figure): array{type: string, data: array, errors: list<string>}`; je Typ eine private Methode (`categorySeries()` für bar/column/line/area/stacked/radar/pie/donut/waffle, `waterfall()`, `matrix()` für heatmap, `calendar()`, `links()` für sankey, `hierarchy()` für treemap, `points()` für bubble, `regions()` für map); Zahlenlesen in `number(string $cell): ?float`. Keine Ausgabe, keine Zeichnung.
- [ ] `FormCheck::checkComponents()` aus Phase 1 um Diagramme erweitern (nutzt `ChartData`, je Fehlertext eine Warnung; Interaktions-Typen als Konstante `INTERACTIVE_TYPES = ['line', 'heatmap', 'calendar', 'sankey', 'treemap', 'bubble', 'map']`).
- [ ] `app/mcp/style-guide.md`: Klassenliste, Abschnitt „Diagramme“ (Auswahlhilfe in einer Tabelle „Aussage → Typ“, Beispiel je Typ, Interaktionsregel, Zahlenformat, Quelle in `figcaption`), Warnung in die Liste.
- [ ] Prüfen: Scratchpad-Skript ruft `Sanitizer::sanitize()` und `ChartData::fromFigure()` mit je einem gültigen und einem fehlerhaften Beispiel pro Typ; Ausgabe gegen AK 1–4 abgleichen.
- [ ] `php -l` für `Sanitizer.php`, `FormCheck.php`, `ChartData.php`.

## Report-Back
