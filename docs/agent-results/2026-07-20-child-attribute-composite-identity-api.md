# Child-Attribute: eindeutige Modell-/Parent-Kombination

## Task

Die lokale API-Vorlage `wela-api` so ändern, dass Child-Attribute über `attributes_model` und `attributes_parent` eindeutig behandelt werden.

## Files read

- `wela-api/src/ProductSyncService.php`
- `wela-api/index.php`
- `src/Service/XtProductWriter.php`
- `wela-api-xt/README.md`

## Changed files

- `wela-api/src/ProductSyncService.php`
- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-20-child-attribute-composite-identity-api.md`

## Summary

- Parent-Attribute werden weiterhin über ihren Modellnamen und Parent `0` aufgelöst.
- Child-Attribute verwenden intern den zusammengesetzten Schlüssel `attributes_model|attributes_parent`.
- Damit ist `160mm` unter Parent `534` eindeutig, kann aber unter einem anderen Parent als eigener Wert bestehen.
- Attributbeschreibungen und Produktrelationen lösen Child-IDs mit derselben Kombination auf und können dadurch nicht mehr auf einen gleichnamigen Wert eines anderen Parents zeigen.
- Der Writer übergibt für Child-Beschreibungen zusätzlich das Parent-Modell an die API.

## Open points

- `wela-api-xt` muss mit dieser lokalen Vorlage aktualisiert werden.
- Das XT-Schema muss doppelte `attributes_model`-Werte zulassen, sofern sie unterschiedliche `attributes_parent`-Werte haben.

## Validation steps

- `php -l wela-api/src/ProductSyncService.php` erfolgreich.
- `php -l src/Service/XtProductWriter.php` erfolgreich.

## Recommended next step

Die angepassten Dateien nach `wela-api-xt` kopieren, dort per PHP-Lint prüfen und mit einem Wert testen, der unter zwei verschiedenen Parents vorkommt.

## Exportlauf

`wela-api-xt/src/ProductSyncService.php` wurde mit der lokalen Vorlage abgeglichen. Nach XT-Mirror-Refresh und Delta-Lauf wurden 5.219 Produkt-Updates erzeugt. Der Export läuft in 500er-Batches; bei der Zwischenprüfung waren 1.779 Einträge erfolgreich bestätigt, 500 in Verarbeitung und 2.940 wartend.

## Duplikatkorrektur

Die Live-Prüfung zeigte sechs identische Child-Attribute: IDs `921`, `925`, `928`, `931`, `934` und `937` sind alle `attributes_model = stehend` unter `attributes_parent = 910` (`Ausführung`).

Ursache war ein im Batch vorbereiteter, veralteter Attributcache. Die lokale API-Vorlage fragt vor jedem Child-Insert nun die aktuelle Kombination aus Modell und Parent direkt in XT ab. Ein bereits vorhandener Datensatz wird aktualisiert statt erneut angelegt.

Die geänderte lokale Datei muss noch nach `wela-api-xt/src/ProductSyncService.php` übernommen werden, bevor erneut exportiert wird.
