# 018 — MCP-Werkzeuge und Agentenrechte

**Status:** Akzeptiert (2026-11-10)

## Kontext

Das Fachkonzept nennt für Agenten ein Werkzeug `complete_queue_item` und lässt sie Artikel nur anlegen, ändern und einreichen. Beim Zuschnitt von Meilenstein 3 zeigte sich: Ein Auftrag ist mit der Freigabe erledigt (`PostService::approve` → `done`), und Agenten brauchen einen Weg, eingereichte Arbeit zurückzunehmen und eigene Entwürfe zu entsorgen.

## Entscheidung

- **`complete_queue_item` entfällt.** Der Auftrag wird `done`, wenn der Inhaber den Artikel freigibt.
- **Agenten dürfen zurückziehen (`withdraw`):** `review` → `draft` (nie veröffentlicht) bzw. → `published` (veröffentlichter Artikel mit neuem Arbeitsstand; der Live-Stand bleibt). Der verknüpfte Auftrag geht von `review` auf `claimed` zurück, mit frischer Frist (`queue_claim_minutes`); `claimed_by` bleibt. Audit `post.withdraw`.
- **Agenten dürfen reine Entwürfe löschen (`discard`):** nur `status = draft` und `published_revision IS NULL`. Eingereichtes → `forbidden` mit dem Hinweis, erst zurückzuziehen. Veröffentlichtes bleibt `invalid_transition`. Der Inhaber darf wie bisher jeden nie veröffentlichten Artikel verwerfen. Der Auftrag wird wieder `open`.
- **Frühere Fassung wiederherstellen:** `restore_post_revision(id, revision)` mit der laufenden Revisionsnummer des Artikels (nicht der Revisions-ID). Es entsteht eine neue Revision mit dem Inhalt der gewählten; der Slug bleibt. Audit `post.restore` mit `from_revision`.
- **Vorschau-Link und signierte Medien-Links** gibt es erst mit Meilenstein 4.
- **Bewusst kein Werkzeug:** Freigeben, Zurückgeben, Veröffentlichte zurückziehen, Vorschläge übernehmen, Tokens anlegen, Formvorgaben ändern, Reaktionen setzen (Critical Rule 5).

## Konsequenzen

- Ein Agent kann nie etwas Öffentliches entfernen oder verändern: Löschen trifft nur Nie-Veröffentlichtes, Zurückziehen lässt den Live-Stand stehen.
- Nach `withdraw` darf derselbe Agent den Artikel weiter bearbeiten und erneut einreichen; ein anderer Agent kann den Auftrag erst nach Ablauf der Frist neu reservieren.
