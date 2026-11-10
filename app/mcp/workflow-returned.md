# Workflow: zurückgegebener Entwurf

Der Inhaber hat einen Entwurf mit einer Anmerkung zurückgegeben. Er steht im Briefing unter `returned`.

1. **Anmerkung lesen:** `get_post(id, include_html=true)` liefert den Entwurf mit der Anmerkung in `review_note`.
2. **Gezielt nachbessern:** nur ändern, was `review_note` verlangt. Bei Bedarf nachrecherchieren und Quellen ergänzen; alles andere bleibt, wie es ist. Lies `get_style_guide`, bevor du HTML änderst.
3. **Prüfen und erneut einreichen:** `update_post`, dann `validate_post`, bis keine Fehler bleiben, zuletzt `submit_post`.

**Abschlussbericht:** Titel, Artikel-ID, was sich geändert hat, Zahl der Quellen, offene Punkte.
