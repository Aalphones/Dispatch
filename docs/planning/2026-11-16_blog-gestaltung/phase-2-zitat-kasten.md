# Phase 2 — Zitat-Kasten (standard)

## Kontext (lesen)
`app/src/Util/Sanitizer.php` (ALLOWED_ELEMENTS/CLASSES, `keptAttributeValue`, Linkprüfung Z. ~361), `app/src/Site/ContentRenderer.php` (Beleg-Fußnoten, `renderEmbeds` als Muster), `app/theme/bausteine.css`, `app/mcp/style-guide.md`, `app/src/Util/FormCheck.php`. Die Klasse `pullquote` bleibt (Hervorhebung ohne Quelle).

## Entscheidungen
- Markup nach HTML-Standard: `<figure class="zitat"><blockquote cite="https://…"><p>Text</p></blockquote><figcaption><span class="zitat-wer">Person/Institution</span>, <cite>Titel des Werks</cite>, Fundstelle</figcaption></figure>`. Rechtszitat wie im Beispiel: Fundstelle (`§ 327e Abs. 3 BGB`) in der figcaption, Gesetz/Werk in `<cite>`.
- Sanitizer: Klasse `zitat`, Attribut `blockquote cite` (nur http/https wie `a href`). `figcaption` ist erlaubt.
- ContentRenderer: aus `cite` einen Link „Quelle ansehen“ in die figcaption setzen (`rel="noopener nofollow"`); ohne `cite` kein Link. Anführungszeichen per CSS (`quotes: „ “ ‚ ‘`), nicht im Text. Auslassungen `[…]` bleiben Text.
- Optik: Teal-Randbalken links, große Anführungszeichen als `::before`, Quelle klein, Fläche `--paper-2`; Druck: Rand statt Fläche.

## AK
1. `blockquote[cite]` überlebt den Sanitizer; `cite="javascript:…"` wird entfernt und gemeldet. 2. Ohne Skript und im Druck lesbar. 3. Hell/Dunkel Kontrast ≥ 4,5:1. 4. Style-Guide nennt die Syntax mit Beispiel inkl. Rechtszitat.

## Checkliste
- [ ] Sanitizer (Klasse, cite-Attribut)
- [ ] ContentRenderer (Quellenlink)
- [ ] bausteine.css
- [ ] style-guide.md
- [ ] code-map: Zeile „Gestaltungs-Bausteine“ ergänzen

## Report-Back
