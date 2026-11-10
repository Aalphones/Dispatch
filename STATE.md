# STATE

**Aktiver Plan:** Meilenstein 2 — Builder und Theme (`docs/planning/2026-11-10_meilenstein-2-builder-theme/`)
**Phase:** 3/7 abgeschlossen (Inhalt aufbereiten: ContentRenderer, Markdown); als Nächstes Phase 4 — Theme-CSS nach Entwurf
**Grundlage:** Entwurf `docs/design/entwurf-website.html` (in fünf Runden abgestimmt, ADR-013), ADR-014 (Builder), ADR-015 (Budgets, Skriptmodule)
**Nächster Schritt:** Phase 4 starten; Prüfung der Inhaltsaufbereitung: `php bin/demo/pruefe-inhalt.php`. Lokal: PHP 8.5 liegt unter `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_*`, `mysqld` nach Neustart manuell starten, Skripte mit `DISPATCH_ENV_FILE=app/.env.local`. `php bin/dispatch build` schreibt nach `public/` (git-ignoriert).
