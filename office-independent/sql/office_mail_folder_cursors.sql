-- Apply only after backup and schema review. Does not alter existing cursors.
CREATE TABLE IF NOT EXISTS office_mail_folder_cursors (
  mailbox_id BIGINT UNSIGNED NOT NULL,
  folder_name VARCHAR(100) NOT NULL,
  uidvalidity BIGINT UNSIGNED NOT NULL,
  last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (mailbox_id, folder_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
