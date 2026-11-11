# Phase 6 — Redaktionsmodus: Login, Sitzung, Konto-Menü

## Kontext

- `README.md` → „Redaktionsmodus im Browser“, „Byte-Budgets“, „Anmeldung“.
- Entwurf `docs/design/entwurf-website.html` — verbindlich (ADR-013): Kopf mit `.konto` (Zeilen 715–716: Gast-Knopf „Anmelden“, angemeldet „Redaktion“ mit Zähler), Popover `#menue` (Zeilen 733–745), Dialog `#login` (Zeilen 1091–1099), Toast (`#toast`), CSS-Block „Redaktion (nur nach Login)“ ab Zeile 328 und alle Regeln mit `.red`, `body.angemeldet`, `.zaehler`, `.dlg-*`, `.knopf`, `.info`/`data-tipp`. Das Entwurfs-Pult unten links gehört nicht dazu.
- `docs/conventions/frontend.md`, `docs/decisions/015-…`, Vault-Abschnitte `frameworks/a11y.md` (Forms, Interactive, Dynamic Content) — Dialoge mit `<dialog>`, Fokus, `aria-live` für den Toast.
- Code: `app/theme/site.js`, `app/templates/layout.php`, `app/src/Site/Assets.php`, `app/src/Site/Builder.php` (Daten-Attribute an `body`, Karten, Kategorien), `app/templates/partials/card.php`, `app/templates/categories.php`, `app/theme/style.css` (nur Gast-Teil: Knopf „Anmelden“ — der Platz ist seit Meilenstein 2 frei).

## Aufbau (entschieden)

- **Statisch im Layout** (für alle, klein): Knopf `.konto.nur-gast` „Anmelden“ im Kopf und `<dialog id="login">` nach Entwurf. Die Gast-Stile dafür gehören in `style.css` (Budget beachten); alles andere in `redaktion.css`.
- **`site.js`** (≤ 5 000 Byte inkl. Phase 5): Knopf und `/#anmelden` öffnen den Dialog; Absenden → `POST /dispatch/api/auth/login` mit `credentials: 'same-origin'`; Erfolg → Merkzeichen setzen, Token an `start.js` übergeben (`import(root.dataset.redaktion).then(m => m.start(token))` — `start.js` exportiert dafür genau `start(token?)`), Dialog schließen. Fehler: 401 → „Name oder Passwort stimmt nicht.“ im Dialog (`role="alert"`), 429 → „Zu viele Versuche. Bitte in 15 Minuten erneut.“. Merkzeichen beim Laden gesetzt → `start()` ohne Token.
- **`sitzung.js`:** `token()` liefert ein gültiges JWT (Speicher → `BroadcastChannel` → Refresh, nach Kontrakt); `abmelden()`, `ueberallAbmelden()`; nach 401 auf Refresh: Merkzeichen löschen, `body.angemeldet` entfernen, Toast „Sitzung abgelaufen – bitte neu anmelden.“, Redaktionselemente entfernen. Erneuert das JWT 60 s vor `exp` beim nächsten API-Aufruf.
- **`api.js`:** `api(method, path, body?)` → JSON; setzt `Authorization`; bei 401 einmal neues Token holen und wiederholen; Fehler → wirft `{error, message, status}`.
- **`ui.js`:** `toast(text, rueckgaengig?)`, `dialog(id, html)` (legt einmalig an, `showModal`), `info(text)` (i-Icon mit Tooltip nach Entwurf `.info[data-tipp]`, per Tastatur fokussierbar), `el(tag, attrs, kinder)` ohne `style`-Attribute.
- **`konto.js`:** ersetzt den Gast-Knopf durch „Redaktion“ mit Zähler (`overview`: `review + returned + changes`), Popover-Menü nach Entwurf mit Einträgen Freigaben (Zahl), Vorschläge (Zahl), Thema einreichen, Interessen, Statistik, Zugänge für Agenten, Abmelden. Einträge, deren Dialog erst Phase 9 baut, sind bis dahin ausgeblendet. „Freigaben“ und „Vorschläge“ springen zur Startseite, Abschnitt `#wartet` (Phase 8 baut ihn).
- **Daten-Attribute** im Builder: `body[data-post-id]` auf Artikelseiten, `[data-post-id]` an jeder Artikelkarte und am Aufmacher, `[data-category-id]` an jeder Karte auf `/kategorien/`.

## AK der Phase

1. Ohne Merkzeichen: Netzwerk-Tab zeigt auf Start-, Artikel-, Kategorien-, Suchseite keinen Aufruf von `/dispatch/api/*` außer `hit` und kein Laden aus `assets/redaktion-*`.
2. Anmelden über Knopf und über `/#anmelden` funktioniert; falsches Passwort zeigt die Meldung im Dialog, der Dialog bleibt offen, Fokus im Passwortfeld.
3. Nach dem Anmelden: `body.angemeldet`, Konto-Knopf „Redaktion“ mit Zähler, Menü öffnet und schließt (Esc, Klick daneben); Seitenwechsel bleibt angemeldet (Refresh oder Tab-Token).
4. Fünf Tabs gleichzeitig neu laden → alle angemeldet; im Netzwerk höchstens so viele Refresh-Aufrufe wie Tabs, keiner mit 401.
5. Abmelden → Merkzeichen weg, Cookie weg, Redaktionselemente weg ohne Neuladen. „Überall abmelden“ (Menü-Eintrag kommt mit Phase 9 in „Zugänge“; hier über `sitzung.ueberallAbmelden()` in der Konsole prüfen) meldet einen zweiten Browser beim nächsten Refresh ab.
6. `redaktion-<hash>/` entsteht beim Bau, alter Ordner wird über das Manifest entfernt; ein geändertes Modul ergibt einen neuen Hash.
7. Kein `style="…"` in von Modulen erzeugtem HTML (Suche im Ordner nach `style=` liefert nichts); keine CSP-Meldung in der Konsole.
8. Handy 390 px: Konto-Knopf zeigt nur den Avatar mit Zähler (Entwurf Zeile 537), Menü vollständig erreichbar.

## Checkliste

- [x] `Assets`: Ordner `app/theme/redaktion/` als `assets/redaktion-<hash>/` (Kontrakt), Manifest-Einträge je Datei; `layout.php`: `data-redaktion`
- [x] `layout.php`: Gast-Knopf und `<dialog id="login">`; `style.css`: Gast-Stile
- [x] Builder/Templates: Daten-Attribute
- [x] `site.js`: Login und Laden
- [x] `app/theme/redaktion/start.js`, `sitzung.js`, `api.js`, `ui.js`, `konto.js`, `redaktion.css` (Stile aus dem Entwurf übernehmen: Redaktions-Block, Dialoge, Knöpfe, Toast, Konto angemeldet, Popover)
- [x] ADR-023 „Redaktionsmodus im Browser“: Ordner-Asset statt Einzel-Hashes (relative Imports), Login-Logik in `site.js`, Token nur im Speicher + `BroadcastChannel`, neue Budgets, Anmerkungen-Speicherung (Phase 7, Abschnitt dort vorwegnehmen). Verworfen: Import-Pfade beim Bau umschreiben (Reihenfolge der Abhängigkeiten, fehleranfällig)
- [x] Doku: `docs/code-map.md` (Ordner `theme/redaktion/`), `docs/conventions/frontend.md` (Budgets, keine `style`-Attribute)

## Report-Back

Status: complete. Lokal gegengeprüft mit Chrome (headless, Fernsteuerung) gegen `php -S`: AK 1–5, 7 (Ordnersuche `style=` leer; die einzige Konsolenzeile ist der gewollte 401 beim falschen Passwort), 8 (390 px) grün; AK 6 durch Hash-Wechsel und Ordner-Löschung bestätigt. Fünf Tabs ohne offenen Tab: ein Refresh (200), die übrigen Tabs holen das Token per BroadcastChannel. Budgets: `site.js` 4 266, `style.css` 47 545 Byte.

- 🟡 Nicht geprüft: `ueberallAbmelden()` gegen einen zweiten Browser (Refresh-Seite ist Phase 1).
- Daten-Attribute: `data-post-id` an Karten/Aufmacher und `data-category-id` waren schon da; neu ist nur `body[data-post-id]` auf Artikelseiten.
- `start.js` ruft noch keine seitenbezogenen Module (kommen mit Phase 7–9); Menüeinträge für Thema einreichen, Interessen, Statistik, Zugänge sind ausgeblendet (`ready: false` in `konto.js`).
- Der Zähler im Konto nutzt `overview` (`review + returned + changes`); „Freigaben“ = `review + returned`, „Vorschläge“ = `changes`.
