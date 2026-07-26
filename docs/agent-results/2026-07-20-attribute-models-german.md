# Deutsche Attributmodelle

## Task

`attributes_model` auf die deutsche Parent- und Wertbezeichnung umstellen.

## Files read

- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `config/xt_write.php`

## Changed files

- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-07-20-attribute-models-german.md`

## Summary

Parent-Modelle werden jetzt aus der deutschen Attributbezeichnung erzeugt, zum Beispiel `Durchmesser`. Child-Modelle verwenden den deutschen Attributwert, zum Beispiel `160mm`.

Der Delta-Abgleich berücksichtigt die erwarteten Parent- und Child-Modellnamen. Dadurch werden bestehende technische Modellnamen zuverlässig als Abweichung erkannt und erneut exportiert.

## Open points

- Der laufende Produkt-Export muss vollständig abschließen; danach ist ein XT-Mirror-Refresh für die abschließende Prüfung erforderlich.

## Validation steps

- PHP-Syntaxprüfung für beide geänderten Klassen erfolgreich.
- Delta-Lauf erfolgreich; 5.478 Produkte für die Modellumstellung vorgemerkt.
- Produkt-Export in 500er-Batches gestartet.

## Recommended next step

Nach Abschluss des laufenden Exports XT-Mirror aktualisieren und für `HA-1620` Parent-Modell `Durchmesser` sowie Child-Modell `160mm` prüfen.
