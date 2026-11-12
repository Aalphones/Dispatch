# 024 — OAuth 2.1 für die Claude-Apps

**Status:** Akzeptiert (2026-11-12) · Allowlist `claude.ai` ersetzt durch [ADR-025](025-jede-app-per-cimd.md)

## Kontext

Die Claude-App (Android, Web, Desktop) und Claude Code verbinden sich mit einem Connector per Anmeldung, nicht per festem Token. Der Server muss dafür Discovery-Dokumente, eine Autorisierung mit Zustimmung des Inhabers und einen Token-Endpunkt bieten. Feste `dmcp_`-Tokens (ADR-017) bleiben daneben bestehen. Der Inhaber meldet sich im Blog per JWT an (ADR-020), die Redaktions-API kennt nur JWTs (ADR-021).

## Optionen

- **Client-Registrierung**: dynamische Registrierung (DCR) gegen Client-Metadatendokument (CIMD) gegen von Hand angelegte Clients.
- **Token-Form**: JWT gegen undurchsichtige Tokens in der Tabelle `tokens`.
- **Zustimmung**: serverseitig gerenderte Seite gegen statische Seite mit JS-Login.

## Entscheidung

- **CIMD statt DCR**, mit Allowlist `claude.ai` (exakter Host, `oauth.cimd_hosts`). Der AS-Metadatensatz meldet `client_id_metadata_document_supported` und hat keinen `registration_endpoint`. Das Dokument wird mit eigenem HttpClient (SSRF-Schutz, ADR-011) abgerufen, höchstens 64 KiB, einen Tag zwischengespeichert.
- **Undurchsichtige Tokens in `tokens`**: Access-Token `doa_` + 64 Hex (1 Stunde), Refresh-Token `dor_` + 64 Hex (60 Tage ab Ausgabe, nur bei `offline_access`), gespeichert als SHA-256 (`kind = oauth_access` / `oauth_refresh`, Spalten von ADR-017). Kein JWT: Widerruf und „Trennen“ wirken sofort, ohne Sperrliste.
- **Rotation mit Kulanz und Zweig-Regel**: Wie ADR-020. Ein abgelöstes Refresh-Token gilt 30 s weiter; wird es darin erneut vorgelegt, gibt es ein neues Paar und alle anderen offenen Refresh-Tokens der Familie werden widerrufen (es bleibt genau ein gültiger Zweig). Danach widerruft die Wiederverwendung die ganze Familie (Audit `oauth.reuse`).
- **Zustimmung als statische Seite** `anmelden/zustimmung/` mit JS-Login: Der Builder erzeugt sie wie die übrige Website (ADR-013/015), die Seite holt die Anfrage und entscheidet per Redaktions-API mit dem Login-JWT des Inhabers. `/dispatch/oauth/authorize` legt nur die Anfrage an und leitet weiter; Rückadresse und Client werden vorher geprüft, bei Fehlern wird nie an die Rückadresse weitergeleitet.
- **Rückfall-Client nur per Kommandozeile** (Entscheidung des Inhabers): `oauth-client:create|list|revoke`, auch `--remote` über das Deploy-Token. Keine Oberfläche, keine Registrierung.
- **Getrennte Welten**: `/dispatch/mcp` nimmt `dmcp_` und `doa_`; `/dispatch/api/*` nimmt nur JWTs, `doa_`/`dor_` → 401. Veröffentlichen hängt an keinem Scope (ADR-009).
- **Discovery** an der Wurzel (ADR-001): `/.well-known/oauth-protected-resource[/dispatch/mcp]` und `/.well-known/oauth-authorization-server`, ohne Datenbank, `Cache-Control: public, max-age=300`. Der 401 am MCP-Endpunkt trägt `resource_metadata` und `scope="blog:read blog:write"`, `error="invalid_token"` nur, wenn ein Token vorlag; Claude startet die Anmeldung nur bei einem echten 401.
- **Code im Ordner `app/src/OAuth/`**, Tabellen `oauth_clients` und `oauth_codes` (Migration 014); Laufzeiten und Allowlist im Konfigurationsblock `oauth`.

## Verworfen

- **DCR**: jede App-Verbindung legte einen Client an, der aufgeräumt werden müsste; CIMD braucht keinen Zustand außer dem Zwischenspeicher.
- **JWT als Access-Token**: nicht widerrufbar vor Ablauf.
- **Serverseitig gerenderte Zustimmungsseite**: bräuchte eine zweite Anmeldung per Cookie auf dem PHP-Pfad; das JWT des Redaktionsmodus ist schon da.
- **Oberfläche für Rückfall-Clients**: Notweg, der selten gebraucht wird.

## Konsequenzen

- Meilenstein 5 setzt dies in sechs Phasen um; Kontrakt (Endpunkte, Tabellen, Laufzeiten) steht in `docs/planning/…meilenstein-5-oauth/README.md`.
- Die Wurzel-`.htaccess` und der Dev-Router leiten die `.well-known`-Pfade schon an die Brücke.
- Ausgehende Abrufe fremder URLs (CIMD) laufen nur über den HTTP-Client mit SSRF-Schutz.
