# 022 — Zähl-Aufruf der Statistik

**Status:** Akzeptiert (2026-11-11)

## Kontext

Die Statistik-Tabellen (008, 013) und ihre Auswertung (MCP, Redaktions-API) bestehen; es fehlte, was sie füllt. Jedes Ereignis muss genau einmal gezählt werden, ohne IP-Adressen zu speichern und ohne Einwilligungsbanner.

## Entscheidung

- **Ein Beacon je Ereignis**: `navigator.sendBeacon('/dispatch/api/hit', JSON.stringify(…))`, Text, kein Preflight. `t` ist `view`, `read`, `out` oder `search`; Felder und Wirkung stehen in `docs/models.md`. Die Antwort ist immer `204`, auch wenn nichts gezählt wird.
- **Jedes Ereignis zählt genau einmal je Pfad**: Aufruf, Lesezeit, Ausgang und Herkunft hängen am Pfad der Seite; Suchen zählen unter `/suche/`. Besucher je Pfad entscheidet `stats_visitor_paths` (neu für Tag, Hash und Pfad → `visitors +1`); `stats_visitors` hält nur den Stundenzähler.
- **Verworfen wird still**: `stats_enabled` aus, Body über 2 KB oder kein JSON-Objekt, unbekanntes `t`, ungültiger oder ausgeschlossener Pfad (`/vorschau/`, `/anmelden/`, `/dispatch`), fehlender oder zum Bot-Muster (`stats_bot_pattern`) passender User-Agent, fehlender oder fremder `Origin`, `DNT: 1`, `Sec-GPC: 1`, mehr als 60 Beacons je Tages-Hash und Stunde. Der Browser lädt `zaehler.js` zusätzlich nicht bei DNT, GPC, Merkzeichen `dispatch.angemeldet` und Vorschau.
- **Tages-Hash**: `sha256(ip | user_agent | salt)`; die IP wird nie gespeichert. Der Salt liegt in `app/storage/stats/salt.json` (`day`, `salt`), nicht in der Datenbank: ein Lesen je Aufruf, kein Datenbankzugriff vor der Prüfung. Gehört er nicht zum heutigen Tag (UTC), erzeugt der erste Aufruf des Tages einen neuen (unter `LOCK_EX`) und löscht `stats_visitors` und `stats_visitor_paths` aller früheren Tage — ohne Cron. Der alte Salt ist danach weg, ein Wiedererkennen über Tage ist unmöglich.
- **Lesen unter Windows über das Handle**: Beim Erneuern liest der Code die Datei über das gesperrte Handle, weil `flock` sie unter Windows für jeden anderen Lesezugriff sperrt.
- **Suche über ein Ereignis**: `suche.js` meldet den Begriff 1 500 ms nach der letzten Eingabe (auch auf der Suchseite) als `dispatch:suche`; `zaehler.js` verwirft denselben Begriff in derselben Sitzung (`sessionStorage`) und sendet `search` mit Trefferzahl.

## Verworfen

- **Salt aus einem festen Geheimnis ableiten** (z. B. HMAC über das Datum): wäre über Tage reproduzierbar, also doch wiedererkennbar.
- **Salt in der Datenbank**: ein Lesen mehr je Aufruf, und die Löschung bliebe an einem Cron oder einem zweiten Mechanismus hängen.

## Konsequenzen

- Ein fehlgeschlagener Zählvorgang landet nur im Protokoll und stört die Seite nie.
- `reads_half` und `reads_full` zählen je Tab-Wechsel höchstens einmal je Stufe (der Browser sendet die Tiefe nur bei Zuwachs).
- Der Stundenzähler ist hart auf 1 000 gedeckelt; er dient nur dem Lastschutz.
