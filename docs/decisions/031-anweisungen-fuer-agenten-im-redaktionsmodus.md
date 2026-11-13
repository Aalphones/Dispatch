# 031 — Anweisungen für Agenten im Redaktionsmodus

**Status:** Akzeptiert (2026-11-13) · ergänzt ADR-014 und ADR-016

## Kontext

Der Inhaber will alles einsehen können, was ein Agent von DISPATCH an Anweisungen bekommt: Redaktionsrichtlinien, Präferenzen, Server-Anweisung, Prompts, Workflows, Style-Guide und die Beschreibungen aller Werkzeuge samt ihrer Angaben. Bisher ging das nur über MCP (`get_document`, `get_style_guide`) oder den Code. Richtlinien und Präferenzen soll er an derselben Stelle auch bearbeiten können.

## Entscheidung

- **Änderbar sind nur die beiden internen Dokumente** `editorial_guidelines` und `preferences`. Server-Anweisung, Prompts, Workflows, Style-Guide und Werkzeugbeschreibungen bleiben im Code und sind im Redaktionsmodus nur lesbar; die Formvorgaben stehen in der Konfiguration (`form_rules`).
- **Speichern mit Konfliktprüfung:** Speichern legt wie bisher eine neue Fassung an (`DocumentService::update`, `document_revisions`, Audit). Der Browser schickt die Fassung mit, auf der er aufgebaut hat; weicht sie von der aktuellen ab, wirft der Service `revision_conflict` mit Fassung, Akteur, Zeit und Notiz der neueren Fassung. Die Prüfung läuft auf der gesperrten Zeile (`SELECT … FOR UPDATE`) in derselben Transaktion wie das Schreiben. Der Inhaber kann „trotzdem“ speichern (ohne Basis). Agenten (MCP `update_document`) und übernommene Vorschläge schicken keine Basis.
- **Wiederherstellen** einer älteren Fassung legt eine neue Fassung mit deren Inhalt an (Notiz „Fassung N wiederhergestellt.“); nichts wird gelöscht. „Rückgängig“ ist dasselbe Wiederherstellen der Fassung davor.
- **Der Server rendert Markdown**, der Browser nicht: `Util\Markdown` versteht zusätzlich Inline-Code, Code-Blöcke zwischen ```-Zeilen und Tabellen aus `|`-Zeilen mit Trennzeile. Die Vorschau holt das HTML vom Server; der Vergleich zweier Fassungen ist `Util\Diff::html` auf das gerenderte HTML beider. Ein `|` in einer Zelle trennt immer, ein Escape gibt es nicht.
- **Werkzeuge kommen aus der Registry:** `McpController::registry()` ist öffentlich, die Registry merkt sich je Werkzeug einen Bereich (`Registry::group()` vor den `register`-Aufrufen). Ein neues Werkzeug erscheint ohne weiteren Handgriff in der Ansicht.

## Verworfen

- **Markdown im Browser rendern:** ein zweiter Umsetzer, der vom Server abweicht.
- **Eigene Werkzeugliste im Frontend:** veraltet beim ersten neuen Werkzeug.
- **Server-Anweisung und Workflows editierbar machen:** Die Texte sind Teil des Codes und laufen mit Deploy und Versionsverwaltung.
- **Protokoll der Werkzeugaufrufe:** vom Inhaber abgewählt.

## Konsequenzen

- Die öffentlichen Dokumentseiten (Über, Impressum, Datenschutz) verstehen jetzt ebenfalls Code und Tabellen; Markdown ohne Backticks und `|`-Zeilen rendert unverändert.
- Agenten speichern weiter ohne Konfliktprüfung und können damit eine Fassung des Inhabers überschreiben — sie bleibt im Verlauf und lässt sich wiederherstellen.
