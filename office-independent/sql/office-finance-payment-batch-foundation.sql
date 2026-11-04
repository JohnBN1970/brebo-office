-- BREBO Office-test Finance payment batch foundation.
-- Source: brebo_finance PaymentBatchManager, DatabasePaymentBatchRepository,
-- brebo_finance.install audit schema, and OfficePaymentReadiness.
-- Additive only. Bank submission and payment activation are NOT enabled.
-- Run against u213420663_office_test only after backup and schema preflight.
-- MariaDB/MySQL DDL implicitly commits; do not treat this as transactional.

CREATE TABLE IF NOT EXISTS brebo_finance_payment_batch (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_number VARCHAR(64) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  execution_date VARCHAR(10) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  item_count INT UNSIGNED NOT NULL,
  control_sum DECIMAL(18,2) NOT NULL,
  payload_hash VARCHAR(64) NOT NULL,
  controller_verdict VARCHAR(24) NOT NULL DEFAULT 'pending',
  controller_payload LONGTEXT NULL,
  reviewed INT UNSIGNED NULL,
  reviewed_by INT UNSIGNED NULL,
  release_note TEXT NULL,
  released INT UNSIGNED NULL,
  released_by INT UNSIGNED NULL,
  sealed_hash VARCHAR(64) NULL,
  created INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  changed INT UNSIGNED NOT NULL,
  changed_by INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brebo_finance_payment_batch_number (batch_number),
  KEY ix_brebo_finance_payment_batch_status (status),
  KEY ix_brebo_finance_payment_batch_created (created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brebo_finance_payment_batch_item (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  project_nid INT UNSIGNED NOT NULL,
  release_id INT UNSIGNED NOT NULL,
  invoice_id INT UNSIGNED NOT NULL,
  instruction_type VARCHAR(24) NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  creditor_name VARCHAR(140) NOT NULL,
  creditor_iban VARCHAR(34) NOT NULL,
  creditor_bic VARCHAR(11) NULL,
  recipient_hash VARCHAR(64) NOT NULL,
  end_to_end_id VARCHAR(35) NOT NULL,
  remittance_information VARCHAR(140) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'prepared',
  created INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brebo_finance_payment_batch_position (batch_id, position),
  UNIQUE KEY uq_brebo_finance_payment_batch_end_to_end (batch_id, end_to_end_id),
  KEY ix_brebo_finance_payment_batch_item_release (release_id),
  KEY ix_brebo_finance_payment_batch_item_invoice (invoice_id),
  CONSTRAINT fk_brebo_finance_payment_batch_item_batch
    FOREIGN KEY (batch_id) REFERENCES brebo_finance_payment_batch (id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS brebo_finance_audit (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_nid INT UNSIGNED NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  before_hash VARCHAR(64) NULL,
  after_hash VARCHAR(64) NULL,
  payload LONGTEXT NULL,
  reason TEXT NULL,
  created INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_brebo_finance_audit_project_created (project_nid, created),
  KEY ix_brebo_finance_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
