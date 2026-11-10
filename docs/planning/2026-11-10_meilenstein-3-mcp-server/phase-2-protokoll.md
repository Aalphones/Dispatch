# Phase 2 — Protokollschicht, Anmeldung, Tool-Registry, Durchstich

Rating: heikel

## Kontext (vorher lesen)

- `README.md` dieses Plans → Kontrakt: „Dateien und Namen“, „Endpunkt `/dispatch/mcp`“, „Tool-Katalog“ (Spalten Scope, Annotationen).
- Fachkonzept `docs/design/konzept-blog-engine.md` → „MCP-Server“ → „Protokoll“, „Server-Anweisungen“.
- Offizielle Spezifikation: `https://modelcontextprotocol.io/specification/2026-07-28` (Basis-Protokoll, Transport „Streamable HTTP“, `server/discover`, Tools) und `https://modelcontextprotocol.io/specification/2025-11-25` (Lifecycle `initialize`). Per WebFetch lesen, nur die genannten Abschnitte.
- `app/index.php`, `app/src/Http/Request.php`, `app/src/Http/Response.php`, `app/src/Util/Log.php`, `bridge/index.php`, `dev-router.php`.
- `app/src/Domain/TokenService.php`, `app/src/Auth/Scopes.php` (Phase 1), `app/src/Domain/DocumentService.php` (Durchstich-Tool).
- `docs/conventions/php.md`, `docs/conventions/dod.md`.
- Fehlerklassen geprüft (Vault): keine einschlägig.

## AK der Phase

1. Spezifikations-Abgleich steht in `FINDINGS.md`: Header-Namen (`MCP-Protocol-Version`, `Mcp-Method`, `Mcp-Name`), `_meta`-Schlüssel, Felder von `server/discover`, `ttlMs`/`cacheScope` — je „wie Kontrakt“ oder „abweichend: …“. Abweichungen sind umgesetzt nach Spezifikation.
2. Mit gültigem Token (lokal, Dev-Server `php -S localhost:8080 -t public dev-router.php`, Aufrufe per `curl`):
   - Legacy: `initialize` mit `protocolVersion` `2025-06-18` → Antwort `2025-06-18`, mit `1999-01-01` → `2025-11-25`; `serverInfo`, `capabilities`, `instructions` vorhanden; kein Header `Mcp-Session-Id`.
   - `notifications/initialized` → 202 ohne Body.
   - Modern: `server/discover` mit Header `MCP-Protocol-Version: 2026-07-28`, `Mcp-Method: server/discover` → `supportedVersions` mit allen vier Versionen.
   - `tools/list` (modern) mit `ttlMs` und `cacheScope`; Legacy ohne beides.
   - `tools/call` `list_documents` liefert einen Textblock mit den fünf Dokumenten (Schlüssel, Titel, öffentlich ja/nein).
3. Abgewiesen und einzeln belegt: ohne Token 401 mit `WWW-Authenticate: Bearer realm="dispatch"`; falsches, widerrufenes, abgelaufenes Token 401; `GET /dispatch/mcp` 405 mit `Allow: POST`; kaputtes JSON 400 `-32700`; Batch 400 `-32600`; modern ohne `Mcp-Method` 400; `Mcp-Method` ≠ Body-Methode 400; `tools/call` ohne passenden `Mcp-Name` 400; Header-Version ≠ `_meta`-Version 400; unbekannte Version 400 mit `data.supported`; unbekannte Methode `-32601`; unbekanntes Tool `-32602`; Tool ohne Scope → 403 mit `insufficient_scope` (dafür in der Registry ein Test-Durchlauf mit einem Token nur `blog:admin` gegen `list_documents`).
4. Ein Handler, der eine `RuntimeException` wirft, ergibt `-32603` ohne Stacktrace; der Eintrag steht in `app/storage/logs/app.log`. Ein `DomainError` im Handler ergibt `isError: true` mit `error`, `message`.
5. `tools/list` mit Token nur `blog:read` enthält `list_documents`; die Registry verlangt für jedes Tool alle Kontrakt-Felder (fehlende Annotation → `LogicException` beim Registrieren).

## Checkliste

- [x] Schritt 1: Spezifikation lesen, Abgleich in `FINDINGS.md` eintragen (AK 1). Erst danach Code.
- [x] `Http/Request`: `body(): string` (liest `php://input` einmal und merkt es sich). `Http/Response`: `empty(int $status, array $headers = []): never`, `raw(string $json, int $status, array $headers = []): never` (für fertig kodierte JSON-RPC-Antworten).
- [x] `Auth/McpAuth.php`: `public static function authenticate(Request $r, TokenService $tokens): ?array` → `['name', 'scopes']` oder `null`.
- [x] `Mcp/ProtocolError.php`: `final class ProtocolError extends \RuntimeException` mit `int $rpcCode`, `int $httpStatus`, `array $data`, `array $headers`.
- [x] `Mcp/Protocol.php`: Konstanten `MODERN`, `LEGACY`; `public static function negotiate(Request $r, array $message): array` → `['version' => …, 'modern' => bool]`, wirft `ProtocolError` nach den Regeln im Kontrakt (Erkennung und Header-Abgleich).
- [x] `Mcp/Tool.php` (readonly, Felder nach Kontrakt; `scope` ist eine der `Scopes`-Konstanten; `annotations` = `title`, `readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint` alle Pflicht), `Mcp/ToolResult.php` (`static json(mixed $data, array $images = [])`, `static error(string $code, string $message, array $details = [])`, `toArray(): array`).
- [x] `Mcp/Registry.php`: `add(Tool $t)`, `visible(array $scopes): list<Tool>`, `call(string $name, array $args, array $auth): ToolResult`. `call` prüft Existenz (`ProtocolError -32602`), Scope (`ProtocolError` 403, `-32003`, Header nach Kontrakt), Pflichtargumente und Grundtypen laut `inputSchema` (`string`, `integer`, `number`, `boolean`, `array`, `object`; Fehler → `ToolResult::error('invalid_argument', 'Argument „x“ … Beispiel: …')`), ruft den Handler `fn(array $args, Actor $actor): ToolResult` und wandelt `DomainError` in `ToolResult::error($e->errorCode, $e->getMessage(), $e->details)`.
- [x] `Mcp/McpController.php`: Ablauf nach Kontrakt-Reihenfolge; Dispatch der Methoden; `initialize`/`server/discover` mit `serverInfo`, `capabilities`, `instructions` (Datei `app/mcp/instructions.md`); Antwort-Hülle `{"jsonrpc":"2.0","id":…,"result":…}` bzw. `"error":{code,message,data?}`; Notifications → 202. Für `resources/*` und `prompts/*` in dieser Phase leere Listen (`resources: []`, `resourceTemplates: []`, `prompts: []`); `resources/read`/`prompts/get` → `-32602` „Unbekannt“. Phase 5 füllt sie.
- [x] Services einmal pro Anfrage bauen: `Mcp/McpController::services(PDO, array $config): array` liefert ein Array `name → Instanz` (`documents`, `categories`, `settings`, `changes`, `queue`, `posts`, `media`, `stats`, `siteInfo`, `briefing`, `tokens`, `reactions`); in dieser Phase nur `documents` und `tokens`, die übrigen Einträge kommen in Phase 4/5 dazu.
- [x] `Mcp/Tools/DocumentTools.php`: nur `list_documents` (Durchstich; die übrigen Dokument-Tools in Phase 4).
- [x] `app/mcp/instructions.md`: Server-Anweisungen aus dem Fachkonzept, „Feuilleton“ → „DISPATCH“, sonst wörtlich.
- [x] `app/index.php`: `/dispatch/mcp` → `McpController::handle` für jede Methode (der Controller antwortet selbst mit 405); Platzhalter-401 entfernen.
- [x] `dev-router.php`: prüfen, dass `/dispatch/mcp` mit `POST`-Body und `Authorization`-Header bei `app/index.php` ankommt (Aufruf mit `curl -v`); falls nicht, Router anpassen.
- [x] ADR-016 `docs/decisions/016-mcp-protokollschicht-und-registry.md`: zwei Generationen nebeneinander, Erkennung, Fehlerformen, Registry als einzige Quelle für `tools/list` und Scope-Prüfung, Texte für Agenten als Dateien unter `app/mcp/` (Alternative: als Dokumente in der Datenbank — verworfen, weil sie mit dem Code-Stand der Tools zusammenpassen müssen); Abweichungen aus AK 1.
- [x] Doku: `docs/code-map.md` (Ordner `src/Mcp/`, `app/mcp/`, `Auth/McpAuth`), `docs/decisions/README.md`.

## Report-Back

Status: complete. Alle AK lokal per `curl` gegen den Dev-Server belegt (Legacy- und Modern-Durchlauf, alle Abweisungen einzeln, `RuntimeException` → `-32603` ohne Stacktrace mit Logeintrag, `DomainError` → `isError`, Token nur `blog:admin` → 403 `insufficient_scope`); die Test-Tools dafür sind wieder entfernt, die Test-Tokens widerrufen.

Abweichungen vom Plan (Spezifikation hat Vorrang, Details in `FINDINGS.md`, Kontrakt in der README nachgezogen): Fehlercodes `-32020` (Header) und `-32022` (Version) statt `-32600`; Pflicht-`_meta` bei Modern (`-32602`); `resultType` und Server-Info in `_meta`; unbekannte Methode bei Modern HTTP 404; `ttlMs`/`cacheScope` auch bei `server/discover`; Origin-Prüfung (403). `Response` setzt den Status nach den Headern, weil PHP bei `WWW-Authenticate` sonst 401 erzwingt.

Für Phase 4/5: Tools als `Tools/<X>Tools::register`, in `McpController::registry()` aufrufen; Services in `McpController::services()` ergänzen; parameterlose Tools mit `{"type":"object","additionalProperties":false}`.
