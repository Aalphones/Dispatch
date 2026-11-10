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

- Budgets (ADR-015): `style.css` ≤ 48 000 Byte, `site.js` ≤ 5 000, `suche.js` ≤ 9 000, `artikel.js` ≤ 7 000.
- Gestaltung: `docs/design/entwurf-website.html` ist verbindlich (ADR-013).
- Jede Ausgabe in Templates wird escaped (Helfer `e()`); nur Artikel-HTML aus dem Sanitizer geht roh hinaus.
- Ohne JavaScript funktioniert alles außer Suche, Videos und Login.
- Dark Mode über `prefers-color-scheme`, `prefers-reduced-motion` beachten, Druck-Stylesheet mit ausgeschriebenen Quell-URLs.
- Sprunglink zum Inhalt, sichtbare Fokusrahmen, Kontrast mindestens WCAG AA, jedes Bild mit Alt-Text, genau eine `h1` pro Seite.
- Bilder als `<picture>` mit WebP und JPEG, feste `width`/`height`, `loading="lazy"` außer beim Titelbild.
- Das Access-JWT liegt nur im Arbeitsspeicher, nie in `localStorage`; dort steht höchstens das Merkzeichen `dispatch.angemeldet`.
- Alle Pfade zur API sind absolut mit Präfix `/dispatch/api/` (ADR-001).

## Critical Rules

1. **Besucher lösen außer dem Zähl-Aufruf keinen PHP-Aufruf aus** — sonst ist die statische Auslieferung und ihr Tempo dahin.
2. **Kein Inline-Skript** — die CSP blockiert es, und eine Ausnahme dafür würde den Redaktionsmodus angreifbar machen.
