## Task

Pruefen und beheben, warum Produkt-Kategorien ueber die XT-API nicht korrekt uebertragen werden.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/xt_write.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`
- `wela-api-xt/src/ProductSyncService.php`
- `wela-api-xt/index.php`

## Changed files

- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-18-product-category-slave-fallback-fix.md`

## Summary

- Ursache gefunden: Bei Slave-Artikeln wurde die eigene `category_afs_id` ignoriert.
- Der Produkt-Writer nahm fuer Slave-Artikel immer zuerst die Kategorie des Master-Artikels und nur indirekt nie die eigene.
- Fix umgesetzt: Eigene Produkt-Kategorie hat jetzt Vorrang; nur wenn sie leer ist, wird auf die Master-Kategorie zurueckgefallen.
- Verifiziert an `entity_id=65004`: vorher wurde `categories_id=262` erzeugt, nach dem Fix korrekt `categories_id=30` fuer `category_afs_id=87`.

## Open points

- Das System uebertraegt derzeit weiterhin genau eine Kategorie-Relation pro Produkt. Falls kuenftig Mehrfach-Kategorien noetig sind, braucht die Stage-/Payload-Struktur eine eigene Erweiterung.

## Validation steps

- `php -l src/Service/XtProductWriter.php`
- Reflection-Test im `php`-Container gegen echten Queue-Eintrag `65004`
- Verifiziert, dass `category_relations` nach dem Fix die eigene Produkt-Kategorie statt der Master-Kategorie verwendet

## Recommended next step

- Betroffene Artikel erneut auf `pending` setzen und den Produkt-Export neu laufen lassen, damit falsche Kategorie-Zuordnungen auf XT korrigiert werden.
