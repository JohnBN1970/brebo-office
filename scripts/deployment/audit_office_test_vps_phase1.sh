#!/usr/bin/env bash
# BREBO Office VPS phase-1 read-only inventory. Never print credentials.
set -euo pipefail
BASE=/srv/brebo/office-test
HOST=office-test.brebobv.nl
test "$(id -un)" = brebo-deploy || { echo "AUDIT_BLOCKED=EXPECTED_BREBO_DEPLOY"; exit 1; }
test -L "$BASE/current" || { echo "AUDIT_BLOCKED=NO_ACTIVE_SYMLINK"; exit 1; }
release="$(readlink -f "$BASE/current")"
case "$release" in "$BASE"/releases/*) ;; *) echo "AUDIT_BLOCKED=INVALID_RELEASE_PATH"; exit 1;; esac
test -r "$release/.office-source-sha" || { echo "AUDIT_BLOCKED=NO_RELEASE_MARKER"; exit 1; }
sha="$(cat "$release/.office-source-sha")"
[[ "$sha" =~ ^[a-f0-9]{40}$ ]] || { echo "AUDIT_BLOCKED=INVALID_SHA"; exit 1; }
printf 'AUDIT_ACTIVE_SHA=%s\n' "$sha"
printf 'AUDIT_RELEASE=%s\n' "$release"
for file in public/index.php public/office-login.php public/office-finance.php public/office-finance-center.php public/office-calc-crm-workbench.php public/office-buildings.php public/office-projects.php public/office-personal-dashboard.php packages/brebo-finance-core/bootstrap.php deployment/sql/20261118_native_purchase_invoices.sql; do
  if test -r "$release/$file"; then echo "AUDIT_FILE_PRESENT=$file"; else echo "AUDIT_FILE_MISSING=$file"; fi
done
for path in / /office-test/ /office-test/inloggen /office-test/crm/relaties /office-test/gebouwen /office-test/calculatie /office-test/projecten /office-test/finance /office-test/finance/administratie /office-test/mijn-dashboard; do
  code="$(curl -ksS --max-time 12 --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$path" || true)"
  printf 'AUDIT_HTTP path=%s status=%s\n' "$path" "$code"
done
echo 'AUDIT_DB_SCHEMA=NOT_CHECKED_NO_DB_ACCESS_IN_READONLY_AUDIT'
echo 'AUDIT_FPM_FLAGS=NOT_CHECKED_REQUIRES_PRIVILEGED_VERIFICATION'
echo 'AUDIT_AUTHENTICATED_ACCEPTANCE=NOT_CHECKED'
echo 'AUDIT_NO_CHANGES=TRUE'
