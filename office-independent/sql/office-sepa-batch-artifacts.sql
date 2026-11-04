-- Office-test only. Private immutable SEPA artifact metadata.
-- XML ciphertext is never served by public web routes.
-- The application must encrypt before insert using a managed external key.
CREATE TABLE IF NOT EXISTS office_sepa_batch_artifacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ciphertext LONGBLOB NOT NULL,
  encryption_nonce VARBINARY(24) NOT NULL,
  encryption_key_id VARCHAR(120) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_sepa_artifact_batch (batch_id),
  KEY ix_office_sepa_artifact_hash (payload_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- No UPDATE/DELETE application privileges should be granted on this table.
