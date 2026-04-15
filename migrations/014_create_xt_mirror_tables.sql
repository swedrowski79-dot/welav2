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
