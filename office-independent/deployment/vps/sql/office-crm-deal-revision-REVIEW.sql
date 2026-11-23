-- BREBO Office-test only / REVIEW REQUIRED / DO NOT AUTO-EXECUTE
-- Verified by read-only VPS identity inventory on 2026-11-23:
-- brebo_office_test.office_crm_opportunities.id = BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
-- brebo_office_test.office_operator_accounts.id = BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
-- brebo_office_test.office_crm_opportunities.revision = MISSING
-- Existing acceptance transfers and work-budget baseline tables contain zero rows.
--
-- Execution prerequisites:
--  1. Isolated brebo_office_test confirmed on the executing connection
--  2. Database backup completed and RESTORE tested independently
--  3. Existing CRM opportunity rows evaluated; assigning baseline revision 1
--     to existing rows must be approved, because historic changes cannot be inferred.
--  4. No active CRM writes during DDL; test maintenance window
--  5. Full post-install information_schema verification and rollback plan
--  6. A reviewed application release; do not enable any featureflags here
--
-- Important: MySQL ALTER TABLE does an implicit commit and is not rolled back by ROLLBACK.
-- Only execute once, after the prerequisites are signed off.
-- This change does NOT touch project or work-budget tables.

ALTER TABLE office_crm_opportunities
  ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 1
  AFTER currency;

-- Post-check (read-only):
-- SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
--        COLUMN_DEFAULT FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA='brebo_office_test'
--   AND TABLE_NAME='office_crm_opportunities'
--   AND COLUMN_NAME='revision';
