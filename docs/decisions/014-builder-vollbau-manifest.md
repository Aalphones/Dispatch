# 014 — Builder: Vollbau, Sperre, Manifest, Markdown

**Status:** Akzeptiert (2026-11-10)

## Kontext

Die Website besteht nur aus statischen Dateien in der Wurzel, die DISPATCH mit CardMaker und CamScanner teilt (ADR-001). Der Builder muss alles Eigene erzeugen und Veraltetes löschen, ohne je eine fremde Datei anzufassen, und er darf nicht doppelt laufen. Die Dokumente (Über, Impressum, Datenschutz) liegen als Markdown vor; Composer-Bibliotheken sind ausgeschlossen.

## Entscheidung

- **Vollbau bei jedem Lauf:** alle Seiten aus `PostRepository::livePosts()` (ADR-012), Kategorien, Einstellungen und Dokumenten. Erst ab mehreren tausend Artikeln lohnt ein Teilbau.
- **Sperre:** `GET_LOCK('dispatch_build', 30)`; wer nach 30 Sekunden nicht dran ist, bekommt `build_busy`. Freigabe im `finally`.
- **Erst sammeln, dann schreiben:** Alle Dateien entstehen zuerst im Speicher (Pfad → Inhalt). Danach: Manifest = alt ∪ neu schreiben, Dateien schreiben (nur geänderte, atomar per temporärer Datei und `rename`), Veraltetes löschen, Manifest = neu schreiben. Bricht ein Lauf ab, stehen neue Dateien schon im Manifest und werden beim nächsten Lauf aufgeräumt.
- **Doppelte Schreibsperre:** Jeder Pfad muss die reservierten Namen meiden (ADR-001) **und** mit einem Namen aus `builder_top_level` in `config.php` beginnen. Gelöscht wird nur, was im Manifest stand und beide Prüfungen besteht; leere Ordner nur unterhalb dieser Namen.
- **Medien:** Nur Medien, die ein Live-Artikel nutzt, werden nach `media/<uid>/` kopiert; bei Bildern nur die Varianten, nie `original.*`.
- **Auslöser:** CLI `php bin/dispatch build` und der Deploy-Endpunkt. Der Neubau nach Freigabe oder Zurückziehen hängt an der Stelle, die diese Aktionen aufruft (MCP-Werkzeug `rebuild` in Meilenstein 3, Redaktions-API in Meilenstein 4).
- **Markdown:** eigener kleiner Umsetzer `Util\Markdown` (Überschriften `##`–`####`, Absätze, `**fett**`, `*kursiv*`, Links, Listen, Zitate, Trennlinie). Sein HTML läuft danach durch den `Sanitizer`.

## Verworfen

- **Teilbau pro Artikel:** Listen, Suche, Feed und Sitemap hängen an jedem Artikel; der Gewinn trägt den Aufwand nicht.
- **Löschen per Abgleich mit dem Dateisystem:** würde Nachbar-Dateien treffen.
- **Markdown-Bibliothek:** Composer ist ausgeschlossen.

## Konsequenzen

- Ein Lauf dauert so lange wie das Rendern aller Seiten; bei wenigen hundert Artikeln Sekunden, Strato erlaubt 240.
- Wer `builder_top_level` erweitert, muss prüfen, dass kein Nachbar den Namen nutzt.
