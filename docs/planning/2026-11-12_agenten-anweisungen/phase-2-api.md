# Phase 2 — API (standard)

Ziel: Sechs Routen für den Redaktionsmodus liefern die Gesamtansicht aller Anweisungen, die Vorschau, das Speichern mit Konfliktprüfung, den Verlauf, eine Fassung mit Vergleich und das Wiederherstellen.

## Kontext (vor dem Start lesen)

- [`README.md`](README.md): Entscheidungen 2, 3, 4, 7; Kontrakt (Routen, Gesamtansicht, Begründung für `{slug}` und `PATCH`).
- Phase 1, Report-Back (was tatsächlich entstanden ist).
- `app/src/Api/CategoryController.php` (Muster: `ApiAuth::owner`, `$request->json()`, `DomainError` → `ApiErrors::respond`, `Response::json`), `app/src/Api/ApiErrors.php` (`revision_conflict` → 409 ohne Änderung), `app/src/Http/Router.php` (Platzhalter `{id}` als `int`, `{slug}` als Text), `app/index.php` (Routen), `app/src/Http/Request.php`, `Response.php`.
- `app/src/Mcp/McpController.php` (`services`, `registry`), `Registry.php` (`all`, `groupOf`), `Tool.php` (`describe`), `Prompts.php` (`catalog`), `app/mcp/*.md`, `app/config.php` (`form_rules`).
- `docs/routes.md` (Spalten Methode, Pfad, Auth, Controller, Phase).
- Fehlerklassen: keine einschlägig.

## Abnahmekriterien der Phase

- P2-AK1 `GET /dispatch/api/instructions` liefert die Gesamtansicht genau in der Form des Kontrakts; ohne Anmeldung 401 wie jede Route mit `ApiAuth::owner`.
- P2-AK2 `PATCH …/{slug}` mit passender `base_revision` speichert und antwortet `{"document": …}` in der Form eines Eintrags aus `documents`; mit veralteter `409 revision_conflict` samt `details`; mit `force: true` ohne Prüfung.
- P2-AK3 `PATCH` und `restore` prüfen den Body: `content` Text (Pflicht bei `PATCH`), `note` Text oder fehlt, `base_revision` Ganzzahl (Pflicht, außer bei `force: true`), `revision` Ganzzahl (Pflicht bei `restore`), `force` Wahrheitswert oder fehlt; sonst `400 invalid_value` mit `details.field`. Leerer Inhalt → `400 invalid_value` aus `DocumentService`.
- P2-AK4 `{slug}` außer `editorial-guidelines` und `preferences` → `404 not_found`.
- P2-AK5 Speichern über die API landet mit Akteur `owner` im Verlauf und im Audit (`document.update`); ein Agent sieht die neue Fassung beim nächsten `get_document`.
- P2-AK6 `docs/routes.md` nennt alle sechs Routen; `php -l` fehlerfrei.

## Checkliste

- [x] Neue Datei `app/src/Api/InstructionController.php` (`final class`, Konstruktor `private readonly array $config`). Klassen-Docblock: „Ansicht „Anweisungen für Agenten“ im Redaktionsmodus: alles, was ein Agent von DISPATCH bekommt, dazu Fassungen der Richtlinien und Präferenzen (ADR-031). Nur der Inhaber.“
  - Konstante `KEYS_BY_SLUG = ['editorial-guidelines' => 'editorial_guidelines', 'preferences' => 'preferences']` mit Kommentar „Der Router lässt im Pfad keinen Unterstrich zu.“
  - Konstante `TEXTS` (Reihenfolge verbindlich), je Eintrag `[Titel, wann]`:
    - `'instructions' => ['Server-Anweisung', 'beim Verbinden, an jeden Agenten']`
    - `'workflow-scheduled' => ['Workflow: geplanter Artikel', 'im Briefing, wenn weder Auftrag noch zurückgegebener Entwurf warten']`
    - `'workflow-queue' => ['Workflow: Auftrag aus der Queue', 'im Briefing, wenn ein Thema in der Queue liegt']`
    - `'workflow-returned' => ['Workflow: zurückgegebener Entwurf', 'im Briefing, wenn du einen Entwurf zurückgegeben hast']`
    - `'style-guide' => ['Style-Guide', 'über get_style_guide, vor dem ersten Schreiben eines Laufs']`
    Datei je Text: `dirname(__DIR__, 2) . '/mcp/' . $key . '.md'`; `source` in der Antwort `'app/mcp/' . $key . '.md'`.
  - `public function show(Request $request): never` — `ApiAuth::owner`; `$pdo = Connection::open()`; `$services = McpController::services($pdo, $this->config)`; `$registry = McpController::registry($services)`; Antwort:
    - `documents`: je Slug aus `KEYS_BY_SLUG` `$this->document($services['documents'], $slug)`;
    - `tools`: `array_map(fn (Tool $tool): array => $tool->describe() + ['scope' => $tool->scope, 'group' => $registry->groupOf($tool->name)], $registry->all())`;
    - `prompts`: aus `(new Prompts())->catalog()`, je Eintrag `text` entfernen und `html` = `Markdown::toHtml($text)` setzen;
    - `texts`: aus `TEXTS` je `key`, `title`, `when`, `source`, `html` = `Markdown::toHtml(trim((string) file_get_contents($datei)))`;
    - `form_rules`: `(array) $this->config['form_rules']`.
  - `private function document(DocumentService $service, string $slug): array` — `$key = self::KEYS_BY_SLUG[$slug]`; `$document = $service->get($key)`; `$latest = $service->history($key)[0] ?? null`; Rückgabe `key`, `slug`, `title`, `revision`, `updated_at`, `actor` (`$latest['actor'] ?? null`), `note` (`$latest['note'] ?? null`), `content`, `html` (`Markdown::toHtml((string) $document['content'])`).
  - `public function preview(Request $request): never` — `ApiAuth::owner`; `content` muss Text sein (sonst `invalid_value`, Feld `content`); Antwort `{"html": Markdown::toHtml($content)}`. Kein Datenbankzugriff.
  - `public function save(Request $request, string $slug): never` — `$actor = ApiAuth::owner($request)`; `$key = $this->keyFor($slug)`; Body prüfen (P2-AK3); `$base = $force ? null : $baseRevision`; `$service->update($key, $content, null, $note, $actor, $base)`; Antwort `{"document": $this->document($service, $slug)}`.
  - `public function history(Request $request, string $slug): never` — `{"revisions": $service->history($this->keyFor($slug))}`.
  - `public function revision(Request $request, string $slug, int $revision): never` — `$service->revisionWithDiff($this->keyFor($slug), $revision)` direkt als Antwort.
  - `public function restore(Request $request, string $slug): never` — wie `save`, aber `revision` statt `content`; `$service->restore($key, $revision, $base, $actor)`; Antwort wie `save`.
  - `private function keyFor(string $slug): string` — unbekannt → `DomainError('not_found', sprintf('„%s“ lässt sich hier nicht ansehen oder ändern. Gültig: %s.', $slug, implode(', ', array_keys(self::KEYS_BY_SLUG))), ['slug' => $slug])`. In jeder Methode mit `{slug}` als Erstes nach der Anmeldung, innerhalb des `try`.
  - Alle `DomainError` → `ApiErrors::respond`, wie in `CategoryController::patch`.
- [x] `app/index.php`: `use Dispatch\Api\InstructionController;` und nach den Routen von `changes` in dieser Reihenfolge:
  - `GET /dispatch/api/instructions` → `show`
  - `POST /dispatch/api/instructions/preview` → `preview`
  - `PATCH /dispatch/api/instructions/{slug}` → `save($request, (string) $params['slug'])`
  - `GET /dispatch/api/instructions/{slug}/revisions` → `history`
  - `GET /dispatch/api/instructions/{slug}/revisions/{id}` → `revision($request, (string) $params['slug'], (int) $params['id'])`
  - `POST /dispatch/api/instructions/{slug}/restore` → `restore`
  (`POST …/preview` und `PATCH …/{slug}` treffen dasselbe Muster; der Router wählt nach Methode, die Reihenfolge ist egal.)
- [x] Lokal prüfen, angemeldet im Browser, in der Konsole: `const { api } = await import(new URL('api.js', performance.getEntriesByType('resource').find((e) => e.name.endsWith('/start.js')).name))`, dann `await api('GET', 'instructions')` gegen den Kontrakt (33 Werkzeuge mit `group`, fünf Texte, drei Prompts, zwei Dokumente). Danach `PATCH` auf `instructions/preferences` mit alter `base_revision` → 409, mit aktueller → 200, `POST instructions/preferences/restore` mit der Fassung davor → 200; `GET instructions/preferences/revisions` zeigt beide neuen Fassungen. Zum Schluss den Ausgangsinhalt per `restore` zurückholen. Nur lokal.
- [x] `docs/routes.md`: sechs Zeilen nach der Zeile `changes/{id}/reject`, Auth `JWT`, Controller `Api\InstructionController::<methode>` mit Kurzbeschreibung in Klammern wie bei `categories/{id}` (Fehler 409 `revision_conflict` mit `details`, 404 bei unbekanntem Slug; ADR-031), Spalte Phase `AA 2` (Plan „Anweisungen für Agenten“, Phase 2; Muster wie `M5 4`).
- [x] `php -l app/src/Api/InstructionController.php app/index.php`.

## Report-Back

Phase 2 fertig: `InstructionController` mit sechs Routen, Routen in `app/index.php`, `docs/routes.md` (Phase `AA 2`). `php -l` fehlerfrei. Der Browser-Lauf gegen die lokale Instanz (Checklistenpunkt 3) ist nicht gelaufen: kein Zugriff auf die angemeldete Sitzung; P2-AK1–AK5 sind am Code abgeglichen, nicht live geprüft.
