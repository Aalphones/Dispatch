# 017 — MCP-Tokens, Scopes und Wege zum Anlegen

**Status:** Akzeptiert (2026-11-10)

## Kontext

Agenten in Claude Code sprechen den MCP-Server mit einem festen Token an. Der Inhaber braucht einen Weg, Tokens lokal und auf Strato anzulegen, zu listen und zu widerrufen — bevor Meilenstein 4 den Dialog „Zugänge“ bringt.

## Entscheidung

- **Format:** `dmcp_` + 64 Hex-Zeichen (`bin2hex(random_bytes(32))`), 69 Zeichen. Der Klartext wird genau einmal ausgegeben; gespeichert wird nur `hash('sha256', Klartext)`. Bei 256 Bit Zufall genügt ein schneller Hash, ein Passwort-Hash wäre nur Rechenzeit.
- **Name:** `^[a-z0-9][a-z0-9-]{0,39}$`, eindeutig unter den nicht widerrufenen `mcp`-Tokens. Der Akteur im Protokoll heißt `mcp:<name>`.
- **Gültig:** `kind = 'mcp'`, nicht widerrufen, nicht abgelaufen — geprüft in der Abfrage mit `UTC_TIMESTAMP()`. Jede erfolgreiche Anmeldung setzt `last_used_at`. Werte ohne Präfix `dmcp_` oder mit falscher Länge werden gar nicht erst nachgeschlagen (so fallen auch JWTs durch).
- **Scopes:** `blog:read`, `blog:write`, `blog:admin`; keiner schließt einen anderen ein. Standard der CLI: `blog:read blog:write`. Veröffentlichen hängt an keinem Scope (ADR-009).
- **Wege:** lokal `php bin/dispatch token:create|token:list|token:revoke`. Auf Strato dieselben Befehle mit `--remote`: die CLI liest `PUBLIC_BASE_URL` und `DISPATCH_DEPLOY_TOKEN` aus `deploy.env` und ruft `POST /dispatch/api/tokens` auf. Der Endpunkt ist mit dem Deploy-Token geschützt, der Akteur im Protokoll heißt `deploy`. Bis Meilenstein 4 den Dialog „Zugänge“ bringt, ist das der einzige Weg auf dem Server.
- Die Tabelle `tokens` trägt schon alle Spalten für OAuth und Inhaber-Refresh, damit Meilenstein 4 und 5 keine Migration für Spalten brauchen.

## Betrachtete Alternativen

- **Strato-Datenbank direkt von außen:** vom Entwicklungsrechner nicht erreichbar bzw. ungeprüft; die Zugangsdaten müssten zusätzlich lokal liegen.
- **Auf Meilenstein 4 warten:** die Abnahme von Meilenstein 3 gegen Strato wäre blockiert.

## Konsequenzen

- Wer den Deploy-Token hat, kann MCP-Tokens anlegen — das ist ohnehin der Schlüssel zum Server-Code. Der Token-Endpunkt verlangt ihn wie der Deploy-Endpunkt.
- Ein verlorener Klartext ist nicht wiederherstellbar: neues Token anlegen, altes widerrufen.
