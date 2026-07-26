ALTER TABLE export_queue
    ADD COLUMN claim_token VARCHAR(64) NULL AFTER status,
    ADD COLUMN claimed_at DATETIME NULL AFTER claim_token,
    ADD KEY idx_export_queue_claim_token (claim_token),
    ADD KEY idx_export_queue_claimed_at (claimed_at);
