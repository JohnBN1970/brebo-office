#!/usr/bin/env bash
# BREBO Office-test CRM -> Calc shared-identity schema installation.
# OPERATOR-RUN ONLY, requires explicit authorization for isolated test DB.
# Never deploys source files, modifies flags or writes to production.
set -euo pipefail
umask 077
fail(){ echo "CRM_CALC_INSTALL=BLOCKED: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fail "root required"
[ "${1:-}" = "--approved-test-only" ] || fail "explicit --approved-test-only argument required"
command -v mysql >/dev/null || fail "mysql unavailable"
command -v sha256sum >/dev/null || fail "sha256sum unavailable"
command -v gzip >/dev/null || fail "gzip unavailable"
command -v curl >/dev/null || fail "curl unavailable"

BACKUP="/root/brebo-backups/brebo_office_test_before_calc_20261123-120429.sql.gz"
BACKUP_SHA="4d96c9da39b05e4baf7e694a82dccb7b66075cf242bea7f7c26743523f8ffdc5"
[ -s "$BACKUP" ] || fail "verified backup absent"
printf '%s  %s\n' "$BACKUP_SHA" "$BACKUP" | sha256sum -c - >/dev/null || fail "backup checksum"
gzip -t "$BACKUP" || fail "backup gzip integrity"
[ -f /etc/brebo/office-test-db.env ] || fail "Office-test db configuration absent"
[ -L /srv/brebo/office-test/current ] || fail "isolated Office-test current release absent"

# VPS identity and absence proof: never infer correct database from defaults.
readonly DB="brebo_office_test"
q(){ mysql --batch --skip-column-names "$DB" -e "$1"; }
[ "$(q 'SELECT DATABASE()')" = "$DB" ] || fail "database identity"
[ "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_calc_calculations','office_calc_project_access','office_calc_crm_drafts','calculations','calculation_versions','calculation_lines')")" = 0 ] || fail "some target tables already exist"
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_opportunities' AND COLUMN_NAME='revision'")" = 0 ] || fail "CRM revision already exists"
[ "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_crm_opportunities','office_operator_accounts','office_calc_crm_acceptance_transfers') AND ENGINE='InnoDB'")" = 3 ] || fail "required existing tables absent/not InnoDB"
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='office_crm_opportunities' AND COLUMN_NAME='id') OR (TABLE_NAME='office_operator_accounts' AND COLUMN_NAME='id')) AND COLUMN_TYPE LIKE 'bigint%unsigned' AND IS_NULLABLE='NO'")" = 2 ] || fail "ID columns not unsigned BIGINT"
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_opportunities' AND COLUMN_NAME='currency'")" = 1 ] || fail "currency column missing"
[ "$(q "SELECT COUNT(*) FROM office_calc_crm_acceptance_transfers")" = 0 ] || fail "preexisting acceptance transfers; manual review"
[ "$(q "SELECT COUNT(*) FROM office_crm_opportunities")" = 2 ] || fail "Deal count differs from restored backup"
[ "$(q "SELECT COUNT(*) FROM office_crm_opportunities WHERE stage='won' AND status='closed'")" = 2 ] || fail "unexpected Deal statuses"
[ "$(q "SELECT COUNT(*) FROM office_crm_opportunities WHERE status='open'")" = 0 ] || fail "live open Deals require review"

# Pin migration SQL to an already reviewed commit, NOT the moving featurebranch.
SQL_URL="https://raw.githubusercontent.com/JohnBN1970/brebo-office/055c7a48d728cad6126eaedbcd4e4b02d2b04741/office-independent/deployment/vps/sql/office-crm-calc-shared-identity-REVIEW.sql"
TMP="$(mktemp /tmp/brebo-crm-calc-schema.XXXXXX)"
trap 'rm -f "$TMP"' EXIT
curl -fLsS --retry 2 "$SQL_URL" -o "$TMP"
[ -s "$TMP" ] || fail "SQL source is empty"
[ "$(grep -c '^CREATE TABLE ' "$TMP")" = 6 ] || fail "expected exactly six CREATE TABLE statements"
grep -q 'REFERENCES office_calc_calculations(id)' "$TMP" || fail "canonical ID FK missing"
grep -q 'REFERENCES office_calc_calculations (id,project_id)' "$TMP" || fail "project access FK missing"
if grep -q 'IF NOT EXISTS' "$TMP"; then fail "non-failing table creation forbidden"; fi

echo "CRM_CALC_BACKUP_RESTORED=PREVIOUSLY_PASS"
echo "CRM_CALC_TEST_DB_PRECONDITIONS=PASS"
echo "CRM_CALC_SCHEMA_INSTALL=STARTING"
# MySQL DDL makes implicit commits. If interrupted, STOP and inspect; do NOT rerun.
mysql "$DB" -e "ALTER TABLE office_crm_opportunities ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER currency"
mysql "$DB" < "$TMP"

[ "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_calc_calculations','office_calc_project_access','office_calc_crm_drafts','calculations','calculation_versions','calculation_lines')")" = 6 ] || fail "not all schema tables installed"
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_opportunities' AND COLUMN_NAME='revision'")" = 1 ] || fail "Deal revision absent after migration"
[ "$(q "SELECT COUNT(*) FROM office_crm_opportunities WHERE revision=1")" = 2 ] || fail "Deal revision initialization"
[ "$(q "SELECT COUNT(*) FROM office_calc_crm_drafts")" = 0 ] || fail "unexpected CRM drafts"
[ "$(q "SELECT COUNT(*) FROM office_calc_calculations")" = 0 ] || fail "unexpected Office identity records"
[ "$(q "SELECT COUNT(*) FROM office_calc_project_access")" = 0 ] || fail "unexpected project access grants"
echo "CRM_CALC_SCHEMA_INSTALL=PASS"
echo "CRM_CALC_FLAGS=UNCHANGED"
echo "CRM_CALC_BROWSER_E2E=NOT_VERIFIED"
