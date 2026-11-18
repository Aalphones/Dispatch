# DISPATCH: Dark Botanical Editorial

Stand: 2026-11-16 · Quelle: Design-Handover vom 2026-11-16

| Phase | Inhalt | Rating | Status |
|---|---|---|---|
| 1 | Marken-Assets, Builder-Kontrakt und verbindliche Designquelle | heikel | complete |
| 2 | Dunkle globale Schale: Header, Navigation, Footer, Tokens | standard | complete |
| 3 | Startseite: Featured-Artikel, Sidebar und Raster | standard | pending |
| 4 | Restliche öffentliche Seiten, Redaktionsmodus und Dokumentation | standard | pending |

## Kontrakt

- Der Builder liefert vier lokale, hash-benannte Marken-URLs: `logoPrimary`, `logoIcon`, `logoWordmark`, `footerMascot`. Die Quelldateien liegen unter `app/theme/branding/`; Besucher laden keine fremden Assets nach.
- Der sichtbare Header benutzt `logoPrimary`, schaltet unterhalb von 46rem auf `logoIcon` um und behält Suche, Login sowie die Kategorienavigation funktionsgleich. Die einzige `h1` der Startseite ist als Screenreader-Titel im Hauptinhalt vorhanden; direkt sichtbar beginnt der Featured-Artikel.
- `collectHome()` reicht `site_description` (mit Fallback `site_tagline`) und die zehn häufigsten Tags an `home.php`. Daraus entstehen ausschließlich auf der Startseite die Karten „Über DISPATCH“ und „Beliebte Schlagwörter“.
- Der Footer enthält nur Marken-Signet/Name und die Links „Kontakt“ (`/ueber/`), „Impressum“ und „Datenschutz“. Tags, RSS, Kategorien und Claim ziehen aus dem Footer aus.
- Produktions-Footerbild ist `schlafender-blaettergeist-im-gruenen.png`. Es wird unverändert als lokales PNG ausgeliefert; eine Autovektorisierung findet nicht statt.

## Finale AK

1. Die öffentliche Website ist in allen Farbschemata dauerhaft Dark Botanical: Hintergrund `#071D1B`, erkennbare teal-farbene Primäraktionen `#43D6C5`, grüne Zweitfarbe, Text `#F1F5E9`; keine helle Standardvariante oder rote Alt-Primärfarbe bleibt sichtbar.
2. Header und Kategorienavigation sind kompakt; auf der Startseite steht der erste Artikel ohne Hero, Begrüßung oder Leerfläche direkt nach der Navigation. Fokus, Suche, Login, Skip-Link und Tastaturbedienung funktionieren weiter.
3. Desktop-Startseite: Featured Card mit Bild links/Text rechts und rechte Sidebar mit „Über DISPATCH“ sowie „Beliebte Schlagwörter“. Tablet stapelt Sidebar kontrolliert, Mobil zeigt Karten einspaltig und Navigation ohne horizontalen Seitenüberlauf.
4. Vor dem minimalen Footer erscheint das neue Maskottchen-PNG als nicht fixes, dekoratives Footer-Motiv; Gesicht/Silhouette bleiben bei allen Breakpoints erkennbar. Der Footer enthält nur Signet/Name, Kontakt, Impressum und Datenschutz.
5. Artikel, Kategorien, Tags, Suche, Dokumente, 404, Vorschau, Zustimmung und angemeldeter Redaktionsmodus bleiben funktionsfähig, kontrastreich und lesbar; Inhalt, URLs, Builder-Manifest, Bildvarianten und Sicherheitsgrenzen ändern sich nicht.
6. `app/theme/style.css` bleibt höchstens 50.000 Byte; die bestehenden Größenobergrenzen aller übrigen Theme-Dateien bleiben eingehalten. Kein externes CSS, JavaScript oder Webfont wird eingeführt.
7. Die Implementierung enthält ADR-035, aktualisierte Frontend-/Code-Map-Dokumentation und eine an der erzeugten Demo-Site belegte Desktop-, Tablet-, Mobil-, Druck-, Ohne-JavaScript- und angemeldete Redaktionssicht.

## Formatentscheidung

Das vom Auftraggeber gelieferte Maskottchen-PNG (1.774 × 887 px, Alpha-Kanal) ist das verbindliche Footer-Motiv. Es wird als transparentes, lokales PNG mit seinen vorhandenen intrinsischen Maßen durch die hash-benannte Asset-Pipeline ausgeliefert. Eine automatische SVG-Vektorisierung ist verworfen: Ihre vielen weichen Verläufe und feinen Konturen würden entweder zu sichtbaren Trace-Artefakten oder zu einer unnötig großen Pfadsammlung führen. Das ist kein Upgrade, nur XML mit schlechtem Gewissen.

## Summary / Files touched / Commits / Deviations / Follow-ups

**Summary:**

**Files touched:**

**Commits:**

**Deviations from plan:**

**Follow-ups:**
