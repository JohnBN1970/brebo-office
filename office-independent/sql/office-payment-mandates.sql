-- Office-test only. Apply with migration review and backup.
-- Explicit per-operator payment authority; absent or expired means deny.
CREATE TABLE IF NOT EXISTS office_payment_mandates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  operator_id BIGINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  mode ENUM('single','dual','threshold') NOT NULL,
  limit_cents BIGINT UNSIGNED NOT NULL,
  dual_above_cents BIGINT UNSIGNED NULL,
  valid_from DATETIME NOT NULL,
  valid_until DATETIME NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_payment_mandate_operator (operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_payment_mandate_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mandate_id BIGINT UNSIGNED NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL,
  previous_values_json JSON NULL,
  new_values_json JSON NOT NULL,
  PRIMARY KEY (id),
  KEY ix_office_payment_mandate_audit (mandate_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
