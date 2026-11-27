-- READ-ONLY preflight for PR #2235; run against brebo_office_test only.
-- This script does not create or modify tables.
SELECT DATABASE() AS database_name;
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('office_calc_crm_drafts','office_operator_accounts','office_calc_deal_revision_reviews')
ORDER BY TABLE_NAME;
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME='office_calc_crm_drafts' AND COLUMN_NAME='id')
    OR (TABLE_NAME='office_operator_accounts' AND COLUMN_NAME='id')
    OR (TABLE_NAME='office_calc_deal_revision_reviews'
        AND COLUMN_NAME IN ('draft_id','deal_revision','reviewed_by')))
ORDER BY TABLE_NAME,COLUMN_NAME;
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME IN ('office_calc_crm_drafts','office_operator_accounts','office_calc_deal_revision_reviews')
ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;
-- GO only when both parent IDs are BIGINT UNSIGNED, parent engines are InnoDB,
-- both parent id columns are indexed, and the review table does not already exist.
-- If the review table exists, stop and compare its structure; do not rerun CREATE TABLE.
-- Confirm an Office-test backup separately before executing the REVIEW migration.
