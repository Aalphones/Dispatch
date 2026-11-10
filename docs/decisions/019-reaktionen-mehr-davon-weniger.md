# 019 — Reaktionen „Mehr davon / Weniger“

**Status:** Akzeptiert (2026-11-10)

## Kontext

Der Inhaber will den Agenten ohne Aufwand sagen, welche veröffentlichten Artikel er gut findet und welche nicht. Das Briefing soll das als Hinweis für künftige Themen enthalten.

## Entscheidung

- **Eine aktuelle Reaktion je Artikel** (`post_reactions`, Primärschlüssel `post_id`): `more` oder `less`, optional eine Notiz bis 500 Zeichen. Kein Verlauf; „keine Reaktion“ ist die gelöschte Zeile. Eine neue Reaktion überschreibt die alte.
- **Nur Inhaber oder CLI setzt sie** (`ReactionService::set` → sonst `forbidden`). Es gibt bewusst kein MCP-Tool dafür: Agenten sollen ihre eigene Bewertung nicht steuern können.
- **Das Briefing liest 90 Tage** (`ReactionService::recent`): je Reaktion Artikel, Kategorien (Hauptkategorie zuerst), Tags und Notiz.
- Bis Meilenstein 4 die Oberfläche bringt, läuft das Setzen über `php bin/dispatch reaction <slug> more|less|none [Notiz]`. Jedes Setzen erzeugt einen Audit-Eintrag `reaction.set`.

## Konsequenzen

- Löscht man einen Artikel, verschwindet seine Reaktion (Fremdschlüssel mit `CASCADE`).
- Ältere Reaktionen bleiben gespeichert, erscheinen aber nach 90 Tagen nicht mehr im Briefing.
