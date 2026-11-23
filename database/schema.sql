-- =============================================================================
-- Visitor Photo Slideshow System - database schema (Step 1)
-- =============================================================================
-- Target : MySQL 8+ or compatible MariaDB (Windows XAMPP and Ubuntu).
-- Import : into an ALREADY-CREATED, EMPTY database (see README.md).
--          This file deliberately has no CREATE DATABASE / USE statement.
-- Engine : InnoDB. Charset utf8mb4 with an explicit collation
--          (utf8mb4_unicode_ci exists in both MySQL and MariaDB, whereas the
--          MySQL 8 default utf8mb4_0900_ai_ci does not exist in MariaDB).
-- Times  : DATETIME values are stored in UTC. The application will set the
--          connection time zone to +00:00 so CURRENT_TIMESTAMP defaults are UTC.
-- Order  : tables are created parent-first so every foreign key target exists.
-- Deletes: all foreign keys use ON DELETE RESTRICT on purpose. A row cannot be
--          removed while children exist, so the application must delete photo
--          files and rows explicitly (inside a transaction) instead of leaving
--          orphaned private files on disk.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- visitors: one row per person. Identified by a visitor code that is NEVER
-- stored in plaintext; only a 64-character hex digest (HMAC-SHA-256) is kept.
-- The UNIQUE key makes "find returning visitor by code" a fast indexed lookup.
-- -----------------------------------------------------------------------------
CREATE TABLE visitors (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name               VARCHAR(100)    NOT NULL,
    visitor_code_hash  CHAR(64)        NOT NULL,
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_visitors_code_hash (visitor_code_hash),
    KEY idx_visitors_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- consents: one visitor can have many consent records. The application records
-- a NEW consent row for every photo upload session, so the same visitor can
-- have several rows with the SAME consent_version. There is deliberately NO
-- unique key on (visitor_id, consent_version). consent_version says which
-- consent text was agreed to; consented_at says when.
-- idx_consents_visitor_version is an ordinary (non-unique) index. It supports
-- the foreign key fk_consents_visitor and lookups of a visitor's consents.
-- uq_consents_id_visitor exists so that photos can reference (id, visitor_id)
-- together and the database guarantees a photo never points at another
-- visitor's consent.
-- -----------------------------------------------------------------------------
CREATE TABLE consents (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id       BIGINT UNSIGNED NOT NULL,
    consent_version  VARCHAR(20)     NOT NULL,
    consented_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consents_visitor_version (visitor_id, consent_version),
    UNIQUE KEY uq_consents_id_visitor (id, visitor_id),
    CONSTRAINT fk_consents_visitor
        FOREIGN KEY (visitor_id) REFERENCES visitors (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- photos: metadata only. The image file itself lives in the private storage
-- directory; no binary image data is stored in MySQL.
--   stored_filename   : server-generated unique name (never the visitor's name)
--   original_filename : what the visitor's device called it (display only)
--   status            : 'active' = eligible for the slideshow,
--                       'hidden' = kept but removed from the slideshow
--   consent_id        : NOT NULL, so a photo cannot exist without a consent
--   fk_photos_consent : composite key (consent_id, visitor_id) forces the
--                       consent to belong to the same visitor as the photo.
--   Indexes           : InnoDB appends the primary key (id) to every secondary
--                       index, so idx_photos_visitor is (visitor_id, id) and
--                       idx_photos_status is (status, id). That serves the
--                       planned queries "this visitor's photos, newest first"
--                       and "next active photo after id N" with id-based
--                       (keyset) pagination instead of OFFSET scans. Which
--                       index MySQL picks depends on data volume; re-check
--                       with EXPLAIN once real queries exist (Step 4+).
-- -----------------------------------------------------------------------------
CREATE TABLE photos (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_id         BIGINT UNSIGNED NOT NULL,
    consent_id         BIGINT UNSIGNED NOT NULL,
    stored_filename    VARCHAR(64)     NOT NULL,
    original_filename  VARCHAR(255)    NOT NULL,
    mime_type          VARCHAR(50)     NOT NULL,
    file_size          INT UNSIGNED    NOT NULL,
    status             VARCHAR(20)     NOT NULL DEFAULT 'active',
    uploaded_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_photos_stored_filename (stored_filename),
    KEY idx_photos_visitor (visitor_id),
    KEY idx_photos_consent_visitor (consent_id, visitor_id),
    KEY idx_photos_status (status),
    CONSTRAINT chk_photos_status CHECK (status IN ('active', 'hidden')),
    CONSTRAINT fk_photos_visitor
        FOREIGN KEY (visitor_id) REFERENCES visitors (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_photos_consent
        FOREIGN KEY (consent_id, visitor_id) REFERENCES consents (id, visitor_id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- admins: administrator accounts. No account is created here; the first
-- administrator will be created through the application, never hardcoded.
-- password_hash holds a password_hash() result (VARCHAR(255) leaves room for
-- stronger algorithms later). The collation makes usernames case-insensitive.
-- -----------------------------------------------------------------------------
CREATE TABLE admins (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)     NOT NULL,
    password_hash  VARCHAR(255)    NOT NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- settings: key/value store for values an administrator can change at runtime
-- (for example the slideshow display duration). No rows are seeded; the
-- application falls back to its configured defaults when a key is absent.
-- -----------------------------------------------------------------------------
CREATE TABLE settings (
    setting_key    VARCHAR(100)    NOT NULL,
    setting_value  VARCHAR(255)    NOT NULL,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
