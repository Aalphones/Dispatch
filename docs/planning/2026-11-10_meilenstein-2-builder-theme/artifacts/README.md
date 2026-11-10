# Entwurf — Website und Redaktionsmodus

**Quelle:** [`docs/design/entwurf-website.html`](../../../design/entwurf-website.html) — eine Datei, im Browser öffnen (offline lauffähig bis auf die Platzhalterbilder). Sie liegt unter `docs/design/` statt hier, weil sie auch Kontrakt für Meilenstein 4 ist und das Archivieren dieses Plans überdauern muss (ADR-013). Abgestimmt mit dem Inhaber am 2026-11-10 in fünf Runden.

## Bedienung

Das dunkle Pult unten links gehört **nicht** zur Website. Es schaltet zwischen den Ansichten (Startseite, Artikel, Kategorien, Schlagwort-Filter, Vorschau), zeigt Handy (390 × 844) und Tablet (820 × 1180) als Rahmen, wechselt hell/dunkel und die Akzentfarbe und simuliert die Anmeldung („Als Inhaber").

## Was welche Phase nutzt

| Ansicht | Meilenstein 2 | Meilenstein 4 |
|---|---|---|
| Kopf, Fuß, Suche mit Vorschlägen | Phase 2 (Markup), 4 (CSS), 6 (Verhalten) | Konto-Knopf, Menü |
| Startseite | Phase 5 | „Wartet auf dich", Mehr/Weniger an Karten |
| Artikel | Phasen 3–6 (inklusive Fokusmodus) | Redaktionskasten, Mehr/Weniger |
| Alle Kategorien | Phase 5 | Interesse an den Karten |
| Schlagwort-Filter | Phase 5 (statisch), 6 (additiv) | — |
| Vorschau mit Freigabe und Kommentieren | — | komplett |
| Dialoge (Anmelden, Thema einreichen, Interessen, Statistik, Zugänge, Zurückziehen), schwebender Knopf, Hinweise | — | komplett |

## Platzhalter

- Alle Fotos kommen von Unsplash (`images.unsplash.com`) und dienen nur der Anschauung; die Website liefert ausschließlich eigene Medien aus `media/` (CSP `img-src 'self'`).
- Artikeltexte, Zahlen, Namen und Statistikwerte sind erfunden.
- Das Skript im Entwurf ist Demo-Code (Daten im Skript, `innerHTML`), keine Vorlage für die Umsetzung — Phase 6 legt fest, wie es richtig geht.

## Design-System

Werte (Farben, Schriften, Abstände, Bewegungsdauern) stehen als CSS-Variablen im `:root` des Entwurfs; sie werden 1:1 in `app/theme/style.css` übernommen (Phase 4).

## Neu erzeugen

Nichts zu erzeugen — die Datei ist zugleich Quelle und Ansicht. Änderungen am Entwurf nur nach Abstimmung mit dem Inhaber, dann ADR-013 prüfen.
