# 012 — Artikel-Statusmaschine und Live-Lesepfad

**Status:** Akzeptiert (2026-11-10)

## Kontext

Die Regel „nichts ohne Freigabe online" muss im Code gelten, nicht nur in der Absicht: Agenten arbeiten am Arbeitsstand weiter, während die Website einen älteren, freigegebenen Stand zeigt. Jeder Übergang, der etwas veröffentlicht oder zurückzieht, hängt am Inhaber (Critical Rule 2 in `conventions/php.md`).

## Entscheidung

| Aktion | Wer | Von | Nach | Wirkung |
|---|---|---|---|---|
| `submit` | jeder | `draft`; `published` mit `revision > published_revision` | `review` | Formprüfung; Fehler → `form_errors`, Status bleibt |
| `approve` | Inhaber | `review` | `published` | Formprüfung erneut; `published_revision = revision`; `published_at` nur beim ersten Mal; `review_note` leer |
| `returnToAgent` | Inhaber | `review` | `draft` | `review_note` gesetzt; der Live-Stand bleibt |
| `unpublish` | Inhaber | `published`; `review` mit gesetztem `published_revision` | `archived` | `published_revision` bleibt |
| `discard` | Inhaber | jeder Status, nur wenn `published_revision IS NULL` | — | Artikel samt Revisionen, Quellen, Kategorien gelöscht |

Ergänzt durch ADR-028: `approve` kann einplanen (`scheduled`), das Erscheinen löst `release` aus.

Alles andere ist `invalid_transition`. Jede Aktion läuft in einer Transaktion auf der gesperrten Zeile (`SELECT … FOR UPDATE`), damit zwei Übergänge sich nicht überholen, und schreibt `post.<aktion>` ins Protokoll.

- **`published_revision` bleibt beim Zurückziehen stehen.** Sie belegt, welche Revision zuletzt online war, und sperrt `discard`: Was einmal öffentlich war, wird nicht still gelöscht, auch wenn es die Website nicht mehr zeigt (Adresse, Feeds und Suchmaschinen kennen es).
- **`PostRepository::livePosts()` ist die einzige Quelle für die Website.** Sie liest den gespeicherten Snapshot der freigegebenen Revision, nie den Arbeitsstand, und schließt `archived` aus. Auch `liveTitles()` (Dubletten-Warnung) liest dort. Kein anderer Weg darf Artikel für die Website liefern.
- **Formprüfung ist rein (`Util/FormCheck`):** Snapshot, Medien, fremde Live-Titel und Formvorgaben rein, Ergebnis mit Fehlern (blockieren `submit` und `approve`) und Warnungen (nie). `PostService` sammelt die Eingaben.

## Verworfen

- **Live-Stand aus der Tabelle `posts` lesen:** der Arbeitsstand dort ändert sich mit jeder Revision; die Website würde Ungeprüftes zeigen.
- **`published_revision` beim Zurückziehen leeren:** `discard` wäre danach wieder erlaubt, der Nachweis weg.

## Konsequenzen

- Ein Rebuild nach `approve`/`unpublish` kommt mit Meilenstein 2.
- Ein Artikel, der nach einer Rückgabe wieder `draft` ist, aber eine Live-Revision hat, lässt sich nicht direkt zurückziehen: erst `submit`, dann `unpublish` (zulässig aus `review`).
- Abnahme ist Handprüfung (ADR-004): jede Formregel einzeln, alle Übergänge, Rechte je Akteur.
