# STATE

**Aktiver Plan:** Meilenstein 2 — Builder und Theme (`docs/planning/2026-11-10_meilenstein-2-builder-theme/`)
**Phase:** 6/7 abgeschlossen (Skripte und Suchindex); als Nächstes Phase 7. Offen aus 4–6: Browser-Sicht (Layouts, Kontrast, JS-aus, interaktive Skripte) steht noch aus.
**Grundlage:** Entwurf `docs/design/entwurf-website.html` (in fünf Runden abgestimmt, ADR-013), ADR-014 (Builder), ADR-015 (Budgets, Skriptmodule)
**Nächster Schritt:** Phase 7 starten; FINDINGS → Phase 7 lesen. Lokal: PHP 8.5 liegt unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, `mysqld` nach Neustart manuell starten, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`. `php bin/dispatch build` schreibt nach `public/` (git-ignoriert).
