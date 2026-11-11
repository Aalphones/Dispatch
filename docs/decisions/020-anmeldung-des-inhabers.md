# 020 — Anmeldung des Inhabers

**Status:** Akzeptiert (2026-11-11)

## Kontext

Der Inhaber bedient die Redaktion direkt in der Website (Meilenstein 4). Dafür braucht die API eine Anmeldung, die von den MCP-Tokens der Agenten getrennt ist (ADR-017) und ohne Composer-Bibliothek auskommt.

## Entscheidung

- **Zugangsdaten stehen in der `.env`:** `DISPATCH_OWNER_USER`, `DISPATCH_OWNER_PASSWORD_HASH` (bcrypt, `php bin/dispatch hash-password`), `DISPATCH_JWT_SECRET` (mindestens 64 Zeichen). Fehlt eine, antwortet jeder Endpunkt unter `/dispatch/api/auth/` mit 503 `not_configured`.
- **JWT selbst gebaut, Algorithmus fest:** HS256, Header exakt `{"alg":"HS256","typ":"JWT"}`. Beim Prüfen zählt nie das `alg` des Tokens; `iss`, `aud` (`<Basis-URL>/dispatch/api`), `sub` = `owner` und `exp` müssen stimmen. Laufzeit 15 Minuten.
- **Refresh über die Tabelle `tokens`, ohne neue Spalten:** Art `owner_refresh`, Klartext `dref_` + 64 Hex im Cookie `dispatch_refresh` (`HttpOnly`, `Secure`, `SameSite=Strict`, Pfad `/dispatch/api/auth`), gespeichert nur als SHA-256. Jede Anmeldung eröffnet eine Familie. Beim Refresh wird rotiert; das alte Token gilt als Kulanz noch 30 Sekunden (`revoked = 1` mit `expires_at` in der Zukunft), damit parallele Tabs nicht abmelden. Wird ein widerrufenes Token danach noch einmal vorgelegt, ist die ganze Familie widerrufen (`session_revoked`, Audit `auth.reuse`). Das gilt auch für ein Token, das nach dem Abmelden vorgelegt wird.
- **Login-Bremse mit Hash statt IP:** Tabelle `login_attempts` (`ip_hash` = HMAC-SHA-256 der `REMOTE_ADDR` mit dem JWT-Schlüssel). Ab 10 Fehlversuchen in 15 Minuten antwortet der Login mit 429 und `Retry-After: 900`, auch bei richtigem Passwort. Zeilen älter als ein Tag löscht jeder Login-Aufruf. Es steht nie eine IP-Adresse in der Datenbank; Weiterleitungs-Header zählen nicht (Strato hat keinen eigenen Proxy davor).
- **Eigene Anfrage erkennbar:** Refresh verlangt `X-Dispatch: 1` und einen `Origin`, der dem Ursprung der Basis-URL entspricht; Abmelden verlangt `X-Dispatch: 1`.
- **Keine erneute Passworteingabe** vor dem Anlegen eines Zugangs (Entscheidung des Inhabers, weicht vom Fachkonzept ab). Schutz gegen ein abgegriffenes JWT sind die strenge CSP und die kurze Laufzeit.
- **Getrennte Welten:** `/dispatch/api/*` nimmt nur JWTs (und wo vorgesehen das Deploy-Token); ein `dmcp_`-Token ergibt 401. `/dispatch/mcp` bleibt bei `dmcp_` allein.

## Verworfen

- **PHP-Sitzungen:** ein Cookie an jeder API-Anfrage, damit CSRF-Fläche auf jeder Route.
- **Eigene Spalte `replaced_at`** für das Kulanzfenster: eine Migration für einen Sonderfall; `revoked` mit `expires_at` in der Zukunft leistet dasselbe.

## Konsequenzen

- Das Access-JWT lebt nur im Speicher des Browsers; nach dem Neuladen holt die Seite über das Cookie ein neues.
- Wer den Schlüssel `DISPATCH_JWT_SECRET` wechselt, meldet alle Sitzungen ab und leert damit die Zuordnung der Login-Bremse (neue Hashes).
