# Phase 4 — Kommandozeile für Rückfall-Clients

## Kontext

- `README.md` → „Kontrakt“: Endpunkte `oauth-clients`, Tokens und Codes (Rückfall-Client).
- ADR-017 (Weg `--remote` über das Deploy-Token).
- Code: `bin/dispatch` (Befehle `token:create|list|revoke` mit und ohne `--remote` — genau dieses Muster kopieren), `app/src/Api/TokenController.php` (Deploy-Weg), `app/src/Auth/ApiAuth.php` bzw. `app/src/Auth/DeployToken.php`, Phase 2: `ClientRegistry::createManual|listManual|revokeManual`.
- Fehlerklassen geprüft: keine einschlägig.

## AK der Phase

1. `php bin/dispatch oauth-client:create <name>` legt lokal einen Rückfall-Client an und gibt aus: Client-ID, Geheimnis (einmalig), Rückadresse und den Hinweis „In Claude: Connector hinzufügen → Erweiterte Einstellungen → OAuth Client ID und Client Secret eintragen. Adresse: <BASE>/dispatch/mcp“.
2. `oauth-client:list` zeigt ID, Name, Client-ID, angelegt, zuletzt benutzt; `oauth-client:revoke <id>` widerruft (inkl. aller Tokens dieses Clients).
3. Alle drei mit `--remote` gegen die Endpunkte `POST|GET /dispatch/api/oauth-clients`, `DELETE /dispatch/api/oauth-clients/{id}` (nur Deploy-Token, sonst 401; Akteur `deploy`). Ein JWT reicht dort **nicht**.
4. Probe: angelegter Client durchläuft lokal authorize → Zustimmung → Token-Tausch mit `client_secret`; ohne oder mit falschem Geheimnis → 401 `invalid_client`; nach `revoke` → authorize leitet auf `?fehler=unbekannte_app`.

## Checkliste

- [x] `app/src/Api/OAuthClientController.php`, Routen in `app/index.php`
- [x] `bin/dispatch`: drei Befehle, Hilfe-Text
- [x] Doku: `docs/routes.md`, `docs/code-map.md` (Zeile `bin/dispatch`), `AGENTS.md` → „Lokal starten“ (ein Satz zu `oauth-client:create --remote` als Notfall-Weg)

## Report-Back

Status: complete. Neu: `OAuthClientController`, `ApiAuth::deployOnly` (JWT reicht nicht), `callDeployEndpoint` mit Methode (GET/DELETE, 204). Lokal geprüft: create/list/revoke mit und ohne `--remote`, 401 ohne Token, Token-Tausch ohne/falsches Geheimnis → 401 `invalid_client`, richtiges → 200, nach revoke → `?fehler=unbekannte_app`. Nicht live geprüft: `--remote` gegen Strato.
