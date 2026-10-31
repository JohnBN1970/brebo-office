-- Apply to Office-test only, after verifying operator identities.
-- No grants are inserted automatically.
CREATE TABLE IF NOT EXISTS office_communication_mailbox_access (
    mailbox_id BIGINT UNSIGNED NOT NULL,
    operator_id VARCHAR(128) NOT NULL,
    can_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (mailbox_id, operator_id),
    KEY idx_communication_mailbox_access_operator (operator_id, can_read),
    CONSTRAINT fk_communication_mailbox_access_mailbox
      FOREIGN KEY (mailbox_id) REFERENCES office_communication_mailboxes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
