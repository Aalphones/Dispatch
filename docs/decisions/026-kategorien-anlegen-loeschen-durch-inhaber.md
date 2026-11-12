# 026 — Kategorien anlegen und löschen nur durch den Inhaber

**Status:** Akzeptiert (2026-11-12) · ergänzt ADR-009

## Kontext

Der Inhaber konnte Kategorien weder löschen noch in der Oberfläche anlegen; die Abnahme-Kategorie „Testlauf“ ließ sich nicht entfernen. Agenten dürfen Öffentliches nur vorschlagen (ADR-009).

## Entscheidung

- **Nur der Inhaber** löscht und legt an, im Dialog „Deine Interessen“ (`DELETE`/`POST /dispatch/api/categories`, CLI `category:delete`). Per MCP gibt es kein Löschen und keinen Lösch-Vorschlag.
- **Mit Artikeln** nur mit Ersatz-Kategorie (`move_to`), sonst 409 `category_in_use`. Artikel nur dieser Kategorie bekommen die Ersatz-Kategorie als Hauptkategorie; bei weiteren Kategorien entfällt nur die Zuordnung, und die alphabetisch erste übrige wird Hauptkategorie. Alles in einer Transaktion.
- **Unterkategorien** blockieren das Löschen (409 `has_children`).
- **Freigegebene Stände** bleiben unangetastet; der Builder überspringt unbekannte Slugs.
- **Umbenennen** ändert nur den Namen; der Slug bleibt, damit Links weiter gehen. Der Slug neuer Kategorien entsteht aus dem Namen.

## Verworfen

- **Lösch-Vorschlag für Agenten**: mehr Oberfläche ohne Bedarf, der Inhaber räumt allein auf.

## Konsequenzen

- Löschen ist endgültig. Der Neubau nach der Aktion holt die Website nach; schlägt er fehl, nennt der Dialog es.
