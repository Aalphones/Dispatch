# 029 — Feed und Sitemap über die Brücke

**Status:** Akzeptiert (2026-11-13) · ergänzt ADR-003 und ADR-028

## Kontext

Eingeplante Artikel erscheinen beim ersten Aufruf der Brücke ab ihrem Termin (ADR-028); es gibt keinen Server-Cron. Feed-Reader und Crawler rufen aber nur `/feed.xml` und `/sitemap.xml` ab, und die lieferte Apache bisher als statische Dateien aus, ohne PHP. Sie lösten also nichts aus, und ein Artikel zum Termin tauchte dort erst nach dem nächsten Besucher auf.

## Entscheidung

- Die Wurzel-`.htaccess` leitet genau `^(feed|sitemap)\.xml$` an `dispatch/index.php` um. Das Muster hat keinen Unterordner, `/card/feed.xml` o. ä. bleibt unberührt (Critical Rule 2).
- `Api\FeedController` liefert die vom Builder geschriebene Datei aus `web_root` aus. Der Auslöser (ADR-028) läuft vorher, ein gerade erschienener Artikel steckt damit schon im Feed.
- Zwischenspeichern wie bei Apache: `ETag` der Form `"d-<mtime hex>-<size hex>"`, `Last-Modified`, `Cache-Control: public, max-age=300`. `If-None-Match` hat Vorrang vor `If-Modified-Since`; Treffer → 304 ohne Rumpf.
- `GET` und `HEAD` sind eigene Routen (der Router behandelt HEAD nicht selbst); HEAD liefert die Kopfzeilen mit `Content-Length`, ohne Rumpf.
- Fehlt die Datei (vor dem ersten Bau): 404 im Fehlerformat der Brücke.
- Der lokale Dev-Router leitet die zwei Pfade ebenfalls an die Brücke.

## Verworfen

- **Externer Wecker (cron-job.org):** zusätzlicher Dienst, vom Inhaber nicht gewollt (ADR-028).
- **Feed ganz dynamisch erzeugen:** doppelte Logik neben dem Builder.

## Konsequenzen

- Feed und Sitemap hängen an PHP: Fällt die Brücke aus, fallen beide mit aus.
- Der Builder bleibt einzige Quelle des Inhalts.
- Critical Rule 1 des Frontends (kein PHP für Besucher) hat damit zwei benannte Ausnahmen neben dem Zähl-Aufruf.
