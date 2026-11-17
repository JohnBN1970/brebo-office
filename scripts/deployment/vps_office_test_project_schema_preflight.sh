#!/usr/bin/env bash
set -euo pipefail
# Read-only inventory: never creates or changes tables.
test "$(id -u)" -eq 0 || { echo ROOT_REQUIRED >&2; exit 2; }
ENV_FILE=/etc/brebo/office-test-db.env
test -f "$ENV_FILE" && test ! -L "$ENV_FILE" || { echo DB_ENV_MISSING >&2; exit 2; }
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a
test "${OFFICE_TEST_DB_NAME:-}" = brebo_office_test || { echo WRONG_DATABASE >&2; exit 3; }
MYSQL_PWD="${OFFICE_TEST_DB_PASSWORD:?}" mariadb --batch --skip-column-names \
  -h "${OFFICE_TEST_DB_HOST:-localhost}" -u "${OFFICE_TEST_DB_USER:?}" brebo_office_test <<'SQL'
SELECT CONCAT('PROJECT_TABLE=',TABLE_NAME)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE()
AND (TABLE_NAME LIKE 'office_project%' OR TABLE_NAME LIKE 'office_operator%' OR TABLE_NAME LIKE 'office_work_budget%')
ORDER BY TABLE_NAME;
SELECT CONCAT('PROJECT_COLUMN=',TABLE_NAME,'.',COLUMN_NAME,' ',COLUMN_TYPE,' ',IS_NULLABLE)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
AND TABLE_NAME IN ('office_projects','office_project_memberships','office_operator_accounts','office_project_buildings')
ORDER BY TABLE_NAME,ORDINAL_POSITION;
SELECT CONCAT('TOTAL_TABLES=',COUNT(*)) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE();
SQL
echo PROJECT_SCHEMA_PREFLIGHT_READ_ONLY=YES
