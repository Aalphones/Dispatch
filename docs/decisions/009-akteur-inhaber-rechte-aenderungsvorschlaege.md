# 009 — Akteur, Inhaber-Rechte und Änderungsvorschläge

**Status:** Akzeptiert (2026-11-09)

## Kontext

Agenten ändern Inhalte per MCP, der Inhaber über die Redaktions-API. Öffentlich Sichtbares darf nur der Inhaber ändern; alles andere sollen Agenten direkt anpassen können. Die Regeln müssen in der Domänenschicht liegen, damit MCP, API und CLI sie nicht einzeln nachbauen.

## Entscheidung

- **Akteur:** Jede schreibende Service-Methode nimmt als letzten Parameter einen `Actor` (`owner`, `cli`, `deploy`, `mcp:<name>`, `oauth:<name>`). Nur `owner` gilt als Inhaber. Das Protokoll (`audit_log`) trägt den Namen.
- **Was öffentlich ist** — Änderung durch Nicht-Inhaber wird ein Änderungsvorschlag, durch den Inhaber wirkt sie direkt:

  | Domäne | Öffentlich | Intern (jeder Akteur direkt) |
  |---|---|---|
  | Einstellungen | `site_title`, `site_tagline`, `site_description`, `author_name`, `author_url`, `accent`, `ai_disclosure`, `ai_disclosure_text` | `language`, `locale`, `posts_per_page`, `queue_claim_minutes` |
  | Dokumente | `about`, `imprint`, `privacy` | `editorial_guidelines`, `preferences` |
  | Kategorien | Anlegen; `slug`, `name`, `description`, `parent_id`, `position`, `active` | `interest`, `keywords`, `avoid` |

- **Formvorgaben nie per Service:** `form_rules`, `publish_mode`, `stats_enabled` stehen in `config.php`; ein Änderungsversuch wirft `not_changeable`.
- **Gemischte Updates:** interne Felder sofort, öffentliche Felder als **ein** Vorschlag. Rückgabe: `applied` (Feldliste) und `change_id`.
- **Übernahme über die Fach-Services:** `ChangeService::apply` ruft `applyChange` des zuständigen Services mit dem Inhaber als Akteur auf — dieselbe Prüfung wie bei direkter Änderung. Alles läuft in **einer** Transaktion; schlägt die Prüfung fehl, bleibt der Vorschlag `pending` und nichts ändert sich. Der Vorschlag wird dabei gesperrt (`FOR UPDATE`), ein zweites gleichzeitiges Übernehmen scheitert mit `not_pending`.
- **`Transaction::join`:** Services starten ihre Transaktion mit `join` statt `run`. Läuft schon eine, arbeiten sie darin mit (PDO kennt keine verschachtelten Transaktionen); sonst öffnen sie eine eigene.
- **Vorschlags-Schlüssel:** Einstellungen `settings` (Payload: Name → Wert), Dokumente der `doc_key`, Kategorien die ID oder `new`. Medien folgen mit Phase 4.
- **Startinhalte:** `Seeder` legt nur fehlende Einstellungen und Dokumente an (Markdown aus `app/defaults/`, erste Zeile `# Titel`), nie Vorhandenes überschreiben. Ein Startdokument bekommt Revision 1 mit Vermerk „Startinhalt“.

## Konsequenzen

- MCP-Tools und Redaktions-API (spätere Meilensteine) brauchen keine eigene Rechteprüfung für Öffentliches; sie reichen den Akteur durch.
- Eine neue öffentliche Einstellung, Seite oder Kategorie-Eigenschaft muss in die Tabelle oben und in `PUBLIC_*`/`INTERNAL_*` der Services — sonst wäre sie unbekannt (Einstellung, Kategorie-Feld) oder intern (Dokument).
- Der Rebuild der Website nach einer Übernahme kommt mit Meilenstein 2.
