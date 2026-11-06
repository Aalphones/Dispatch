# 001 — Pfade auf der gemeinsamen Domain

**Status:** Akzeptiert (2026-11-06)

## Kontext

DISPATCH soll direkt unter `https://quantum-canvas.de/` laufen. Im selben Webroot `www/` liegen schon andere Anwendungen, die erreichbar bleiben müssen:

```
www/
  card/      CardMaker, Oberfläche
  api/       CardMaker, API-Brücke (Routen tragen /api fest)
  backend/   CardMaker, Programmcode, gesperrt
  scanner/   CamScanner (u. a. /scanner/capture)
```

Das Konzept sah eine eigene Subdomain vor, mit `/api/*`, `/mcp` und `/oauth/*` an der Wurzel. `/api/` ist hier belegt, und eine Subdomain mit Zertifikat bietet das Paket nicht.

## Optionen

- (a) Alle PHP-Endpunkte unter einem eigenen Präfix `/dispatch/`; nur `/.well-known/oauth-*` an der Wurzel.
- (b) Nur die Redaktions-API umbenennen, `/mcp` und `/oauth/` an der Wurzel lassen.
- (c) CardMakers API umziehen.

## Entscheidung

**(a).**

```
www/
  .htaccess       ← DISPATCH (ADR-003)
  index.html, artikel/, kategorie/, assets/, media/, feed.xml, …   ← vom Builder erzeugt
  dispatch/       ← Brücke: einziger PHP-Einstieg (MCP, API, OAuth)
  dispatch-app/   ← Programmcode, Daten, .env — gesperrt (ADR-002)
  card/ api/ backend/ scanner/   ← fremd
```

- `https://quantum-canvas.de/dispatch/mcp` ist die MCP-Adresse, `…/dispatch/api/*` die Redaktions-API, `…/dispatch/oauth/*` der OAuth-Server.
- `/.well-known/oauth-protected-resource/dispatch/mcp`, `/.well-known/oauth-protected-resource` und `/.well-known/oauth-authorization-server` schreibt die Wurzel-`.htaccess` auf die Brücke um. Andere Pfade unter `/.well-known/` (z. B. Zertifikats-Prüfungen) bleiben unberührt.
- **Reservierte Namen auf der obersten Ebene:** `card`, `api`, `backend`, `scanner`, `dispatch`, `dispatch-app`, `.htaccess`, `.well-known`. Der Builder schreibt dort nie hinein und lehnt Slugs ab, die auf oberster Ebene damit kollidieren würden. Die Liste steht in `app/config.php`.
- Der Builder löscht nur Dateien, die in seinem eigenen Manifest stehen.

## Konsequenzen

- Die Wurzel bleibt rein statisch; PHP sieht von Besuchern nur den Zähl-Aufruf unter `/dispatch/api/hit`.
- Kommt eine weitere Anwendung auf die Domain, gehört ihr Ordnername in die Liste der reservierten Namen und in die Ausnahmen der Wurzel-`.htaccess` (ADR-003).
- `robots.txt` sperrt `/dispatch/` und `/dispatch-app/`, nicht `/api/` — das gehört CardMaker.
