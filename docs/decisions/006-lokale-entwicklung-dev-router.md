# 006 — Lokale Entwicklung: Dev-Router und eigene Env-Datei

**Status:** Akzeptiert (2026-11-09)

## Kontext

`deploy.cmd` lädt `app/` nach `dispatch-app/` und überschreibt dabei `app/.env` mit den Server-Werten (ADR-005). Eine lokal genutzte `app/.env` würde beim nächsten Deploy auf dem Server landen oder dort die echten Werte ersetzen. Lokal läuft der Code über `php -S`, das weder Brücke noch Sperre kennt.

## Optionen

- Eine gemeinsame `app/.env` für lokal und Server — Deploy und Entwicklung stören sich gegenseitig.
- `DISPATCH_ENV_FILE` und `app/.env.local` — lokale Werte liegen getrennt, der Server liest weiter `app/.env`.

## Entscheidung

Der Bootstrap liest die Datei aus der Umgebungsvariable `DISPATCH_ENV_FILE`, sonst `app/.env`. Lokal setzt ausschließlich `dev-router.php` die Variable auf `app/.env.local`. Der Dev-Router bildet außerdem die Brücke (`/dispatch/`, `/.well-known/oauth-*`) und die Sperre (`/dispatch-app/` → 403) nach. Vorlage: `dev.env.example`.

## Konsequenzen

- Der Dev-Router ist der einzige Setzer von `DISPATCH_ENV_FILE`; auf dem Server ist sie nie gesetzt.
- `app/.env.local` steht in `.gitignore`, `dev-router.php` wird nicht hochgeladen.
- Der Dev-Router belegt die Sperre nur nachgebildet; der echte Nachweis (403 auf dem Server) bleibt der Rauchtest nach dem Deploy.
