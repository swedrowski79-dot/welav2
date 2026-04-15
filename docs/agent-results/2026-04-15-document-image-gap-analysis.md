# Task

Analyze the missing AFS document/image source integration, identify what is currently missing in schema/config/pipeline, and create follow-up tickets.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ticket-task.md`
- `config/sources.php`
- `config/normalize.php`
- `database.sql`
- `src/Importer/AfsImporter.php`
- `src/Service/Normalizer.php`
- `src/Service/MergeService.php`
- `config/pipeline.php`
- `config/xt_write.php`
- relevant result files in `docs/agent-results/`

# Changed files

- `docs/agent-results/2026-04-15-document-image-gap-analysis.md`
- `docs/tickets/open/T-025-afs-document-raw-import-and-normalization.md`
- `docs/tickets/open/T-026-document-and-product-media-stage-model.md`
- `docs/tickets/open/T-027-document-media-merge-expand-pipeline-wiring.md`

# Summary

## Current gap analysis

The repository already contains partial AFS document/media intent, but not a complete working pipeline.

What already exists:
- `config/sources.php`
  - defines an AFS entity `documents`
  - source defaults to `AFS_DOCUMENTS_TABLE` / `Dokumente`
  - selected columns:
    - `Dokument`
    - `Artikel`
    - `Artikelnummer`
    - `Bezeichnung`
    - `Dateiname`
    - `Pfad`
    - `Typ`
    - `Sortierung`
- `config/pipeline.php`
  - already mentions conceptual steps:
    - `import_raw_afs_documents`
    - `normalize_afs_documents`
    - `expand_stage_media`
    - `expand_stage_documents`
    - `calculate_media_deltas`
    - `calculate_document_deltas`
    - `write_xt_media_documents`
    - `write_xt_media_link_documents`
- `config/xt_write.php`
  - already expects downstream stage tables such as:
    - `stage_product_documents`
    - media link records based on product-linked stage rows

What is missing in the active code path:
- `AfsImporter` only implements:
  - `importArticles()`
  - `importCategories()`
- `ImportWorkflow` only truncates/imports:
  - `raw_afs_articles`
  - `raw_afs_categories`
  - extra article/category translations
- `Normalizer` has no `afs.documents` mapping
- `MergeService` only builds:
  - `stage_products`
  - `stage_product_translations`
  - `stage_categories`
  - `stage_category_translations`
- there is no active expand/media service for document/image records

Conclusion:
- the AFS `Dokumente` source is configured only at the source-config level
- the rest of the pipeline is missing or only referenced conceptually

## Missing tables

Missing raw table:
- `raw_afs_documents`

Missing stage tables:
- `stage_product_documents`
- a product image/media stage table is also missing if article image fields `Bild1..Bild10` are to be integrated cleanly
  - likely something like `stage_product_media` or equivalent

Missing from current schema even though downstream config implies them:
- no stage table exists for XT media document export input
- no raw or stage persistence exists for article image variants `Bild1..Bild10`

## Missing config

Missing normalization config:
- no `afs.documents` entry in `config/normalize.php`

Missing merge/expand config:
- `config/merge.php` contains no document/media stage definitions
- `config/delta.php` contains no media/document delta handling in the active stage pipeline

Partially configured but not wired:
- `config/sources.php` includes `documents`
- `config/pipeline.php` includes document/media step names
- `config/xt_write.php` includes target mapping expectations for documents/media

## Missing pipeline steps

Missing active raw import step:
- no document import execution in `ImportWorkflow`
- no `AfsImporter::importDocuments()`

Missing normalized raw persistence:
- no raw document table
- no mapping from AFS document columns into raw fields

Missing stage transformation:
- no merge stage for documents/images
- no expand stage for product media/documents

Missing relation modeling:
- no active persistence that relates document/image rows to:
  - products by `Artikel` / `Artikelnummer`
- no active category-document relation model is present or implied by current source config

## Relation of document/image data to products and categories

From the configured AFS document columns, the source appears product-linked, not category-linked:
- `Artikel`
- `Artikelnummer`

This strongly suggests:
- primary relation: documents/images belong to products
- no current evidence of category-linked AFS document rows in the configured source shape

For categories:
- current category image handling comes directly from `raw_afs_categories.image` and `raw_afs_categories.header_image`
- category image normalization is implemented
- no separate category document/media pipeline exists at present

For products:
- article source selects `Bild1..Bild10`
- but these fields are not normalized into raw schema and not persisted into stage schema
- this is the main missing product image integration gap

## Proposed ticket list

- `T-025` Add raw import and normalization for AFS document records
- `T-026` Design and implement stage tables for product documents and product images
- `T-027` Wire document/media data through merge and expand pipeline steps

# Open points

- The exact semantic difference between AFS `Dokumente.Typ` values is not yet modeled, so image-vs-document classification still needs explicit implementation work.
- The current source config uses `Dokumente` (plural), while the requested analysis referenced `Dokument` (singular). The repository currently points to `Dokumente`; the real environment table/view name should be confirmed before implementation.
- Category-linked documents are not evidenced by the current configured source columns.

# Validation steps

- Read and compared:
  - source config
  - normalization config
  - active importer/workflow code
  - active merge service
  - schema
  - downstream conceptual pipeline and XT write config
- Verified by inspection that:
  - `documents` source is configured
  - no raw document table exists
  - no active import/normalize/merge/expand implementation exists for documents/images
  - downstream config references stage tables that do not yet exist

# Recommended next step

Implement `T-025` first. The highest-value incremental step is to get AFS document rows into a dedicated raw table through the existing importer/normalizer path before deciding the full stage media/document model.
