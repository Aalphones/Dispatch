# Phase 7 — Feed, Sitemap, SEO, Deploy und Abschluss

**Rating:** standard · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt", „Finale Abnahme"
- Konzept → „SEO" (Meta, OpenGraph, JSON-LD, Feed, Sitemap); `docs/PROJECT.md` → Abweichungen (robots.txt sperrt `/dispatch/`, `/dispatch-app/`, `/vorschau/`, `/anmelden/`)
- ADR-001, ADR-003, ADR-005 (Deploy mit Rauchtest), ADR-014
- `docs/conventions/dod.md` (Nachweis `/card/` und `/scanner/capture` nach allem, was Builder oder Wurzel berührt)
- `docs/knowledge/INDEX.md` → Strato-Messwerte; Vault-Topic `strato-shared-hosting` → „aus einem 200 nicht schließen, dass …" (Inhalt prüfen, nicht nur Status)
- Fehlerklassen (Vault `werkzeuge/edge-headless.md`), falls Bildschirmfotos oder Lighthouse per Headless-Browser laufen: **Kein Bild, kein Fehler**, **Schmale Bildschirmfotos sind nur beschnitten** (Handybreiten per Geräte-Emulation setzen), **„Datei existiert" ist kein Nachweis** (Ergebnis ansehen), **gleiche Dateigröße heißt nicht „nichts geändert"** (frisches Profil je Lauf)

## AK der Phase

1. `<head>` jeder Seite: `title` (`seo_title` bzw. Titel + „ · {site_title}"; Startseite `site_title — site_tagline`), `meta description` (`seo_description` bzw. Teaser bzw. `site_description`), `link rel="canonical"` (absolut mit `base_url`), `meta robots` laut Phase 5, `meta name="theme-color"` (Papierton hell/dunkel). Artikel zusätzlich OpenGraph (`og:type article`, `og:title`, `og:description`, `og:url`, `og:image` = 1200er JPEG, `og:image:width/height/alt`, `article:published_time`, `article:modified_time`, `article:section`, je Schlagwort `article:tag`) und `twitter:card summary_large_image`.
2. JSON-LD (als `<script type="application/ld+json">` — Daten, kein ausführbares Skript; CSP bleibt unverändert, Browser führen den Typ nicht aus): Artikel `BlogPosting` (headline, description, datePublished, dateModified, author `Person`, publisher `Organization` mit `name`, wordCount, articleSection, keywords, `citation` = alle Quellen als `CreativeWork` mit `name`/`url`, `image` als `ImageObject` mit `url`, `width`, `height`, `creditText`, `creator`, `license`, `acquireLicensePage` = `source_url`, `copyrightNotice` = credit) + `BreadcrumbList`; Startseite `WebSite` mit `SearchAction` (`/suche/?q={search_term_string}`). JSON per `json_encode(…, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR)` — `JSON_HEX_TAG` verhindert ein `</script>` im Inhalt.
3. `feed.xml`: RSS 2.0 mit `content:`- und `atom:`-Namensraum, die 20 neuesten Artikel, je `title`, `link`, `guid isPermaLink="true"`, `pubDate` (RFC 822), `description` (Teaser), `content:encoded` (gerendertes HTML in CDATA, alle `src`, `srcset`, `href` absolut gemacht; `]]>` im Inhalt aufgetrennt), je Kategorie `category`, `enclosure` = 1200er JPEG des Titelbilds mit `length` (Dateigröße) und `type`; Kanal mit `atom:link rel="self"`. Besteht den W3C-Feed-Validator (Inhalt per „Validate by Direct Input").
4. `sitemap.xml`: alle indexierbaren Seiten (Start, Seitenzahlen nicht, Artikel, Kategorien, Kategorien-Übersicht, Schlagwörter, Über) mit `lastmod` (Artikel: `updated_at`; Listen: neuester enthaltener Artikel), Artikel mit `image:image`/`image:loc` (Titelbild 1200er JPEG); Namensraum `http://www.google.com/schemas/sitemap-image/1.1`.
5. `robots.txt`: `User-agent: *`, `Disallow: /dispatch/`, `/dispatch-app/`, `/vorschau/`, `/anmelden/`, `/suche/`, `Sitemap: {base_url}/sitemap.xml`. **Vor dem ersten Deploy** prüfen, ob auf Strato schon eine `robots.txt` liegt (`curl -s https://quantum-canvas.de/robots.txt`, Inhalt mit einer erfundenen Adresse vergleichen) — liegt dort eine fremde, nicht überschreiben, sondern im Chat melden.
6. Finale Abnahme aus der README vollständig durchgegangen, je Punkt mit Beleg im Report-Back. Was hier nicht prüfbar ist (Lighthouse ohne Browser, Rich-Results-Test braucht eine öffentliche Adresse), ausdrücklich so benennen.

## Checkliste

- [ ] `Site/Seo.php`: `meta(array $page, SiteData $data): array` und `jsonLd…`-Methoden; `layout.php` gibt Meta und JSON-LD aus.
- [ ] `Site/Feed.php`, `Site/Sitemap.php`, `robots.txt` in `Builder::collectTechnical()`; Hilfsfunktion `absolut(string $html, string $baseUrl): string` per `Dom\HTMLDocument` (Attribute `src`, `href`, `srcset` mit führendem `/`).
- [ ] Lighthouse-Lauf lokal: `npx lighthouse http://localhost:8080/ --preset=desktop` und mit `--form-factor=mobile` für Startseite und einen Longform-Artikel (Ergebnis-Zahlen ins Report-Back). Ohne lokalen Chrome: `npx -y @puppeteer/browsers install chrome@stable` in einen Ordner außerhalb des Repos, Pfad per `CHROME_PATH`. Unter 95 → Ursache beheben (meist Kontrast, fehlende Maße, Render-Blocker), nicht die Messung wiederholen, bis es passt.
- [ ] Deploy: `deploy.cmd`; danach Startseite, ein Artikel, `/kategorien/`, `/suche/?q=…`, `/feed.xml`, `/sitemap.xml`, `/robots.txt`, eine unbekannte Adresse (404-Seite mit Status 404) per `curl -sI` und im Browser; `/card/`, `/scanner/capture`, `/dispatch-app/.env` (403) wie vorher.
- [ ] Auf Strato gibt es keine Demo-Daten (CLI läuft nur lokal) — die Live-Seite zeigt den echten, anfangs leeren Bestand im Leer-Zustand aus Phase 5 (AK 7); die Suche läuft mit leerem Index ohne Konsolenfehler.
- [ ] Doc: `docs/code-map.md` (Site/Seo, Feed, Sitemap), `docs/PROJECT.md` → Meilenstein 2 „(erledigt)", `docs/knowledge/INDEX.md` → Lighthouse-Werte und Build-Dauer auf Strato, `AGENTS.md` → „Lokal starten" um `php bin/dispatch build`.
- [ ] Plan archivieren nach `docs/archive/2026-11/` (Ablauf `mode-implementing`), STATE.md auf den nächsten Schritt „Meilenstein 3 planen".

## Report-Back
