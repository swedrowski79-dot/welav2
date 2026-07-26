# Produkttexte aus Übersetzungen

## Task

Prüfen, ob Einleitungstext und Beschreibung aus den Artikel-Übersetzungen an XT übergeben werden, und fehlende Texte korrigieren.

## Files read

- `AGENTS.md`
- `database.sql`
- `config/merge.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`

## Changed files

- `docs/agent-results/2026-07-23-product-translation-text-export.md`

## Summary

- Die bestehende Zuordnung ist korrekt: `intro_text` wird in Stage zu `short_description` und nach XT zu `products_short_description`; die Beschreibung einschließlich technischer Daten wird nach `products_description` geschrieben.
- Queue-Payload und API-Payload enthalten die Texte korrekt.
- 5.384 bestehende Produkt-Payloads mit leeren XT-Textfeldern wurden gezielt neu exportiert.
- Weitere 75 Produkte ohne passenden historischen Queue-Payload wurden direkt mit den aktuellen Stage-Daten exportiert.
- HA-000 und AD-000 wurden jeweils in Deutsch, Englisch, Französisch und Niederländisch kontrolliert.
- Der nach einem Deadlock wartende Queue-Eintrag wurde erfolgreich wiederholt.

## Open points

- `STW1K230V` (64471) und `STW1K24V` (64472) können nicht exportiert werden, weil ihre Kategorie 380 weder in `stage_categories` noch in XT existiert. Dadurch fehlen die Produkte selbst und folglich ihre Texte in XT.

## Validation steps

- Stage- und XT-Vergleich für DE-Einleitung und DE-Beschreibung, jeweils nur bei gefüllter Stage-Quelle.
- Kontrollabfrage für HA-000 und AD-000 in allen vier Sprachen.
- Export-Queue-Retry für Eintrag 40370, anschließend Status `done`.

## Recommended next step

Die fehlende Kategorie 380 in der AFS-/Stage-Kategoriequelle klären und danach die beiden betroffenen Produkte erneut exportieren.
