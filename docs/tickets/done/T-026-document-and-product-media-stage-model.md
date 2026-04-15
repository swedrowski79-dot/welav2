# Ticket: T-026

## Status
done

## Title
Design and implement stage tables for product documents and product images

## Problem
The repository references downstream media/document steps such as `stage_product_documents`, but the stage schema does not currently contain document/media tables and no stage model exists for product-linked files.

## Goal
Introduce the missing stage-level tables and mapping model for document/image data linked to products.

## Scope
- define stage tables for product documents and product images/media
- define key fields needed for downstream XT media export
- document identity strategy between AFS document rows and stage media rows
- keep the change focused on stage schema/modeling and not full XT write behavior

## Acceptance Criteria
- required stage media/document tables exist
- the schema captures relation to products
- file name, path/source reference, type, and sort/order metadata are modeled
- the model is consistent with existing `config/xt_write.php` expectations

## Files / Areas
- `database.sql`
- migrations if required
- documentation/result files

## Notes
This ticket should align the stage schema with the already referenced `stage_product_documents` and any needed product image/media table.

## Implementation Notes
- Added `stage_product_documents` for product-linked file/doc rows using `afs_document_id` as the source-side document identity.
- Added `stage_product_media` for product-linked image/media rows with a dedicated `media_external_id` for future XT media identity mapping.
- Both tables include `afs_artikel_id` as the product relation and model `file_name`, `path`, `document_type`, and `sort_order`.
- `position` was added alongside `sort_order` to stay compatible with the existing downstream `config/xt_write.php` expectations.
- `stage_product_documents` now also includes a dedicated `title` field for the normalized human-readable document label from AFS `Titel`.
- `stage_product_documents.source_path` was added so future pipeline steps can preserve the original technical source path independently from `title` and `file_name`.
- AFS `Titel` is explicitly treated as display title, not as `file_name`; if it contains a path, only the basename belongs in `title`.
- No merge, expand, or writer population logic was added in this ticket.
