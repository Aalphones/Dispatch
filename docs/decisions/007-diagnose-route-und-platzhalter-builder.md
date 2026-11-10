# 007 — Strato-Probe: Diagnose-Route und Platzhalter über den Builder

**Status:** Akzeptiert (2026-11-09) · Diagnose-Route nur bis Ende Phase 5 von Meilenstein 0

## Kontext

Ob das Strato-Paket PHP 8.5, die nötigen Erweiterungen, den `Authorization`-Header, MySQL und ausgehendes HTTPS liefert, ist bisher Annahme. Ohne SSH lässt sich das nur über eine Route messen. Die Wurzel gehört zudem mehreren Anwendungen (ADR-001, ADR-003); ADR-005 lädt außer der `.htaccess` nichts dorthin.

## Entscheidung

- **Token aus zwei Headern:** Das Deploy-Token wird aus `Authorization: Bearer` oder `X-Dispatch-Token` gelesen, Vergleich mit `hash_equals`; leeres Token in `.env` lehnt jede Anfrage ab. Der zweite Header sorgt dafür, dass die Diagnose auch dann erreichbar ist, wenn Strato den `Authorization`-Header verschluckt — genau das soll sie ja messen.
- **Diagnose-Route (`GET /dispatch/api/diag`)** liefert Messwerte, nie Werte aus `.env` (nur „gesetzt ja/nein"). Sie wird am Ende von Phase 5 entfernt, nachdem die Messwerte in `docs/knowledge/INDEX.md` stehen.
- **Platzhalter per Deploy-Endpunkt statt Upload:** `POST /dispatch/api/deploy` migriert und lässt den `Builder` die Startseite schreiben. Der Builder schreibt nur relativ zur Wurzel, verweigert `..`, absolute Pfade und reservierte oberste Namen, schreibt atomar und trägt jede Datei ins Manifest. Löschen folgt erst in Meilenstein 2.
- **Migrator ohne Transaktion, Anweisungen einzeln:** MySQL committet DDL implizit; ein Mehrfach-`exec` verschluckt Fehler nach der ersten Anweisung.
- **Fehlerantworten ohne Ausnahme-Text:** Der Text kann Host oder Benutzer enthalten; er steht nur im Server-Log.

## Konsequenzen

- Die Diagnose-Route ist eine zeitlich begrenzte Angriffsfläche, nur mit Token erreichbar; ihr Entfernen ist Pflichtpunkt von Phase 5.
- Eine veraltete `placeholder.<hash>.css` bleibt nach einer CSS-Änderung liegen, bis Meilenstein 2 das Löschen über das Manifest einführt.
- Platzhalter-Builder in Meilenstein 2 durch `build()` ersetzt (ADR-014).
