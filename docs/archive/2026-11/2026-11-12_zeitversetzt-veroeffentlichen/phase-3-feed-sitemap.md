# Phase 3 — Feed und Sitemap über die Brücke (heikel)

Ziel: Abrufe von `/feed.xml` und `/sitemap.xml` laufen durch die Brücke, lösen damit die Fälligkeitsprüfung aus (Phase 2) und bekommen dieselbe Datei wie bisher, mit sauberem Zwischenspeichern (`ETag`, `Last-Modified`, `304`). Heikel, weil die Wurzel-`.htaccess` sich auf die Nachbar-Anwendungen vererbt (Critical Rule 2 in `AGENTS.md`, ADR-003).

## Kontext (vor dem Start lesen)

- [`README.md`](README.md): Entscheidung 8, Kontrakt (Zeile `/feed.xml`, `/sitemap.xml`), Konfidenz-Ausweis Punkt 1.
- `AGENTS.md` → Critical Rules 1–3; `docs/decisions/001-pfade-auf-gemeinsamer-domain.md`, `003-wurzel-htaccess.md`, `006-lokale-entwicklung-dev-router.md`.
- `web-root/.htaccess` (bestehende Regel für `.well-known` → `dispatch/index.php` ist das Vorbild), `bridge/.htaccess`, `bridge/index.php`.
- `dev-router.php` (Bedingung für Brücke und `.well-known`).
- `app/src/Http/Response.php`, `Http/Request.php` (`header()`, `method()`), `Http/Router.php` (HEAD ist **keine** eigene Behandlung → eigene Route nötig).
- `app/config.php` (`web_root`), `app/src/Site/Builder.php` → `collectTechnical` (welche Dateien entstehen).
- `docs/conventions/frontend.md` → Critical Rule 1.

## Abnahmekriterien der Phase

- P3-AK1: Lokal (`php -S … dev-router.php`) liefern `curl -si http://localhost:8080/feed.xml` und `…/sitemap.xml` Status 200, den Inhalt der Datei in `public/`, `Content-Type` `application/rss+xml; charset=utf-8` bzw. `application/xml; charset=utf-8`, `ETag` der Form `"d-<hex>-<hex>"`, `Last-Modified`, `Cache-Control: public, max-age=300`.
- P3-AK2: Mit `-H 'If-None-Match: <ETag>'` → 304 ohne Rumpf; mit `-H 'If-Modified-Since: <Last-Modified>'` (ohne `If-None-Match`) → 304; `curl -I` (HEAD) → 200 mit `Content-Length`, ohne Rumpf.
- P3-AK3: Fehlt die Datei (vor dem ersten Bau) → 404 im Fehlerformat der Brücke.
- P3-AK4: Die neue Rewrite-Regel trifft nur `^(feed|sitemap)\.xml$` an der Wurzel; `/card/feed.xml` o. ä. bleibt unberührt (Regel-Blick: Muster ohne Unterordner, steht in der Wurzel-`.htaccess`).
- P3-AK5: Nach dem Deploy: Check aus dem Konfidenz-Ausweis (ETag-Format der Brücke, `304`) auf `https://quantum-canvas.de/feed.xml`.
- P3-AK6: ADR-029 existiert; ADR-003 verweist darauf; Critical Rule 1 in `docs/conventions/frontend.md` nennt die Ausnahme.

## Checkliste

- [x] `app/src/Http/Response.php`: zwei Helfer.
  - `public static function file(string $path, string $contentType, array $headers, bool $withBody): never` — `http_response_code(200)`, `Content-Type`, `Content-Length` (`filesize`), übergebene Kopfzeilen, bei `$withBody` `readfile($path)`, dann `exit`. **Kein** `Cache-Control: no-store` (anders als `raw`/`empty`).
  - `public static function notModified(array $headers): never` — `http_response_code(304)`, übergebene Kopfzeilen, kein Rumpf, `exit`.
- [x] Neue Datei `app/src/Api/FeedController.php` (`final class`, Konstruktor `private readonly array $config`), Docblock „Liefert Feed und Sitemap durch die Brücke aus, damit ihr Abruf die Fälligkeitsprüfung auslöst (ADR-029). Inhalt ist die vom Builder geschriebene Datei.“
  - Konstante `TYPES = ['feed.xml' => 'application/rss+xml; charset=utf-8', 'sitemap.xml' => 'application/xml; charset=utf-8']`.
  - `serve(Request $request, string $file): never`: Pfad `rtrim((string) $this->config['web_root'], '/') . '/' . $file`; nicht vorhanden → `Response::error('not_found', 'Die Datei gibt es noch nicht.', 404)`. `clearstatcache(true, $path)`; `$mtime = filemtime`, `$size = filesize`; `$etag = '"d-' . dechex($mtime) . '-' . dechex($size) . '"'`; `$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT'`; `$headers = ['ETag' => $etag, 'Last-Modified' => $lastModified, 'Cache-Control' => 'public, max-age=300']`.
  - 304, wenn `If-None-Match` vorhanden und (nach Zerlegen an Kommas, getrimmt, Präfix `W/` entfernt) `$etag` oder `*` enthält; sonst wenn `If-Modified-Since` vorhanden, per `strtotime` lesbar und `>= $mtime`. Sonst `Response::file($path, self::TYPES[$file], $headers, $request->method() !== 'HEAD')`.
- [x] `app/index.php`: vier Routen (GET und HEAD je Datei), z. B. `$router->add('GET', '/feed.xml', static fn () => (new FeedController($config))->serve($request, 'feed.xml'));`, dasselbe mit `'HEAD'` und für `sitemap.xml`. `use Dispatch\Api\FeedController;`. Die Routen stehen vor dem `ScheduleTrigger`-Aufruf im Router; der Trigger läuft ohnehin vor `dispatch` — damit ist der Feed beim Ausliefern schon neu gebaut, wenn gerade etwas erschien.
- [x] `web-root/.htaccess`: direkt unter der `.well-known`-Regel:
  ```apache
  # Feed und Sitemap laufen durch die Bruecke: ihr Abruf loest das Erscheinen eingeplanter Artikel aus (ADR-029).
  RewriteRule ^(feed|sitemap)\.xml$ dispatch/index.php [L]
  ```
  Nur ASCII im Kommentar (wie der Rest der Datei).
- [x] `dev-router.php`: Bedingung für die Brücke um `$path === '/feed.xml' || $path === '/sitemap.xml'` erweitern; Kommentar entsprechend.
- [x] Lokal P3-AK1 bis P3-AK3 mit `curl` prüfen (nach `php bin/dispatch build`).
- [x] `docs/decisions/029-feed-und-sitemap-ueber-die-bruecke.md`: Kontext (Feed-Reader und Crawler lösen sonst nichts aus, kein Cron), Entscheidung (Rewrite nur für zwei Dateien an der Wurzel, Auslieferung aus der gebauten Datei, `ETag`/`304`, HEAD), Verworfen (externer Wecker; Feed ganz dynamisch erzeugen — doppelte Logik zum Builder), Konsequenzen (Feed und Sitemap hängen an PHP; fällt die Brücke aus, fallen beide mit aus; Builder bleibt einzige Quelle des Inhalts).
- [x] ADR-003: eine Zeile unter „Entscheidung“: „Ergänzt durch ADR-029: Rewrite von `/feed.xml` und `/sitemap.xml` auf die Brücke.“
- [x] `docs/conventions/frontend.md` Critical Rule 1: „Besucher lösen außer dem Zähl-Aufruf keinen PHP-Aufruf aus — Ausnahme: `/feed.xml` und `/sitemap.xml` laufen durch die Brücke (ADR-029), sonst ist die statische Auslieferung und ihr Tempo dahin.“
- [x] `docs/routes.md`: Zeile für `GET, HEAD /feed.xml`, `/sitemap.xml` (Auth —, `Api\FeedController::serve`, Plan 2026-11-12); `docs/code-map.md`: `src/Api/` um `FeedController`, Tabelle „Repo → Server“ Zeile `web-root/.htaccess` um „leitet Feed und Sitemap an die Brücke“.
- [x] `php -l` auf alle geänderten PHP-Dateien.

## Report-Back

Stand: P3-AK1 bis AK4 lokal per curl belegt (200, ETag/Last-Modified, 304 per If-None-Match und If-Modified-Since, HEAD ohne Rumpf, 404 ohne Datei). P3-AK5 offen bis nach dem Deploy. ADR-029 (nicht 028, 028 ist der Zeitplan).
