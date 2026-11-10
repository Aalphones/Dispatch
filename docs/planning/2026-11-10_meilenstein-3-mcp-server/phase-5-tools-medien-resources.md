# Phase 5 — Tools: Medien, Statistik, Website; Resources und Prompts

Rating: standard

## Kontext (vorher lesen)

- `README.md` dieses Plans → Kontrakt: „Endpunkt“ (Bild-Block, Listen mit `ttlMs`), „Tool-Katalog“.
- Fachkonzept → „MCP-Server“ → „Resources und Prompts“, „Protokoll“ → „Ergebnisse“.
- `app/src/Mcp/McpController.php`, `Registry.php`, `Mcp/Tools/QueueTools.php` (`claim_queue_item`), `Mcp/Tools/PostTools.php` (`get_post`).
- `app/src/Domain/MediaService.php` (`get`, `filePath`, `variants`), `docs/models.md` → `media` (Varianten-Format), `StatsService`, `app/src/Site/Builder.php` (`build`), `bin/dispatch` (Befehl `build`).
- `app/mcp/workflow-*.md` (Phase 4).
- Fehlerklassen geprüft (Vault): keine einschlägig.

## AK der Phase

1. `get_media(uid, view: true)` liefert Metadaten plus einen Bild-Block, Kante ≤ 1024 px; `view: false` nur Metadaten. Video/Audio/Dokument: nur Metadaten mit `note: "Signierte Links folgen mit Meilenstein 4"`.
2. `claim_queue_item` liefert zu einem Auftrag mit 8 Bildern genau 6 Bild-Blöcke und nennt die übrigen UIDs im Text (`images_not_shown`).
3. `import_media_from_url` lädt ein Bild von Wikimedia Commons; eine URL auf `127.0.0.1` wird abgewiesen (lokal mit `DISPATCH_ALLOW_PRIVATE_IPS` leer). `upload_media` mit SVG → `isError`.
4. `get_stats` und `get_post_stats` liefern die Werte aus Phase 3; `rebuild_site` liefert `written, unchanged, deleted, ms`; ein zweiter Aufruf direkt danach `written: 0`.
5. `resources/list` nennt `dispatch://documents/{key}` für alle fünf Dokumente und `dispatch://style-guide`; `resources/templates/list` nennt `dispatch://posts/{id}`; `resources/read` liefert Markdown (`text/markdown`) bzw. JSON (`application/json`); unbekannte URI → `-32602`.
6. `prompts/list` nennt `redaktion_geplant`, `redaktion_queue`, `thema_einreichen` (Argument `thema`, Pflicht); `prompts/get` liefert eine `user`-Nachricht mit dem Workflow-Text.
7. Danach `tools/list` = genau 33 Tools (Zahl im Report-Back).

## Checkliste

- [ ] `Mcp/ImageBlocks.php`: `public static function forMedia(MediaService $m, array $media, int $max = 6): array` → `['blocks' => list<array>, 'skipped' => list<string>]`. Datei-Wahl je Bild: größte Variante mit `w ≤ 1024` im Format `jpg` oder `png`; gibt es keine, das Original, wenn `width ≤ 1024` und `mime` ∈ jpeg/png/webp; sonst überspringen. Pfad über `MediaService::filePath`.
- [ ] `QueueTools::claim_queue_item` um Bild-Blöcke ergänzen (Anhänge mit `kind = image`), Text-JSON um `images_not_shown`.
- [ ] `Mcp/Tools/MediaTools.php`: `import_media_from_url`, `upload_media` (`base64_decode(…, true)`; ungültig → `invalid_argument`), `update_media`, `list_media`, `get_media`.
- [ ] `Mcp/Tools/StatsTools.php`: `get_stats`, `get_post_stats`.
- [ ] `Mcp/Tools/SiteTools.php`: `rebuild_site` → `(new Builder($config, $pdo))->build()`; läuft gerade ein Build (Sperre), Meldung als `isError` `build_locked` (Ausnahme-Typ aus `Builder::build` übernehmen).
- [ ] `Mcp/Resources.php`: `list(): array`, `templates(): array`, `read(string $uri): array` (Inhalte `[{uri, mimeType, text}]`); Dokumente über `DocumentService::get`, Artikel über `PostService::get` (wie `get_post` mit HTML). In `McpController` verdrahten, Scope `blog:read`.
- [ ] `Mcp/Prompts.php`: `list(): array`, `get(string $name, array $args): array` → `{description, messages: [{role: "user", content: {type: "text", text}}]}`. `redaktion_geplant` = „Rufe get_editorial_brief(mode=\"scheduled\") auf und folge dem Workflow.“ + Inhalt `workflow-scheduled.md`; `redaktion_queue` analog mit `queue`; `thema_einreichen` = „Setze dieses Thema mit add_to_queue auf die Queue: <thema>. Frage nach Priorität und Tiefe nur, wenn der Text sie nicht nennt.“ Fehlendes `thema` → `-32602`.
- [ ] Doku: `docs/code-map.md` (`ImageBlocks`, `Resources`, `Prompts`, übrige Tools-Klassen).

## Report-Back
