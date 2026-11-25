ALTER TABLE photos
    MODIFY consent_id BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN visibility VARCHAR(10) NOT NULL DEFAULT 'private' AFTER approval_status,
    ADD CONSTRAINT chk_photos_visibility CHECK (visibility IN ('private', 'public'));

UPDATE photos p
INNER JOIN consents c
    ON c.id = p.consent_id AND c.visitor_id = p.visitor_id
SET p.visibility = 'public'
WHERE c.consent_version = '1.0'
  AND c.withdrawn_at IS NULL;
