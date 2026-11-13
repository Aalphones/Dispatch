# Frontend Conventions — DISPATCH

> **Source-of-truth references:**
> - Fachkonzept: [`../design/konzept-blog-engine.md`](../design/konzept-blog-engine.md) → „Statisches Frontend und SEO", „Redaktionsmodus im Frontend"
> - [WCAG 2.2](https://www.w3.org/TR/WCAG22/)

## Stack

| Layer | Choice |
|---|---|
| Markup | statisches HTML aus PHP-Templates (`app/templates/`) |
| Styling | ein `style.css`, ohne Präprozessor, Systemschriften |
| Script | Vanilla-JS als ES-Module, kein Build-Schritt |
| Redaktionsmodus | `theme/redaktion/*.js` + `redaktion.css`, per `import()` erst nach Login |
| Tests | keine (ADR-004) |

## Library Policy

**Forbidden:**
- JS- und CSS-Bibliotheken, Webfonts, alles von fremden Servern — die Website lädt für Besucher nichts von Dritten.
- Inline-Skripte und Event-Attribute (`onclick=…`) — die CSP erlaubt `script-src 'self'` ohne `unsafe-inline`.

## Regeln

- Budgets (ADR-015, 023, 032): `style.css` ≤ 50 000 Byte, `site.js` ≤ 5 000, `suche.js` ≤ 9 500, `artikel.js` ≤ 13 000, `zaehler.js` ≤ 2 500, `vorschau.js` ≤ 3 000, `bausteine.js` ≤ 8 000, `bausteine.css` ≤ 16 000, `diagramme.js` ≤ 13 500, `diagramme.css` ≤ 9 000 (ADR-030). `bausteine.*` laden nur bei Artikeln mit Gestaltungs-Bausteinen (Stylesheet per Seitenkopf, Skript per `artikel.js` bei bedienbaren Bausteinen); `diagramme.css` nur bei Artikeln mit Diagrammen, `diagramme.js` nur bei `.diagramm[data-interaktiv]` (Tabelle `COMPONENTS` in `loadComponents()`). Redaktionsmodule: kein Budget.
- Kein `style="…"` in von Modulen erzeugtem HTML (`el()` in `redaktion/ui.js` ignoriert es); Maße und Farben über `element.style.…`.
- Gestaltung: `docs/design/entwurf-website.html` ist verbindlich (ADR-013).
- Jede Ausgabe in Templates wird escaped (Helfer `e()`); nur Artikel-HTML aus dem Sanitizer geht roh hinaus.
- Ohne JavaScript funktioniert alles außer Suche, Videos und Login.
- Dark Mode über `prefers-color-scheme`, `prefers-reduced-motion` beachten, Druck-Stylesheet mit ausgeschriebenen Quell-URLs.
- Sprunglink zum Inhalt, sichtbare Fokusrahmen, Kontrast mindestens WCAG AA, jedes Bild mit Alt-Text, genau eine `h1` pro Seite.
- Bilder als `<picture>` mit WebP und JPEG, feste `width`/`height`, `loading="lazy"` außer beim Titelbild.
- Das Access-JWT liegt nur im Arbeitsspeicher, nie in `localStorage`; dort steht höchstens das Merkzeichen `dispatch.angemeldet`.
- Alle Pfade zur API sind absolut mit Präfix `/dispatch/api/` (ADR-001).

## Critical Rules

1. **Besucher lösen außer dem Zähl-Aufruf keinen PHP-Aufruf aus** — Ausnahme: `/feed.xml` und `/sitemap.xml` laufen durch die Brücke ([ADR-029](../decisions/029-feed-und-sitemap-ueber-die-bruecke.md)); sonst ist die statische Auslieferung und ihr Tempo dahin.
2. **Kein Inline-Skript** — die CSP blockiert es, und eine Ausnahme dafür würde den Redaktionsmodus angreifbar machen.
