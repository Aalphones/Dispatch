# Phase 5 — Zustimmungsseite und „Verbundene Apps“

## Kontext

- **Verbindlicher Entwurf:** `artifacts/entwurf-zustimmung.html` und `artifacts/README.md` (Zustände, Texte, CSS-Block „Ergänzungen für Zustimmung …“).
- `README.md` → „Kontrakt“: Endpunkte `oauth/requests`, `oauth/consent`, `oauth/grants`, „Weiterleitungen aus `/authorize`“ (Fehlercodes der Seite).
- ADR-023 (Redaktionsmodus im Browser: `start.js` wählt per `data-seite` das Modul), ADR-013 (Website-Entwurf), ADR-015 (Skript-Budgets; der Redaktionsmodus hat keins), ADR-022 (Zählen).
- Code: `app/src/Site/Builder.php` (`collectPreview()` als Muster einer Hülle, `collectTechnical()` für `robots.txt` — `/anmelden/` ist dort schon gesperrt), `app/templates/preview.php`, `app/templates/layout.php` (`data-seite`, Login-Dialog `#login`), `app/config.php` (`builder_top_level`), `app/theme/site.js` (Login, `isMarked()`, Laden des Redaktionsmodus, Bedingung mit `document.body.dataset.seite !== 'vorschau'`), `app/theme/zaehler.js` (welche Seiten nicht gezählt werden), `app/theme/redaktion/start.js` (`startPage`), `app/theme/redaktion/api.js`, `ui.js` (`el`, `info`, `toast`, `fehlerZeile`), `token.js` (Dialog „Zugänge“), `redaktion.css`.
- Fehlerklassen geprüft: keine einschlägig.

## AK der Phase

1. **Hülle:** `Builder::collectConsent()` schreibt `anmelden/zustimmung/index.html` mit `bodyPage` = `zustimmung`, `robots` = `noindex,nofollow`, Inhalt aus `app/templates/consent.php`: `<main>`-Inhalt = Abschnitt `.zust` im Gast-Zustand des Entwurfs („Eine App möchte auf deinen Blog zugreifen“, Erklärsatz, Knopf „Anmelden“, der den Login-Dialog öffnet). `anmelden` steht in `builder_top_level`. Kein Besuch dieser Seite wird gezählt (`zaehler.js`/`site.js` wie bei `vorschau`).
2. **Login:** Ohne Merkzeichen öffnet `site.js` auf `data-seite="zustimmung"` den Login-Dialog sofort; über dem Formular steht der Hinweis aus dem Entwurf („Eine App möchte sich mit deinem Blog verbinden. Melde dich an, um zu entscheiden.“). Nach dem Login (oder mit Merkzeichen nach dem Refresh) startet `start.js` → `zustimmung.js` → `seite()`.
3. **`zustimmung.js`** zeigt nach `?anfrage` bzw. `?fehler` die Zustände des Entwurfs mit dessen Texten und Klassen:
   - Laden (Platzhalterbalken) während `GET oauth/requests/{id}`.
   - Zustimmung: Kopfzeile „Verbindung anfragen“, Titel „<Name> möchte auf deinen Blog zugreifen“, Liste „App“ (Name + „ausgewiesen von <host>“ mit Häkchen; bei `kind = manual` stattdessen „von dir angelegt“ ohne Häkchen) und „Antwort geht an“ (`redirect_host`, bei `loopback` Zusatz „ein Programm auf diesem Gerät“ und der Warnhinweis), Rechte als Auswahlkarten nur für angefragte Scopes (`blog:admin` nicht vorab angehakt) je mit Info-Symbol und Erklärtext aus dem Entwurf, Hinweis „Veröffentlichen kann keine App …“, Knöpfe „Ablehnen“ (sekundär) und „Erlauben“ (primär), Restzeit aus `expires_in` in Minuten.
   - Keine Rechte gewählt → Fehlerzeile „Wähle mindestens ein Recht – oder lehne ab.“, kein Aufruf.
   - Senden scheitert (Netz, 5xx) → Fehlerzeile aus dem Entwurf, Knöpfe bleiben bedienbar.
   - Erfolg → Zustand „Erlaubt“ mit „Weiter zu <host> …“ und Link „hier weiter“ auf `redirect`, dann `location.assign(redirect)`; Ablehnen → Zustand „Abgelehnt“, dann ebenso weiter.
   - 404/410 → „Anfrage abgelaufen“; `?fehler=unbekannte_app` → „Unbekannte App abgewiesen“ (mit `host`, falls vorhanden), `app_nicht_erreichbar` → Titel „App nicht erreichbar“, Text „Claude konnte nicht geprüft werden. Versuche es in ein paar Minuten noch einmal.“, `falsche_rueckadresse` → Titel „Unbekannte Rückadresse“, Text „Die Anfrage wollte die Antwort an eine Adresse schicken, die die App nicht angemeldet hat. Nichts wurde gespeichert.“
   - Handy (≤ 46rem): Knöpfe untereinander, „Erlauben“ oben; „App“/„Antwort geht an“ einspaltig.
4. **„Verbundene Apps“ im Dialog „Zugänge“** (`token.js`): zwei Gruppen mit Gruppenkopf wie im Entwurf — „Zugänge mit Token · Claude Code, geplante Aufgaben“ (bisherige Liste) und „Verbundene Apps · per Zustimmung“ aus `GET oauth/grants`: Zeile mit KI-Symbol, „<Name> · <host>“, „verbunden seit <Datum> · zuletzt <Datum/Zeit>“, Rechte-Chips, Knopf „Trennen“ mit aufklappender Nachfrage („… trennen? Die App verliert sofort den Zugriff und muss sich neu verbinden.“), danach Toast „„<Name>“ getrennt.“. Leere Gruppe → Anleitung in drei Schritten mit Kopierfeld für `<BASE>/dispatch/mcp`. Einleitungssatz des Dialogs wie im Entwurf.
5. CSS-Block des Entwurfs (ohne Pult und ohne `body { padding-bottom }`) in `redaktion.css`; Hell und Dunkel; bei 390 px keine waagerechte Bildlaufleiste.
6. CSP bleibt unverändert: die Weiterleitung läuft über `location.assign`, nicht über ein Formular (`form-action 'self'`).

## Checkliste

- [x] `app/templates/consent.php`, `Builder::collectConsent()`, `config.php` (`builder_top_level`)
- [x] `site.js`, `zaehler.js` (Ausnahme `zustimmung`), `start.js` (`case 'zustimmung'`)
- [x] `app/theme/redaktion/zustimmung.js`
- [x] `token.js` um Gruppe „Verbundene Apps“, `redaktion.css`
- [x] `php bin/dispatch build`, lokal mit Claude Code durchspielen (`claude mcp add --transport http dispatch-oauth http://localhost:8080/dispatch/mcp`, dann `/mcp` → Anmelden)
- [x] Doku: `docs/code-map.md` (Seitentyp `consent`, `zustimmung.js`), `docs/glossary.md`, falls neue Begriffe

## Report-Back
Umgesetzt wie im Entwurf. Geprüft lokal mit Chrome (Gast-Hülle mit Login-Hinweis, Zustimmung bei 390 px ohne Querlauf, „kein Recht“-Fehler, Erlauben, Zugänge-Dialog mit Trennen). Abweichungen: Die Gast-Hülle lädt `redaktion.css` selbst (sonst wäre sie vor dem Login unformatiert); bei Rückfall-Clients zeigt „Verbundene Apps“ den vom Server gelieferten Host („von dir angelegt“). Nicht geprüft: Claude Code gegen die Seite (Phase 6).
