# 004 — Keine automatisierten Tests

**Status:** Akzeptiert (2026-11-06)

## Kontext

Das Konzept nennt PHPUnit als Entwicklungs-Abhängigkeit und Tests in mehreren Abnahmen (Sanitizer, Formregeln, Queue-Reservierung, End-to-End mit MCP-SDK). DISPATCH ist ein Ein-Personen-Projekt auf Shared Hosting ohne Composer.

## Entscheidung

Keine automatisierten Tests, kein Test-Framework, keine Test-Abhängigkeit. Absicherung über strikte Typen, PHP-Syntaxprüfung in der CI und die manuelle Abnahme je Phase ([`conventions/dod.md`](../conventions/dod.md)). Wo das Konzept einen Test als Abnahme nennt, wird der Fall von Hand durchgespielt und im Abschlussbericht belegt.

## Konsequenzen

- Für sicherheitskritische Teile (Sanitizer, JWT, OAuth-Rotation) braucht die Abnahme eine ausdrückliche Liste der durchgespielten Fälle, z. B. die bekannten XSS-Muster und manipulierte JWTs (`alg: none`, falscher Schlüssel, abgelaufen).
- Kein `testing.md` in `docs/conventions/`.
