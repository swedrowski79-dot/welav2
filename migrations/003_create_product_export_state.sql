CREATE TABLE IF NOT EXISTS product_export_state (
    product_id INT NOT NULL PRIMARY KEY,
    last_exported_hash VARCHAR(64) NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_product_export_state_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
