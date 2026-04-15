# Ticket: T-026

## Status
open

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
