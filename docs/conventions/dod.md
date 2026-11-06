# Definition of Done — DISPATCH

**Pflicht, bevor eine Aufgabe abgehakt wird.** Es gibt keine automatisierten Tests (ADR-004); diese Handprüfung ist die einzige Prüfebene.

- Jeden DoD-Punkt einzeln durchgehen und im Abschlussbericht sagen, was konkret geprüft wurde: Adresse aufgerufen, Statuscode, Antwort, Seite im Browser geöffnet, Konsole geprüft.
- „Die CI ist grün" belegt nur die Syntax, nicht das Verhalten.
- Sicherheitsrelevante Punkte (Sanitizer, JWT, Scopes, OAuth, Sperre von `dispatch-app/`) brauchen eine Liste der durchgespielten Fälle, inklusive der Fälle, die abgewiesen werden müssen.
- Alles, was die Wurzel-`.htaccess` oder den Builder berührt, schließt mit dem Nachweis, dass `/card/` und `/scanner/capture` weiter antworten.

## Wenn hier nicht prüfbar

Braucht ein Punkt den echten Strato-Server oder Daten, die nur der Inhaber hat: das ausdrücklich sagen, um Bestätigung bitten, den Punkt nicht stellvertretend abhaken.

## Critical Rules

1. **Kein Haken ohne Beleg** — ein stiller Fehler (Route nie verdrahtet, Header nie angekommen) fällt sonst erst im Betrieb auf.
