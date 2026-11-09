# 011 — Medien: HTTP-Client mit SSRF-Schutz und Bildvarianten

**Status:** Akzeptiert (2026-11-09)

## Kontext

Agenten übergeben Medien als URL; der Server lädt sie, speichert sie und liefert sie später öffentlich aus. Das ist Angriffsfläche in zwei Richtungen: der Abruf (Zugriff auf interne Dienste, Metadaten-Endpunkte) und die Datei selbst (aktive Inhalte, eingebettete Ortsdaten, Speicherbomben).

## Entscheidung

- **Ein Weg nach außen:** `Util/HttpClient`. Jede Anfrage und jedes Redirect-Ziel wird neu geprüft: Schema `http`/`https`, kein Benutzer/Passwort, Port nur 80/443, jede aufgelöste Adresse im globalen Bereich (`FILTER_FLAG_GLOBAL_RANGE`; IPv4-in-IPv6 wird vorher entpackt). Erst dann geht die Anfrage raus.
- **Festnageln per `CURLOPT_RESOLVE`:** cURL verbindet sich mit der geprüften Adresse, nicht mit einer zweiten DNS-Antwort. Das schließt DNS-Rebinding (erste Antwort öffentlich, zweite `127.0.0.1`). Zertifikat und SNI laufen weiter über den Hostnamen.
- **Redirects selbst folgen** (`FOLLOWLOCATION` aus), höchstens 4, damit jede Station die Prüfung durchläuft.
- **Größenlimit beim Empfang:** der Schreib-Callback bricht beim Überschreiten ab; es wird nie mehr als `max_bytes` gehalten.
- **`allow_private`** (nur lokal, `DISPATCH_ALLOW_PRIVATE_IPS=1`) schaltet Adress- **und** Port-Prüfung ab, damit sich der Weg gegen `php -S` testen lässt. Auf Strato wird die Variable nie gesetzt.
- **Typ per `finfo` auf den Bytes**, nie nach Dateiname oder Header. Erlaubt sind JPEG, PNG, WebP, GIF, MP4, WebM, MP3, M4A, OGG, WebM-Audio und PDF; SVG, HTML und XML werden abgewiesen.
- **Bilder werden immer neu kodiert** (`Util/ImageProcessor`): EXIF und GPS fallen weg, die Orientierung wird vorher eingerechnet. Das Original-Byte wird nie gespeichert. Pixelgrenze (`max_megapixels` 50) gilt **vor** dem Dekodieren. Je Breite entsteht `<w>.webp` plus JPEG (oder PNG bei Transparenz).
- **Ablage:** `<storage_dir>/media/<uid>/`, geschrieben in einen `.tmp-`-Ordner und dann per `rename` umbenannt. Dubletten werden über `sha256` der Eingangs-Bytes erkannt.
- **Öffentlich oder intern:** ein Medium gilt als öffentlich, sobald ein Live-Stand es nennt; Nicht-Inhaber ändern dessen Metadaten nur per Vorschlag (ADR-009).

## Gemessener Speicherbedarf

50-Megapixel-JPEG (8660 × 5773, 13,5 MB) mit allen fünf Varianten: Spitze **246 MB** im ganzen Prozess (CLI, `memory_limit` 512M), 2,2 s. Strato erlaubt 512M, die Grenze `max_megapixels` bleibt bei 50. Ein 3072 × 2048-Foto braucht 54 MB.

## Verworfen

- **`CURLOPT_FOLLOWLOCATION`:** cURL würde Ziele ohne unsere Prüfung ansteuern.
- **Nur Hostnamen prüfen, dann normal verbinden:** ein zweiter DNS-Abruf kann eine andere Adresse liefern.
- **Typ nach Endung oder `Content-Type`:** beides kommt vom Angreifer.

## Konsequenzen

- Lokal unter Windows hat PHP kein CA-Bundle; für HTTPS-Abrufe `curl.cainfo` in der `php.ini` auf eine `cacert.pem` setzen. Strato hat eines.
- Transparente WebP-Quellen werden mit PNG als Rückfall ausgeliefert, auch wenn sie keine Transparenz nutzen.
- Abnahme ist Handprüfung (ADR-004): zwölf SSRF-Fälle, Redirect-Kette, echte und synthetische Bilder, Speichermessung.
