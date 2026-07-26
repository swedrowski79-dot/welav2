## Task

Automatische Batch-Verkleinerung im Produkt-Export entfernen, damit Serverfehler die Batch-Groesse nicht dynamisch herunterstufen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `src/Service/XtProductWriter.php`

## Changed files

- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-17-remove-batch-downshift.md`

## Summary

- Die rekursive Halbierung von Produkt-Batches bei HTTP-/JSON-/Transportfehlern wurde entfernt.
- Produkt-Batches werden weiter nur noch durch die normale Chunk-Bildung anhand von `product_batch_request_size` und `product_batch_request_max_payload_bytes` aufgeteilt.
- Ein fehlerhafter Batch wird jetzt nicht mehr automatisch in kleinere Teil-Batches zerlegt.

## Open points

- Wenn der Gegenseiten-Server bei einer bestimmten Batch-Groesse instabil ist, schlagen diese Chunks jetzt direkt fehl statt automatisch kleiner erneut zu laufen.

## Validation steps

- `php -l src/Service/XtProductWriter.php`

## Recommended next step

- Falls gewuenscht, die feste `product_batch_request_size` gezielt so waehlen, dass sie stabil bleibt, ohne dynamisches Downshifting.
