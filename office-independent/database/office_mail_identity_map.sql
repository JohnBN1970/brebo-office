-- Manual, reviewed identity binding only. Never populate by matching IDs.
-- verified_at must be set only after independent owner verification.
CREATE TABLE IF NOT EXISTS office_mail_identity_map (
  office_operator_id BIGINT UNSIGNED NOT NULL,
  drupal_uid BIGINT UNSIGNED NOT NULL,
  verified_at DATETIME DEFAULT NULL,
  verified_by BIGINT UNSIGNED DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  PRIMARY KEY (office_operator_id),
  UNIQUE KEY uq_office_mail_drupal_uid (drupal_uid),
  KEY ix_office_mail_identity_active (verified_at, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
