# Ticket: T-025

## Status
done

## Title
Add raw import and normalization for AFS document records

## Problem
The AFS source configuration already defines a `documents` entity, but the active import workflow does not import it and there is no normalization mapping for AFS document rows.

## Goal
Import AFS document/image source records into a dedicated raw table through the same normalization/import path used for products and categories.

## Scope
- add raw schema for AFS documents
- add normalization mapping for the AFS `Dokumente` entity
- extend the import workflow and importer to execute the document import
- keep the change limited to raw import and normalization only

## Acceptance Criteria
- a dedicated raw document table exists
- AFS document rows are imported into stage MySQL
- path/file fields are normalized in the mapping layer where appropriate
- monitoring reflects the document import as part of the import workflow

## Files / Areas
- `database.sql`
- `config/normalize.php`
- `src/Importer/AfsImporter.php`
- `src/Service/ImportWorkflow.php`
- related migration files if needed

## Notes
This ticket should not yet decide final stage/media modeling. It should stop at raw import and normalized raw persistence.

## Implementation Notes
- `raw_afs_documents` was added as a dedicated raw import table.
- `afs.documents` was added to `config/normalize.php`.
- `Dateiname` is normalized to `file_name` in the mapping layer via the existing filename transform.
- The live AFS source uses `Dateiname` as the stored source path, so that value is persisted into `path` for traceability.
- `ImportWorkflow` imports AFS documents together with the product-side raw import path.
- The default AFS documents source was aligned to the live environment name `Dokument`, while remaining overrideable through `AFS_DOCUMENTS_TABLE`.
- The live raw mapping uses `Zaehler`, `Artikel`, `Titel`, `Dateiname`, and `Art` because the current AFS source does not expose the originally assumed `Dokumente` columns.
