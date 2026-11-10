# 016 — MCP-Protokollschicht und Registry

**Status:** Akzeptiert (2026-11-10)

## Kontext

Der Endpunkt `/dispatch/mcp` muss die Revision 2026-07-28 (zustandslos, jede Anfrage trägt Version und Fähigkeiten) und die älteren Clients mit `initialize` (2025-11-25, 2025-06-18, 2025-03-26) bedienen. Auf Shared Hosting gibt es keine Sitzungen im Speicher; jede Anfrage ist ein eigener PHP-Aufruf.

## Entscheidung

- **Zwei Generationen nebeneinander, Erkennung pro Anfrage:** Die Methode `initialize` wählt Legacy (Antwort-Version = angefragte, sonst `2025-11-25`, nie eine `Mcp-Session-Id`). Sonst entscheidet der Header `MCP-Protocol-Version`, ersatzweise `params._meta["io.modelcontextprotocol/protocolVersion"]`; ohne beides gilt Legacy `2025-03-26`. Modern verlangt zusätzlich `Mcp-Method` (und `Mcp-Name` bei `tools/call`, `prompts/get`, `resources/read`) sowie `_meta` mit Version und `clientCapabilities`.
- **Fehlerformen nach Spezifikation 2026-07-28:** Header ↔ Body abweichend oder fehlend `-32020` (400); unbekannte Version `-32022` mit `data.supported`/`requested` (400); fehlende `_meta`-Pflichtfelder `-32602` (400); unbekannte Methode `-32601` (Modern 404, Legacy 200); fehlender Scope `-32003` (403 mit `WWW-Authenticate: Bearer error="insufficient_scope"`); Parse-Fehler `-32700` und Batch `-32600` (400). Unerwartete Ausnahmen: Logeintrag, `-32603` ohne Stacktrace (500). Fachliche Fehler und falsche Argumente sind Tool-Ergebnisse mit `isError: true`.
- **Modern-Ergebnisse** tragen `resultType: "complete"` und die Server-Info in `_meta`; `server/discover`, `tools/list`, `resources/*`, `prompts/list` zusätzlich `ttlMs: 300000` und `cacheScope` (`private`, weil die Listen vom Token abhängen; `server/discover` `public`). Legacy-Ergebnisse bleiben unverändert.
- **Registry als einzige Quelle** für `tools/list`, Scope-Prüfung und Prüfung der Pflichtargumente und Grundtypen; ein Tool ohne Scope oder ohne eine der fünf Annotationen kann nicht registriert werden (`LogicException`). Services werden einmal pro Anfrage gebaut und den Tool-Klassen übergeben.
- **Origin-Prüfung:** Ein gesetzter `Origin`-Header, der nicht zum `Host` passt, wird mit 403 abgewiesen (Schutz gegen DNS-Rebinding, Pflicht der Spezifikation). Claude Code sendet keinen.
- **Texte für Agenten als Dateien** unter `app/mcp/` (Server-Anweisungen, Stilführer, Workflows). Alternative: als Dokumente in der Datenbank — verworfen, weil sie zum Code-Stand der Tools passen müssen und mit ihm zusammen ausgeliefert werden.
- **Nicht umgesetzt:** `subscriptions/listen`, mehrstufige Anfragen (`input_required`), `x-mcp-header`, `structuredContent`, SSE-Antworten; der Server antwortet immer mit einem JSON-Objekt und bietet kein `listChanged` an.

## Abweichungen vom Kontrakt des Plans

Die Spezifikation schlägt den Plan: `-32020`/`-32022` statt `-32600`, `resultType`, Server-Info in `_meta` bei `server/discover`, 404 bei unbekannter Methode (Modern), Origin-Prüfung. Einzelnachweis: `FINDINGS.md` des Meilensteins.

## Konsequenzen

- Clients, die ältere Entwürfe der Revision 2026-07-28 sprechen, scheitern an den Pflicht-Headern; das ist gewollt (die Spezifikation verlangt sie).
- Neue Tools sind eine Klasse `Tools/<X>Tools` mit `register(Registry, services)`; `McpController::registry()` ruft sie auf.
