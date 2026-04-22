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
    attribute_name1 VARCHAR(255) NULL,
    attribute_name2 VARCHAR(255) NULL,
    attribute_name3 VARCHAR(255) NULL,
    attribute_name4 VARCHAR(255) NULL,
    attribute_value1 VARCHAR(255) NULL,
    attribute_value2 VARCHAR(255) NULL,
    attribute_value3 VARCHAR(255) NULL,
    attribute_value4 VARCHAR(255) NULL,
    unit VARCHAR(255) NULL,
    online_flag INT NULL,
    image_1 VARCHAR(255) NULL,
    image_2 VARCHAR(255) NULL,
    image_3 VARCHAR(255) NULL,
    image_4 VARCHAR(255) NULL,
    image_5 VARCHAR(255) NULL,
    image_6 VARCHAR(255) NULL,
    image_7 VARCHAR(255) NULL,
    image_8 VARCHAR(255) NULL,
    image_9 VARCHAR(255) NULL,
    image_10 VARCHAR(255) NULL,
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

CREATE TABLE IF NOT EXISTS raw_afs_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_document_id INT NULL,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    title VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    path VARCHAR(255) NULL,
    document_type VARCHAR(255) NULL,
    sort_order INT NULL,
    KEY idx_raw_afs_documents_afs_document_id (afs_document_id),
    KEY idx_raw_afs_documents_afs_artikel_id (afs_artikel_id),
    KEY idx_raw_afs_documents_sku (sku)
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
    intro_text MEDIUMTEXT NULL,
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

CREATE TABLE IF NOT EXISTS raw_extra_attribute_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    row_id BIGINT NULL,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    sort_order INT NULL,
    attribute_name VARCHAR(255) NULL,
    attribute_value VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    language_code_normalized VARCHAR(10) NULL,
    source_directory VARCHAR(255) NULL,
    translated_name VARCHAR(255) NULL,
    translated_value VARCHAR(255) NULL,
    is_auto_generated TINYINT NULL,
    translation_source VARCHAR(50) NULL,
    KEY idx_raw_extra_attribute_translations_afs_artikel_id (afs_artikel_id),
    KEY idx_raw_extra_attribute_translations_sku (sku),
    KEY idx_raw_extra_attribute_translations_name (attribute_name),
    KEY idx_raw_extra_attribute_translations_lang (language_code_normalized),
    KEY idx_raw_extra_attribute_translations_name_lang (attribute_name, attribute_value, language_code_normalized)
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

CREATE DATABASE IF NOT EXISTS afs_extras CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON afs_extras.* TO 'stage'@'%';
FLUSH PRIVILEGES;

CREATE TABLE IF NOT EXISTS afs_extras.article_translations (
    id INT NOT NULL PRIMARY KEY,
    artikel_id INT NULL,
    article_number VARCHAR(255) NULL,
    master_article_number VARCHAR(255) NULL,
    language VARCHAR(10) NULL,
    article_name VARCHAR(255) NULL,
    intro_text MEDIUMTEXT NULL,
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
    KEY idx_afs_extras_article_translations_artikel_id (artikel_id),
    KEY idx_afs_extras_article_translations_language (language),
    KEY idx_afs_extras_article_translations_article_number (article_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS afs_extras.category_translations (
    id INT NOT NULL PRIMARY KEY,
    warengruppen_id INT NULL,
    original_name VARCHAR(255) NULL,
    language VARCHAR(10) NULL,
    translated_name VARCHAR(255) NULL,
    meta_description MEDIUMTEXT NULL,
    meta_title VARCHAR(255) NULL,
    KEY idx_afs_extras_category_translations_warengruppen_id (warengruppen_id),
    KEY idx_afs_extras_category_translations_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS afs_extras.attribute_name_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    attribute_name VARCHAR(255) NOT NULL,
    attribute_value VARCHAR(255) NOT NULL DEFAULT '',
    language VARCHAR(10) NOT NULL,
    translated_name VARCHAR(255) NULL,
    translated_value VARCHAR(255) NULL,
    is_auto_generated TINYINT NOT NULL DEFAULT 1,
    translation_source VARCHAR(50) NOT NULL DEFAULT 'afs_auto',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_afs_extras_attribute_name_translation (attribute_name, attribute_value, language),
    KEY idx_afs_extras_attribute_name_translations_language (language),
    KEY idx_afs_extras_attribute_name_translations_name (attribute_name, attribute_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS afs_extras.missing_article_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    article_id VARCHAR(255) NOT NULL,
    article_number VARCHAR(255) NULL,
    article_name TEXT NULL,
    language VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'missing',
    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_afs_extras_missing_article_translation (article_id, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS afs_extras.missing_category_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(255) NOT NULL,
    category_name TEXT NULL,
    language VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'missing',
    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_afs_extras_missing_category_translation (category_id, language)
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
    master_afs_artikel_id INT NULL,
    online_flag INT NULL,
    hash VARCHAR(64) NULL,
    KEY idx_stage_products_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_products_sku (sku),
    KEY idx_stage_products_master_afs_artikel_id (master_afs_artikel_id),
    KEY idx_stage_products_hash (hash)
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
    hash VARCHAR(64) NULL,
    KEY idx_stage_categories_afs_wg_id (afs_wg_id),
    KEY idx_stage_categories_hash (hash)
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

CREATE TABLE IF NOT EXISTS stage_product_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_external_id VARCHAR(255) NULL,
    afs_artikel_id INT NULL,
    source_slot VARCHAR(50) NULL,
    file_name VARCHAR(255) NULL,
    path VARCHAR(255) NULL,
    type VARCHAR(50) NULL,
    document_type VARCHAR(255) NULL,
    sort_order INT NULL,
    position INT NULL,
    hash VARCHAR(64) NULL,
    KEY idx_stage_product_media_external_id (media_external_id),
    KEY idx_stage_product_media_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_product_media_type (type),
    KEY idx_stage_product_media_hash (hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_product_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_document_id INT NULL,
    afs_artikel_id INT NULL,
    title VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    path VARCHAR(255) NULL,
    source_path VARCHAR(255) NULL,
    document_type VARCHAR(255) NULL,
    sort_order INT NULL,
    position INT NULL,
    hash VARCHAR(64) NULL,
    KEY idx_stage_product_documents_afs_document_id (afs_document_id),
    KEY idx_stage_product_documents_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_product_documents_document_type (document_type),
    KEY idx_stage_product_documents_hash (hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents_file (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    reference_count INT NOT NULL DEFAULT 0,
    local_path VARCHAR(1024) NULL,
    file_hash VARCHAR(64) NULL,
    file_size BIGINT NULL,
    file_created_at DATETIME NULL,
    file_modified_at DATETIME NULL,
    upload TINYINT NOT NULL DEFAULT 0,
    uploaded_at DATETIME NULL,
    shop_server_path VARCHAR(1024) NULL,
    last_scan_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_documents_file_title (title),
    KEY idx_documents_file_upload (upload),
    KEY idx_documents_file_hash (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_products_snapshot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    xt_products_id INT NULL,
    external_id VARCHAR(255) NULL,
    afs_artikel_id INT NULL,
    category_afs_id INT NULL,
    sku VARCHAR(255) NULL,
    ean VARCHAR(255) NULL,
    stock DECIMAL(18,4) NULL,
    price DECIMAL(18,4) NULL,
    weight DECIMAL(18,4) NULL,
    online_flag INT NULL,
    is_master TINYINT NULL,
    master_sku VARCHAR(255) NULL,
    image VARCHAR(255) NULL,
    translation_hash VARCHAR(64) NULL,
    attribute_hash VARCHAR(64) NULL,
    seo_hash VARCHAR(64) NULL,
    last_modified DATETIME NULL,
    snapshot_hash VARCHAR(64) NULL,
    imported_at DATETIME NOT NULL,
    UNIQUE KEY uniq_xt_products_snapshot_external_id (external_id),
    KEY idx_xt_products_snapshot_afs_artikel_id (afs_artikel_id),
    KEY idx_xt_products_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_categories_snapshot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    xt_categories_id INT NULL,
    external_id VARCHAR(255) NULL,
    afs_wg_id INT NULL,
    parent_xt_id INT NULL,
    parent_external_id VARCHAR(255) NULL,
    parent_afs_id INT NULL,
    level INT NULL,
    image VARCHAR(255) NULL,
    header_image VARCHAR(255) NULL,
    online_flag INT NULL,
    last_modified DATETIME NULL,
    snapshot_hash VARCHAR(64) NULL,
    imported_at DATETIME NOT NULL,
    UNIQUE KEY uniq_xt_categories_snapshot_external_id (external_id),
    KEY idx_xt_categories_snapshot_afs_wg_id (afs_wg_id),
    KEY idx_xt_categories_snapshot_parent_afs_id (parent_afs_id),
    KEY idx_xt_categories_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_media_snapshot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    xt_media_id INT NULL,
    xt_products_id INT NULL,
    media_external_id VARCHAR(255) NULL,
    afs_artikel_id INT NULL,
    file_name VARCHAR(255) NULL,
    media_type VARCHAR(50) NULL,
    class VARCHAR(50) NULL,
    sort_order INT NULL,
    status INT NULL,
    last_modified DATETIME NULL,
    snapshot_hash VARCHAR(64) NULL,
    imported_at DATETIME NOT NULL,
    UNIQUE KEY uniq_xt_media_snapshot_external_id (media_external_id),
    KEY idx_xt_media_snapshot_afs_artikel_id (afs_artikel_id),
    KEY idx_xt_media_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_documents_snapshot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    xt_media_id INT NULL,
    xt_products_id INT NULL,
    document_external_id VARCHAR(255) NULL,
    afs_document_id INT NULL,
    afs_artikel_id INT NULL,
    file_name VARCHAR(255) NULL,
    document_type VARCHAR(50) NULL,
    class VARCHAR(50) NULL,
    sort_order INT NULL,
    status INT NULL,
    last_modified DATETIME NULL,
    snapshot_hash VARCHAR(64) NULL,
    imported_at DATETIME NOT NULL,
    UNIQUE KEY uniq_xt_documents_snapshot_external_id (document_external_id),
    KEY idx_xt_documents_snapshot_afs_document_id (afs_document_id),
    KEY idx_xt_documents_snapshot_afs_artikel_id (afs_artikel_id),
    KEY idx_xt_documents_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_products (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    products_id INT NULL,
    external_id VARCHAR(255) NULL,
    permission_id VARCHAR(255) NULL,
    products_owner VARCHAR(255) NULL,
    products_ean VARCHAR(255) NULL,
    products_quantity VARCHAR(255) NULL,
    show_stock VARCHAR(255) NULL,
    products_average_quantity VARCHAR(255) NULL,
    products_shippingtime VARCHAR(255) NULL,
    products_shippingtime_nostock VARCHAR(255) NULL,
    products_model VARCHAR(255) NULL,
    products_master_flag VARCHAR(255) NULL,
    products_master_model VARCHAR(255) NULL,
    ms_open_first_slave VARCHAR(255) NULL,
    ms_show_slave_list VARCHAR(255) NULL,
    ms_filter_slave_list VARCHAR(255) NULL,
    ms_filter_slave_list_hide_on_product VARCHAR(255) NULL,
    products_image_from_master VARCHAR(255) NULL,
    ms_load_masters_free_downloads VARCHAR(255) NULL,
    ms_load_masters_main_img VARCHAR(255) NULL,
    products_price VARCHAR(255) NULL,
    date_added DATETIME NULL,
    last_modified DATETIME NULL,
    products_weight VARCHAR(255) NULL,
    products_status VARCHAR(255) NULL,
    products_tax_class_id VARCHAR(255) NULL,
    products_unit VARCHAR(255) NULL,
    products_average_rating VARCHAR(255) NULL,
    products_rating_count VARCHAR(255) NULL,
    products_digital VARCHAR(255) NULL,
    flag_has_specials VARCHAR(255) NULL,
    products_serials VARCHAR(255) NULL,
    total_downloads VARCHAR(255) NULL,
    group_discount_allowed VARCHAR(255) NULL,
    google_product_cat VARCHAR(255) NULL,
    products_canonical_master VARCHAR(255) NULL,
    products_image VARCHAR(255) NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_products_products_id (products_id),
    KEY idx_xt_mirror_products_external_id (external_id),
    KEY idx_xt_mirror_products_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_categories (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    categories_id INT NULL,
    external_id VARCHAR(255) NULL,
    permission_id VARCHAR(255) NULL,
    categories_owner VARCHAR(255) NULL,
    parent_id INT NULL,
    categories_left INT NULL,
    categories_right INT NULL,
    categories_level INT NULL,
    categories_image VARCHAR(255) NULL,
    categories_master_image VARCHAR(255) NULL,
    categories_status VARCHAR(255) NULL,
    categories_template VARCHAR(255) NULL,
    listing_template VARCHAR(255) NULL,
    sort_order INT NULL,
    products_sorting VARCHAR(255) NULL,
    products_sorting2 VARCHAR(255) NULL,
    top_category VARCHAR(255) NULL,
    start_page_category VARCHAR(255) NULL,
    date_added DATETIME NULL,
    last_modified DATETIME NULL,
    category_custom_link VARCHAR(255) NULL,
    category_custom_link_type VARCHAR(255) NULL,
    category_custom_link_id VARCHAR(255) NULL,
    google_product_cat VARCHAR(255) NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_categories_categories_id (categories_id),
    KEY idx_xt_mirror_categories_external_id (external_id),
    KEY idx_xt_mirror_categories_parent_id (parent_id),
    KEY idx_xt_mirror_categories_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_categories_description (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    categories_id INT NULL,
    language_code VARCHAR(10) NULL,
    categories_name VARCHAR(255) NULL,
    categories_description MEDIUMTEXT NULL,
    categories_heading_title VARCHAR(255) NULL,
    categories_description_bottom MEDIUMTEXT NULL,
    categories_store_id INT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_categories_description_key (categories_id, language_code),
    KEY idx_xt_mirror_categories_description_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_products_description (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    products_id INT NULL,
    language_code VARCHAR(10) NULL,
    reload_st VARCHAR(255) NULL,
    products_name VARCHAR(255) NULL,
    products_description MEDIUMTEXT NULL,
    products_short_description MEDIUMTEXT NULL,
    products_keywords MEDIUMTEXT NULL,
    products_url VARCHAR(255) NULL,
    products_store_id INT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_products_description_key (products_id, language_code),
    KEY idx_xt_mirror_products_description_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_products_to_categories (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    products_id INT NULL,
    categories_id INT NULL,
    master_link VARCHAR(255) NULL,
    store_id INT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_products_to_categories_key (products_id, categories_id),
    KEY idx_xt_mirror_products_to_categories_category (categories_id),
    KEY idx_xt_mirror_products_to_categories_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_media (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    id INT NULL,
    file VARCHAR(255) NULL,
    type VARCHAR(50) NULL,
    class VARCHAR(50) NULL,
    download_status VARCHAR(255) NULL,
    status VARCHAR(255) NULL,
    owner VARCHAR(255) NULL,
    date_added DATETIME NULL,
    last_modified DATETIME NULL,
    max_dl_count VARCHAR(255) NULL,
    max_dl_days VARCHAR(255) NULL,
    total_downloads VARCHAR(255) NULL,
    copyright_holder VARCHAR(255) NULL,
    external_id VARCHAR(255) NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_media_id (id),
    KEY idx_xt_mirror_media_external_id (external_id),
    KEY idx_xt_mirror_media_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_media_link (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    ml_id INT NULL,
    m_id INT NULL,
    link_id INT NULL,
    class VARCHAR(50) NULL,
    type VARCHAR(50) NULL,
    sort_order INT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_media_link_ml_id (ml_id),
    KEY idx_xt_mirror_media_link_relation (m_id, link_id, type),
    KEY idx_xt_mirror_media_link_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_plg_products_attributes (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    attributes_id INT NULL,
    attributes_parent INT NULL,
    attributes_model VARCHAR(255) NULL,
    sort_order INT NULL,
    status VARCHAR(255) NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_plg_products_attributes_id (attributes_id),
    KEY idx_xt_mirror_plg_products_attributes_model (attributes_model),
    KEY idx_xt_mirror_plg_products_attributes_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_plg_products_attributes_description (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    attributes_id INT NULL,
    language_code VARCHAR(10) NULL,
    attributes_name VARCHAR(255) NULL,
    attributes_desc MEDIUMTEXT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_plg_products_attributes_description_key (attributes_id, language_code),
    KEY idx_xt_mirror_plg_products_attributes_description_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_plg_products_to_attributes (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    products_id INT NULL,
    attributes_id INT NULL,
    attributes_parent_id INT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_plg_products_to_attributes_key (products_id, attributes_id),
    KEY idx_xt_mirror_plg_products_to_attributes_parent (attributes_parent_id),
    KEY idx_xt_mirror_plg_products_to_attributes_snapshot_hash (snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xt_mirror_seo_url (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    url_md5 VARCHAR(64) NULL,
    url_text VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    link_type INT NULL,
    link_id INT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description MEDIUMTEXT NULL,
    meta_keywords MEDIUMTEXT NULL,
    store_id INT NULL,
    imported_at DATETIME NOT NULL,
    snapshot_hash VARCHAR(64) NOT NULL,
    UNIQUE KEY uniq_xt_mirror_seo_url_key (link_type, link_id, language_code, store_id),
    KEY idx_xt_mirror_seo_url_url_md5 (url_md5),
    KEY idx_xt_mirror_seo_url_snapshot_hash (snapshot_hash)
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

CREATE TABLE IF NOT EXISTS export_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(255) NOT NULL,
    action VARCHAR(20) NOT NULL,
    payload JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claim_token VARCHAR(64) NULL,
    claimed_at DATETIME NULL,
    processed_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_export_queue_entity (entity_type, entity_id),
    KEY idx_export_queue_status (status),
    KEY idx_export_queue_available_at (available_at),
    KEY idx_export_queue_claim_token (claim_token),
    KEY idx_export_queue_claimed_at (claimed_at),
    KEY idx_export_queue_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_export_state (
    product_id INT NOT NULL PRIMARY KEY,
    last_exported_hash VARCHAR(64) NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_product_export_state_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS category_export_state (
    category_id INT NOT NULL PRIMARY KEY,
    last_exported_hash VARCHAR(64) NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_category_export_state_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
