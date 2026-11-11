# Entwürfe zu Meilenstein 4

| Datei | Zeigt | Phase |
|---|---|---|
| `entwurf-zugaenge.html` | Dialog „Zugänge für Agenten“ mit Liste, Widerrufen-Nachfrage, „Überall abmelden“, leerer Liste und dem Ablauf „Neuer Zugang“ (Formular ohne Passwortfeld, Feldfehler, Ergebnis mit Kopieren) | 9 |

Alle übrigen Bildschirme des Redaktionsmodus stehen im verbindlichen Website-Entwurf `docs/design/entwurf-website.html` (ADR-013).

**Ansehen:** Datei im Browser öffnen, im Pult oben links „Dialog öffnen“. Direkt in einen Zustand: `?offen=1` (Liste), `#neu` (Formular), `?demo=fehler#neu` (Feldfehler), `?demo=ergebnis#ergebnis`, `?demo=nachfrage&offen=1`, `?leer=1&offen=1`; Farbschema mit `thema=hell|dunkel`. Ein schon vorhandener Name (z. B. „laptop“) zeigt die Meldung „gibt es schon“.

**Herkunft der Werte:** Farben, Schriften, Abstände, Dialog-, Knopf-, Feld-, Listen-, Info- und Toast-Stile sind der `<style>`-Block und die Symbole aus `docs/design/entwurf-website.html` (unverändert übernommen); nur der Block „Ergänzungen für Zugänge“ ist neu und gehört in Phase 9 nach `app/theme/redaktion/redaktion.css`.

**Platzhalter:** Zugangsnamen, Zeiten, Ablaufdaten und das Token sind Beispielwerte. Das Entwurfs-Pult und die Bühne gehören nicht zur Website.

**Neu erzeugen:** Die Datei ist aus zwei Teilen zusammengesetzt (Kopf und Symbole des Website-Entwurfs + eigener Teil). Wer sie ändert, ändert sie direkt; ändert sich der Website-Entwurf, den `<style>`-Block hier von Hand nachziehen.
