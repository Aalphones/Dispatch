# DISPATCH — Dark Botanical Editorial (verbindliche Designquelle)

Gilt seit ADR-035 und ersetzt `entwurf-website.html` als verbindliche visuelle Referenz. Der alte Entwurf bleibt als Verlaufsdokument (ADR-013) liegen. Informationsarchitektur, Adressen, Inhalte und Redaktionsfunktionen ändern sich nicht.

## Prinzip

Ruhiges, minimalistisches Dark-Editorial-Magazin: klare Typografie, feine Konturen, kaum Schatten. Die botanische Gestaltung ist ein dezenter Wiedererkennungswert, keine Kulisse. Es gibt nur diese Dark-Variante, in allen Farbschemata.

## Tokens

| Token | Wert | Verwendung |
|---|---|---|
| `--color-bg` | `#071D1B` | Seitenhintergrund |
| `--color-surface` | `#102E2A` | Karten, Kästen |
| `--color-surface-raised` | `#153A34` | erhöhte Flächen, Hover |
| `--color-border` | `#23645B` | feine Konturen |
| `--color-primary` | `#43D6C5` | Primäraktion, Links, aktive Navigation, Fokus |
| `--color-primary-hover` | `#62E0D0` | Hover der Primärfarbe |
| `--color-green` | `#78C850` | Zweitfarbe |
| `--color-lime` | `#A5DB55` | Zweitfarbe, Akzent |
| `--color-text` | `#F1F5E9` | Text |
| `--color-muted` | `#A5B9B1` | Metadaten (Kontrast prüfen) |
| `--color-accent` | `#E76F65` | nur Warnung/Negativ, nie Primärfarbe |
| `--radius-card` | `16px` | Karten |
| `--radius-control` | `999px` | Chips, Knöpfe |
| `--content-max` | `1440px` | Inhaltsbreite |

`color-scheme: dark`. Keine hellen Varianten, keine rote Alt-Primärfarbe. Keine harten Farbwerte außerhalb der Tokens.

## Breakpoints

- Mobil: unter 46rem. Header zeigt `logoIcon`, Navigation horizontal scrollbar oder menüfähig, Karten einspaltig, Sidebar-Karten unter den Artikeln, Footer-Maskottchen kleiner und mittig.
- Tablet: Sidebar stapelt kontrolliert unter Featured Card/Raster.
- Desktop: Featured Card und Sidebar zweispaltig (Hauptspalte etwa 2/3, Sidebar 1/3, Abstand 24–32 px, oberer Abstand nach der Navigation 24–32 px).
- Nie horizontaler Seitenüberlauf.

## Komponenten

- **Header:** kompakt. `logoPrimary` links (ab 46rem), darunter `logoIcon`; Suche und Login rechts; Kategorienavigation als zweite kompakte Zeile. Aktiver Punkt und Fokus in `--color-primary`. Die einzige `h1` der Startseite ist ein Screenreader-Titel im Hauptinhalt.
- **Featured Card (nur Startseite):** Bild links, Text rechts, Kategorie-Badge in Teal, Titel und Teaser priorisiert, Metadaten dezent, Pfeil als Teal-Aktion. Kein Hero, keine Begrüßung, keine Leerfläche: der erste Artikel beginnt direkt nach der Navigation.
- **Artikelraster:** drei Spalten Desktop, responsiv reduziert; vorhandene Bilder, Kategorien, Titel, Teaser, Metadaten.
- **Sidebar (nur Startseite):** Karte „Über DISPATCH“ (`aboutText`, kleines Blatt-Signet) und Karte „Beliebte Schlagwörter“ (`topTags`, Outline-Chips mit Teal-Rahmen und Teal-Hover). Keine großen Grafiken.
- **Footer-Vorspann:** `footerMascot` unmittelbar vor dem Footer, dekoratives Bild (`alt=""`), nicht `position: fixed`, transparent. Desktop rechts/mittig-rechts, Mobil kleiner und mittig; leicht angeschnitten erlaubt, Gesicht und Silhouette bleiben erkennbar. Kein Text.
- **Footer:** nur Signet/Name, „Kontakt“ (`/ueber/`), „Impressum“, „Datenschutz“. Keine Tags, kein RSS, keine Kategorien, kein Claim.
- **Interaktion:** Hover/Fokus in Teal, Fokusrahmen `outline: 2px solid var(--color-primary); outline-offset: 3px`, Übergänge 150–200 ms, `prefers-reduced-motion` respektieren.

## Marken-Assets (Asset-Regel)

Quellen liegen in `app/theme/branding/`; `Assets::collect()` legt sie hash-benannt unter `assets/` ab und liefert die Adressen `logoPrimary`, `logoIcon`, `logoWordmark`, `footerMascot` im `$assets`-Array. Templates lesen sie nur dort (defensiv, leer erlaubt). Das Footer-Maskottchen ist `footer-mascot.png` (1774 × 887 px, RGBA), wird unverändert als PNG mit festen `width`/`height` ausgeliefert und nie vektorisiert. Besucher laden nichts von Dritten.

## Verbote

- Kein Intro-Text, kein Hero-Banner, keine große Leerfläche unter der Navigation.
- Keine zufälligen Blätter, Partikel, Glow-/Schatten-Effekte, keine Dschungel-Hintergründe.
- Keine Pokémon-Figuren oder offiziellen Assets.
- Maskottchen nie `position: fixed`.
- Keine Artikel, Kategorien oder Funktionen entfernen; kein externes CSS/JS, kein Webfont.
- Budgets (ADR-015): `style.css` ≤ 50 000 Byte.
