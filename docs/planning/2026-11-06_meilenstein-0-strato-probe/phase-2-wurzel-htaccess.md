# Phase 2 — Wurzel-`.htaccess`

**Rating:** heikel (eine Datei, die jede Anwendung der Domain betrifft) · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- `docs/decisions/001-pfade-auf-gemeinsamer-domain.md`, `003-wurzel-htaccess.md`
- `docs/knowledge/INDEX.md` → Eintrag „CamScanner unter `/scanner/`" (Header vererben sich, CSP würde die KI-Funktion brechen)
- Konzept `docs/design/konzept-blog-engine.md` → „Betrieb auf Strato Shared Hosting" → „`.htaccess`, die Kernregeln" (Inhalt der CSP; wo DISPATCH abweicht, gilt ADR-003)
- Bisherige Datei als Ausgangslage: `C:\Users\sasch\develop\CardMaker\www-root\.htaccess`
- Fehlerklassen: `~/.claude/knowledge/topics/strato-shared-hosting.md` — keine Zeile betrifft `.htaccess` direkt; Vererbungsrisiko steht im Projekt-Wissen oben.

## Chesterton-Check der alten Datei (verstanden)

Die CardMaker-Fassung tut drei Dinge: `Options -Indexes` (keine Ordnerlisten — bleibt global), HTTPS-Weiterleitung (bleibt global), `RewriteRule ^$ /card/ [L,R=302]` (Startseite zeigte auf CardMaker — entfällt laut ADR-003, weil `/` jetzt der Blog ist). Nichts davon geht ersatzlos verloren außer der gewollt entfallenden Weiterleitung.

## Entscheidungen (fest)

- **Keine verschachtelten `<If>`-Blöcke** (Apache unterstützt Verschachtelung erst ab 2.4.26, Version auf Strato unbekannt). Jede Bedingung als eigener flacher Block mit vollständigem Ausdruck.
- Ausnahme-Muster für Nachbarn: `m#^/(card|api|backend|scanner)(/|$)#`.
- `/dispatch/` bekommt keine HTML-Cache-Regel (PHP setzt `no-store` selbst); die Sicherheits-Header gelten dort mit, das schadet JSON nicht.
- Der Authorization-Header wird nur in `bridge/.htaccess` durchgereicht (Phase 1), nicht in der Wurzel — die Wurzel-Regel würde sich sonst in die Nachbarn vererben. Ob er nach dem Umschreiben von `/.well-known/oauth-*` ankommt, prüft Meilenstein 5.

## Zieldatei `web-root/.htaccess`

```apache
# Wurzel von quantum-canvas.de — gehoert DISPATCH (ADR-003), hochgeladen von DISPATCHs deploy.cmd.
# Jede Regel hier vererbt sich in /card/, /api/, /backend/ (CardMaker) und /scanner/ (CamScanner).
# Global nur HTTPS, -Indexes, DirectoryIndex; alles Blog-Spezifische nur in Bloecken, die die Nachbarn ausnehmen.
Options -Indexes
DirectoryIndex index.html

RewriteEngine On

RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# OAuth-Discovery liegt an der Wurzel, beantwortet wird sie von der Bruecke (ADR-001).
RewriteRule ^\.well-known/oauth-(protected-resource|authorization-server)(/.*)?$ dispatch/index.php [L]

# Blog-Regeln: nie fuer die Nachbarordner.
<If "%{REQUEST_URI} !~ m#^/(card|api|backend|scanner)(/|$)#">
    ErrorDocument 404 /404.html
    <IfModule mod_headers.c>
        Header always set X-Content-Type-Options "nosniff"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    </IfModule>
</If>

# Dateien unter /assets/ und /media/ tragen Versions-Hashes im Namen.
<If "%{REQUEST_URI} =~ m#^/(assets|media)/#">
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=31536000, immutable"
    </IfModule>
</If>

# HTML des Blogs kurz cachen, Nachbarn und Bruecke ausgenommen.
<If "%{REQUEST_URI} !~ m#^/(card|api|backend|scanner|dispatch|dispatch-app)(/|$)# && %{REQUEST_URI} =~ m#(/|\.html)$#">
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=300"
    </IfModule>
</If>
```

Umlaute in Kommentaren bewusst als `ae/oe/ue` (Apache-Konfiguration, ASCII-sicher).

## Abnahme (Phase)

Lokal gibt es kein Apache; die Wirkung prüft Phase 5 auf Strato. Hier:

- [x] Datei liegt unter `web-root/.htaccess`, Inhalt wie oben, LF-Zeilenenden (`.gitattributes` setzt `eol=lf`).
- [x] Gegenlesen jeder Regel gegen ADR-003: global nur HTTPS, `-Indexes`, `DirectoryIndex`; keine `/card/`-Weiterleitung; kein `SetEnv`; keine Regel ohne Nachbar-Ausnahme außer den drei globalen und der Discovery-Umschreibung.
- [x] Muster-Probe der Ausdrücke mit PHP im Scratchpad (`preg_match` mit denselben Mustern, Delimiter `#`): `/card/`, `/card`, `/api/x`, `/scanner/capture` → Nachbar; `/cardinal/`, `/`, `/artikel/x/`, `/assets/a.css` → kein Nachbar.

## Checkliste

- [x] `web-root/.htaccess` anlegen
- [x] Abnahme fahren, Ergebnis in „Report-Back"
- [x] `docs/code-map.md` prüfen (Zeile `web-root/.htaccess` existiert schon) — nichts zu tun, falls unverändert

## Report-Back

- `web-root/.htaccess` wortgleich nach Plan angelegt; 0 CR-Bytes, `git check-attr eol` = lf.
- Muster-Probe mit PHP `preg_match`: alle 8 Fälle wie erwartet (`/card/`, `/card`, `/api/x`, `/scanner/capture` → Nachbar; `/cardinal/`, `/`, `/artikel/x/`, `/assets/a.css` → kein Nachbar).
- Gegenlesen gegen ADR-003: global nur HTTPS, `-Indexes`, `DirectoryIndex`; keine `/card/`-Weiterleitung, kein `SetEnv`; alles übrige im Nachbar-Ausnahmeblock bzw. Discovery-Umschreibung.
- 🟡 Die Wirkung (Header-Vererbung, `<If>`-Syntax auf der Strato-Apache-Version) ist lokal nicht prüfbar; Phase 5.
- `docs/code-map.md`: Zeile existierte, unverändert.
