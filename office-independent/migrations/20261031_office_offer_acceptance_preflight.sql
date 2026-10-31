-- READ ONLY: run in the intended Office-test database before any migration.
-- Inspect column types and actual table presence; do not assume a green CI means deployed.
SELECT DATABASE() AS selected_database, @@hostname AS database_host, VERSION() AS database_version;

SELECT expected.table_name,
       IF(t.table_name IS NULL, 'MISSING', 'PRESENT') AS table_status
FROM (
  SELECT 'office_operator_accounts' AS table_name UNION ALL
  SELECT 'office_project_memberships' UNION ALL
  SELECT 'office_module_permissions' UNION ALL
  SELECT 'office_user_roles' UNION ALL
  SELECT 'office_role_settings_audit' UNION ALL
  SELECT 'office_offer_change_links' UNION ALL
  SELECT 'office_offers' UNION ALL
  SELECT 'office_offer_acceptances' UNION ALL
  SELECT 'office_project_change_orders' UNION ALL
  SELECT 'office_project_contracts'
) expected
LEFT JOIN information_schema.tables t
  ON t.table_schema = DATABASE() AND t.table_name = expected.table_name
ORDER BY expected.table_name;

SELECT c.table_name, c.column_name, c.column_type, c.is_nullable,
       c.column_key, c.extra
FROM information_schema.columns c
WHERE c.table_schema = DATABASE()
  AND (
    (c.table_name = 'office_operator_accounts' AND c.column_name = 'id')
    OR (c.table_name = 'office_project_memberships' AND c.column_name = 'operator_id')
    OR (c.table_name = 'office_module_permissions' AND c.column_name = 'operator_id')
    OR (c.table_name = 'office_user_roles' AND c.column_name = 'operator_id')
    OR (c.table_name = 'office_role_settings_audit' AND c.column_name = 'actor_operator_id')
    OR (c.table_name = 'office_offer_change_links' AND c.column_name = 'linked_by_operator_id')
  )
ORDER BY c.table_name, c.column_name;

-- Review any constraints before changing a linked identity column.
SELECT k.table_name, k.column_name, k.constraint_name,
       k.referenced_table_name, k.referenced_column_name
FROM information_schema.key_column_usage k
WHERE k.table_schema = DATABASE()
  AND k.table_name IN ('office_operator_accounts',
                       'office_project_memberships',
                       'office_module_permissions',
                       'office_user_roles',
                       'office_offer_change_links')
  AND k.column_name IN ('id', 'operator_id', 'linked_by_operator_id')
ORDER BY k.table_name, k.constraint_name;

-- NO ALTER/INSERT/UPDATE/DELETE in this file.
