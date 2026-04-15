# Ticket: T-025

## Status
open

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
