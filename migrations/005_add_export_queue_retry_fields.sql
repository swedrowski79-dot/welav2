ALTER TABLE export_queue
    ADD COLUMN last_error TEXT NULL AFTER claimed_at,
    ADD COLUMN next_retry_at DATETIME NULL AFTER last_error;
