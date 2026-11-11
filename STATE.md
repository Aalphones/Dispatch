# STATE

**Aktiver Plan:** Meilenstein 4 — Redaktions-API und Redaktionsmodus, `docs/planning/2026-11-11_meilenstein-4-redaktion/` (10 Phasen, Umsetzung im Verwalter angelegt).
**Phase:** 6/10 — Redaktionsmodus: Login, Sitzung, Konto-Menü (complete)
**Nächster Schritt:** Phase 7 (Vorschau-Seite mit Freigabe und Kommentieren). Lokal: MySQL läuft über `mysqld` mit Kurzpfad-`--basedir`, PHP 8.5 unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`; Dev-Server: `php -S localhost:8080 -t public dev-router.php` (läuft gerade keiner). Browser-Prüfung ohne Playwright: Chrome headless mit `--remote-debugging-port` und Node-WebSocket (Skript war im Scratchpad).
