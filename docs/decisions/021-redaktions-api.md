# 021 — Redaktions-API

**Status:** Akzeptiert (2026-11-11)

## Kontext

Der Redaktionsmodus (Meilenstein 4) gibt Artikel frei, gibt sie mit Anmerkung zurück, zieht sie zurück und übernimmt Änderungsvorschläge. Danach muss die Website neu gebaut sein, und der Inhaber (und über den Link aus `submit_post` auch ein Handy ohne Anmeldung) muss den Arbeitsstand so sehen, wie er erscheinen wird.

## Entscheidung

- **Router mit Platzhaltern** (`Http\Router`, `{id}`, `{slug}`); jeder Controller prüft die Anmeldung selbst: `ApiAuth::owner` (JWT) oder `ApiAuth::ownerOrDeploy`. Die Routen stehen in `docs/routes.md`.
- **Aktionen laufen über die Services** (`PostService`, `ChangeService`) mit `Actor::owner()`. Ein `DomainError` wird 409 mit dem Fehlercode (`invalid_transition`, `form_errors` mit `details`); unbekannte Eingaben 400 `invalid_value`, Unbekanntes 404 (`Api\ApiErrors`).
- **Neubau synchron im Request** (`Api\Rebuild`): nach `approve`, `unpublish` und `changes/{id}/apply`. Strato erlaubt 240 s, der lokale Bau braucht ≈ 3 s. Scheitert der Bau, bleibt die Aktion bestehen; die Antwort trägt `rebuilt: false` und `rebuild_error`. Die Wartezeit auf die Bau-Sperre beträgt dabei 10 s (das MCP-Tool wartet 30 s), damit eine belegte Sperre den Inhaber nicht an das PHP-Zeitlimit laufen lässt.
- **Die Vorschau rendert über dieselbe Methode wie der Builder:** `Builder::articleMain` übersetzt einen Artikel in die Variablen von `article.php`; der Bau und `Builder::previewMain` rufen sie. Die Vorschau liest den Arbeitsstand (`PostService::previewSnapshot`), ergänzt dessen Medien (`SiteData::withPreview`) und schreibt nie in die Wurzel. Ein Artikel, der nicht live ist, hat keinen Vorgänger und Nachfolger.
- **Signierte Vorschau-Links:** `PreviewLink::url` (`/vorschau/?id&exp&sig`, `sig` = HMAC-SHA-256 über `preview:<id>:<exp>` mit dem JWT-Schlüssel, sieben Tage). Die statische Seite `vorschau/index.html` (`Builder::collectPreview`) ist nur eine Hülle; ihr Skript holt `GET /dispatch/api/preview` mit denselben Parametern. Geprüft wird mit `hash_equals`; falsch oder abgelaufen → 403 `invalid_signature`.

## Änderungsansicht

`GET posts/{id}/diff` vergleicht den Arbeitsstand mit dem Snapshot der `published_revision`; ohne Live-Fassung antwortet er 409 `no_live_version`. `Util\Diff` zerlegt beide Fassungen in die direkten Kindelemente, bildet die längste gemeinsame Teilfolge über Tag und Klartext und ordnet entfernte und neue Blöcke zwischen zwei gleichen Blöcken paarweise zu, solange der Tag gleich ist. Ein geänderter Textblock wird wortweise mit `<del>`/`<ins>` neu aufgebaut (Auszeichnung darin entfällt), alles Übrige erscheint als ganzer Block in einem `div.diff-weg` bzw. `div.diff-neu`; über 400 Blöcken je Seite oder 2 000 Wörtern in einem Blockpaar wird dieser Teil ganz ersetzt. Das Ergebnis läuft über `Builder::previewMain` wie jede Vorschau.

Festlegungen der Umsetzung: Entfernte und neue Blöcke stecken in einem Wrapper-`div` statt die Klasse am Block zu tragen, weil `ContentRenderer` Abbildungen und Galerien ersetzt und deren Klasse sonst verlöre. Blöcke ohne Text (Abbildung, Galerie, Trennlinie) unterscheidet das Markup, nicht der Klartext. Listen, Tabellen, Abbildungen und `details` werden nie paarweise vermischt, weil ihr Klartext als Fließtext die Struktur zerstörte; sie erscheinen als weg/neu. `blocks_changed` zählt je Paar 1 und je ganz entfernten oder neuen Block 1. Quellen: Schlüssel URL, sonst Titel. Medien: UIDs aus Titelbild und `data-media`.

## Verworfen

- **Zeichengenaues Diff über das ganze HTML:** zerreißt Tags und liefert ungültiges Markup.
- **Neubau per Warteschlange:** Strato hat keinen Cron; die Freigabe müsste auf einen Aufruf warten, der nie kommt.
- **Vorschau als eigene Template-Kopie:** zwei Darstellungen driften auseinander, und der Inhaber sähe in der Vorschau etwas anderes als online.

## Konsequenzen

- Die Freigabe-Antwort dauert so lange wie der Bau; der Redaktionsmodus zeigt „Wird gebaut …" und bei `rebuilt: false` den Fehler samt „Neu bauen"-Hinweis.
- Wer den JWT-Schlüssel wechselt, entwertet alle ausgegebenen Vorschau-Links.
