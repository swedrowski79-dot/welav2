ALTER TABLE xt_products_snapshot
    ADD COLUMN category_afs_id INT NULL AFTER afs_artikel_id,
    ADD COLUMN translation_hash VARCHAR(64) NULL AFTER image,
    ADD COLUMN attribute_hash VARCHAR(64) NULL AFTER translation_hash,
    ADD COLUMN seo_hash VARCHAR(64) NULL AFTER attribute_hash;
