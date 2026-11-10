# Meilenstein 1 — Kern

Ziel: Die Domänenschicht von DISPATCH steht — Datenbank-Schema, Einstellungen, Dokumente mit Startinhalten, Kategorien, Änderungsvorschläge, Sanitizer, Medien mit Bildvarianten und URL-Import, Artikel mit Quellen, Revisionen und getrenntem Live-Stand, Formprüfung, Queue mit Reservierung, Statistik-Tabellen und Audit-Log. Alles ohne HTTP-Schnittstelle: MCP (Meilenstein 3) und Redaktions-API (Meilenstein 4) rufen später genau diese Services. Grundlage: `docs/PROJECT.md` → Meilenstein 1, Konzept → „Datenmodell", „Authentifizierung und Sicherheit" → „Schutz vor bösartigen Inhalten", „Redaktions-Workflows", „Qualität".

## Phasen

| # | Phase | Rating | Status |
|---|---|---|---|
| 1 | [Lokale Umgebung, Migrationen, CLI, Akteur und Protokoll](phase-1-umgebung-migrationen.md) | standard | complete |
| 2 | [Einstellungen, Dokumente, Kategorien, Änderungsvorschläge, Startinhalte](phase-2-einstellungen-dokumente-vorschlaege.md) | heikel | complete |
| 3 | [Sanitizer](phase-3-sanitizer.md) | heikel | complete |
| 4 | [Medien: HTTP-Client mit SSRF-Schutz, Ablage, Bildvarianten](phase-4-medien.md) | heikel | complete |
| 5 | [Artikel: Quellen, Kategorien, Revisionen](phase-5-artikel.md) | standard | complete |
| 6 | [Formprüfung und Freigabe-Übergänge](phase-6-formpruefung-freigabe.md) | heikel | complete |
| 7 | [Queue mit Reservierung](phase-7-queue.md) | heikel | complete |
| 8 | [Deploy mit Startinhalten, Abnahme auf Strato, Doku](phase-8-deploy-abschluss.md) | standard | complete |

Reihenfolge ist Pflicht: jede Phase baut auf den Klassen der vorigen auf.

## Kontrakt (gilt für alle Phasen)

**Akteur.** `Dispatch\Domain\Actor` (final readonly, `public string $name`). Fabriken: `owner()` → `owner`, `cli()` → `cli`, `mcp(string $tokenName)` → `mcp:<name>`, `oauth(string $clientName)` → `oauth:<name>`. `deploy()` → `deploy` (Seeder beim Deploy, ab Phase 8). `isOwner(): bool` ist nur für `owner` wahr. Jede schreibende Service-Methode nimmt als **letzten** Parameter `Actor $actor`. Freigeben, Zurückgeben, Zurückziehen, Verwerfen, Vorschläge übernehmen oder ablehnen verlangen `isOwner()` — sonst `DomainError('owner_only', …)`.

**Fachlicher Fehler.** `Dispatch\Domain\DomainError extends \RuntimeException` mit `public readonly string $errorCode` und `public readonly array $details` (Default `[]`). Konstruktor `(string $errorCode, string $message, array $details = [])`. Die Meldung ist ein deutscher Satz, der sagt, wie es richtig geht. MCP und API übersetzen ihn später (MCP: `isError: true`, API: 4xx).

**Services.** `Dispatch\Domain\<X>Service`, Konstruktor `(PDO $pdo, array $config)`; jeder Service legt seine Repositories selbst an (`new <X>Repository($pdo)`). Kein Container. Services kennen kein HTTP.

**Repositories.** `Dispatch\Domain\<X>Repository`, Konstruktor `(PDO $pdo)`, nur SQL, ausschließlich Prepared Statements, Zeilen als `array<string, mixed>`; JSON-Spalten werden im Repository mit `json_decode(…, true, 512, JSON_THROW_ON_ERROR)` gelesen und mit `json_encode(…, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)` geschrieben.

**Transaktion.** `Dispatch\Db\Transaction::run(PDO $pdo, callable $work): mixed` — `beginTransaction`, `$work()`, `commit`; bei Ausnahme `rollBack` und weiterwerfen. `Transaction::join(PDO, callable)` arbeitet in einer laufenden Transaktion mit, sonst wie `run` — Services nutzen `join`, damit `ChangeService::apply` Fach-Änderung, Statuswechsel und Audit atomar hält (ADR-009). Jede Service-Methode, die mehr als eine Zeile oder Tabelle schreibt, läuft darin — **inklusive** Audit-Eintrag.

**Protokoll.** `Dispatch\Domain\AuditRepository::record(Actor $actor, string $action, string $target, array $details = []): void`. `action` ist `<domäne>.<verb>` (z. B. `post.create`, `change.apply`), `target` ist `<domäne>:<schlüssel>` (z. B. `post:12`, `setting:site_title`).

**Zeit.** Alle Zeitstempel `DATETIME` in UTC, geschrieben und verglichen mit `UTC_TIMESTAMP()` im SQL, nie in PHP berechnet.

**SQL-Dialekt.** Ziel ist MySQL 8.0.36 (Strato), lokal MySQL 8.4. Keine JSON-Pfad-Abfragen (`->`, `->>`, `JSON_EXTRACT`) im SQL — JSON-Spalten werden nur gespeichert und in PHP ausgewertet. Keine Trigger, keine Prozeduren (der Migrator teilt an `;` + Zeilenende).

## Finale Abnahme (Gesamtergebnis)

1. `deploy.cmd` auf Strato endet grün; die Antwort des Deploy-Endpunkts listet die Migrationen `001` bis `009` und die angelegten Startinhalte; ein zweiter Deploy liefert `migrations: []` und `seeded: []`; `/card/` und `/scanner/capture` antworten wie vorher.
2. Lokal lässt sich per Skript der komplette Weg durchspielen: Medium importieren, Artikel mit Quellen und Kategorie anlegen, Formprüfung bis fehlerfrei, einreichen, als Inhaber freigeben — danach liefert `PostRepository::livePosts()` genau diesen Stand; eine weitere Änderung am Arbeitsstand ändert `livePosts()` nicht, bis sie erneut freigegeben ist.
3. Kein Akteur außer `owner` kann freigeben, zurückziehen, verwerfen oder Vorschläge übernehmen; öffentliche Änderungen durch andere Akteure landen als Änderungsvorschlag.
4. Der Sanitizer besteht die XSS-Fallliste aus Phase 3, die Formprüfung jede Regel aus Phase 6, die Queue den Zwei-Prozess-Test aus Phase 7.
5. `docs/models.md` beschreibt alle Tabellen, `docs/code-map.md` alle neuen Ordner und Klassen; ADR-008 bis ADR-012 liegen vor.

## Voraussetzungen beim Inhaber

- Freigabe für die lokale Installation von PHP 8.5 und MySQL 8.4 per `winget` (Phase 1).
- Vor Phase 8: `deploy.cmd` darf auf Strato laufen (Deploy wirkt auf die Live-Domain).

## Summary

Die Domänenschicht steht: 9 Migrationen (16 Tabellen), Akteur/Fehler/Transaktion/Protokoll, Einstellungen, Dokumente, Kategorien, Änderungsvorschläge, Startinhalte, Sanitizer, Medien mit SSRF-Schutz und Bildvarianten, Artikel mit Quellen und Revisionen, Formprüfung und Statusmaschine, Queue mit atomarer Reservierung. Der Deploy-Endpunkt legt Startinhalte an; auf Strato am 2026-11-10 ausgeliefert (9 Migrationen, 17 Startinhalte, zweiter Lauf leer, Rauchtest grün).

## Files touched

`app/src/Domain/`, `app/src/Util/`, `app/src/Db/`, `app/src/Api/DeployController.php`, `app/migrations/001–009`, `app/defaults/`, `bin/dispatch`, `docs/` (models, code-map, PROJECT, knowledge/INDEX, ADR-008 bis ADR-012).

## Commits

1be5b80, 85bd9ca, 0fe3108, 8b91d2c, e82d27b, 09891d0, d9f9e28, 202ae66

## Deviations from plan

- Abnahme „frische `dispatch_dev`“: der App-Benutzer darf keine Datenbank anlegen; stattdessen wurden alle Tabellen gelöscht und der Deploy-Endpunkt lokal darüber laufen gelassen.
- Header-Block von `/card/` und `/scanner/capture` nicht erneut Byte-genau verglichen; geprüft wurden Status 200 und der Rauchtest von `deploy.cmd`.

## Follow-ups

- Meilenstein 2 (Builder und Theme) planen.

## Follow-ups
