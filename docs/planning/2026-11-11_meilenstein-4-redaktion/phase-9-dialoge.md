# Phase 9 — Dialoge: Thema einreichen, Interessen, Statistik, Zugänge

## Kontext

- `README.md` → „Endpunkte“ (`queue`, `categories`, `stats`, `tokens`, `auth/logout-all`).
- Entwurf (verbindlich): `.fab` Zeile 1086; Dialoge `#einreichen` 1101–1115, `#interessen` 1117–1131, `#statistik` 1133–1143, `#zugaenge` 1145–1156; CSS `.dlg-*`, `.ablage`, `.schalter`, `.zwei`, `.interessen-liste`, `.int`, `.signale`, `.kpis`, `.balken`, `.liste`; Menü-Einträge in `#menue`.
- Vault `frameworks/a11y.md` → Forms (Beschriftungen, Fehlermeldungen am Feld).
- Code: Module aus Phase 6 (`ui.js`, `api.js`, `sitzung.js`, `konto.js`).

## Zugänge und „Neuer Zugang“ — Entwurf `artifacts/entwurf-zugaenge.html` (verbindlich)

Der Entwurf zeigt alle Zustände (Anleitung: `artifacts/README.md`); sein Stilblock „Ergänzungen für Zugänge“ wandert nach `redaktion.css`, Texte wörtlich übernehmen. Struktur: Liste mit Schlüssel-Symbol, Name, „zuletzt … · läuft ab …“ und Rechte-Etiketten (Lesen, Schreiben, Einstellungen — letzteres in Akzentfarbe); Widerrufen und „Überall abmelden“ fragen **im Dialog** nach (aufklappende Zeile), nicht in einem zweiten Dialog; leere Liste mit gestricheltem Kasten. Klick „Neuer Zugang …“ ersetzt Rumpf, Fuß und Überschrift des Dialogs (kein zweiter Dialog):

1. **Name** (Pflicht, Muster aus ADR-017, Platzhalter „z. B. laptop oder geplante-aufgabe“, Fehler am Feld).
2. **Darf** — drei Kästchen: „Lesen“ (an, `blog:read`), „Schreiben und einreichen“ (an, `blog:write`), „Einstellungen und Kategorien ändern“ (aus, `blog:admin`), je mit `info`: Lesen = „Briefing, Artikel, Statistik lesen“; Schreiben = „Entwürfe anlegen, Bilder hochladen, zur Freigabe einreichen. Veröffentlichen kann kein Zugang.“; Einstellungen = „Richtlinien, Präferenzen, Kategorien. Öffentliches wird nur vorgeschlagen.“
3. **Läuft ab** — Auswahl „nie“ (Vorgabe), „in 30 Tagen“, „in 90 Tagen“, „in einem Jahr“.
4. **Kein Passwortfeld** (Entscheidung des Inhabers). Absenden ruft `POST tokens`.
5. **Ergebnis:** „Zugang angelegt. Das Token siehst du nur jetzt.“; zwei Felder schreibgeschützt (Token, fertiger `claude mcp add`-Befehl), je ein Knopf „Kopieren“ (Toast „Kopiert“); Knopf „Fertig“ kehrt zur Liste zurück und verwirft den Klartext aus dem Speicher.

## AK der Phase

1. **FAB „Thema einreichen“** auf allen Seiten außer Vorschau (Entwurf Zeile 605) und Menü-Eintrag öffnen `#einreichen`. Felder nach Entwurf; Ablage: Klick öffnet die Dateiauswahl mit `accept="image/*,video/*,audio/*"` und `capture` **nicht** gesetzt (Handy bietet Kamera und Galerie an); Ziehen und Ablegen auf dem Desktop; ausgewählte Dateien als Liste mit Größe und Entfernen-Knopf; Dateien über 50 MB werden vor dem Senden abgewiesen („<Name> ist größer als 50 MB.“). Priorität „Normal/Bald/Sofort“ → `normal/soon/now`, Tiefe → `standard/deep`, beide mit `info` (Tiefe: „Tiefenrecherche heißt: ab 1.200 Wörter und 5 Quellen.“). Senden zeigt Fortschritt (`XMLHttpRequest.upload.onprogress`, weil `fetch` keinen Upload-Fortschritt kennt), dann Toast „Thema eingereicht – der nächste Agentenlauf greift es auf.“ und bei `rejected` je Datei eine Zeile im Toast. Titel fehlt → Fehler am Feld, nichts gesendet.
2. **Interessen** (`#interessen`): Liste aus `GET categories`, Unterkategorien eingerückt (`.int.unter`); je Zeile Gewicht 0–5 mit Wortlaut (0 „nie von selbst“, 1 „selten“, 2 „ab und zu“, 3 „gelegentlich“, 4 „oft“, 5 „bevorzugt“), `info` wie Phase 8; Signale „▲ n× mehr davon · ▼ m× weniger“; Stichwörter „Gern über“ und „Bitte nicht“ als bearbeitbare Chips (Eingabe + Enter fügt hinzu, × entfernt) → `PATCH categories/{id}`; bei `suggestion` die Zeile „Vorschlag aus deinen Klicks: auf n?“ mit „Übernehmen“.
3. **Statistik** (`#statistik`): Zeitraum 7/14/30/90 Tage (Vorgabe 14, Überschrift „Statistik · letzte n Tage“); KPIs Aufrufe, Besucher (je Tag, mit `info` „Eindeutig je Tag, über Tage nicht addierbar.“), zu Ende gelesen, Lesezeit Ø; Balken je Tag (Höhen per `element.style.height`, `aria-label` mit Zahlen); Meistgelesen (5), Herkunft (5), Suchen ohne Treffer als Chips — Klick auf einen Chip öffnet „Thema einreichen“ mit dem Begriff als Titel. Ohne Daten: „Noch keine Aufrufe im Zeitraum.“
4. **Zugänge** (`#zugaenge`) nach `artifacts/entwurf-zugaenge.html`: Liste aus `GET tokens` mit „zuletzt <Datum Uhrzeit>|noch nie benutzt“, „läuft ab <Datum>|kein Ablauf“ und Rechte-Etiketten; „Widerrufen“ fragt inline nach („„<Name>“ widerrufen? Agenten mit diesem Zugang verlieren sofort den Zugriff.“) → `DELETE tokens/{id}`. „Neuer Zugang …“ nach Abschnitt oben, Ergebnis mit „Befehl für Claude Code“ zuerst, dann „Nur das Token“. „Überall abmelden“ → Nachfrage → `auth/logout-all` → abgemeldet. Name schon vergeben (`name_taken`) → Fehler am Namensfeld. OAuth-Einträge (Claude-App) erscheinen nicht (Meilenstein 5).
5. Alle Dialoge: Esc und ✕ schließen, Fokus kehrt zum Auslöser zurück, Fehler der API stehen im Dialog (`role="alert"`), nicht nur im Toast.
6. Handy 390 px: Dialoge füllen die Breite, Absende-Knopf ohne Scrollen erreichbar (fester Fuß `.dlg-fuss`), Tastatur verdeckt das aktive Feld nicht.

## Checkliste

- [x] `app/theme/redaktion/queue.js`
- [x] `app/theme/redaktion/category.js` (Interessen-Dialog)
- [x] `app/theme/redaktion/stats.js` (Dialog)
- [x] `app/theme/redaktion/token.js`
- [x] `konto.js`: Menü-Einträge einblenden; FAB
- [x] `redaktion.css`: Dialog-Stile nach Entwurf, Formular „Neuer Zugang“
- [x] Doku: `docs/code-map.md`

## Report-Back

Status: complete. Lokal gegengeprüft mit Chrome (headless, Fernsteuerung) gegen `php -S` und lokale MySQL (Teststatistik und Testzugänge danach gelöscht), 1280 und 390 px: AK 1–6 grün — FAB und Menü öffnen „Thema einreichen“ (Dateiauswahl `image/*,video/*,audio/*`, kein `capture`; leerer Titel → Fehler am Feld; 51-MB-Datei abgewiesen; Upload mit Fortschritt, Toast); Interessen mit Baum, Gewicht 0–5, Chips (Enter fügt hinzu, × entfernt) und Speichern über `PATCH`; Statistik mit 7/14/30/90 Tagen, KPIs, Balken, Meistgelesen, Herkunft und Suchbegriffen, Klick auf Begriff öffnet „Thema einreichen“ mit Titel; Zugänge: Liste, Widerrufen mit Nachfrage im Dialog, „Neuer Zugang“ mit Feldfehlern (leer, ungültig, `name_taken`), Ergebnis mit Befehl zuerst, „Fertig“ verwirft den Klartext; Esc schließt, Fokus kehrt zum Auslöser zurück; Seitenbreite 390, Fuß der Dialoge sichtbar.

- Der Dialog „Thema einreichen“ hat statt „Kamera öffnen“ die Fläche „Dateien auswählen“ (AK: Handy bietet Kamera und Galerie an).
- 🟡 Nicht geprüft: „Überall abmelden“ bis zum Ende (nur die Nachfrage, sonst wäre die Testsitzung weg); Vorschlag-Zeile „Übernehmen“ (keine Testdaten mit Signalen); Tastatur auf einem echten Handy.
