# Commit Conventions — DISPATCH

## Logische Einheit pro Commit

Ein Commit pro abgeschlossener, prüfbarer Änderung — Code und betroffene Doku zusammen. Nie mitten in einer Phase „zur Sicherung" committen. Unabhängige Fixes, die nebenbei auffallen, bekommen einen eigenen Commit. Migrationen sind immer ihr eigener Commit.

## Format (Conventional Commits)

```
<type>(<scope>): <subject>

<body — das Warum>
```

- Types: `feat`, `fix`, `refactor`, `perf`, `docs`, `chore`, `build`, `style`, `ci`
- Scopes: `mcp`, `oauth`, `api`, `auth`, `domain`, `site`, `theme`, `redaktion`, `stats`, `db`, `deploy`, `docs`
- Subject: Imperativ, kleingeschrieben, kein Punkt, höchstens 72 Zeichen
- Body: das Warum; der Diff zeigt das Was

## Pushen

Explizit mit Branch-Namen: `git push origin main`.

## Critical Rules

1. **Nie `deploy.env`, `app/.env` oder Tokens committen** — sie enthalten Klartext-Zugangsdaten.
2. **Kein Commit, der die PHP-Syntaxprüfung bricht** — die CI würde rot, und `deploy.cmd` lädt nichts hoch.
