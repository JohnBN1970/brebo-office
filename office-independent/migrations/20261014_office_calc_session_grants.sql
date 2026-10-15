-- BREBO Office independent Calc session grants.
-- Apply only to reviewed staging schema first; never auto-run on production.
-- Opaque references and Office session identifiers are stored only as SHA-256 hashes.
CREATE TABLE IF NOT EXISTS office_calc_session_grants (
  reference_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  office_session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  calculation_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (reference_hash),
  KEY office_calc_grants_session_idx (office_session_hash, revoked_at),
  KEY office_calc_grants_expiry_idx (expires_at),
  KEY office_calc_grants_scope_idx (actor_id, project_id, calculation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
