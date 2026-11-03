-- Office-test migration only; apply after backup and schema review.
-- Unique batch_id ensures a failed/uncertain call cannot be automatically resent.
CREATE TABLE IF NOT EXISTS office_bank_submission_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status VARCHAR(40) NOT NULL,
  bank_reference VARCHAR(255) NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  changed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_bank_submission_batch (batch_id),
  UNIQUE KEY uq_office_bank_submission_key (idempotency_key),
  KEY ix_office_bank_submission_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
