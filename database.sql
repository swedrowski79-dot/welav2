CREATE TABLE IF NOT EXISTS raw_afs_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    name TEXT NULL,
    description MEDIUMTEXT NULL,
    short_text MEDIUMTEXT NULL,
    ean VARCHAR(255) NULL,
    stock DECIMAL(18,4) NULL,
    price DECIMAL(18,4) NULL,
    weight DECIMAL(18,4) NULL,
    category_afs_id INT NULL,
    category_name VARCHAR(255) NULL,
    tax_rate DECIMAL(10,4) NULL,
    min_qty DECIMAL(18,4) NULL,
    variant_flag VARCHAR(255) NULL,
    unit VARCHAR(255) NULL,
    online_flag INT NULL,
    product_type VARCHAR(50) NULL,
    is_master TINYINT NULL,
    is_slave TINYINT NULL,
    is_standard TINYINT NULL,
    master_sku VARCHAR(255) NULL,
    KEY idx_raw_afs_articles_afs_artikel_id (afs_artikel_id),
    KEY idx_raw_afs_articles_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raw_afs_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_wg_id INT NULL,
    parent_afs_id INT NULL,
    level INT NULL,
    name VARCHAR(255) NULL,
    description MEDIUMTEXT NULL,
    image VARCHAR(255) NULL,
    header_image VARCHAR(255) NULL,
    online_flag INT NULL,
    KEY idx_raw_afs_categories_afs_wg_id (afs_wg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raw_extra_article_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    row_id INT NULL,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    master_sku VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    language_code_normalized VARCHAR(10) NULL,
    name VARCHAR(255) NULL,
    description MEDIUMTEXT NULL,
    technical_data_html MEDIUMTEXT NULL,
    attribute_name1 VARCHAR(255) NULL,
    attribute_name2 VARCHAR(255) NULL,
    attribute_name3 VARCHAR(255) NULL,
    attribute_name4 VARCHAR(255) NULL,
    attribute_value1 VARCHAR(255) NULL,
    attribute_value2 VARCHAR(255) NULL,
    attribute_value3 VARCHAR(255) NULL,
    attribute_value4 VARCHAR(255) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description MEDIUMTEXT NULL,
    is_master TINYINT NULL,
    source_directory VARCHAR(255) NULL,
    KEY idx_raw_extra_article_translations_afs_artikel_id (afs_artikel_id),
    KEY idx_raw_extra_article_translations_lang (language_code_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raw_extra_category_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    row_id INT NULL,
    afs_wg_id INT NULL,
    original_name VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    language_code_normalized VARCHAR(10) NULL,
    name VARCHAR(255) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description MEDIUMTEXT NULL,
    KEY idx_raw_extra_category_translations_afs_wg_id (afs_wg_id),
    KEY idx_raw_extra_category_translations_lang (language_code_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    name_default VARCHAR(255) NULL,
    description_default MEDIUMTEXT NULL,
    short_description_default MEDIUMTEXT NULL,
    ean VARCHAR(255) NULL,
    stock DECIMAL(18,4) NULL,
    price DECIMAL(18,4) NULL,
    weight DECIMAL(18,4) NULL,
    category_afs_id INT NULL,
    category_name VARCHAR(255) NULL,
    tax_rate DECIMAL(10,4) NULL,
    unit VARCHAR(255) NULL,
    min_qty DECIMAL(18,4) NULL,
    variant_flag VARCHAR(255) NULL,
    product_type VARCHAR(50) NULL,
    is_master TINYINT NULL,
    is_slave TINYINT NULL,
    is_standard TINYINT NULL,
    master_sku VARCHAR(255) NULL,
    online_flag INT NULL,
    KEY idx_stage_products_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_products_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_product_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    master_sku VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    name VARCHAR(255) NULL,
    description MEDIUMTEXT NULL,
    technical_data_html MEDIUMTEXT NULL,
    short_description MEDIUMTEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description MEDIUMTEXT NULL,
    product_type VARCHAR(50) NULL,
    attribute_name1 VARCHAR(255) NULL,
    attribute_name2 VARCHAR(255) NULL,
    attribute_name3 VARCHAR(255) NULL,
    attribute_name4 VARCHAR(255) NULL,
    attribute_value1 VARCHAR(255) NULL,
    attribute_value2 VARCHAR(255) NULL,
    attribute_value3 VARCHAR(255) NULL,
    attribute_value4 VARCHAR(255) NULL,
    source_directory VARCHAR(255) NULL,
    KEY idx_stage_product_translations_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_product_translations_lang (language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_wg_id INT NULL,
    parent_afs_id INT NULL,
    level INT NULL,
    name_default VARCHAR(255) NULL,
    description_default MEDIUMTEXT NULL,
    image VARCHAR(255) NULL,
    header_image VARCHAR(255) NULL,
    online_flag INT NULL,
    KEY idx_stage_categories_afs_wg_id (afs_wg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_category_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_wg_id INT NULL,
    language_code VARCHAR(10) NULL,
    original_name VARCHAR(255) NULL,
    name VARCHAR(255) NULL,
    description MEDIUMTEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description MEDIUMTEXT NULL,
    KEY idx_stage_category_translations_afs_wg_id (afs_wg_id),
    KEY idx_stage_category_translations_lang (language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_attribute_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    sort_order INT NULL,
    attribute_name VARCHAR(255) NULL,
    attribute_value VARCHAR(255) NULL,
    source_directory VARCHAR(255) NULL,
    KEY idx_stage_attribute_translations_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_attribute_translations_lang (language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_type VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    imported_records INT NOT NULL DEFAULT 0,
    merged_records INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    message TEXT NULL,
    context_json JSON NULL,
    KEY idx_sync_runs_type (run_type),
    KEY idx_sync_runs_status (status),
    KEY idx_sync_runs_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_run_id BIGINT UNSIGNED NULL,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL,
    KEY idx_sync_logs_run_id (sync_run_id),
    KEY idx_sync_logs_level (level),
    KEY idx_sync_logs_created_at (created_at),
    CONSTRAINT fk_sync_logs_run
        FOREIGN KEY (sync_run_id) REFERENCES sync_runs(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_run_id BIGINT UNSIGNED NULL,
    source VARCHAR(100) NULL,
    record_identifier VARCHAR(255) NULL,
    message TEXT NOT NULL,
    details JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    KEY idx_sync_errors_run_id (sync_run_id),
    KEY idx_sync_errors_status (status),
    KEY idx_sync_errors_created_at (created_at),
    CONSTRAINT fk_sync_errors_run
        FOREIGN KEY (sync_run_id) REFERENCES sync_runs(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
