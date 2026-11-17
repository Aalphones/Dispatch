# Phase 2: Veröffentlichungsplan sortieren

## Kontext

- `app/src/Domain/ScheduleService.php`, `ScheduleRepository.php`, `app/src/Api/ScheduleController.php`
- `app/src/Mcp/Tools/ScheduleTools.php`, `app/theme/redaktion/schedule.js`
- Phase 1 Kontrakt und Queue-Reihenfolge
- `docs/conventions/php.md`, `docs/conventions/frontend.md`
- Fehlerklassen geprüft: Vault-Dateien waren in dieser Umgebung nicht lesbar; die Projekt-Conventions und bestehenden Zeitplanregeln sind maßgeblich.

## Akzeptanzkriterien

- Wartende Queue-Artikel im Veröffentlichungsplan lassen sich per Drag-and-drop sortieren; feste Termine und veröffentlichte Einträge sind nicht verschiebbar.
- Neue Reihenfolge wird gespeichert, erneut eingelesen und zur Berechnung der nächsten Termine verwendet.
- Veraltete oder unvollständige Sortierlisten werden abgewiesen, ohne die Reihenfolge teilweise zu verändern.
- Eine tastaturbedienbare Alternative kann Einträge nach oben und unten verschieben.

## Checkliste

- [x] Die Sortiergruppe und Wechselwirkung mit fälligen/verstrichenen Terminen im aktuellen Planmodell verifizieren.
- [x] Schedule-API und Service an die Queue-Reihenfolge anbinden und atomare Aktualisierung sicherstellen.
- [x] Veröffentlichungsplan-Oberfläche um Drag-and-drop und Tastaturalternative erweitern.
- [x] Auswirkungen auf MCP-Lese-/Schreibwerkzeuge und Dokumentation abgleichen.
- [x] Planungs- und Queue-Dokumentation aktualisieren.

## Report-Back

Implemented; static syntax checks passed.
