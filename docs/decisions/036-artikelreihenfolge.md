# ADR-036: Gespeicherte Reihenfolge für Queue und Veröffentlichungsplan

- **Kontext:** Reihenfolge ergab sich bisher aus Priorität/Alter bei Vorschlägen und Freigabezeit bei geplanten Artikeln. Agenten und Inhaber brauchen eine gemeinsame, änderbare Reihenfolge.
- **Optionen:** Zeitstempel umschreiben; Positionen je Domäne speichern; nur die Oberfläche sortieren.
- **Entscheidung:** `queue_items.sort_order` und `posts.schedule_order` speichern Reihenfolgen unabhängig voneinander. Eine Sortieraktion muss exakt alle aktuell offenen beziehungsweise wartenden IDs enthalten und läuft mit Zeilensperren in einer Transaktion. Feste Termine bleiben außerhalb der Veröffentlichungsreihenfolge.
- **Konsequenzen:** Neue Einträge werden angehängt; die bisherige Reihenfolge wird in Migration 017 übernommen. MCP-Agenten mit `blog:write` können offene Vorschläge sortieren und eigene offene Vorschläge bearbeiten sowie bereits freigegebene Queue-Artikel neu ordnen. Die Werkzeuge lösen keine Freigabe oder Veröffentlichung aus.
