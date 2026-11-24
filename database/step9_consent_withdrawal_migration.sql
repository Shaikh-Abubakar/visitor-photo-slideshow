ALTER TABLE consents
    ADD COLUMN withdrawn_at DATETIME NULL DEFAULT NULL AFTER consented_at;
