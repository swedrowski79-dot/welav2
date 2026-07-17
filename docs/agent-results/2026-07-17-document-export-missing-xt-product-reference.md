Task
- Fehlerbild fuer Dokument-Export mit fehlender XT-Produkt-Referenz analysieren und beheben.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- config/xt_write.php
- src/Service/AbstractXtWriter.php
- src/Service/XtMediaDocumentWriter.php

Changed files
- src/Service/XtMediaDocumentWriter.php
- docs/agent-results/2026-07-17-document-export-missing-xt-product-reference.md

Summary
- Der gemeldete Fehler ist fachlich nachvollziehbar: das Dokument verweist auf `afs_artikel_id = 64080`, aber dieses Produkt ist in XT noch nicht verfuegbar.
- Verifiziert:
  - Dokument-Queue `10295` gehoert zu `afs_document_id = 42243`
  - das Dokument verweist auf `afs_artikel_id = 64080`
  - der Produkt-Queue-Eintrag fuer `64080` existiert und steht noch auf `processing`
  - im XT-Mirror war fuer `external_id = 64080` noch kein XT-Produkt vorhanden
- Bisher wurde dieser Zustand als permanenter Fehler behandelt.
- Die Aenderung stellt den Fall jetzt auf retrybar um: wenn fuer Dokumente/Medien nur die `xt_products`-Referenz fehlt, wird kein `PermanentExportQueueException` mehr geworfen, sondern ein normaler Runtime-Fehler fuer spaeteren Retry.

Open points
- Der bereits auf `error` gesetzte Dokument-Queue-Eintrag `10295` wird durch die Codeaenderung nicht automatisch neu eingereiht.
- Solange der Produkt-Export-Worker weiterhin blockiert ist, kann auch der Dokument-Retry noch nicht erfolgreich werden.

Validation steps
- SQL-Pruefung:
  - Dokument-Queue `10295`
  - `stage_product_documents.afs_document_id = 42243`
  - `stage_products.afs_artikel_id = 64080`
  - Produkt-Queue fuer `entity_type = product`, `entity_id = 64080`
- `docker compose exec php php -l src/Service/XtMediaDocumentWriter.php`

Recommended next step
- Nach Behebung des Export-Worker-Hauptfehlers die Dokument-Fehlerqueue fuer solche Eintraege resetten oder neu erzeugen und den Worker erneut laufen lassen.
