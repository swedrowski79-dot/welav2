ALTER TABLE export_queue
    ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER claimed_at,
    ADD COLUMN last_error TEXT NULL AFTER attempt_count,
    ADD COLUMN next_retry_at DATETIME NULL AFTER last_error;
