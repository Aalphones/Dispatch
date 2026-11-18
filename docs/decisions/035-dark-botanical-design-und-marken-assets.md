# 035 — Dark-Botanical-Gestaltung und lokale Marken-Assets

**Status:** Akzeptiert (2026-11-18) · ersetzt den Entwurf aus ADR-013 als visuelle Referenz

## Kontext

Die Website soll als ruhiges Dark-Editorial-Magazin mit Teal und Blattfarben erscheinen. Dafür gibt es neue Logos und ein Footer-Maskottchen. Der bisherige Aufbau lieferte nur Theme-Dateien (CSS/JS) als hash-benannte Assets.

## Optionen

1. **Alte Papier-Optik behalten.** Kein Aufwand, erfüllt den Auftrag nicht.
2. **Handover mit SVG-Footerbild.** Das Handover brachte eine SVG-Pflanzengeist-Illustration mit.
3. **Handover mit dem neuen Maskottchen-PNG.** Das vom Auftraggeber gelieferte PNG (1774 × 887 px, RGBA) ist das freigegebene Motiv.

## Entscheidung

- Option 3. Das PNG wird unverändert ausgeliefert; Autovektorisierung ist verworfen (weiche Verläufe wären entweder Trace-Artefakte oder eine große Pfadsammlung).
- Marken-Assets liegen in `app/theme/branding/` (`logo-primary.svg`, `logo-icon.svg`, `logo-wordmark.svg`, `footer-mascot.png`). `Assets::collect()` hasht den Inhalt und liefert `assets/<name>.<hash>.<ext>` unter den Schlüsseln `logoPrimary`, `logoIcon`, `logoWordmark`, `footerMascot`. Fehlt eine Quelle, ist die Adresse leer; der Bau läuft weiter.
- `Builder::collectHome()` reicht `aboutText` (`site_description`, sonst `site_tagline`) und `topTags` (zehn häufigste) an `home.php`; keine neue Abfrage, das Dokument „Über“ wird nicht als HTML gelesen.
- Verbindliche visuelle Quelle ist `docs/design/dispatch-dark-botanical.md` (Tokens, Breakpoints, Komponenten, Verbote, Asset-Regel).

## Folgen

- Es gibt keine hellen Farbschemata mehr; die Website ist dauerhaft dunkel.
- Neuer Build-Assettyp (Bilddateien aus `theme/branding/`); sie stehen im Manifest wie alle Assets.
- Das Footerbild hat feste intrinsische Maße (1774 × 887) und ist mit rund 800 KB das größte Theme-Asset; es lädt nur als dekoratives Bild (`loading="lazy"`).
- `style.css` bleibt ≤ 50 000 Byte, kein externes CSS/JS/Webfont.
- Kein Test-Framework (ADR-004).
