ALTER TABLE raw_afs_documents
    ADD COLUMN title VARCHAR(255) NULL AFTER sku;

ALTER TABLE stage_product_documents
    ADD COLUMN title VARCHAR(255) NULL AFTER afs_artikel_id,
    ADD COLUMN source_path VARCHAR(255) NULL AFTER path;
