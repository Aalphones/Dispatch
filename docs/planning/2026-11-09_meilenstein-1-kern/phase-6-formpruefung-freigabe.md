# Phase 6 — Formprüfung und Freigabe-Übergänge

**Rating:** heikel (Statusmaschine trägt die Regel „nichts ohne Freigabe online") · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt"
- Phase 5 → Snapshot-Format, Status bei Änderung; Phase 4 → `MediaRepository::findManyByUid`; Phase 2 → `SettingService::formRules()`
- Konzept → „Formprüfung (Validator)" (Regeltabelle), „Freigabe: eingeloggt alles, öffentlich nur Freigegebenes", „Bilder und Lizenzen" (Pflichtangaben je Medium)
- `docs/conventions/php.md` → Critical Rule 2
- Fehlerklassen: Zeitabhängige Prüfungen — keine in dieser Phase (Statusmaschine ist zeitunabhängig)

## Entscheidungen (fest)

**`Util\FormCheck` ist rein:** keine Datenbank. Eingabe: Snapshot (Phase 5), Medien der verwendeten UIDs (`array<uid, row>`), Titel aller **anderen** Live-Artikel, Formvorgaben. `PostService` sammelt die Eingaben.

**Regeln** (Code `snake_case`, `field`, deutscher Satz mit Abhilfe):

| Code | Regel | Art |
|---|---|---|
| `title_missing` / `title_long` | Titel leer / über 90 Zeichen | Fehler / Warnung |
| `excerpt_missing` / `excerpt_length` | Teaser leer / außerhalb 70–180 Zeichen | Fehler / Warnung |
| `too_short` | Wörter < Mindestwert. Mindestwert: `depth = deep` → `min_words_deep`, sonst `min_words`; Layout `briefing` → `ceil(Mindestwert × 0.4)` | Fehler |
| `no_subheadings` | über 700 Wörter und kein `<h2` im Inhalt | Warnung |
| `too_few_sources` | Quellen < `min_sources` (`deep`: `min_sources_deep`) | Fehler |
| `single_domain` | mindestens 2 Quellen mit URL, alle mit demselben Host (klein, ohne führendes `www.`) | Fehler |
| `source_url_missing` / `source_publisher_missing` | URL fehlt bei `kind` ∉ {book, interview, other} / Herausgeber fehlt | Fehler / Warnung |
| `no_citations` / `unknown_citation` | kein `data-cite` im Inhalt / `data-cite` nennt `ref` ohne Quelle | Fehler |
| `uncited_source` | Quelle wird nirgends zitiert | Warnung |
| `unknown_media` | UID aus `data-media` oder Titelbild existiert nicht | Fehler |
| `media_incomplete` | Pflichtangabe fehlt: `alt` bei image/video; `credit` und `license` wenn `require_image_license`; `source_url` außer bei `origin = owner` | Fehler (je Medium und Feld) |
| `cover_missing` | kein Titelbild und `require_cover` | Fehler |
| `category_count` / `category_primary` | 0 oder > 3 Kategorien / nicht genau eine Hauptkategorie | Fehler |
| `longform_short` | Layout `longform` unter 1500 Wörtern | Warnung |
| `possible_duplicate` | `similar_text` (beide Titel klein) > 80 % zu einem anderen Live-Titel; Meldung nennt ihn | Warnung |

UIDs aus dem Inhalt: alle Werte von `data-media` (auch Galerie-Listen, an `,` geteilt). `data-cite` ebenso. Gezählt per Regex auf dem bereinigten HTML (`/data-(media|cite)="([^"]*)"/`) — das HTML stammt immer aus dem Sanitizer, die Attributform ist also fest.

**Ergebnis** `FormCheckResult(array $errors, array $warnings, array $metrics)`; `ok(): bool` = keine Fehler; `toArray()`. `metrics`: `words`, `reading_minutes` (`max(1, ceil(words / 200))`), `sources`, `cited_sources`, `citations` (Anzahl `data-cite`-Verweise), `source_domains` (Anzahl verschiedener Hosts), `images` (Bild-Medien inkl. Titelbild, ohne Doppelte), `minimums` (`{min_words, min_sources}` wie angewandt).

**Statusmaschine** (alles andere → `DomainError('invalid_transition', 'Aus Status <x> geht <aktion> nicht.')`):

| Aktion | Wer | Von | Nach | Wirkung |
|---|---|---|---|---|
| `submit` | jeder | `draft`; `published` mit `revision > published_revision` | `review` | Formprüfung; Fehler → `DomainError('form_errors', …, ['result' => …])`, Status bleibt |
| `approve` | Inhaber | `review` | `published` | Formprüfung erneut (Fehler blockieren); `published_revision = revision`; `published_at = COALESCE(published_at, UTC_TIMESTAMP())`; `review_note = NULL` |
| `returnToAgent(note)` | Inhaber | `review` | `draft` | `review_note = note` (1–2000 Zeichen); Live-Stand bleibt |
| `unpublish` | Inhaber | `published`; `review` mit gesetztem `published_revision` | `archived` | `published_revision` bleibt als Nachweis; die Website zeigt ihn nicht mehr |
| `discard` | Inhaber | jeder Status, **nur** wenn `published_revision IS NULL` | — | Artikel samt Revisionen, Quellen, Kategorien löschen; Audit `post.discard` mit Titel und Slug |

Jede Aktion: eine Transaktion, Audit `post.<aktion>`. Ein Rebuild nach `approve`/`unpublish` kommt mit Meilenstein 2.

**Live-Lesepfad (einzige Quelle des Builders):** `PostRepository::livePosts(): list<array>` — `SELECT r.snapshot, p.published_at, p.updated_at FROM posts p JOIN post_revisions r ON r.post_id = p.id AND r.revision = p.published_revision WHERE p.published_revision IS NOT NULL AND p.status <> 'archived' ORDER BY p.published_at DESC`; Rückgabe: dekodierter Snapshot plus `published_at`. Keine andere Methode darf Artikel für die Website liefern.

Festgehalten in ADR-012 „Artikel-Statusmaschine und Live-Lesepfad".

## Dateien

| Datei | Inhalt |
|---|---|
| `app/src/Util/FormCheck.php` | `final class FormCheck`: `check(array $snapshot, array $mediaByUid, array $otherLiveTitles, array $rules): FormCheckResult` |
| `app/src/Util/FormCheckResult.php` | `final readonly class FormCheckResult(array $errors, array $warnings, array $metrics)` mit `ok()` und `toArray()`; Einträge `{code, field, message}` |
| `app/src/Domain/PostRepository.php` | ergänzen: `livePosts()`, `liveTitles(?int $exceptId): list<string>` (gleiche Bedingung wie `livePosts`), `setStatus(int $id, array $fields): void`, `delete(int $id): void` |
| `app/src/Domain/PostService.php` | ergänzen: `validate(int $id): FormCheckResult`, `submit(int $id, Actor $actor): array`, `approve(int $id, Actor $actor): array`, `returnToAgent(int $id, string $note, Actor $actor): array`, `unpublish(int $id, Actor $actor): array`, `discard(int $id, Actor $actor): void`, `live(): array` (reicht `livePosts()` durch) |

## Abnahme (Phase)

Scratchpad-Skripte gegen `dispatch_dev`.

**Jede Formregel einzeln:** Ein gültiger Artikel (≥ 600 Wörter, 3 Quellen auf 3 Domains, alle zitiert, Titelbild mit vollständigen Angaben, eine Kategorie) liefert `ok() = true` und keine Warnungen. Dann je **ein** Feld verändern und den erwarteten Code prüfen:

- [ ] Titel leer → `title_missing`; 95 Zeichen → `title_long` (Warnung)
- [ ] Teaser leer → `excerpt_missing`; 60 Zeichen → `excerpt_length`
- [ ] 550 Wörter → `too_short`; gleicher Artikel mit Layout `briefing` (Mindestwert 240) → kein `too_short`; `depth = deep` mit 1000 Wörtern → `too_short`
- [ ] 800 Wörter ohne `<h2>` → `no_subheadings`
- [ ] 2 Quellen → `too_few_sources`; `deep` mit 4 Quellen → `too_few_sources`
- [ ] 3 Quellen auf `www.a.de` und `a.de` → `single_domain`
- [ ] Quelle `kind = web` ohne URL → `source_url_missing`; `kind = book` ohne URL → kein Fehler; ohne Herausgeber → Warnung
- [ ] kein `data-cite` → `no_citations`; `data-cite="gibtsnicht"` → `unknown_citation`; eine Quelle nie zitiert → `uncited_source`
- [ ] `data-media` mit unbekannter UID → `unknown_media`; Titelbild ohne `alt` → `media_incomplete`; ohne `license` → `media_incomplete`; `origin = owner` ohne `source_url` → kein Fehler
- [ ] ohne Titelbild → `cover_missing`
- [ ] 0 Kategorien → `category_count`; 4 → `category_count`
- [ ] `longform` mit 1200 Wörtern → `longform_short`
- [ ] Live-Artikel „Warum werden Bananen braun?" vorhanden, neuer Titel „Warum werden Bananen so braun?" → `possible_duplicate`
- [ ] `metrics` für den gültigen Artikel im Report-Back, von Hand nachgezählt

**Statusmaschine und Rechte:**

- [ ] `submit` mit Fehlern → `form_errors`, Status bleibt `draft`; ohne Fehler → `review`
- [ ] `approve`, `returnToAgent`, `unpublish`, `discard` mit `Actor::mcp('test')` und `Actor::cli()` → `owner_only`, nichts geändert
- [ ] `approve` durch Inhaber → `published`, `published_revision = revision`, `published_at` gesetzt; `livePosts()` liefert genau diesen Snapshot
- [ ] Danach `update` durch `mcp:test` (Titel ändern) → `livePosts()` zeigt weiter den **alten** Titel; `submit` → `review`, `livePosts()` unverändert und Artikel weiter enthalten; `approve` → neuer Titel live, `published_at` unverändert
- [ ] `returnToAgent` mit Anmerkung → `draft`, `review_note` gesetzt, Live-Stand unverändert
- [ ] `unpublish` → `livePosts()` ohne den Artikel; `discard` danach → `invalid_transition` (war veröffentlicht)
- [ ] Nie veröffentlichter Entwurf → `discard` → Zeilen in `posts`, `post_revisions`, `sources`, `post_categories` weg, Audit-Eintrag da
- [ ] `approve` aus `draft` → `invalid_transition`

## Checkliste

- [ ] Dateien laut Tabelle
- [ ] Abnahme fahren, Fallliste mit Ergebnis im Report-Back (`dod.md`)
- [ ] ADR-012 „Artikel-Statusmaschine und Live-Lesepfad" (Tabelle oben; warum `published_revision` beim Zurückziehen bleibt; `livePosts()` als einzige Quelle) + Zeile in `docs/decisions/README.md`
- [ ] `docs/models.md`: Statusmaschine, Live-Lesepfad
- [ ] `docs/code-map.md`: `Util/FormCheck*`

## Report-Back
