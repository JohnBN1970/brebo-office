-- BREBO Office-test native mail context links.
-- Apply only after backup, on the Office-test database, never automatically on production.
-- One canonical message is referenced by multiple module dossiers; no mail content copied.
CREATE TABLE IF NOT EXISTS office_mail_communication_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) NOT NULL,
    module VARCHAR(48) NOT NULL,
    object_type VARCHAR(48) NOT NULL,
    object_id BIGINT UNSIGNED NOT NULL,
    confirmed_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY office_mail_link_unique (message_id, module, object_type, object_id),
    KEY office_mail_link_target (module, object_type, object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
