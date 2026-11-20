-- Native Office-test staging only. Apply after review and backup.
-- Does not copy personal mailbox content or commit any dossier relationship.
CREATE TABLE IF NOT EXISTS office_mail_push_proposals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  source_id VARCHAR(512) NOT NULL,
  owner_uid BIGINT UNSIGNED NOT NULL,
  actor_uid BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(512) NOT NULL DEFAULT '',
  status VARCHAR(32) NOT NULL DEFAULT 'pending_review',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_mail_push_identity (identity_hash),
  KEY ix_office_mail_push_owner_status (owner_uid, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
