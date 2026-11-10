# STATE

**Aktiver Plan:** Meilenstein 2 — Builder und Theme (`docs/planning/2026-11-10_meilenstein-2-builder-theme/`)
**Phase:** 5/7 abgeschlossen (Seiten); als Nächstes Phase 6 — Skripte und Suchindex. Offen aus 4/5: Browser-Sicht (Layouts, Kontrast, JS-aus) steht noch aus.
**Grundlage:** Entwurf `docs/design/entwurf-website.html` (in fünf Runden abgestimmt, ADR-013), ADR-014 (Builder), ADR-015 (Budgets, Skriptmodule)
**Nächster Schritt:** Phase 6 starten; FINDINGS → Phase 6 lesen (Ids für `artikel.js`/`suche.js`). Lokal: PHP 8.5 liegt unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, `mysqld` nach Neustart manuell starten, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`. `php bin/dispatch build` schreibt nach `public/` (git-ignoriert).
