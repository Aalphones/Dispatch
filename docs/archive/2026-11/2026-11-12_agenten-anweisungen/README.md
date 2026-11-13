# Anweisungen für Agenten: im Redaktionsmodus einsehen und bearbeiten

Zeremonie-Tier: Vollplan (private). Auslöser: Der Inhaber will alles einsehen können, was ein Agent von DISPATCH an Anweisungen bekommt — Redaktionsrichtlinien, Präferenzen, Server-Anweisung, Prompts, Workflows, Style-Guide und die Beschreibungen aller Werkzeuge samt ihrer Angaben. Wortlaut: „Alle Anweisungen und toolcalls mitsamt anweisungen an den toolcalls selbst sollten zumindest für den Redakteur irgendwo einsehbar sein.“ Heute geht das nur über MCP (`get_document`, `get_style_guide`) oder den Code. Entscheidungen des Inhabers (2026-11-12): Werkzeuge nur als Beschreibungen (kein Protokoll der Aufrufe); Richtlinien und Präferenzen gleich im selben Dialog bearbeitbar; erst ein Entwurf.

Entwurf (verbindlich mit der Freigabe dieses Plans): [`artifacts/entwurf-agenten-anweisungen.html`](artifacts/entwurf-agenten-anweisungen.html), Erläuterung in [`artifacts/README.md`](artifacts/README.md).

**Reihenfolge zu anderen Plänen:** Erst umsetzen, wenn `2026-11-12_zeitversetzt-veroeffentlichen` umgesetzt ist. Beide hängen Routen an `app/index.php`, Stile an `redaktion.css` und einen Menüeintrag an `konto.js`; nacheinander gibt es keine Überschneidung.

## Entscheidungen (fallen hier, nicht beim Umsetzen)

1. **Ein Dialog „Anweisungen für Agenten“** im Kontomenü, direkt nach „Zugänge für Agenten“ (Symbol `ki`), mit fünf Reitern: Übersicht, Richtlinien, Präferenzen, Werkzeuge (mit Anzahl), Abläufe. Keine eigene Seite, keine Adresse mit `#` — der Reiter ist Zustand des Moduls.
2. **Änderbar sind nur die beiden internen Dokumente** `editorial_guidelines` und `preferences` (`DocumentService::INTERNAL_KEYS`). Server-Anweisung, Prompts, Workflows, Style-Guide und Werkzeugbeschreibungen bleiben im Code und sind nur lesbar (Marke „fest im Code“). Die Formvorgaben stehen in der Konfiguration (`config['form_rules']`, Marke „fest in der Konfiguration“).
3. **Speichern legt eine neue Fassung an** (bestehender Weg `DocumentService::update`, Revision, `document_revisions`, Audit). Neu ist nur die **Konfliktprüfung**: Der Browser schickt die Fassung mit, auf der er aufgebaut hat (`base_revision`). Weicht sie von der aktuellen ab, antwortet der Server `409 revision_conflict` mit Fassung, Akteur und Zeit der neueren Fassung. Der Inhaber kann dann „Meine Fassung trotzdem speichern“ (`force: true`) oder die Änderung des Agenten ansehen. Die Prüfung läuft auf der gesperrten Zeile (`SELECT … FOR UPDATE`) in derselben Transaktion wie das Schreiben. Agenten (MCP `update_document`) schicken keine Basis und speichern wie bisher.
4. **Wiederherstellen** einer älteren Fassung legt eine neue Fassung mit deren Inhalt an (Notiz „Fassung N wiederhergestellt“); nichts wird gelöscht. „Rückgängig“ nach dem Speichern ist dasselbe Wiederherstellen der Fassung davor.
5. **Der Server rendert Markdown**, der Browser nicht: `Util\Markdown` lernt Inline-Code, Code-Blöcke (```) und Tabellen (`| … |`). Die Vorschau im Editor holt das HTML über eine Route. Der Vergleich zweier Fassungen nimmt den bestehenden `Util\Diff::html` auf das gerenderte HTML beider Fassungen. Damit gibt es genau einen Markdown-Umsetzer; die öffentlichen Dokumentseiten (Über, Impressum, Datenschutz) können dieselben Mittel nutzen.
6. **Werkzeuge kommen aus der Registry**, nicht aus einer zweiten Liste: `McpController::registry()` wird öffentlich, die Registry merkt sich je Werkzeug einen **Bereich** (`Registry::group()` vor jedem `register`-Aufruf). Bereiche: „Briefing und Dokumente“ (BriefingTools, DocumentTools), „Kategorien und Einstellungen“ (CategoryTools, SettingTools, ChangeTools), „Queue“ (QueueTools), „Artikel“ (PostTools), „Medien“ (MediaTools), „Statistik und Website“ (StatsTools, SiteTools). Ein neues Werkzeug erscheint damit ohne weiteren Handgriff im Dialog.
7. **Nur der Inhaber** sieht und ändert (`ApiAuth::owner`); keine neuen MCP-Werkzeuge, kein neues Recht.
8. **Anzeige der Werkzeuge** (Liste oder Tabelle, Gruppierung) merkt sich der Browser in `localStorage` unter `dispatch.anweisungen.werkzeuge`; Suche und Filter nicht.
9. **ADR:** `031-anweisungen-fuer-agenten-im-redaktionsmodus.md` (Entscheidungen 2–6) in Phase 1. Nummer beim Umsetzen neu geprüft (2026-11-13): 029 hat `2026-11-12_zeitversetzt-veroeffentlichen` belegt (Feed und Sitemap), 030 reserviert `2026-11-12_gestaltungs-bausteine` (Phase 8) — daher 031 statt der geplanten 029.

Verworfen: Markdown im Browser rendern (zweiter Umsetzer, der vom Server abweicht); eigene Werkzeugliste im Frontend (veraltet beim ersten neuen Werkzeug); Server-Anweisung und Workflows editierbar machen (die Texte sind Teil des Codes und laufen mit Deploy und Versionsverwaltung); Protokoll der Werkzeugaufrufe (vom Inhaber abgewählt).

## Overview

| Phase | Inhalt | Rating | Status |
|---|---|---|---|
| 1 | [Fachlogik und Markdown](phase-1-fachlogik-markdown.md): `Util\Markdown` um Code und Tabellen, Konfliktprüfung und Fassungen in `DocumentService`/`DocumentRepository`, Bereiche in der Registry, `McpController::registry` öffentlich, ADR-031 | heikel | complete |
| 2 | [API](phase-2-api.md): `Api\InstructionController` mit sechs Routen, Routen in `app/index.php`, `docs/routes.md` | standard | complete |
| 3 | [Oberfläche nach Entwurf](phase-3-oberflaeche.md): `redaktion/anweisungen.js`, Menüeintrag, Symbole `lupe`/`zurueck` in `ui.js`, CSS, Glossar und Code-Map | standard | pending |

Reihenfolge zwingend 1 → 2 → 3. Modell je Phase: 1 auf dem großen Modell (Sperre und Parser), 2 und 3 auf Sonnet.

## Kontrakt

### PHP-Schnittstellen

- `Util\Markdown::toHtml(string $markdown): string` — unverändert in der Signatur; versteht zusätzlich `` `Code` ``, Code-Blöcke zwischen ```-Zeilen (Sprachangabe nach ``` wird ignoriert) und Tabellen aus `|`-Zeilen mit Trennzeile `|---|`.
- `Domain\DocumentRepository::lockRevision(string $key): ?int` — `SELECT revision FROM documents WHERE doc_key = ? FOR UPDATE`.
- `Domain\DocumentRepository::revision(string $key, int $revision): ?array` — eine Zeile aus `document_revisions` (`revision`, `content`, `note`, `actor`, `created_at`).
- `Domain\DocumentRepository::revisionList(string $key): array` — wie `revisions`, aber ohne `content`.
- `Domain\DocumentService::update(string $key, string $content, ?string $title, ?string $note, Actor $actor, ?int $baseRevision = null): array` — neuer letzter Parameter; `null` = keine Prüfung (MCP, Vorschläge).
- `Domain\DocumentService::restore(string $key, int $revision, ?int $baseRevision, Actor $actor): array` — Rückgabe wie `update`; `null` = ohne Prüfung („trotzdem“).
- `Domain\DocumentService::history(string $key): array` und `revisionWithDiff(string $key, int $revision): array` (Form unten).
- `Mcp\Registry::group(string $name): void`, `Mcp\Registry::all(): list<Tool>`, `Mcp\Registry::groupOf(string $toolName): string`.
- `Mcp\McpController::registry(array $services): Registry` — `public static`.
- `Mcp\Prompts::catalog(): list<array{name, title, description, arguments, text}>` — Liste wie `list()` plus der Text, den `get()` liefern würde (für `thema_einreichen` mit Platzhalter `‹Thema›`).

### Routen (alle JWT des Inhabers, Fehler `{"error","message","details"?}` über `ApiErrors`)

| Methode | Pfad | Body | Antwort |
|---|---|---|---|
| GET | `/dispatch/api/instructions` | — | Gesamtansicht (unten) |
| POST | `/dispatch/api/instructions/preview` | `{"content": "…"}` | `{"html": "…"}` |
| PATCH | `/dispatch/api/instructions/{slug}` | `{"content", "note"?, "base_revision", "force"?}` | `{"document": Dokument}`; `409 revision_conflict` mit `details: {current_revision, actor, created_at, note}` |
| GET | `/dispatch/api/instructions/{slug}/revisions` | — | `{"revisions": [{revision, created_at, actor, note}]}` neueste zuerst |
| GET | `/dispatch/api/instructions/{slug}/revisions/{id}` | — | `{"revision", "created_at", "actor", "note", "html", "diff": {"html", "blocks_changed"} \| null}` — `{id}` ist die Fassungsnummer; `diff` gegen die Fassung davor, `null` bei der ersten |
| POST | `/dispatch/api/instructions/{slug}/restore` | `{"revision", "base_revision", "force"?}` | wie `PATCH` |

`{slug}` nur `editorial-guidelines` (Dokument `editorial_guidelines`) oder `preferences`, sonst `404 not_found`. Grund: Der Router kennt nur die Platzhalter `{id}` (Ziffern) und `{slug}` (`a–z`, `0–9`, Bindestrich), ein Unterstrich passt nicht (`app/src/Http/Router.php`). `PATCH` statt `PUT`, weil `PATCH` auf Strato schon läuft (`categories/{id}`). Zeiten UTC `Y-m-d H:i:s`.

Gesamtansicht:

```json
{
  "documents": [
    {"key": "editorial_guidelines", "slug": "editorial-guidelines", "title": "Redaktionsrichtlinien", "revision": 3, "updated_at": "2026-11-12 12:33:09",
     "actor": "oauth:Claude Code", "note": "…", "content": "…Markdown…", "html": "…"}
  ],
  "tools": [
    {"name": "create_post", "title": "Entwurf anlegen", "description": "…", "inputSchema": {}, "annotations": {}, "scope": "blog:write", "group": "Artikel"}
  ],
  "prompts": [{"name": "redaktion_geplant", "title": "…", "description": "…", "arguments": [], "html": "…"}],
  "texts": [
    {"key": "instructions", "title": "Server-Anweisung", "when": "beim Verbinden, an jeden Agenten", "source": "app/mcp/instructions.md", "html": "…"}
  ],
  "form_rules": {"min_words": 600, "min_sources": 3, "min_words_deep": 1200, "min_sources_deep": 5, "require_cover": true, "require_image_license": true}
}
```

`documents` in der Reihenfolge `editorial_guidelines`, `preferences`; `actor`/`note` aus der neuesten Zeile von `document_revisions` (gibt es keine, `actor` `null`). `tools` in Registry-Reihenfolge. `texts` genau diese fünf, in dieser Reihenfolge: `instructions`, `workflow-scheduled`, `workflow-queue`, `workflow-returned`, `style-guide` (Titel und „wann“ in Phase 2).

## Finale Abnahmekriterien

- AK1: Im Kontomenü öffnet „Anweisungen für Agenten“ den Dialog; er zeigt alles, was ein Agent bekommt: beide Dokumente in der gültigen Fassung, Server-Anweisung, drei Prompts mit Text, drei Workflows, Style-Guide und alle Werkzeuge der Registry mit Beschreibung, Angaben (Name, Art, Pflicht, Beschreibung, erlaubte Werte) und Hinweisen.
- AK2: Ein neues Werkzeug in einer `*Tools::register` erscheint ohne weitere Änderung im Reiter „Werkzeuge“ unter seinem Bereich.
- AK3: Der Inhaber bearbeitet Richtlinien und Präferenzen im Dialog; Speichern legt eine Fassung an, die ein Agent beim nächsten `get_editorial_brief` bekommt.
- AK4: Hat ein Agent zwischen Öffnen und Speichern eine Fassung angelegt, speichert der Dialog nicht still darüber, sondern meldet es; „Meine Fassung trotzdem speichern“ legt die nächste Fassung an, die des Agenten bleibt im Verlauf. Zwei gleichzeitige Speichervorgänge mit derselben Basis erzeugen genau eine Fassung und einen Konflikt.
- AK5: Der Verlauf zeigt jede Fassung mit Zeit, Akteur und Notiz, den Vergleich zur Vorgängerfassung und kann eine Fassung wiederherstellen (als neue Fassung).
- AK6: Agenten speichern per MCP `update_document` unverändert ohne Konfliktprüfung; die öffentlichen Dokumentseiten sehen für Markdown ohne Code und Tabellen gleich aus wie vorher.
- AK7: Die Oberfläche entspricht dem Entwurf (Struktur-Punkte in Phase 3); ein Erstnutzer findet ohne Anleitung, was ein Agent bekommt, und kann bearbeiten, vergleichen und wiederherstellen; jede nicht triviale Wahl hat ein i-Symbol.
- AK8: `php -l` auf alle geänderten PHP-Dateien fehlerfrei (ADR-004: keine automatisierten Tests); `docs/routes.md`, `docs/code-map.md`, `docs/glossary.md`, ADR-031 nachgezogen; `app/theme/style.css` unverändert (Budget 48 000 Byte, nur 8 Byte Luft).

## Konfidenz-Ausweis

- 🟡 Konfliktprüfung unter Last: Die Sperre greift nur, wenn `lockRevision` innerhalb der Transaktion von `update` läuft. `Transaction::join` öffnet eine, wenn keine offen ist (Datei `app/src/Db/Transaction.php`). Check in Phase 1: zwei `php -r`-Aufrufe parallel mit derselben `base_revision` (Befehl steht in der Phase) — genau einer muss `revision_conflict` werfen, `document_revisions` darf nur eine neue Zeile haben.
- 🟡 Markdown-Erweiterung und öffentliche Seiten: `Builder` rendert Über, Impressum und Datenschutz mit `Markdown::toHtml`. Enthalten sie heute Backticks oder `|`-Zeilen, sähen sie nach Phase 1 anders aus. Check in Phase 1: die drei Dokumente vor und nach der Änderung lokal rendern und die HTML-Ausgaben vergleichen (Befehl steht in der Phase).
- 🟡 Größe der Gesamtansicht: 33 Werkzeuge mit Schemas plus fünf Texte und zwei Dokumente ergeben rund 90 KB JSON (gemessen am Entwurf: `daten.js` 88 KB). Für einen Dialog, der einmal lädt, unkritisch; keine Paginierung geplant.

Fehlerklassen geprüft: Einschlägig ist `frameworks/css-architecture.md` → „Freiraum per Innenabstand reserviert, Liste quillt aus ihrem Feld“ (der Dialog hat eine feste Höhe, der Rumpf rollt) — Prüfpunkt in Phase 3. `systeme/strato-shared-hosting.md`, `frameworks/a11y.md`, `frameworks/frontend-architecture.md` und `frameworks/ui-gestaltung.md` haben keinen Abschnitt „Fehlerklassen“; Entities für PHP, MySQL und JavaScript fehlen. Aus diesem Projekt einschlägig: Budget von `style.css` (alles Neue nur in `redaktion.css`).

## Summary

## Files touched

## Commits

## Deviations from plan

## Follow-ups
