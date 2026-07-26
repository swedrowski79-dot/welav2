ALTER TABLE stage_product_media
    ADD COLUMN hash VARCHAR(64) NULL AFTER position,
    ADD KEY idx_stage_product_media_hash (hash);

ALTER TABLE stage_product_documents
    ADD COLUMN hash VARCHAR(64) NULL AFTER position,
    ADD KEY idx_stage_product_documents_hash (hash);

CREATE TABLE IF NOT EXISTS product_media_export_state (
    entity_id VARCHAR(255) NOT NULL PRIMARY KEY,
    last_exported_hash VARCHAR(64) NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_product_media_export_state_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_document_export_state (
    entity_id VARCHAR(255) NOT NULL PRIMARY KEY,
    last_exported_hash VARCHAR(64) NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_product_document_export_state_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
