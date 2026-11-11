-- Native Office mailbox registry. No Drupal identifiers or services.
-- Credentials are referenced by private configuration keys, never stored here.
CREATE TABLE IF NOT EXISTS office_mail_mailboxes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  address VARCHAR(320) NOT NULL,
  label VARCHAR(190) NOT NULL,
  mailbox_type ENUM('functional','personal') NOT NULL,
  owner_operator_id BIGINT UNSIGNED DEFAULT NULL,
  credential_ref VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_mail_mailbox_address (address),
  KEY ix_office_mail_owner (owner_operator_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_mail_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mailbox_id BIGINT UNSIGNED NOT NULL,
  source_id VARCHAR(512) NOT NULL,
  source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  thread_id VARCHAR(512) DEFAULT NULL,
  sender VARCHAR(320) NOT NULL DEFAULT '',
  recipient VARCHAR(1000) NOT NULL DEFAULT '',
  subject VARCHAR(512) NOT NULL DEFAULT '',
  received_at DATETIME NOT NULL,
  body_text MEDIUMTEXT DEFAULT NULL,
  classification_status ENUM('unprocessed','pending_review','approved','rejected') NOT NULL DEFAULT 'unprocessed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_mail_message_identity (mailbox_id, source_hash),
  KEY ix_office_mail_message_recent (mailbox_id, received_at, id),
  CONSTRAINT fk_office_mail_message_mailbox FOREIGN KEY (mailbox_id)
    REFERENCES office_mail_mailboxes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_mail_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  filename VARCHAR(512) NOT NULL,
  content_type VARCHAR(190) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL,
  private_storage_key VARCHAR(512) NOT NULL,
  sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (id),
  KEY ix_office_mail_attachment_message (message_id),
  CONSTRAINT fk_office_mail_attachment_message FOREIGN KEY (message_id)
    REFERENCES office_mail_messages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explicit functional mailbox permissions; personal mailbox access is owner-only.
CREATE TABLE IF NOT EXISTS office_mail_mailbox_access (
  mailbox_id BIGINT UNSIGNED NOT NULL,
  operator_id BIGINT UNSIGNED NOT NULL,
  can_read TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (mailbox_id, operator_id),
  KEY ix_office_mail_access_operator (operator_id, can_read),
  CONSTRAINT fk_office_mail_access_mailbox FOREIGN KEY (mailbox_id)
    REFERENCES office_mail_mailboxes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classification suggestions are not dossier links; approval is a separate operation.
CREATE TABLE IF NOT EXISTS office_mail_classification_proposals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  proposed_module VARCHAR(32) NOT NULL,
  proposed_target_id BIGINT UNSIGNED DEFAULT NULL,
  reason VARCHAR(1000) NOT NULL DEFAULT '',
  status ENUM('pending_review','approved','rejected') NOT NULL DEFAULT 'pending_review',
  reviewed_by_operator_id BIGINT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_mail_classification_message (message_id),
  KEY ix_office_mail_classification_status (status, created_at),
  CONSTRAINT fk_office_mail_classification_message FOREIGN KEY (message_id)
    REFERENCES office_mail_messages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
