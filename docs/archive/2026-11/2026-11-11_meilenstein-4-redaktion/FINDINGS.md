# Findings — Meilenstein 4

Format: `- [ ] → Phase N: <Erkenntnis>` (offen) bzw. `- [x] → Phase N: <Erkenntnis> — <wie erledigt>`.

- [ ] → Phase 7: `vorschau.js` ruft die API unter `/dispatch/api/preview?id&exp&sig` (nicht unter dem Seitenpfad `/vorschau/`), mit JWT unter `/dispatch/api/posts/{id}/preview`. Antwort: `post`, `html`, `form{errors,warnings}` (je `code`, `message`), `has_live`, bei JWT zusätzlich `stats: null`. Ohne Veröffentlichung zeigt die Vorschau als Datum die letzte Änderung (`published_at` fehlt im Arbeitsstand).
- [ ] → Phase 7: Aktionsantworten (`approve`, `unpublish`, `changes/{id}/apply`) tragen `rebuilt` und `rebuild_error`; `approve`/`return`/`unpublish` liefern `post` ohne HTML (`id, slug, title, status, revision, published_revision, published_at, review_note`).
- [ ] → Phase 6: Fehlerstatus der Redaktions-API: 400 `invalid_value`/`invalid_json`, 404 `not_found`, sonst 409 mit `error` = Fehlercode und `details`; 401 ohne gültiges JWT.
