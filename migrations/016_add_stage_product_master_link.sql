ALTER TABLE stage_products
    ADD COLUMN master_afs_artikel_id INT NULL AFTER master_sku,
    ADD KEY idx_stage_products_master_afs_artikel_id (master_afs_artikel_id);
