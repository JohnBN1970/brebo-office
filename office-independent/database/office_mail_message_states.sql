-- Optional per-operator mailbox state. Does not mutate source messages or Office work routing.
CREATE TABLE IF NOT EXISTS office_mail_message_states (
  operator_id VARCHAR(128) NOT NULL,
  message_id BIGINT UNSIGNED NOT NULL,
  folder ENUM('inbox','archive','trash','spam') NOT NULL DEFAULT 'inbox',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (operator_id,message_id),
  KEY ix_mail_state_operator_folder (operator_id,folder),
  CONSTRAINT fk_mail_state_message FOREIGN KEY (message_id) REFERENCES office_mail_messages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
