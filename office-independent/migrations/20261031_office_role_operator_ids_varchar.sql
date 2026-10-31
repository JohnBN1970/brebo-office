-- Office-test only. Review and back up before execution.
-- Preflight: all existing numeric role assignments must match an Office operator ID.
-- Do not execute against production without separate acceptance.
-- MySQL/MariaDB DDL auto-commits; take a verified backup first.
ALTER TABLE office_user_roles
  MODIFY operator_id VARCHAR(128) NOT NULL;
ALTER TABLE office_role_settings_audit
  MODIFY actor_operator_id VARCHAR(128) NOT NULL;
-- Follow-up: migrate PHP operator identity handling to strings before deployment.
