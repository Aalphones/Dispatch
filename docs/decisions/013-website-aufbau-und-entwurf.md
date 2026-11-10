# 013 — Aufbau der öffentlichen Website und verbindlicher Entwurf

**Status:** Akzeptiert (2026-11-10)

## Kontext

Meilenstein 2 legt die öffentlichen Adressen fest; sie lassen sich danach nur mit Weiterleitungen ändern. Das Konzept beschreibt Seitentypen und Gestaltung in Worten, ein Entwurf fehlte. Der Inhaber hat in fünf Runden einen Entwurf abgestimmt (`docs/design/entwurf-website.html`), der in mehreren Punkten vom Konzept abweicht.

## Entscheidung

- **Sprache:** Pfade und Oberfläche deutsch (`/artikel/`, `/kategorie/`, `/kategorien/`, `/tag/`, `/suche/`, `/seite/{n}/`, `/ueber/`, `/impressum/`, `/datenschutz/`). Name und Unterzeile bleiben englisch.
- **Kein Archiv:** `/archiv/` und `/schlagwoerter/` entfallen. Ältere Artikel erreicht man über Seitenzahlen, Kategorien, Schlagwörter und Suche.
- **Seitenzahlen statt Endlos-Scrollen** auf Startseite, Kategorie- und Schlagwortseiten, damit der Fuß erreichbar bleibt.
- **Schlagwort-Seiten sind Schnellfilter:** `/tag/{slug}/` ist statisch (funktioniert ohne JS); weitere Schlagwörter kombiniert die Suche additiv über `/suche/?tag=a&tag=b`.
- **Kategorie-Bild** ist das Titelbild des neuesten Live-Artikels der Kategorie (inklusive Unterkategorien); ohne Artikel ein Farbverlauf in der Akzentfarbe. Keine neue Spalte.
- **„Ausgabe Nr. n"** im Kopf ist die Zahl der Live-Artikel.
- **Hüllen für Vorschau und Zustimmung** baut nicht Meilenstein 2, sondern der Meilenstein, der sie braucht (Vorschau: 4, Zustimmung: 5).
- **Login-Einstieg:** Knopf „Anmelden" oben rechts im Kopf plus `/#anmelden` (Meilenstein 4). Meilenstein 2 lässt im Kopf Platz dafür.
- **Redaktion in die Seite eingebaut** statt eigener Leiste: Konto-Menü, „Wartet auf dich", Redaktionskasten am Artikel, Interesse an den Kategorie-Karten, Freigabe-Leiste mit Kommentieren in der Vorschau, „Mehr davon / Weniger" (Meilenstein 4). Der Entwurf ist dafür der Maßstab.

## Verworfen

- **Englische Pfade:** passen nicht zu deutschen Artikeln und zur Einstellung `language = de`.
- **Endlos-Scrollen:** im Entwurf probiert, der Fuß war nicht mehr erreichbar.
- **Eigene Bildspalte für Kategorien:** mehr Pflege, und das neueste Titelbild ist ohnehin passend.

## Konsequenzen

- `docs/design/entwurf-website.html` ist Kontrakt für Meilenstein 2 (öffentliche Seiten) und Meilenstein 4 (Redaktion). Bilder darin sind Unsplash-Platzhalter, das Entwurfs-Pult unten links gehört nicht zur Website.
- Meilenstein 4 wächst um: Interessen-Dialog, „Mehr davon / Weniger", Anmerkungen an Stellen in der Vorschau. Meilenstein 3 nimmt die Reaktionen ins Briefing.
