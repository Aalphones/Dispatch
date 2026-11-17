# STATE

**Aktiver Plan:** (kein aktiver Plan)
**Next step:** Kategorien-Umstellung live bringen (ADR-037): Strato-Datenbanksicherung, `deploy.cmd` (Migration 018), dann `php bin/dispatch category:restructure --dry-run --remote` prüfen, `--apply --remote`, danach Rauchtest (alte Kategorie-URLs, Archive, Plan). Danach den Browser-Check der Vorschlags- und Veröffentlichungsplan-Dialoge.

**Kategorien-Umstellung (2026-11-17):** Code, Test gegen Scratch-MySQL (Apply, Rollback, Negativfälle, API) und Doku fertig, nicht committet, nicht deployt. Live bereits geändert: Dokumente `preferences` und `editorial_guidelines` (Revision 8) und die Reihenfolge des Veröffentlichungsplans (Gaming-Reserve ans Ende, 26/37/32/35 vorn). Offen: Live-Lauf, Historien-Artikel (Altes Land 45, Postmaster 51, Ada Lovelace 50) erst nach Recherche und Freigabe in den Plan.
**Weitere offene Arbeit:** Nach dem Deploy stehen die Browser-Sichtpruefung des Artikels, der Agenten-Anweisungen und der Gestaltungs-Bausteine aus.

**Nachbar GARAGE (2026-11-16, deployt):** Wurzel-`.htaccess` (Ausnahmen `/garage/`, Discovery-Umleitung), reservierte Namen, `robots.txt` und Rauchtest (Nachbarn + Blog-CSP-Prüfung) live; Rauchtest grün.

**Offen, plan-unabhängig:** Browser-Lauf des Dialogs „Anweisungen für Agenten“ gegen die lokale Instanz (angemeldet: Speichern, Konflikt, Wiederherstellen; bisher nur gegen Stub-API geprüft). Nach dem Deploy P3-AK5 des Plans „zeitversetzt veröffentlichen“ prüfen (ETag/304 auf https://quantum-canvas.de/feed.xml); Kategorie „Testlauf“ auf dem Server per Dialog löschen. Offen aus dem Plan Gestaltungs-Bausteine: Sichtprüfung der Baustein-Galerie (AK 5) im Browser, Druck- und Ohne-Skript-Ansicht der Diagramme.

**Zuletzt archiviert:** `docs/archive/2026-11/2026-11-16_blog-gestaltung/` (Teal, Zitate, Social-Einbettungen, Agenten-CSS, Phasen 1–5).
