-- BREBO Office-test: align mail operator references with office_operator_accounts.id VARCHAR(128).
-- Apply only to u213420663_office_test after a verified database backup.
-- Preflight: check foreign keys and dependent constraints before execution.
-- Do not run against production or other databases.
-- Existing indexes are retained by MODIFY COLUMN.
ALTER TABLE office_mail_mailboxes
  MODIFY COLUMN owner_operator_id VARCHAR(128) NULL;
ALTER TABLE office_mail_mailbox_access
  MODIFY COLUMN operator_id VARCHAR(128) NOT NULL;
ALTER TABLE office_mail_board_routes
  MODIFY COLUMN changed_by_operator_id VARCHAR(128) NULL;
ALTER TABLE office_mail_board_moves
  MODIFY COLUMN operator_id VARCHAR(128) NOT NULL;
ALTER TABLE office_mail_classification_proposals
  MODIFY COLUMN reviewed_by_operator_id VARCHAR(128) NULL;
-- After migration verify SHOW COLUMNS, SHOW INDEX and information_schema.KEY_COLUMN_USAGE.
-- Grant access explicitly to the intended active Office operator; never grant everyone access.
-- Do not enable cron/import until PHP code, mailbox ACL and board routing are deployed/tested.
