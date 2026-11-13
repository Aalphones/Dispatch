# 028 — Zeitversetztes Veröffentlichen ohne Cron

**Status:** Akzeptiert (2026-11-13) · ergänzt ADR-012

## Kontext

Der Inhaber will nicht alle freigegebenen Artikel auf einmal online haben: ein Veröffentlichungsdatum am Artikel und ein Tageslimit für die Warteschlange. Bisher macht `approve` den Artikel sofort zum Live-Stand (ADR-012). Die Website ist statisch und es gibt keinen Server-Cron (ADR-005, ADR-021, `docs/PROJECT.md` → Nicht im Scope); „zum Termin erscheinen“ muss daher von einer Anfrage ausgelöst werden.

## Entscheidung

- **Neuer Status `scheduled` („Geplant“).** `approve(when)` plant einen nie veröffentlichten Artikel ein: `slot` (Warteschlange), `fixed` (fester Termin) oder `now` (sofort, Standard — jeder alte Aufrufer verhält sich wie zuvor). Eine neue Fassung eines Live-Artikels geht immer sofort online. `reschedule`, `unschedule` (zurück auf `review`) und `release` (System) ergänzen die Übergänge aus ADR-012; alle laufen in `PostService` in einer Transaktion auf der gesperrten Zeile und schreiben ins Protokoll.
- **Sperre für Agenten:** `update` und `restoreRevision` auf `scheduled` → `invalid_transition`, auch für den Inhaber; erst `unschedule`. Der freigegebene Inhalt ist damit gleich dem, was erscheint. `discard` bleibt dem Inhaber.
- **Termine:** Berliner Kalendertag, `release_per_day` (1–6, Standard 2) feste Uhrzeiten von 08:00 bis 18:00 als feste Tabelle, jeden Tag. Je Tag in Europe/Berlin gerechnet und nach UTC umgerechnet, nicht mit festen Versätzen; gespeichert wird UTC. Das Tageslimit gilt nur für die Warteschlange, feste Termine und „sofort“ kommen zusätzlich.
- **Zuteilung:** Reihenfolge `scheduled_since`, dann `id`. Ein Termin ist belegt, sobald ein Artikel mit diesem `release_slot` erschienen ist; ein Artikel nimmt nur Termine, die nicht vor seinem `scheduled_since` liegen. Ein verstrichener, ungenutzter Termin verfällt. Termine vergangener Tage werden nie nachgeholt (keine Welle nach einem stillen Tag); verpasste Termine des laufenden Tages beim nächsten Auslöser.
- **Auslöser an der Brücke:** Jede Anfrage an `app/index.php` prüft, ob etwas fällig ist (Umsetzung Phase 2). Die Schnellprüfung liest nur `storage/schedule/next.json` (`ScheduleClock`), kein Datenbankzugriff; der Prüfzeitpunkt liegt höchstens 15 Minuten voraus, ein veralteter Stand heilt sich selbst. Änderungen am Plan und am Tageslimit löschen die Datei.
- **Akteur `schedule`** veröffentlicht (`PostService::release`, Audit `post.release`). Doppeltes Erscheinen verhindern die Datenbanksperre `dispatch_schedule_release` (nicht wartend) und die Statusprüfung auf der gesperrten Zeile.
- **Agenten** sehen `scheduled` und den festen Termin; geplante Artikel zählen in der Rotation als „heute erschienen“ und in der Dubletten-Warnung als Live-Titel. `release_per_day` können Agenten nur vorschlagen.
- **Der Queue-Auftrag gilt mit der Freigabe als erledigt**, auch wenn der Artikel später aus dem Plan genommen wird.

## Verworfen

- **Externer Wecker (cron-job.org):** zusätzlicher Dienst; der Inhaber wählte Besucher als Auslöser.
- **Verpasste Tage nachholen:** erzeugt nach stillen Tagen eine Welle.
- **Reihenfolge von Hand:** mehr Oberfläche ohne genannten Bedarf; vorziehen geht über „Fester Termin“ oder „Jetzt veröffentlichen“.

## Konsequenzen

- Das Erscheinen hängt am Verkehr: Ohne Anfrage an die Brücke erscheint nichts, auch nicht zum Termin. Feed und Sitemap werden dafür in einer späteren Phase ebenfalls über die Brücke geliefert.
- Termine einer Stunde, die es bei der Zeitumstellung nicht gibt, lassen sich nicht als fester Termin wählen (`invalid_value`).
