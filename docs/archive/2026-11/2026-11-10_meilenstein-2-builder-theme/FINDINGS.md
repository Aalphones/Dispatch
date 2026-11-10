# Findings — Meilenstein 2

Format: `- [ ] → Phase N: <Erkenntnis>` (offen) · `- [x] → Phase N: <Erkenntnis> — erledigt in <Commit/Datei>`
- [x] → Phase 6: Der Fokus-Knopf `#fokus-an` im Artikelkopf trägt `hidden` (ohne JavaScript tut er nichts); `artikel.js` muss es entfernen. Ebenso erwartet `artikel.js` die Ids `seitenspalte`, `iv-mobil`, `iv-jetzt` und `quellen` aus `article.php`.
- [x] → Phase 6: `search.php` liefert `#filterleiste` (leer, `.wrap` darin), `#suche-raster` und `#suche-seiten` als Füllstellen für `suche.js`; Karten dort bitte mit demselben Markup wie `partials/card.php`.
- [x] → Phase 7: `Builder::page()` bekommt für Artikel schon `ogType`/`ogImage` in `$meta`; `jsonLd` (Artikel, Brotkrumen aus `partials/breadcrumbs.php`-Daten) und die Open-Graph-Tags fehlen noch im `layout.php`.
- [x] → Phase 7: Lokale Datenbank enthält ein Testrelikt (Artikel `durchstich-90e257`, Kategorie `test-90e257`) aus früheren Phasen; Demo-Zählungen (14 Artikel) weichen lokal um eins ab.
- [x] → Phase 7: `suche.js` ist wegen des Budgets (9 000 Byte) bewusst gekürzt: kein Chip zum Entfernen des Suchworts, keine Lesezeit in der Karte, keine Ergebniszahl, keine „Alle Filter entfernen“-Schaltfläche, keine Auslassungspunkte im Pager, keine Kategorie-Zeile unter „Hinzufügen“. Theme-JS ist mit Tabs eingerückt (spart Bytes). Browser-Sicht (Vorschläge, Pfeiltasten, Fokusmodus, Lightbox, Video) nur per Headless-Chrome-DOM geprüft, nicht interaktiv.
- [x] → Phase 7: `layout.php` trägt jetzt `button#suche-zu` (Abbrechen) im Suchformular; `style.css` kennt `.suche-zu` bereits.
