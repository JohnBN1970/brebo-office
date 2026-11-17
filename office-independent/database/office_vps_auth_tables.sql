-- Isolated VPS Office-test only. Backup and review existing columns before applying.
-- This script intentionally does not alter office_operator_accounts.
CREATE TABLE IF NOT EXISTS office_operator_sessions (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  operator_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  INDEX idx_office_sessions_operator (operator_id),
  INDEX idx_office_sessions_expiry (expires_at),
  CONSTRAINT fk_office_sessions_operator FOREIGN KEY (operator_id) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_login_attempts (
  identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  failures INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
