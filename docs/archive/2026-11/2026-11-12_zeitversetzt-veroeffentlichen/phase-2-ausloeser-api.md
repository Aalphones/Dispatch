# Phase 2 — Auslöser und API (standard, complete)

Ziel: Jede Anfrage an die Brücke prüft, ob etwas fällig ist, und der Inhaber kann per API einplanen, umplanen, zurücknehmen und das Tageslimit setzen. Agenten sehen den neuen Status.

**Vor dem Start:** `git -C <repo> status` muss sauber sein. Die Themenliste „Vorschläge“ (Commit `d0e92bc`, 2026-11-12) hat `app/index.php`, `app/theme/redaktion/konto.js`, `post.js`, `docs/routes.md` und `QueueController` geändert — auf diesem Stand aufsetzen; Einfügestellen über Symbole suchen, nicht über Zeilennummern.

## Kontext (vor dem Start lesen)

- [`README.md`](README.md): Entscheidungen 2, 3, 7, 9, 10 und Kontrakt (Routen, Plan-Antwort).
- Phase 1 im Code: `Domain/ScheduleService.php`, `ScheduleClock.php`, `PostService::approve|reschedule|unschedule`.
- `app/index.php` (Router, Reihenfolge der Routen), `app/src/Api/PostController.php` (`approve`, `unpublish`, `summary`, `STATUSES`, `posts()`), `Api/OverviewController.php`, `Api/Rebuild.php`, `Api/ApiErrors.php`, `Api/CategoryController.php` (`patch`: JSON-Body lesen, Muster).
- `app/src/Http/Request.php` (`json()` wirft bei leerem Body), `Http/Response.php`.
- `app/src/Mcp/Tools/PostTools.php` (`STATUSES`, Beschreibungen von `get_post`/`list_posts`/`update_post`), `app/mcp/instructions.md`, `workflow-scheduled.md`, `workflow-queue.md`.
- `app/src/Util/Log.php`.
- `docs/routes.md` (Tabellenformat).

## Abnahmekriterien der Phase

- P2-AK1: Ist in `storage/schedule/next.json` ein Zeitpunkt in der Zukunft eingetragen und `rebuild` falsch, öffnet eine Anfrage an die Brücke keine Datenbankverbindung für die Prüfung (Code-Blick: frühes `return` vor `Connection::open`).
- P2-AK2: Fehlt die Datei oder ist der Zeitpunkt erreicht, ruft die Anfrage `releaseDue` auf, baut bei mindestens einem erschienenen Artikel neu und schreibt die Datei neu; eine Ausnahme darin landet im Log, die Anfrage läuft normal weiter.
- P2-AK3: Die fünf Routen aus dem Kontrakt antworten wie dort beschrieben; ohne Anmeldung 401; `approve` ohne Body verhält sich wie bisher (sofort).
- P2-AK4: `GET /dispatch/api/overview` enthält `scheduled`.
- P2-AK5: `list_posts` akzeptiert `status = scheduled`; `get_post` zeigt `status`, `schedule_mode`, `scheduled_at`; `update_post` auf einen eingeplanten Artikel liefert die Meldung aus Phase 1.
- P2-AK6: `docs/routes.md`, `docs/code-map.md` nachgezogen.

## Checkliste

### Auslöser

- [x] Neue Datei `app/src/Api/ScheduleTrigger.php` (`final class`), `public static function run(array $config): void`, Docblock „Fälligkeitsprüfung bei jeder Anfrage an die Brücke (ADR-028). Wirft nie: Ein Fehler hier darf die eigentliche Anfrage nicht stören.“ Ablauf, alles in `try { … } catch (Throwable $error) { Log::error('Fälligkeitsprüfung fehlgeschlagen', ['type' => $error::class, 'message' => $error->getMessage()]); }`:
  1. `$clock = new ScheduleClock((string) $config['storage_dir']); $state = $clock->read(); $now = time();`
  2. `if ($state !== null && $state['next_check'] > $now && !$state['rebuild']) { return; }`
  3. `$pdo = Connection::open(); $service = new ScheduleService($pdo, $config); $nowUtc = new DateTimeImmutable('@' . $now);` (Zeitzone UTC).
  4. `$released = $service->releaseDue($nowUtc);`
  5. `$rebuildOk = true; if ($released > 0 || ($state['rebuild'] ?? false)) { $rebuildOk = Rebuild::run($config, $pdo)['rebuilt']; }`
  6. `$next = $service->nextCheck($nowUtc); if ($released === 0 && $next <= $now) { $next = $now + ScheduleService::RETRY_SECONDS; } if (!$rebuildOk) { $next = min($next, $now + 300); }` (300 als Konstante `REBUILD_RETRY_SECONDS` in der Klasse).
  7. `$clock->write($next, !$rebuildOk);`
- [x] `app/index.php`: direkt vor `$router->dispatch($request);` die Zeile `ScheduleTrigger::run($config);` mit Kommentar „Eingeplante Artikel erscheinen beim ersten Aufruf ab ihrem Termin (ADR-028).“ und `use Dispatch\Api\ScheduleTrigger;`.

### API

- [x] `Api/PostController.php`:
  - `STATUSES` um `'scheduled'` ergänzen.
  - Private Hilfe `private function readWhen(Request $request): array` → `[string $when, ?DateTimeImmutable $at]`: Body leer (`trim($request->body()) === ''`) → `['now', null]`; sonst `$input = $request->json()`, `$when = (string) ($input['when'] ?? 'now')`, `$at = isset($input['at']) ? ScheduleService::parseAt((string) $input['at'], new DateTimeImmutable('now', new DateTimeZone('UTC'))) : null`. Fehler fliegen als `DomainError` und laufen über `ApiErrors::respond` (Aufruf innerhalb des bestehenden `try`).
  - `approve`: `[$when, $at] = $this->readWhen($request);` dann `$this->posts()->approve($id, Actor::owner(), $when, $at)`. Antwort: Ist `$post['status'] === 'scheduled'` → `Response::json(['post' => $this->summary($post), 'rebuilt' => null, 'rebuild_error' => null])`, sonst wie bisher mit `Rebuild::run`.
  - Neue Methode `schedule(Request $request, int $id): never` — `ApiAuth::owner`, `readWhen`, `reschedule($id, $when, $at, Actor::owner())`, Antwort wie `approve`.
  - Neue Methode `unschedule(Request $request, int $id): never` — `ApiAuth::owner`, `unschedule($id, Actor::owner())`, `Response::json(['post' => $this->summary($post)])`.
  - `summary`: `'schedule_mode' => $post['schedule_mode'] ?? null, 'scheduled_at' => $post['scheduled_at'] ?? null` ergänzen.
- [x] Neue Datei `Api/ScheduleController.php` (`final class`, Konstruktor `private readonly array $config`):
  - `show(Request $request): never` — `ApiAuth::owner`, `Response::json((new ScheduleService(Connection::open(), $this->config))->plan(new DateTimeImmutable('now', new DateTimeZone('UTC'))))`.
  - `patch(Request $request): never` — `ApiAuth::owner`; `$input = $request->json()`; `per_day` fehlt → `invalid_value` (Feld `per_day`); `(new SettingService($pdo, $this->config))->set(['release_per_day' => $input['per_day']], Actor::owner())`; danach wie `show` (eine Verbindung für beides). Fehler über `ApiErrors::respond`.
- [x] `app/index.php` Routen ergänzen (bei den übrigen `posts/{id}/…`-Routen):
  - `POST /dispatch/api/posts/{id}/schedule` → `PostController::schedule`
  - `POST /dispatch/api/posts/{id}/unschedule` → `PostController::unschedule`
  - `GET /dispatch/api/schedule` → `ScheduleController::show`
  - `PATCH /dispatch/api/schedule` → `ScheduleController::patch`
- [x] `Api/OverviewController.php`: `'scheduled' => $posts->countByStatus()['scheduled'] ?? 0` (das Ergebnis von `countByStatus()` einmal in eine Variable holen).

### MCP und Texte für Agenten

- [x] `Mcp/Tools/PostTools.php`: `STATUSES` um `'scheduled'`. In der Beschreibung von `list_posts`/`get_post` einen Satz: „`scheduled` = vom Inhaber freigegeben und eingeplant; erscheint zu einem Termin (`scheduled_at` bei festem Termin, sonst in der Warteschlange).“ In `update_post`: „Eingeplante Artikel (`scheduled`) lassen sich nicht ändern.“
- [x] `app/mcp/workflow-scheduled.md` und `workflow-queue.md`: an den Satz zur Freigabe anhängen: „Nach der Freigabe kann der Artikel eingeplant sein (`scheduled`); er erscheint dann zu seinem Termin und ist bis dahin gesperrt.“ (Diese Dateien hat die parallele Arbeit geändert — auf deren Stand aufsetzen.)

### Doku

- [x] `docs/routes.md`: fünf Zeilen (vier neue Routen, `approve` mit Body und `rebuilt: null` bei Einplanung), Phase-Spalte `Plan 2026-11-12`.
- [x] `docs/code-map.md`: Namensschema-Liste um `schedule` ergänzen; Zeile `src/Domain/` um `ScheduleService`, `ScheduleRepository`, `ScheduleClock` (Termine, Freigeben fälliger Artikel, `storage/schedule/next.json`); `src/Api/` um `ScheduleController`, `ScheduleTrigger` (Fälligkeitsprüfung je Anfrage); `storage/` um `schedule/next.json`; `index.php`-Zeile: „ruft vor dem Router `ScheduleTrigger::run`“.
- [x] `php -l` auf alle geänderten PHP-Dateien.

## Report-Back

Umgesetzt wie geplant. `RETRY_SECONDS` (60 s) steht als Konstante in `ScheduleTrigger`, nicht in `ScheduleService` (dort gab es sie nicht). `php -l` auf alle geänderten PHP-Dateien fehlerfrei.
