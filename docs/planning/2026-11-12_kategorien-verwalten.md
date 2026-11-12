# Kategorien verwalten: anlegen und löschen im Redaktionsmodus

Zeremonie-Tier: Lean (private). Auslöser: Der Inhaber kann Kategorien weder löschen noch anlegen — die Oberfläche kann nur Interessen ändern (`PATCH /dispatch/api/categories/{id}`), MCP kann Öffentliches nur vorschlagen, und eine Löschfunktion gibt es nirgends (Grep über `app/src`, `bin/dispatch`: kein `DELETE FROM categories`). Folge: die Abnahme-Kategorie „Testlauf“ lässt sich nicht entfernen.

## Entscheidungen (fallen hier, nicht beim Umsetzen)

1. **Löschen und Anlegen nur durch den Inhaber, nie per MCP.** Agenten schlagen weiter nur vor (ADR-009). Ein Lösch-Vorschlag für Agenten entfällt: die Änderungsansicht kennt nur Feld-Werte, kein „Entfernen“.
2. **Löschen mit Artikeln:** Kategorie mit zugeordneten Artikeln wird nur gelöscht, wenn der Inhaber eine Ersatz-Kategorie (`move_to`) wählt. Artikel, die **nur** diese Kategorie haben, bekommen `move_to` als Hauptkategorie. Artikel mit weiteren Kategorien verlieren nur die Zuordnung; war die gelöschte die Hauptkategorie, wird die Kategorie mit dem alphabetisch ersten Namen unter den übrigen die Hauptkategorie. Ohne `move_to` und mit Artikeln: Fehler `category_in_use` (HTTP 409) mit `count`.
3. **Freigegebene Stände bleiben unangetastet.** Revisions-Snapshots werden nicht umgeschrieben; `SiteData::buildPost` überspringt unbekannte Slugs bereits (`$categoriesBySlug[...] ?? null` → `continue`), das ist derselbe Weg wie bei abgeschalteten Kategorien. Nach dem nächsten Freigeben eines Artikels stimmt der Snapshot wieder.
4. **Unterkategorien:** Kategorie mit Unterkategorien lässt sich nicht löschen (`has_children`, 409) — der Inhaber löscht erst die unteren.
5. **Warteschlange und Reaktionen:** `queue_items.category_id` wird per Fremdschlüssel `SET NULL`; Reaktionen hängen nur über `post_categories` an Kategorien (kein Fremdschlüssel). Beides braucht keinen Code.
6. **Slug beim Anlegen:** Der Inhaber gibt nur Namen (und optional Oberkategorie) ein; der Slug entsteht serverseitig aus dem Namen (`Util\Text::slugify`). Ist er vergeben → `slug_taken` mit der bestehenden Meldung.
7. **ADR:** `docs/decisions/026-kategorien-anlegen-loeschen-durch-inhaber.md` (Punkte 1–4 in zehn Zeilen) — Nummer geprüft: höchste vorhandene ADR ist 025, `docs/planning/` enthält keine Reservierung.

Alternative, bewusst verworfen: Lösch-Vorschlag für Agenten über die Änderungsansicht. Mehr Oberfläche, kein Bedarf — der Inhaber ist der Einzige, der aufräumt.

## Overview

| Phase | Inhalt | Rating | Status |
|---|---|---|---|
| 1 | Backend: `CategoryService::delete`, Repository, API (`DELETE`, Anlegen per Anmeldung, `name` im `PATCH`), CLI `category:delete`, bessere Meldung bei überholtem Vorschlag | heikel | complete |
| 2 | Oberfläche: „Neue Kategorie“, „Umbenennen“ und „Löschen“ im Dialog „Deine Interessen“, Doku, ADR | standard | pending |

## Finale Abnahmekriterien

- AK1: `DELETE /dispatch/api/categories/{id}` ohne `move_to` auf eine Kategorie ohne Artikel löscht sie (200, `{deleted: id, rebuilt, rebuild_error}`); eine Kategorie mit Artikeln liefert 409 `category_in_use` mit `count` und ändert nichts.
- AK2: Mit `move_to` gilt die Verschiebe-Regel aus Entscheidung 2; die gesamte Aktion läuft in einer Transaktion (bei Fehler bleibt alles unverändert).
- AK3: Kategorie mit Unterkategorie → 409 `has_children`; `move_to` auf sich selbst, auf eine abgeschaltete oder auf eine Unterkategorie der gelöschten → 400 `invalid_value`.
- AK4: Nicht angemeldet → 401 wie die anderen Redaktions-Routen. `audit_log` bekommt `category.delete` mit Slug und Anzahl verschobener Artikel.
- AK5: `POST /dispatch/api/categories` legt mit Anmeldung (ohne Deploy-Token) eine aktive Kategorie aus `name` an; mit Deploy-Token verhält es sich unverändert.
- AK6: Dialog „Deine Interessen“: Knopf „Neue Kategorie“ (Name, optional Oberkategorie) und je Zeile „Löschen“; bei Artikeln erscheint eine Auswahl der Ersatz-Kategorie mit der Anzahl betroffener Artikel; jede Aktion hat ein i-Symbol mit Erklärung; nach Erfolg wird die Liste neu geladen. Ein Erstnutzer kommt ohne Anleitung durch.
- AK6a: `PATCH /dispatch/api/categories/{id}` akzeptiert für den Inhaber zusätzlich `name` (sofort wirksam, Slug bleibt); die Antwort enthält bei geändertem Namen `rebuilt`/`rebuild_error`. Im Dialog hat jede Zeile „Umbenennen“ (Name wird zum Eingabefeld, Enter speichert, Escape bricht ab) mit i-Symbol: „Ändert den Anzeigenamen; die Adresse (Slug) bleibt, damit bestehende Links weiter gehen.“
- AK7: Scheitert die Übernahme eines überholten Kategorie-Vorschlags an `slug_taken`, nennt die Meldung, dass der Vorschlag überholt ist und abgelehnt werden kann.
- AK8: `php -l` auf alle geänderten PHP-Dateien fehlerfrei (CI-Prüfung, ADR-004: keine automatisierten Tests); `docs/routes.md`, `docs/code-map.md`, `docs/models.md` (falls betroffen) und `docs/glossary.md` nachgezogen.

## Phase 1 — Backend (heikel)

**Kontext zum Lesen:** `app/src/Domain/CategoryService.php`, `CategoryRepository.php`, `PostRepository.php` (Zeilen um `replaceCategories`, `categories`), `app/src/Api/CategoryController.php`, `Api/Rebuild.php`, `Api/ApiErrors.php`, `Domain/ChangeService.php` (Zeilen 60–120), `app/index.php` (Zeilen 30–40), `bin/dispatch` (Fall `category:create`, Zeilen um 500), `docs/decisions/009-akteur-inhaber-rechte-aenderungsvorschlaege.md`, `app/migrations/005_post.sql` und `006_queue.sql`. Fehlerklassen der Technik (PHP/MySQL): nicht eingesehen, siehe Konfidenz.

- [x] `CategoryRepository`: `delete(int $id): void` (`DELETE FROM categories WHERE id = ?`), `postIds(int $categoryId): list<int>` (`SELECT post_id FROM post_categories WHERE category_id = ?`).
- [x] `PostRepository` (oder `CategoryRepository`, je nachdem, wo `post_categories` schon bedient wird — dort ergänzen): `moveCategoryLinks(int $fromId, ?int $toId): int` — Verschiebe-Regel aus Entscheidung 2, gibt Anzahl betroffener Artikel zurück. Reihenfolge je Artikel: (a) hat er andere Zuordnungen → Zeile der alten löschen, war sie primär und gibt es keine primäre mehr → die erste nach `ORDER BY c.name, c.id` auf `is_primary = 1`; (b) hat er nur die alte → `UPDATE post_categories SET category_id = ?` auf `toId`, `is_primary = 1`; (c) hat er schon `toId` (aber nicht nur die alte) → Zeile der alten löschen, `toId` bleibt, Primär-Regel wie (a). Alles ohne `UNIQUE`-Verletzung (`PRIMARY KEY (post_id, category_id)`).
- [x] `CategoryService::delete(int $id, ?int $moveTo, Actor $actor): array{deleted: int, moved: int}`: `if (!$actor->isOwner()) throw new DomainError('owner_only', 'Kategorien löscht nur der Inhaber.')`; `Transaction::join`; `get($id)`; `hasChildren` → `DomainError('has_children', ..., 409-fähig)`; Artikelzahl über `postIds`; Zahl > 0 und `$moveTo === null` → `DomainError('category_in_use', ..., ['count' => n])`; `$moveTo` prüfen (existiert, ≠ `$id`, aktiv, `parent_id` ≠ `$id`) sonst `invalid`-Fehler wie `normalizeParent`; verschieben; `delete`; `audit->record($actor, 'category.delete', 'category:' . $id, ['slug' => ..., 'moved' => n, 'move_to' => $moveTo])`.
- [x] Keine Änderung an `ApiErrors::respond`: geprüft, `invalid_value`/`invalid_json`/`invalid_field` → 400, `not_found` → 404, alles andere (also `category_in_use`, `has_children`, `owner_only`, `slug_taken`) → 409.
- [x] `CategoryController::delete(Request $request, int $id): never` — `ApiAuth::owner`, `move_to` aus dem JSON-Body (optional, Ganzzahl oder fehlend), `CategoryService::delete`, Antwort `['deleted' => $id, 'moved' => n] + Rebuild::run(...)`. Route in `app/index.php`: `DELETE /dispatch/api/categories/{id}` (Router-Methode `DELETE` prüfen: wenn `Router::add` nur bekannte Verben kennt, dort ergänzen).
- [x] `CategoryController::handle` (`POST /dispatch/api/categories`): Akteur wie bisher `Actor::owner()`, wenn `DeployToken::matches`; sonst `ApiAuth::owner($request)`. Fehlt `slug` und ist `name` gesetzt, `slug` per `Util\Text::slugify($name)` setzen. Antwort enthält zusätzlich `Rebuild::run` nur bei Anmeldung (die Deploy-Variante behält ihre Antwort).
- [x] `bin/dispatch`: `category:delete <slug> [--move-to=<slug>] [--remote]` nach dem Muster von `category:create` (lokal: `CategoryService::delete` mit `Actor::owner()` — geprüft: `Actor::cli()` hat den Namen `cli` und gilt **nicht** als Inhaber, `isOwner()` ist nur für `owner` wahr; `--remote` für `category:delete` **nicht** anbieten, Löschen auf dem Server läuft über den Redaktionsmodus).
- [x] `CategoryController::patch`: Erlaubt sind für den Inhaber `CategoryService::INTERNAL_FIELDS` plus `name` (neue Konstante `OWNER_PATCH_FIELDS` im Controller; Fehlermeldung „Erlaubt: …“ zählt sie auf). `CategoryService::update` schreibt `name` für den Inhaber bereits direkt (`$actor->isOwner()` → `$direct`) — keine Service-Änderung. Wurde `name` mitgeschickt, die Antwort um `Rebuild::run($this->config, Connection::open())` ergänzen. Slug und Beschreibung bleiben unveränderbar.
- [x] `ChangeService::apply`: schlägt `applyChange` mit `DomainError` `slug_taken` bei `entity === 'category'` und `key === 'new'` fehl, die Meldung um „Der Vorschlag ist überholt: Die Kategorie gibt es schon. Lehne ihn ab.“ ergänzen (Fehlercode bleibt `slug_taken`). Fundstelle: `ChangeService.php` Zeilen 100–120; Anhängen an die bestehende Meldung, kein neuer Fehlercode.
- [x] `php -l` über jede geänderte Datei.

## Phase 2 — Oberfläche und Doku (standard)

**Kontext zum Lesen:** `app/theme/redaktion/category.js` (ganz), `app/theme/redaktion/ui.js` (`dialog`, `el`, `info`, `rahmen`, `toast`, `zeigeFehler`), `app/theme/redaktion/api.js`, `docs/conventions/frontend.md`, `docs/design/entwurf-website.html` (nur Abschnitt Kategorien/Interessen, falls vorhanden), Mechanismus-Referenz: Muster der Zeile `interestRow` in `category.js`.

- [ ] `category.js`: Funktion `newCategoryForm(categories, reload)` — Eingabe Name (Pflicht), Auswahl Oberkategorie (nur Hauptkategorien, Standard „keine“), Knopf „Anlegen“ → `api('POST', 'categories', {name, parent_id?})` → `reload()`. Fehler in `errorLine`. i-Symbol: „Der Slug (Adresse) entsteht aus dem Namen. Neue Kategorien starten mit Interesse 3 und sind sofort aktiv.“
- [ ] `category.js`: je Zeile in `interestRow` ein Knopf „Löschen“ (`class="knopf leise klein-k"`). Klick → `api('DELETE', 'categories/' + id)`. Antwort 409 `category_in_use` → Auswahl-Dialog: Text „N Artikel gehören dazu. Verschieben nach:“ plus `<select>` mit allen aktiven Kategorien außer der gelöschten und deren Unterkategorien, Knopf „Löschen und verschieben“ → `api('DELETE', 'categories/' + id, {move_to})`. Antwort `has_children` → die Fehlermeldung der API zeigen. Vor dem ersten Löschen eine Bestätigung („Kategorie „X“ wirklich löschen? Das lässt sich nicht rückgängig machen.“) über `dialog` aus `ui.js`. i-Symbol am Löschen-Knopf: „Löscht die Kategorie endgültig. Artikel darin werden auf eine Ersatz-Kategorie verschoben.“
- [ ] `category.js`: je Zeile „Umbenennen“ (Knopf neben dem Namen). Klick → `<b>` wird `<input>` mit dem aktuellen Namen; Enter → `api('PATCH', 'categories/' + id, {name})`, Escape oder Fokusverlust ohne Änderung → zurück. Antwort in `category` übernehmen (`Object.assign`, `render()` wie in `save`). Leerer Name wird nicht gesendet. i-Symbol wie AK6a.
- [ ] Nach Erfolg `interessen()` neu rendern (Liste neu laden); `toast('Kategorie gelöscht')`; bei `rebuilt === false` den `rebuild_error` im Toast nennen (wie andere Redaktions-Aktionen, Muster in `app/theme/redaktion/` per Grep nach `rebuild_error`).
- [ ] `api.js`: prüfen, ob `api('DELETE', …, body)` einen Body mitsenden kann; sonst dort ergänzen.
- [ ] Doku: `docs/routes.md` (neue/geänderte Zeilen Kategorien), `docs/code-map.md` (CLI-Zeile: `category:delete`), `docs/glossary.md` (Begriff „Ersatz-Kategorie“), `docs/decisions/026-kategorien-anlegen-loeschen-durch-inhaber.md`, `docs/knowledge/GAPS.md` falls Lücken offenbleiben.
- [ ] Deploy nach Inhaber-Freigabe über `deploy.cmd` (ADR-005); dort anschließend „Testlauf“ löschen (Artikel dort auf eine neue Kategorie verschieben oder, falls es der Test-Artikel ist, vorher als Inhaber verwerfen — veröffentlichte Artikel lassen sich nur zurückziehen, nicht verwerfen).

## Konfidenz

- Geprüft vor dem Vorlegen: `ApiErrors` bildet unbekannte Codes auf 409 ab (kein Eingriff nötig); `Router::add` nimmt jedes Verb, `DELETE` geht also im Code; `Actor::cli()` ist kein Inhaber (CLI nutzt `Actor::owner()`).
- Wackelig: (1) ob die Seitenvorlagen mit einem live geschalteten Artikel **ohne** gültige Hauptkategorie umgehen (Snapshot enthält gelöschten Slug; `SiteData::buildPost` überspringt ihn, aber ob die Vorlage `primary_category === null` verträgt, ist ungelesen) — Check: Kategorie mit Artikel abschalten, `php bin/dispatch build`, Artikel- und Kategorieseite ansehen, bevor Phase 2 beginnt — **erledigt nach Phase 1 (am Code):** alle Verbraucher von `primary_category` (`Seo.php`, `functions.php` Kicker, `Builder::breadcrumbs`/`collectArticles`/verwandte Artikel) fangen `null` ab, die Vorlagen unter `app/templates/` greifen nicht direkt darauf zu; kein Umbau nötig; (2) ob Strato-Apache `DELETE` an PHP durchlässt (`PATCH` tut es bereits) — Check: nach dem Deploy eine Wegwerf-Kategorie per Dialog löschen.
- Fehlerklassen aus dem Vault: nicht gelesen; Prüfpunkte hier aus dem Code abgeleitet (Transaktion, Fremdschlüssel `RESTRICT`/`SET NULL`).

## Summary / Files touched / Commits / Deviations / Follow-ups

(beim Archivieren füllen)
