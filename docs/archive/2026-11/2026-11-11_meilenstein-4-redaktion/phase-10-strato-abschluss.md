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

- [x] `FINDINGS.md` sichten (drei Einträge, reine API-Fakten, stehen in `routes.md`/ADR-021)
- [x] Deploy und Nachlauf
- [x] Abnahme 1–12 (3, 4, 7, 10, 11, 12 per curl; 1, 2, 5, 6, 8, 9 vom Inhaber im Browser auf Strato geprüft)
- [x] Doku
- [x] Archivieren, `STATE.md`

## Report-Back

Stand 2026-11-11:

- AK 1: `deploy.cmd` grün; Nachlauf `migrations: [012_login_attempt.sql, 013_stats_visitor_path.sql]`, Neubau 30 geschrieben; Rauchtest 7/7 ok.
- Abnahme 3: `overview` mit `dmcp_`-Token 401; `POST /dispatch/mcp` mit gültigem Access-JWT 401.
- Abnahme 4: JWTs mit `alg: none`, falschem Schlüssel, abgelaufen, falschem `aud` → je 401; gültiges JWT → 200 (`overview` `{"review":1,…}`).
- Abnahme 7: curl, 9 falsche Logins nach 1 früheren → 429 `Retry-After: 900` ab dem 11. Fehlversuch insgesamt.
- Abnahme 10: gültig signierter Link → 200 mit `post,html,form,has_live`; geänderte Signatur → 403.
- Abnahme 11: `site.js` 4266, `suche.js` 9433, `artikel.js` 6616, `zaehler.js` 2489, `vorschau.js` 1090, `style.css` 47545 — alle im Budget.
- Abnahme 12 / AK 3 teilweise: `/card/` 200, `/scanner/capture` 200, `/dispatch-app/.env` 403. `Set-Cookie` des Logins noch nicht gesehen (braucht erfolgreichen Login).
- Abnahme 8 teilweise: curl-`hit` ohne Browser zählt nichts (Statistik `views: 0`).
- Handprüfung 1, 2, 5, 6, 8, 9 und Cookie-Attribute: vom Inhaber auf Strato durchgesehen, „sieht in Ordnung aus“ (Rückmeldung 2026-11-11). Dabei gefundene Fehler sofort behoben und neu deployt: Bilder in der Vorschau (404), Fokus/Lightbox in der Vorschau, Leiste nicht bündig oben, „false“ im Konto-Knopf, Menü am Fensterrand statt unter dem Knopf.
