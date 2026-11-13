# Zeitversetzt veröffentlichen: fester Termin und Warteschlange mit Tageslimit

Zeremonie-Tier: Vollplan (private). Auslöser: Der Inhaber will nicht alle freigegebenen Artikel auf einmal online haben. Wunsch 1: ein Veröffentlichungsdatum am Artikel. Wunsch 2: ein Tageslimit, wie viele Artikel „aus der Freigegeben-Queue“ pro Tag höchstens erscheinen. Heute macht `approve` einen Artikel sofort zum Live-Stand (ADR-012), und einen Server-Cron gibt es nicht (ADR-005, ADR-021, `docs/PROJECT.md` → Nicht im Scope).

Entwurf (freigegeben am 2026-11-12, verbindlich): [`artifacts/entwurf-veroeffentlichungsplan.html`](artifacts/entwurf-veroeffentlichungsplan.html), Erläuterung in [`artifacts/README.md`](artifacts/README.md).

## Entscheidungen (fallen hier, nicht beim Umsetzen)

1. **Neuer Status `scheduled` („Geplant“).** Ein freigegebener, nie veröffentlichter Artikel wartet darin auf seinen Termin. Übergänge (ergänzen ADR-012, alle in `PostService`, Transaktion auf gesperrter Zeile, Audit):

   | Aktion | Wer | Von | Nach | Wirkung |
   |---|---|---|---|---|
   | `approve(when)` | Inhaber | `review` | `published` bei `when = now` **oder** wenn `published_revision` gesetzt ist; sonst `scheduled` | Formprüfung wie bisher; `scheduled`: `schedule_mode`, `scheduled_since = jetzt`, bei `fixed` `scheduled_at`; Queue-Auftrag `markDone` in beiden Fällen |
   | `reschedule(when)` | Inhaber | `scheduled` | `scheduled` (`slot`/`fixed`) oder `published` (`now`) | `slot`: ans Ende der Warteschlange (`scheduled_since = jetzt`); `fixed`: neuer `scheduled_at` |
   | `unschedule` | Inhaber | `scheduled` | `review` | Plan-Spalten geleert; wartet wieder auf Freigabe |
   | `release` | System (Akteur `schedule`) | `scheduled`, fällig | `published` | `published_revision = revision`, `published_at` jetzt, bei Warteschlange `release_slot` = Termin |
   | `update`, `restore` | — | `scheduled` | — | abgewiesen (`invalid_transition`): erst aus dem Plan nehmen |
   | `discard` | Inhaber | `scheduled` | — | erlaubt (nie veröffentlicht), wie bisher |

   `submit`, `withdraw`, `return`, `unpublish` gehen aus `scheduled` nicht — die bestehenden Prüfungen schließen den Status schon aus (geprüft in `PostService`).
2. **Drei Zeitpunkte beim Freigeben:** `slot` (nächster freier Platz der Warteschlange; Vorauswahl in der Oberfläche), `fixed` (fester Termin), `now` (sofort). Fehlt `when` im API-Aufruf, gilt `now` — das bisherige Verhalten bleibt für jeden alten Aufrufer gleich.
3. **Neue Fassung eines Live-Artikels geht immer sofort online** (`published_revision` gesetzt → `when` wird ignoriert). Die Oberfläche zeigt dafür keinen Zeitpunkt-Dialog.
4. **Das Tageslimit gilt nur für die Warteschlange** (Wortlaut des Inhabers). Feste Termine und „sofort“ kommen zusätzlich. Einstellung `release_per_day`, Ganzzahl 1–6, Standard 2; Agenten können sie nur vorschlagen.
5. **Erscheinungstermine** (Zeitzone Europe/Berlin): Termin k (k = 0 … n−1) liegt bei 08:00 + round(k · 720 / n / 15) · 15 Minuten. Fest verdrahtet als Tabelle, damit nichts gerechnet werden muss:

   | n | Termine |
   |---|---|
   | 1 | 08:00 |
   | 2 | 08:00, 14:00 |
   | 3 | 08:00, 12:00, 16:00 |
   | 4 | 08:00, 11:00, 14:00, 17:00 |
   | 5 | 08:00, 10:30, 12:45, 15:15, 17:30 |
   | 6 | 08:00, 10:00, 12:00, 14:00, 16:00, 18:00 |

   Jeden Tag, auch am Wochenende.
6. **Zuteilung der Warteschlange:** Reihenfolge `scheduled_since`, dann `id` (älteste Freigabe zuerst). Ein Termin wird belegt, sobald ein Artikel mit diesem `release_slot` erschienen ist. Ein Artikel nimmt nur Termine, die nicht vor seinem `scheduled_since` liegen. Verstreicht ein Termin, ohne dass ein Artikel bereitstand, verfällt er. **Termine vergangener Tage werden nie nachgeholt** (keine Nachhol-Welle nach einem stillen Tag); verpasste Termine des laufenden Tages schon, beim nächsten Auslöser, notfalls mehrere auf einmal.
7. **Auslöser ohne Cron:** Jede Anfrage an die Brücke (`app/index.php`, vor `$router->dispatch`) prüft, ob etwas fällig ist: Zähl-Aufruf der Besucher, Redaktions-API, MCP, OAuth und nach Entscheidung 8 auch Feed und Sitemap. Die Schnellprüfung liest nur `storage/schedule/next.json` (kein Datenbankzugriff). Der Prüfzeitpunkt darin ist höchstens 15 Minuten entfernt, so heilt sich ein veralteter Stand selbst. Wird etwas freigegeben, folgt der Neubau in derselben Anfrage; scheitert er, merkt die Datei `rebuild: true` vor und der nächste Auslöser holt den Bau nach.
8. **Feed und Sitemap laufen über die Brücke** (Inhaber, 2026-11-12): Eine Rewrite-Regel in der Wurzel-`.htaccess` leitet nur `/feed.xml` und `/sitemap.xml` an `dispatch/index.php` (dieselbe Technik wie die bestehende `.well-known`-Regel). `Api\FeedController` liefert die statische Datei mit `Last-Modified`, `ETag` und `304` aus. Feed-Reader und Crawler lösen damit die Prüfung aus.
9. **Agenten** sehen den Status `scheduled` (und bei festem Termin `scheduled_at`) in `get_post`, `list_posts` und im Briefing. Geplante Artikel zählen in der Rotation als „heute erschienen“ und in der Dubletten-Warnung als Live-Titel.
10. **Zeitangaben:** Datenbank in UTC (wie überall). Die API nimmt `at` als Wandzeit Europe/Berlin im Format `YYYY-MM-DDTHH:MM` (Wert eines `datetime-local`-Feldes) und liefert UTC `Y-m-d H:i:s`. Die Oberfläche zeigt jede Zeit in Europe/Berlin (`timeZone: 'Europe/Berlin'`), unabhängig vom Gerät.
11. **Keine Reihenfolge von Hand** (diese Version): Vorziehen geht über „Fester Termin“ oder „Jetzt veröffentlichen“.
12. **Der Queue-Auftrag (Thema) gilt mit der Freigabe als erledigt**, auch wenn der Artikel später aus dem Plan genommen wird (`QueueService::markDone` bleibt beim `approve`, `unschedule` fasst den Auftrag nicht an).
13. **ADRs:** `028-zeitversetztes-veroeffentlichen-ohne-cron.md` (Entscheidungen 1–7, 9, 10) in Phase 1, `029-feed-und-sitemap-ueber-die-bruecke.md` (Entscheidung 8) in Phase 3. Nummern geprüft: höchste ADR auf Platte ist 026; `docs/planning/` reserviert keine Nummer ab 028.

Verworfen: externer Wecker (cron-job.org) — zusätzlicher Dienst, Inhaber wählte Besucher als Auslöser; nur Besucher ohne Feed — Feed und Suchmaschinen bekämen neue Artikel erst nach dem nächsten Besuch; verpasste Tage nachholen — erzeugt nach stillen Tagen eine Welle; Reihenfolge von Hand — mehr Oberfläche ohne genannten Bedarf.

## Overview

| Phase | Inhalt | Rating | Status |
|---|---|---|---|
| 1 | [Datenmodell und Fachlogik](phase-1-datenmodell-fachlogik.md): Migration 016, Status `scheduled`, `ScheduleService`/`ScheduleRepository`/`ScheduleClock`, Übergänge in `PostService`, Sperre für Agenten, Rotation und Dubletten, Einstellung `release_per_day`, ADR-028 | heikel | complete |
| 2 | [Auslöser und API](phase-2-ausloeser-api.md): `ScheduleTrigger` in `app/index.php`, Routen (Freigeben mit Zeitpunkt, Termin ändern, aus dem Plan nehmen, Plan lesen und einstellen), Übersicht, MCP-Status, Doku | standard | complete |
| 3 | [Feed und Sitemap über die Brücke](phase-3-feed-sitemap.md): `.htaccess`, `FeedController` mit `304`, `Response::file`, Dev-Router, ADR-029, Frontend-Regel | heikel | complete |
| 4 | [Oberfläche nach Entwurf](phase-4-oberflaeche.md): `redaktion/schedule.js`, Freigeben-Dialog, „Geplant“ auf der Startseite, Dialog „Veröffentlichungsplan“, Leiste des geplanten Artikels, Menüeintrag, CSS, Glossar und Code-Map | standard | complete |

Reihenfolge zwingend 1 → 2 → 3 → 4 (Phase 3 braucht den Auslöser aus Phase 2, Phase 4 die Routen aus Phase 2).

## Kontrakt

### Datenbank (Migration `016_schedule.sql`)

- `posts.status` ENUM `('draft', 'review', 'published', 'archived', 'scheduled')` — neuer Wert **am Ende**, damit MySQL die Tabelle nicht umkopieren muss.
- `posts.schedule_mode` ENUM `('slot', 'fixed')` NULL — wie der Artikel eingeplant ist bzw. war; bleibt nach dem Erscheinen stehen (zeigt „Warteschlange“/„fester Termin“), `NULL` = sofort.
- `posts.scheduled_at` DATETIME NULL — fester Termin (UTC), nur bei `fixed`.
- `posts.scheduled_since` DATETIME NULL — Zeitpunkt des Einplanens (UTC), Reihenfolge der Warteschlange.
- `posts.release_slot` DATETIME NULL — der belegte Erscheinungstermin (UTC), nur bei Warteschlange; Index `idx_posts_release_slot`.
- Einstellung `release_per_day` (Tabelle `settings`, JSON-Ganzzahl), Standard 2.

### PHP-Schnittstellen

- `Domain\Actor::schedule(): self` → Name `schedule`.
- `Domain\PostService::approve(int $id, Actor $actor, string $when = 'now', ?DateTimeImmutable $at = null): array`
- `Domain\PostService::reschedule(int $id, string $when, ?DateTimeImmutable $at, Actor $actor): array`
- `Domain\PostService::unschedule(int $id, Actor $actor): array`
- `Domain\PostService::release(int $id, ?string $slotUtc, Actor $actor): bool` (nur `ScheduleService`)
- `Domain\ScheduleService::plan(DateTimeImmutable $nowUtc): array` (Form wie `GET /dispatch/api/schedule`), `releaseDue(DateTimeImmutable $nowUtc): int`, `nextCheck(DateTimeImmutable $nowUtc): int`, `static parseAt(?string $at, DateTimeImmutable $nowUtc): DateTimeImmutable`, `static slotLabels(int $perDay): list<string>`.
- `Domain\ScheduleClock(string $storageDir)`: `read(): ?array{next_check: int, rebuild: bool}`, `write(int $nextCheck, bool $rebuild): void`, `forget(): void`.
- `Api\ScheduleTrigger::run(array $config): void` — wirft nie.

### Routen (alle JWT, Fehler `{"error","message"}` über `ApiErrors`)

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| POST | `/dispatch/api/posts/{id}/approve` | optional `{"when": "now"\|"slot"\|"fixed", "at": "YYYY-MM-DDTHH:MM"}` | `{post, rebuilt, rebuild_error}`; bei `scheduled` ist `rebuilt` `null` und `rebuild_error` `null` (kein Bau nötig) |
| POST | `/dispatch/api/posts/{id}/schedule` | `{"when", "at"}` | wie `approve` |
| POST | `/dispatch/api/posts/{id}/unschedule` | — | `{post}` |
| GET | `/dispatch/api/schedule` | — | Plan (unten) |
| PATCH | `/dispatch/api/schedule` | `{"per_day": 1–6}` | Plan (unten) |
| GET, HEAD | `/feed.xml`, `/sitemap.xml` | — | statische Datei, `304` bei passendem `If-None-Match`/`If-Modified-Since` (ohne Anmeldung) |

`post` (Zusammenfassung in `PostController::summary`) bekommt zusätzlich `schedule_mode`, `scheduled_at`. `GET /dispatch/api/overview` bekommt `scheduled` (Anzahl).

Plan-Antwort:

```json
{
  "per_day": 2,
  "times": ["08:00", "14:00"],
  "next_free": "2026-11-14 13:00:00",
  "items": [
    {"id": 12, "title": "…", "slug": "…", "mode": "slot", "expected_at": "2026-11-12 13:00:00", "position": 1, "due": false},
    {"id": 15, "title": "…", "slug": "…", "mode": "fixed", "expected_at": "2026-11-13 10:30:00", "position": null, "due": false}
  ],
  "released_today": [
    {"id": 9, "title": "…", "slug": "…", "published_at": "2026-11-12 07:00:00", "mode": "slot"}
  ]
}
```

`expected_at`, `next_free`, `published_at` in UTC `Y-m-d H:i:s`. `items` aufsteigend nach `expected_at`. `position` = Platz in der Warteschlange (1 = nächster), bei `fixed` `null`. `due: true` = Termin ist verstrichen, der Artikel erscheint mit dem nächsten Auslöser. `released_today` = heute (Berliner Kalendertag) erstmals veröffentlichte Artikel, `mode` aus `schedule_mode` (`null` = sofort).

## Finale Abnahmekriterien

- AK1: Freigeben mit `when = slot` setzt `scheduled`; der Artikel fehlt auf der Website, in Feed, Sitemap und Suchindex, bis sein Termin verstrichen ist und eine Anfrage die Brücke erreicht; danach ist er Live-Stand mit `published_at` = Zeitpunkt des Erscheinens.
- AK2: Mit `release_per_day = 2` erscheinen aus der Warteschlange höchstens zwei Artikel je Berliner Kalendertag, zu den Terminen aus Entscheidung 5 oder beim ersten Auslöser danach; Termine vergangener Tage werden nicht nachgeholt.
- AK3: Ein fester Termin erscheint zum ersten Auslöser ab `scheduled_at`, zusätzlich zum Tageslimit.
- AK4: Eine neue Fassung eines Live-Artikels geht beim Freigeben sofort online, unabhängig von `when`.
- AK5: Agenten können einen eingeplanten Artikel weder ändern noch wiederherstellen (`invalid_transition`) und sehen seinen Status `scheduled`.
- AK6: Zwei gleichzeitige Auslöser veröffentlichen keinen Artikel doppelt und belegen keinen Termin doppelt (Sperre `GET_LOCK` plus Statusprüfung auf gesperrter Zeile).
- AK7: Fällt die Fälligkeitsprüfung aus (Datenbank weg, Ausnahme), antwortet die eigentliche Anfrage trotzdem normal; der Fehler steht im Log.
- AK8: `/feed.xml` und `/sitemap.xml` liefern denselben Inhalt wie die gebauten Dateien, mit `ETag`/`Last-Modified`, `304` auf passende Bedingungen, `HEAD` ohne Rumpf; Nachbarordner (`/card/`, `/api/`, `/backend/`, `/scanner/`) bleiben unberührt.
- AK9: Die Oberfläche entspricht dem Entwurf (Struktur-Punkte je Bildschirm in Phase 4); ein Erstnutzer kann ohne Anleitung einplanen, Termin ändern, aus dem Plan nehmen, sofort veröffentlichen und das Tageslimit ändern; jede Einstellung und jede nicht triviale Wahl hat ein i-Symbol.
- AK10: `php -l` auf alle geänderten PHP-Dateien fehlerfrei (ADR-004: keine automatisierten Tests); `docs/models.md`, `docs/routes.md`, `docs/code-map.md`, `docs/glossary.md`, `docs/PROJECT.md`, `docs/conventions/frontend.md`, ADR-012 (Verweis), ADR-028, ADR-029 nachgezogen.

## Konfidenz-Ausweis

- 🟡 Rewrite von `/feed.xml` auf die Brücke bei Strato: dieselbe Regelform wie `.well-known` (läuft dort), aber für eine Datei, die physisch existiert. Check nach dem Deploy: `curl -sI https://quantum-canvas.de/feed.xml` muss den `ETag` im Format der Brücke (`"d-…"`) zeigen, nicht den von Apache; ein zweiter Aufruf mit `-H 'If-None-Match: <etag>'` muss `304` liefern.
- 🟡 Ein Neubau im Zähl-Aufruf dauert auf Strato Sekunden. Der Besucher merkt es nicht (`sendBeacon` wartet nicht auf die Antwort), aber zwei Bauten können sich treffen (`Builder` wartet 10 s auf seine Sperre, ADR-021). Abgefangen durch `rebuild: true` in `next.json`. Check in der Umsetzung: zwei Freigaben mit festem Termin in derselben Minute, dann zwei parallele `curl`-Aufrufe auf `/dispatch/api/hit`; danach `storage/schedule/next.json` und `storage/logs/` ansehen.
- 🟡 Sommer-/Winterzeit: Termine werden je Tag in Europe/Berlin erzeugt und nach UTC umgerechnet (`DateTimeImmutable`), nicht als feste UTC-Versätze. Check in Phase 1: `php -r` mit `ScheduleService::slotsOn` für den 2027-03-28 und den 2027-10-31 — die UTC-Zeiten müssen sich um eine Stunde gegenüber dem Vortag verschieben.

Fehlerklassen geprüft: Im Vault gibt es für die beteiligte Technik nur `systeme/strato-shared-hosting.md` und `frameworks/a11y.md`; beide haben keinen Abschnitt „Fehlerklassen“, Entities für PHP und MySQL fehlen.

Nebenbefund: Zeile 1 von `app/theme/redaktion/redaktion.css` ist doppelt kodiert („fÃ¼r“, Bytes `C3 83 C2 BC`). Nur ein Kommentar, ohne Wirkung; Reparatur steht als Aufgabe in Phase 4.

## Summary

Freigegebene Artikel können zeitversetzt erscheinen: auf einen festen Termin oder über eine Warteschlange mit einstellbarem Tageslimit (1–6, Standard 2). Ohne Cron: jede Anfrage an die Brücke prüft die Fälligkeit; Feed und Sitemap laufen dafür über die Brücke. Der Redaktionsmodus hat Freigeben-Dialog, „Geplant“, Dialog „Veröffentlichungsplan“ und Leiste am geplanten Artikel.

## Files touched

Domain (`PostService`, `ScheduleService`, `ScheduleRepository`, `ScheduleClock`), Migration 016, `Api\ScheduleTrigger`, `ScheduleController`, `FeedController`, `.htaccess`, `theme/redaktion/` (`schedule.js`, `post.js`, `konto.js`, `redaktion.css`), ADR-028, ADR-029, `docs/`.

## Commits

8e2c61c (Phase 1), 2131b39 (Phase 2), 9fdae46 (Phase 3), Phase 4 siehe `git log`.

## Deviations from plan

Phase 1: Migrationsnummer 016, ADR-Nummern 028/029. Phase 4: siehe dort (`meldeFreigabe`, `leiste` ohne `onReady`).

## Follow-ups

- Nach dem Deploy: P3-AK5 prüfen (`curl -sI https://quantum-canvas.de/feed.xml`: ETag der Brücke, `304` auf `If-None-Match`).
- Konfidenz-Checks aus dem Plan (zwei parallele Auslöser-Aufrufe, Sommer-/Winterzeit) auf dem Server beobachten.
- Offen im Browser: Zustand „viele“ und Server-Fehler im Plan-Dialog.
