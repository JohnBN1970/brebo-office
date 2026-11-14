-- BREBO Office-test CRM-first Calc: READ-ONLY schema diagnostic.
-- Run only against the verified Office-test connection. Does not create or change data.
-- A zero missing_required result does NOT prove that runtime code is deployed or authorized.
SELECT required.table_name,
       required.column_name,
       CASE WHEN actual.column_name IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS result
FROM (
    SELECT 'office_crm_opportunities' AS table_name, 'id' AS column_name
    UNION ALL SELECT 'office_crm_opportunities', 'revision'
    UNION ALL SELECT 'office_crm_opportunities', 'stage'
    UNION ALL SELECT 'office_crm_opportunities', 'status'
    UNION ALL SELECT 'office_calc_crm_drafts', 'id'
    UNION ALL SELECT 'office_calc_crm_drafts', 'opportunity_id'
    UNION ALL SELECT 'office_calc_crm_drafts', 'crm_revision'
    UNION ALL SELECT 'office_calc_crm_drafts', 'created_by'
    UNION ALL SELECT 'office_calc_crm_drafts', 'project_id'
    UNION ALL SELECT 'calculations', 'id'
    UNION ALL SELECT 'calculations', 'office_calculation_id'
    UNION ALL SELECT 'calculations', 'office_project_id'
    UNION ALL SELECT 'calculations', 'status'
    UNION ALL SELECT 'calculation_versions', 'id'
    UNION ALL SELECT 'calculation_versions', 'calculation_id'
    UNION ALL SELECT 'calculation_lines', 'id'
    UNION ALL SELECT 'calculation_lines', 'version_id'
) required
LEFT JOIN information_schema.COLUMNS actual
  ON actual.TABLE_SCHEMA = DATABASE()
 AND actual.TABLE_NAME = required.table_name
 AND actual.COLUMN_NAME = required.column_name
ORDER BY required.table_name, required.column_name;

-- Exactly one Calc row must be possible per CRM draft identity.
-- The expected index is a unique key on office_calculation_id, not merely a normal index.
SELECT TABLE_NAME, INDEX_NAME,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_index,
       MIN(NON_UNIQUE) AS non_unique
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'office_calc_crm_drafts' AND INDEX_NAME = 'uq_calc_crm_draft_opportunity')
    OR (TABLE_NAME = 'calculations' AND COLUMN_NAME = 'office_calculation_id')
  )
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

-- Read-only duplicate audit. Only execute after the above table checks pass.
-- SELECT office_calculation_id, COUNT(*) AS copies
-- FROM calculations WHERE office_calculation_id IS NOT NULL
-- GROUP BY office_calculation_id HAVING COUNT(*) > 1;
