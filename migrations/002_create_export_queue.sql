CREATE TABLE IF NOT EXISTS export_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    payload JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    KEY idx_export_queue_entity (entity_type, entity_id),
    KEY idx_export_queue_status (status),
    KEY idx_export_queue_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
