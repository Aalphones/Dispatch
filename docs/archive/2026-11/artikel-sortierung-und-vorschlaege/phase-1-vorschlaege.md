# Phase 1: Vorschläge bearbeiten und sortieren

## Kontext

- `app/src/Domain/QueueService.php`, `QueueRepository.php`, `app/src/Api/QueueController.php`, `app/src/Mcp/Tools/QueueTools.php`
- `app/theme/redaktion/queue.js`, `post.js`, `redaktion.css`, `app/src/Api/OverviewController.php`
- `app/migrations/` und `docs/models.md`
- `docs/conventions/php.md`, `docs/conventions/frontend.md`
- Fehlerklassen geprüft: Vault-Dateien waren in dieser Umgebung nicht lesbar; die Projekt-Conventions und bestehenden Transaktionsmuster sind maßgeblich.

## Akzeptanzkriterien

- Noch offene Queue-Einträge können bearbeitet werden; Änderungen verwenden die bestehende Feldvalidierung und Auditerfassung.
- Der Inhaber kann Queue-Einträge mit Drag-and-drop sortieren. Speichern und erneutes Laden erhalten die Reihenfolge.
- MCP bietet passende Bearbeitungs- und Sortierwerkzeuge; Sortierung akzeptiert nur die vollständige aktuell sortierbare Menge und weist veraltete Zustände verständlich zurück.
- Eine tastaturbedienbare Nach-oben/Nach-unten-Alternative ist vorhanden.

## Checkliste

- [x] Queue-Reihenfolge, Statusregeln und konkurrierende Änderungen anhand Repository/Migrationen prüfen.
- [x] Reihenfolge persistieren und atomar validierte Domain-Aktionen für Bearbeiten und Sortieren ergänzen.
- [x] Redaktionsoberfläche um Bearbeiten und zugängliches Sortieren erweitern.
- [x] API und MCP mit den passenden Akteur-/Scope-Regeln anbinden.
- [x] `docs/models.md`, `docs/code-map.md` und MCP-Dokumentation aktualisieren.

## Report-Back

Implemented; static syntax checks passed.
