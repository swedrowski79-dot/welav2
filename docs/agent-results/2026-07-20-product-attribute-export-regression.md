# Task

Analyse und Korrektur, warum Produktattribute nach der Überarbeitung von `wela-api` nicht mehr in XT geschrieben werden.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/DeltaRunnerService.php`
- `config/delta.php`
- `docs/agent-results/2026-07-17-attribute-dictionary-rebuild.md`

# Changed files

- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-07-20-product-attribute-export-regression.md`

# Summary

- Die Ursache liegt vor der XT-API im Produkt-Delta.
- Die neue Dictionary-Abkürzung ersetzte die Stage-Quelle `stage_attribute_translations` durch einen Aufbau aus `stage_products`.
- `stage_products` enthält keine Spalten `attribute_name1..4` beziehungsweise `attribute_value1..4`; dadurch entstand für alle Produkte ein leeres `data.attributes`-Array in der Export-Queue.
- Die Abkürzung wurde entfernt. Der Produkt-Delta nutzt wieder die konfigurierte interne Stage-Quelle `stage_attribute_translations`.
- In der vorhandenen Queue wurden 10.248 bereits erledigte Produkt-Exporte mit Stage-Attributen, aber leerem Attribut-Payload nachgewiesen. Diese historischen Exporte werden durch die Codekorrektur nicht automatisch wiederholt.

# Open points

- Für die bereits exportierten Produkte muss nach der Bereitstellung ein neuer Delta-Lauf und danach der Export-Worker ausgeführt werden, damit die fehlenden XT-Attributdaten nachgezogen werden.
- Die nun ungenutzten Dictionary-Hilfsmethoden im `ProductDeltaService` können in einem separaten Cleanup entfernt werden; sie beeinflussen den korrigierten Exportpfad nicht.

# Validation steps

- PHP-Syntaxprüfung für `src/Service/ProductDeltaService.php` ausführen.
- Anschließend `docker compose exec php php run_delta.php` starten und für ein Produkt mit Attributen prüfen, dass `export_queue.payload.data.attributes` nicht leer ist.
- Danach den Export-Worker ausführen und einen XT-Mirror-Refresh starten; die Attribute und Zuordnungen in den Mirror-Tabellen gegen `stage_attribute_translations` vergleichen.

# Recommended next step

Code bereitstellen, dann Delta, Export-Worker und XT-Mirror gezielt ausführen, um die 10.248 historischen Produkte erneut mit Attributen zu exportieren.
