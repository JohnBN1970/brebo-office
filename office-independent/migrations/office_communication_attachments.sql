-- Apply only to the isolated Office-test database after schema review.
-- Files remain in a private attachment vault, never in public_html.
CREATE TABLE IF NOT EXISTS office_communication_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(127) NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_attachment (message_id, sha256, filename),
    KEY idx_attachment_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
