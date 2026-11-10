# Workflow: Auftrag aus der Queue

Ein Lauf bearbeitet genau einen Auftrag. Die Redaktionsrichtlinien aus dem Briefing sind verbindlich.

1. **Reservieren:** `claim_queue_item` (ohne `id` den nächsten offenen Auftrag, mit `id` einen bestimmten). Die Reservierung sperrt den Auftrag gegen doppelte Bearbeitung; abgelaufene Reservierungen fallen automatisch zurück. Lies `get_style_guide` einmal, bevor du schreibst.
2. **Material sichten:** Titel, Auftragstext, Links und Anhänge des Auftrags auswerten. Video und Audio: nur Metadaten, Inhalt aus dem Auftragstext entnehmen.
3. **Tief recherchieren:** Bei `depth = deep` gelten `form_rules.min_sources_deep` und `form_rules.min_words_deep`, sonst `min_sources` und `min_words`. Primärquellen zuerst, zentrale Zahlen aus zwei unabhängigen Quellen.
4. **Eigenes Material verwenden:** Anhänge mit `own_material` tragen als Urheber den Namen des Inhabers und als Lizenz „Eigenes Material“. Alle anderen Bilder brauchen Urheber, Lizenz und Herkunftsseite; ergänze sie mit `update_media`.
5. **Schreiben, prüfen, einreichen:** `create_post` mit `queue_item_id` (Quellen, Belege im Text, Titelbild), dann `validate_post`, bei Fehlern `update_post`, zuletzt `submit_post`. Der Auftrag wechselt auf `review` und ist mit der Freigabe erledigt.
6. **Abbrechen statt abschwächen:** Lässt sich das Thema nicht sauber belegen, ruf `release_queue_item(status="failed", note=…)` mit Begründung auf. Hast du schon einen Entwurf angelegt, verwirf ihn vorher mit `delete_post`. Ein dünner Artikel landet nie in der Freigabe.

**Abschlussbericht:** Titel, Artikel-ID, Zahl der Quellen, offene Punkte.
