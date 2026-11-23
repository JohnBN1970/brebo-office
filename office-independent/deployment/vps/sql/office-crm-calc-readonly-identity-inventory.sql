-- BREBO Office-test / CRM to Calc readiness inventory
-- READ ONLY: selects only information_schema metadata and aggregate counts.
-- Execute manually with: sudo mysql --table brebo_office_test < this-file.sql
-- Does not install schemas or change runtime flags.

SELECT 'DATABASE' AS check_name, DATABASE() AS observed_database,
       IF(DATABASE() = 'brebo_office_test', 'MATCH', 'BLOCKED') AS result;

SELECT 'REQUIRED_TABLE' AS check_name, wanted.name AS object_name,
       IF(t.TABLE_NAME IS NULL,'MISSING','PRESENT') AS result
FROM (
 SELECT 'office_crm_opportunities' name UNION ALL
 SELECT 'office_operator_accounts' UNION ALL
 SELECT 'office_calc_crm_drafts' UNION ALL
 SELECT 'calculations' UNION ALL
 SELECT 'calculation_versions' UNION ALL
 SELECT 'calculation_lines'
) AS wanted
LEFT JOIN information_schema.TABLES t ON t.TABLE_SCHEMA='brebo_office_test' AND t.TABLE_NAME=wanted.name
ORDER BY wanted.name;

SELECT 'IDENTITY_COLUMN' AS check_name, wanted.table_name, wanted.column_name,
       COALESCE(c.COLUMN_TYPE,'MISSING') AS column_type,
       COALESCE(c.IS_NULLABLE,'MISSING') AS nullable,
       COALESCE(c.EXTRA,'MISSING') AS extra,
       COALESCE(c.COLLATION_NAME,'-') AS collation_name
FROM (
 SELECT 'office_crm_opportunities' table_name,'id' column_name UNION ALL
 SELECT 'office_crm_opportunities','revision' UNION ALL
 SELECT 'office_operator_accounts','id' UNION ALL
 SELECT 'office_calc_crm_drafts','id' UNION ALL
 SELECT 'office_calc_crm_drafts','opportunity_id' UNION ALL
 SELECT 'office_calc_crm_drafts','created_by' UNION ALL
 SELECT 'calculations','office_calculation_id'
) AS wanted
LEFT JOIN information_schema.COLUMNS c
 ON c.TABLE_SCHEMA='brebo_office_test' AND c.TABLE_NAME=wanted.table_name
 AND c.COLUMN_NAME=wanted.column_name
ORDER BY wanted.table_name,wanted.column_name;

SELECT 'DRAFT_INDEX' AS check_name, INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='brebo_office_test' AND TABLE_NAME='office_calc_crm_drafts'
ORDER BY INDEX_NAME,SEQ_IN_INDEX;

SELECT 'CRM_REVISION_REFERENCES' AS check_name, TABLE_NAME, COLUMN_NAME,
       COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA='brebo_office_test'
  AND (TABLE_NAME='office_crm_opportunities' OR TABLE_NAME='office_calc_crm_acceptance_transfers')
  AND COLUMN_NAME IN ('id','revision','crm_opportunity_id','accepted_revision','calculation_id')
ORDER BY TABLE_NAME,COLUMN_NAME;

SELECT 'EXISTING_HANDOFF_COUNT' AS check_name,
       (SELECT COUNT(*) FROM office_calc_crm_acceptance_transfers) AS transfers,
       (SELECT COUNT(*) FROM office_calc_work_budget_baselines) AS baselines,
       (SELECT COUNT(*) FROM office_calc_work_budget_lines) AS baseline_lines;

SELECT 'STATUS' AS check_name, 'READ_ONLY_INVENTORY_COMPLETE_NOT_MIGRATION_APPROVAL' AS result;
