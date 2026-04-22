## Task

XT-Kategoriebaum weiter eingrenzen und den Datenfehler beheben, der Hauptkategorien im Shop unsichtbar macht und Kategorie-/Sprachrouting instabil werden laesst.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `src/Service/XtCategoryWriter.php`
- `src/Service/StageCategoryMap.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-17-shop-refresh-and-offline-cleanup.md`

## Changed files

- `src/Service/XtCategoryWriter.php`
- `docs/agent-results/2026-04-17-category-level-routing-fix.md`

## Summary

- Die Live-/Mirror-Daten zeigen einen inkonsistenten XT-Kategoriebaum:
  - Root-Kategorie `external_id = 115` liegt mit `categories_level = 0` im Shop.
  - Ihre direkte Child `external_id = 272` liegt mit `categories_level = 2` im Shop.
- Die interne Stage-Baumlogik liefert fuer denselben Pfad korrekt:
  - `115 => depth 0`
  - `272 => depth 1`
- Daraus folgt: XT erwartet fuer `categories_level` einen 1-basierten Wert, waehrend der Export zuletzt 0-basiert geschrieben hat.
- Der Kategorieexport schreibt `categories_level` jetzt XT-kompatibel als `depth + 1`.

## Open points

- Die geaenderte Writer-Logik muss noch durch den Kategorieexport in den Zielshop geschrieben werden.
- Danach muss erneut geprueft werden, ob
  - die Hauptkategorien wieder sichtbar sind
  - die Kategorie-404s in `en/fr/nl` verschwinden
- Der bestehende Queue-Blocker `The table 'export_queue' is full` kann die vollstaendige Auslieferung weiterhin verhindern.

## Validation steps

- Vergleich StageCategoryMap:
  - `115: depth=0, parent=NULL, top=1`
  - `272: depth=1, parent='115', top=0`
- Vergleich XT-Mirror:
  - `103 / external_id 115 / parent_id 0 / categories_level 0`
  - `46 / external_id 272 / parent_id 103 / categories_level 2`
- Live-Storefront-Pruefung:
  - Kategorie `103` funktioniert in `de/en`, aber `fr/nl` liefern 404
  - Kategorie `46` liefert in den Fremdsprachen 404

## Recommended next step

Kategorieexport erneut laufen lassen und danach die Root-/Child-Kategorien im Shop in `de/en/fr/nl` direkt gegenpruefen. Wenn der Worker erneut stoppt, zuerst den Queue-Speicherblocker beseitigen.
