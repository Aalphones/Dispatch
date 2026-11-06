# 003 — Die Wurzel-`.htaccess` gehört DISPATCH

**Status:** Akzeptiert (2026-11-06)

## Kontext

`www/.htaccess` erzwingt HTTPS und leitet bisher `/` nach `/card/` um. Hochgeladen wurde sie von CardMakers Deploy (`www-root/.htaccess`, Schalter `REMOTE_ROOT_PATH`). Mit dem Blog auf der Wurzel braucht diese Datei Regeln für DISPATCH. Zwei Repos, die dieselbe Datei hochladen, überschreiben sich gegenseitig.

Apache vererbt die Regeln einer `.htaccess` in alle Unterordner: Header, `ErrorDocument`, Caching und Rewrite-Bedingungen aus der Wurzel gelten auch für `/card/`, `/api/` und `/scanner/`, solange deren eigene `.htaccess` sie nicht ersetzt. Eine strenge Content-Security-Policy des Blogs würde dort Skripte blockieren.

## Entscheidung

- DISPATCH führt die Datei in `web-root/.htaccess` und lädt sie hoch. CardMakers Deploy lädt sie nicht mehr hoch (`REMOTE_ROOT_PATH` in CardMakers `deploy.env` entfernt).
- Global gelten nur: HTTPS erzwingen, `Options -Indexes`, `DirectoryIndex index.html`.
- Die Weiterleitung `/` → `/card/` entfällt.
- Alles Blog-Spezifische — CSP und weitere Sicherheits-Header, `ErrorDocument 404`, Cache-Zeiten — steht in einem `<If>`-Block, der die Nachbarordner (`card`, `api`, `backend`, `scanner`) ausnimmt.
- Rewrites auf die Brücke greifen nur für `/dispatch/` und `/.well-known/oauth-*` (ADR-001).

## Konsequenzen

- Eine Änderung an dieser Datei betrifft potenziell alle Anwendungen der Domain. Nach jedem Deploy prüft das Skript, dass die Nachbarn antworten (ADR-005).
- Kommt eine neue Anwendung dazu, wird ihr Ordner in die Ausnahme aufgenommen.
- In CardMakers `www-root/.htaccess` und ADR-020 steht noch die alte Zuständigkeit; das ist dort nachzuziehen.
