# Phase 4 — Medien: HTTP-Client mit SSRF-Schutz, Ablage, Bildvarianten

**Rating:** heikel (SSRF, Bildverarbeitung unter Speichergrenzen) · **Status:** complete

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Kontrakt"
- Phase 1 → Migration `004_media.sql`; Phase 2 → `ChangeService`, `SettingService` (Tabelle „Was öffentlich ist")
- Konzept → „Datenmodell" (Tabelle `media`, Absatz „Medien-Ablage"), „Schutz vor bösartigen Inhalten" (Medien nur aus der Bibliothek, SSRF-Schutz), „Bilder und Lizenzen", „Risiken" (Zeile „Strato-Limits bei großen Dateien")
- `docs/knowledge/INDEX.md` → „Strato-Messwerte" (`memory_limit` 512M, `max_execution_time` 240, `upload_max_filesize` 128M, GD mit WebP, `exif` vorhanden)
- `docs/conventions/php.md` → Critical Rule 4
- Fehlerklassen: keine einschlägig über die README hinaus

## Entscheidungen (fest)

**`config.php` ergänzen:** `'media' => ['max_bytes' => 50 * 1024 * 1024, 'max_megapixels' => 50, 'variant_widths' => [480, 800, 1200, 1600, 2000], 'jpeg_quality' => 82, 'webp_quality' => 80]`, `'http' => ['connect_timeout' => 5, 'timeout' => 20, 'max_redirects' => 4, 'allow_private' => Env::get('DISPATCH_ALLOW_PRIVATE_IPS') === '1']`. `DISPATCH_ALLOW_PRIVATE_IPS` gibt es nur lokal (`dev.env.example`), auf Strato nie.

**HTTP-Client (`Util\HttpClient`)** — einziger Weg nach außen:

1. URL mit `parse_url` zerlegen. Erlaubt: Schema `http`/`https`, Host vorhanden, **kein** Benutzer/Passwort, Port leer, 80 oder 443. Sonst `DomainError('url_rejected', …)`.
2. Host auflösen: IP-Literal (auch `[…]` für IPv6) direkt, sonst `dns_get_record($host, DNS_A | DNS_AAAA)`. Keine Adresse → `url_rejected`. **Jede** Adresse muss `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)` bestehen (außer `allow_private`), sonst `url_rejected` mit Satz „Ziel liegt in einem privaten oder reservierten Netz.".
3. Anfrage mit cURL auf die **geprüfte** Adresse festnageln: `CURLOPT_RESOLVE => ["$host:$port:$ip"]` (IPv6 in eckigen Klammern) — gegen DNS-Rebinding. `CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS`, `CURLOPT_FOLLOWLOCATION => false`, `CURLOPT_CONNECTTIMEOUT`, `CURLOPT_TIMEOUT`, `CURLOPT_USERAGENT => 'DISPATCH/1 (+<base_url>)'`, `CURLOPT_SSL_VERIFYPEER => true`.
4. Größenlimit über `CURLOPT_WRITEFUNCTION`: Bytes sammeln, bei Überschreitung `-1` zurückgeben (Abbruch) → `DomainError('too_large', …)`.
5. Status 301/302/303/307/308 mit `Location` → Ziel gegen die aktuelle URL auflösen (relativ, absolut, schemalos), ab Schritt 1 neu prüfen; höchstens `max_redirects`, sonst `DomainError('too_many_redirects', …)`.
6. Ergebnis `HttpResponse(int $status, string $contentType, string $body, string $finalUrl)`; Status ≠ 200 → `DomainError('fetch_failed', 'Abruf lieferte Status <n>.')`.

**Erlaubte Medientypen** — Typ per `finfo(FILEINFO_MIME_TYPE)->buffer($bytes)`, nie nach Dateiname oder Header:

| MIME | `kind` | `ext` |
|---|---|---|
| `image/jpeg`, `image/png`, `image/webp`, `image/gif` | image | `jpg`, `png`, `webp`, `png` (GIF wird als PNG neu kodiert, erstes Bild) |
| `video/mp4`, `video/webm` | video | `mp4`, `webm` |
| `audio/mpeg`, `audio/mp4`, `audio/ogg`, `audio/webm` | audio | `mp3`, `m4a`, `ogg`, `weba` |
| `application/pdf` | document | `pdf` |

Alles andere (ausdrücklich SVG, HTML, XML) → `DomainError('type_rejected', …)` mit erkanntem Typ.

**Bilder (`Util\ImageProcessor`)** — nie das Original-Byte speichern:

1. `getimagesizefromstring` → Breite × Höhe; über `max_megapixels` → `DomainError('too_many_pixels', …)` **vor** dem Dekodieren.
2. `imagecreatefromstring`; bei JPEG `exif_read_data` (aus temporärer Datei, Fehler ignorieren) → `Orientation` 3/6/8 drehen, 2/4/5/7 zusätzlich spiegeln (`imageflip`).
3. Alpha-fähig = Quelle PNG, WebP oder GIF → Rückfallformat PNG (`imagealphablending(false)`, `imagesavealpha(true)`); sonst JPEG.
4. `original.<ext>` neu kodiert schreiben (JPEG Qualität 92, PNG Stufe 6, WebP Qualität 90) — dabei fallen EXIF und GPS weg.
5. Varianten: alle Breiten aus `variant_widths` mit `w <= Breite`; ist das Bild schmaler als 480, genau eine Variante in Originalbreite. Je Breite `imagescale($img, $w, -1, IMG_BICUBIC)` → `<w>.webp` und `<w>.jpg` bzw. `<w>.png`. JPEG progressiv (`imageinterlace(true)`).
6. Speicher nach jeder Variante freigeben; `memory_get_peak_usage(true)` geht in den Rückgabewert (`peak_bytes`), damit die Abnahme die Grenze messen kann.
7. Rückgabe `ImageResult(int $width, int $height, string $ext, array $variants)`; `variants` = `[{"w": 480, "files": ["480.webp", "480.jpg"]}, …]`.

**Ablage:** `<storage_dir>/media/<uid>/`. Erst in `<storage_dir>/media/.tmp-<zufall>/` schreiben, dann Ordner per `rename` auf `<uid>`; bei Fehler den temporären Ordner löschen. `uid` = `'m' . bin2hex(random_bytes(5))`.

**Dubletten:** `sha256` der **Eingangs**-Bytes. Existiert der Hash → vorhandenen Datensatz zurückgeben mit `duplicate: true`, Metadaten **nicht** ändern.

**Metadaten** (`alt` ≤ 500, `caption` ≤ 1000, `credit` ≤ 300, `license` ≤ 120, `license_url` und `source_url` leer oder `http(s)://` ≤ 500/1000): beim Speichern alle optional — Pflicht prüft die Formprüfung (Phase 6). `origin = owner` setzt, falls leer, `credit` auf `SettingService::get('author_name')` und `license` auf „Eigenes Material".

**Öffentlich oder intern:** Ein Medium gilt als öffentlich, sobald ein Live-Stand es nutzt: `SELECT 1 FROM posts p JOIN post_revisions r ON r.post_id = p.id AND r.revision = p.published_revision WHERE p.published_revision IS NOT NULL AND p.status <> 'archived' AND r.snapshot LIKE ? LIMIT 1` mit `%<uid>%` (die UID ist zufällig und eindeutig, ein Fehltreffer ist ausgeschlossen). Dann ändert ein Nicht-Inhaber Metadaten nur per Vorschlag (`entity = 'media'`, `entity_key = <uid>`).

Festgehalten in ADR-011 „Medien: HTTP-Client mit SSRF-Schutz und Bildvarianten".

## Dateien

| Datei | Inhalt |
|---|---|
| `app/config.php` | `media`, `http` (oben); `use Dispatch\Util\Env;` ist schon da |
| `app/src/Util/HttpClient.php` | `final class HttpClient(array $httpConfig)`: `get(string $url, int $maxBytes): HttpResponse` |
| `app/src/Util/HttpResponse.php` | `final readonly class HttpResponse(int $status, string $contentType, string $body, string $finalUrl)` |
| `app/src/Util/ImageProcessor.php` | `final class ImageProcessor(array $mediaConfig)`: `process(string $bytes, string $mime, string $targetDir): ImageResult` |
| `app/src/Util/ImageResult.php` | `final readonly class ImageResult(int $width, int $height, string $ext, array $variants, int $peakBytes)` |
| `app/src/Domain/MediaRepository.php` | `findByUid(string $uid): ?array`, `findBySha(string $sha): ?array`, `findManyByUid(array $uids): array` (Schlüssel = uid), `insert(array $fields): int`, `updateMeta(string $uid, array $fields): void`, `list(?string $kind, int $limit, int $offset): array`, `isLive(string $uid): bool` (SQL oben) |
| `app/src/Domain/MediaService.php` | `store(string $bytes, array $meta, string $origin, Actor $actor): array` (Typ prüfen → Dublette? → Bild: `ImageProcessor`, sonst `original.<ext>` 1:1 → Zeile anlegen → Audit `media.store`; Rückgabe Datensatz + `duplicate`), `importFromUrl(string $url, array $meta, Actor $actor): array` (`HttpClient::get` mit `max_bytes`, dann `store(…, 'import', …)`; `source_url` bleibt, was der Einreicher angibt), `get(string $uid): array` (`not_found`), `list(…)`, `updateMeta(string $uid, array $meta, Actor $actor): array` (`applied`/`change_id`, Regel oben), `applyChange(string $uid, array $payload): void`, `filePath(string $uid, string $file): string` (nur Dateinamen aus `variants` oder `original.<ext>`, sonst `not_found`) |
| `app/src/Domain/ChangeService.php` | Zweig `media` → `MediaService::applyChange` |
| `dev.env.example` | `DISPATCH_ALLOW_PRIVATE_IPS='1'` mit Kommentar „nur lokal" |

## Abnahme (Phase)

Scratchpad-Skripte gegen `dispatch_dev`. Für die SSRF-Fälle `allow_private` **aus** (Variable leer).

- [x] SSRF abgewiesen (`url_rejected`): `http://127.0.0.1/`, `http://localhost/`, `http://10.0.0.1/`, `http://192.168.1.1/`, `http://169.254.169.254/latest/meta-data/`, `http://[::1]/`, `http://[::ffff:127.0.0.1]/`, `http://0.0.0.0/`, `ftp://example.com/x`, `file:///etc/passwd`, `http://user:pw@example.com/`, `http://example.com:8080/`.
- [x] Redirect auf privat: lokal `php -S 127.0.0.1:9000` mit einem Skript, das per 302 auf `http://127.0.0.1:9000/x` leitet, ist nur mit `allow_private` erreichbar — **ohne** wird schon der erste Aufruf abgewiesen; zusätzlich mit `allow_private` an: Kette aus 5 Redirects → `too_many_redirects`.
- [x] Echte Bilder: `importFromUrl` einer Wikimedia-Commons-Datei (JPEG, > 2000 px) → Ordner mit `original.jpg` und `480/800/1200/1600/2000` je `.webp` + `.jpg`; `exif_read_data` auf `original.jpg` findet kein GPS/`Make`; zweiter Import derselben URL → `duplicate: true`, gleiche `uid`.
- [x] Ein Handyfoto mit `Orientation = 6` und GPS → Varianten stehen aufrecht, kein GPS in `original.jpg`.
- [x] PNG mit Transparenz (300 px breit) → genau eine Variante `300.webp` + `300.png`, Transparenz erhalten (im Browser auf dunklem Hintergrund ansehen).
- [x] SVG, HTML-Datei, als `.jpg` umbenannte HTML-Datei → `type_rejected`; Datei über `max_bytes` (lokal `max_bytes` kurz auf 1 MB setzen) → `too_large`; Bild mit 60 MP (per GD erzeugt) → `too_many_pixels`.
- [x] Speicher: 50-MP-JPEG (per GD erzeugt, 8660 × 5774) verarbeiten → `peak_bytes` im Report-Back; liegt er über 400 MB, `max_megapixels` auf den gemessenen sicheren Wert senken und in ADR-011 begründen.
- [x] `updateMeta` durch `mcp:test` auf ungenutztem Medium → direkt; Medium per SQL als Live-Nutzung vorbereitet (Revision mit `uid` im Snapshot, `published_revision` gesetzt) → Vorschlag; `ChangeService::apply` mit Inhaber → angewandt.
- [x] `filePath(uid, '../../.env')` → `not_found`.

## Checkliste

- [x] Dateien laut Tabelle
- [x] Abnahme fahren, SSRF-Fälle einzeln mit Ergebnis im Report-Back (`dod.md`)
- [x] ADR-011 „Medien: HTTP-Client mit SSRF-Schutz und Bildvarianten" (Festnageln per `CURLOPT_RESOLVE` und warum; Ports nur 80/443; Typ per `finfo`; immer neu kodieren; gemessener Speicherbedarf) + Zeile in `docs/decisions/README.md`
- [x] `docs/models.md`: `media.variants`-Format, Ablage-Layout
- [x] `docs/code-map.md`: `Util/HttpClient`, `Util/ImageProcessor`, `Domain/Media*`, `storage/media/`

## Report-Back

- **SSRF (12 Fälle, `allow_private` aus):** alle `url_rejected` — `127.0.0.1`, `localhost`, `10.0.0.1`, `192.168.1.1`, `169.254.169.254`, `[::1]`, `[::ffff:127.0.0.1]`, `0.0.0.0`, `ftp://`, `file://`, `user:pw@`, Port 8080. Zusätzlich `http://2130706433/` abgewiesen.
- **Redirect:** ohne `allow_private` schon der erste Aufruf abgewiesen; mit: Weiterleitung auf `/x` und relative Weiterleitung funktionieren, Kette aus 5 → `too_many_redirects`; 3-MB-Antwort bei 1 MB → `too_large`.
- **Wikimedia-JPEG (3072×2048):** 5 Varianten × webp/jpg, `original.jpg` ohne Make/GPS, zweiter Import `duplicate: true`, gleiche uid; Spitze 54 MB.
- **EXIF-Foto (Orientation 6, Make, GPS, per Hand gebaut):** 200×400, aufrecht (rote Ecke oben rechts), nach Neukodierung weder Make noch GPS noch Orientation.
- **PNG 300 px mit Alpha:** genau `300.webp` + `300.png`, Alpha in beiden erhalten (Ecke 127, Mitte 0; per Pixelwert, nicht im Browser).
- **Abgewiesen:** SVG, HTML, HTML als `.jpg` → `type_rejected`; 2 MB bei 1 MB → `too_large`; 60 MP → `too_many_pixels`.
- **Speicher 50 MP:** 8660×5773 → Spitze 246 MB im Prozess (< 400 MB), 2,2 s; `max_megapixels` bleibt 50.
- **Metadaten:** `mcp:test` auf ungenutztem Medium direkt; auf Live-Medium Vorschlag; `apply` als `mcp` → `owner_only`, als Inhaber angewandt; `filePath(uid, ../../.env)` → `not_found`.
- **Abweichungen:** (1) Plan nennt 8660×5774 = 50,005 MP, das lehnt die eigene Grenze ab — getestet mit 8660×5773. (2) `allow_private` hebt auch die Port-Prüfung auf (sonst wäre `php -S :9000` nicht erreichbar). (3) HTTPS lokal braucht `curl.cainfo` (Windows-PHP ohne CA-Bundle). (4) `peak_bytes` steckt im `ImageResult`, nicht im Medien-Datensatz.
