# 023 — Redaktionsmodus im Browser

**Status:** Akzeptiert (2026-11-11)

## Kontext

Der Inhaber redigiert in der Website selbst (ADR-013). Besucher dürfen dafür nichts laden und keinen PHP-Aufruf auslösen (ADR-015); die CSP erlaubt weder Inline-Skripte noch `style`-Attribute.

## Entscheidung

- **Ordner-Asset statt Einzel-Hashes**: Der Builder legt `app/theme/redaktion/` unverändert als `assets/redaktion-<sha1-8>/` ab (Hash über alle Dateien, nach Name sortiert, Inhalt aneinandergehängt; Manifest-Einträge je Datei). Die Module importieren einander relativ; ein geänderter Inhalt ergibt einen neuen Ordnernamen und umgeht den Cache, der alte Ordner fällt über das Manifest weg. Das `<html>`-Tag trägt `data-redaktion` mit der Adresse von `start.js`.
- **Login-Logik in `site.js`**: Knopf „Anmelden“ und `<dialog id="login">` stehen statisch im Layout, `/#anmelden` öffnet ihn. Erfolg setzt das Merkzeichen `localStorage['dispatch.angemeldet']` und lädt `start.js` per `import()`; `start(token?)` ist der einzige Export. Mit Merkzeichen lädt `site.js` `start()` beim Seitenaufruf. Ohne Merkzeichen gibt es keinen Aufruf außer dem Anmelden und dem Zähl-Aufruf.
- **Token nur im Speicher, geteilt über `BroadcastChannel('dispatch-sitzung')`**: `sitzung.token()` liefert aus der Modul-Variablen, sonst fragt es offene Tabs (150 ms), sonst `POST auth/refresh`; gleichzeitige Aufrufe teilen sich ein Promise. Erneuert wird 60 s vor `exp`. Ein 401 auf Refresh beendet die Sitzung in allen Tabs (Merkzeichen weg, `body.angemeldet` weg, Konto-Elemente entfernt, Meldung).
- **Budgets**: `site.js` ≤ 5 000, `suche.js` ≤ 9 500, `zaehler.js` ≤ 2 500, `vorschau.js` ≤ 3 000, `style.css` ≤ 48 000 Byte (ersetzt die Zahlen aus ADR-015 für diese Dateien). Redaktionsmodule haben kein Budget.
- **Kein `style`-Attribut in erzeugtem HTML**: `el()` ignoriert es; Maße setzt das Skript über CSSOM.
- **Gast-Stile in `style.css`, alles Übrige in `redaktion.css`**: Knopf, Dialog-Grundlagen und Formularfelder für den Login gehören zur Gastseite; Popover, Zähler, Toast und Erweiterungen laden erst mit `start.js`.
- **Anmerkungen (Phase 7)**: werden mit der Rückgabe als Text im `review_note` des Artikels gespeichert; Phase 7 legt das Format fest.

## Verworfen

- **Import-Pfade beim Bau umschreiben** (Einzel-Hashes pro Modul): Reihenfolge der Abhängigkeiten, fehleranfällig.
- **Token in `localStorage`**: für Skripte lesbar und bleibt nach dem Schließen liegen.

## Konsequenzen

- Der Gast-Knopf bleibt im HTML und wird per `body.angemeldet` ausgeblendet; das Konto fügt `konto.js` daneben ein.
- Menüeinträge, deren Dialog erst Phase 9 baut, sind ausgeblendet (`ready: false` in `konto.js`).
