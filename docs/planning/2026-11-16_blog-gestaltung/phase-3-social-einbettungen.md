# Phase 3 — Social-Einbettungen (heikel)

## Kontext (lesen)
`ContentRenderer::renderEmbeds` (Z. ~643–673) und `EMBED_LABELS`, `Sanitizer::ALLOWED_EMBEDS` + `figure data-embed`-Zweig (Z. ~288), `app/theme/artikel.js` (Einbettungs-Start, Z. ~3 und ~252), `web-root/.htaccess` Z. 24 (CSP; Critical Rule 2), ADR-003, ADR-032, `app/mcp/style-guide.md`.

## Entscheidungen (ADR-033)
- Kein Plattform-Skript. Alle vier bieten Beitrags-iframes: X `https://platform.twitter.com/embed/Tweet.html?id=<id>`, Instagram `https://www.instagram.com/<p|reel|tv>/<code>/embed/`, Facebook `https://www.facebook.com/plugins/post.php?href=<url>` (Videos `plugins/video.php`), TikTok `https://www.tiktok.com/embed/v2/<id>`. Text, Bild und Video deckt die Plattform im iframe ab.
- Syntax: `figure[data-embed=x|instagram|facebook|tiktok][data-url]`. Neue Klasse `Site/SocialEmbed` parst die URL strikt je Plattform (Host-Liste, Regex für die ID), baut die iframe-URL und eine Standardhöhe (X 520, Instagram 640, Facebook 560, TikTok 740 px; Breite max. 550 px, mittig). Ungültige URL → Sanitizer-Hinweis, die Figure fällt auf einen Klartext-Link zurück.
- Zwei-Klick wie bei YouTube: Vorschau-Karte mit Plattformname, Hinweis „lädt Inhalte von <Plattform>“, Knopf „Beitrag laden“ und immer sichtbarer Link „Original öffnen“ (Fallback ohne Skript). `artikel.js` setzt erst nach dem Klick `iframe.src`.
- CSP `frame-src` um `https://platform.twitter.com https://www.instagram.com https://www.facebook.com https://www.tiktok.com` erweitern, nur im Block, der die Nachbarn ausnimmt. Wurzel-`.htaccess` wird nur beim Deploy getauscht; Sicherung in `deploy-backup/` beachten.
- Verworfen: Plattform-Skripte (`script-src` aufweichen) — öffnet die ganze Seite für Fremdcode.

## AK
1. Je Plattform ein URL-Beispiel (Text, Bild, Video) wird gebaut. 2. Vor dem Klick lädt der Browser nichts von der Plattform. 3. Fremde Host-URL wird nicht zum iframe. 4. Ohne Skript: Link „Original öffnen“. 5. CSP-Header enthält genau die vier neuen Hosts.

## Checkliste
- [ ] `Site/SocialEmbed.php`
- [ ] Sanitizer (Provider, `data-url`)
- [ ] ContentRenderer
- [ ] artikel.js
- [ ] bausteine.css
- [ ] `.htaccess` CSP
- [ ] FormCheck-Meldung
- [ ] ADR-033
- [ ] style-guide.md
- [ ] code-map

## Report-Back
