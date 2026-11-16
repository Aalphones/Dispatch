# Phase 3 — Startseite mit redaktioneller Hierarchie

## Kontext (lesen)

- `app/src/Site/Builder.php::collectHome()` und `app/templates/home.php` — derzeit Featured-Post plus Kartenraster.
- `app/templates/partials/card.php` — wiederverwendbare Artikelkarte; Bild, Dachzeile, Titel, Teaser und Metadaten bleiben vollständig erhalten.
- `app/templates/layout.php` — liefert die neue semantische Startseiten-`h1` und globale Schale.
- `app/theme/style.css` — Klassen `.aufmacher`, `.raster`, `.karte`, `.chips`, `.meta`, Breakpoints.
- `docs/design/dispatch-dark-botanical.md` und `docs/conventions/frontend.md`.

## AK

1. Erste Seite: nach der Navigation 24–32 px Abstand, dann ein Layout mit Hauptspalte ungefähr zwei Drittel und Sidebar ungefähr ein Drittel bei 24–32 px Gap. Es gibt keinen Einleitungstext und keinen Banner.
2. Der Featured-Artikel nutzt den existierenden Post samt Cover, Kategorie, Titel, Teaser und Metadaten: Bild links, Text rechts, Teal-Kategorie und eindeutig verlinkter Teal-Pfeil. Ohne Cover bleibt die Textkarte vollständig und das Raster stabil.
3. Die Sidebar zeigt den vorhandenen Seitentext über `aboutText`, einen Link zu `/ueber/`, ein kleines `logoIcon` und die vorhandenen häufigsten Tags als Outline-Chips. Sie existiert nur auf Seite 1; ältere Startseiten bleiben reine Archivlisten.
4. Das nachfolgende Grid hat drei Spalten ab 62rem, zwei zwischen 46rem und 62rem und eine Spalte darunter; Kartenbilder und Text dürfen nicht horizontal überlaufen. Unter 46rem liegt die Sidebar nach Artikeln und vor Footer-Motiv.

## Checkliste

- [ ] `app/templates/home.php`: `$aboutText` und `$topTags` dokumentieren, den bisherigen Aufmacher/Raster in `.home-layout`, `.home-main`, `.home-sidebar` strukturieren und ohne Datenverlust rendern.
- [ ] `app/templates/home.php`: Sidebar-Sektionen als echte Überschriften und Listen ausgeben; Tag-URLs und Zähler weiter escapen; kein JavaScript für Inhalt/Reihenfolge einsetzen.
- [ ] `app/templates/home.php`: den vorhandenen Featured-Link um einen sichtbaren, per CSS dekorierten Text-/Pfeilhinweis ergänzen, ohne verschachtelte Links zu erzeugen.
- [ ] `app/templates/partials/card.php`: nur ergänzen, falls ein benannter Karten-Hook für das neue Raster zwingend fehlt; keine parallele Card-Komponente anlegen.
- [ ] `app/theme/style.css`: `.home-layout`, `.home-sidebar`, Featured- und Card-Varianten für Desktop/Tablet/Mobil definieren; Bilder behalten `<picture>`, intrinsische Maße und Lazy-Loading aus dem bestehenden Helfer.

## Report-Back
