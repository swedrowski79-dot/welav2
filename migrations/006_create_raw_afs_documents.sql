CREATE TABLE IF NOT EXISTS raw_afs_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afs_document_id INT NULL,
    afs_artikel_id INT NULL,
    sku VARCHAR(255) NULL,
    name VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    path VARCHAR(255) NULL,
    document_type VARCHAR(255) NULL,
    sort_order INT NULL,
    KEY idx_raw_afs_documents_afs_document_id (afs_document_id),
    KEY idx_raw_afs_documents_afs_artikel_id (afs_artikel_id),
    KEY idx_raw_afs_documents_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
