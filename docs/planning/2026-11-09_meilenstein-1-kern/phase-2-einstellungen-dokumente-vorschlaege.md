# Phase 2 — Einstellungen, Dokumente, Kategorien, Änderungsvorschläge, Startinhalte

**Rating:** heikel (definiert, was ohne Login öffentlich wirken darf) · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt"
- Phase 1 → „Dateien" (Migrationen 001–003, 007; `Actor`, `DomainError`, `Transaction`, `AuditRepository`)
- Konzept `docs/design/konzept-blog-engine.md` → „Datenmodell" (Tabelle + Absatz „Einstellungen"), „Freigabe: eingeloggt alles, öffentlich nur Freigegebenes" (letzter Spiegelpunkt: Änderungsvorschläge), „Redaktionsrichtlinien (Startfassung)", „Recht und Datenschutz"
- `docs/PROJECT.md` → „Offene Fragen" (Sprache, Kategorienbaum, Transparenztext — hier **nicht** entscheiden)
- `docs/conventions/php.md`, `docs/glossary.md`
- Fehlerklassen: keine einschlägig über die README hinaus

## Entscheidungen (fest)

**Was öffentlich ist** (Änderung durch Nicht-Inhaber → Änderungsvorschlag; durch den Inhaber → direkt):

| Domäne | Öffentlich | Intern (jeder Akteur direkt) | Nie änderbar per Service |
|---|---|---|---|
| Einstellungen | `site_title`, `site_tagline`, `site_description`, `author_name`, `author_url`, `accent`, `ai_disclosure`, `ai_disclosure_text` | `language`, `locale`, `posts_per_page`, `queue_claim_minutes` | Formvorgaben, `publish_mode`, `stats_enabled` — stehen in `config.php` (`DomainError('not_changeable', 'Formvorgaben stehen in config.php und ändern sich nur über den Inhaber.')`) |
| Dokumente | `about`, `imprint`, `privacy` | `editorial_guidelines`, `preferences` | — |
| Kategorien | Anlegen; Felder `slug`, `name`, `description`, `parent_id`, `position`, `active` | Felder `interest`, `keywords`, `avoid` | — |

Ein Update mit gemischten Feldern (öffentlich + intern) durch einen Nicht-Inhaber: interne Felder sofort anwenden, öffentliche als **ein** Vorschlag. Rückgabe immer `['applied' => list<string> Felder, 'change_id' => ?int]`.

**Startwerte der Einstellungen:** `site_title` „DISPATCH", `site_tagline` „Your agents. Your publication.", `site_description` "", `author_name` "", `author_url` "", `accent` "#b3261e", `ai_disclosure` true, `ai_disclosure_text` „Dieser Artikel wurde mit KI-Unterstützung recherchiert und geschrieben und vor der Veröffentlichung vom Herausgeber geprüft und freigegeben.", `language` "de", `locale` "de_DE", `posts_per_page` 12, `queue_claim_minutes` 180. Der Wortlaut des Transparenztexts bleibt offene Frage; er ist danach jederzeit änderbar.

**Prüfung der Werte** (`DomainError('invalid_value', …)` mit Feld in `details`): Zeichenketten getrimmt, `site_title` 1–80, `site_tagline` ≤ 120, `site_description` ≤ 300, `author_name` ≤ 120, `author_url` leer oder `http(s)://…` ≤ 300, `accent` `/^#[0-9a-fA-F]{6}$/`, `ai_disclosure` bool, `ai_disclosure_text` ≤ 500, `language` `de`|`en`, `locale` `/^[a-z]{2}_[A-Z]{2}$/`, `posts_per_page` int 1–100, `queue_claim_minutes` int 15–1440. Unbekannter Name → `DomainError('unknown_setting', …)`.

**Kategorien:** kein Startbaum (offene Frage). `slug` `/^[a-z0-9]+(-[a-z0-9]+)*$/` ≤ 80, `name` 1–120, `interest` 0–5, `keywords`/`avoid` Listen von Zeichenketten (je ≤ 60 Zeichen, höchstens 30). `parent_id` muss existieren und darf nicht die Kategorie selbst oder eine ihrer Unterkategorien sein (nur zwei Ebenen: eine Kategorie mit `parent_id` darf selbst keine Kinder bekommen). Anlegen durch Nicht-Inhaber → Vorschlag mit `entity_key = 'new'`.

**Dokumente:** `public_slug` beim Start: `about` → `ueber`, `imprint` → `impressum`, `privacy` → `datenschutz`, interne → `NULL`. Jede Änderung: `documents.revision + 1`, Zeile in `document_revisions`, Audit `document.update`. Inhalt ist Markdown, ≤ 200 000 Zeichen.

**Startinhalte:** Markdown-Dateien in `app/defaults/<doc_key>.md`; erste Zeile `# <Titel>` ist der Titel, der Rest der Inhalt. Der `Seeder` legt **nur fehlende** Einstellungen und Dokumente an (vorhandene nie überschreiben), Akteur `cli` bzw. `deploy`. Platzhalter im Text stehen als `[[…]]`.

**Vorschlag übernehmen:** `ChangeService::apply` ruft je `entity` die Methode des Fach-Service mit `Actor::owner()` auf — so läuft dieselbe Prüfung wie bei direkter Änderung. Schlägt sie fehl (`DomainError`), bleibt der Vorschlag `pending` und der Fehler geht an den Aufrufer. `media` folgt in Phase 4 (bis dahin `DomainError('not_supported', …)`). Ein Rebuild nach dem Übernehmen kommt mit Meilenstein 2.

Festgehalten in ADR-009 „Akteur, Inhaber-Rechte und Änderungsvorschläge".

## Dateien

| Datei | Inhalt |
|---|---|
| `app/config.php` | ergänzen: `'form_rules' => ['min_sources' => 3, 'min_sources_deep' => 5, 'min_words' => 600, 'min_words_deep' => 1200, 'require_cover' => true, 'require_image_license' => true]`, `'publish_mode' => 'review'`, `'stats_enabled' => true` |
| `app/src/Domain/SettingRepository.php` | `all(): array<string, mixed>`, `get(string $name): mixed` (fehlend → `null`), `upsert(string $name, mixed $value): void` (`INSERT … ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()`), `exists(string $name): bool` |
| `app/src/Domain/SettingService.php` | Konstanten `PUBLIC_KEYS`, `INTERNAL_KEYS`, `DEFAULTS` (Startwerte oben). `get(string $name): mixed` (DB-Wert, sonst Default), `all(): array` (Defaults überlagert mit DB), `formRules(): array` (aus `config`), `set(array $values, Actor $actor): array` (Rückgabe `applied`/`change_id`, Regeln oben; je angewandtem Schlüssel Audit `setting.set` mit altem und neuem Wert), `applyChange(string $key, array $payload): void` (für `ChangeService`, Akteur Inhaber) |
| `app/src/Domain/DocumentRepository.php` | `get(string $key): ?array`, `all(): array` (ohne `content`), `insert(string $key, string $title, string $content, ?string $publicSlug): void`, `updateContent(string $key, string $title, string $content): int` (neue Revision zurück), `insertRevision(string $key, int $revision, string $content, ?string $note, string $actor): void`, `revisions(string $key): array` |
| `app/src/Domain/DocumentService.php` | `PUBLIC_KEYS = ['about','imprint','privacy']`, `INTERNAL_KEYS = ['editorial_guidelines','preferences']`. `get(string $key): array` (fehlend → `DomainError('not_found', …)`), `list(): array`, `update(string $key, string $content, ?string $title, ?string $note, Actor $actor): array` (Regeln oben), `applyChange(string $key, array $payload): void` |
| `app/src/Domain/CategoryRepository.php` | `all(bool $includeInactive): array` (sortiert `parent_id`, `position`, `name`), `find(int $id): ?array`, `findBySlug(string $slug): ?array`, `insert(array $fields): int`, `update(int $id, array $fields): void`, `hasChildren(int $id): bool` |
| `app/src/Domain/CategoryService.php` | `PUBLIC_FIELDS`, `INTERNAL_FIELDS` (Tabelle oben). `list(bool $includeInactive = false)`, `get(int\|string $idOrSlug): array`, `create(array $fields, Actor $actor): array` (Inhaber → `['category' => …]`; sonst `['change_id' => …]`), `update(int $id, array $fields, Actor $actor): array` (`applied`/`change_id`), `applyChange(string $key, array $payload): void` (`'new'` → anlegen, sonst Update) |
| `app/src/Domain/ChangeRepository.php` | `insert(string $entity, string $key, array $payload, string $createdBy): int`, `find(int $id): ?array`, `list(?string $status): array` (neueste zuerst), `decide(int $id, string $status, string $decidedBy): void` (setzt `decided_at = UTC_TIMESTAMP()`) |
| `app/src/Domain/ChangeService.php` | `propose(string $entity, string $key, array $payload, Actor $actor): int` (Audit `change.propose`), `list(?string $status = 'pending'): array` — je Eintrag zusätzlich `before` (aktueller Wert der betroffenen Felder) für die spätere Vorher/Nachher-Ansicht, `apply(int $id, Actor $actor): void` und `reject(int $id, Actor $actor): void` (beide nur Inhaber, nur aus `pending`, sonst `DomainError('not_pending', …)`; Audit `change.apply`/`change.reject`). `apply` läuft in **einer** Transaktion mit der Fach-Änderung |
| `app/src/Domain/Seeder.php` | `__construct(PDO $pdo, array $config)`; `run(Actor $actor): list<string>` — liefert die angelegten Schlüssel als `setting:<name>` / `document:<key>` |
| `app/defaults/editorial_guidelines.md` | `# Redaktionsrichtlinien`, dann die acht Punkte aus dem Konzept („Redaktionsrichtlinien (Startfassung)") als Abschnitte `## 1. Haltung` … `## 8. Korrekturen`, ausformuliert als Anweisung an den Einreicher |
| `app/defaults/preferences.md` | `# Präferenzen des Lesers`, Abschnitte Hintergrund, Vorwissen, Interessen, Ton, Länge, No-Gos, Rhythmus — je mit `[[…]]`-Platzhalter |
| `app/defaults/about.md` | `# Über DISPATCH`, kurzer Text: Agenten recherchieren, der Herausgeber prüft und gibt jeden Artikel frei; `[[Name]]`-Platzhalter |
| `app/defaults/imprint.md` | `# Impressum`, Angaben nach § 5 DDG und Verantwortlicher nach § 18 Abs. 2 MStV, alles als `[[…]]`-Platzhalter, Hinweis „Vorlage, rechtlich prüfen lassen" als Markdown-Kommentar `<!-- … -->` |
| `app/defaults/privacy.md` | `# Datenschutz`, Abschnitte: keine Cookies für Besucher, nichts von Dritten, eigene Zugriffsstatistik ohne IP-Adressen (Tageswerte, Tages-Hash), Server-Logs bei Strato, Videos erst nach Klick, Login-Cookie nur für den Inhaber; Platzhalter `[[…]]`; gleicher Prüfhinweis wie Impressum |
| `bin/dispatch` | `seed` verdrahten: `Seeder::run(Actor::cli())`, angelegte Schlüssel zeilenweise oder „Nichts zu tun." |

## Abnahme (Phase)

Scratchpad-Skripte gegen `dispatch_dev`; jeder Fall mit Ergebnis im Report-Back.

- [ ] `php bin/dispatch seed` → 12 Einstellungen und 5 Dokumente angelegt; zweiter Lauf → „Nichts zu tun."; ein vorher von Hand geänderter Dokumentinhalt bleibt nach erneutem `seed` unverändert.
- [ ] `SettingService::set(['site_title' => 'X'], Actor::mcp('test'))` → `change_id` gesetzt, `get('site_title')` unverändert; `ChangeService::apply(id, Actor::owner())` → Wert `X`, Vorschlag `applied`, Audit-Einträge `change.propose`, `setting.set`, `change.apply`.
- [ ] Gemischt `['site_title' => 'Y', 'posts_per_page' => 20]` durch `mcp:test` → `posts_per_page` sofort 20, `site_title` als Vorschlag.
- [ ] `set(['min_words' => 100], Actor::owner())` → `not_changeable`; `set(['accent' => 'rot'], …)` → `invalid_value`; `set(['foo' => 1], …)` → `unknown_setting`.
- [ ] `ChangeService::apply` mit `Actor::mcp('test')` → `owner_only`; zweites `apply` auf denselben Vorschlag → `not_pending`.
- [ ] Ein Vorschlag, dessen Wert inzwischen ungültig ist (z. B. `parent_id` einer gelöschten Kategorie, von Hand per SQL vorbereitet) → `apply` wirft, Vorschlag bleibt `pending`, keine halbe Änderung in der DB.
- [ ] `DocumentService::update('editorial_guidelines', …, Actor::mcp('test'))` → direkt, Revision 2, Zeile in `document_revisions`; `update('imprint', …, mcp)` → Vorschlag; mit Inhaber → direkt.
- [ ] Kategorie anlegen durch `mcp:test` → Vorschlag `entity_key = 'new'`; nach `apply` existiert sie. `update(id, ['interest' => 5, 'name' => 'Neu'], mcp)` → `interest` sofort, `name` als Vorschlag. Kategorie als eigene Elternkategorie → `invalid_value`; dritte Ebene → `invalid_value`.

## Checkliste

- [ ] Dateien laut Tabelle
- [ ] ADR-009 „Akteur, Inhaber-Rechte und Änderungsvorschläge" (Tabelle öffentlich/intern, gemischte Updates, Übernahme über die Fach-Services) + Zeile in `docs/decisions/README.md`
- [ ] `docs/models.md`: Abschnitt „Einstellungen" (Schlüssel, Gruppe, Startwert, Prüfregel) und „Was öffentlich ist"
- [ ] `docs/code-map.md`: `defaults/` (fünf Dateien), `Domain/Seeder`, neue Services/Repositories
- [ ] `docs/glossary.md`: „Akteur", „Startinhalte"

## Report-Back
