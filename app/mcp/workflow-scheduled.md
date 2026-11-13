# Workflow: geplanter Artikel

Ein Lauf schreibt genau einen Artikel. Die Redaktionsrichtlinien aus dem Briefing sind verbindlich.

1. **Briefing lesen:** `get_editorial_brief(mode="auto")` hast du schon getan; es hat diesen Weg gewählt, weil weder ein zurückgegebener Entwurf noch ein offener Queue-Auftrag wartet. Lies `get_style_guide` einmal, bevor du schreibst.
2. **Thema wählen:** Kategorien mit hohem Rotationswert bevorzugen; Leserinteresse (gefragte Kategorien, Suchen ohne Treffer) und die Reaktionen „Mehr davon“ / „Weniger“ sind Hinweise, keine Vorgaben. Mit `search_posts` und der Liste der letzten Artikel Dubletten ausschließen. Einen aktuellen Aufhänger der letzten 14 Tage suchen: Studie, Entscheidung, Veröffentlichung, Jahrestag.
3. **Recherchieren und prüfen** mit den Werkzeugen deines Clients (Websuche, Seitenabruf): mindestens so viele Quellen, wie `form_rules.min_sources` verlangt, Primärquellen zuerst, zentrale Zahlen aus zwei unabhängigen Quellen. Verworfenes und Offenes kommt in `editor_notes`.
4. **Bebildern:** Bilder nach dem Ampelsystem der Redaktionsrichtlinien (Abschnitt 5) recherchieren — eigenes Material, freie Lizenzen, offizielle Pressebereiche, im begründeten Einzelfall Bildzitate — und mit `import_media_from_url` oder `upload_media` übergeben, mit Urheber, Rechtsgrundlage im Feld `license`, Lizenz-Link, Herkunftsseite, Alt-Text und Bildunterschrift. Bearbeitung und Begründungen für gelbe Fälle kommen je Medien-UID in `editor_notes`. Eigene Diagramme als PNG mit `upload_media`.
5. **Schreiben:** `create_post` mit Quellen, Belegen im Text, Titelbild (`cover_media_uid`), passendem Layout und Akzentfarbe. Prüfe `sanitizer_notes`: Was dort steht, hat der Server entfernt.
6. **Form prüfen:** `validate_post` meldet fehlende oder unstimmige Pflichtangaben; ergänze mit `update_post`, bis keine Fehler mehr bleiben. Die inhaltliche Prüfung machst du vorher selbst.
7. **Zur Freigabe einreichen:** `submit_post` mit `model` (dein Modell, z. B. "Claude Sonnet 5.5"). Veröffentlichen kann nur der Inhaber.

**Abschlussbericht:** Titel, Artikel-ID, Zahl der Quellen, offene Punkte.
