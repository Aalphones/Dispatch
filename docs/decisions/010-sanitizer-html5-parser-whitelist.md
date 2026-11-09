# 010 — Sanitizer mit HTML5-Parser und Whitelist

**Status:** Akzeptiert (2026-11-09)

## Kontext

Artikel und Dokumente kommen als HTML von Agenten und werden auf derselben Domain angezeigt, auf der der Redaktionsmodus läuft. Eine XSS-Lücke wäre eine Lücke im Redaktionszugang (Critical Rule 3 in `conventions/php.md`).

## Entscheidung

- **Parser:** `Dom\HTMLDocument` (PHP 8.4+, HTML5-Parser). Er baut denselben Baum wie ein Browser; der Sanitizer sieht also, was später gerendert wird.
- **Verfahren:** Whitelist. Erlaubte Elemente bleiben, gefährliche verschwinden samt Inhalt, unbekannte werden ausgepackt (Inhalt bleibt). Attribute, Klassen und Link-Ziele werden einzeln gegen feste Muster geprüft; was nicht passt, wird entfernt, der Rest des Elements bleibt. Elemente fremder Namensräume (SVG, MathML) fliegen immer.
- **Rückgabe:** bereinigtes HTML plus deutsche Hinweise (`SanitizeResult`), damit der Agent erfährt, was geändert wurde. Der Lauf ist idempotent.
- **Wo:** `Util/Sanitizer`. Die Listen und Muster stehen im Code, nicht hier.

## Verworfen

- **`DOMDocument::loadHTML` (libxml, HTML4-Parser):** liest anders als der Browser; Markup, das der Sanitizer als harmlos sieht, kann im Browser einen anderen Baum ergeben (Mutation-XSS).
- **Blacklist gefährlicher Tags:** neue Elemente und Attribute wären standardmäßig erlaubt.

## Konsequenzen

- Jede neue Komponenten-Klasse oder jedes neue `data-*`-Attribut des Designs muss in die Listen im Sanitizer, sonst wird es gestrichen.
- Abnahme ist die XSS-Fallliste aus Phase 3 des Plans (Handprüfung, ADR-004).
