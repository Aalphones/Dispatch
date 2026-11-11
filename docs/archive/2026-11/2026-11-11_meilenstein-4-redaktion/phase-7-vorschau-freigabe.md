# Phase 7 — Vorschau-Seite mit Freigabe und Kommentieren

## Kontext

- `README.md` → „Endpunkte“ (Vorschau, Freigabe, Diff), „Signierte Vorschau-Links“.
- Entwurf (verbindlich): `#v-vorschau` Zeilen 1011–1057 (Freigabe-Leiste `.freigabe` mit `.pruefung`, Knöpfe Kommentieren/Änderungen zeigen/Zurück an Agent/Verwerfen/Freigeben, `.anmerkung`, `.k-hinweis`, `.vorschau-band`, `.k-liste`, `.k-box`), CSS Zeilen 404–420 und Handy-Regeln um Zeile 605–618, Verhalten im Entwurfs-Skript ab Zeile 1260 (`kModus`, `k-speichern`, `data-zurueckgeben`).
- Vault-Fehlerklasse (`frameworks/css-architecture.md`): **„Freiraum per Innenabstand reserviert“** — der Entwurf setzt auf dem Handy `#v-vorschau .artikel { padding-bottom: 18rem }` unter der festen Freigabe-Leiste. Rollbaren Freiraum stattdessen als `::after` mit fester Höhe anlegen; prüfen, dass der letzte Absatz über der Leiste lesbar ist und kein zweiter Rollbalken entsteht.
- Code: `app/theme/vorschau.js` (Platzhalter aus Phase 2), `templates/preview.php`, Module aus Phase 6.

## Anmerkungen an Stellen (entschieden)

- Kommentarmodus nach Entwurf: Absatz/Bild/Kasten anklicken oder Text markieren → `.k-box` mit Zitat (markierter Text, sonst Klartext des Blocks, gekürzt auf 200 Zeichen) und Textfeld; „Anmerkung speichern“ (auch Strg/⌘+Enter) legt einen Eintrag in `.k-liste` an und markiert die Stelle; Esc beendet.
- Ungesendete Anmerkungen liegen in `sessionStorage` unter `dispatch.anmerkungen.<id>.<revision>` und überleben Neuladen.
- „Zurück an Agent“ öffnet `.anmerkung`; „Zurückgeben“ sendet `POST posts/{id}/return` mit `note` = freier Text, dann je Anmerkung ein Block `> „<Zitat>“` + Zeilenumbruch + `→ <Anmerkung>`, Blöcke durch Leerzeile getrennt. Kein neues Datenbankfeld: das Briefing liefert `review_note` schon heute. Leerer freier Text und keine Anmerkung → Knopf deaktiviert.

## AK der Phase

1. **Ohne Anmeldung, mit Signatur:** `vorschau.js` holt `GET /dispatch/api/preview?…` und setzt `html` in `#vorschau-inhalt`; keine Freigabe-Leiste. Falsche Signatur → Text „Dieser Vorschau-Link ist ungültig oder abgelaufen.“ und Knopf „Anmelden“.
2. **Ohne Anmeldung, ohne Signatur:** Text „Zum Ansehen bitte anmelden.“ und der Login-Dialog öffnet sich.
3. **Angemeldet:** `post.js` holt `posts/{id}/preview` und zeigt die Leiste nach Entwurf: `.pruefung` mit je einem Eintrag pro Fehler (`.pruef.fehler`) und Warnung (`.pruef.warn`, Tooltip „Warnung, blockiert nicht“) plus grünen Einträgen Quellen/Wörter/Titelbild, wenn ohne Fehler. „Freigeben“ ist bei Fehlern deaktiviert, mit `title` = erster Fehler.
4. Freigeben → Toast „Freigegeben. Die Website wird neu gebaut.“; nach Antwort Weiterleitung auf `/artikel/<slug>/`. `rebuilt: false` → Toast „Freigegeben, aber die Website wurde nicht neu gebaut: <rebuild_error>. Der nächste Neubau holt es nach.“ und keine Weiterleitung (Freigabe bleibt; den Neubau löst jede weitere Freigabe oder ein Agent mit `rebuild_site` aus).
5. „Verwerfen“ fragt per `<dialog>` nach („Entwurf endgültig löschen? Das lässt sich nicht rückgängig machen.“) und nur bei `published_revision = null` sichtbar; danach zur Startseite.
6. „Änderungen zeigen“ nur bei `has_live`; schaltet zwischen Vorschau-HTML und Diff-HTML (`posts/{id}/diff`) um; Kopfzeile darüber: „2 Absätze geändert · 1 Quelle neu · 1 Bild entfernt“ aus `summary`; Listen der Quellen- und Bild-Änderungen unter dem Text. Stile für `ins`, `del`, `.diff-weg`, `.diff-neu` nach Entwurf (Lead-Absatz Zeile 1037).
7. Kommentieren und Zurückgeben nach „Anmerkungen an Stellen“; nach Zurückgeben: Liste und `sessionStorage` leer, Toast „Zurück an den Agenten, mit n Anmerkungen.“, Weiterleitung zur Startseite. Das Briefing (`get_editorial_brief`) zeigt den Text.
8. Handy 390 px: Leiste unten fest, „Freigeben“ volle Breite oben in der Leiste (Entwurf Zeile 617), letzter Absatz lesbar (Fehlerklasse oben), Kommentieren per Antippen.
9. `vorschau.js` ≤ 3 000 Byte.

## Checkliste

- [x] `app/theme/vorschau.js`
- [x] `app/theme/redaktion/post.js` (Vorschau-Teil: Leiste, Freigeben, Zurückgeben, Verwerfen, Diff)
- [x] `app/theme/redaktion/kommentar.js`
- [x] `redaktion.css`: Freigabe-Leiste, Kommentarmodus, Diff-Stile, Handy-Regeln (ohne Innenabstand-Freiraum)
- [x] `start.js`: Seite `vorschau` → `post.vorschau()`
- [x] Doku: `docs/code-map.md`

## Report-Back

Status: complete. Lokal gegengeprüft mit Chrome (headless, Fernsteuerung) gegen `php -S`: AK 1–8 grün (Verwerfen-Dialog, Freigeben mit Weiterleitung, Diff-Kopf „n Absätze geändert“, zwei Anmerkungen überleben Neuladen, Zurückgeben leert `sessionStorage`, 390 px: Leiste fest, „Freigeben“ volle Breite, kein Quer-Rollbalken). `vorschau.js` 1 090 Byte.

- Abweichung: Der grüne Eintrag „Titelbild“ der Prüfung entfällt — die Vorschau-Antwort trägt dazu kein Feld; fehlt ein verlangtes Titelbild, erscheint es als roter Fehler. Quellen und Wörter stehen grün.
- Freiraum unter der Handy-Leiste: `body.mit-freigabe::after` mit der gemessenen Leistenhöhe (`--freigabe-h`, ResizeObserver), kein Innenabstand am Artikel.
- 🟡 Nicht geprüft: Text über mehrere Absätze markieren (fällt auf den Block der Auswahl zurück), Antippen auf einem echten Touch-Gerät (getestet mit künstlichem `mouseup`).
