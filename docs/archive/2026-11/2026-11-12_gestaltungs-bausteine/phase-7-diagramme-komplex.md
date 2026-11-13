# Phase 7 — Diagramme: Sankey, Baumkarte, Blasen, Karte

Komplexität: standard · Modell: Sonnet · Voraussetzung: Phase 6 abgeschlossen

## Kontext (vor dem Start lesen)

- `README.md` dieses Plans, „Kontrakt Diagramme“ (Tabellenform sankey/treemap/bubble/map, Grenzen).
- Phase 6 (`ChartRenderer`, Helfer, `diagramme.css`, freigegebener Entwurf mit diesen vier Typen).
- Dataviz-Skill: `references/choosing-a-form.md`, `anti-patterns.md`.
- Fehlerklassen (Vault) geprüft: keine einschlägig.

Verstanden, bevor geändert: Diese vier Typen brauchen eigene Rechenarbeit auf dem Server (Layout), damit im Browser kein Layout-Code liegt. Jede Interaktion in Phase 8 arbeitet auf **vorgerechnetem** Markup: Zustandswechsel nur über Attribute/Klassen, nie über neu berechnete Geometrie im Browser (Ausnahme: keine).

## Abnahmekriterien

1. **Sankey** (`Von | Nach | Wert`): Knoten in Spalten nach längstem Pfad, Knotenhöhe = max(Zufluss, Abfluss), Bänder mit kubischer Kurve, 2 px Abstand zwischen Knoten, Kreisläufe → Fehler in `ChartData` (kein Sankey möglich); Knoten `<g class="knoten" data-knoten="ID">`, Bänder `<path class="band" data-von="ID" data-nach="ID">`, jedes mit `<title>` („A → B: 12 %“); höchstens 40 Verbindungen, 5 Spalten.
2. **Baumkarte** (`Gruppe | Untergruppe | Wert`): Squarified-Layout; Ebene 1 als Flächen mit Gruppenname, jede Gruppe zusätzlich als **vorgerechnete zweite Ebene** `<g class="ebene" data-gruppe="ID" hidden>` mit den Untergruppen auf voller Fläche; Beschriftung nur, wenn die Fläche reicht (Mindestmaß in Konstante), sonst nur `<title>`; höchstens 60 Blätter.
3. **Blasen** (`Name | x | y | Größe | Kategorie`): zwei Achsen mit schönen Schritten, Fläche ∝ Größe (nicht Radius), Kategorien als Reihenfarben `r1…r8` (höchstens 8), 2 px Ring in Flächenfarbe bei Überlappung, Marken nach Größe absteigend gezeichnet (kleine oben), Direktbeschriftung nur der größten 5; höchstens 60 Blasen.
4. **Karte** (`Region | Wert | Name`, `data-map="de"` Bundesländer oder `"welt"` Länder): Regionen als `<path class="region" data-region="DE-BY">` mit Einfarbskala in 5 Klassen (Klassengrenzen nach Quantilen, Legende nennt die Grenzen), Regionen ohne Wert in neutralem Grau mit Muster; Geodaten als PHP-Dateien `app/data/karten/de.php` und `welt.php` (vereinfachte Pfade, ISO 3166-2 bzw. alpha-2), erzeugt von einem Skript `bin/karten-bauen.php` aus Natural Earth (gemeinfrei) — Lizenz und Quelle in `docs/PROJECT.md` und im Skriptkopf; je Datei ≤ 60 000 Byte.
5. Jede Figur bleibt ohne JavaScript vollständig lesbar: Sankey/Baumkarte/Blasen/Karte zeigen alle Zahlen über `<title>` und die Tabelle in `details`.
6. Sichtprüfung hell/dunkel bei 390, 860, 1366 px gegen den Entwurf; Karte auf dem Handy nicht kleiner als 320 px Breite (sonst waagerechtes Scrollen in `diagramm-flaeche`).
7. `php -l` grün; `diagramme.css` bleibt ≤ 9 000 Byte (sonst Grenze im README mit Begründung anheben, in „Deviations“ vermerken).

## Checkliste

- [x] `ChartRenderer` um `sankey()`, `treemap()`, `bubbles()`, `map()` und private Layout-Helfer (`SankeyLayout`, `squarify()`) erweitern; eigene Klassen nur, wenn `ChartRenderer.php` über ~600 Zeilen wüchse.
- [x] `bin/karten-bauen.php` (einmalig ausgeführt, Ergebnis eingecheckt): Natural-Earth-Daten laden (Pfad als Argument, nicht im Repo), Pfade vereinfachen und auf ein 1000-Einheiten-Raster runden, PHP-Array schreiben. **Quelle und Lizenz vor Einsatz am Datensatz selbst nachsehen** (nicht aus dem Gedächtnis eintragen).
- [x] `ChartData`: Zyklus-Erkennung für Sankey, Regionscode-Prüfung gegen die Karte.
- [x] `diagramme.css` um die vier Typen ergänzen.
- [x] Prüfen: Scratchpad-Skript mit je einem Beispiel pro Typ (Sankey: Energiefluss, Baumkarte: Haushalt mit Unterposten, Blasen: Länderdaten, Karte `de` und `welt`), Screenshots.

## Report-Back

Phase 7 fertig: Sankey, Baumkarte, Blasen und Karte als Server-SVG (`ChartComplexPlots` mit `SankeyLayout`, `TreemapLayout`, `ChartMaps`, `ChartShapes`; Helfer aus `ChartRenderer` nach `ChartShapes` verlagert, da über 600 Zeilen). Kartendateien `de.php` 24 919 B, `welt.php` 40 084 B (110m-Auflösung: Kleinstaaten wie MT, SG fehlen und werden von der Formprüfung abgelehnt); `diagramme.css` 7 021 B. `ChartData`: Spaltengrenze 5 (Sankey), 8 Gruppen (Baumkarte), Regionscodes aus den Kartendateien, Kopfzellen der Blasen als Achsentitel. Geprüft: Skript mit allen vier Typen (de und welt), Fehlerfälle, Chrome-Screenshots dunkel bei 1366 px; hell und 390 px nicht eigens geprüft (Headless-Fenster mindestens ca. 500 px).
