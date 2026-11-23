-- BREBO Office-test / Deal -> Calc / read-only migration inventory.
-- Run ONLY on the intended VPS with: mysql -u root brebo_office_test < this-file
-- No DDL, no data changes, no credential output, no business row contents.
SELECT IF(DATABASE()='brebo_office_test','PASS TEST_DATABASE','BLOCKED WRONG_DATABASE') AS scope;
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA='brebo_office_test'
  AND TABLE_NAME IN (
    'office_crm_opportunities','office_calc_crm_drafts',
    'calculations','calculation_versions','calculation_lines',
    'office_calc_crm_acceptance_transfers','office_calc_work_budget_baselines',
    'office_project_calculation_scopes','office_calc_calculations')
ORDER BY TABLE_NAME;
SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA='brebo_office_test'
  AND TABLE_NAME IN ('office_crm_opportunities','office_calc_crm_drafts',
    'calculations','calculation_versions','calculation_lines',
    'office_calc_crm_acceptance_transfers','office_project_calculation_scopes')
ORDER BY TABLE_NAME,ORDINAL_POSITION;
SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='brebo_office_test'
  AND TABLE_NAME IN ('office_crm_opportunities','office_calc_crm_drafts',
    'calculations','calculation_versions','calculation_lines',
    'office_calc_crm_acceptance_transfers')
ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;
SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA='brebo_office_test'
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND TABLE_NAME IN ('office_crm_opportunities','office_calc_crm_drafts',
    'calculations','calculation_versions','calculation_lines',
    'office_calc_crm_acceptance_transfers')
ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION;
SELECT 'office_crm_opportunities' AS table_name,COUNT(*) AS row_count FROM office_crm_opportunities
UNION ALL SELECT 'office_calc_crm_acceptance_transfers',COUNT(*) FROM office_calc_crm_acceptance_transfers;
