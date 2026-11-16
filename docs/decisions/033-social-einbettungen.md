# 033 — Social-Einbettungen ohne Plattform-Skript

**Status:** Akzeptiert (2026-11-16) · ergänzt ADR-003 (Wurzel-.htaccess) und ADR-032

## Kontext

Artikel sollen Beiträge von X, Instagram, Facebook und TikTok zeigen können (Text, Bild, Video). Die üblichen Einbettungs-Skripte der Plattformen würden `script-src` für die ganze Seite aufweichen und beim Seitenaufruf Daten übertragen.

## Entscheidung

- **Kein Plattform-Skript.** Alle vier Plattformen bieten Beitrags-iframes: X `platform.twitter.com/embed/Tweet.html?id=<id>`, Instagram `www.instagram.com/<p|reel|tv>/<code>/embed/`, Facebook `www.facebook.com/plugins/post.php?href=<url>` (Videos `plugins/video.php`), TikTok `www.tiktok.com/embed/v2/<id>`.
- **Syntax:** `<figure data-embed="x|instagram|facebook|tiktok" data-url="https://…">` mit optionaler `figcaption`. `Site/SocialEmbed` prüft die Adresse je Plattform streng (nur https, feste Host-Liste, ID nach Muster, kein Benutzername/Port) und baut die iframe-Adresse samt Standardhöhe (X 520, Instagram 640, Facebook 560, TikTok 740 px; Breite höchstens 550 px, mittig).
- **Ungültige Adresse:** Der Sanitizer behält eine https-Adresse und meldet, dass sie nicht zur Plattform passt; `FormCheck` warnt (`component_invalid`); der Renderer macht daraus nur einen Klartext-Link, nie einen iframe. Eine Adresse ohne http(s) fällt weg.
- **Zwei-Klick:** Der Renderer erzeugt eine Vorschau-Karte (Plattformname, Hinweis „Lädt Inhalte von …“, Knopf „Beitrag laden“, Link „Original öffnen“). Die iframe-Adresse steht nur in `data-src`; `artikel.js` setzt erst nach dem Klick `iframe.src` (mit `sandbox`). Ohne Skript bleibt der Link zum Original.
- **CSP:** `frame-src` wird um `https://platform.twitter.com https://www.instagram.com https://www.facebook.com https://www.tiktok.com` erweitert, nur im Block der Wurzel-`.htaccess`, der die Nachbarordner ausnimmt. `script-src` bleibt `'self'`. Die Wurzel-`.htaccess` wirkt erst nach dem Deploy.
- **Stil:** Die Karte gehört zu den Bausteinen (`bausteine.css`, Flag `bausteine`). Neue Obergrenzen: `bausteine.css` ≤ 17 000, `artikel.js` ≤ 13 500 Byte.

## Verworfen

- **Plattform-Skripte** (`widgets.js`, `embed.js`): öffnen die ganze Seite für Fremdcode.
- **Beitragstext selbst abrufen und speichern:** Rechte- und Aktualitätsproblem, Nutzungsbedingungen der Plattformen.
- **Kurzlinks** (`vm.tiktok.com`, `fb.watch`, `t.co`): würden einen Abruf beim Bauen brauchen; der Agent nimmt die lange Adresse.

## Folgen

- Weitere Plattform = Eintrag in `SocialEmbed` (Host, Muster, iframe-Adresse), `frame-src` und Style-Guide.
- Nach dem Deploy der Wurzel-`.htaccess` prüfen, dass der Header genau die vier neuen Hosts enthält.
