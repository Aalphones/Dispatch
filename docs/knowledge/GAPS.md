# Knowledge Gaps — DISPATCH

Bemerkte, noch nicht geklärte Wissenslücken. Was Meilenstein 0 misst, steht in `docs/PROJECT.md` → Offene Fragen.

- **Ob Claude-Apps sich per CIMD an einer Pfad-Adresse (`/dispatch/mcp`) anmelden** — die Discovery liegt an der Wurzel, die Ressource unter einem Pfad; Meilenstein 5 prüft es.
- **Kategorie-Anlegen durch Agenten** — `save_category` mit Nicht-Inhaber-Token erzeugt nur einen Änderungsvorschlag; ohne Freigabe-Oberfläche (Meilenstein 4) bleibt Strato ohne Kategorien, bis der Inhaber `category:create --remote` nutzt.
- **Statistik-Zählung** — `StatsService` summiert `stats_dimensions` ohne Filter; jedes Ereignis darf nur einmal gezählt werden (je Pfad oder seitenübergreifend), Entscheidung beim Zähl-Aufruf (Meilenstein 4).
- **Bild-Importe per Upload** — `add_to_queue` mit `data_base64` speichert ohne Herkunftsseite (`source_url`); für fremdes Material fehlt sie.
- **Queue-Auftrag ohne Kategorie** — `submit_post` übernimmt die Kategorie des Artikels nicht in den Auftrag (`category_id` bleibt `null`), und `editor_notes` behalten veralteten Text; im Abnahmelauf aufgefallen.
- **Bild-Metadaten ungeprüft** — der Server prüft Urheber und Lizenz eines importierten Bildes nicht gegen die Quelle (Abnahmelauf: aus dem Gedächtnis des Agenten).
