# Slave-SEO über Master-Kategorie

## Task

Die Kategorieauflösung für Slave-Artikel so korrigieren, dass ihre SEO-URL und Kategoriezuordnung die Kategorie des Master-Artikels verwenden.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Service/XtProductWriter.php`
- `src/Service/StageCategoryMap.php`
- `wela-api/src/ProductSyncService.php`
- `wela-api/seo_helpers.php`
- `docs/agent-results/2026-07-17-master-slave-category-analysis.md`
- `docs/agent-results/2026-07-18-product-category-slave-fallback-fix.md`

## Changed files

- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-20-slave-master-category-seo-fix.md`

## Summary

Bei Slaves wird zuerst die Kategorie des Masters über `master_sku` aufgelöst. Nur wenn diese nicht auflösbar ist, verwendet der Export die eigene Slave-Kategorie als Fallback.

Live-Befund vor der Änderung für die HA-Slaves:

- Master HA-000: Kategorie `272` / XT-Kategorie `Absaugarm` / URL-Pfad `absaugarme`.
- Slaves: eigene Kategorie `173` / XT-Kategorie `Absaugarme ECON` / URL-Pfad `absaugarme-econ`.

Dadurch wurden die Slave-URLs unter dem falschen Kategoriepfad erzeugt. Mit der Änderung wird für die HA-Slaves wieder die Master-Kategorie genutzt.

## Open points

- Die betroffenen Slaves müssen erneut exportiert werden, damit Kategorie-Relation und SEO-URL in XT umgestellt werden.

## Validation steps

- Vergleich der Stage-Kategorien und der live freigegebenen XT-Datenbank für HA-000 und seine Slaves.
- PHP-Syntaxprüfung für `src/Service/XtProductWriter.php`.
- Lokaler Auflösungstest: HA-1620 wird auf Master-Kategorie `272` aufgelöst.
- HA-000 sowie HA-1620, HA-1620-P, HA-1630 und HA-1630-P gezielt exportiert: 5 von 5 erfolgreich.
- Live geprüft: Alle vier Slaves sind mit XT-Kategorie `46` / External-ID `272` (`Absaugarm`) verknüpft. Ihre deutschen SEO-URLs beginnen nun mit `de/absaugarme-zubehoer/absaugarme/`.

## Recommended next step

HA-000 sowie die vier HA-Slaves gezielt erneut exportieren und danach die deutsche SEO-URL und Kategorie-Relation prüfen.
