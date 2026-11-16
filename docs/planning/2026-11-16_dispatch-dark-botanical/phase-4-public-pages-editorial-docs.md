# Phase 4 — Öffentliche Restseiten und Redaktionsmodus

## Kontext (lesen)

- `app/templates/{article,category,categories,tag,search,document,404,preview,consent}.php` und `app/templates/partials/{breadcrumbs,pagination,card}.php` — bestehende Informationsarchitektur und semantisches Markup.
- `app/theme/style.css` — Artikel, Kategorien, Filter, Seitenzahlen, Dokumente, Druck und responsive Regeln.
- `app/theme/redaktion/redaktion.css` und alle Klassen/IDs daraus; `app/theme/redaktion/*.js` erzeugen diese Elemente zur Laufzeit.
- `docs/conventions/frontend.md`, `docs/conventions/linting.md`, `docs/conventions/dod.md`, ADR-004, ADR-015, ADR-023, ADR-035.

## AK

1. Artikeltypografie, Bilder, Quellen, Inhaltsverzeichnis, Kategorien, Tags, Suche, Pagination, Dokumente und 404 verwenden dieselben dunklen Tokens und behalten ihre jetzigen Strukturen, URLs und ohne-JavaScript-Fallbacks.
2. Der Redaktionsmodus ist nach Anmeldung auf dunklem Grund vollständig lesbar: Popover, Dialoge, Formfelder, Tabellen, Status-/Gefahrenfarben, Tooltips, Planungs- und Anweisungsdialoge sind kontrastreich und per Tastatur fokussierbar.
3. Vorschau- und OAuth-Zustimmungsseite laden die gleichen lokalen Tokens und sind nicht als helle Altoberfläche sichtbar. Keine CSP-Ausnahme, kein Inline-Skript und keine neue Fremdabhängigkeit entsteht.
4. Code-Map, Designquelle und ADR entsprechen dem realen Asset- und Templatepfad; der alte Designentwurf bleibt nur historischer Kontext, nicht umsetzungsverbindlich.

## Checkliste

- [ ] `app/theme/style.css`: Restseitenregel für Regel auf alte Papier-/Schatten-/Rot-Annahmen prüfen und in das Dark-Editorial-System überführen; Artikelbreiten, Diagramm-/Baustein-Erweiterungen und Druckregeln nicht zurückbauen.
- [ ] `app/theme/redaktion/redaktion.css`: alle direkten Farben und Tokenannahmen gegen die neue Schale prüfen; neutralen Zustand, Erfolg, Warnung und Gefahr als semantische, AA-kontrastreiche Farben belassen.
- [ ] `app/templates/{consent,preview}.php` und `app/theme/redaktion/zustimmung.js`: prüfen, dass bereits geladene Redaktionsstyles mit den neuen Tokens funktionieren; nur nötiges Markup ergänzen.
- [ ] `docs/code-map.md`, `docs/conventions/frontend.md`, `docs/design/dispatch-dark-botanical.md`, ADR-035 abschließend gegen die realen Dateien abgleichen.
- [ ] Vor Abschluss: `php bin/dispatch build` mit vorhandener lokaler Datenbank ausführen; alle geänderten PHP-Dateien mit `php -l` prüfen; CSS-/JS-Größen messen.
- [ ] Die erzeugte Website im Browser auf Desktop, Tablet und Mobil prüfen: Startseite, Artikel, Kategorien, Suche, Dokumente, 404, Druckansicht, ohne JavaScript, Login/Redaktionsdialoge und Footer-Motiv. Den konkreten Befund gemäß `docs/conventions/dod.md` dokumentieren.

## Report-Back
