# STATE

**Aktiver Plan:** `docs/planning/2026-11-16_dispatch-dark-botanical/`
**Phase:** 4/4 abgeschlossen (Code und Doku); offen nur Sichtprüfung an der Demo-Site (finale AK 7)
**Next step (Dark Botanical):** Datenbank einrichten, `php bin/dispatch demo` + `build`, Desktop/Tablet/Mobil/Druck/ohne JS/Redaktionssicht belegen, dann Plan archivieren. **Weiter (alt):** Browser-Check der Vorschlags- und Veröffentlichungsplan-Dialoge; Historien-Artikel (Altes Land 45, Postmaster 51, Ada Lovelace 50) schreiben lassen, freigeben und einplanen, damit die Geschichtsquote greift.

**Kategorien-Umstellung (ADR-037, 2026-11-17, live):** Migration 018, `category:restructure --apply --remote` und Rauchtest durch (42 Kategorien neu, 33 Artikel und 22 Queue-Einträge umgehängt, alte Adressen leiten weiter, Nachbarn und Sperre von `/dispatch-app/` in Ordnung). Sicherung für den Rollback: `deploy-backup/category-restructure-20261117-153851.json` (lokal, nicht im Git); Rollback: `php -d curl.cainfo=<ca-bundle.crt> bin/dispatch category:restructure --rollback=<Datei> --remote`. Dokumente `preferences` und `editorial_guidelines` (Revision 8) und die Plan-Reihenfolge sind angepasst.
**Weitere offene Arbeit:** Nach dem Deploy stehen die Browser-Sichtpruefung des Artikels, der Agenten-Anweisungen und der Gestaltungs-Bausteine aus.

**Nachbar GARAGE (2026-11-16, deployt):** Wurzel-`.htaccess` (Ausnahmen `/garage/`, Discovery-Umleitung), reservierte Namen, `robots.txt` und Rauchtest (Nachbarn + Blog-CSP-Prüfung) live; Rauchtest grün.

**Offen, plan-unabhängig:** Browser-Lauf des Dialogs „Anweisungen für Agenten“ gegen die lokale Instanz (angemeldet: Speichern, Konflikt, Wiederherstellen; bisher nur gegen Stub-API geprüft). Nach dem Deploy P3-AK5 des Plans „zeitversetzt veröffentlichen“ prüfen (ETag/304 auf https://quantum-canvas.de/feed.xml); Kategorie „Testlauf“ auf dem Server per Dialog löschen. Offen aus dem Plan Gestaltungs-Bausteine: Sichtprüfung der Baustein-Galerie (AK 5) im Browser, Druck- und Ohne-Skript-Ansicht der Diagramme.

**Zuletzt archiviert:** `docs/archive/2026-11/2026-11-16_blog-gestaltung/` (Teal, Zitate, Social-Einbettungen, Agenten-CSS, Phasen 1–5).
