# Phase 10 — Strato, Abnahme, Doku

## Kontext

- `README.md` → „Finale Abnahme“, „Umgebung“.
- `docs/decisions/005-deploy-skript.md`, `AGENTS.md` (Critical Rules 1–4), `docs/archive/2026-11/2026-11-10_meilenstein-3-mcp-server/phase-6-strato-abschluss.md` (Muster für Deploy, Nachlauf und Abnahme auf Strato).
- `FINDINGS.md` dieses Plans (offene Punkte zuerst abarbeiten oder als Follow-up festhalten).

## AK der Phase

1. `deploy.env` trägt die drei neuen Werte (der Inhaber setzt sie; Passwort-Hash per `php bin/dispatch hash-password`); `deploy.cmd` läuft durch, Nachlauf migriert (012, 013) und baut neu.
2. Alle zwölf Punkte der finalen Abnahme im README auf Strato geprüft, Ergebnis je Punkt in `## Report-Back` (eine Zeile, wie geprüft).
3. Nach dem Deploy: `/card/`, `/scanner/` erreichbar, `/dispatch-app/.env` → 403, `Set-Cookie` des Logins trägt `Secure` und `Path=/dispatch/api/auth`.
4. Doku nachgezogen: `docs/PROJECT.md` (Meilenstein 4 „erledigt“ mit Plan-Pfad; Abweichungstabelle: CLI-Zeile „bis die Redaktion Kategorien anlegen kann“ prüfen — Kategorien legt die Redaktion in diesem Meilenstein **nicht** an, Zeile bleibt; neue Zeilen für Anmerkungen in `review_note`, Diff-Verfahren, Budgets und „Zugang anlegen ohne erneute Passworteingabe“), `AGENTS.md` (Lokal starten, Hinweis Login), `docs/routes.md`, `docs/models.md`, `docs/code-map.md`, `docs/glossary.md` (Redaktionsmodus, Merkzeichen, Vorschau-Link, Familie, Kulanzfenster), `docs/knowledge/GAPS.md`.
5. Plan archiviert nach `docs/archive/2026-11/2026-11-11_meilenstein-4-redaktion/`, Bottom-Sektionen der README gefüllt, `STATE.md` → nächster Schritt „Meilenstein 5 (OAuth 2.1) planen“.

## Checkliste

- [ ] `FINDINGS.md` sichten
- [ ] Deploy und Nachlauf
- [ ] Abnahme 1–12
- [ ] Doku
- [ ] Archivieren, `STATE.md`

## Report-Back
