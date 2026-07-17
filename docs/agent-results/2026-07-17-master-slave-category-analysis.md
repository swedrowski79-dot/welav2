Task
- Master-/Slave-Kategoriezuordnung so anpassen, dass beim XT-Export alle Slave-Artikel immer die Warengruppe des jeweiligen Master-Artikels verwenden.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- config/merge.php
- config/xt_write.php
- src/Service/MergeService.php
- src/Service/StageProductVariantLinkService.php
- src/Service/XtProductWriter.php

Changed files
- src/Service/XtProductWriter.php
- docs/agent-results/2026-07-17-master-slave-category-analysis.md

Summary
- Die Kategorie kommt aktuell zunaechst direkt aus `raw_afs_articles.category_afs_id` nach `stage_products.category_afs_id`.
- Vor der Aenderung nutzte `XtProductWriter::resolvedCategoryId()` bei Slaves zuerst deren eigene `category_afs_id` und fiel nur bei leerer Kategorie auf den Master zurueck.
- Die Export-Logik wurde jetzt so geaendert, dass fuer alle Slave-Artikel ausschliesslich die Kategorie des Masters ueber `master_sku` aufgeloest wird.
- Master- und normale Einzelartikel behalten weiterhin ihre eigene `category_afs_id`.
- Die Stage-Daten wurden bewusst nicht geaendert; die Vereinheitlichung findet nur im XT-Export statt und bleibt damit ein kleiner, gezielter Eingriff.

Open points
- Die Aenderung wurde nur im Export umgesetzt. Falls dieselbe Logik spaeter auch in der Stage sichtbar sein soll, muesste der Merge-/Expand-Pfad separat angepasst werden.

Validation steps
- `docker compose exec php php -l src/Service/XtProductWriter.php`

Recommended next step
- Einen Exportlauf mit einigen bekannten Master-/Slave-Artikeln pruefen und kontrollieren, dass die Slaves im Payload dieselbe Kategorie wie der Master erhalten.
