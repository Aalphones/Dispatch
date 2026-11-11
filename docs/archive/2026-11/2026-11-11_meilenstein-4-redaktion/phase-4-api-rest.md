# Phase 4 — API: Queue, Zugänge, Kategorien, Reaktionen, Statistik

## Kontext

- `README.md` → „Endpunkte“ (Phase 4).
- `docs/decisions/017-…` (Tokens), `019-…` (Reaktionen), `009-…` (Kategorie-Felder intern vs. öffentlich).
- Code: `app/src/Domain/QueueService.php` (`add`, Pflichtfelder), `app/src/Domain/MediaService.php` (`store`, erlaubte Typen, `max_bytes` 50 MB in `config.php`), `app/src/Mcp/Tools/QueueTools.php` (`add_to_queue` als Muster für Anhänge und `own_material`), `app/src/Domain/TokenService.php`, `app/src/Api/TokenController.php` (Deploy-Weg, ADR-017), `app/src/Api/CategoryController.php`, `app/src/Domain/CategoryService.php`, `app/src/Domain/ReactionService.php`, `app/src/Domain/StatsService.php`, `app/src/Domain/StatsRepository.php`.
- Strato: `upload_max_filesize` und `post_max_size` je 128 MB (`docs/knowledge/INDEX.md`).

## AK der Phase

1. **`POST queue`** (multipart/form-data): Felder `title` (Pflicht), `brief`, `links` (eine URL je Zeile), `priority` (`normal`|`soon`|`now`), `depth` (`standard`|`deep`), `own_material` (`1`/fehlt), Dateien `files[]`. Jede Datei geht über `MediaService::store($bytes, $meta, 'upload', Actor::owner())` mit `own_material` → Urheber = `author_name`-Einstellung, Lizenz „Eigenes Material“ (wie `add_to_queue`). Danach `QueueService::add` mit den Medien-UIDs. Eine abgelehnte Datei bricht nichts ab: Antwort 201 mit `item` und `rejected: [{name, error, message}]`. `priority` abbilden auf die Ganzzahlen von `QueueService` (1 = am wichtigsten): `now` → 1, `soon` → 2, `normal` → 3. 🟡 `QueueService` setzt ohne Angabe 2; die Oberfläche schickt immer einen Wert, „Normal“ landet also bewusst bei 3. Überschreitet die Anfrage `post_max_size`, ist `$_POST` leer → 413 `too_large` mit Hinweis auf 50 MB je Datei.
2. **Tokens:** `GET tokens`; `POST tokens` Body `{"name","scopes":[…],"expires_days":null|30|90|365}` → Klartext einmal, `command` = `claude mcp add --transport http dispatch <DISPATCH_BASE_URL>/dispatch/mcp --header "Authorization: Bearer <Klartext>"`; `DELETE tokens/{id}`. Der bisherige Deploy-Weg (`token:create|list|revoke --remote`) funktioniert unverändert — Gegenprobe mit der CLI. Ein gültiges Access-JWT genügt (keine erneute Passworteingabe, Entscheidung des Inhabers).
3. **Kategorien:** `GET categories` liefert alle aktiven Kategorien mit Baum (`parent_id`), `interest`, `keywords`, `avoid`, `signals` = Anzahl `more`/`less`-Reaktionen der letzten 90 Tage auf Artikel mit dieser Hauptkategorie, `suggestion`: `interest - 1`, wenn `less ≥ more + 2` und `interest > 0`; `interest + 1`, wenn `more ≥ less + 2` und `interest < 5`; sonst `null`. `PATCH categories/{id}` ruft `CategoryService::update` mit `Actor::owner()`; nur `interest`, `keywords`, `avoid` erlaubt, andere Felder → 400 `invalid_field`.
4. **Reaktionen:** `POST posts/{id}/reaction` → `ReactionService::set` mit `Actor::owner()`; `reaction: null` löscht. `GET reactions` → `{"reactions":{"<post_id>":"more"|"less"}}` aller gespeicherten Reaktionen (eine Abfrage, für die Karten auf Start-, Kategorie- und Schlagwortseiten).
5. **Statistik:** `GET stats?days=14` (1–365) → `{"from","to","views","visitors","read_full_pct","avg_seconds","daily":[{day,views}],"top_posts":[{id,title,slug,views,read_full_pct}],"referrers":[{value,count}],"searches_without_hits":[…],"devices":[{value,count}]}` aus `StatsService::stats` (bestehende Gruppierungen nutzen; was fehlt, in `StatsRepository` ergänzen). `GET stats/posts/{id}?days=30` nach Kontrakt. `read_full_pct` = `reads_full / views` (gerundet, ohne Aufrufe 0). `avg_seconds` = `engaged_seconds / max(reads_half, 1)`. `posts/by-slug/{slug}` füllt jetzt `stats` (30 Tage).
6. Alle Routen ohne JWT → 401.

## Checkliste

- [x] `app/src/Api/QueueController.php`
- [x] `TokenController` um `list`, `create` (JWT oder Deploy), `revoke` erweitern; bestehenden Deploy-Weg erhalten
- [x] `CategoryController` um `list`, `patch`; `signals`/`suggestion` per Abfrage in `CategoryRepository` oder `ReactionRepository`
- [x] `app/src/Api/ReactionController.php`
- [x] `app/src/Api/StatsController.php`; `StatsRepository` ergänzen
- [x] Routen in `app/index.php`
- [x] Doku: `docs/routes.md`, `docs/code-map.md`

## Report-Back
Phase 4 komplett, lokal gegengeprüft (PHP-Server + MySQL): alle Routen, 401 ohne JWT, Deploy-Weg (`action`) und Token ohne Passwort, Upload mit abgelehnter Datei, 413 bei überschrittenem `post_max_size`.
- `priority` fehlt im Formular → `normal` (3), nicht der Dienst-Standard 2.
- Ungültige `links` fallen erst im Dienst auf, nachdem Dateien gespeichert wurden: die Medien bleiben in der Mediathek (Dubletten werden beim erneuten Senden wiedererkannt).
- `GET categories` / `GET reactions` antworten als `{"categories":[…]}` bzw. `{"reactions":{…}}`; neuer Fehlercode `invalid_field` → 400.
- `Request` kennt jetzt `form()`, `files()`, `contentLength()`.
- Lokale Testreste: Queue-Aufträge 9 und 10 samt Testbild.
