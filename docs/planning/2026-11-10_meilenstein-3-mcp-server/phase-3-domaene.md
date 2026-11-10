# Phase 3 — Domänen-Ergänzungen: Artikel, Medien, Statistik, Briefing

Rating: heikel (Statusmaschine und Queue-Kopplung)

## Kontext (vorher lesen)

- `README.md` dieses Plans → Kontrakt: „Neue Service-Methoden“, „Tool-Katalog“.
- `docs/models.md` → `posts`, `post_revisions` (Snapshot-Felder), Statusmaschine, Queue-Ablauf, `stats_daily`, `stats_dimensions`, `changes`.
- ADR-012 (Statusmaschine, Live-Lesepfad), ADR-009.
- `app/src/Domain/PostService.php` (insbesondere `update`, `submit`, `discard`, `requireOwner`, `lockedPost`, `queueAfter`), `PostRepository.php` (`list`, `livePosts`).
- `app/src/Domain/QueueService.php` (`markReview`, `reopen`, `linkPost`), `QueueRepository.php`.
- `app/src/Domain/MediaService.php` (`list`), `MediaRepository.php`.
- `app/src/Domain/CategoryService.php`, `DocumentService.php`, `SettingService.php` (`formRules`), `ChangeService.php`, `ReactionService.php` (Phase 1).
- Fachkonzept → „Tool-Katalog“ (Rotationswert), „Reihenfolge im Briefing“, „Zugriffsstatistik“ → „Kennzahlen“.
- Fehlerklassen geprüft (Vault): keine einschlägig.

## AK der Phase

Geprüft per kleinem Prüfskript im Scratchpad (`php -r` bzw. Datei außerhalb des Repos) gegen `dispatch_dev` nach `php bin/dispatch demo`; jeder Fall im Report-Back mit Ergebnis.

1. `withdraw`: Entwurf in `review` → `draft`; veröffentlichter Artikel mit neuem Arbeitsstand in `review` → `published` (Live-Stand unverändert); aus anderen Status → `invalid_transition`. Verknüpfter Queue-Auftrag in `review` → `claimed`, `claim_expires` = jetzt + `queue_claim_minutes`, `claimed_by` unverändert. Audit `post.withdraw`.
2. `discard` mit `Actor::mcp('x')`: Entwurf (`draft`, nie veröffentlicht) wird gelöscht, Auftrag wieder `open`; Artikel in `review` → `forbidden` mit Hinweis „erst withdraw_post“; veröffentlichter → `invalid_transition` wie bisher. Inhaber unverändert.
3. `restoreRevision(id, 2)` erzeugt eine neue Revision, deren Inhalt dem Snapshot von Revision 2 entspricht (Slug bleibt); unbekannte Revision → `not_found`.
4. `list` filtert nach Kategorie-Slug (direkte Zuordnung), Schlagwort (exakter Name), Jahr (`COALESCE(published_at, created_at)`) und Status, kombinierbar.
5. `search('Gletscher')` findet den Demo-Artikel über den Fließtext; Treffer im Titel stehen vor Treffern nur im Text; `%` und `_` im Suchwort werden wörtlich gesucht; weniger als 2 Zeichen → `invalid_value`.
6. `MediaService::list(query: 'x')` sucht in `uid`, `alt`, `caption`, `credit`.
7. `StatsService::stats` mit von Hand eingefügten Zeilen: `group_by` `day`, `post`, `category`, `referrer`, `device`, `outbound`, `search` liefern Summen; Zeitraum ohne Angabe = letzte 30 Tage bis heute (UTC); mehr als 366 Tage oder `from > to` → `invalid_value`. `postStats` per ID und per Slug.
8. `BriefingService::brief('auto')`: mit zurückgegebenem Entwurf → Modus `returned`; sonst mit offenem Auftrag → `queue`; sonst `scheduled`. Rotationswert nach Regel; Kategorie mit Interesse 0 hat `excluded: true` und Rotation 0. Reaktionen aus Phase 1 erscheinen unter `reactions`.

## Checkliste

- [ ] `PostService::withdraw(int $id, Actor $actor): array` — in `Transaction::join`, `lockedPost`, nur aus `review`; Ziel `published`, wenn `published_revision` gesetzt, sonst `draft`; Queue über neue Methode `QueueService::backToClaimed(int $id): void` (setzt `status = 'claimed'`, `claim_expires = UTC_TIMESTAMP() + INTERVAL <queue_claim_minutes> MINUTE`, nur wenn Status `review`); Audit; Rückgabe `get($id)`.
- [ ] `PostService::discard`: `requireOwner` ersetzen durch: Inhaber wie bisher; Nicht-Inhaber nur bei `status = 'draft'` und `published_revision IS NULL`, sonst `DomainError('forbidden', 'Agenten löschen nur Entwürfe. Eingereichtes erst mit withdraw_post zurückziehen.')`. Die Prüfung muss nach `lockedPost` in der Transaktion liegen.
- [ ] `PostService::restoreRevision(int $id, int $revision, Actor $actor): array` — Snapshot über `revision()`; Patch aus den Snapshot-Feldern `title, subtitle, kicker, excerpt, content_html, tags, layout, accent, seo_title, seo_description, editor_notes, sources`, dazu `cover_media` = `cover_media_uid` und `categories` = Slugs mit `primary` zuerst; dann `update($id, $patch, $actor)`. Audit `post.restore` mit `from_revision`.
- [ ] `PostService::list` um `?string $category, ?string $tag, ?int $year` erweitern (Signatur nach Kontrakt, bisherige Aufrufer unverändert gültig); `PostRepository::list` baut `JOIN post_categories`/`categories` nur bei Kategorie-Filter, `JSON_CONTAINS(p.tags, JSON_QUOTE(?))` für Schlagwort, `YEAR(COALESCE(p.published_at, p.created_at)) = ?` für Jahr. Unbekannte Kategorie → `not_found` mit Liste gültiger Slugs.
- [ ] `PostService::search(string $query, int $limit = 20): array` + `PostRepository::search` — `LIKE` mit `ESCAPE '\\'` auf `title`, `subtitle`, `excerpt`, `content_html`; Sortierung `CASE WHEN title LIKE … THEN 0 ELSE 1 END, updated_at DESC`; `limit` 1–50; Felder `id, slug, title, status, excerpt, published_at, updated_at`.
- [ ] `MediaService::list` + `MediaRepository::list` um `?string $query` erweitern (gleiche `LIKE`-Escapes).
- [ ] `StatsRepository` + `StatsService` nach Kontrakt. Rückgabe `stats`: `{from, to, group_by, rows: [{key, label, views, visitors, engaged_seconds, reads_half, reads_full}]}` für `day|post|category` aus `stats_daily` (post: `label` = Titel; category: über Hauptkategorie des Artikels), `{…, rows: [{value, count}]}` für Dimensionen aus `stats_dimensions` (höchstens 50, absteigend); bei `search` zusätzlich `hits` aus `extra`. `filter`: `{post: id|slug}` oder `{path_prefix: "/artikel/"}`. `postStats`: Summen plus Aufschlüsselung `referrer`, `device`, `outbound` für die Pfade des Artikels.
- [ ] `SiteInfoService::info(): array` — `settings` (`SettingService::all`), `form_rules` (`formRules`), `counts` (`posts` je Status, `queue` je `effective_status`, `changes_pending`, `media`, `tokens_active`), `last_build_at` (Änderungszeit von `storage/manifest.json` als UTC `Y-m-d H:i:s` oder `null`), `base_url`.
- [ ] `BriefingService` — `brief(string $mode): array` mit Schlüsseln: `mode` (aufgelöst: `returned|queue|scheduled`), `requested_mode`, `today` (UTC-Datum), `guidelines` und `preferences` (Markdown der Dokumente), `form_rules`, `categories` (aktive, je `slug, name, parent, interest, keywords, avoid, post_count, last_published_at, rotation, excluded`, sortiert nach `rotation` absteigend), `recent_posts` (30, alle Status, nach `updated_at`: `id, slug, title, status, primary_category, tags, published_at`), `reader_interest` (letzte 30 Tage: `top_posts` 10, `top_categories` 5, `searches_without_results` 10), `reactions` (`ReactionService::recent(90)`), `queue` (Zählung je `effective_status` plus `next` = Titel/Priorität/Tiefe des nächsten offenen Auftrags, ohne ihn zu reservieren), `returned` (Entwürfe mit `review_note`: `id, title, review_note, updated_at`), `workflow` (leer lassen — Phase 4 füllt den Text aus `app/mcp/workflow-<mode>.md`). `mode=queue` ohne offenen Auftrag bleibt `queue` mit `queue.next = null`. `mode` außerhalb `auto|scheduled|queue` → `invalid_value`.
- [ ] Rotationsregel: `interest × min(Tage seit letztem Live-Artikel der Kategorie, 90)`; ohne Live-Artikel 60 Tage; `interest = 0` → `excluded: true`, `rotation: 0`. `categoryStats()` liefert dieselben Zahlen für `list_categories`.
- [ ] „Zurückgegeben“ = `status = 'draft'` und `review_note` nicht leer. Geprüft beim Planen: `review_note` setzt nur `returnToAgent`, geleert wird er erst bei `approve`; nach erneutem `submit` ist der Status `review` und der Artikel zählt nicht mehr als zurückgegeben — keine Änderung nötig.
- [ ] ADR-018 `docs/decisions/018-mcp-werkzeuge-und-agentenrechte.md`: `complete_queue_item` entfällt; Agenten dürfen zurückziehen (`withdraw`) und reine Entwürfe löschen; `restore_post_revision(id, revision)` statt Revisions-ID; Vorschau-Link und signierte Medien-Links erst mit Meilenstein 4.
- [ ] Doku: `docs/models.md` (Statusmaschine: `withdraw`, `discard` für Agenten; Queue: `backToClaimed`), `docs/code-map.md` (`StatsService`, `SiteInfoService`, `BriefingService`, `ReactionService`), `docs/decisions/README.md`.

## Report-Back
