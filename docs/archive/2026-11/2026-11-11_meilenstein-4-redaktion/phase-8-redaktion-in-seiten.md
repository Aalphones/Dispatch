# Phase 8 — Startseite, Artikel, Kategorien: Redaktion in der Seite

## Kontext

- `README.md` → „Endpunkte“ (`overview`, `posts`, `changes`, `posts/by-slug`, `reaction`, `categories`, `stats/posts`).
- Entwurf (verbindlich): Startseite `.wartet` Zeilen 749–770 (Karten `.wartekarte` mit `.status review|zurueck|vorschlag`, `.vorher-nachher`), Reaktionen und `.kennzahl` am Aufmacher Zeilen 782–785; Artikel `.redkasten` Zeilen 813–823 (`.zahlen` mit `.funken`, `.aktionen` mit `.reaktion` und „Zurückziehen“, `.hinweiszeile` „Neue Fassung“); Dialog `#zurueckziehen` Zeilen 1158–1162; Kategorien `.gewicht-inline` Zeilen 949–990 mit Hinweistext; CSS: `.wartet*`, `.redkasten`, `.reaktion`, `.gewicht*`, `.kennzahl`, Handy-Regeln (Zeilen 512–513, 607).
- Code: Module aus Phase 6 (`api.js`, `ui.js`), Daten-Attribute aus Phase 6.

## AK der Phase

1. **Startseite, „Wartet auf dich“** (nur wenn mindestens ein Eintrag): Überschrift mit Zusammenfassung „n Entwürfe · m Vorschläge“; je Artikel in `review` eine Karte „Zur Freigabe“ mit Meta „Eingereicht <Datum Uhrzeit> · <Wörter> Wörter · <Quellen> Quellen · Formprüfung ok|n Fehler“ und Knopf „Ansehen und freigeben“ → `/vorschau/?id=<id>`; je zurückgegebenem Entwurf (`draft` mit `review_note`) eine Karte „Zurückgegeben“ mit gekürzter Anmerkung (120 Zeichen) und Knopf „Ansehen“; je Vorschlag eine Karte „Vorschlag · <Einstellung|Seite|Kategorie|Medium>“ mit vorher/nachher (`<del>`/`<ins>`, Texte gekürzt) und „Übernehmen“/„Ablehnen“. Übernehmen → Toast „Übernommen. Die Website wird neu gebaut.“, Karte verschwindet, Zähler im Konto-Knopf sinkt.
2. Die Reihe steht vor dem Aufmacher und ist auf dem Handy waagerecht wischbar (Entwurf Zeile 607).
3. **„Mehr davon / Weniger“** an Aufmacher und jeder Artikelkarte (über `data-post-id`) und im Redaktionskasten: Zustand aus `posts/by-slug` (Artikelseite) bzw. `GET reactions` (Start-, Kategorie- und Schlagwortseiten, Phase 4). Klick setzt/entfernt (zweiter Klick auf denselben Knopf = keine Reaktion), `aria-pressed` folgt, Toast mit „Rückgängig“.
4. **Kennzahl am Aufmacher** und **Redaktionskasten** auf Artikelseiten: „<Aufrufe> Aufrufe · <n> % zu Ende gelesen“, im Kasten zusätzlich „Lesezeit Ø m:ss“ und Funken der letzten 7 Tage (Höhen per `element.style.height`). Ohne Daten: „Noch keine Aufrufe“.
5. **Zurückziehen** im Redaktionskasten → Dialog nach Entwurf → `POST posts/{id}/unpublish` → Toast „Zurückgezogen. Die Website wird neu gebaut.“ → Weiterleitung zur Startseite.
6. **Neue Fassung:** bei `has_new_version` die `.hinweiszeile` „Neue Fassung — der Agent hat eine Überarbeitung eingereicht (Zusammenfassung aus `posts/{id}/diff` → `summary`)“ mit Knopf „Änderungen ansehen“ → `/vorschau/?id=<id>`.
7. **Kategorien-Seite:** an jeder Karte `.gewicht-inline` 0–5 mit `aria-pressed` des aktuellen Werts, Hinweiszeile nach Entwurf über dem Raster plus `info`-Icon („0 = Agenten schreiben nie von selbst darüber, 5 = bevorzugt. Selbst eingereichte Themen schreiben sie immer.“). Klick → `PATCH categories/{id}` `{interest}`, Toast „Interesse für <Name>: <n>“.
8. Handy 390 px: alle Knöpfe mindestens 44 px hoch tippbar, Kasten und Reihe ohne waagerechten Seiten-Rollbalken.

## Checkliste

- [x] `app/theme/redaktion/post.js` (Startseite: Wartet; Artikelseite: Redaktionskasten, Zurückziehen, neue Fassung)
- [x] `app/theme/redaktion/change.js` (Vorschlagskarten)
- [x] `app/theme/redaktion/reaction.js`
- [x] `app/theme/redaktion/stats.js` (Kennzahl-Teil)
- [x] `app/theme/redaktion/category.js` (Gewichte auf der Kategorien-Seite)
- [x] `start.js`: Seiten `start`, `artikel`, `kategorien` verdrahten (Werte von `data-seite` aus den Templates lesen)
- [x] `redaktion.css`: Stile nach Entwurf
- [x] Doku: `docs/code-map.md`

## Report-Back

Status: complete. Lokal gegengeprüft mit Chrome (headless, Fernsteuerung) gegen `php -S` und lokale MySQL mit Testdaten: AK 1–8 grün — Wartet-Reihe vor dem Aufmacher mit „2 Entwürfe · 14 Vorschläge“, Karten „Zur Freigabe“/„Zurückgegeben“ (Anmerkung auf 120 Zeichen gekürzt)/„Vorschlag · …“, Ablehnen entfernt Karte und senkt Zähler (17 → 16); Mehr/Weniger setzt, wechselt, entfernt (zweiter Klick) und „Rückgängig“ stellt wieder her, Stand nach Neuladen aus `posts/by-slug`; Kennzahl „1.224 Aufrufe · 58 % zu Ende gelesen“, Kasten mit Lesezeit 4:12 und sieben Funken; „Zurückziehen“-Dialog öffnet; Hinweiszeile „Neue Fassung“; Kategorien: sechs Gruppen mit richtigem Wert, Klick → Toast „Interesse für <Name>: <n>“; 390 px: alle geprüften Knöpfe ≥ 44 px, Seiten-Breite 390, Reihe scrollt in sich.

- `posts/by-slug` liefert `reaction` als Objekt `{reaction, note}` (Kontrakt nannte es nur „reaction“); `artikel()` liest `reaction.reaction`.
- Die Reaktions-Knöpfe an Karten kommen auch auf Kategorie- und Schlagwortseiten; Karten der Suche (`suche.js`) haben sie nicht.
- 🟡 Nicht geprüft: „Übernehmen“ mit echtem Neubau und das tatsächliche Zurückziehen (Aufruf der API in Phase 2 geprüft, hier nur der Dialog); Zusammenfassung in der Hinweiszeile mit echten Änderungen (Testdaten hatten keine).
