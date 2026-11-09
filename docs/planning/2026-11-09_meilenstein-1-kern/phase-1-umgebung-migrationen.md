# Phase 1 — Lokale Umgebung, Migrationen, CLI, Akteur und Protokoll

**Rating:** standard · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt" — verbindlich
- `docs/conventions/php.md`, `docs/conventions/dod.md`, `docs/code-map.md`
- `app/bootstrap.php`, `app/config.php`, `app/src/Db/Connection.php`, `app/src/Db/Migrator.php`, `dev-router.php` (wie `DISPATCH_ENV_FILE` gesetzt wird), `dev.env.example`
- `docs/decisions/006-lokale-entwicklung-dev-router.md`, `007-diagnose-route-und-platzhalter-builder.md` (Migrator: keine Transaktion, Anweisungen einzeln)
- `docs/knowledge/INDEX.md` → „Strato-Messwerte" (MySQL 8.0.36, PHP 8.5.12)
- Fehlerklassen: `~/.claude/knowledge/topics/strato-shared-hosting.md` → Zeilen „Migrations-Runner als CLI-Skript" (auf dem Server läuft nichts per Kommandozeile — Migrationen nur über den Deploy-Endpunkt) und „`CREATE TABLE` in eine PDO-Transaktion" (DDL nie in eine Transaktion)

## Stand vor der Phase (gemessen 2026-11-09)

Lokal: PHP 8.3.32 (winget `PHP.PHP.8.3`), `gd`, `intl`, `exif` in der `php.ini` auskommentiert, kein MySQL/MariaDB, kein Docker. Strato: PHP 8.5.12, MySQL 8.0.36.

## Entscheidungen (fest)

- **Lokale Umgebung** angleichen: `winget install --id PHP.PHP.8.5` und `winget install --id Oracle.MySQL` (8.4 LTS, gleiche Familie wie Strato). In der `php.ini` von PHP 8.5 aktivieren: `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `exif` (nach `mbstring`), `openssl`, `pdo_mysql`. Danach muss `php -v` 8.5 zeigen (PATH-Reihenfolge prüfen; PHP 8.3 bleibt installiert, steht aber hinten). Grund: der Sanitizer (Phase 3) nutzt `Dom\HTMLDocument` (ab PHP 8.4), Medien brauchen GD mit WebP.
- **Datenbank lokal:** Datenbank `dispatch_dev`, Benutzer `dispatch` mit Rechten nur darauf, Zeichensatz `utf8mb4`. Werte nach `app/.env.local`.
- **Zuschnitt der Tabellen:** Meilenstein 1 legt 16 der 20 Konzept-Tabellen an. `tokens` kommt mit Meilenstein 3 (ohne MCP gibt es keinen Verbraucher, und ein Token per lokaler CLI landete nur in der lokalen Datenbank), `login_attempts` mit Meilenstein 4, `oauth_clients` und `oauth_codes` mit Meilenstein 5.
- **CLI `bin/dispatch`** (nur lokal, ADR-002/ADR-006): Befehle in diesem Meilenstein `hash-password`, `migrate`, `seed` (`seed` wird in Phase 2 verdrahtet). `build` folgt mit Meilenstein 2, `token:create` entfällt zugunsten von Meilenstein 3.
- Alle Tabellen: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. Zeitspalten `DATETIME NOT NULL` ohne `DEFAULT` — geschrieben wird mit `UTC_TIMESTAMP()`. JSON-Spalten `JSON NOT NULL` ohne `DEFAULT` (MySQL 8.0 erlaubt dort nur Ausdrücke) — der Code schreibt immer einen Wert (`[]` bzw. `{}`).
- Festgehalten in ADR-008.

## Dateien

| Datei | Inhalt |
|---|---|
| `app/migrations/001_setting.sql` | `settings`: `name VARCHAR(64) PK`, `value JSON NOT NULL`, `updated_at DATETIME NOT NULL` |
| `app/migrations/002_document.sql` | `documents`: `doc_key VARCHAR(40) PK`, `title VARCHAR(200) NOT NULL`, `content MEDIUMTEXT NOT NULL`, `public_slug VARCHAR(80) NULL UNIQUE`, `revision INT UNSIGNED NOT NULL DEFAULT 1`, `updated_at DATETIME NOT NULL`. `document_revisions`: `id BIGINT UNSIGNED AUTO_INCREMENT PK`, `doc_key VARCHAR(40) NOT NULL` (FK → `documents.doc_key` ON DELETE CASCADE), `revision INT UNSIGNED NOT NULL`, `content MEDIUMTEXT NOT NULL`, `note VARCHAR(500) NULL`, `actor VARCHAR(120) NOT NULL`, `created_at DATETIME NOT NULL`, `UNIQUE (doc_key, revision)` |
| `app/migrations/003_category.sql` | `categories`: `id INT UNSIGNED AUTO_INCREMENT PK`, `slug VARCHAR(80) NOT NULL UNIQUE`, `name VARCHAR(120) NOT NULL`, `description TEXT NULL`, `parent_id INT UNSIGNED NULL` (FK → `categories.id` ON DELETE RESTRICT), `position INT NOT NULL DEFAULT 0`, `interest TINYINT UNSIGNED NOT NULL DEFAULT 3`, `keywords JSON NOT NULL`, `avoid JSON NOT NULL`, `active TINYINT(1) NOT NULL DEFAULT 1`, `created_at`, `updated_at` |
| `app/migrations/004_media.sql` | `media`: `id INT UNSIGNED AUTO_INCREMENT PK`, `uid CHAR(11) NOT NULL UNIQUE`, `kind ENUM('image','video','audio','document') NOT NULL`, `mime VARCHAR(100) NOT NULL`, `ext VARCHAR(10) NOT NULL`, `bytes INT UNSIGNED NOT NULL`, `sha256 CHAR(64) NOT NULL UNIQUE`, `width INT UNSIGNED NULL`, `height INT UNSIGNED NULL`, `variants JSON NOT NULL`, `alt VARCHAR(500) NULL`, `caption VARCHAR(1000) NULL`, `credit VARCHAR(300) NULL`, `license VARCHAR(120) NULL`, `license_url VARCHAR(500) NULL`, `source_url VARCHAR(1000) NULL`, `origin ENUM('import','upload','owner') NOT NULL`, `created_at`, `updated_at` |
| `app/migrations/005_post.sql` | `posts`: `id INT UNSIGNED AUTO_INCREMENT PK`, `slug VARCHAR(120) NOT NULL UNIQUE`, `title VARCHAR(200) NOT NULL`, `subtitle VARCHAR(300) NULL`, `kicker VARCHAR(80) NULL`, `excerpt VARCHAR(400) NULL`, `content_html MEDIUMTEXT NOT NULL`, `status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft'`, `tags JSON NOT NULL`, `layout ENUM('standard','feature','longform','briefing') NOT NULL DEFAULT 'standard'`, `accent CHAR(7) NULL`, `cover_media_id INT UNSIGNED NULL` (FK → `media.id` ON DELETE SET NULL), `seo_title VARCHAR(200) NULL`, `seo_description VARCHAR(300) NULL`, `origin ENUM('scheduled','queue','manual') NOT NULL DEFAULT 'manual'`, `queue_item_id INT UNSIGNED NULL` (Index, **kein** FK — sonst Kreis mit `queue_items.post_id`), `depth ENUM('standard','deep') NOT NULL DEFAULT 'standard'`, `editor_notes MEDIUMTEXT NULL`, `review_note TEXT NULL`, `ai_assisted TINYINT(1) NOT NULL DEFAULT 1`, `word_count INT UNSIGNED NOT NULL DEFAULT 0`, `revision INT UNSIGNED NOT NULL DEFAULT 1`, `published_revision INT UNSIGNED NULL`, `published_at DATETIME NULL`, `created_at`, `updated_at`, `INDEX (status)`. `post_revisions`: `id BIGINT UNSIGNED AUTO_INCREMENT PK`, `post_id` (FK ON DELETE CASCADE), `revision INT UNSIGNED NOT NULL`, `snapshot JSON NOT NULL`, `actor VARCHAR(120) NOT NULL`, `created_at`, `UNIQUE (post_id, revision)`. `post_categories`: `post_id` (FK CASCADE), `category_id` (FK RESTRICT), `is_primary TINYINT(1) NOT NULL DEFAULT 0`, `PRIMARY KEY (post_id, category_id)`. `sources`: `id INT UNSIGNED AUTO_INCREMENT PK`, `post_id` (FK CASCADE), `ref VARCHAR(60) NOT NULL`, `position SMALLINT UNSIGNED NOT NULL`, `kind ENUM('web','news','study','official','dataset','book','interview','video','podcast','social','other') NOT NULL`, `title VARCHAR(500) NOT NULL`, `url VARCHAR(2000) NULL`, `publisher VARCHAR(300) NULL`, `author VARCHAR(300) NULL`, `published VARCHAR(10) NULL` (`YYYY`, `YYYY-MM` oder `YYYY-MM-DD`), `accessed DATE NULL`, `note TEXT NULL`, `UNIQUE (post_id, ref)` |
| `app/migrations/006_queue.sql` | `queue_items`: `id INT UNSIGNED AUTO_INCREMENT PK`, `title VARCHAR(200) NOT NULL`, `brief TEXT NULL`, `links JSON NOT NULL`, `status ENUM('open','claimed','review','done','failed','cancelled') NOT NULL DEFAULT 'open'`, `priority TINYINT UNSIGNED NOT NULL DEFAULT 2`, `depth ENUM('standard','deep') NOT NULL DEFAULT 'standard'`, `category_id INT UNSIGNED NULL` (FK ON DELETE SET NULL), `claimed_by VARCHAR(120) NULL`, `claim_expires DATETIME NULL`, `post_id INT UNSIGNED NULL` (FK → `posts.id` ON DELETE SET NULL), `result_note TEXT NULL`, `created_by VARCHAR(120) NOT NULL`, `created_at`, `updated_at`, `INDEX (status, priority, created_at)`. `queue_media`: `queue_item_id` (FK CASCADE), `media_id` (FK → `media.id` CASCADE), `note VARCHAR(500) NULL`, `own_material TINYINT(1) NOT NULL DEFAULT 0`, `PRIMARY KEY (queue_item_id, media_id)` |
| `app/migrations/007_change.sql` | `changes`: `id INT UNSIGNED AUTO_INCREMENT PK`, `entity ENUM('document','media','category','setting') NOT NULL`, `entity_key VARCHAR(120) NOT NULL`, `payload JSON NOT NULL`, `status ENUM('pending','applied','rejected') NOT NULL DEFAULT 'pending'`, `created_by VARCHAR(120) NOT NULL`, `created_at`, `decided_at DATETIME NULL`, `decided_by VARCHAR(120) NULL`, `INDEX (status)` |
| `app/migrations/008_stats.sql` | `stats_daily`: `day DATE`, `path VARCHAR(300)`, `post_id INT UNSIGNED NULL`, `views`, `visitors`, `reads_half`, `reads_full` (je `INT UNSIGNED NOT NULL DEFAULT 0`), `engaged_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0`, `PRIMARY KEY (day, path)`. `stats_dimensions`: `id BIGINT UNSIGNED AUTO_INCREMENT PK`, `day DATE NOT NULL`, `path VARCHAR(300) NOT NULL` (leer = seitenübergreifend), `dimension ENUM('referrer','utm','device','outbound','search') NOT NULL`, `value VARCHAR(300) NOT NULL`, `count INT UNSIGNED NOT NULL DEFAULT 0`, `extra JSON NULL`, `UNIQUE (day, path, dimension, value)`. `stats_visitors`: `day DATE`, `visitor_hash CHAR(64)`, `hits_last_hour SMALLINT UNSIGNED NOT NULL DEFAULT 0`, `hour_started DATETIME NOT NULL`, `PRIMARY KEY (day, visitor_hash)`. Nur Tabellen — Erfassung folgt in Meilenstein 4. |
| `app/migrations/009_audit.sql` | `audit_log`: `id BIGINT UNSIGNED AUTO_INCREMENT PK`, `at DATETIME NOT NULL`, `actor VARCHAR(120) NOT NULL`, `action VARCHAR(60) NOT NULL`, `target VARCHAR(160) NOT NULL`, `details JSON NULL`, `INDEX (at)` |
| `app/src/Db/Transaction.php` | `final class Transaction`: `static run(PDO $pdo, callable $work): mixed` laut Kontrakt |
| `app/src/Domain/Actor.php` | laut Kontrakt |
| `app/src/Domain/DomainError.php` | laut Kontrakt |
| `app/src/Domain/AuditRepository.php` | `record(...)` laut Kontrakt: `INSERT INTO audit_log (at, actor, action, target, details) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?)`; `details` leer → `NULL`. Dazu `latest(int $limit = 50): array` (neueste zuerst) |
| `bin/dispatch` | PHP-Skript ohne Endung, erste Zeile `#!/usr/bin/env php`, dann `<?php declare(strict_types=1);`. Bricht ab, wenn `PHP_SAPI !== 'cli'`. Setzt `putenv('DISPATCH_ENV_FILE=' . <repo>/app/.env.local)`, falls die Variable leer ist und die Datei existiert; lädt `app/bootstrap.php`. Danach **eigener** `set_exception_handler`, der `Fehler: <Meldung>` nach STDERR schreibt und mit Code 1 endet (der JSON-Handler aus `bootstrap.php` passt nicht zur Konsole). Befehle per `match ($argv[1] ?? '')`: `hash-password` (liest eine Zeile von STDIN, gibt `password_hash($zeile, PASSWORD_DEFAULT)` aus; leere Eingabe → Fehler), `migrate` (`Migrator` wie im `DeployController`, gibt die neu angewandten Dateien zeilenweise aus oder „Nichts zu tun."), `seed` (Platzhalter mit Meldung „folgt in Phase 2", wird dort ersetzt); sonst Hilfe-Text mit allen Befehlen, Code 1. Aufruf: `php bin/dispatch <befehl>` |
| `dev.env.example` | unverändert lassen außer: Beispiel-Werte `DISPATCH_DB_NAME='dispatch_dev'`, `DISPATCH_DB_USER='dispatch'` |

## Abnahme (Phase)

- [x] `php -v` → 8.5.x; `php -m` listet `curl, exif, fileinfo, gd, intl, mbstring, openssl, pdo_mysql`; `php -r "var_dump(gd_info()['WebP Support']);"` → `true`.
- [x] `php bin/dispatch migrate` auf leerer `dispatch_dev` → listet `001_setting.sql` bis `009_audit.sql`; zweiter Lauf → „Nichts zu tun."; `SHOW TABLES` → 16 Tabellen plus `schema_migrations`.
- [x] `SHOW CREATE TABLE posts` zeigt die FKs auf `media` und keinen auf `queue_items`; `queue_items` hat den FK auf `posts`.
- [x] `echo geheim | php bin/dispatch hash-password` → Zeile beginnt mit `$2y$` oder `$argon2`; `php bin/dispatch` ohne Befehl → Hilfe, Exit-Code 1.
- [x] Scratchpad-Skript: `Transaction::run` mit einer Ausnahme nach einem `INSERT` in `audit_log` → Zeile ist danach **nicht** da; ohne Ausnahme → Zeile da, `latest(1)` liefert sie mit `actor` und `details` als Array.
- [x] `php -l` über alle neuen PHP-Dateien inkl. `bin/dispatch`.

## Checkliste

- [x] Umgebung einrichten (Entscheidungen oben), `app/.env.local` mit lokalen DB-Werten
- [x] Migrationen und Klassen laut Tabelle
- [x] ADR-008 „Zuschnitt Meilenstein 1 und lokale Umgebung" (Tabellen-Zuschnitt und warum; CLI nur lokal, `token:create` nach Meilenstein 3; lokal MySQL 8.4 + PHP 8.5 statt MariaDB; JSON nur speichern, nie im SQL abfragen) + Zeile in `docs/decisions/README.md`
- [x] `docs/models.md` neu: eine Sektion pro Tabelle (Spalten, Typen, Defaults, FKs) aus den Migrationen; Kopf: „Quelle sind die Migrationen in `app/migrations/`"
- [x] `docs/code-map.md`: `bin/dispatch` (Befehle aktualisieren), `Db/Transaction`, `Domain/Actor`, `Domain/DomainError`, `Domain/AuditRepository`, Migrationen 001–009
- [x] `AGENTS.md` → „Wo du mehr findest": Datenmodell-Zeile auf `docs/models.md` umstellen; „Lokal starten": PHP 8.5 und MySQL 8.4 nennen

## Report-Back

- PHP 8.5.12 per winget, `php.ini` mit den acht Erweiterungen; PATH (Benutzer) so umgestellt, dass 8.5 vor 8.3 steht. MySQL 8.4.9 per winget (erster Lauf scheiterte an der Rechteabfrage, zweiter ging durch). Das winget-Paket legt keinen Dienst und kein Datenverzeichnis an: Datenverzeichnis `C:\Users\sasch\mysql-dispatch\data` per `--initialize-insecure` angelegt, `mysqld` läuft als Hintergrundprozess (kein Dienst), bindet nur `127.0.0.1:3306`. Nach einem Neustart muss er neu gestartet werden: `mysqld --no-defaults --basedir="C:\Program Files\MySQL\MySQL Server 8.4" --datadir=C:\Users\sasch\mysql-dispatch\data --bind-address=127.0.0.1`.
- `root` hat ein Passwort, Benutzer `dispatch` nur Rechte auf `dispatch_dev`; Werte in `app/.env.local` und `C:\Users\sasch\mysql-dispatch\pw.txt`.
- Alle Abnahmepunkte geprüft: `migrate` listet 001–009, zweiter Lauf „Nichts zu tun.", 16 Tabellen + `schema_migrations`, `posts` ohne FK auf `queue_items`, `Transaction`-Rollback und `latest(1)` mit Array-Details, `hash-password` liefert `$2y$`, Hilfe mit Exit 1, `php -l` sauber.
