ALTER TABLE stage_categories
    ADD COLUMN hash VARCHAR(64) NULL AFTER online_flag,
    ADD KEY idx_stage_categories_hash (hash);

CREATE TABLE IF NOT EXISTS category_export_state (
    category_id INT NOT NULL PRIMARY KEY,
    last_exported_hash VARCHAR(64) NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_category_export_state_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
