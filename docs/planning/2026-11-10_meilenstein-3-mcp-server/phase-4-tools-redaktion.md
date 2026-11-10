# Phase 4 — Tools: Kontext, Queue, Artikel; Anweisungen und Workflows

Rating: standard

## Kontext (vorher lesen)

- `README.md` dieses Plans → Kontrakt: „Endpunkt“ (Tool-Ergebnis, Fehler), „Tool-Katalog“.
- `app/src/Mcp/Registry.php`, `Tool.php`, `ToolResult.php`, `McpController.php` (`services`), `Mcp/Tools/DocumentTools.php` (Muster eines Tools aus Phase 2).
- Services: `DocumentService`, `CategoryService` (Felder in `normalizeForCreate` / `normalize`), `SettingService`, `ChangeService`, `QueueService` (Eingaben in `normalizeNew`, `normalizeMedia`), `PostService` (Eingaben von `create`/`update`), `MediaService` (`store`, `importFromUrl`), `BriefingService`.
- Fachkonzept → „Tool-Katalog“ (inklusive „Felder von create_post“), „Redaktions-Workflows“ (drei Abläufe), „Qualität“ → „Redaktionsrichtlinien“, „Quellen und Belege“, „Bilder und Lizenzen“, „Statisches Frontend und SEO“ → „Anatomie eines Artikels“, „Gestaltungsmittel für den Agenten“.
- `app/src/Util/Sanitizer.php` (erlaubte Elemente, Klassen, Attribute), `app/src/Site/ContentRenderer.php` (`data-media`, Galerien, `data-embed`, Belege, Kurz-gesagt), `app/src/Util/FormCheck.php` (Regelcodes) — Grundlage für `style-guide.md`.
- Fehlerklassen geprüft (Vault): keine einschlägig.

## AK der Phase

1. Jedes Tool dieser Phase steht mit Titel, Beschreibung, Input-Schema und Annotationen nach Katalog in `tools/list`. Jede Beschreibung sagt in 2–4 Sätzen, wann das Tool dran ist, gibt ein Beispiel-Argument und nennt das typische Folge-Tool.
2. Jedes Tool einmal erfolgreich per `curl` gegen den Dev-Server aufgerufen (Ergebnis im Report-Back, eine Zeile je Tool), dazu je Tool ein typischer Fehler (`isError: true` mit hilfreicher Meldung).
3. `add_to_queue` mit `attachments: [{"url": "<Bild-URL>"}, {"data_base64": "…", "filename": "x.png", "own_material": true}, {"uid": "<vorhandene UID>"}]` legt Medien an und hängt sie an; ein fehlerhafter Anhang bricht ab, bevor der Auftrag entsteht.
4. `update_settings` mit öffentlichem Wert → Ergebnis `change_id`, nichts Öffentliches geändert; mit `min_sources` → `isError` `not_changeable`. `update_document('about', …)` → Vorschlag; `update_document('preferences', …)` → direkt mit Revision.
5. `get_editorial_brief` liefert die Felder aus Phase 3 plus `workflow` (Text des aufgelösten Modus); `get_style_guide` liefert den Style-Guide.
6. `delete_post` ohne `confirm: true` → `isError` `confirm_required`, Artikel bleibt.

## Checkliste

- [ ] `McpController::services` vervollständigen (alle Einträge aus Phase 2, Checkliste „Services einmal pro Anfrage“).
- [ ] `Mcp/Tools/BriefingTools.php`: `get_editorial_brief` (setzt `workflow` = Inhalt von `app/mcp/workflow-<mode>.md`), `get_style_guide` (Inhalt `app/mcp/style-guide.md` als `{"style_guide": "<Markdown>"}`).
- [ ] `Mcp/Tools/DocumentTools.php`: `get_document`, `update_document`.
- [ ] `Mcp/Tools/CategoryTools.php`: `list_categories` (Kategorien + `categoryStats`), `save_category` (Argument `parent` = Slug → `parent_id` über `CategoryService::get`; übrige Felder in der Form, die `CategoryService::normalizeForCreate`/`normalize` erwartet).
- [ ] `Mcp/Tools/SettingTools.php`: `get_site_info`, `update_settings`. `Mcp/Tools/ChangeTools.php`: `list_changes` (`all` → `ChangeService::list(null)`).
- [ ] `Mcp/Tools/QueueTools.php`: `list_queue`, `add_to_queue`, `claim_queue_item`, `release_queue_item`. `add_to_queue`: Anhänge der Reihe nach in Medien verwandeln (`url` → `MediaService::importFromUrl`, `data_base64` + `filename` → `MediaService::store(…, 'upload', …)`, `uid` → unverändert), dann `QueueService::add` mit `media: [{uid, note, own_material}]` und `category` (Slug) in der Form, die `normalizeNew` erwartet. `claim_queue_item` gibt Bild-Anhänge in dieser Phase noch ohne Bild-Blöcke zurück (Phase 5 ergänzt `ImageBlocks`).
- [ ] `Mcp/Tools/PostTools.php`: `list_posts`, `search_posts`, `get_post` (`include_html` Default `false` → `content_html` weglassen; Antwort mit Quellen, Kategorien, `review_note`, Formprüfung nicht enthalten), `create_post`, `update_post` (Antworten: Artikel + `sanitizer_notes` + `form_check` mit `errors`, `warnings`, `metrics` — Felder so übernehmen, wie `PostService::create`/`update` und `FormCheckResult` sie liefern), `validate_post`, `submit_post`, `withdraw_post`, `delete_post`, `list_post_revisions`, `restore_post_revision`. Kein `preview_url` (ADR-018).
- [ ] `app/mcp/workflow-scheduled.md`, `workflow-queue.md`, `workflow-returned.md`: die drei Abläufe aus dem Fachkonzept → „Redaktions-Workflows“ als nummerierte Schritte mit den echten Tool-Namen dieses Katalogs; Feuilleton → DISPATCH; Schritt „Vorschau-Link“ ersetzen durch „Artikel-ID und Titel“; im Queue-Ablauf: „Video und Audio: nur Metadaten, Inhalt aus dem Auftragstext entnehmen“; im Ablauf `returned`: „nur ändern, was `review_note` verlangt“. Am Ende jedes Ablaufs der Abschlussbericht (Titel, Artikel-ID, Zahl der Quellen, offene Punkte).
- [ ] `app/mcp/style-guide.md`: erlaubte HTML-Elemente und Klassen (aus `Sanitizer`), Medien-Syntax (`data-media`, Galerien, `data-embed` — aus `ContentRenderer`), Belegsyntax mit `ref`, Layouts (`standard, feature, longform, briefing`) mit je einem Satz, Akzentfarbe `#rrggbb`, Pflichtangaben laut `FormCheck` mit Regelcode. Jede Angabe muss im Code belegt sein; nichts erfinden, was Sanitizer oder Renderer nicht kennen.
- [ ] Doku: `docs/code-map.md` (Tools-Klassen, `app/mcp/`-Texte).

## Report-Back
