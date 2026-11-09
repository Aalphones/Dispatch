# Phase 5 — Artikel: Quellen, Kategorien, Revisionen

**Rating:** standard · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt"
- Phase 1 → Migration `005_post.sql`; Phase 2 → `CategoryService`; Phase 3 → `Sanitizer`; Phase 4 → `MediaRepository`
- Konzept → „Datenmodell" (Tabellen `posts`, `post_revisions`, `post_categories`, `sources`), „Quellen und Belege", „Gestaltungsmittel für den Agenten" (Layouts, Akzentfarbe)
- `docs/glossary.md` (Arbeitsstand, Live-Stand)
- Fehlerklassen: keine einschlägig über die README hinaus

## Entscheidungen (fest)

**Eingabe** (`create` und `update`, alle Felder optional außer `title` und `content_html` bei `create`):

| Feld | Regel (Verstoß → `DomainError('invalid_value', …, ['field' => …])`) |
|---|---|
| `title` | getrimmt, 1–200 Zeichen (`mb_strlen`) |
| `subtitle`, `kicker`, `excerpt`, `seo_title`, `seo_description` | getrimmt, ≤ Spaltenlänge aus Phase 1, leer → `NULL` |
| `slug` | optional; sonst aus `title`. Muster `^[a-z0-9]+(-[a-z0-9]+)*$`, ≤ 120. Bereits vergeben (anderer Artikel) → `-2`, `-3`, … anhängen, nur beim automatisch erzeugten Slug; ein ausdrücklich gesetzter belegter Slug → `slug_taken` |
| `content_html` | durch `Sanitizer`; die Hinweise gehen als `removed` in die Antwort |
| `tags` | Liste von Zeichenketten, getrimmt, je 1–40 Zeichen, höchstens 10, Dubletten (ohne Groß/klein) entfernt |
| `layout` | `standard\|feature\|longform\|briefing` |
| `accent` | `^#[0-9a-fA-F]{6}$` oder `NULL` |
| `cover_media_uid` | muss existieren (`MediaRepository::findByUid`) und `kind = image` sein → `cover_media_id` |
| `origin` | `scheduled\|manual` (`queue` setzt nur Phase 7) |
| `depth` | `standard\|deep` |
| `editor_notes` | ≤ 100 000 Zeichen |
| `ai_assisted` | bool, Default `true` |
| `categories` | Liste `{slug, primary?}`; jeder Slug muss eine **aktive** Kategorie sein (`unknown_category`); mehr als ein `primary` → `invalid_value`; keiner → der erste wird Hauptkategorie. Anzahl > 3 wird gespeichert und von der Formprüfung gemeldet |
| `sources` | Liste von Objekten mit `ref` (Muster wie `data-cite` in Phase 3), `kind` (Enum aus Phase 1), `title` (1–500), `url` (leer oder `http(s)://`, ≤ 2000), `publisher`, `author` (≤ 300), `published` (`^\d{4}(-\d{2}(-\d{2})?)?$`), `accessed` (`YYYY-MM-DD`), `note`. `ref` doppelt → `duplicate_ref`. `position` = Reihenfolge der Liste |

`categories` und `sources` ersetzen bei `update` die ganze Liste, wenn sie übergeben werden; fehlen sie, bleiben sie.

**Slug aus Titel:** `ä→ae, ö→oe, ü→ue, Ä→Ae, Ö→Oe, Ü→Ue, ß→ss` ersetzen, dann `Transliterator::create('Any-Latin; Latin-ASCII; Lower()')`, alles außer `[a-z0-9]` → `-`, Folgen zusammenziehen, Ränder trimmen, auf 80 Zeichen an einer Wortgrenze kürzen. Leer → `artikel`.

**Slug nach Veröffentlichung:** ist `published_revision` gesetzt, darf nur der Inhaber den Slug ändern (`slug_locked`), weil er die öffentliche Adresse ist.

**Wortzahl:** `Util\Text::wordCount(string $html)` — `strip_tags`, `html_entity_decode`, dann `preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u')`. Wird bei jedem Speichern in `word_count` geschrieben.

**Revisionen:** `create` legt Revision 1 an; jedes `update` erhöht `revision` um 1 und schreibt eine neue Zeile in `post_revisions` — in **derselben** Transaktion wie die Änderung und der Audit-Eintrag (`post.create` / `post.update`). Der Snapshot ist das vollständige Bild des Arbeitsstands:

```json
{
  "id": 12, "revision": 3, "slug": "…", "title": "…", "subtitle": null, "kicker": null, "excerpt": "…",
  "content_html": "…", "tags": [], "layout": "standard", "accent": null, "cover_media_uid": "m…",
  "seo_title": null, "seo_description": null, "origin": "manual", "queue_item_id": null, "depth": "standard",
  "ai_assisted": true, "word_count": 812, "editor_notes": "…",
  "categories": [{"slug": "…", "name": "…", "primary": true}],
  "sources": [{"ref": "…", "kind": "…", "title": "…", "url": "…", "publisher": "…", "author": null, "published": "2026", "accessed": "2026-11-01", "note": null}]
}
```

`status`, `review_note`, `published_revision`, `published_at` gehören **nicht** in den Snapshot (sie beschreiben den Ablauf, nicht den Inhalt).

**Status bei Änderung:** Ein `update` durch einen Nicht-Inhaber auf einen Artikel mit Status `review` setzt ihn auf `draft` zurück (er muss neu eingereicht werden). Status `published` bleibt `published` — der neue Arbeitsstand ist an `revision > published_revision` erkennbar. Status `archived` → `DomainError('archived', …)`.

## Dateien

| Datei | Inhalt |
|---|---|
| `app/src/Util/Text.php` | `final class Text`: `static wordCount(string $html): int`, `static slugify(string $text): string` |
| `app/src/Domain/PostRepository.php` | `find(int $id): ?array`, `findBySlug(string $slug): ?array`, `slugExists(string $slug, ?int $exceptId): bool`, `insert(array $fields): int`, `update(int $id, array $fields): void`, `list(array $statuses, int $limit, int $offset): array` (ohne `content_html`, `editor_notes`), `replaceCategories(int $postId, array $rows): void`, `categories(int $postId): array`, `replaceSources(int $postId, array $rows): void`, `sources(int $postId): array`, `insertRevision(int $postId, int $revision, array $snapshot, string $actor): void`, `revision(int $postId, int $revision): ?array`, `revisions(int $postId): array` (Nummer, Akteur, Zeit) |
| `app/src/Domain/PostService.php` | `create(array $input, Actor $actor): array`, `update(int $id, array $patch, Actor $actor): array` — beide Rückgabe `['post' => <Snapshot + status, review_note, published_revision, published_at>, 'removed' => list<string>]`; `get(int $id): array`, `getBySlug(string $slug): array` (`not_found`), `list(array $statuses = [], int $limit = 50, int $offset = 0): array`, `snapshot(int $id): array` (baut den Snapshot aus den Tabellen), `revisions(int $id): array`, `revision(int $id, int $revision): array` |

## Abnahme (Phase)

Scratchpad-Skripte gegen `dispatch_dev` (vorher eine aktive Kategorie und ein Bild aus Phase 4 anlegen).

- [ ] `create` mit Titel „Über Größe & Maß", Inhalt mit `<script>`, zwei Quellen, Kategorie, Titelbild → Slug `ueber-groesse-mass`, `removed` nennt das Skript, `revision = 1`, eine Zeile in `post_revisions`, Snapshot enthält Quellen, Kategorie (`primary: true`) und `cover_media_uid`, Audit `post.create`.
- [ ] Zweiter Artikel mit gleichem Titel → Slug `ueber-groesse-mass-2`; ausdrücklich `slug: 'ueber-groesse-mass'` → `slug_taken`.
- [ ] `update` nur `title` → `revision = 2`, Quellen und Kategorien unverändert; `update` mit `sources: []` → keine Quellen mehr, Revision 3; `revision(id, 2)` liefert den alten Stand mit Quellen.
- [ ] Doppelter `ref`, unbekannte Kategorie, Kategorie `active = 0`, zwei `primary`, `accent: 'rot'`, Titelbild-UID eines PDFs → je der benannte Fehler, **keine** Änderung in der DB (Revision unverändert).
- [ ] Artikel per SQL auf `status = 'review'` → `update` durch `mcp:test` → `draft`; durch `owner` → bleibt `review`. Per SQL `published_revision = 1`, `status = 'published'` → `update` durch `mcp:test` → bleibt `published`, `revision` steigt; Slug-Änderung durch `mcp:test` → `slug_locked`.
- [ ] `Text::wordCount('<p>Das ist&nbsp;ein Test-Satz, oder? 3,5 Mio.</p>')` → im Report-Back mit Begründung; der Wert muss „Test-Satz" als ein Wort zählen.

## Checkliste

- [ ] Dateien laut Tabelle
- [ ] `docs/models.md`: Snapshot-Format, Slug-Regel, Wortzahl-Regel
- [ ] `docs/code-map.md`: `Util/Text`, `Domain/Post*`

## Report-Back
