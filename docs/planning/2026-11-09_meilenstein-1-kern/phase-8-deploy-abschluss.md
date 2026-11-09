# Phase 8 — Deploy mit Startinhalten, Abnahme auf Strato, Doku

**Rating:** standard · **Status:** pending

## Kontext (vor dem ersten Edit lesen)

- README dieses Plans → „Finale Abnahme"
- `app/src/Api/DeployController.php`, `deploy.cmd` (prüft nur den Status 200 des Deploy-Endpunkts), `docs/decisions/005-deploy-skript.md`, `007-diagnose-route-und-platzhalter-builder.md`
- Phase 2 → `Seeder`
- `docs/conventions/dod.md` (Nachbarn nach jedem Deploy)
- Fehlerklassen: `~/.claude/knowledge/topics/strato-shared-hosting.md` → Zeile „Migrations-Runner als CLI-Skript" (Startinhalte deshalb im Deploy-Endpunkt, nicht per CLI)

## Entscheidungen (fest)

- **Deploy-Ablauf** wird: Token → Datenbank → Migrationen → **Startinhalte** → Platzhalter bauen. Neuer Schritt `startinhalte` ruft `(new Seeder($pdo, $config))->run(new Actor('deploy'))`; Fehlerbehandlung wie die übrigen Schritte. Antwort: `{"migrations": […], "seeded": […], "built": […]}`. `deploy.cmd` bleibt unverändert (es wertet nur den Status aus).
- **Builder bleibt der Platzhalter** — die echte Website kommt mit Meilenstein 2.
- **Deploy auf Strato** nur nach ausdrücklichem Go des Inhabers in der Session (wirkt auf die Live-Domain).

## Dateien

| Datei | Inhalt |
|---|---|
| `app/src/Api/DeployController.php` | Schritt `startinhalte`, Antwortfeld `seeded` |
| `docs/models.md` | Abschluss-Durchsicht gegen alle Migrationen |
| `docs/PROJECT.md` | Meilenstein 1 als erledigt markieren; „Abweichungen vom Konzept" ergänzen: Tabellen `tokens` → Meilenstein 3, `login_attempts` → 4, OAuth-Tabellen → 5; CLI ohne `token:create`; Formprüfung als reine Klasse `Util\FormCheck` |
| `docs/code-map.md` | Abschluss-Durchsicht: jede neue Datei aus Phase 1–7 genannt (Ordner-Ebene genügt) |
| `docs/knowledge/INDEX.md` | Eintrag „Lokale Umgebung": PHP 8.5 + MySQL 8.4 per winget, Datenbank `dispatch_dev` |

## Abnahme (Phase)

- [ ] Lokal: `POST /dispatch/api/deploy` gegen den Dev-Router auf frischer `dispatch_dev` → 200 mit 9 Migrationen, 17 `seeded`-Einträgen, Platzhalter-Dateien; zweiter Aufruf → `migrations: []`, `seeded: []`.
- [ ] Lokal: Durchstich aus README → „Finale Abnahme" Punkt 2 als ein Skript (Medium importieren → Artikel → Formprüfung → einreichen → freigeben → `livePosts()`), Ausgabe im Report-Back.
- [ ] Strato (nach Go): `deploy.cmd` → grün; Antwort des Endpunkts zeigt 9 Migrationen und 17 Startinhalte; zweiter `deploy.cmd app` → `migrations: []`, `seeded: []`.
- [ ] Strato: `https://quantum-canvas.de/` Platzhalter 200; `/card/` und `/scanner/capture` 200 mit unverändertem Header-Block; `/dispatch-app/.env` 403; `/dispatch-app/defaults/imprint.md` und `/dispatch-app/migrations/005_post.sql` 403.
- [ ] `git status` sauber bis auf Absicht; CI grün.

## Checkliste

- [ ] Dateien laut Tabelle
- [ ] Abnahme fahren (Strato-Teil erst nach Go)
- [ ] Plan archivieren nach `docs/archive/2026-11/` (bzw. Monat des Abschlusses), README-Bottom-Sektionen füllen, `STATE.md` aktualisieren (nächster Schritt: Meilenstein 2 planen)

## Report-Back
