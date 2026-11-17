-- Isolated Office-test VPS only; backup and verify target DB before applying.
-- Additive account columns required by the existing Office login and session code.
-- Existing users remain unable to log in until credentials are provisioned explicitly.
ALTER TABLE office_operator_accounts
  ADD COLUMN IF NOT EXISTS username VARCHAR(190) NULL,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS can_edit_crm TINYINT(1) NOT NULL DEFAULT 0;
-- Username uniqueness must be verified before applying this separate index.
-- Never grant CRM access or create default passwords automatically.
