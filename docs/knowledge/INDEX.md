# Knowledge Index — DISPATCH

Projekt-eigener Wissensbestand: Fakten und Referenzen, die für DISPATCH gelten und nicht anderswo stehen. Ein Eintrag pro Fakt, kurz; Details bei Bedarf in eigener Datei.

- **Webroot von quantum-canvas.de** — `www/` enthält `card/`, `api/`, `backend/` (CardMaker) und `scanner/` (CamScanner); der einzige SFTP-Zugang sieht nur `www/`. Quelle: CardMaker ADR-020.
- **CamScanner unter `/scanner/`** — eigene `.htaccess` mit `RewriteEngine On` und Fallback auf `index.html`: Rewrites der Wurzel greifen dort nicht, ein `ErrorDocument` der Wurzel nie. **Header vererben sich aber:** CamScanner setzt selbst COOP/COEP und Cache-Control nur für einzelne Dateitypen, lädt WebAssembly, Worker und ONNX-Modelle. Eine CSP oder Cache-Regel des Blogs ohne Ausnahme würde dort mitgelten und die KI-Funktionen brechen — der Ausnahme-Block aus ADR-003 ist Pflicht. Sein Deploy gleicht nur `/scanner/` ab und verweigert `/` als Ziel. Quelle: CamScanner `public/.htaccess`, `deploy.cmd`, ADR-004.
- **Quellen des Fachkonzepts** — MCP 2026-07-28, Claude-Connector-Authentifizierung (CIMD): [`../design/konzept-blog-engine.md`](../design/konzept-blog-engine.md) → „Quellen".
