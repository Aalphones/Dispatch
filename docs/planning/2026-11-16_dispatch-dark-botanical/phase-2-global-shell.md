# Phase 2 — Dunkle globale Schale

## Kontext (lesen)

- `app/templates/layout.php` — Kopf, Suche, Login, Kategorienavigation, gemeinsamer Footer.
- `app/theme/style.css` — Tokens, Header, Navigation, Karten, Footer und Breakpoints; aktuell 51.001 Byte bei maximal 50.000 Byte.
- `app/theme/site.js`, `app/theme/suche.js`, `app/theme/redaktion/{start,konto,ui}.js` — Klassen, IDs und Datenattribute der bestehenden Interaktionen dürfen nicht umbenannt werden.
- `docs/design/dispatch-dark-botanical.md` (entsteht in Phase 1), `docs/conventions/frontend.md`, ADR-015, ADR-023.

## AK

1. Der sichtbare Kopf besteht auf breiten Ansichten aus einer kompakten Zeile mit Logo links sowie Suche/Login rechts und einer zweiten Kategorienavigation; er produziert weder doppelte sichtbare Seitentitel noch eine große Leerzone.
2. Alle bisherigen Selektoren/IDs für Suche, Konto, Login und Navigation bleiben erhalten; der sichtbare Startseiten-Name wird nicht als zweites Hero ausgegeben, die semantische `h1` bleibt einmal im Hauptinhalt.
3. Der Footer-Motivblock ist im normalen Dokumentfluss nach `<main>` und vor `<footer>`, nie `fixed`, auf Desktop rechts/mittig-rechts und unter 46rem mittig und kleiner. Das Maskottchen-PNG ist dekorativ (`alt=""`) und hat feste CSS-Containermaße gegen Layout Shift.
4. `style.css` fasst Tokens und wiederkehrende Regeln zusammen statt Regeln anzuhäufen und unterschreitet 50.000 Byte; `prefers-reduced-motion`, sichtbarer Fokus und Druckansicht bleiben erhalten.

## Checkliste

- [ ] `app/templates/layout.php`: alte Ausgabe-/Titelkopf-Struktur durch Logo-Verlinkung mit `logoPrimary` und Mobil-`logoIcon` ersetzen; Search-Form, `#anmelden`, `.nav`, Kategorienlinks und `aria-current` unverändert anbinden.
- [ ] `app/templates/layout.php`: auf der Startseite eine visuell versteckte `h1` am Anfang von `<main>` ausgeben; `Builder::collectHome()` setzt `nameAlsH1` nicht mehr für den Header.
- [ ] `app/templates/layout.php`: Footer-Tags und RSS/Kategorien/Claim entfernen; minimalen Linkblock `Kontakt → /ueber/`, `Impressum`, `Datenschutz` und Marken-Signet aus `logoIcon` ausgeben; `footerMascot` direkt davor als dekoratives `<img>` mit seinen vorhandenen `width="1774" height="887"` einfügen.
- [ ] `app/theme/style.css`: Tokenblock auf Handoverwerte umstellen, `color-scheme: dark` dauerhaft setzen und die alte helle bzw. papierfarbene Token-Überschreibung entfernen. Bestehende semantische Tokens (`--paper`, `--ink`, `--rule`, `--accent-ink`) dürfen als kompatible Aliase bleiben, müssen aber auf die neuen `--color-*`-Werte zeigen.
- [ ] `app/theme/style.css`: Header, Suche, Konto, Navigation, Chip, Dialog-Grundform, Footer, Fokus und Druck mit den neuen Tokens konsolidieren; Hover nur 150–200 ms und bei `prefers-reduced-motion` ohne Bewegung.
- [ ] `app/theme/style.css`: Desktop-, 62rem- und 46rem-Regeln neu ordnen, damit vorhandene mobile Suche, horizontale Kategorienavigation und Login nicht verdeckt oder abgeschnitten werden.

## Report-Back
