-- BREBO Office outbound delivery ledger; existing Office database only.
-- Run once after backup. No existing mail records are modified.
CREATE TABLE IF NOT EXISTS office_mail_outbound_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  mailbox_id BIGINT UNSIGNED NOT NULL,
  operator_id VARCHAR(128) NOT NULL,
  recipient VARCHAR(254) NOT NULL,
  subject VARCHAR(512) NOT NULL,
  body_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status ENUM('reserved','accepted','failed','unknown') NOT NULL DEFAULT 'reserved',
  transport_reference VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_mail_outbound_idempotency (idempotency_key),
  KEY idx_office_mail_outbound_mailbox (mailbox_id,created_at),
  KEY idx_office_mail_outbound_operator (operator_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
