# Entwurf: Anweisungen für Agenten

Klickdummy für den neuen Dialog im Redaktionsmodus; verbindlich für Phase 3, sobald der Inhaber den Plan freigibt.

| Datei | Inhalt |
|---|---|
| `entwurf-agenten-anweisungen.html` | Seite mit Entwurfsleiste oben: „Ansicht“ wählt den Bildschirm, „Zustand“ normal, lädt, Ladefehler, Konflikt beim Speichern („ein Agent war schneller“) oder keine Verbindung; „Thema“ hell/dunkel; „Werkzeug je Aktion zeigen“ blendet ein, welches MCP-Werkzeug ein Agent für dieselbe Aktion nimmt (nur Entwurfshilfe). Die Zeile „Wofür“ nennt die Aufgabe des Bildschirms. |
| `_entwurf-agenten-anweisungen/dummy.js` | Aufbau aller Elemente, Texte und Zustände; Vorlage für `app/theme/redaktion/anweisungen.js`. Markdown-Umsetzung und Absatzvergleich darin sind nur Nachbau — in der Umsetzung liefert der Server das HTML (`Util\Markdown`) und den Vergleich (`Util\Diff`). |
| `_entwurf-agenten-anweisungen/dummy.css` | Teil 1 = neue Stile für `redaktion.css` (nur Theme-Variablen); Teil 2 = nur die Entwurfsleiste, nicht übernehmen. |
| `_entwurf-agenten-anweisungen/daten.js` | Daten des Dummys (siehe „Platzhalter“). |

Bildschirme → Phase 3: Menü mit neuem Eintrag (P3-AK1), Reiter Übersicht (P3-AK2), Richtlinien und Präferenzen lesen (P3-AK3), bearbeiten mit Vorschau, Notiz, Konflikt und Offline-Meldung (P3-AK4), Verlauf mit Vergleich und Wiederherstellen (P3-AK5), Werkzeuge als Liste und Tabelle mit Suche, Filter, Gruppierung und Details (P3-AK6), Abläufe (P3-AK7).

**Ansehen:** Die Seite bindet das echte Theme relativ ein (`../../../../app/theme/style.css`, `…/redaktion/redaktion.css`) — direkt aus dem Repo im Browser öffnen, kein Server nötig. Farben, Schrift und Radien kommen aus diesem Theme. Für Bildschirmfotos nimmt die Adresse `?zustand=konflikt|offline|laedt|fehler`, `?thema=hell|dunkel`, `?darstellung=tabelle` und `#reiter/unteransicht` (z. B. `#werkzeuge/create_post`, `#richtlinien/verlauf`).

**Platzhalter:** Echt sind die 33 Werkzeuge mit Beschreibung, Angaben und Hinweisen, die drei Prompts, die Texte unter `app/mcp/` (Export aus der Registry, Stand 12.11.2026) und die aktuellen Fassungen beider Dokumente (Live-Stand: Richtlinien Fassung 3, Präferenzen Fassung 4). Beispiel sind Zeitpunkte und Akteure im Verlauf, die älteren Fassungen (teils nachgebaut; Präferenzen Fassung 2 fehlt) und der Konflikt mit „ChatGPT, Fassung 4“. Die Marke „Beispiel“ und die Entwurfsleiste gibt es im Produkt nicht.

**Neu erzeugen:** `daten.js` entstand aus einem Export der Registry (`McpController::registry` per Reflection, alle drei Rechte) und einem Live-Abzug der Dokumente über MCP. Nach Phase 2 liefert `GET /dispatch/api/instructions` dieselben Daten; ein neuer Abzug ist dann die Antwort dieser Route.

Eine Kopie mit eingebettetem Theme liegt zusätzlich im Artefakte-Ordner des Vorhabens (Agenten-Verwalter); sie ist nicht die Quelle.
