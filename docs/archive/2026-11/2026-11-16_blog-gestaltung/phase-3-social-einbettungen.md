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
- [x] `Site/SocialEmbed.php`
- [x] Sanitizer (Provider, `data-url`)
- [x] ContentRenderer
- [x] artikel.js
- [x] bausteine.css
- [x] `.htaccess` CSP
- [x] FormCheck-Meldung
- [x] ADR-033
- [x] style-guide.md
- [x] code-map

## Report-Back
- Fertig: `Site/SocialEmbed` (strenge Adressprüfung je Plattform, iframe-Adresse, Standardhöhe), Sanitizer (Provider, `data-url`, Hinweis bei unpassender Adresse), ContentRenderer (Vorschau-Karte, Klartext-Link als Rückfall), `artikel.js` (iframe erst nach Klick, `sandbox`), `bausteine.css`, `.htaccess`-CSP, FormCheck-Warnung, ADR-033, Style-Guide, Code-Map.
- AK 1: je Plattform Beispiel gebaut (X Text/Foto, Instagram p/reel/tv, Facebook posts/videos/watch, TikTok video) — per Skript gegen die Klasse geprüft. AK 2: iframe-Adresse nur in `data-src`, kein `<iframe>` im Ausgabe-HTML. AK 3: fremde Host, http, Benutzer@Host, Subdomain-Tricks, Kurzlinks ergeben null. AK 4: Link „Original öffnen“ im Markup. AK 5: Header enthält die vier Hosts im Nachbar-Ausnahme-Block.
- Abweichung: Karten-CSS liegt in `bausteine.css` (wie geplant), nicht in `style.css`; Obergrenzen in ADR-033 angehoben (bausteine.css 17 000, artikel.js 13 500; artikel.js lag schon vorher über 12 500).
- Offen: 🟡 `.htaccess` wirkt erst nach Deploy — erst dann laden die iframes im Browser. Sichtprüfung der echten Plattform-Einbettungen im Browser steht aus (kein Netz/Browser-Lauf in dieser Station).
