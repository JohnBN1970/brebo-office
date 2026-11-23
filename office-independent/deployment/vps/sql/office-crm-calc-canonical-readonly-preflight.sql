-- BREBO Office-test. READ-ONLY canonical Calc identity + permissions inventory.
-- Run ONLY against brebo_office_test, explicitly selected by mysql client.
-- No schema/data writes. Never includes secrets or personal business records.
SELECT DATABASE() AS database_name,
       CASE WHEN DATABASE()='brebo_office_test' THEN 'MATCH' ELSE 'BLOCKED' END AS safety;

SELECT expected.table_name,
       COALESCE(t.ENGINE,'MISSING') AS engine,
       CASE WHEN t.TABLE_NAME IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS status
FROM (
 SELECT 'office_calc_calculations' table_name
 UNION ALL SELECT 'office_calc_project_access'
 UNION ALL SELECT 'office_calc_crm_drafts'
 UNION ALL SELECT 'calculations'
 UNION ALL SELECT 'calculation_versions'
 UNION ALL SELECT 'calculation_lines'
 UNION ALL SELECT 'office_crm_opportunities'
) expected
LEFT JOIN information_schema.TABLES t
 ON t.TABLE_SCHEMA='brebo_office_test' AND t.TABLE_NAME=expected.table_name
ORDER BY expected.table_name;

SELECT expected.table_name, expected.column_name,
 COALESCE(c.COLUMN_TYPE,'MISSING') AS column_type,
 COALESCE(c.IS_NULLABLE,'MISSING') AS nullable,
 COALESCE(c.COLUMN_DEFAULT,'-') AS default_value
FROM (
 SELECT 'office_calc_calculations' table_name,'id' column_name
 UNION ALL SELECT 'office_calc_calculations','project_id'
 UNION ALL SELECT 'office_calc_project_access','calculation_id'
 UNION ALL SELECT 'office_calc_project_access','project_id'
 UNION ALL SELECT 'office_calc_project_access','operator_id'
 UNION ALL SELECT 'office_calc_project_access','permission'
 UNION ALL SELECT 'office_calc_crm_drafts','calculation_id'
 UNION ALL SELECT 'office_calc_crm_drafts','opportunity_id'
 UNION ALL SELECT 'calculations','office_calculation_id'
 UNION ALL SELECT 'calculations','office_project_id'
 UNION ALL SELECT 'office_crm_opportunities','revision'
) expected
LEFT JOIN information_schema.COLUMNS c
 ON c.TABLE_SCHEMA='brebo_office_test' AND c.TABLE_NAME=expected.table_name
 AND c.COLUMN_NAME=expected.column_name
ORDER BY expected.table_name,expected.column_name;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='brebo_office_test'
 AND TABLE_NAME IN ('office_calc_calculations','office_calc_project_access','office_calc_crm_drafts','calculations')
ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;

SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA='brebo_office_test'
 AND TABLE_NAME IN ('office_calc_calculations','office_calc_project_access','office_calc_crm_drafts','calculations')
 AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME,COLUMN_NAME;

SELECT 'READ_ONLY_DONE_NO_MIGRATION' AS result;
