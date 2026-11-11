# Phase 3 — Änderungsansicht gegenüber der Live-Fassung

## Kontext

- `README.md` → „Endpunkte“ (`GET posts/{id}/diff`).
- Entwurf `docs/design/entwurf-website.html`: Vorschau (`#v-vorschau`, Knopf „Änderungen zeigen“, `<del>`/`<ins>` im Lead-Absatz) und Redaktionskasten („Neue Fassung … 2 Absätze, 1 neue Quelle“).
- Code: `app/src/Util/Sanitizer.php` (nutzt den HTML5-Parser `Dom\HTMLDocument`, ADR-010 — derselbe Parser hier), `app/src/Site/ContentRenderer.php`, `app/src/Domain/PostService.php` (`revision`, `snapshot`), `Builder::articleMain` (Phase 2), `app/src/Util/Text.php` (Klartext).
- Erlaubte Elemente des Artikel-HTML: `docs/decisions/010-sanitizer-html5-parser-whitelist.md`.

## Verfahren (entschieden)

1. Alte Fassung = Snapshot der `published_revision` (`PostService::revision`), neue Fassung = Arbeitsstand (`snapshot`).
2. **Blöcke:** die direkten Kindelemente des Artikel-HTML. Signatur je Block = Tag-Name + Klartext (Leerraum zusammengezogen). Längste gemeinsame Teilfolge (LCS) über die Signaturen.
3. Unveränderter Block → unverändert ausgeben. Läufe von entfernten und hinzugefügten Blöcken zwischen zwei gleichen Blöcken werden paarweise zugeordnet, solange der Tag gleich ist: **geänderter Block** → Block neu aufgebaut aus dem Klartext mit Wort-LCS, entfernte Wörter in `<del>`, neue in `<ins>`; Inline-Auszeichnung im geänderten Block entfällt in der Ansicht (bewusst). Übrige entfernte Blöcke → Element mit Klasse `diff-weg`; übrige neue → Klasse `diff-neu`.
4. Deckel: mehr als 400 Blöcke je Seite oder mehr als 2 000 Wörter in einem Blockpaar → dieser Teil wird als „ganz ersetzt“ markiert (alt `diff-weg`, neu `diff-neu`), keine Wort-LCS.
5. Danach läuft das Ergebnis durch dieselbe Darstellung wie die Vorschau (`articleMain` mit dem neuen Snapshot, dessen `content_html` durch das Diff-HTML ersetzt ist). `ContentRenderer` muss `<ins>`, `<del>` und die Klassen `diff-weg`/`diff-neu` erhalten — prüfen; falls er sie entfernt, dort gezielt erlauben.
6. **Quellen:** Schlüssel = URL, sonst Titel; `added`/`removed` als Listen `{title, url}`. **Bilder:** Menge der Medien-UIDs (Titelbild + `data-media` im HTML); `added`/`removed` als UID-Listen mit `alt`.

## AK der Phase

1. `Util\Diff::html(string $old, string $new): array{html: string, blocks_changed: int}` ist rein (keine Datenbank), Eingaben sind bereits sanitisiert.
2. Gegenproben mit einem Wegwerf-Skript (Scratchpad): gleicher Text → keine Marken, `blocks_changed = 0`; ein Wort im zweiten Absatz geändert → genau ein `<del>` und ein `<ins>` dort; Absatz eingefügt → ein `diff-neu`; Absatz gelöscht → ein `diff-weg`; Überschrift zu Absatz geändert → `diff-weg` + `diff-neu`; 2 500-Wort-Absatz geändert → ganz ersetzt, Laufzeit unter 1 s.
3. `GET posts/{id}/diff` für einen Artikel ohne Live-Fassung → 409 `no_live_version`; sonst Antwort nach Kontrakt.
4. Die Ausgabe enthält nur Elemente und Attribute, die der Sanitizer erlaubt, plus `ins`, `del`, `class="diff-weg|diff-neu"` (Gegenprobe: Ausgabe erneut durch den Sanitizer → keine weiteren Entfernungen außer diesen).

## Checkliste

- [x] `app/src/Util/Diff.php` (Blöcke, Paarung, Wort-LCS, Deckel)
- [x] Quellen- und Bild-Diff in `PostController::diff` (oder privater Helfer dort)
- [x] `ContentRenderer` auf `ins`/`del`/Klassen prüfen, ggf. erlauben
- [x] Route `GET posts/{id}/diff`
- [x] ADR-021 um Abschnitt „Änderungsansicht“ ergänzen (Verfahren oben in drei Sätzen; verworfen: zeichengenaues Diff über das ganze HTML — zerreißt Tags)
- [x] Doku: `docs/routes.md`, `docs/code-map.md` (`Util/Diff`)

## Report-Back

**Status: complete.** Gegenproben (Scratchpad-Skript) alle grün: gleicher Text, ein Wort, Einfügen, Löschen, Überschrift→Absatz, 2 500 Wörter (0,6 s bei 2 000 Wörtern ohne Gemeinsamkeit, 71 MB Spitze), > 400 Blöcke, Abbildung getauscht, Sanitizer-Gegenprobe (nur ins/del/diff-Klassen entfernt). Live gegen die Demo-Datenbank: `posts/1/diff` 200 mit Kontrakt-Schlüsseln und `blocks_changed = 0`, unbekannte ID 404; geänderter Stand zeigt `<ins>`, `<del>`, `diff-neu` in der Darstellung. 🟡 Der 409 `no_live_version` ist nicht live gelaufen (Demo-Datenbank hat nur freigegebene Artikel); er läuft über denselben `ApiErrors`-Pfad wie die übrigen 409.

Abweichungen (in ADR-021 festgehalten): Entfernte/neue Blöcke stecken in einem Wrapper-`div.diff-weg|diff-neu`, weil `ContentRenderer` `figure[data-media]` und Galerien ersetzt und die Klasse sonst verlöre (`ins`/`del`/Klassen selbst erhält er unverändert, ohne Änderung dort). Textlose Blöcke haben das Markup als Signatur. Listen, Tabellen, Abbildungen und `details` werden nicht paarweise vermischt.
