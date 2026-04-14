ALTER TABLE export_queue
    ADD COLUMN IF NOT EXISTS attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER attempt_count,
    ADD COLUMN IF NOT EXISTS processed_at DATETIME NULL AFTER claimed_at,
    ADD COLUMN IF NOT EXISTS last_error TEXT NULL AFTER processed_at,
    ADD KEY idx_export_queue_available_at (available_at);
