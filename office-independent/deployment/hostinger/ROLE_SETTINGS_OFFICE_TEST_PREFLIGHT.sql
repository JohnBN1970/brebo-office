-- READ-ONLY preflight. Run only against the separately verified Office-test database.
-- Never paste production credentials or execute migrations as part of this check.
SELECT DATABASE() AS connected_database,
       CASE WHEN DATABASE() = 'u213420663_office_test'
            THEN 'EXPECTED_OFFICE_TEST' ELSE 'STOP_WRONG_DATABASE' END AS environment_check;

SELECT table_name, table_type
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'office_roles', 'office_user_roles', 'office_role_permissions',
    'office_role_scopes', 'office_role_settings_audit',
    'office_operator_accounts', 'office_module_permissions',
    'office_project_memberships'
  )
ORDER BY table_name;

SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN (
    'office_roles', 'office_user_roles', 'office_role_permissions',
    'office_role_scopes', 'office_role_settings_audit'
  )
ORDER BY table_name, ordinal_position;

-- Check existing direct settings grants only after verifying expected schema.
-- No INSERT, UPDATE, DELETE, CREATE, DROP or ALTER statements are included.
