-- Issue #2082: one canonical mail message, multiple approved Office contexts.
-- Additive only. Do not enable UI before authorization integration.
CREATE TABLE IF NOT EXISTS office_mail_context_links (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 message_id BIGINT UNSIGNED NOT NULL,
 module_key VARCHAR(32) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 linked_by VARCHAR(128) NOT NULL,
 linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_mail_context (message_id,module_key,entity_id),
 KEY ix_mail_context_lookup (module_key,entity_id,message_id),
 KEY ix_mail_context_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
