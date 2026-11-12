# Datenmodell — DISPATCH

Quelle sind die Migrationen in `app/migrations/`. Alle Tabellen: InnoDB, `utf8mb4_unicode_ci`. Zeitspalten sind UTC, geschrieben mit `UTC_TIMESTAMP()`. JSON-Spalten werden nur gespeichert, nie im SQL abgefragt ([ADR-008](decisions/008-zuschnitt-meilenstein-1-lokale-umgebung.md)). `schema_migrations` (`filename` PK, `applied_at`) führt der Migrator selbst.

Nicht in Meilenstein 1: `login_attempts` (M4), `oauth_clients`, `oauth_codes` (M5, Migration 014).

## settings (001)

| Spalte | Typ | Hinweis |
|---|---|---|
| `name` | VARCHAR(64) | PK |
| `value` | JSON | |
| `updated_at` | DATETIME | |

## documents (002)

| Spalte | Typ | Hinweis |
|---|---|---|
| `doc_key` | VARCHAR(40) | PK |
| `title` | VARCHAR(200) | |
| `content` | MEDIUMTEXT | |
| `public_slug` | VARCHAR(80) NULL | UNIQUE |
| `revision` | INT UNSIGNED | Default 1 |
| `updated_at` | DATETIME | |

## document_revisions (002)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `doc_key` | VARCHAR(40) | FK → `documents.doc_key` CASCADE |
| `revision` | INT UNSIGNED | UNIQUE mit `doc_key` |
| `content` | MEDIUMTEXT | |
| `note` | VARCHAR(500) NULL | |
| `actor` | VARCHAR(120) | |
| `created_at` | DATETIME | |

## categories (003)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `slug` | VARCHAR(80) | UNIQUE |
| `name` | VARCHAR(120) | |
| `description` | TEXT NULL | |
| `parent_id` | INT UNSIGNED NULL | FK → `categories.id` RESTRICT |
| `position` | INT | Default 0 |
| `interest` | TINYINT UNSIGNED | Default 3 |
| `keywords`, `avoid` | JSON | immer `[]` oder Liste |
| `active` | TINYINT(1) | Default 1 |
| `created_at`, `updated_at` | DATETIME | |

## Einstellungen (Tabelle `settings`)

Werte stehen als JSON in `settings.value`; fehlt eine Zeile, gilt der Startwert. Der `Seeder` legt alle zwölf an (`php bin/dispatch seed`). Formvorgaben (`form_rules`, `publish_mode`, `stats_enabled`) stehen in `app/config.php`, nicht in der Tabelle; ein Änderungsversuch → `not_changeable`.

| Schlüssel | Gruppe | Startwert | Prüfregel |
|---|---|---|---|
| `site_title` | öffentlich | `DISPATCH` | Text, 1–80 |
| `site_tagline` | öffentlich | `Your agents. Your publication.` | Text, ≤ 120 |
| `site_description` | öffentlich | `` | Text, ≤ 300 |
| `author_name` | öffentlich | `` | Text, ≤ 120 |
| `author_url` | öffentlich | `` | leer oder `http(s)://…`, ≤ 300 |
| `accent` | öffentlich | `#b3261e` | `#rrggbb` |
| `ai_disclosure` | öffentlich | `true` | bool |
| `ai_disclosure_text` | öffentlich | Transparenztext (Wortlaut offen, jederzeit änderbar) | Text, ≤ 500 |
| `language` | intern | `de` | `de` \| `en` |
| `locale` | intern | `de_DE` | `xx_XX` |
| `posts_per_page` | intern | `12` | Ganzzahl 1–100 |
| `queue_claim_minutes` | intern | `180` | Ganzzahl 15–1440 |

Texte werden getrimmt. Fehler: `invalid_value` (Feld in `details`), unbekannter Name `unknown_setting`.

## Was öffentlich ist

Änderung durch Nicht-Inhaber → Änderungsvorschlag (`changes`); durch den Inhaber → direkt ([ADR-009](decisions/009-akteur-inhaber-rechte-aenderungsvorschlaege.md)).

| Domäne | Öffentlich | Intern (jeder Akteur direkt) |
|---|---|---|
| Einstellungen | `site_title`, `site_tagline`, `site_description`, `author_name`, `author_url`, `accent`, `ai_disclosure`, `ai_disclosure_text` | `language`, `locale`, `posts_per_page`, `queue_claim_minutes` |
| Dokumente | `about` (`ueber`), `imprint` (`impressum`), `privacy` (`datenschutz`) | `editorial_guidelines`, `preferences` |
| Kategorien | Anlegen; `slug`, `name`, `description`, `parent_id`, `position`, `active` | `interest`, `keywords`, `avoid` |

Kategorien: nur zwei Ebenen (`parent_id` muss eine Kategorie ohne Elternteil sein); `slug` `[a-z0-9-]` ≤ 80 und eindeutig, `name` 1–120, `interest` 0–5, `keywords`/`avoid` Listen (≤ 30 Einträge à ≤ 60 Zeichen). Dokumente: Markdown ≤ 200 000 Zeichen; jede Änderung erhöht `revision` und schreibt `document_revisions`. Vorschlags-Schlüssel (`changes.entity_key`): Einstellungen `settings`, Dokumente der `doc_key`, Kategorien die ID oder `new`.

## media (004)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `uid` | CHAR(11) | UNIQUE |
| `kind` | ENUM | `image`, `video`, `audio`, `document` |
| `mime` | VARCHAR(100) | |
| `ext` | VARCHAR(10) | |
| `bytes` | INT UNSIGNED | |
| `sha256` | CHAR(64) | UNIQUE |
| `width`, `height` | INT UNSIGNED NULL | |
| `variants` | JSON | Bilder: `[{"w": 480, "files": ["480.webp", "480.jpg"]}, …]`, sonst `[]` |
| `alt` | VARCHAR(500) NULL | |
| `caption` | VARCHAR(1000) NULL | |
| `credit` | VARCHAR(300) NULL | |
| `license` | VARCHAR(120) NULL | |
| `license_url` | VARCHAR(500) NULL | |
| `source_url` | VARCHAR(1000) NULL | |
| `origin` | ENUM | `import`, `upload`, `owner` |
| `created_at`, `updated_at` | DATETIME | |

Ablage: `storage/media/<uid>/original.<ext>` plus die Dateien aus `variants`. Bilder werden immer neu kodiert (kein EXIF/GPS); `ext` ist `jpg`, `png` oder `webp` (GIF wird PNG), `bytes` die Größe von `original.<ext>`, `sha256` gehört den Eingangs-Bytes (Dublettenerkennung). Breiten 480/800/1200/1600/2000, nur bis zur Originalbreite; schmaler als 480 → eine Variante in Originalbreite. Metadaten sind beim Speichern optional; Vorschlags-Schlüssel (`changes.entity_key`) ist die `uid`. ADR-011.

## posts (005)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `slug` | VARCHAR(120) | UNIQUE |
| `title` | VARCHAR(200) | |
| `subtitle` | VARCHAR(300) NULL | |
| `kicker` | VARCHAR(80) NULL | |
| `excerpt` | VARCHAR(400) NULL | |
| `content_html` | MEDIUMTEXT | |
| `status` | ENUM | `draft` (Default), `review`, `published`, `archived`; Index |
| `tags` | JSON | |
| `layout` | ENUM | `standard` (Default), `feature`, `longform`, `briefing` |
| `accent` | CHAR(7) NULL | |
| `cover_media_id` | INT UNSIGNED NULL | FK → `media.id` SET NULL |
| `seo_title` | VARCHAR(200) NULL | |
| `seo_description` | VARCHAR(300) NULL | |
| `origin` | ENUM | `scheduled`, `queue`, `manual` (Default) |
| `queue_item_id` | INT UNSIGNED NULL | Index, bewusst kein FK (Kreis mit `queue_items.post_id`) |
| `depth` | ENUM | `standard` (Default), `deep` |
| `editor_notes` | MEDIUMTEXT NULL | |
| `review_note` | TEXT NULL | |
| `ai_assisted` | TINYINT(1) | Default 1 |
| `word_count` | INT UNSIGNED | Default 0 |
| `revision` | INT UNSIGNED | Default 1 |
| `published_revision` | INT UNSIGNED NULL | |
| `published_at` | DATETIME NULL | |
| `created_at`, `updated_at` | DATETIME | |

**Regeln (`Domain/PostService`, `Util/Text`).** Slug aus dem Titel: `ä→ae, ö→oe, ü→ue, ß→ss`, dann ASCII-Transliteration, alles außer `[a-z0-9]` → `-`, höchstens 80 Zeichen an einer Wortgrenze, leer → `artikel`; belegt → `-2`, `-3`, … (nur automatisch erzeugte), ausdrücklich gesetzt und belegt → `slug_taken`. Nach der Veröffentlichung (`published_revision` gesetzt) ändert nur der Inhaber den Slug (`slug_locked`). Wortzahl (`word_count`): Blockelemente zählen als Trennung, Tags entfernt, Entities dekodiert, ein Wort ist eine Folge aus Buchstaben/Ziffern, innen verbunden durch `'`, `’` oder `-` („Test-Satz“ = 1, „3,5“ = 2). Jedes Speichern erhöht `revision` und schreibt eine Zeile in `post_revisions`; `update` durch einen Nicht-Inhaber setzt `review` → `draft` zurück, `published` bleibt, `archived` ist gesperrt.

**Statusmaschine und Live-Lesepfad (ADR-012).** `submit` (jeder: `draft`, oder `published` mit `revision > published_revision` → `review`; Formprüfung), `approve` (Inhaber: `review` → `published`, `published_revision = revision`, `published_at` nur beim ersten Mal, Formprüfung erneut), `returnToAgent` (Inhaber: `review` → `draft`, `review_note`), `unpublish` (Inhaber: `published` oder `review` mit `published_revision` → `archived`, `published_revision` bleibt), `withdraw` (jeder, ADR-018: nur `review`; Ziel `published`, wenn `published_revision` gesetzt, sonst `draft`), `discard` (nur wenn `published_revision IS NULL`, löscht Artikel samt Revisionen, Quellen, Kategorien; Inhaber aus jedem Status, andere nur aus `draft`, sonst `forbidden`), `restoreRevision` (jeder: schreibt den Inhalt einer früheren Revision per `update` als neue Revision zurück, Slug bleibt; Audit `post.restore`). Sonst `invalid_transition`; Audit `post.<aktion>`. Die Website liest ausschließlich `PostRepository::livePosts()` — den Snapshot der Revision `published_revision` aller Artikel mit gesetzter `published_revision` und Status ≠ `archived`, nie den Arbeitsstand; zum Snapshot kommen `published_at` und `updated_at` (= `created_at` der Live-Revision). Formprüfung: `Util/FormCheck` (Regelcodes siehe Quelltext und Phase 6), Fehler blockieren `submit`/`approve`, Warnungen nie.

## post_revisions (005)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `post_id` | INT UNSIGNED | FK → `posts.id` CASCADE |
| `revision` | INT UNSIGNED | UNIQUE mit `post_id` |
| `snapshot` | JSON | |
| `actor` | VARCHAR(120) | |
| `created_at` | DATETIME | |

`snapshot` ist das vollständige Bild des Arbeitsstands: `id`, `revision`, `slug`, `title`, `subtitle`, `kicker`, `excerpt`, `content_html`, `tags`, `layout`, `accent`, `cover_media_uid`, `seo_title`, `seo_description`, `origin`, `queue_item_id`, `depth`, `ai_assisted`, `word_count`, `editor_notes`, `categories` (`{slug, name, primary}`), `sources` (`{ref, kind, title, url, publisher, author, published, accessed, note}`). Nicht enthalten: `status`, `review_note`, `published_revision`, `published_at` (Ablauf, nicht Inhalt). MySQL sortiert JSON-Schlüssel beim Speichern um — die Reihenfolge der Schlüssel ist ohne Bedeutung.

## post_categories (005)

| Spalte | Typ | Hinweis |
|---|---|---|
| `post_id` | INT UNSIGNED | PK, FK → `posts.id` CASCADE |
| `category_id` | INT UNSIGNED | PK, FK → `categories.id` RESTRICT |
| `is_primary` | TINYINT(1) | Default 0 |

## sources (005)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `post_id` | INT UNSIGNED | FK → `posts.id` CASCADE |
| `ref` | VARCHAR(60) | UNIQUE mit `post_id` |
| `position` | SMALLINT UNSIGNED | |
| `kind` | ENUM | `web`, `news`, `study`, `official`, `dataset`, `book`, `interview`, `video`, `podcast`, `social`, `other` |
| `title` | VARCHAR(500) | |
| `url` | VARCHAR(2000) NULL | |
| `publisher`, `author` | VARCHAR(300) NULL | |
| `published` | VARCHAR(10) NULL | `YYYY`, `YYYY-MM` oder `YYYY-MM-DD` |
| `accessed` | DATE NULL | |
| `note` | TEXT NULL | |

## queue_items (006)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `title` | VARCHAR(200) | |
| `brief` | TEXT NULL | |
| `links` | JSON | |
| `status` | ENUM | `open` (Default), `claimed`, `review`, `done`, `failed`, `cancelled`; Index (`status`, `priority`, `created_at`) |
| `priority` | TINYINT UNSIGNED | Default 2 |
| `depth` | ENUM | `standard` (Default), `deep` |
| `category_id` | INT UNSIGNED NULL | FK → `categories.id` SET NULL |
| `claimed_by` | VARCHAR(120) NULL | |
| `claim_expires` | DATETIME NULL | |
| `post_id` | INT UNSIGNED NULL | FK → `posts.id` SET NULL |
| `result_note` | TEXT NULL | |
| `created_by` | VARCHAR(120) | |
| `created_at`, `updated_at` | DATETIME | |

## queue_media (006)

| Spalte | Typ | Hinweis |
|---|---|---|
| `queue_item_id` | INT UNSIGNED | PK, FK → `queue_items.id` CASCADE |
| `media_id` | INT UNSIGNED | PK, FK → `media.id` CASCADE |
| `note` | VARCHAR(500) NULL | |
| `own_material` | TINYINT(1) | Default 0 |

**Queue-Ablauf** (`QueueService`):

- **Status:** `open` → `claimed` (Reservierung) → `review` (Artikel eingereicht) → `done` (Artikel freigegeben). Daneben `failed` (Agent gibt mit Begründung auf), `cancelled` (Inhaber zieht zurück; nur aus `open`/`claimed` und nur ohne verknüpften Artikel).
- **Reservierung:** ein einziges `UPDATE … WHERE id = ? AND (status = 'open' OR (status = 'claimed' AND claim_expires <= UTC_TIMESTAMP()))`; `rowCount() = 1` heißt reserviert, sonst `already_claimed`. Dauer = Einstellung `queue_claim_minutes`. Ohne `id` werden bis zu 5 Kandidaten nach Priorität, Alter, `id` der Reihe nach versucht.
- **Ablauf:** kein Hintergrundprozess. `get`/`list` geben einen abgelaufenen Claim als `open` aus (`effective_status`; `status` bleibt der Rohzustand der Zeile). Wer arbeiten darf, entscheidet `claimed_by` im Rohzustand, nicht die Uhr: abgelaufen und nicht neu reserviert → der bisherige Agent darf weiter; neu reserviert → `not_claimant`.
- **Verknüpfung mit Artikeln** (gleiche Transaktion): `PostService::create` mit `queue_item_id` (Auftrag muss reserviert sein, vom Akteur oder Inhaber) setzt `origin = queue`, `depth` des Auftrags und `queue_items.post_id`; `submit` → `review`; `approve` → `done`; `discard` → `open` mit `post_id`, `claimed_by`, `claim_expires` = NULL. `withdraw` → `claimed` (`QueueService::backToClaimed`: nur aus `review`, `claim_expires` = jetzt + `queue_claim_minutes`, `claimed_by` bleibt). `returnToAgent` und `unpublish` lassen den Auftrag stehen. `release` (zurück auf `open`/`failed`) ist bei verknüpftem Artikel gesperrt (`has_post`) — erst verwerfen.
- **Eigenes Material:** `own_material` an einem Anhang setzt beim Anlegen am Medium leeres `credit` = `author_name` und leere `license` = „Eigenes Material“.

## changes (007)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `entity` | ENUM | `document`, `media`, `category`, `setting` |
| `entity_key` | VARCHAR(120) | |
| `payload` | JSON | |
| `status` | ENUM | `pending` (Default), `applied`, `rejected`; Index |
| `created_by` | VARCHAR(120) | |
| `created_at` | DATETIME | |
| `decided_at` | DATETIME NULL | |
| `decided_by` | VARCHAR(120) NULL | |

## stats_daily (008)

| Spalte | Typ | Hinweis |
|---|---|---|
| `day` | DATE | PK mit `path` |
| `path` | VARCHAR(300) | PK mit `day` |
| `post_id` | INT UNSIGNED NULL | |
| `views`, `visitors`, `reads_half`, `reads_full` | INT UNSIGNED | Default 0 |
| `engaged_seconds` | BIGINT UNSIGNED | Default 0 |

## stats_dimensions (008)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `day` | DATE | |
| `path` | VARCHAR(300) | Pfad der Seite, auf der das Ereignis geschah; Suchen unter `/suche/` |
| `dimension` | ENUM | `referrer`, `utm`, `device`, `outbound`, `search` |
| `value` | VARCHAR(300) | UNIQUE mit `day`, `path`, `dimension` |
| `count` | INT UNSIGNED | Default 0 |
| `extra` | JSON NULL | nur `search`: `{"hits": n}` (Trefferzahl der letzten Suche) |

Bedeutung von `value` (ADR-022, je Aufruf einmal gezählt): `referrer` = Domain der Herkunftsseite, leer → `direkt`, eigene Domain zählt nicht; `utm` = `utm_source`; `device` = `mobil`/`tablet`/`desktop`; `outbound` = Zieldomain eines Links; `search` = kleingeschriebener Suchbegriff (2–100 Zeichen).

Beacon-Felder (`POST /dispatch/api/hit`): `t` = `view` (`p` Pfad, `r`, `u`, `d`), `read` (`p`, `s` Sekunden 0–3600, `h` 0/50/100), `out` (`p`, `z`), `search` (`q`, `n`). `stats_daily.post_id` = Live-Artikel zu `/artikel/{slug}/`, sonst `NULL`.

## stats_visitors (008)

| Spalte | Typ | Hinweis |
|---|---|---|
| `day` | DATE | PK mit `visitor_hash` |
| `visitor_hash` | CHAR(64) | PK mit `day` |
| `hits_last_hour` | SMALLINT UNSIGNED | Default 0 |
| `hour_started` | DATETIME | |

## audit_log (009)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `at` | DATETIME | Index |
| `actor` | VARCHAR(120) | z. B. `owner`, `cli`, `mcp:<name>` |
| `action` | VARCHAR(60) | `<domäne>.<verb>` |
| `target` | VARCHAR(160) | `<domäne>:<schlüssel>` |
| `details` | JSON NULL | leer → `NULL` |

## tokens (010)

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `token_hash` | CHAR(64) | UNIQUE; SHA-256 (hex) des Klartexts |
| `kind` | ENUM | `mcp`, `oauth_access`, `oauth_refresh`, `owner_refresh`; genutzt: `mcp` (M3), `owner_refresh` (M4); Index mit `revoked` |
| `name` | VARCHAR(120) | bei `mcp`: `^[a-z0-9][a-z0-9-]{0,39}$`, eindeutig unter nicht widerrufenen; bei `owner_refresh`: `owner` |
| `client_id` | VARCHAR(500) NULL | OAuth (M5) |
| `scope` | VARCHAR(200) | Scopes durch Leerzeichen getrennt; bei `owner_refresh` leer |
| `family` | CHAR(32) NULL | Refresh-Familie: bei `owner_refresh` eine je Anmeldung, OAuth-Refresh (M5) ebenso |
| `expires_at` | DATETIME NULL | `NULL` = läuft nie ab; `owner_refresh`: Anlage + 30 Tage, nach Rotation `revoked = 1` mit jetzt + 30 s (Kulanzfenster), nach Widerruf jetzt |
| `revoked` | TINYINT(1) | Default 0 |
| `last_used_at` | DATETIME NULL | bei jeder erfolgreichen Anmeldung |
| `created_at` | DATETIME | |

## login_attempts (012)

Fehlversuche der Anmeldung für die Login-Bremse (ADR-020). Keine IP im Klartext.

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `ip_hash` | CHAR(64) | HMAC-SHA-256 der `REMOTE_ADDR` mit `DISPATCH_JWT_SECRET`; Index mit `attempted_at` |
| `attempted_at` | DATETIME | UTC; Zeilen älter als 1 Tag löscht jeder Login-Aufruf |

## stats_visitor_paths (013)

Je Besucher-Hash und Tag die schon gezählten Pfade, damit ein Ereignis je Pfad genau einmal zählt. Der erste Aufruf eines neuen Tages löscht alle früheren Tage (zusammen mit `stats_visitors`, ADR-022).

| Spalte | Typ | Hinweis |
|---|---|---|
| `day` | DATE | PK |
| `visitor_hash` | CHAR(64) | PK |
| `path` | VARCHAR(300) | PK |

## oauth_clients (014)

OAuth-Clients der Claude-Apps ([ADR-024](decisions/024-oauth-fuer-claude-apps.md)): `cimd` = per Metadatendokument ausgewiesen (Zwischenspeicher), `manual` = von Hand angelegter Rückfall-Client.

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `client_id` | VARCHAR(500) | UNIQUE; bei `cimd` die Dokument-URL, bei `manual` `dcl_` + 32 Hex |
| `kind` | ENUM | `cimd`, `manual` |
| `name` | VARCHAR(120) | Anzeigename |
| `secret_hash` | CHAR(64) NULL | nur `manual`: SHA-256 von `dcs_` + 64 Hex |
| `redirect_uris` | JSON | erlaubte Rückadressen |
| `metadata` | JSON NULL | zwischengespeichertes CIMD |
| `fetched_at` | DATETIME NULL | letzter CIMD-Abruf |
| `revoked` | TINYINT(1) | Default 0 |
| `last_used_at` | DATETIME NULL | |
| `created_at` | DATETIME | |

## oauth_codes (014)

Autorisierungsanfragen und -codes; eine Zeile von der Anfrage bis zur Einlösung.

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | INT UNSIGNED | PK, AUTO_INCREMENT |
| `request_id` | CHAR(32) | UNIQUE; Klartext, 600 s gültig |
| `code_hash` | CHAR(64) NULL | UNIQUE; SHA-256 des Codes (300 s, einmal), erst nach Zustimmung |
| `client_id` | VARCHAR(500) | |
| `redirect_uri` | VARCHAR(500) | |
| `challenge` | VARCHAR(128) | PKCE S256 |
| `scope` | VARCHAR(200) | angefragte bzw. erteilte Scopes |
| `resource` | VARCHAR(300) NULL | |
| `state` | VARCHAR(500) NULL | |
| `status` | ENUM | `pending`, `approved`, `denied`, `used`; Default `pending` |
| `family` | CHAR(32) NULL | Token-Familie dieser Zustimmung |
| `expires_at` | DATETIME | Index |
| `created_at` | DATETIME | |

## post_reactions (011)

| Spalte | Typ | Hinweis |
|---|---|---|
| `post_id` | INT UNSIGNED | PK, FK → `posts.id` CASCADE |
| `reaction` | ENUM | `more`, `less`; keine Reaktion = Zeile gelöscht |
| `note` | VARCHAR(500) NULL | |
| `updated_at` | DATETIME | |
