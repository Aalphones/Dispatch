# DISPATCH — Design Handover für Claude Code

## Ziel

Überarbeite die bestehende DISPATCH-Website zu einem ruhigen, minimalistischen Dark-Editorial-Magazin. Die Inhalte stehen im Vordergrund. Die botanische Gestaltung ist ein subtiler Wiedererkennungswert, keine Dekorationskulisse.

**Wichtig:** Bestehende Informationsarchitektur, Kategorien, Artikelinhalte und redaktionelle Funktionen erhalten. Kein neues Produktkonzept daraus machen.

## Designprinzipien

- Dark Botanical Editorial: hochwertig, ruhig, reduziert.
- Kein Intro-Text und kein Hero-Banner auf der Startseite.
- Der erste Artikel beginnt direkt unter dem kompakten Header.
- BisaSam-inspirierte Stimmung nur über Teal, Blattfarben und eine eigenständige Pflanzengeist-Illustration. Keine offiziellen Pokémon-Artworks oder Pokémon-Logos verwenden.
- Viel klare Typografie, feine Konturen, kaum Schatten.
- Keine zufälligen Blätter, Partikel, Glow-Effekte oder Dschungel-Hintergründe.

## Farbvariablen

```css
:root {
  color-scheme: dark;
  --color-bg: #071D1B;
  --color-surface: #102E2A;
  --color-surface-raised: #153A34;
  --color-border: #23645B;

  /* Teal ist die aktive Primärfarbe und muss sichtbar sein. */
  --color-primary: #43D6C5;
  --color-primary-hover: #62E0D0;

  --color-green: #78C850;
  --color-lime: #A5DB55;
  --color-text: #F1F5E9;
  --color-muted: #A5B9B1;
  --color-accent: #E76F65;

  --radius-card: 16px;
  --radius-control: 999px;
  --content-max: 1440px;
}
```

## Assets

Die SVG-Dateien liegen im selben Ordner:

- `logo-primary.svg` — horizontales Logo mit Signet und Claim.
- `logo-icon.svg` — Signet für Favicon / kompakte UI.
- `logo-wordmark.svg` — reine Wortmarke.
- `footer-plant-spirit.svg` — eigenständige, ruhige Pflanzengeist-Illustration für den Footer-Vorspann.

SVGs sind skalierbar und können direkt als `<img>` oder als CSS-Hintergrund eingebunden werden. Bei Verwendung als CSS-Hintergrund `background-size: contain; background-repeat: no-repeat; background-position: bottom center;` setzen.

## Startseiten-Layout

### 1. Header

- Bestehende DISPATCH-Wortmarke links.
- Claim optional klein neben dem Logo.
- Suche und Login rechts.
- Kategorienavigation als zweite, kompakte Zeile.
- Header insgesamt kompakt halten; keine große vertikale Leerfläche.
- Aktiver Navigationspunkt und Fokuszustände in `--color-primary`.

### 2. Hauptbereich — direkt mit Artikeln starten

**Kein Intro, kein Begrüßungstext, kein Hero-Banner.**

Desktop-Raster:
- Hauptspalte ca. 2/3 der verfügbaren Breite.
- Sidebar ca. 1/3.
- Gap 24–32 px.
- Oberer Abstand nach Navigation ca. 24–32 px.

Hauptspalte:
- Erster / hervorgehobener Artikel als Featured Card.
- Darunter das Artikel-Grid (z. B. drei Spalten, responsiv reduziert).
- Bestehende Artikelbilder, Kategorien, Titel, Teaser und Metadaten weiterverwenden.

Featured Card:
- Bild links, Text rechts.
- Kategorie-Badge in Teal.
- Titel und Teaser klar priorisieren.
- Metadaten (Format, Lesezeit) dezent.
- Pfeil / Link als Teal-Aktion.

### 3. Sidebar

Die Sidebar-Module aus dem bisherigen Entwurf beibehalten:

**Über DISPATCH**
- Kurzer vorhandener Beschreibungstext.
- Kleines Blatt-Signet neben der Überschrift.

**Beliebte Schlagwörter**
- Tags als kompakte Outline-Chips.
- Teal-Rahmen und Teal-Hover.
- Keine großen dekorativen Grafiken in der Sidebar.

### 4. Footer-Vorspann mit Pflanzengeist

- Illustration `footer-plant-spirit.svg` am unteren Ende des Inhalts platzieren.
- Sie sitzt am unteren Bildschirm-/Seitenrand als Eyecatcher, direkt oberhalb des eigentlichen Footers.
- Nicht `position: fixed`; sie darf beim Scrollen keine Inhalte überlagern.
- Keine eigene große Hero-Fläche und keine zusätzliche Textbotschaft.
- Auf Desktop rechts oder mittig-rechts ausrichten; auf Mobile kleiner und mittig.
- Illustration darf leicht angeschnitten sein, aber Gesicht und Silhouette müssen erkennbar bleiben.
- Hintergrund bleibt transparent; die bestehende Seitenfläche scheint durch.

### 5. Minimaler Footer

Nur:
- kleines DISPATCH-Logo / Signet
- Impressum
- Datenschutz
- Kontakt

Keine zweite Navigation, keine Social-Wall, keine zusätzlichen Werbetexte.

## Komponenten / Umsetzung

Vorhandene Komponenten bevorzugt erweitern statt parallel neue Systeme zu bauen. Sinnvolle Komponenten, falls noch nicht vorhanden:

- `SiteHeader`
- `CategoryNavigation`
- `FeaturedArticleCard`
- `ArticleCard`
- `ArticleGrid`
- `AboutSidebarCard`
- `PopularTagsCard`
- `FooterMascot`
- `SiteFooter`

## Responsive Verhalten

- Desktop: Featured Card + Sidebar zweispaltig.
- Tablet: Sidebar unter Featured / Artikelgrid oder als kompakter Block.
- Mobile: Header kompakt, Navigation horizontal scroll- oder menüfähig; Artikelkarten einspaltig; Sidebar-Module unter den Artikeln; Footer-Illustration kleiner.
- Keine horizontalen Überläufe.
- `prefers-reduced-motion` respektieren.

## Interaktionszustände

- Links und Buttons: Teal als Hover-/Focus-Farbe.
- Tastatur-Fokus gut sichtbar, z. B. `outline: 2px solid var(--color-primary); outline-offset: 3px`.
- Hover-Animationen subtil (ca. 150–200 ms), keine dauerhaften Bewegungsanimationen.
- Kontrast für Fließtext und Metadaten prüfen.

## Nicht tun

- Keinen zusätzlichen Hero-Text über den Artikeln einfügen.
- Keine riesige leere Fläche unter der Navigation.
- Keine vollflächige Dschungelillustration.
- Keine Schatten- und Glow-Orgie.
- Keine Pokémon-Figur 1:1 nachzeichnen oder offizielle Assets verwenden.
- Den Footer-Pflanzengeist nicht fixed positionieren.
- Keine bestehenden Artikel, Kategorien oder Funktionen entfernen.

## Abnahmekriterien

1. Der erste Artikel ist direkt nach dem Header sichtbar.
2. Es gibt keinen einleitenden Fülltext und keinen Banner.
3. Teal ist klar als Primärfarbe erkennbar (Navigation, Links, Buttons, Badges).
4. Sidebar enthält „Über DISPATCH“ und „Beliebte Schlagwörter“.
5. Pflanzengeist sitzt am unteren Ende des Inhalts direkt vor dem schlichten Footer.
6. Footer enthält nur Logo/Signet sowie Impressum, Datenschutz und Kontakt.
7. Layout funktioniert auf Desktop, Tablet und Mobile.
8. Bestehende Inhalte und Funktionen bleiben erhalten.
