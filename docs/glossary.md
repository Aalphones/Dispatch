# Glossar — DISPATCH

Ein Begriff, eine Bedeutung. Fachbegriffe in Code, Docs und Commits nur so verwenden, wie sie hier stehen.

| Begriff | Bedeutung |
|---|---|
| Inhaber | Das einzige Konto; meldet sich im Redaktionsmodus an und gibt frei. |
| Einreicher | Wer per MCP Artikel oder Themen einreicht, meist ein Agent in Claude Code; verantwortet die inhaltliche Qualität. |
| Freigabe | Die Handlung des Inhabers, die einen Arbeitsstand zum Live-Stand macht. Kein Token kann sie auslösen. |
| Arbeitsstand | Die aktuelle Revision eines Artikels, an der gearbeitet wird (`revision`). |
| Live-Stand | Die freigegebene Revision, aus der der Builder baut (`published_revision`). |
| Formprüfung | Prüft Pflichtangaben auf Vollständigkeit und Stimmigkeit, nicht auf Wahrheit (`validate_post`). |
| Briefing | Antwort von `get_editorial_brief`: Richtlinien, Kontext und genau ein Workflow für den Lauf. |
| Queue / Auftrag | Eingereichtes Thema, das ein Agentenlauf reserviert und abarbeitet (`queue_items`). |
| Rotationswert | Gewicht einer Kategorie × Tage seit dem letzten Artikel darin, gedeckelt bei 90. |
| Änderungsvorschlag | Eine per MCP gewünschte Änderung an öffentlich Sichtbarem, die erst nach Übernahme durch den Inhaber wirkt (`changes`). |
| Redaktionsmodus | Nachgeladenes JavaScript, das nach dem Login Redaktionsfunktionen in die öffentliche Website einblendet. |
| Builder | Erzeugt aus den Live-Ständen die statische Website in der Wurzel. |
| Manifest | Liste aller Dateien, die der Builder erzeugt hat; nur was darin steht, darf er löschen. |
| Brücke | `www/dispatch/index.php`: der einzige PHP-Einstieg, bindet den gesperrten Code ein. |
| Nachbar-Anwendung | Andere Anwendung im selben Webroot: CardMaker (`/card/`, `/api/`, `/backend/`), CamScanner (`/scanner/`). |
| Reservierter Name | Ordner- oder Dateiname auf oberster Ebene, den der Builder nie belegt. |
| MCP-Token | Festes Bearer-Token mit Präfix `dmcp_` für Claude Code und geplante Aufgaben. |
| Scope | Rechtebereich eines Tokens: `blog:read`, `blog:write`, `blog:admin`, `offline_access`. |
| CIMD | Client ID Metadata Document: Claude weist sich per URL auf ein Metadaten-Dokument aus, statt sich zu registrieren. |
| Deploy-Token | `DISPATCH_DEPLOY_TOKEN`; erlaubt nur Migration und Rebuild nach dem Deploy. |
| Akteur | Wer eine Änderung auslöst (`Actor`): `owner`, `cli`, `deploy`, `mcp:<name>`, `oauth:<name>`. Nur `owner` ist der Inhaber; der Name steht im Protokoll. |
| Startinhalte | Markdown-Vorlagen in `app/defaults/` (Richtlinien, Präferenzen, Über, Impressum, Datenschutz); der Seeder legt sie an, überschreibt aber nie Vorhandenes. |
