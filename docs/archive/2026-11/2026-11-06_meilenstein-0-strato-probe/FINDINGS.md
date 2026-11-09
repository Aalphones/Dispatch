# Findings — Meilenstein 0

Format: `- [ ] → Phase N: <Erkenntnis>` (offen) · `- [x] → Phase N: <Erkenntnis> — erledigt in <Commit/Datei>`

- [ ] → Phase 5: Migrator und Deploy-Erfolgspfad (200, zweiter Lauf `migrations: []`) waren lokal nicht prüfbar (kein MySQL) — beim ersten Strato-Deploy beobachten; `schema_migrations` muss danach existieren.
- [ ] → Phase 4: Der Rauchtest nach dem Deploy sollte den Deploy-Aufruf mit `Authorization: Bearer` schicken; bei 401 mit Token auf `X-Dispatch-Token` zurückfallen (Strato kann den Header verschlucken).
