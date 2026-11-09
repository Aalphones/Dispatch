# Phase 7 — Queue mit Reservierung

**Rating:** heikel (gleichzeitige Agentenläufe) · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt"
- Phase 1 → Migration `006_queue.sql`; Phase 4 → `MediaService::store` (Anhänge); Phase 5/6 → `PostService` (`create`, `submit`, `approve`, `discard`)
- Konzept → „Auftrag aus der Queue", „Reihenfolge im Briefing" (Priorität und Alter), „Datenmodell" (Tabellen `queue_items`, `queue_media`)
- Fehlerklassen: `mode-planning` → „Zeitabhängige Prüfungen nicht am Umschaltpunkt als Wächter" — einschlägig für den Ablauf der Reservierung, siehe Entscheidungen

## Entscheidungen (fest)

**Anlegen** `add(array $input, Actor $actor)`: `title` 1–200 (Pflicht), `brief` ≤ 10 000, `links` Liste von `http(s)://`-URLs (≤ 2000 Zeichen, höchstens 20), `priority` 1–3 (Default 2), `depth` `standard|deep`, `category` (Slug einer aktiven Kategorie, optional), `media` Liste `{uid, note?, own_material?}` (UID muss existieren). Status `open`, `created_by = actor`. Audit `queue.add`.

**Reservieren — atomar, ohne vorheriges Lesen als Wächter:**

```sql
UPDATE queue_items
SET status = 'claimed', claimed_by = ?, claim_expires = UTC_TIMESTAMP() + INTERVAL ? MINUTE, updated_at = UTC_TIMESTAMP()
WHERE id = ? AND (status = 'open' OR (status = 'claimed' AND claim_expires <= UTC_TIMESTAMP()))
```

`rowCount() === 1` → reserviert; `0` → `DomainError('already_claimed', 'Auftrag ist reserviert oder nicht mehr offen.')`. Dauer = `SettingService::get('queue_claim_minutes')`. `claim(?int $id, Actor $actor)`: mit `id` genau dieser; ohne `id` die Kandidaten `SELECT id … WHERE <gleiche Bedingung> ORDER BY priority ASC, created_at ASC, id ASC LIMIT 5` der Reihe nach versuchen, erster Erfolg gewinnt; keiner → `DomainError('queue_empty', …)`.

**Abgelaufene Reservierung:** Kein Hintergrundprozess. Beim Lesen (`list`, `get`) wird ein Eintrag mit `status = 'claimed' AND claim_expires <= UTC_TIMESTAMP()` als `open` ausgegeben (`effective_status` per `CASE` im SQL), in der Tabelle bleibt er unverändert, bis ihn jemand neu reserviert.

**Wer mit einem reservierten Auftrag arbeiten darf — Rohzustand, nicht Uhr:** Geprüft wird `claimed_by = actor.name` (oder Inhaber), **nicht** „Reservierung läuft noch". Wer seine abgelaufene Reservierung noch hält (kein anderer hat neu reserviert), darf also einreichen; hat ein anderer neu reserviert, steht dessen Name in `claimed_by` und der erste wird abgewiesen (`not_claimant`). So kann ein Lauf, der knapp über der Frist fertig wird, nicht an einem Uhrvergleich scheitern.

**Freigeben** `release(int $id, string $status, ?string $note, Actor $actor)`: nur `claimed_by` oder Inhaber, nur aus `claimed`; `status` ∈ `open|failed`; `failed` verlangt `note` (1–2000) → `result_note`. Setzt `claimed_by`/`claim_expires` auf `NULL`. `cancel(int $id, Actor $actor)`: nur Inhaber, aus `open|claimed` → `cancelled`.

**Verknüpfung mit Artikeln** (in `PostService`, gleiche Transaktion):

| Ereignis | Bedingung | Queue-Wirkung |
|---|---|---|
| `create` mit `queue_item_id` | Auftrag `claimed`, `claimed_by = actor` oder Inhaber (`not_claimant`) | Artikel bekommt `origin = queue`, `depth` des Auftrags; `queue_items.post_id` = Artikel |
| `submit` | Artikel hat `queue_item_id` | Auftrag → `review` |
| `approve` | dito | Auftrag → `done` |
| `discard` | dito | Auftrag → `open`, `post_id`, `claimed_by`, `claim_expires` → `NULL` |

`returnToAgent` und `unpublish` lassen den Auftrag unverändert.

**Eigenes Material:** `own_material = true` an einem Anhang setzt beim Anlegen am Medium, falls leer, `credit` = `author_name` und `license` = „Eigenes Material" (über `MediaService::updateMeta` mit dem Akteur des Aufrufs; der Inhaber ändert direkt).

## Dateien

| Datei | Inhalt |
|---|---|
| `app/src/Domain/QueueRepository.php` | `insert(array $fields): int`, `attachMedia(int $itemId, int $mediaId, ?string $note, bool $own): void`, `find(int $id): ?array` (mit `effective_status`), `media(int $itemId): array`, `list(array $statuses, int $limit, int $offset): array` (Filter auf `effective_status`, sortiert Priorität, Alter), `claimCandidates(int $limit): list<int>`, `tryClaim(int $id, string $actor, int $minutes): bool` (SQL oben), `update(int $id, array $fields): void` |
| `app/src/Domain/QueueService.php` | `add`, `get(int $id): array` (mit Medien), `list(array $statuses = ['open','claimed','review'], …)`, `claim`, `release`, `cancel`; je Aktion Audit `queue.<aktion>`; dazu für `PostService`: `assertClaimant(int $id, Actor $actor): array`, `linkPost(int $id, int $postId)`, `markReview(int $id)`, `markDone(int $id)`, `reopen(int $id)` |
| `app/src/Domain/PostService.php` | Verknüpfung laut Tabelle oben; `origin = queue` nur über `queue_item_id` |

## Abnahme (Phase)

Scratchpad-Skripte gegen `dispatch_dev`.

- [ ] **Zwei gleichzeitige Agenten:** Skript `claim.php <id> <name> <startzeit>` wartet per `time_sleep_until(<startzeit>)` und ruft dann `claim(id, Actor::mcp(name))`. Zwei Prozesse per `Start-Process php` mit derselben Startzeit (+3 s) auf denselben offenen Auftrag, 20 Durchläufe → in **jedem** Durchlauf genau ein Erfolg und ein `already_claimed`; Zählung im Report-Back.
- [ ] Ohne `id`: drei offene Aufträge mit Priorität 3, 1, 2 → erster `claim()` bekommt Priorität 1, zweiter Priorität 2.
- [ ] Ablauf: `queue_claim_minutes` per Setting auf 15, Reservierung per SQL auf `claim_expires = UTC_TIMESTAMP() - INTERVAL 1 MINUTE` setzen → `get()` zeigt `effective_status = open`; Agent B reserviert neu; Agent A (`create` mit `queue_item_id`) → `not_claimant`.
- [ ] Abgelaufen, aber nicht neu reserviert → Agent A legt den Artikel an, reicht ein → Auftrag `review`; Inhaber gibt frei → Auftrag `done`.
- [ ] `release(…, 'failed', null)` → Fehler (Begründung fehlt); mit Begründung → `failed`, `result_note` gesetzt; `release` durch fremden Agenten → `not_claimant`.
- [ ] `discard` eines Queue-Artikels → Auftrag wieder `open`, `post_id` leer.
- [ ] `add` mit `own_material` an einem Medium ohne `credit` → `credit` = `author_name`, `license` = „Eigenes Material".

## Checkliste

- [ ] Dateien laut Tabelle
- [ ] Abnahme fahren, Ergebnisse im Report-Back
- [ ] `docs/models.md`: Queue-Status, Reservierung, Verknüpfung mit Artikeln
- [ ] `docs/code-map.md`: `Domain/Queue*`

## Report-Back
