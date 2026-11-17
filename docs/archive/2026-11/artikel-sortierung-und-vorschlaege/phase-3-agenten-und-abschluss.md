# Phase 3: Agenten-Werkzeuge und Abschluss

## Kontext

- `app/src/Mcp/Tools/QueueTools.php`, `ScheduleTools.php`, Tool-Registry und Scopes
- `app/mcp/instructions.md`, `workflow-queue.md`, `workflow-scheduled.md`
- `docs/code-map.md`, `docs/models.md`, `docs/routes.md`, `docs/decisions/`
- `docs/conventions/php.md`, `docs/conventions/frontend.md`
- Fehlerklassen geprüft: Vault-Dateien waren in dieser Umgebung nicht lesbar; Projekt-Conventions und existierende MCP-Schema-/Scope-Muster nutzen.

## Akzeptanzkriterien

- Verbundene Agenten können dieselben sortierbaren Listen lesen und ordnen sowie offene Queue-Vorschläge bearbeiten.
- Tool-Beschreibungen erklären, dass die vollständige ID-Reihenfolge zu übergeben ist und wie Konflikte aufgelöst werden.
- MCP-Rechte ermöglichen keine Freigabe, Übernahme oder Veröffentlichung.
- Code-Map, Modelle, Routen und Agenten-Abläufe spiegeln das ausgelieferte Verhalten wider.

## Checkliste

- [x] MCP-Tool-Schemas und Anweisungen für Agenten an die implementierten Aktionen anpassen.
- [x] Scope-Grenzen anhand vorhandener Schreibwerkzeuge prüfen und erforderliche Änderungen dokumentieren.
- [x] Relevante Projektübersichten und ADR bei neuer Architekturentscheidung nachführen.
- [x] Akzeptanzkriterien am Diff abgleichen und `STATE.md` aktualisieren.

## Report-Back

Implemented; static syntax checks passed.
