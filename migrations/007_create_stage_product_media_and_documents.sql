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
    KEY idx_stage_product_media_external_id (media_external_id),
    KEY idx_stage_product_media_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_product_media_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stage_product_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_document_id INT NULL,
    afs_artikel_id INT NULL,
    file_name VARCHAR(255) NULL,
    path VARCHAR(255) NULL,
    document_type VARCHAR(255) NULL,
    sort_order INT NULL,
    position INT NULL,
    KEY idx_stage_product_documents_afs_document_id (afs_document_id),
    KEY idx_stage_product_documents_afs_artikel_id (afs_artikel_id),
    KEY idx_stage_product_documents_document_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
