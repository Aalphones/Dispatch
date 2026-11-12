# 025 — Jede App per CIMD zulassen

**Status:** Akzeptiert (2026-11-12) · ersetzt in ADR-024 die Allowlist `claude.ai`

## Kontext

ADR-024 ließ per CIMD nur `client_id`-Adressen unter `claude.ai` zu. Der Inhaber will selbst entscheiden, welches Sprachmodell Artikel recherchiert und schreibt: ChatGPT (`chatgpt.com`), künftig Grok und lokal laufende Modelle. ChatGPT wurde mit „Unbekannte App abgewiesen“ abgelehnt.

## Optionen

- Allowlist im Dialog „Zugänge“ pflegbar machen.
- `chatgpt.com` fest in die Allowlist eintragen.
- Allowlist streichen, jede App zulassen.

## Entscheidung

- **Jede App zulassen** (Entscheidung des Inhabers). Die Konfiguration `oauth.cimd_hosts` entfällt. Weiter geprüft wird die Form der `client_id` (`https`, kein Port, keine Zugangsdaten, kein Fragment, Pfad nötig) und das Metadatendokument (`client_id` gleich, `redirect_uris` gültig, Rückadresse exakt bzw. Loopback).
- **Schranke ist die Zustimmung**: Ohne Anmeldung des Inhabers und seinen Klick auf „Erlauben“ bekommt keine App ein Token. Die Zustimmungsseite zeigt den Host der App.
- Apps ohne CIMD (lokale Modelle) nutzen ein festes MCP-Token (ADR-017). Der Rückfall-Client (`oauth-client:create`) kennt weiter nur die Rückadressen aus `oauth.manual_redirect_uris`.

## Verworfen

- **Pflegbare Allowlist**: zusätzliche Oberfläche ohne Schutzgewinn, weil die Zustimmung ohnehin den Inhaber verlangt.
- **Feste Einträge pro App**: jede neue App bräuchte einen Code-Eingriff und ein Deployment.

## Konsequenzen

- Jede Seite im Netz kann eine Zustimmungsanfrage auslösen. Der Inhaber muss vor „Erlauben“ den angezeigten Host prüfen.
- CIMD-Abrufe gehen jetzt an beliebige Hosts; der SSRF-Schutz des HttpClient (ADR-011) bleibt die Grenze.
- Texte der Zustimmungsseite sprechen von „der App“ statt von Claude.
