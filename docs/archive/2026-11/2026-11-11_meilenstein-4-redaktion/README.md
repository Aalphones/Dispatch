# Meilenstein 4 — Redaktions-API und Redaktionsmodus

Ziel: Der Inhaber meldet sich auf `https://quantum-canvas.de/` an und erledigt die Redaktion direkt in der Website — Entwürfe ansehen, mit Anmerkungen an einzelnen Stellen zurückgeben, freigeben (die Website baut sich danach neu), Änderungen an veröffentlichten Artikeln gegenüber der Live-Fassung prüfen, zurückziehen, Änderungsvorschläge übernehmen, Themen vom Handy einreichen, Interessen je Kategorie stellen, „Mehr davon / Weniger“ geben, Zugänge für Agenten anlegen und widerrufen, die Statistik lesen. Die Website zählt Besuche selbst, ohne Cookies und ohne gespeicherte IP-Adressen. Grundlage: `docs/PROJECT.md` → Meilenstein 4; Fachkonzept `docs/design/konzept-blog-engine.md` → „Dein Login“, „Freigabe: eingeloggt alles, öffentlich nur Freigegebenes“, „Zugriffsstatistik“, „Redaktionsmodus im Frontend“; verbindlicher Entwurf `docs/design/entwurf-website.html` (ADR-013); ADR-009, 012, 015, 017, 019.

Nicht in diesem Meilenstein: OAuth, verbundene Claude-Apps und OAuth-Clients im Dialog „Zugänge“, Zustimmungsseite (alle Meilenstein 5); TOTP; Dialoge für Dokumente, Einstellungen, Medien und Protokoll; `publish_mode = auto`.

## Phasen

| # | Phase | Rating | Status |
|---|---|---|---|
| 1 | [Anmeldung des Inhabers, Router, API-Anmeldung](phase-1-anmeldung.md) | heikel | complete |
| 2 | [API: Übersicht, Vorschau, Freigabe, Vorschläge, Neubau](phase-2-api-freigabe.md) | heikel | complete |
| 3 | [Änderungsansicht gegenüber der Live-Fassung](phase-3-aenderungsansicht.md) | heikel | complete |
| 4 | [API: Queue, Zugänge, Kategorien, Reaktionen, Statistik](phase-4-api-rest.md) | standard | complete |
| 5 | [Zähl-Aufruf der Statistik](phase-5-zaehlen.md) | heikel | complete |
| 6 | [Redaktionsmodus: Login, Sitzung, Konto-Menü](phase-6-redaktion-grundgeruest.md) | heikel | complete |
| 7 | [Vorschau-Seite mit Freigabe und Kommentieren](phase-7-vorschau-freigabe.md) | standard | complete |
| 8 | [Startseite, Artikel, Kategorien: Redaktion in der Seite](phase-8-redaktion-in-seiten.md) | standard | complete |
| 9 | [Dialoge: Thema einreichen, Interessen, Statistik, Zugänge](phase-9-dialoge.md) | standard | complete |
| 10 | [Strato, Abnahme, Doku](phase-10-strato-abschluss.md) | standard | complete |

Reihenfolge ist Pflicht. ADRs dieses Meilensteins (Nummern reserviert): **020** Anmeldung des Inhabers (Phase 1), **021** Redaktions-API (Phase 2), **022** Zähl-Aufruf (Phase 5), **023** Redaktionsmodus im Browser (Phase 6). Phase 3 ergänzt ADR-021 um das Diff-Verfahren.

Lokale Umgebung: PHP 8.5 unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`. 🟡 MySQL ließ sich zuletzt nach einem Neustart nicht starten (STATE.md). Phase 1 beginnt deshalb damit, MySQL lokal wieder lauffähig zu machen; gelingt das nicht in einer Runde → anhalten und den Inhaber fragen.

## Kontrakt (gilt für alle Phasen)

### Umgebung

| Variable | Inhalt | Wo |
|---|---|---|
| `DISPATCH_OWNER_USER` | Benutzername des Inhabers | `deploy.env` → `.env`; `dev.env.example` |
| `DISPATCH_OWNER_PASSWORD_HASH` | Ausgabe von `php bin/dispatch hash-password` (bcrypt) | wie oben |
| `DISPATCH_JWT_SECRET` | mindestens 64 Hex-Zeichen (`bin2hex(random_bytes(32))`) | wie oben |
| `DISPATCH_INSECURE_COOKIES` | `1` nur lokal (Refresh-Cookie ohne `Secure` auf `http://localhost`) | nur `dev.env.example` |

Fehlt eine der drei ersten Variablen, antworten alle Endpunkte unter `/dispatch/api/auth/` mit 503 `{"error":"not_configured", …}`.

### Dateien und Namen

| Was | Datei |
|---|---|
| Migrationen | `app/migrations/012_login_attempt.sql`, `app/migrations/013_stats_visitor_path.sql` |
| Router mit Platzhaltern | `app/src/Http/Router.php` (`{id}` = `[0-9]+`, `{slug}` = `[a-z0-9-]+`) |
| JWT | `app/src/Auth/Jwt.php` |
| Anmeldung, Refresh, Abmelden | `app/src/Auth/OwnerSession.php` |
| Login-Bremse | `app/src/Auth/LoginThrottle.php` |
| Anmeldung an der API | `app/src/Auth/ApiAuth.php` (`owner(Request): Actor`, `ownerOrDeploy(Request): Actor`) |
| Signierte Vorschau-Links | `app/src/Auth/PreviewLink.php` |
| Controller | `app/src/Api/<X>Controller.php` mit `X` ∈ `Auth, Overview, Post, Change, Queue, Token (vorhanden, erweitert), Category (vorhanden, erweitert), Reaction, Stats, Hit, Preview` |
| Zählen | `app/src/Domain/StatsRecorder.php` (schreibt), `StatsRepository` (erweitert) |
| Änderungsansicht | `app/src/Util/Diff.php` |
| Öffentliche Skripte | `app/theme/site.js` (erweitert), `app/theme/suche.js` (erweitert), neu `app/theme/zaehler.js`, `app/theme/vorschau.js` |
| Redaktionsmodus | Ordner `app/theme/redaktion/`: `start.js`, `sitzung.js`, `api.js`, `ui.js`, `konto.js`, `post.js`, `kommentar.js`, `change.js`, `queue.js`, `category.js`, `reaction.js`, `stats.js`, `token.js`, `redaktion.css` |
| Vorschau-Hülle | `app/templates/preview.php`, gebaut von `Builder::collectPreview()` nach `vorschau/index.html` |
| Routen-Doku | `docs/routes.md` (neu in Phase 1, jede Phase trägt ihre Routen nach) |

### Anmeldung (ADR-020)

- **Login** `POST /dispatch/api/auth/login`, Body JSON `{"user","password"}`. Prüfung: `hash_equals(DISPATCH_OWNER_USER, user)` und `password_verify`. Erfolg → 200 `{"access_token","expires_in":900,"user"}` plus Refresh-Cookie. Fehlschlag → 401 `invalid_credentials` (gleiche Meldung für falschen Namen und falsches Passwort).
- **Login-Bremse:** Tabelle `login_attempts` (`id` BIGINT PK AI, `ip_hash` CHAR(64), `attempted_at` DATETIME, Index (`ip_hash`, `attempted_at`)). `ip_hash = hash_hmac('sha256', REMOTE_ADDR, DISPATCH_JWT_SECRET)`. Jeder Fehlschlag schreibt eine Zeile. Vor der Prüfung: ≥ 10 Zeilen dieses Hashes in den letzten 15 Minuten → 429 `too_many_attempts`, Header `Retry-After: 900`. Zeilen älter als 1 Tag löscht jeder Login-Aufruf. Keine IP-Adresse im Klartext in der Datenbank.
- **Access-JWT:** HS256, Header exakt `{"alg":"HS256","typ":"JWT"}`; Claims `iss` = `DISPATCH_BASE_URL`, `aud` = `DISPATCH_BASE_URL` + `/dispatch/api`, `sub` = `owner`, `iat`, `exp` = `iat + 900`, `jti` = 32 Hex. Prüfung: drei Teile, Header-`alg` muss `HS256` sein (sonst ungültig, nie übernehmen), Signatur mit `hash_equals`, `exp > time()`, `iss`/`aud`/`sub` exakt. Base64url ohne Auffüllung.
- **Refresh-Token:** Klartext `dref_` + 64 Hex, gespeichert als `tokens`-Zeile `kind = 'owner_refresh'`, `token_hash` = SHA-256, `family` = 32 Hex (eine je Login), `expires_at` = jetzt + 30 Tage, `name` = `owner`, `scope` = `''`. Cookie `dispatch_refresh`, `Path=/dispatch/api/auth`, `HttpOnly`, `Secure` (außer `DISPATCH_INSECURE_COOKIES=1`), `SameSite=Strict`, `Max-Age=2592000`.
- **Refresh** `POST /dispatch/api/auth/refresh`: verlangt Header `X-Dispatch: 1` und einen `Origin`, der exakt dem Ursprung von `DISPATCH_BASE_URL` entspricht (sonst 403 `forbidden_origin`). Token-Zustände:
  - `revoked = 0`, nicht abgelaufen → **rotieren**: altes Token `revoked = 1`, `expires_at = jetzt + 30 s`; neues Token derselben Familie, neues Cookie, neues Access-JWT.
  - `revoked = 1`, `expires_at > jetzt` (Kulanzfenster) → neues Access-JWT, **kein** neues Cookie (der parallele Tab hat es schon gesetzt).
  - `revoked = 1`, `expires_at ≤ jetzt` → **Wiederverwendung**: alle Tokens der Familie `revoked = 1`, `expires_at = jetzt`; 401 `session_revoked`; Audit `auth.reuse`.
  - unbekannt oder `revoked = 0` und abgelaufen → 401 `session_expired`.
- **Abmelden** `POST /dispatch/api/auth/logout` (Cookie, `X-Dispatch: 1`): Token `revoked = 1`, `expires_at = jetzt`, Cookie löschen; immer 204. `POST /dispatch/api/auth/logout-all` (JWT): alle `owner_refresh`-Tokens widerrufen, Cookie löschen, 204.
- **Keine erneute Passworteingabe** beim Anlegen eines Zugangs (Entscheidung des Inhabers, weicht vom Fachkonzept ab): `POST tokens` genügt ein gültiges Access-JWT.
- **Getrennte Welten:** `/dispatch/api/*` nimmt nur JWTs (und das Deploy-Token, wo unten genannt); ein `dmcp_`-Token ergibt 401. `/dispatch/mcp` bleibt wie in Meilenstein 3 (`dmcp_` only).
- Audit: `auth.login`, `auth.login_failed` (ohne Namen), `auth.logout`, `auth.logout_all`, `auth.reuse`. Akteur `owner`.

### Endpunkte

Alle unter `/dispatch/api/`, Antworten JSON (`Response::json`), Fehler `{"error","message"}` mit passendem Status. „JWT“ = `ApiAuth::owner`, Akteur `Actor::owner()`. „JWT/Deploy“ = `ApiAuth::ownerOrDeploy` (JWT → `owner`, Deploy-Token → `deploy`).

| Methode | Pfad | Auth | Phase | Antwort / Wirkung |
|---|---|---|---|---|
| POST | `auth/login` | — | 1 | s. o. |
| POST | `auth/refresh` | Cookie | 1 | s. o. |
| POST | `auth/logout` | Cookie | 1 | 204 |
| POST | `auth/logout-all` | JWT | 1 | 204 |
| GET | `overview` | JWT | 2 | `{"review":n,"returned":n,"changes":n,"queue_open":n}` |
| GET | `posts?status=review,draft` | JWT | 2 | `{"posts":[{id,slug,title,status,revision,published_revision,updated_at,word_count,sources,review_note,form:{errors,warnings}}]}` |
| GET | `posts/{id}/preview` | JWT | 2 | `{"post":{…},"html":<Inhalt von <main>>,"form":{"errors":[…],"warnings":[…]},"has_live":bool,"stats":…|null}` |
| GET | `preview?id=&exp=&sig=` | Signatur | 2 | wie oben ohne `stats`; falsche oder abgelaufene Signatur → 403 `invalid_signature` |
| GET | `posts/by-slug/{slug}` | JWT | 2 | `{id,status,revision,published_revision,has_new_version,reaction,stats}` |
| POST | `posts/{id}/approve` | JWT | 2 | Freigabe + Neubau → `{"post":…,"rebuilt":bool,"rebuild_error":string|null}` |
| POST | `posts/{id}/return` | JWT | 2 | Body `{"note"}` (1–20 000 Zeichen) |
| POST | `posts/{id}/unpublish` | JWT | 2 | + Neubau |
| DELETE | `posts/{id}` | JWT | 2 | `discard`; 204 |
| GET | `changes` | JWT | 2 | `{"changes":[{id,entity,key,payload,before,created_at,actor}]}` |
| POST | `changes/{id}/apply` | JWT | 2 | + Neubau |
| POST | `changes/{id}/reject` | JWT | 2 | 204 |
| GET | `posts/{id}/diff` | JWT | 3 | `{"html","sources":{"added":[],"removed":[]},"media":{"added":[],"removed":[]},"summary":{"blocks_changed":n,"sources_added":n,"sources_removed":n,"media_changed":n}}` |
| POST | `queue` | JWT | 4 | multipart, s. Phase 4 → 201 `{"item":…}` |
| GET | `tokens` | JWT | 4 | `{"tokens":[{id,name,scopes,expires_at,last_used_at,created_at}]}` (nur `kind = mcp`, nicht widerrufen) |
| POST | `tokens` | JWT/Deploy | 4 | `{"token":<Klartext>,"name","scopes","expires_at","command":<claude mcp add …>}` |
| DELETE | `tokens/{id}` | JWT/Deploy | 4 | 204 |
| GET | `categories` | JWT | 4 | Kategorien mit `interest`, `keywords`, `avoid`, `signals:{more,less}`, `suggestion:int|null` |
| PATCH | `categories/{id}` | JWT | 4 | Body mit beliebigen von `interest` (0–5), `keywords`, `avoid` (Listen von Strings) |
| POST | `categories` | Deploy (unverändert) | — | Meilenstein 3 |
| POST | `posts/{id}/reaction` | JWT | 4 | Body `{"reaction":"more"|"less"|null,"note"?}` → 204 |
| GET | `reactions` | JWT | 4 | `{"reactions":{"<post_id>":"more"|"less"}}` |
| GET | `stats?days=14` | JWT | 4 | s. Phase 4 |
| GET | `stats/posts/{id}?days=30` | JWT | 4 | `{"views","read_full_pct","avg_seconds","daily":[{day,views}]}` |
| POST | `hit` | — | 5 | immer 204 |

### Neubau

`approve`, `unpublish`, `changes/{id}/apply` rufen nach erfolgreicher Transaktion `(new Builder($config, $pdo))->build()` auf. Scheitert der Bau, bleibt die fachliche Aktion bestehen; die Antwort trägt `rebuilt: false` und `rebuild_error` (Meldung der Ausnahme), der Fehler steht im Log. `build_busy` (paralleler Bau) zählt als Fehler mit dieser Meldung.

### Signierte Vorschau-Links

`PreviewLink::url(int $id): string` → `DISPATCH_BASE_URL/vorschau/?id=<id>&exp=<unix>&sig=<hex>` mit `exp = time() + 7 * 86400`, `sig = hash_hmac('sha256', "preview:<id>:<exp>", DISPATCH_JWT_SECRET)`. Prüfung mit `hash_equals` und `exp > time()`. `submit_post` (MCP) liefert zusätzlich `preview_url`.

### Zählen (ADR-022)

Siehe Phase 5; dort steht das Beacon-Format. Kurz: `POST /dispatch/api/hit` mit JSON-Text, Arten `view`, `read`, `out`, `search`; jedes Ereignis wird genau einmal und je Pfad gezählt; Suchen unter Pfad `/suche/` mit `extra.hits`.

### Redaktionsmodus im Browser (ADR-023)

- Der Builder kopiert `app/theme/redaktion/` unverändert nach `assets/redaktion-<sha1-8>/` (Hash über alle Dateien, sortiert nach Name, Inhalt aneinandergehängt). Relative `import`-Pfade zwischen den Modulen funktionieren damit, der Ordnername bricht den Cache. Das `<html>`-Tag trägt `data-redaktion="/assets/redaktion-<hash>/start.js"`.
- `site.js` öffnet den Login-Dialog (Knopf „Anmelden“ im Kopf, `/#anmelden`), meldet an, setzt das Merkzeichen `localStorage['dispatch.angemeldet'] = '1'` und lädt `start.js` per `import()`. Steht das Merkzeichen beim Seitenaufruf, lädt `site.js` `start.js` sofort. Ohne Merkzeichen kein PHP-Aufruf außer dem Zähl-Aufruf.
- `start.js` exportiert nichts; es lädt `redaktion.css` (ein `<link>` im `<head>`), stellt die Sitzung her (`sitzung.js`) und ruft je Seite (`document.body.dataset.seite`) die zuständigen Module.
- Das Access-JWT liegt nur in einer Modul-Variablen von `sitzung.js`. Tabs teilen es über `BroadcastChannel('dispatch-sitzung')`: Nachricht `{"typ":"frage"}` → offene Tabs antworten `{"typ":"token","token","exp"}` binnen 150 ms; sonst `POST auth/refresh`. Refresh-Anfragen eines Tabs laufen nie parallel (ein gemeinsames Promise).
- CSP bleibt: kein Inline-Skript, kein `style="…"` im erzeugten HTML; Höhen und Farben setzt das Skript über `element.style.…` (CSSOM, von `style-src 'self'` nicht betroffen).
- Daten-Attribute, die der Builder dafür liefert (Phase 6): `body[data-seite]` (vorhanden), `body[data-post-id]` auf Artikelseiten, `article.karte[data-post-id]` auf Karten, `[data-category-id]` an Kategorie-Karten und Kategorie-Links.

### Byte-Budgets (ADR-023, ersetzt die Zahlen aus ADR-015 für die genannten Dateien)

`site.js` ≤ 5 000 · `suche.js` ≤ 9 500 · `artikel.js` ≤ 7 000 (unverändert) · `zaehler.js` ≤ 2 500 · `vorschau.js` ≤ 3 000 · `style.css` ≤ 48 000. Redaktionsmodule (nur für den Inhaber) haben kein Byte-Budget.

## Finale Abnahme (gesamter Meilenstein)

Handprüfung (ADR-004), lokal und auf Strato:

1. Jede Funktion ist auf dem Handy (390 px breit) bedienbar: Anmelden, Freigeben, Zurückgeben mit Anmerkungen an Stellen, Verwerfen, Änderungen ansehen und freigeben, Zurückziehen, Vorschlag übernehmen und ablehnen, Thema einreichen mit Kamera-Foto, Interessen, Mehr davon / Weniger, Statistik, Zugang anlegen und widerrufen, Überall abmelden.
2. Besucher ohne Merkzeichen lösen auf keiner Seitenart einen PHP-Aufruf aus außer `POST /dispatch/api/hit` (Netzwerk-Tab der Entwicklerwerkzeuge).
3. `/dispatch/api/overview` mit einem `dmcp_`-Token → 401; `/dispatch/mcp` mit einem Access-JWT → 401.
4. Manipulierte JWTs werden abgelehnt: `alg: none`, falscher Schlüssel, abgelaufen, falsches `aud`.
5. Fünf Tabs gleichzeitig neu laden führt nicht zur Abmeldung.
6. Ein Refresh-Token, das nach mehr als 30 s erneut vorgelegt wird, widerruft die Familie (nächster Refresh jedes Tabs → abgemeldet).
7. Der 11. Fehlversuch binnen 15 Minuten ergibt 429.
8. Die Statistik zählt weder einen Aufruf mit `curl` noch den angemeldeten Inhaber; in keiner Tabelle steht eine IP-Adresse.
9. Freigeben macht den Artikel öffentlich und baut die Website neu; ein verknüpfter Queue-Auftrag ist danach `done`.
10. Der Vorschau-Link aus `submit_post` öffnet den Entwurf ohne Anmeldung nur lesend; eine geänderte Signatur ergibt 403.
11. Byte-Budgets eingehalten.
12. `/card/` und `/scanner/` unverändert erreichbar, `/dispatch-app/.env` antwortet 403.

## Summary

Der Inhaber meldet sich auf quantum-canvas.de an (Zugangsdaten per Umgebungswerte, Login mit Bremse, kurzlebiges JWT, rotierendes Refresh-Cookie) und erledigt die Redaktion in der Website: Freigabe-Leiste mit Anmerkungen an Stellen, Änderungsansicht gegenüber der Live-Fassung, Zurückziehen, Vorschläge, Thema einreichen, Interessen, „Mehr davon / Weniger“, Statistik, Zugänge. Die Website zählt Besuche selbst ohne Cookies und ohne IP-Adressen. ADR-020 bis 023. Abnahme auf Strato: 12 von 12 Punkten.

## Files touched

`app/src/Auth`, `app/src/Api`, `app/src/Http/Router.php`, `app/src/Domain`, `app/src/Util/Diff.php`, `app/src/Site`, `app/theme/` (inkl. `redaktion/`), `app/templates`, `app/migrations/012–013`, `docs/`, `README.md`, `deploy.env.example`, `deploy.cmd`.

## Commits

7258280, de28ee3, 1a63829, 4a8c18d, cce6761, 6c2379d, 3c6b948, 57ec422, c7f6f8a; Abschluss in Phase 10 (Vorschau-Bilder, Vorschau-Skripte, Konto-Menü, Doku, Archiv).

## Deviations from plan

- Phase 10 fand vier Lücken erst in der Handprüfung auf Strato: Bilder unveröffentlichter Artikel in der Vorschau (neuer geschützter Endpunkt `preview-media`), `artikel.js` fehlte in der Vorschau (Fokus, Lightbox, Inhaltsverzeichnis-Marker), Freigabe-Leiste nicht bündig oben, Konto-Knopf zeigte „false“ und das Menü hing am Fensterrand.
- Die Login-Bremsen-Probe sperrte die IP des Inhabers 15 Minuten lang (Bremse künftig zuletzt prüfen).

## Follow-ups

- Meilenstein 5 (OAuth 2.1) planen.
- Der Testartikel aus Meilenstein 3 hat weder Zwischenüberschriften noch „Kurz gesagt“; Kommentieren an diesen Bereichen ist nicht mit echtem Inhalt geprüft.
- Die Redaktion legt keine Kategorien an (`category:create --remote` bleibt).
- Mehr-Tab-Verhalten und Handy-Bedienung sind nicht automatisiert prüfbar (siehe GAPS).
