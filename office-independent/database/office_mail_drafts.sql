-- Optional native Office mail drafts. Install before enabling OFFICE_MAIL_DRAFTS_SCHEMA_VERIFIED.
-- Drafts are owned by the creating operator; no outgoing delivery is performed.
CREATE TABLE IF NOT EXISTS office_mail_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  operator_id VARCHAR(128) NOT NULL,
  source_message_id BIGINT UNSIGNED NOT NULL,
  draft_type ENUM('reply','forward') NOT NULL,
  recipient VARCHAR(1000) NOT NULL DEFAULT '',
  subject VARCHAR(512) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mail_draft_operator_source_type (operator_id,source_message_id,draft_type),
  KEY ix_mail_drafts_operator_updated (operator_id,updated_at),
  CONSTRAINT fk_mail_drafts_source FOREIGN KEY (source_message_id) REFERENCES office_mail_messages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
