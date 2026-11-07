-- Staging migration only. Do not apply without reviewed Office-test preflight.
-- Grant reference and session hash are SHA-256/cryptographic hex identifiers;
-- raw Office session tokens are never persisted here.
CREATE TABLE IF NOT EXISTS office_calc_session_grants (
  reference CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  calculation_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reference),
  KEY idx_office_calc_grant_session (session_hash),
  KEY idx_office_calc_grant_scope (actor_id, project_id, calculation_id),
  KEY idx_office_calc_grant_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
