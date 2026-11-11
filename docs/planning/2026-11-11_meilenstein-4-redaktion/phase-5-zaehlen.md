# Phase 5 — Zähl-Aufruf der Statistik

## Kontext

- Fachkonzept → „Zugriffsstatistik“ → „Erfassung“ und „Kennzahlen“.
- `docs/knowledge/GAPS.md` → „Statistik-Zählung“; `docs/archive/2026-11/2026-11-10_meilenstein-3-mcp-server/FINDINGS.md` → offener Punkt „Meilenstein 4 (Zähl-Aufruf)“: jedes Ereignis genau einmal zählen.
- `docs/decisions/015-theme-budgets-skriptmodule.md`; Budgets im README-Kontrakt dieses Plans.
- Code: `app/migrations/008_stats.sql`, `013_stats_visitor_path.sql` (Phase 1), `app/src/Domain/StatsRepository.php`, `StatsService.php`, `app/config.php` (`stats_enabled`), `app/theme/site.js`, `app/theme/suche.js`, `app/src/Site/Assets.php`, `app/templates/layout.php`, `app/src/Site/Builder.php` (`collectTechnical`, `robots.txt`).

## Beacon-Format (entschieden)

`navigator.sendBeacon('/dispatch/api/hit', JSON.stringify(x))` (Text, kein Preflight). Felder kurz:

| `t` | Felder | Wirkung |
|---|---|---|
| `view` | `p` Pfad, `r` Referrer-Domain oder `''`, `u` `utm_source` oder `''`, `d` `mobil`/`tablet`/`desktop` | `stats_daily.views +1`; `visitors +1`, wenn (`day`, Hash, `p`) neu in `stats_visitor_paths`; Dimensionen `referrer` (Wert = Domain, leer → `direkt`, eigene Domain → nicht gezählt), `utm` (nur wenn gesetzt), `device` — alle mit `path = p` |
| `read` | `p`, `s` Sekunden (0–3600), `h` 0/50/100 | `engaged_seconds += s`; `h ≥ 50` → `reads_half +1`; `h = 100` → `reads_full +1` |
| `out` | `p`, `z` Zieldomain | Dimension `outbound`, `path = p` |
| `search` | `q` Begriff, `n` Trefferzahl | Dimension `search`, `path = '/suche/'`, Wert = `mb_strtolower(trim(q))` (2–100 Zeichen), `extra = {"hits": n}` (letzter Wert) |

`post_id` in `stats_daily` = ID des Live-Artikels, wenn `p` auf `/artikel/{slug}/` passt, sonst `NULL`.

## Verwerfen (Antwort trotzdem 204)

`stats_enabled` aus · Body > 2 KB oder kein gültiges JSON · unbekanntes `t` · `p` beginnt nicht mit `/`, ist länger als 300 Zeichen oder beginnt mit `/vorschau/`, `/anmelden/`, `/dispatch` · `User-Agent` leer oder passt auf `config.php` → `stats_bot_pattern` (`/bot|crawl|spider|slurp|preview|headless|lighthouse|curl|wget|python|httpclient|java\//i`) · `Origin` fehlt oder ≠ Ursprung von `DISPATCH_BASE_URL` · Header `DNT: 1` oder `Sec-GPC: 1` · Lastschutz: mehr als 60 Beacons je Tages-Hash in der laufenden Stunde (`stats_visitors.hits_last_hour`, `hour_started` älter als 1 h → zurücksetzen).

## Tages-Hash (entschieden)

`hash('sha256', ip . '|' . user_agent . '|' . salt)`. Der Salt liegt in `app/storage/stats/salt.json` (`{"day":"YYYY-MM-DD","salt":<64 Hex>}`); gehört er nicht zu heute (UTC), wird er neu erzeugt (Datei mit `LOCK_EX` schreiben) und `DELETE FROM stats_visitors WHERE day < CURRENT_DATE` sowie dasselbe für `stats_visitor_paths` ausgeführt. Der alte Salt ist danach weg; ein Wiedererkennen über Tage ist unmöglich.

## Skripte (entschieden)

- `zaehler.js` (≤ 2 500 Byte, neu): von `site.js` per `import()` geladen, **nicht** wenn `navigator.doNotTrack === '1'`, `navigator.globalPrivacyControl === true`, das Merkzeichen `dispatch.angemeldet` gesetzt ist oder `body[data-seite]` = `vorschau`. Sendet `view` beim Laden. Auf Artikelseiten (`data-seite="artikel"`): aktive Zeit zählt nur bei `document.visibilityState === 'visible'` und Fokus; Tiefe über `IntersectionObserver` auf zwei leere Marken, die das Skript in den Artikeltext einfügt (nach der Hälfte der Kindelemente und am Ende); bei `visibilitychange` → `hidden` ein `read`-Beacon, danach Zähler zurücksetzen. Klick auf einen Link mit fremdem Host → `out`.
- `suche.js` meldet nach 1 500 ms ohne Eingabe (Begriff ≥ 2 Zeichen) und beim Absenden einmal je Begriff `window.dispatchEvent(new CustomEvent('dispatch:suche', {detail: {q, n}}))`; `zaehler.js` hört darauf und sendet `search`.
- `Assets` nimmt `zaehler.js` auf; `layout.php` reicht seine Adresse als `data-zaehler` am `<html>`-Tag.

## AK der Phase

1. Ein Seitenaufruf im Browser erzeugt genau einen `view`; Neuladen derselben Seite erhöht `views`, nicht `visitors`.
2. Artikel 40 s lesen, bis zum Ende scrollen, Tab wechseln → `engaged_seconds` ≈ 40, `reads_half` und `reads_full` je +1.
3. `curl -X POST … -H "Origin: <Basis>" -A curl/8` → 204, keine Zeile geändert; ohne `Origin` → 204, nichts gezählt; mit `DNT: 1` → nichts gezählt.
4. 61 Beacons binnen einer Stunde vom selben Browser → der 61. zählt nicht.
5. Suche „wasserstoff“ ohne Treffer → Zeile `search` mit `extra.hits = 0` unter `/suche/`; `get_editorial_brief` zeigt sie unter „Suchen ohne Treffer“.
6. `get_stats` (MCP) liefert nach den Schritten oben stimmige Summen (keine Doppelzählung zwischen Pfaden).
7. Angemeldet (Merkzeichen gesetzt) → kein Beacon im Netzwerk-Tab.
8. In keiner Tabelle steht eine IP-Adresse; nach Datumswechsel (Salt-Datei auf gestern setzen) sind `stats_visitors` und `stats_visitor_paths` von gestern leer.
9. Byte-Budgets: `site.js` ≤ 5 000, `suche.js` ≤ 9 500, `zaehler.js` ≤ 2 500.

## Checkliste

- [ ] `app/src/Domain/StatsRecorder.php` (`record(array $beacon, string $ip, string $ua): void`), Upserts in `StatsRepository` (`INSERT … ON DUPLICATE KEY UPDATE`)
- [ ] `app/src/Api/HitController.php` (Prüfungen oben, immer `Response::empty(204)`), Route `POST hit`
- [ ] `config.php`: `stats_bot_pattern`
- [ ] `app/theme/zaehler.js`, `site.js` (Laden), `suche.js` (Ereignis), `Assets`, `layout.php`
- [ ] ADR-022 „Zähl-Aufruf“: Beacon-Format, Verwerfen-Regeln, Salt-Datei statt Datenbank (ein Lesen pro Aufruf, Löschen ohne Cron beim ersten Aufruf des Tages), Besucher je Pfad über `stats_visitor_paths`, jedes Ereignis genau einmal je Pfad. Verworfen: Salt aus einem festen Geheimnis ableiten (wäre über Tage reproduzierbar)
- [ ] `docs/knowledge/GAPS.md`: Punkt „Statistik-Zählung“ entfernen (entschieden in ADR-022)
- [ ] Doku: `docs/routes.md`, `docs/models.md` (013, Bedeutung der Dimensionen), `docs/code-map.md`, `docs/conventions/frontend.md` (Budgets)

## Report-Back
