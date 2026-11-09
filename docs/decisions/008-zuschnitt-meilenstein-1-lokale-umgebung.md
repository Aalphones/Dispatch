# 008 — Zuschnitt Meilenstein 1 und lokale Umgebung

**Status:** Akzeptiert (2026-11-09)

## Kontext

Meilenstein 1 baut die Domänenschicht ohne HTTP-Schnittstelle. Das Konzept nennt 20 Tabellen; nicht alle haben in diesem Meilenstein einen Verbraucher. Lokal lief PHP 8.3 ohne Datenbank, Strato liefert PHP 8.5.12 und MySQL 8.0.36 (`docs/knowledge/INDEX.md`). Der Sanitizer braucht `Dom\HTMLDocument` (ab PHP 8.4), die Medien brauchen GD mit WebP.

## Entscheidung

- **16 von 20 Tabellen.** `tokens` folgt mit Meilenstein 3 (ohne MCP gibt es keinen Verbraucher; ein Token per lokaler CLI läge nur in der lokalen Datenbank), `login_attempts` mit Meilenstein 4, `oauth_clients` und `oauth_codes` mit Meilenstein 5.
- **CLI nur lokal:** `bin/dispatch` kennt in diesem Meilenstein `hash-password`, `migrate`, `seed`. `build` folgt mit Meilenstein 2, `token:create` entfällt zugunsten von Meilenstein 3. Auf dem Server läuft nichts per Kommandozeile; Migrationen laufen dort nur über den Deploy-Endpunkt (ADR-002, ADR-007).
- **Lokal MySQL 8.4 LTS und PHP 8.5 statt MariaDB und PHP 8.3:** gleiche Familie und gleiche PHP-Reihe wie Strato. Datenbank `dispatch_dev`, Benutzer `dispatch` mit Rechten nur darauf, Zeichensatz `utf8mb4`.
- **Tabellenform:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`; Zeitspalten `DATETIME NOT NULL` ohne `DEFAULT`, geschrieben mit `UTC_TIMESTAMP()`; JSON-Spalten `JSON NOT NULL` ohne `DEFAULT` (MySQL 8.0 erlaubt dort nur Ausdrücke), der Code schreibt immer einen Wert (`[]` bzw. `{}`).
- **JSON nur speichern, nie im SQL abfragen:** keine `->`, `->>`, `JSON_EXTRACT`; Auswertung in PHP. Hält den Code unabhängig vom JSON-Dialekt der MySQL-Version.
- **Kein Fremdschlüssel von `posts.queue_item_id` auf `queue_items`:** `queue_items.post_id` zeigt bereits auf `posts`; ein zweiter wäre ein Kreis.

## Konsequenzen

- Spätere Meilensteine legen ihre Tabellen mit eigenen, höher nummerierten Migrationen an.
- Lokale und Strato-Version unterscheiden sich in der Minor-Reihe (8.4 gegen 8.0); der SQL-Dialekt bleibt deshalb auf das Gemeinsame beschränkt.
