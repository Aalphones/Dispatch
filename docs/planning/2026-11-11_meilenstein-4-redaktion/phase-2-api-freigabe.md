# Phase 2 — API: Übersicht, Vorschau, Freigabe, Vorschläge, Neubau

## Kontext

- `README.md` → „Kontrakt“: „Endpunkte“ (Phase 2), „Neubau“, „Signierte Vorschau-Links“.
- `docs/decisions/012-artikel-statusmaschine-live-lesepfad.md`, `009-…`, `013-…`, `014-builder-vollbau-manifest.md`.
- Code: `app/src/Domain/PostService.php` (`list`, `get`, `snapshot`, `validate`, `approve`, `returnToAgent`, `unpublish`, `discard`), `app/src/Domain/ChangeService.php`, `app/src/Domain/QueueService.php` (`markDone` — prüfen, ob `approve` ihn schon aufruft; falls nicht, hier ergänzen), `app/src/Site/Builder.php` (`build`, `collectArticles`, `page`, `render`), `app/src/Site/SiteData.php`, `app/templates/article.php`, `app/templates/layout.php`, `app/config.php` (`builder_top_level`), `app/src/Mcp/Tools/PostTools.php` (`submit_post`), `app/src/Mcp/Tools/SiteTools.php` (Muster für den Neubau-Aufruf).
- Chesterton: `livePosts()` ist die einzige Quelle der Website (ADR-012). Die Vorschau liest den **Arbeitsstand** und schreibt nie in die Wurzel; sie nutzt nur die Darstellung.

## AK der Phase

1. `GET overview` zählt: `review` = Artikel mit Status `review`; `returned` = Status `draft` mit nicht leerem `review_note`; `changes` = Vorschläge `pending`; `queue_open` = Queue-Aufträge `open`.
2. `GET posts?status=review,draft` liefert die Felder des Kontrakts; `form` aus `PostService::validate` (nur Anzahl Fehler/Warnungen), `sources` = Anzahl Quellen. Unbekannter Status → 400 `invalid_value`.
3. **Eine Darstellung:** Die Abbildung „Artikel-Datensatz → Variablen für `templates/article.php`“ wird aus `Builder::collectArticles` in eine öffentliche Methode `Builder::articleMain(array $post): string` gezogen, die den Inhalt von `<main>` liefert. `collectArticles` nutzt sie danach selbst (die gebaute Website ist byte-gleich vorher/nachher: `php bin/dispatch build`, dann Prüfsummen von `artikel/*/index.html` vergleichen). Die Vorschau ruft `articleMain` mit dem Arbeitsstand aus `PostService::snapshot` (gleiche Form wie der Live-Snapshot; Abweichungen in `FINDINGS.md` notieren und angleichen).
4. `GET posts/{id}/preview` (JWT) und `GET preview?id&exp&sig` (Signatur) liefern `html`, `form` (volle Listen aus `FormCheckResult`, je Eintrag `code` und `message`), `post` (`id, slug, title, status, revision, published_revision, review_note, word_count, sources, updated_at`), `has_live`. Signatur falsch/abgelaufen → 403 `invalid_signature`.
5. `GET posts/by-slug/{slug}`: `has_new_version` = `published_revision IS NOT NULL AND revision > published_revision AND status = 'review'`; `reaction` aus `ReactionService` (`{"reaction","note"}` oder `null`); `stats` = `null` (füllt Phase 4).
6. `approve`, `return`, `unpublish`, `DELETE` rufen die gleichnamigen `PostService`-Methoden mit `Actor::owner()`; `DomainError` → 409 mit `error` = Code des Fehlers (z. B. `invalid_transition`, `form_errors` mit `details`). Nach `approve` ist ein verknüpfter Queue-Auftrag `done`.
7. Neubau nach `approve`, `unpublish`, `changes/{id}/apply` nach Kontrakt; mit absichtlich gesperrtem Builder (zweiter Prozess hält `GET_LOCK`) → Aktion gilt, Antwort `rebuilt: false`, `rebuild_error` gesetzt.
8. `GET changes` liefert je Vorschlag `before` (aktueller Wert der betroffenen Felder) und `payload` (neuer Wert), damit die Karte „vorher/nachher“ zeigen kann. `apply`/`reject` über `ChangeService` mit `Actor::owner()`.
9. Vorschau-Hülle: `Builder::collectPreview()` schreibt `vorschau/index.html` aus `templates/preview.php` über `layout.php` mit `<meta name="robots" content="noindex">`, `body data-seite="vorschau"`, `<main>` mit `<p class="vorschau-band">Vorschau · nicht öffentlich</p>` und leerem `<div id="vorschau-inhalt"></div>`; `vorschau` steht in `builder_top_level`. Die Seite lädt `vorschau.js` (Phase 7 füllt es; hier leere Datei mit Kommentar anlegen und in `Assets` aufnehmen).
10. `submit_post` (MCP) liefert zusätzlich `preview_url` (`PreviewLink::url`). Die Tool-Beschreibung nennt das Feld.

## Checkliste

- [ ] `app/src/Auth/PreviewLink.php` (`url`, `verify(int $id, int $exp, string $sig): bool`)
- [ ] `Builder::articleMain` herausziehen, `collectArticles` darauf umstellen, byte-gleich prüfen
- [ ] `Builder::collectPreview`, `templates/preview.php`, `config.php` (`vorschau`), `app/theme/vorschau.js` (Platzhalter)
- [ ] `app/src/Api/OverviewController.php`, `PostController.php`, `ChangeController.php`, `PreviewController.php`; Routen in `app/index.php`
- [ ] Hilfsfunktion für den Neubau nach Kontrakt (private Methode in einer gemeinsamen Klasse `app/src/Api/Rebuild.php`, `run(array $config, PDO $pdo): array{rebuilt: bool, rebuild_error: ?string}`)
- [ ] `PostTools::submit_post`: `preview_url`
- [ ] ADR-021 „Redaktions-API“: Router mit Platzhaltern; JWT/Deploy-Anmeldung je Route; Neubau synchron im Request (Strato: `max_execution_time` 240 s, lokaler Bau ≈ 3 s), Fehler im Bau macht die Freigabe nicht rückgängig; Vorschau rendert über dieselbe Methode wie der Builder; signierte Links mit dem JWT-Schlüssel. Verworfen: Neubau per Warteschlange (kein Cron auf Strato), Vorschau als eigene Template-Kopie (zwei Darstellungen driften)
- [ ] Doku: `docs/routes.md`, `docs/code-map.md` (neue Controller, `Builder::articleMain`, `collectPreview`)

## Report-Back
