# Artikel sortieren und Vorschläge bearbeiten

## Überblick

| Phase | Thema | Status | Komplexität |
|---|---|---|---|
| 1 | Vorschläge bearbeiten und sortieren | complete | heikel |
| 2 | Veröffentlichungsplan sortieren | complete | heikel |
| 3 | Oberfläche, Agenten-Werkzeuge und Dokumentation abschließen | complete | standard |

## Kontrakt

- Die Reihenfolge eingereichter Themen wird in der Queue-Domäne dauerhaft gespeichert. Der Inhaber und verbundene Agenten sehen und ändern dieselbe Reihenfolge.
- Sortieroperationen übertragen die vollständige geordnete Liste der betroffenen IDs. Die Domain validiert, dass sie exakt der aktuell sortierbaren Menge entspricht; Teilmengen oder fremde IDs werden zurückgewiesen.
- Queue-Änderungen und Änderungsvorschläge bleiben getrennte Domänen: „Vorschläge“ meint die eingereichten Artikelthemen der Queue. Inhaber können einen noch offenen Queue-Eintrag bearbeiten; Agenten dürfen nur Einträge bearbeiten, die ihre Berechtigungen zulassen.
- Der Veröffentlichungsplan hat eine gespeicherte Reihenfolge für wartende Queue-Artikel. Feste Termine und bereits veröffentlichte Artikel sind nicht Teil der umsortierbaren Liste. Bei der Freigabe verwendet die Zeitplanung die gespeicherte Reihenfolge.
- MCP-Werkzeuge stellen Agenten dieselben fachlichen Aktionen bereit wie die Redaktions-API. Agenten können keine Freigabe oder Veröffentlichung auslösen.

## Finale Akzeptanzkriterien

- Inhaber können eingereichte Vorschläge bearbeiten und ihre Reihenfolge per Drag-and-drop ändern; Änderungen bleiben nach erneutem Laden erhalten.
- Inhaber können wartende Einträge im Veröffentlichungsplan per Drag-and-drop neu ordnen. Die angezeigten voraussichtlichen Termine folgen der neuen Reihenfolge.
- Verbundene Agenten können Queue-Einträge bearbeiten und die sortierbaren Listen über MCP neu ordnen; ungültige oder veraltete Listen werden verständlich abgewiesen.
- Tastaturbedienung bietet eine gleichwertige Möglichkeit, Einträge nach oben oder unten zu verschieben.
- Die Zeitplanung und Veröffentlichungsregeln bleiben erhalten; die gespeicherte Reihenfolge führt nicht zu einer Veröffentlichung durch ein Agenten-Token.

## Risiken und Entscheidungen

- 🟡 Queue-Reihenfolge ist heute implizit über Einreichungszeitpunkte bestimmt. Speicherung und Verhalten bei gleichzeitigem Agenten-/Inhaber-Update müssen in Phase 1 gegen QueueRepository und Transaktionsmuster geprüft werden.
- 🟡 Der Veröffentlichungsplan mischt automatisch terminierte Queue-Artikel und feste Termine. Phase 2 muss die Grenze der umsortierbaren Gruppe in UI und Domain klar halten.
- Alternative: nur Drag-and-drop im Veröffentlichungsplan und unveränderte Reihenfolge der Vorschläge. Das vermeidet doppelte Sortierflächen, erfüllt aber den Wunsch, bereits eingereichte Themen bei der Verteilung thematisch umzustellen, nicht.

## Konfidenz-Ausweis

- Reihenfolge und Parallelitätsverhalten: `QueueRepository.php`, Migrationen und `ScheduleRepository.php` prüfen; bei persistierter Reihenfolge mit atomarer Validierung umsetzen.
- MCP-Scope der Sortierwerkzeuge: `QueueTools.php`, `ScheduleTools.php` und `Registry.php` prüfen; vorhandene Schreibrechte als Maßstab verwenden.

## Summary

Gespeicherte und zuganglich bedienbare Sortierung fur Queue-Vorschlage und den Warteschlangen-Teil des Veroffentlichungsplans. Offene Vorschlage koennen bearbeitet werden; MCP gibt passende Sortier- und Bearbeitungswerkzeuge frei, ohne Freigabe zu erlauben.


## Files touched

Domain, Migration, API, MCP, Redaktionsoberflache, Projektdokumentation und Entscheidung ADR-036.


## Commits

Feature commit on branch feature/article-ordering (this commit).
## Deviations from plan

Kategorien lassen sich in der Inhaberoberflache ebenfalls bearbeiten; Agenten koennen nur eigene offene Queue-Vorschlaege bearbeiten.


## Follow-ups

- Nach Release Migration 017 ausfuehren.
- Sichtpruefung im Redaktionsdialog beim naechsten lokalen/Live-Browserlauf.
