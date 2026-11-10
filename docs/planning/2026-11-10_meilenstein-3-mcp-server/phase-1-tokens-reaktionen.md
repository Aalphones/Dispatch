# Phase 1 — Tokens, Scopes, Reaktionen und Token-Wege

Rating: standard

## Kontext (vorher lesen)

- `README.md` dieses Plans → Kontrakt: „Tabellen“, „MCP-Token“, „Scopes“, „Neue Service-Methoden“ (TokenService, ReactionService).
- `docs/models.md` (Aufbau der Tabellen-Abschnitte), `app/migrations/007_change.sql` (Stil einer Migration), `app/migrations/009_audit.sql`.
- `app/src/Domain/ChangeService.php` + `ChangeRepository.php` (Muster Service/Repository, `Transaction::join`, `DomainError`, `AuditRepository::record`).
- `app/src/Domain/Actor.php`, `app/src/Domain/DomainError.php`.
- `app/src/Api/DeployController.php`, `app/src/Auth/DeployToken.php`, `app/index.php` (Routen-Tabelle).
- `bin/dispatch` (Aufbau der Befehle, `hash-password` als Muster für Ausgabe und Fehler).
- `deploy.env.example` (Schlüssel `PUBLIC_BASE_URL`, `DISPATCH_DEPLOY_TOKEN`).
- `docs/conventions/php.md`, `docs/conventions/dod.md`, ADR-009.
- Fehlerklassen geprüft (Vault): keine einschlägig.

## AK der Phase

1. `php bin/dispatch migrate` legt `tokens` und `post_reactions` exakt nach Kontrakt an; ein zweiter Lauf ändert nichts.
2. `php bin/dispatch token:create claude-code` gibt genau einmal den Klartext `dmcp_<64 hex>` und einen fertigen Befehl `claude mcp add --transport http dispatch <base_url>/dispatch/mcp --header "Authorization: Bearer dmcp_…"` aus; in der Datenbank steht nur der SHA-256-Hash, `scope` = `blog:read blog:write`.
3. `--scopes=blog:read,blog:admin` und `--days=30` wirken; ein unbekannter Scope, ein ungültiger Name oder ein doppelter aktiver Name brechen mit verständlicher Meldung ab (Exit-Code 1).
4. `token:list` zeigt Tabelle ohne Hash: id, Name, Scopes, Ablauf, widerrufen, zuletzt benutzt. `token:revoke <id>` setzt `revoked = 1`; Audit `token.create` / `token.revoke` mit Akteur `cli`.
5. `TokenService::authenticate` liefert für gültige Tokens `id, name, scopes` und setzt `last_used_at`; für unbekannte, widerrufene, abgelaufene oder Nicht-`dmcp_`-Werte `null` — Fälle einzeln durchgespielt und im Report-Back aufgelistet.
6. `POST /dispatch/api/tokens` mit gültigem Deploy-Token und Body `{"action":"create","name":"…","scopes":["blog:read"],"expires_days":null}` liefert `201` mit Token; `list` → `200` mit Liste; `revoke` → `200`. Ohne/mit falschem Deploy-Token → 401; andere Methode → 405; unbekannte `action` → 400. Akteur im Audit: `deploy`.
7. `token:create|token:list|token:revoke … --remote` liest `PUBLIC_BASE_URL` und `DISPATCH_DEPLOY_TOKEN` aus `deploy.env` im Repo-Wurzelverzeichnis und ruft den Endpunkt per `curl`-Erweiterung auf; Ausgabe wie lokal. Fehlt `deploy.env` oder ein Schlüssel → klare Meldung.
8. `php bin/dispatch reaction <slug> more|less|none [Notiz]` setzt bzw. löscht die Reaktion; unbekannter Slug → Meldung. `ReactionService::set` mit einem `mcp:`-Akteur wirft `DomainError('forbidden')`.

## Checkliste

- [x] `app/migrations/010_token.sql`: Tabelle `tokens` nach Kontrakt, `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` wie die übrigen Migrationen.
- [x] `app/migrations/011_reaction.sql`: Tabelle `post_reactions` nach Kontrakt.
- [x] `app/src/Auth/Scopes.php`: `final class Scopes` mit Konstanten `READ = 'blog:read'`, `WRITE = 'blog:write'`, `ADMIN = 'blog:admin'`, `ALL = [READ, WRITE, ADMIN]`; `public static function parse(string|array $value): array` (trennt an Leerzeichen und Kommas, entfernt Doppelte, sortiert in `ALL`-Reihenfolge, wirft `DomainError('invalid_value', …)` bei Unbekanntem oder leerer Liste, Meldung nennt die gültigen Scopes).
- [x] `app/src/Domain/TokenRepository.php`: `insert(array $fields): int`, `findActiveByHash(string $hash): ?array` (Gültigkeitsregel aus dem Kontrakt in SQL), `activeNameExists(string $name): bool`, `listMcp(): array` (neueste zuerst, ohne `token_hash`), `revoke(int $id): bool` (`rowCount() === 1`), `touch(int $id): void`.
- [x] `app/src/Domain/TokenService.php` nach Kontrakt. `create`: Name prüfen (`invalid_value`), `name_taken`, Scopes über `Scopes::parse`, `expiresDays` null oder 1–3650 (`invalid_value`), Klartext erzeugen, Hash speichern, Audit `token.create` (Details: Name, Scopes, Ablauf — nie Klartext oder Hash). `revoke`: unbekannte oder schon widerrufene ID → `DomainError('not_found')`; Audit `token.revoke`. `authenticate`: nur Werte mit Präfix `dmcp_` und Länge 69 prüfen, sonst sofort `null`.
- [x] `app/src/Domain/ReactionRepository.php` + `ReactionService.php`: `set` prüft Akteur (`isOwner()` oder Name `cli`, sonst `forbidden`), Reaktion ∈ {`more`, `less`, null}, Notiz ≤ 500 Zeichen; `INSERT … ON DUPLICATE KEY UPDATE`; null löscht. Audit `reaction.set`. `recent(int $days)`: Reaktionen der letzten `$days` Tage, neueste zuerst, je Zeile `post_id, slug, title, reaction, note, updated_at, categories` (Slugs, Haupt zuerst), `tags`.
- [x] `app/src/Api/TokenController.php`: Aufbau wie `DeployController` (Prüfung mit `DeployToken::matches` gegen `DISPATCH_DEPLOY_TOKEN`), Body per `json_decode(file_get_contents('php://input'), true)`; Aktionen `create`, `list`, `revoke` rufen `TokenService` mit `Actor::deploy()`; `DomainError` → `Response::error($code, $message, 400)`.
- [x] `app/index.php`: Route `'/dispatch/api/tokens' => ['POST', …TokenController…]`.
- [x] `bin/dispatch`: Befehle `token:create <name> [--scopes=…] [--days=N] [--remote]`, `token:list [--remote]`, `token:revoke <id> [--remote]`, `reaction <slug> more|less|none [Notiz]`. Lokal mit `Actor::cli()`. `--remote`: `deploy.env` zeilenweise lesen (`KEY=VALUE`, `#`-Zeilen und leere Zeilen überspringen, Wert ohne Anführungszeichen), `curl` POST mit `Authorization: Bearer <DISPATCH_DEPLOY_TOKEN>`, `Content-Type: application/json`, Zeitlimit 30 s. Hilfe-Ausgabe des CLI um die vier Befehle ergänzen.
- [x] ADR-017 `docs/decisions/017-mcp-tokens-und-scopes.md`: Format, Hash, Scopes ohne Einschluss, Wege (CLI lokal, `--remote` über den Deploy-Token bis Meilenstein 4 den Dialog „Zugänge“ bringt); betrachtete Alternative: Strato-Datenbank direkt von außen (nicht erreichbar bzw. ungeprüft) und Warten auf Meilenstein 4 (blockiert die Abnahme).
- [x] ADR-019 `docs/decisions/019-reaktionen-mehr-davon-weniger.md`: eine aktuelle Reaktion je Artikel statt Verlauf, nur Inhaber/CLI setzt sie, Briefing liest 90 Tage.
- [x] Doku: `docs/models.md` (Abschnitte `tokens (010)`, `post_reactions (011)`), `docs/code-map.md` (`token`, `reaction` ins Namensschema; CLI-Befehle in der Repo→Server-Tabelle; `Api/TokenController`), `docs/decisions/README.md` (ADR-017, ADR-019).

## Report-Back
- Alle acht AK lokal durchgespielt (`dispatch_dev`, Endpunkt über `php -S`, `--remote` über ein Wegwerf-Wurzelverzeichnis mit eigener `deploy.env` gegen localhost; nie gegen Strato).
- `authenticate`: gültig → Name/Scopes + `last_used_at`; unbekannt, ohne Präfix, falsche Länge, abgelaufen, widerrufen → `null`.
- Endpunkt: ohne/falschen Token 401, GET 405, unbekannte `action` 400, `create` 201, `list` 200; Audit-Akteur `deploy`. `revoke` mit Erfolg nur über Service/CLI geprüft, über HTTP nur der Fehlerfall.
- Abweichung: `curl_close` entfällt (in PHP 8.5 veraltet und wirkungslos).
- 🟡 `--remote` gegen Strato ist erst in Phase 6 geprüft.
