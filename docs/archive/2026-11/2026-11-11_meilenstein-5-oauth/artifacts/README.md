# Entwürfe zu Meilenstein 5

| Datei | Zeigt | Phase |
|---|---|---|
| `entwurf-zustimmung.html` | Zustimmungsseite `/anmelden/zustimmung/` in allen Zuständen (Claude-App, Claude Code mit Warnhinweis, nicht angemeldet mit Login-Dialog, lädt, Anfrage abgelaufen, unbekannte App abgewiesen, Senden fehlgeschlagen, keine Rechte gewählt, Erlaubt, Abgelehnt) und den Dialog „Zugänge für Agenten“ mit der neuen Gruppe „Verbundene Apps“ (Liste mit Trennen-Nachfrage, leere Gruppe mit Anleitung) | 5 |

**Ansehen:** Datei im Browser öffnen; das Pult unten links wählt den Zustand und öffnet „Zugänge“. Direkt: `?stand=app|code|gast|laedt|abgelaufen|fremd|sendefehler`, `?zugaenge=1`, `?zugaenge=1&leer=1`, `?demo=ohne-rechte`, Farbschema `thema=hell|dunkel`. „Erlauben“ und „Ablehnen“ zeigen die Endzustände.

**Herkunft der Werte:** Der erste `<style>`-Block ist unverändert der des Website-Entwurfs `docs/design/entwurf-website.html` (über den Entwurf aus Meilenstein 4); nur der zweite Block „Ergänzungen für Zustimmung …“ ist neu und gehört nach `app/theme/redaktion/redaktion.css` (ohne den Teil „Entwurfs-Pult“). Kopf der Seite (Ausgabe-Zeile, Konto-Knopf, Name) wie auf allen Seiten der Website; die Kategorien-Leiste ist im Entwurf weggelassen und bleibt in der echten Seite, wie das Layout sie liefert.

**Platzhalter:** App-Namen, Ports, Datumsangaben, die fremde Adresse `connector.example.net` und die Restzeit sind Beispielwerte. Pult und Rand unten gehören nicht zur Website.

**Neu erzeugen:** Zusammengesetzt aus Kopf und Stil-Block von `docs/archive/2026-11/2026-11-11_meilenstein-4-redaktion/artifacts/entwurf-zugaenge.html` (Zeilen 1–660) und dem eigenen Teil. Änderungen direkt in der Datei vornehmen.
