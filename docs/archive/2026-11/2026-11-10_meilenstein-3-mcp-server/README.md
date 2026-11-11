# Meilenstein 3 — MCP-Server mit Tokens

Ziel: Agenten in Claude Code arbeiten über `https://quantum-canvas.de/dispatch/mcp` mit dem Blog — Briefing lesen, Queue-Aufträge reservieren, Artikel anlegen, prüfen und zur Freigabe einreichen, Medien importieren, Statistik lesen, die Website neu bauen. Zugang über feste MCP-Tokens mit Scopes. Der Server spricht die MCP-Revision 2026-07-28 (zustandslos) und ältere Clients mit `initialize` (2025-11-25, 2025-06-18, 2025-03-26). Das Briefing enthält zusätzlich die „Mehr davon / Weniger“-Reaktionen des Inhabers. Grundlage: `docs/PROJECT.md` → Meilenstein 3, Fachkonzept `docs/design/konzept-blog-engine.md` → „MCP-Server“, „Authentifizierung und Sicherheit“ → „MCP-Tokens“ und „Scopes“, „Redaktions-Workflows“, „Anbindung an Claude“; ADR-009, ADR-012.

Nicht in diesem Meilenstein: OAuth und `resource_metadata` im 401 (Meilenstein 5), Dialog „Zugänge“, Freigabe, Vorschau-Seite, signierte Links für Vorschau und private Medien, Zähl-Aufruf der Statistik, Oberfläche für „Mehr davon / Weniger“ (alle Meilenstein 4), automatischer Neubau nach Freigabe (Meilenstein 4, Freigabe gibt es erst dort).

## Phasen

| # | Phase | Rating | Status |
|---|---|---|---|
| 1 | [Tokens, Scopes, Reaktionen und Token-Wege](phase-1-tokens-reaktionen.md) | standard | complete |
| 2 | [Protokollschicht, Anmeldung, Tool-Registry, Durchstich](phase-2-protokoll.md) | heikel | complete |
| 3 | [Domänen-Ergänzungen: Artikel, Medien, Statistik, Briefing](phase-3-domaene.md) | heikel | complete |
| 4 | [Tools: Kontext, Queue, Artikel; Anweisungen und Workflows](phase-4-tools-redaktion.md) | standard | complete |
| 5 | [Tools: Medien, Statistik, Website; Resources und Prompts](phase-5-tools-medien-resources.md) | standard | complete |
| 6 | [Strato, Abnahme mit echten Clients, Doku](phase-6-strato-abschluss.md) | standard | complete |

Reihenfolge ist Pflicht. ADRs dieses Meilensteins: 016 (Phase 2), 017 (Phase 1), 018 (Phase 3), 019 (Phase 1) — die Nummern sind reserviert.

## Kontrakt (gilt für alle Phasen)

### Dateien und Namen

| Was | Datei |
|---|---|
| Migrationen | `app/migrations/010_token.sql`, `app/migrations/011_reaction.sql` |
| Tokens | `app/src/Domain/TokenService.php`, `app/src/Domain/TokenRepository.php` |
| Scopes | `app/src/Auth/Scopes.php` |
| Anmeldung am MCP-Endpunkt | `app/src/Auth/McpAuth.php` |
| Token-Endpunkt für die CLI | `app/src/Api/TokenController.php`, Route `POST /dispatch/api/tokens` |
| Reaktionen | `app/src/Domain/ReactionService.php`, `app/src/Domain/ReactionRepository.php` |
| Statistik (lesen) | `app/src/Domain/StatsService.php`, `app/src/Domain/StatsRepository.php` |
| Kennzahlen der Website | `app/src/Domain/SiteInfoService.php` |
| Briefing | `app/src/Domain/BriefingService.php` |
| MCP-Einstieg | `app/src/Mcp/McpController.php` (`handle(Request): never`) |
| Protokoll | `app/src/Mcp/Protocol.php` (Versionserkennung, Header-Abgleich), `app/src/Mcp/ProtocolError.php` |
| Registry | `app/src/Mcp/Registry.php`, `app/src/Mcp/Tool.php` (readonly: `name, title, description, inputSchema, annotations, scope, handler`), `app/src/Mcp/ToolResult.php` |
| Bild-Blöcke | `app/src/Mcp/ImageBlocks.php` |
| Tools | `app/src/Mcp/Tools/<X>Tools.php` mit `X` ∈ `Document, Category, Setting, Change, Queue, Post, Media, Stats, Site, Briefing`; jede Klasse hat `public static function register(Registry $r, array $services): void` |
| Resources, Prompts | `app/src/Mcp/Resources.php`, `app/src/Mcp/Prompts.php` |
| Texte für Agenten | `app/mcp/instructions.md`, `app/mcp/style-guide.md`, `app/mcp/workflow-returned.md`, `app/mcp/workflow-queue.md`, `app/mcp/workflow-scheduled.md` |

### Tabellen

**`tokens` (010)** — vollständig nach Fachkonzept, damit Meilenstein 4 und 5 keine Migration für Spalten brauchen: `id` INT UNSIGNED PK AI · `token_hash` CHAR(64) UNIQUE (SHA-256 hex des Klartexts) · `kind` ENUM(`mcp`, `oauth_access`, `oauth_refresh`, `owner_refresh`) · `name` VARCHAR(120) · `client_id` VARCHAR(500) NULL · `scope` VARCHAR(200) (Scopes durch Leerzeichen getrennt) · `family` CHAR(32) NULL · `expires_at` DATETIME NULL · `revoked` TINYINT(1) Default 0 · `last_used_at` DATETIME NULL · `created_at` DATETIME · Index (`kind`, `revoked`). Meilenstein 3 nutzt nur `kind = mcp`.

**`post_reactions` (011)** — eine aktuelle Reaktion je Artikel: `post_id` INT UNSIGNED PK, FK → `posts.id` CASCADE · `reaction` ENUM(`more`, `less`) · `note` VARCHAR(500) NULL · `updated_at` DATETIME. „Keine Reaktion“ = Zeile gelöscht.

### MCP-Token

- Klartext: `dmcp_` + 64 Hex-Zeichen (`bin2hex(random_bytes(32))`). Gespeichert wird nur `hash('sha256', $klartext)`. Der Klartext wird genau einmal ausgegeben.
- Name: `^[a-z0-9][a-z0-9-]{0,39}$`, eindeutig unter den nicht widerrufenen `mcp`-Tokens (sonst `DomainError('name_taken')`). Der Akteur heißt `mcp:<name>` (`Actor::mcp`).
- Gültig = `kind = 'mcp'`, `revoked = 0`, `expires_at IS NULL OR expires_at > UTC_TIMESTAMP()`. Jede erfolgreiche Anmeldung setzt `last_used_at = UTC_TIMESTAMP()`.
- Anlegen, Auflisten, Widerrufen: lokal per CLI (`bin/dispatch token:create|token:list|token:revoke`), auf Strato per CLI mit `--remote` über `POST /dispatch/api/tokens`, geschützt mit `DISPATCH_DEPLOY_TOKEN` (ADR-017).

### Scopes

`blog:read`, `blog:write`, `blog:admin` (Konstanten in `Auth\Scopes`: `READ`, `WRITE`, `ADMIN`, `ALL`). Keiner schließt einen anderen ein. CLI-Standard: `blog:read blog:write`. Veröffentlichen hängt an keinem Scope.

### Endpunkt `/dispatch/mcp`

- Nur `POST`. `GET`, `DELETE` und andere → 405 mit `Allow: POST`.
- Reihenfolge: (1) Methode prüfen, (2) Anmeldung, (3) Body parsen, (4) Version erkennen und Header abgleichen, (5) Methode ausführen.
- **Anmeldung:** `Request::bearerToken()` muss mit `dmcp_` beginnen und gültig sein. Sonst HTTP 401, `WWW-Authenticate: Bearer realm="dispatch"`, Body `{"error":"unauthorized","message":"Gültiges MCP-Token im Header Authorization: Bearer … erforderlich."}`. Damit fallen auch JWTs (Meilenstein 4) durch.
- **Antworten** immer `Content-Type: application/json; charset=utf-8`; Notifications (kein `id`) → 202 ohne Body. Batch (JSON-Array) → JSON-RPC-Fehler `-32600`, HTTP 400. Kaputtes JSON → `-32700`, HTTP 400.
- **Versionen** (`Mcp\Protocol`): `MODERN = '2026-07-28'`, `LEGACY = ['2025-11-25', '2025-06-18', '2025-03-26']`.
  - Methode `initialize` → Legacy. Antwort-Version = angefragte, wenn in `LEGACY`, sonst `2025-11-25`. Keine `Mcp-Session-Id`.
  - Sonst: Header `MCP-Protocol-Version` oder `params._meta["io.modelcontextprotocol/protocolVersion"]`. Stehen beide da und weichen ab → HTTP 400, JSON-RPC `-32020` (HeaderMismatch). Wert `2026-07-28` → modern. Wert in `LEGACY` → Legacy. Keiner da → Legacy (`2025-03-26`, wie die Spezifikation 2025-06-18 es für fehlende Header vorsieht). Unbekannter Wert → HTTP 400, `-32022` (UnsupportedProtocolVersion), `data` = `{supported: alle vier, requested: <Wert>}`.
  - **Modern** verlangt Header `MCP-Protocol-Version` (fehlt er bei `_meta`-Version modern → `-32020`), Header `Mcp-Method` = Body-`method` und bei `tools/call`, `prompts/get` den Header `Mcp-Name` = `params.name`, bei `resources/read` `Mcp-Name` = `params.uri` (Wert darf `=?base64?…?=` sein, der Server dekodiert). Fehlt oder weicht ab → HTTP 400, `-32020`, Meldung nennt den Header. Im Body Pflicht: `params._meta` mit Version und `clientCapabilities`, sonst HTTP 400, `-32602`.
  - Abgleich mit der Spezifikation 2026-07-28 steht in `FINDINGS.md` (Phase 2); die Spezifikation hat Vorrang, Abweichungen sind oben eingearbeitet.
- **Methoden:** `initialize` (Legacy), `server/discover` (modern), `ping` (beide, Ergebnis `{}`), `tools/list`, `tools/call`, `resources/list`, `resources/templates/list`, `resources/read`, `prompts/list`, `prompts/get`. Unbekannt → `-32601` (modern HTTP 404, Legacy HTTP 200).
- `initialize` (Legacy) liefert `serverInfo` `{name: "dispatch", title: "DISPATCH", version: "0.3.0"}`, `capabilities` `{tools: {}, resources: {}, prompts: {}}` und `instructions` (Inhalt von `app/mcp/instructions.md`). `server/discover` (modern) liefert `supportedVersions` (alle vier), `capabilities`, `instructions`; die Server-Info steht dort in `_meta["io.modelcontextprotocol/serverInfo"]` `{name, version}`.
- Modern: jedes Ergebnis trägt `resultType: "complete"` und die Server-Info in `_meta`. `server/discover`, `tools/list`, `resources/list`, `resources/templates/list`, `prompts/list` und `resources/read` tragen `ttlMs: 300000` und `cacheScope` (`private`; `server/discover`: `public`). Legacy-Ergebnisse tragen beides nicht.
- **Origin:** Ein gesetzter `Origin`-Header, der nicht zum `Host` gehört → HTTP 403.
- Parameterlose Tools haben `inputSchema` `{"type":"object","additionalProperties":false}`.
- **Scope-Prüfung:** `tools/list` zeigt nur Tools, deren Scope im Token steht. `tools/call` auf ein Tool ohne passenden Scope → HTTP 403, `WWW-Authenticate: Bearer error="insufficient_scope", scope="<scope>"`, Body JSON-RPC-Fehler `-32003` mit derselben Meldung. Unbekanntes Tool → `-32602`. Resources und Prompts brauchen `blog:read`.
- **Tool-Ergebnis:** `{"content": [{"type":"text","text": <kompaktes JSON, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES>}, …Bild-Blöcke], "isError": false}`. Bild-Block: `{"type":"image","data":<base64>,"mimeType":"image/jpeg|image/png|image/webp"}`, höchstens 6 je Antwort, Kante ≤ 1024 px (`Mcp\ImageBlocks`).
- **Fachliche Fehler** (`DomainError`, fehlende Pflichtargumente, falscher Typ) → `isError: true`, Text = `{"error": <code>, "message": <Meldung, die sagt, wie es richtig geht>, "details": …}`. Andere Ausnahmen → `Log` + JSON-RPC `-32603` „Interner Fehler“, HTTP 500, ohne Stacktrace in der Antwort.
- **Audit:** schreibende Tools reichen `Actor::mcp($tokenName)` an die Services; das Protokoll schreiben die Services (ADR-009).

### Tool-Katalog (33 Tools)

Annotationen: `R` = `readOnlyHint: true, idempotentHint: true`; `W` = `readOnlyHint: false`; `D` = zusätzlich `destructiveHint: true`; `O` = `openWorldHint: true`. Ohne Angabe gilt `destructiveHint: false`, `openWorldHint: false`.

| Tool | Argumente (Pflicht **fett**) | Scope | Ann. | Service |
|---|---|---|---|---|
| `get_editorial_brief` | `mode` (auto\|scheduled\|queue, Default auto) | read | R | `BriefingService::brief` |
| `get_style_guide` | — | read | R | Datei `app/mcp/style-guide.md` |
| `list_documents` | — | read | R | `DocumentService::list` |
| `get_document` | **`key`** | read | R | `DocumentService::get` |
| `update_document` | **`key`**, **`content`**, `title`, `note` | admin | W | `DocumentService::update` |
| `list_categories` | `include_inactive` | read | R | `CategoryService::list` + Kennzahlen aus `BriefingService::categoryStats` |
| `save_category` | `id` (fehlt = neu), `slug`, `name`, `description`, `parent`, `position`, `interest`, `keywords`, `avoid`, `active` | admin | W | `CategoryService::create` / `update` |
| `get_site_info` | — | read | R | `SiteInfoService::info` |
| `update_settings` | **`settings`** (Objekt) | admin | W | `SettingService::set` |
| `list_changes` | `status` (pending\|applied\|rejected\|all, Default pending) | read | R | `ChangeService::list` |
| `list_queue` | `status` (Liste), `limit`, `offset` | read | R | `QueueService::list` |
| `add_to_queue` | **`title`**, `brief`, `links`, `attachments`, `priority`, `depth`, `category` | write | W | `MediaService` + `QueueService::add` |
| `claim_queue_item` | `id` | write | W | `QueueService::claim` + Bild-Blöcke |
| `release_queue_item` | **`id`**, **`status`** (open\|failed\|cancelled), `note` | write | W, D | `QueueService::release` |
| `list_posts` | `status`, `category`, `tag`, `year`, `limit`, `offset` | read | R | `PostService::list` |
| `search_posts` | **`query`**, `limit` | read | R | `PostService::search` |
| `get_post` | `id` oder `slug`, `include_html` | read | R | `PostService::get` / `getBySlug` |
| `create_post` | Felder nach Fachkonzept „Felder von create_post“ | write | W | `PostService::create` |
| `update_post` | **`id`** + Felder | write | W | `PostService::update` |
| `validate_post` | **`id`** | read | R | `PostService::validate` |
| `submit_post` | **`id`** | write | W | `PostService::submit` |
| `withdraw_post` | **`id`** | write | W | `PostService::withdraw` |
| `delete_post` | **`id`**, **`confirm`** (muss `true` sein) | write | W, D | `PostService::discard` |
| `list_post_revisions` | **`id`** | read | R | `PostService::revisions` |
| `restore_post_revision` | **`id`**, **`revision`** | write | W | `PostService::restoreRevision` |
| `import_media_from_url` | **`url`**, `alt`, `caption`, `credit`, `license`, `license_url`, `source_url` | write | W, O | `MediaService::importFromUrl` |
| `upload_media` | **`data_base64`**, **`filename`**, `alt`, `caption`, `credit`, `license`, `license_url`, `source_url` | write | W | `MediaService::store` |
| `update_media` | **`uid`**, `alt`, `caption`, `credit`, `license`, `license_url`, `source_url` | write | W | `MediaService::updateMeta` |
| `list_media` | `kind`, `query`, `limit`, `offset` | read | R | `MediaService::list` |
| `get_media` | **`uid`**, `view` (bool) | read | R | `MediaService::get` + Bild-Block |
| `get_stats` | `from`, `to`, `group_by`, `filter` | read | R | `StatsService::stats` |
| `get_post_stats` | `id` oder `slug`, `from`, `to` | read | R | `StatsService::postStats` |
| `rebuild_site` | — | write | W | `Site\Builder::build` |

Bewusst kein Tool: Freigeben, Zurückgeben, Zurückziehen Veröffentlichter, Vorschläge übernehmen, Tokens anlegen, Formvorgaben ändern, Reaktionen setzen. `complete_queue_item` aus dem Fachkonzept entfällt (ADR-018): ein Auftrag ist mit der Freigabe erledigt, das macht `PostService::approve`.

### Neue Service-Methoden (Phase 1 und 3)

```php
// Domain/TokenService
public function create(string $name, array $scopes, ?int $expiresDays, Actor $actor): array; // ['token' => Klartext, 'id', 'name', 'scopes', 'expires_at']
public function list(): array;                       // mcp-Tokens ohne Hash: id, name, scopes, expires_at, revoked, last_used_at, created_at
public function revoke(int $id, Actor $actor): void;
public function authenticate(string $plain): ?array; // ['id', 'name', 'scopes' => list<string>] oder null
// Domain/ReactionService
public function set(int $postId, ?string $reaction, ?string $note, Actor $actor): void; // null = löschen; nur owner oder cli
public function recent(int $days = 90): array;
// Domain/PostService
public function withdraw(int $id, Actor $actor): array;
public function restoreRevision(int $id, int $revision, Actor $actor): array;
public function list(array $statuses = [], int $limit = 50, int $offset = 0, ?string $category = null, ?string $tag = null, ?int $year = null): array;
public function search(string $query, int $limit = 20): array;
// discard: Nicht-Inhaber dürfen, wenn status = draft und published_revision IS NULL
// Domain/MediaService
public function list(?string $kind = null, int $limit = 50, int $offset = 0, ?string $query = null): array;
// Domain/StatsService
public function stats(?string $from, ?string $to, string $groupBy = 'day', array $filter = []): array;
public function postStats(int|string $idOrSlug, ?string $from, ?string $to): array;
// Domain/SiteInfoService
public function info(): array;
// Domain/BriefingService
public function brief(string $mode): array;
public function categoryStats(): array; // category_id => ['post_count', 'last_published_at', 'rotation']
```

## Finale Abnahme (Gesamtergebnis)

1. Lokal und auf Strato: `npx @modelcontextprotocol/inspector` verbindet sich mit Header-Token und listet alle 33 Tools, 3 Prompts, die Resources und die Vorlage — einmal mit Protokoll 2026-07-28, einmal mit 2025-11-25 (Inspector-Version, die beide kann; sonst ein Abruf mit `@modelcontextprotocol/client` v2 per `node`-Skript im Scratchpad, nicht im Repo).
2. Ein Token mit nur `blog:read` sieht keine schreibenden Tools; `tools/call` auf `create_post` damit ergibt 403 mit `insufficient_scope`. Ohne, mit falschem, widerrufenem und abgelaufenem Token: 401.
3. Ein kompletter Lauf in Claude Code gegen Strato: `add_to_queue` → `get_editorial_brief(mode="auto")` liefert den Queue-Workflow → `claim_queue_item` → `import_media_from_url` → `create_post` mit `queue_item_id` → `validate_post` → `submit_post`; Artikel steht auf `review`, Auftrag auf `review`, nichts davon ist öffentlich.
4. `update_settings({"site_title": …})` und `update_document("about", …)` per MCP erzeugen Änderungsvorschläge, nichts Öffentliches ändert sich; ein Versuch, `min_sources` zu ändern, endet mit `isError` `not_changeable`.
5. Eine per CLI gesetzte Reaktion „Mehr davon“ erscheint im Briefing. `get_stats` liefert Tageswerte (lokal mit von Hand eingefügten Zeilen in `stats_daily`).
6. Nach `deploy.cmd`: `/card/`, `/scanner/capture` antworten wie vorher, `/dispatch-app/.env` → 403, `GET /dispatch/mcp` → 405.

## Summary

Agenten arbeiten über `https://quantum-canvas.de/dispatch/mcp` mit dem Blog: Tokens mit Scopes (CLI, auch `--remote`), Endpunkt mit beiden Protokoll-Generationen, 33 Tools, 6 Resources, 3 Prompts, Briefing mit Reaktionen. Abnahme lokal und mit Claude Code gegen Strato; ein Testartikel steht auf `review`.

## Files touched

`app/src/Mcp/`, `app/src/Auth/`, `app/src/Domain/` (Token, Reaktion, Briefing, Statistik, Site-Info; Ergänzungen an Post/Media/Queue), `app/src/Api/TokenController.php`, `app/src/Api/CategoryController.php`, `app/mcp/`, `app/migrations/010–011`, `bin/dispatch`, `deploy.cmd`, `docs/` (PROJECT, ADR-016 bis 019, code-map, models, knowledge).

## Commits

810a33a, 4bb83c8, 3ab10c7, 1dc5714, 585ad9e; Phase 6 und Abschluss siehe `git log`.

## Deviations from plan

- Standard-Token (`blog:read`, `blog:write`) sieht 30 statt 33 Tools; die übrigen drei brauchen `blog:admin`.
- Rauchtest in `deploy.cmd`: `GET /dispatch/mcp` → 405, zusätzlich `POST` → 401.
- Neu: `category:create` und `POST /dispatch/api/categories`, weil Strato ohne Kategorie keine Einreichung zuließ.
- Kein Inspector; beide Protokolle per `node`-Skript, dazu Claude Code.

## Follow-ups

Siehe `docs/knowledge/GAPS.md`: Kategorien durch Agenten, Statistik-Zählung, Herkunft bei Upload-Bildern, Queue-Auftrag ohne Kategorie, ungeprüfte Bild-Metadaten. Der Testartikel 1 (Auftrag 1) auf Strato wartet auf die Freigabe (Meilenstein 4).
