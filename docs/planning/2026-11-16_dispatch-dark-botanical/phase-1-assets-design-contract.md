# Phase 1 — Marken-Assets und Designvertrag

## Kontext (lesen)

- `app/src/Site/Assets.php` — bisher nur CSS/JS und der Redaktionsordner werden als hash-benannte Build-Assets gesammelt.
- `app/src/Site/Builder.php` — `collect()` verbindet Assets und alle statischen Seiten; `collectHome()` stellt die Daten für `home.php` bereit.
- `app/templates/layout.php`, `app/templates/home.php`, `app/src/Site/SiteData.php` — derzeitige Datenquelle für Seitenname, Beschreibung, Tags und eindeutige `h1`.
- `docs/conventions/frontend.md`, `docs/decisions/013-website-aufbau-und-entwurf.md`, `docs/decisions/015-theme-budgets-skriptmodule.md`.
- `artifacts/README.md` sowie die drei Logo-SVGs und das freigegebene Maskottchen-PNG in diesem Plan.

Fehlerklassen: PHP-/CSS-/HTML-Vault-Abschnitte waren in dieser Planungssitzung nicht lesbar (Zugriff auf `C:\Users\sasch\.codex\knowledge` verweigert). Für diese Phase gelten daher zusätzlich die direkten Fehlergrenzen unten: kein unmanifestiertes Asset, kein unescaped URL-Ausgabeweg und keine Cache-Datei ohne Inhalts-Hash.

## AK

1. `Assets::collect()` erzeugt aus drei SVGs und dem Maskottchen-PNG in `app/theme/branding/` hash-benannte Dateien unter `assets/` und liefert dafür die vier URLs aus dem README-Kontrakt; ein fehlendes Asset bricht nicht den ganzen Build, sondern fällt auf eine leere URL zurück wie bestehende optionale Theme-Assets.
2. `layout.php` erhält die Asset-URLs ausschließlich über `$assets`; keine absolute lokale Adresse und kein externes CDN gelangt in das generierte HTML.
3. `collectHome()` übergibt `aboutText` aus nichtleerem `site_description`, sonst `site_tagline`, sowie `topTags = array_slice($this->site->tags, 0, 10)` an `home.php`. Es gibt keine neue Datenbankabfrage und keine Interpretation des Markdown-Dokuments „Über“ als HTML.
4. `docs/design/dispatch-dark-botanical.md` ist die präzise, knappe visuelle Quelle (Tokens, Breakpoints, Komponenten, Verbote und Asset-Regel); Frontend-Konvention und ADR-035 erklären, dass sie den alten interaktiven Entwurf als verbindliche Referenz ersetzt.

## Checkliste

- [ ] `app/theme/branding/` mit `logo-primary.svg`, `logo-icon.svg`, `logo-wordmark.svg`, `footer-mascot.png` aus `artifacts/` anlegen.
- [ ] `app/src/Site/Assets.php`: getrennte, explizite Branding-Map ergänzen; Inhalt lesen, Hash über den Inhalt bilden, als `assets/<name>.<hash>.<ext>` registrieren und URLs unter den vier Kontrakt-Schlüsseln bereitstellen.
- [ ] `app/src/Site/Builder.php`: für die Startseite `aboutText` und `topTags` wie im Kontrakt bereitstellen; keine Seitendaten global mutieren.
- [ ] `app/templates/layout.php`: die später verwendeten vier `$assets`-Einträge defensive lesen, damit Vorschau/Build ohne Asset nicht mit Notice scheitert.
- [ ] `docs/design/dispatch-dark-botanical.md` als Designvertrag anlegen; `docs/conventions/frontend.md` von `entwurf-website.html` auf diese Datei umstellen.
- [ ] `docs/decisions/035-dark-botanical-design-und-marken-assets.md` anlegen: Optionen (alte Papier-Optik, Handover mit SVG-Footerbild, Handover mit neuem Maskottchen-PNG), Entscheidung für lokale hash-benannte Marken-Assets und das PNG, Konsequenzen (keine hellen Varianten, neuer Build-Assettyp, feste intrinsische Bildmaße).
- [ ] `docs/code-map.md` um `app/theme/branding/` und die Branding-Map in `Assets` ergänzen.

## Report-Back
