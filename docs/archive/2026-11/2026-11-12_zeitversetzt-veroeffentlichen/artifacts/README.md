# Entwurf: Zeitversetztes Veröffentlichen

Klickdummy, vom Inhaber am 2026-11-12 freigegeben; verbindlich für Phase 4.

| Datei | Inhalt |
|---|---|
| `entwurf-veroeffentlichungsplan.html` | Seite mit Entwurfsleiste oben: „Ansicht“ wählt den Bildschirm, „Daten“ den Zustand (Beispiel, leer, 30 Artikel, lädt, Ladefehler), „Artikel“ neu oder neue Fassung, „Thema“ hell/dunkel. Die Zeile „Wofür“ nennt die Aufgabe des Bildschirms. |
| `_entwurf-veroeffentlichungsplan/dummy.js` | Aufbau aller Elemente, Texte und Zustände; Vorlage für `app/theme/redaktion/schedule.js`. Die Plan-Rechnung darin ist nur Nachbau — in der Umsetzung kommt sie vom Server (`GET /dispatch/api/schedule`). |
| `_entwurf-veroeffentlichungsplan/dummy.css` | Teil 1 = neue Stile für `redaktion.css` (nur Theme-Variablen); Teil 2 = nur die Entwurfsleiste, nicht übernehmen. |

Bildschirme → Phase 4: Startseite mit „Geplant“ (P4-AK3), Menü (P4-AK4), Vorschau mit „Freigeben …“ und Freigeben-Dialog (P4-AK1, P4-AK2), Vorschau eines geplanten Artikels (P4-AK6), Dialog „Veröffentlichungsplan“ inkl. aufgeklappter Zeile (P4-AK5).

**Ansehen:** Die Seite bindet das echte Theme relativ ein (`../../../../app/theme/style.css`, `…/redaktion/redaktion.css`) — direkt aus dem Repo im Browser öffnen, kein Server nötig. Die Werte (Farben, Schrift, Radien) kommen aus diesem Theme.

**Platzhalter:** Beispielzeit Do, 12.11.2026, 10:30; Titel aus den Demo-Artikeln (`bin/demo/demo.php`), alle Termine erfunden; Artikeltext und Aufmacher sind Platzhalter („unverändert“). Knöpfe ohne neue Funktion (Kommentieren, Zurück an Agent, Interessen …) melden nur „im Entwurf nicht belegt“.

Eine Kopie mit eingebettetem Theme liegt zusätzlich im Artefakte-Ordner des Vorhabens (Agenten-Verwalter); sie ist nicht die Quelle.
