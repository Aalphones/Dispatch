# PHP Conventions — DISPATCH

> **Source-of-truth references:**
> - [PSR-12](https://www.php-fig.org/psr/psr-12/), [PSR-4](https://www.php-fig.org/psr/psr-4/)
> - Fachkonzept: [`../design/konzept-blog-engine.md`](../design/konzept-blog-engine.md) → „Hinweise für die Umsetzung"
>
> Projekt-Regeln unten haben Vorrang vor allgemeinen Coding-Style-Regeln.

## Stack

| Layer | Choice |
|---|---|
| PHP | 8.5, `declare(strict_types=1)` in jeder Datei |
| Framework | keins; eigener PSR-4-Autoloader `Dispatch\` → `app/src/` |
| DB | MySQL, PDO, `utf8mb4`, InnoDB, ausschließlich Prepared Statements |
| Abhängigkeiten | keine — kein Composer, kein `vendor/` |
| Tests | keine (ADR-004) |

## Library Policy

**Forbidden:**
- Composer-Pakete jeder Art — Shared Hosting ohne Composer, und das Konzept schließt Laufzeit-Abhängigkeiten aus.
- Kryptografie-Eigenbau jenseits der PHP-Bordmittel.

**Allowed:**
- `random_bytes`, `hash`, `hash_hmac`, `hash_equals`, `password_hash`/`password_verify` — für Tokens, JWT (HS256 fest), signierte Links.
- `DOMDocument` für den Sanitizer, GD für Bildvarianten, `curl` für den HTTP-Client mit SSRF-Schutz.

## Regeln

- PSR-12, typisierte Properties, Parameter und Return-Types überall.
- Services kennen kein HTTP: kein `$_SERVER`, `$_GET`, `header()` in `Domain/`. Sie werden von MCP, API und CLI gleich genutzt.
- Jede Änderung an Inhalten erzeugt eine Revision und einen Audit-Eintrag mit Akteur.
- Zeitstempel als `DATETIME` in UTC, geschrieben mit `UTC_TIMESTAMP()` und **in der Abfrage** verglichen (`expires_at > UTC_TIMESTAMP()`), nie in PHP.
- Mehrere Tabellen ändern → Transaktion. **Ausnahme Migrationen:** DDL nie in eine PDO-Transaktion packen — MySQL committet DDL implizit, der spätere `commit()` wirft „There is no active transaction".
- Kein `@` zur Fehlerunterdrückung.
- Fehler der API immer als JSON `{"error": "…", "message": "…"}` mit passendem Status. Fachliche Fehler im MCP als Tool-Ergebnis mit `isError: true` und einem Text, der sagt, wie es richtig geht.
- `.env`-Werte stehen in einfachen Anführungszeichen; der eigene Parser entfernt sie.
- Kommentare nur, wo das Warum nicht offensichtlich ist (Strato-Eigenheit, Sicherheitsgrund, Invariante).

## Project Layout

Siehe [`../code-map.md`](../code-map.md).

## Critical Rules

1. **Prepared Statements ausnahmslos** — auch für interne Abfragen.
2. **Kein Token außer dem Login des Inhabers kann veröffentlichen** — Freigabe, Übernahme von Vorschlägen und Zurückziehen hängen an keinem Scope.
3. **Eingereichtes HTML läuft immer durch den Sanitizer, bevor es gespeichert wird** — der Redaktionsmodus läuft auf derselben Domain, eine XSS-Lücke wäre eine Lücke im Redaktionszugang.
4. **Ausgehende Anfragen nur über den HTTP-Client mit SSRF-Schutz** — Medienimport und CIMD-Abruf laden fremde URLs.
5. **Der JWT-Algorithmus steht serverseitig fest** — ein `alg` aus dem Token wird nie übernommen.
