-- Apply only to Office-test after backup and schema verification.
USE brebo_office_test;
ALTER TABLE office_mail_mailboxes ADD COLUMN IF NOT EXISTS gateway_ref VARCHAR(190) NULL DEFAULT NULL AFTER credential_ref;
