# Findings — Meilenstein 3

Format: `- [ ] → Phase N: <Erkenntnis>` (offen) bzw. `- [x] → Phase N: <Erkenntnis> — <wie erledigt>`.

## Spezifikations-Abgleich 2026-07-28 / 2025-11-25 (Phase 2, Schritt 1)

Quellen (am 2026-11-10 gelesen): `basic/transports/streamable-http`, `basic/versioning`, `basic/index` (`_meta`, Fehlercodes), `server/discover`, `server/utilities/caching`, `server/tools`, 2025-11-25 `basic/lifecycle`. Jede Zeile „wie Kontrakt“ oder „abweichend“; Abweichungen sind in Phase 2 umgesetzt und in der README nachgezogen.

- [x] → Phase 2: `MCP-Protocol-Version` (Header) und `_meta["io.modelcontextprotocol/protocolVersion"]` — wie Kontrakt (Namen stimmen). Abweichend: Der Header ist bei Modern auf **jeder** Anfrage Pflicht; fehlt er, ist das ein Fehler (nur Server, die ältere Clients bedienen, dürfen „kein Header“ als `2025-03-26` lesen — das tun wir für Legacy-Anfragen ohne `_meta`-Version).
- [x] → Phase 2: `Mcp-Method` (alle Anfragen) und `Mcp-Name` (`tools/call`, `prompts/get` = `params.name`, `resources/read` = `params.uri`) — wie Kontrakt. Zusätzlich: Wert darf als `=?base64?…?=` kommen (UTF-8, Base64); der Server dekodiert vor dem Vergleich. Umgesetzt in `Protocol`.
- [x] → Phase 2: Fehlercode bei Header ↔ Body-Abweichung oder fehlendem/ungültigem Pflicht-Header: **abweichend** `-32020` (`HeaderMismatch`), HTTP 400 — nicht `-32600`.
- [x] → Phase 2: Unbekannte Version: **abweichend** `-32022` (`UnsupportedProtocolVersion`), HTTP 400, `data` = `{supported: [alle vier], requested: <Wert>}` — nicht `-32600`.
- [x] → Phase 2: Modern verlangt `_meta["io.modelcontextprotocol/protocolVersion"]` und `_meta["io.modelcontextprotocol/clientCapabilities"]` im Body (`clientInfo` optional); fehlt eines → **neu** `-32602`, HTTP 400.
- [x] → Phase 2: Unbekannte Methode bei Modern: **abweichend** HTTP 404 mit `-32601` (Legacy: HTTP 200 mit `-32601`, wie JSON-RPC üblich).
- [x] → Phase 2: Ergebnisse tragen bei Modern `resultType: "complete"` (**neu**, Pflicht); Legacy nicht.
- [x] → Phase 2: `server/discover`: `serverInfo` steht bei Modern in `result._meta["io.modelcontextprotocol/serverInfo"]` (nicht als Feld `serverInfo`); Felder `supportedVersions`, `capabilities`, `instructions` wie Kontrakt; **neu** `ttlMs`/`cacheScope` auch hier (Pflicht für `server/discover`). Bei Legacy-`initialize` bleibt `serverInfo` ein Feld. Modern-Ergebnisse tragen die Server-Info zusätzlich in `_meta` (SHOULD).
- [x] → Phase 2: `ttlMs` (Ganzzahl ms) und `cacheScope` (`public`|`private`) — Namen wie Kontrakt; Pflicht für `server/discover`, `tools/list`, `prompts/list`, `resources/list`, `resources/templates/list`, `resources/read`. Wir setzen `300000`; `private` für alles, was vom Token abhängt, `public` nur für `server/discover`.
- [x] → Phase 2: `notifications/*` → 202 ohne Body — wie Kontrakt; Header-Pflichten für Notifications sind in der Spezifikation nicht definiert, wir prüfen dort nichts.
- [x] → Phase 2: Legacy `initialize` — Antwortfelder (`protocolVersion`, `capabilities`, `serverInfo` mit `name/title/version`, `instructions`) wie Kontrakt. Aushandlung wie Kontrakt (gleiche Version, sonst eine unterstützte; wir nehmen `2025-11-25`).
- [x] → Phase 2: `GET`/`DELETE` → 405; `Mcp-Session-Id` ignorieren, nie vergeben — wie Kontrakt.
- [x] → Phase 2: **neu** `Origin`-Prüfung (Spezifikation: MUST, DNS-Rebinding): Ist `Origin` gesetzt und gehört nicht zum selben Host wie `Host`, antwortet der Endpunkt mit 403. Claude Code und der Inspector (Proxy) senden keinen.
- [x] → Phase 2: Tool-Ergebnis `content`/`isError`, Unbekanntes Tool `-32602`, fachliche Fehler als `isError: true` — wie Kontrakt. Parameterlose Tools: `inputSchema` = `{"type":"object","additionalProperties":false}` (empfohlene Form) — gilt für alle Tools in Phase 4/5.
- [x] → Phase 2: Nicht umgesetzt, bewusst: `subscriptions/listen`, MRTR (`input_required`), `x-mcp-header`, `structuredContent`/`outputSchema`, SSE-Antworten — Server antwortet immer mit einem JSON-Objekt, `listChanged` wird nicht angeboten.
- [ ] → Phase 5: `resources/read`, `resources/list`, `resources/templates/list`, `prompts/list` müssen bei Modern `ttlMs`/`cacheScope`/`resultType` tragen — die Hülle im `McpController` setzt das für alle Modern-Ergebnisse; die Methoden liefern nur ihren Kern. Unbekannte Resource: `-32602` (nicht `-32002`, das ist ab 2026-07-28 verboten).
- [x] → Phase 4: `BriefingService::brief` lässt `workflow` leer (`''`); der Text aus `app/mcp/workflow-<mode>.md` gehört in `BriefingTools` (Modus = aufgelöstes `mode`). `queue.next` trägt zusätzlich `id`.
- [ ] → Meilenstein 4 (Zähl-Aufruf der Statistik): `StatsService` summiert `stats_dimensions` ohne Filter über alle Pfade. Jedes Ereignis darf deshalb nur einmal gezählt werden — entweder je Pfad oder seitenübergreifend (`path = ''`), nicht beides. Suchen tragen die Trefferzahl als `extra.hits` (Zahl); „ohne Ergebnis“ im Briefing heißt `hits = 0` in jeder Zeile.
- [ ] → Phase 5: `claim_queue_item` liefert Bild-Anhänge noch ohne Bild-Blöcke; Phase 5 ergänzt sie über `ImageBlocks`.
- [ ] → Phase 5/6: `add_to_queue` mit `data_base64` speichert über `MediaService::store(…, 'upload', …)`; für fremde Bilder fehlt dann die Herkunftsseite (`source_url`), für eigenes Material nicht (`own_material`). Lokale Bild-Importe per HTTPS scheitern ohne CA-Bündel für PHP-curl; Test lief über `DISPATCH_ALLOW_PRIVATE_IPS` und HTTP (nur in `.env.local`, wieder entfernt).
- [ ] → Phase 6: `save_category` mit Nicht-Inhaber-Token legt beim Anlegen nur einen Änderungsvorschlag an (Kategorie existiert erst nach Freigabe); Doku/Tests der Abnahme entsprechend wählen.
