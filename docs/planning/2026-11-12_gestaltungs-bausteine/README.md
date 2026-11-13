# Gestaltungs-Bausteine für Artikel

Agenten bekommen neue Bausteine für Artikel: drei Galerie-Formen (Mosaik, Karussell, Bildstreifen), eine Bildansicht mit Blättern, Vorschaubildern und Tastatursteuerung für alle Bilder, Vorher/Nachher-Regler, Bild mit Erklärpunkten, Wertungsbalken, einen überarbeiteten Zeitstrahl und aufklappbaren Text. Freies CSS und JS für Agenten bleiben gesperrt: Agenten schreiben nur die Syntax unten, der Server erzeugt das Ausgabe-Markup. Stile und Skripte der Bausteine liegen in eigenen Nachlade-Dateien, die nur Artikel mit Bausteinen laden. Entscheidungen: ADR-029 (Phase 4).

Entwurf (freigegeben am 2026-11-13): [`artifacts/`](artifacts/README.md). Teil 1 von `artifacts/_entwurf-bausteine/bausteine.css` und `bausteine.js` ist die verbindliche Vorlage für die Theme-Dateien.

## Overview

| Phase | Inhalt | Komplexität | Status |
|---|---|---|---|
| 1 | [Agenten-Syntax: Sanitizer, Formprüfung, Style-Guide](phase-1-syntax-sanitizer.md) | standard | pending |
| 2 | [Renderer: Ausgabe-Markup der Bausteine](phase-2-renderer.md) | standard | pending |
| 3 | [Theme: bausteine.css, bausteine.js, Bildansicht](phase-3-theme.md) | standard | pending |
| 4 | [Laden, Demo-Artikel, Doku, ADR-029](phase-4-laden-demo-doku.md) | standard | pending |

## Kontrakt: Agenten-Syntax → Ausgabe

Agenten-Klassen sind englisch, Ausgabe-Klassen deutsch (wie bisher `gallery` → `galerie`). Alles, was nicht hier steht, entfernt der Sanitizer.

| Baustein | Agenten-Syntax (gespeichert) | Ausgabe des Renderers |
|---|---|---|
| Galerie-Form | `<div class="gallery" data-variant="mosaic\|carousel\|strips" data-media="UID,…">` | `<figure class="galerie" data-anzahl="N" data-variante="mosaik\|karussell\|streifen">`; ohne `data-variant` kein `data-variante` (bisheriges Raster). Karussell: alle `.foto` in `<div class="spur">`. |
| Bild-Unterschrift je Foto | – | jedes `.foto` jeder Galerie trägt `data-unterschrift` = nicht leere Teile von [Bildunterschrift des Mediums, „Foto: “ + Urheber, Lizenz] mit ` · ` verbunden |
| Vorher/Nachher | `<figure class="before-after" data-media="UID_ALT,UID_NEU" data-labels="1985\|2025"><figcaption>…</figcaption></figure>` | `<figure class="vergleich"><div class="vergleich-flaeche"><div class="vergleich-bild vorher">PICTURE<span class="vergleich-marke">1985</span></div><div class="vergleich-bild nachher">PICTURE<span class="vergleich-marke">2025</span></div></div><figcaption>Text NACHWEISE</figcaption></figure>`; ohne `data-labels` „Vorher“/„Nachher“ |
| Erklärpunkte | `<figure class="hotspots" data-media="UID"><ol><li data-x="22" data-y="35"><b>Titel</b> Text</li>…</ol><figcaption>…</figcaption></figure>` | `<figure class="erklaerbild"><div class="erklaerbild-flaeche">PICTURE</div><ol class="erklaerbild-liste"><li data-x="22" data-y="35">…</li></ol><figcaption>Text NACHWEIS</figcaption></figure>` |
| Wertung | `<ul class="rating" data-max="10" data-total="7.8"><li data-score="9">Datenqualität</li>…</ul>` | `<div class="wertung"><p class="wertung-gesamt"><span class="klein">Gesamt</span><b>7,8</b><span>von 10</span></p><ul><li><span class="wertung-name">Datenqualität</span><meter min="0" max="10" value="9">9 von 10</meter><span class="wertung-wert">9</span></li></ul></div>`; ohne `data-total` keine Gesamtzeile |
| Zeitstrahl | `<ol class="timeline"><li class="highlight"><time datetime="1850">1850</time> <b>Titel</b> Text</li>…</ol>` (bestehende Syntax, neu: `highlight`) | `<ol class="zeitstrahl"><li class="markiert"><span class="zeitstrahl-datum"><time datetime="1850">1850</time></span><div class="zeitstrahl-text"><b>Titel</b> Text</div></li></ol>` |
| Aufklappbarer Text | `<div class="accordion"><details><summary>Frage</summary><p>…</p></details>…</div>` | `<div class="akkordeon">`, jedes direkte `details` bekommt `name="akkordeon-N"` (N zählt je Artikel ab 1) |

Werte: `data-x`/`data-y` 0 bis 100 mit höchstens einer Nachkommastelle; `data-max` 5, 10 oder 100 (Standard 10); `data-score`/`data-total` 0 bis 999 mit höchstens einer Nachkommastelle; `data-labels` zwei Texte bis 40 Zeichen, getrennt durch `|`.

Laden: `ContentRenderer::render()` liefert zusätzlich `bausteine` (bool). Der Builder setzt dann `<link rel="stylesheet">` auf `bausteine.css` im Kopf der Artikelseite. `artikel.js` lädt `bausteine.js` per `import()`, sobald ein Baustein mit Skript im Text steht, und ergänzt die Stil-Datei, falls sie fehlt (Vorschau). Adressen stehen an `<html>` als `data-bausteine` und `data-bausteinestil`.

## Größengrenzen (Rohgröße in Byte, entschieden am 2026-11-13)

`bausteine.js` ≤ 8 000 · `bausteine.css` ≤ 16 000 · `artikel.js` ≤ 12 500 (vorher 7 000) · `style.css` ≤ 50 000 (vorher 48 000). Übrige Grenzen unverändert.

## Finale Abnahmekriterien

1. Jeder Baustein der Kontrakt-Tabelle übersteht das Speichern (Sanitizer) und erscheint auf der Artikelseite im Ausgabe-Markup; die Bausteine sehen aus wie im Entwurf (hell und dunkel; 390, 860, 1366 px).
2. Ohne JavaScript bleibt jeder Baustein lesbar: Karussell als wischbare Reihe, Vorher/Nachher nebeneinander, Erklärpunkte als nummerierte Liste, Wertung, Zeitstrahl und Aufklappbarer Text vollständig.
3. Die Bildansicht öffnet für jedes Bild (Einzelbild, jede Galerie-Form) per Klick, Enter oder Leertaste; ←/→ blättern innerhalb der Galerie ohne Umlauf, Pos1/Ende springen, Esc schließt, der Fokus kehrt zum Bild zurück; Vorschaubilder-Leiste ab zwei Bildern.
4. Artikel ohne Bausteine laden weder `bausteine.css` noch `bausteine.js`.
5. Die Formprüfung warnt (`component_invalid`) bei falsch befüllten Bausteinen; der Style-Guide beschreibt jede Syntax.
6. Die Größengrenzen oben halten; `php -l` ist für alle geänderten PHP-Dateien grün.

## Summary

## Files touched

## Commits

## Deviations from plan

## Follow-ups
