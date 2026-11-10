# 015 — Theme: Budgets und Skriptmodule

**Status:** Akzeptiert (2026-11-10)

## Kontext

Das Konzept begrenzt CSS auf 30 KB und das öffentliche JS auf 5 KB in einer Datei. Der abgestimmte Entwurf (ADR-013) bringt Inline-Suche mit Vorschlägen, mitlaufendes Inhaltsverzeichnis, Fokusmodus, Lightbox, Zwei-Klick-Videos, Handy-Ansichten und Bewegungen; das passt nicht in diese Grenzen. Ein Build-Schritt bleibt ausgeschlossen.

## Entscheidung

- **Drei Module statt einer Datei**, alle als ES-Module ohne Bibliothek:
  - `site.js` (≤ 5 000 Byte) auf jeder Seite: Tastenkürzel Strg/⌘+K und `/`, lädt `suche.js` beim ersten Fokus des Suchfelds, setzt die Höhe der Kategorie-Leiste als CSS-Variable. Ab Meilenstein 4 zusätzlich Login-Auslöser.
  - `suche.js` (≤ 9 000 Byte), nachgeladen: Suchindex holen, Vorschläge im Kopf, Seite `/suche/` mit additiven Filtern.
  - `artikel.js` (≤ 7 000 Byte), nur auf Artikelseiten: Inhaltsverzeichnis-Marker, Fokusmodus, Lightbox, Zwei-Klick-Videos, Ausblenden der Navigation neben randlosen Bildern.
- **CSS:** eine `style.css` ≤ 48 000 Byte unkomprimiert. Redaktions-CSS kommt in Meilenstein 4 als eigene, nachgeladene Datei.
- **Bewegung als System:** drei Dauern und zwei Kurven als CSS-Variablen; `prefers-reduced-motion` schaltet alles ab. Seitenwechsel per CSS `@view-transition`, Lesefortschritt per scroll-gebundener Animation — beides ohne Skript und dort, wo der Browser es nicht kann, einfach ohne Effekt.
- **Ohne JS** funktionieren alle Seiten; es fehlen nur Suche, Vorschläge, Videos, Fokusmodus und Lightbox.

## Verworfen

- **Ein Bündel mit Build-Schritt:** widerspricht dem Stack (kein Node auf dem Server, kein Build).
- **Alles in `site.js`:** jeder Besucher lüde Such- und Artikel-Code mit.

## Konsequenzen

- `docs/conventions/frontend.md` nennt die neuen Budgets; Prüfung per Dateigröße beim Abschluss jeder Theme-Phase.
