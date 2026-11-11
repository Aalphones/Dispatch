# STATE

**Aktiver Plan:** Meilenstein 4 — Redaktions-API und Redaktionsmodus, `docs/planning/2026-11-11_meilenstein-4-redaktion/` (10 Phasen, Umsetzung im Verwalter angelegt).
**Phase:** 8/10 — Startseite, Artikel, Kategorien: Redaktion in der Seite (complete)
**Nächster Schritt:** Phase 9 (Dialoge). Lokal: MySQL über `mysqld` mit Kurzpfad-`--basedir` `C:\PROGRA~1\MySQL\MYSQLS~1.4` und `--datadir=C:\Users\sasch\mysql-dispatch\data` (läuft gerade), PHP 8.5 unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`; Dev-Server: `php -S localhost:8080 -t public dev-router.php` (läuft gerade keiner). Browser-Prüfung ohne Playwright: Chrome headless mit `--remote-debugging-port` und Node-WebSocket; Test-JWT per PHP (`Jwt::encode(Jwt::newAccessClaims())`), im Tab `import(dataset.redaktion).then(m => m.start(jwt))`.
