-- BREBO Office-test Calc bridge: READ-ONLY schema inventory.
-- Run against the intended Office-test database before any Calc activation.
-- No DDL, DML, grants or session data are changed.
SELECT
  required.table_name,
  CASE WHEN actual.table_name IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS table_status
FROM (
  SELECT 'office_projects' AS table_name
  UNION ALL SELECT 'office_project_memberships'
  UNION ALL SELECT 'office_calc_calculations'
  UNION ALL SELECT 'office_calc_project_access'
  UNION ALL SELECT 'office_calc_session_grants'
  UNION ALL SELECT 'office_calc_signed_request_ids'
) AS required
LEFT JOIN information_schema.tables AS actual
  ON actual.table_schema = DATABASE()
 AND actual.table_name = required.table_name
ORDER BY required.table_name;

-- Column-level contract; absence of any required column blocks activation.
SELECT required.table_name, required.column_name,
  CASE WHEN actual.column_name IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS column_status
FROM (
  SELECT 'office_calc_calculations' AS table_name, 'id' AS column_name
  UNION ALL SELECT 'office_calc_calculations', 'project_id'
  UNION ALL SELECT 'office_calc_project_access', 'calculation_id'
  UNION ALL SELECT 'office_calc_project_access', 'project_id'
  UNION ALL SELECT 'office_calc_project_access', 'operator_id'
  UNION ALL SELECT 'office_calc_project_access', 'permission'
  UNION ALL SELECT 'office_project_memberships', 'operator_id'
  UNION ALL SELECT 'office_project_memberships', 'project_id'
  UNION ALL SELECT 'office_project_memberships', 'permission'
  UNION ALL SELECT 'office_calc_session_grants', 'reference'
  UNION ALL SELECT 'office_calc_session_grants', 'session_hash'
  UNION ALL SELECT 'office_calc_session_grants', 'actor_id'
  UNION ALL SELECT 'office_calc_session_grants', 'project_id'
  UNION ALL SELECT 'office_calc_session_grants', 'calculation_id'
  UNION ALL SELECT 'office_calc_session_grants', 'expires_at'
  UNION ALL SELECT 'office_calc_session_grants', 'revoked_at'
  UNION ALL SELECT 'office_calc_signed_request_ids', 'request_id'
  UNION ALL SELECT 'office_calc_signed_request_ids', 'expires_at'
) AS required
LEFT JOIN information_schema.columns AS actual
  ON actual.table_schema = DATABASE()
 AND actual.table_name = required.table_name
 AND actual.column_name = required.column_name
ORDER BY required.table_name, required.column_name;
