-- Isolated Office-test VPS only. Backup and verify the selected database first.
-- MariaDB supports ADD COLUMN IF NOT EXISTS. No passwords or grants are assigned.
-- Existing users cannot log in until explicitly provisioned.
ALTER TABLE office_operator_accounts
  ADD COLUMN IF NOT EXISTS username VARCHAR(190) NULL,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS can_edit_crm TINYINT(1) NOT NULL DEFAULT 0;

-- Username is intentionally nullable for legacy accounts. Unique usernames
-- are required for unambiguous login; verify duplicates before creating index.
CREATE UNIQUE INDEX IF NOT EXISTS ux_office_operator_username
  ON office_operator_accounts (username);
