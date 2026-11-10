# Findings — Meilenstein 2

Format: `- [ ] → Phase N: <Erkenntnis>` (offen) · `- [x] → Phase N: <Erkenntnis> — erledigt in <Commit/Datei>`
- [ ] → Phase 6: Der Fokus-Knopf `#fokus-an` im Artikelkopf trägt `hidden` (ohne JavaScript tut er nichts); `artikel.js` muss es entfernen. Ebenso erwartet `artikel.js` die Ids `seitenspalte`, `iv-mobil`, `iv-jetzt` und `quellen` aus `article.php`.
- [ ] → Phase 6: `search.php` liefert `#filterleiste` (leer, `.wrap` darin), `#suche-raster` und `#suche-seiten` als Füllstellen für `suche.js`; Karten dort bitte mit demselben Markup wie `partials/card.php`.
- [ ] → Phase 7: `Builder::page()` bekommt für Artikel schon `ogType`/`ogImage` in `$meta`; `jsonLd` (Artikel, Brotkrumen aus `partials/breadcrumbs.php`-Daten) und die Open-Graph-Tags fehlen noch im `layout.php`.
- [ ] → Phase 7: Lokale Datenbank enthält ein Testrelikt (Artikel `durchstich-90e257`, Kategorie `test-90e257`) aus früheren Phasen; Demo-Zählungen (14 Artikel) weichen lokal um eins ab.
