# STATE

**Aktiver Plan:** `docs/planning/2026-11-11_meilenstein-5-oauth/` (freigegeben 2026-11-11).
**Phase:** 5/6 — Zustimmungsseite und „Verbundene Apps“ (pending; Phase 1–4 abgeschlossen)
**Nächster Schritt:** Phase 5 umsetzen (`phase-5-oberflaeche.md`). Lokal (CIMD-Abruf braucht für PHP `-d curl.cainfo=<ca-bundle.crt>`, z. B. das von Git): MySQL über `mysqld` mit Kurzpfad-`--basedir` `C:\PROGRA~1\MySQL\MYSQLS~1.4` und `--datadir=C:\Users\sasch\mysql-dispatch\data`, PHP 8.5 unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`; Dev-Server: `php -S localhost:8080 -t public dev-router.php`. Strato: `deploy.cmd` (Werte in `deploy.env`, auch Anmeldung des Inhabers).
