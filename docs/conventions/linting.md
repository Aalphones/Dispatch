# Linting Conventions — DISPATCH

## Stack

| Layer | Choice |
|---|---|
| PHP | `php -l` je Datei (CI und `deploy.cmd`) |
| JS/CSS | keine Lint-Werkzeuge; Budgets und Regeln aus [`frontend.md`](frontend.md) |

## Policy

- Ein grüner Lauf ist der einzig akzeptable Zustand; vorhandene Befunde werden mitgefixt.
- CI: `.github/workflows/ci.yml` prüft jede PHP-Datei unter `app/`, `bridge/` und `bin/` mit PHP 8.5.
- Lokal: `deploy.cmd` prüft vor dem Hochladen, wenn `PHP_PATH` in `deploy.env` gesetzt ist.

## Critical Rules

1. **Rote CI wird vor dem nächsten Deploy behoben** — ein Syntaxfehler im Router legt alle PHP-Endpunkte lahm.
