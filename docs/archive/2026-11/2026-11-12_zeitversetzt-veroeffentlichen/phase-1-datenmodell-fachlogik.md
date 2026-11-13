# Phase 1 — Datenmodell und Fachlogik (heikel)

Ziel: Der Status `scheduled`, die Plan-Spalten, die Termin-Rechnung und das Freigeben fälliger Artikel stehen in `Domain/`, ohne HTTP. Danach lässt sich alles per `php -r` gegen die lokale Datenbank prüfen; Routen und Oberfläche folgen in Phase 2 und 4.

## Kontext (vor dem Start lesen)

- [`README.md`](README.md) dieses Plans: Entscheidungen 1–13, Kontrakt.
- `docs/decisions/012-artikel-statusmaschine-live-lesepfad.md` (Übergänge, Live-Lesepfad), `021-redaktions-api.md` (Neubau synchron), `022-zaehl-aufruf.md` (Datei in `storage/` statt Datenbank, ohne Cron).
- `docs/conventions/php.md`.
- `app/migrations/005_post.sql`, `001_setting.sql`; `app/src/Db/Migrator.php` (wie Migrationen laufen), `app/src/Db/Transaction.php`.
- `app/src/Domain/PostService.php`: `approve`, `update`, `restoreRevision`, `discard`, `lockedPost`, `queueAfter`, `requireOwner`, `invalidTransition`, `withWorkflowFields`, `validate` (ruft `liveTitles`).
- `app/src/Domain/PostRepository.php`: `LIST_COLUMNS`, `setStatus`, `liveTitles`, `findForUpdate`.
- `app/src/Domain/SettingService.php` (`PUBLIC_KEYS`, `DEFAULTS`, `set`, `write`, `normalize`, `integerInRange`).
- `app/src/Domain/BriefingRepository.php` (`liveStatsByCategory`), `app/src/Domain/Actor.php`.
- `app/src/Domain/StatsRecorder.php`, Abschnitt Salt-Datei (`storage_dir`, `mkdir`, `flock`) — Muster für `ScheduleClock`.
- `app/src/Site/Builder.php`, Methoden um `GET_LOCK` (`LOCK_NAME = 'dispatch_build'`) — Muster für die Sperre.
- Fehlerklassen: keine einschlägig (README → Konfidenz-Ausweis).

## Abnahmekriterien der Phase

- P1-AK1: `php bin/dispatch migrate` läuft lokal durch; `SHOW CREATE TABLE posts` zeigt den ENUM-Wert `scheduled` am Ende, die Spalten `schedule_mode`, `scheduled_at`, `scheduled_since`, `release_slot` und den Index `idx_posts_release_slot`.
- P1-AK2: `PostService::approve` verhält sich nach der Tabelle in Entscheidung 1: `slot`/`fixed` → `scheduled` mit gesetzten Plan-Spalten; `now` oder gesetzte `published_revision` → `published` wie bisher; `fixed` ohne `at` oder mit `at` in der Vergangenheit → `invalid_value` (Feld `at`); unbekanntes `when` → `invalid_value` (Feld `when`).
- P1-AK3: `update` und `restoreRevision` auf einen `scheduled`-Artikel werfen `invalid_transition` (auch für den Inhaber); `discard` durch den Inhaber geht.
- P1-AK4: `ScheduleService::slotLabels(n)` liefert für n = 1 … 6 exakt die Tabelle aus Entscheidung 5.
- P1-AK5: `releaseDue` veröffentlicht fällige feste Termine und je verstrichenem, freiem Tagestermin höchstens einen Warteschlangen-Artikel, dessen `scheduled_since` nicht nach dem Termin liegt; Termine vergangener Tage bleiben unberührt; jeder Termin wird höchstens einmal belegt.
- P1-AK6: Sommer-/Winterzeit: Für den 2027-03-28 liegt 08:00 Berlin bei 06:00 UTC, am 2027-03-27 bei 07:00 UTC; für den 2027-10-31 bei 07:00 UTC, am 2027-10-30 bei 06:00 UTC.
- P1-AK7: `release_per_day`: Inhaber setzt direkt (1–6), Agenten erzeugen einen Änderungsvorschlag, Werte außerhalb 1–6 → `invalid_value`.
- P1-AK8: Ein eingeplanter Artikel zählt in `liveStatsByCategory` (Tage seit dem letzten Artikel = 0 für seine Kategorien) und sein Titel in `liveTitles`.
- P1-AK9: `docs/decisions/028-zeitversetztes-veroeffentlichen-ohne-cron.md` existiert; ADR-012 verweist darauf.

## Checkliste

### Datenbank

- [x] `app/migrations/016_schedule.sql` anlegen, genau eine Anweisung:
  ```sql
  ALTER TABLE posts
      MODIFY status ENUM('draft', 'review', 'published', 'archived', 'scheduled') NOT NULL DEFAULT 'draft',
      ADD COLUMN schedule_mode ENUM('slot', 'fixed') NULL AFTER published_at,
      ADD COLUMN scheduled_at DATETIME NULL AFTER schedule_mode,
      ADD COLUMN scheduled_since DATETIME NULL AFTER scheduled_at,
      ADD COLUMN release_slot DATETIME NULL AFTER scheduled_since,
      ADD INDEX idx_posts_release_slot (release_slot);
  ```
- [x] Lokal `php bin/dispatch migrate`, dann P1-AK1 prüfen.

### Akteur und Einstellung

- [x] `Domain/Actor.php`: `public static function schedule(): self { return new self('schedule'); }` mit Docblock „Das System, wenn ein eingeplanter Artikel zu seinem Termin erscheint (ADR-028).“
- [x] `Domain/SettingService.php`: `'release_per_day'` an `PUBLIC_KEYS` anhängen (Agenten → Vorschlag), in `DEFAULTS` `'release_per_day' => 2`, in `normalize()` neben `posts_per_page`: `case 'release_per_day': return $this->integerInRange($name, $value, 1, 6);`. In `write()` nach der Schleife: wenn `release_per_day` unter den geschriebenen Namen ist → `(new ScheduleClock((string) $this->config['storage_dir']))->forget();`.

### Repository

- [x] `Domain/PostRepository.php`:
  - `LIST_COLUMNS` um `'schedule_mode', 'scheduled_at'` ergänzen (nach `'published_at'`).
  - `setStatus`: erlaubte Spalten um `'schedule_mode', 'scheduled_at', 'scheduled_since', 'release_slot'` erweitern; Docblock entsprechend.
  - `liveTitles`: nach der bestehenden Schleife eine zweite Abfrage `SELECT title FROM posts WHERE status = 'scheduled' AND id <> ?` (Parameter `$exceptId ?? 0`) und die Titel anhängen. Docblock: „Titel der Live-Stände plus eingeplanter Artikel; deren Arbeitsstand ist der freigegebene, weil eingeplante Artikel gesperrt sind (ADR-028).“
- [x] `Domain/BriefingRepository.php` `liveStatsByCategory`: Join-Bedingung zu `AND ((p.published_revision IS NOT NULL AND p.status <> 'archived') OR p.status = 'scheduled')`, `MAX(p.published_at)` an beiden Stellen durch `MAX(COALESCE(p.published_at, UTC_TIMESTAMP()))` ersetzen. Docblock: „Live und eingeplant; ein eingeplanter Artikel zählt als heute erschienen.“
- [x] Neue Datei `Domain/ScheduleRepository.php` (`final class`, Konstruktor `private readonly PDO $pdo`), Methoden, alle Zeiten als UTC-Strings `Y-m-d H:i:s`:
  - `waitingSlots(): list<array{id: int, title: string, slug: string, scheduled_since: string}>` — `SELECT id, title, slug, scheduled_since FROM posts WHERE status = 'scheduled' AND schedule_mode = 'slot' ORDER BY scheduled_since, id`.
  - `waitingFixed(): list<array{id: int, title: string, slug: string, scheduled_at: string}>` — dasselbe mit `schedule_mode = 'fixed' ORDER BY scheduled_at, id`.
  - `takenSlots(string $fromUtc, string $toUtc): list<string>` — `SELECT release_slot FROM posts WHERE release_slot >= ? AND release_slot < ?`.
  - `releasedBetween(string $fromUtc, string $toUtc): list<array{id: int, title: string, slug: string, published_at: string, mode: string|null}>` — `SELECT id, title, slug, published_at, schedule_mode AS mode FROM posts WHERE published_revision IS NOT NULL AND published_at >= ? AND published_at < ? ORDER BY published_at, id`.
  - `lock(): bool` — `SELECT GET_LOCK('dispatch_schedule_release', 0)` → `=== 1`; `unlock(): void` — `SELECT RELEASE_LOCK('dispatch_schedule_release')`.
  - `id` jeweils als `int` casten.

### Datei mit dem nächsten Prüfzeitpunkt

- [x] Neue Datei `Domain/ScheduleClock.php` (`final class`, Konstruktor `private readonly string $storageDir`), Pfad `$storageDir . '/schedule/next.json'`, Inhalt `{"next_check": <Unix-Sekunden>, "rebuild": <bool>}`:
  - `read(): ?array` — fehlt die Datei oder ist sie kein gültiges Objekt mit `next_check` (int) → `null`; sonst `['next_check' => int, 'rebuild' => (bool) ($data['rebuild'] ?? false)]`. Kein Datenbankzugriff.
  - `write(int $nextCheck, bool $rebuild): void` — Ordner mit `mkdir(…, 0775, true)` anlegen, falls nötig; `file_put_contents(…, json_encode(…), LOCK_EX)`.
  - `forget(): void` — `@unlink`, wenn vorhanden. Klassen-Docblock: „Schnellprüfung ohne Datenbank (ADR-028): Jede Anfrage liest nur diese Datei. Löschen erzwingt eine Neuberechnung beim nächsten Auslöser.“

### Übergänge in `PostService`

- [x] Konstante `private const WHEN = ['now', 'slot', 'fixed'];` und Hilfsmethode `private function assertWhen(string $when, ?DateTimeImmutable $at): void` — unbekanntes `when` → `invalid` mit Feld `when` und Text „muss now, slot oder fixed sein“; `fixed` ohne `$at` → Feld `at`, „ist bei einem festen Termin Pflicht“. (Zeitpunkt in der Vergangenheit prüft `ScheduleService::parseAt`.)
- [x] Hilfsmethode `private function forgetSchedule(): void { (new ScheduleClock((string) $this->config['storage_dir']))->forget(); }`.
- [x] `approve(int $id, Actor $actor, string $when = 'now', ?DateTimeImmutable $at = null): array`:
  - `requireOwner` wie bisher, dann `assertWhen`.
  - In der Transaktion nach `assertFormOk`: Ist `$post['published_revision'] !== null` **oder** `$when === 'now'` → bisheriger Zweig unverändert (`status => published`, …). Sonst `setStatus($id, ['status' => 'scheduled', 'review_note' => null, 'schedule_mode' => $when, 'scheduled_at' => $when === 'fixed' ? $at->format('Y-m-d H:i:s') : null, 'scheduled_since' => gmdate('Y-m-d H:i:s')])`. `$at` ist bereits UTC (kommt aus `parseAt`).
  - `queueAfter($post, 'markDone')` in beiden Zweigen (Entscheidung 12). Audit `post.approve` mit `['revision' => …, 'when' => $published ? 'now' : $when, 'at' => …|null]`.
  - Nach der Transaktion (also nach `Transaction::join`, vor `return`) `forgetSchedule()`, wenn eingeplant wurde. Dafür das Ergebnis von `Transaction::join` in einer Variablen halten.
- [x] `reschedule(int $id, string $when, ?DateTimeImmutable $at, Actor $actor): array`: `requireOwner($actor, 'neu einplanen')`, `assertWhen`; Transaktion, `lockedPost`; Status ≠ `scheduled` → `invalidTransition($post, 'reschedule')`.
  - `now` → `setStatus(['status' => 'published', 'published_revision' => (int) $post['revision'], 'touch_published_at' => true, 'schedule_mode' => null])`.
  - `slot` → ist schon `slot`: nichts schreiben (kein Audit); sonst `['schedule_mode' => 'slot', 'scheduled_at' => null, 'scheduled_since' => gmdate('Y-m-d H:i:s')]` (ans Ende der Warteschlange).
  - `fixed` → `['schedule_mode' => 'fixed', 'scheduled_at' => $at->format('Y-m-d H:i:s')]`.
  - Audit `post.reschedule` mit `when`, `at`. Nach der Transaktion `forgetSchedule()`. Rückgabe `$this->get($id)`.
- [x] `unschedule(int $id, Actor $actor): array`: `requireOwner($actor, 'aus dem Plan nehmen')`; Transaktion, `lockedPost`; Status ≠ `scheduled` → `invalidTransition($post, 'unschedule')`; `setStatus(['status' => 'review', 'schedule_mode' => null, 'scheduled_at' => null, 'scheduled_since' => null])`; Audit `post.unschedule`; danach `forgetSchedule()`; Rückgabe `get`.
- [x] `release(int $id, ?string $slotUtc, Actor $actor): bool`: Docblock „Nur für `ScheduleService::releaseDue`. Keine erneute Formprüfung: Der Inhalt ist seit der Freigabe gesperrt.“ Transaktion, `lockedPost`; Status ≠ `scheduled` → `false` (ein paralleler Auslöser war schneller). Sonst `setStatus(['status' => 'published', 'published_revision' => (int) $post['revision'], 'touch_published_at' => true, 'release_slot' => $slotUtc])`, Audit `post.release` mit `['revision', 'mode' => $post['schedule_mode'], 'slot' => $slotUtc]`, `true`.
- [x] `update()`: direkt nach der `archived`-Prüfung: Status `scheduled` → `throw new DomainError('invalid_transition', 'Der Artikel ist eingeplant und wartet auf seinen Termin. Ändern geht erst, wenn der Inhaber ihn aus dem Plan nimmt.', ['id' => $id, 'status' => 'scheduled', 'action' => 'update']);`. `restoreRevision` läuft über `update` und ist damit mit abgedeckt.
- [x] `discard()`: nach dem Löschen, wenn `$post['status'] === 'scheduled'` → nach der Transaktion `forgetSchedule()`.
- [x] `withWorkflowFields`: `'schedule_mode' => $post['schedule_mode'], 'scheduled_at' => $post['scheduled_at']` ergänzen.
- [x] `use DateTimeImmutable;` ergänzen.

### `ScheduleService`

- [x] Neue Datei `Domain/ScheduleService.php` (`final class`), Konstruktor `(private readonly PDO $pdo, private readonly array $config)`, erzeugt `ScheduleRepository`, `PostService`, `SettingService`.
  - Konstanten: `ZONE = 'Europe/Berlin'`, `CHECK_CAP_SECONDS = 900`, `RETRY_SECONDS = 60`, `MAX_DAYS_AHEAD = 365`, `SLOT_MINUTES = [1 => [480], 2 => [480, 840], 3 => [480, 720, 960], 4 => [480, 660, 840, 1020], 5 => [480, 630, 765, 915, 1050], 6 => [480, 600, 720, 840, 960, 1080]]` (Minuten nach Mitternacht, Tabelle aus Entscheidung 5).
  - `public static function slotLabels(int $perDay): array` → `['08:00', '14:00']` aus `SLOT_MINUTES`.
  - `public function perDay(): int` → `(int) $this->settings->get('release_per_day')`.
  - `private function dayStart(DateTimeImmutable $utc): DateTimeImmutable` → derselbe Zeitpunkt in `ZONE`, `setTime(0, 0)` (bleibt in `ZONE`).
  - `public function slotsOn(DateTimeImmutable $berlinDay): array` → `list<DateTimeImmutable>` in UTC: je Minutenwert `$berlinDay->setTime(intdiv($m, 60), $m % 60)->setTimezone(new DateTimeZone('UTC'))`. **Nicht** mit festen Versätzen rechnen (Sommerzeit, P1-AK6).
  - `public static function parseAt(?string $at, DateTimeImmutable $nowUtc): DateTimeImmutable` — `$at` muss `^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$` treffen, sonst `DomainError('invalid_value', 'at: muss Datum und Uhrzeit im Format JJJJ-MM-TTTHH:MM sein', ['field' => 'at'])`; als Wandzeit in `ZONE` lesen (`DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $at, new DateTimeZone(self::ZONE))`), nach UTC; `<= $nowUtc` → `invalid_value` „at: liegt in der Vergangenheit“; mehr als `MAX_DAYS_AHEAD` Tage voraus → `invalid_value` „at: höchstens ein Jahr im Voraus“.
  - `public function plan(DateTimeImmutable $nowUtc): array` — Form wie die Plan-Antwort im README-Kontrakt. Ablauf:
    1. `$perDay`, `$today = dayStart($nowUtc)`, `$tomorrow = $today->modify('+1 day')`, beide als UTC-Strings für die Abfragen.
    2. `$taken = takenSlots(today, tomorrow)`; `$waiting = waitingSlots()`; `$i = 0`; `$items = []`; `$nextFree = null`.
    3. Für Tag `d = 0, 1, 2, …` (Abbruch spätestens bei `MAX_DAYS_AHEAD`): für jeden Termin `s` aus `slotsOn($today->modify("+$d day"))`:
       - `d === 0` und `s` in `$taken` → überspringen.
       - `d === 0` und `s <= $nowUtc` → wenn `$i < count($waiting)` und `waiting[$i]['scheduled_since'] <= s`: Eintrag mit `due: true`, `$i++`; in jedem Fall weiter (ein verstrichener Termin ist nie `next_free`).
       - sonst: wenn `$i < count($waiting)` → Eintrag (`mode: slot`, `expected_at: s`, `position: $i + 1`, `due: false`), `$i++`; sonst `$nextFree = s` und beide Schleifen beenden.
    4. Feste Termine aus `waitingFixed()` als Einträge (`mode: fixed`, `expected_at: scheduled_at`, `position: null`, `due: scheduled_at <= now`).
    5. `items` nach `expected_at` sortieren (`usort`, Gleichstand nach `id`); `released_today = releasedBetween(today, tomorrow)`; `times = slotLabels($perDay)`.
  - `public function releaseDue(DateTimeImmutable $nowUtc): int`:
    1. `if (!$this->schedule->lock()) return 0;` dann `try { … } finally { $this->schedule->unlock(); }`.
    2. Feste: für jeden Eintrag aus `waitingFixed()` mit `scheduled_at <= now`: `posts->release(id, null, Actor::schedule())`, Zähler bei `true`.
    3. Warteschlange, nur heute: `$taken`, `$waiting`, `$i = 0`; für jeden Termin `s` aus `slotsOn($today)` in Reihenfolge: `s > now` → abbrechen; `s` in `$taken` → weiter; `$i >= count($waiting)` → abbrechen; `waiting[$i]['scheduled_since'] > s` → weiter (Termin verfällt); sonst `posts->release(waiting[$i]['id'], s als UTC-String, Actor::schedule())`, Zähler bei `true`, `$i++`.
    4. Anzahl zurückgeben.
  - `public function nextCheck(DateTimeImmutable $nowUtc): int` → `min($nowUtc->getTimestamp() + CHECK_CAP_SECONDS, frühestes expected_at aller items aus plan($nowUtc) als Timestamp)`; ohne Einträge nur der Deckel.
  - Klassen-Docblock: drei Sätze aus Entscheidungen 5–7, Verweis ADR-028.
- [x] Gegenprobe P1-AK4, P1-AK5, P1-AK6 per `php -r` gegen die lokale Datenbank (Wegwerf-Skript im Scratchpad, nicht im Repo; ADR-004): zwei Artikel einreichen und mit `slot` freigeben, `releaseDue` mit einem künstlichen `$nowUtc` heute 08:05 Berlin → genau einer erscheint; erneut → keiner; mit 14:05 → der zweite.

### Doku und ADR

- [x] `docs/decisions/028-zeitversetztes-veroeffentlichen-ohne-cron.md` (Format wie ADR-026): Kontext (kein Cron, statische Website), Entscheidung (Status `scheduled`, Termine, Zuteilung, Auslöser an der Brücke, `next.json` mit 15-Minuten-Deckel, Akteur `schedule`, Sperre für Agenten), Verworfen (externer Wecker, Nachholen verpasster Tage, Reihenfolge von Hand), Konsequenzen (Erscheinen hängt am Verkehr; ohne Anfrage kein Erscheinen).
- [x] ADR-012 unter „Entscheidung“ eine Zeile: „Ergänzt durch ADR-028: `approve` kann einplanen (`scheduled`), das Erscheinen löst `release` aus.“
- [x] `docs/models.md`: `posts` — ENUM-Wert und vier Spalten mit einer Zeile Bedeutung; Abschnitt „Einstellungen“ — Zeile `release_per_day` (öffentlich/Vorschlag, Standard `2`, Ganzzahl 1–6); Tabelle „Was öffentlich ist“ nachziehen.

## Report-Back

Status: complete (2026-11-13). Alle P1-AK per Wegwerf-Skript gegen die lokale Datenbank geprüft (in einer Transaktion, am Ende zurückgerollt): 90 Prüfungen grün, `php -l` fehlerfrei.

Abweichungen vom Plan:
- Migration heißt `016_schedule.sql` (015 ist `author_model`), die ADR `028-…` (027 ist „Refresh-Token“). Die ADR für Feed und Sitemap in Phase 3 heißt dadurch `029`; README und Phasen-Dateien sind nachgezogen.
- `parseAt` prüft zusätzlich per Rundlauf, dass Datum und Uhrzeit existieren (31. Februar, ausgefallene Stunde der Zeitumstellung → `invalid_value`).
- `reschedule` auf `now` lässt `scheduled_at`/`scheduled_since` stehen (nur `schedule_mode` wird `NULL`); das Erscheinen setzt `published_at`.

Für Phase 2: `ScheduleService::releaseDue` gibt die Anzahl zurück; `nextCheck` liefert auch einen Zeitpunkt in der Vergangenheit, wenn etwas fällig ist (der Auslöser muss das als „sofort prüfen“ lesen und nach dem Freigeben neu schreiben).
