#!/usr/bin/env bash
# Read-only diagnostics for isolated VPS; never print credentials or enable router.
set -euo pipefail
BASE=/srv/brebo/office-test
test "$(id -un)" = brebo-deploy
release="$(readlink -f "$BASE/current")"
case "$release" in "$BASE"/releases/*) ;; *) echo "::error::VPS_CURRENT_RELEASE_INVALID"; exit 1;; esac
for file in public/index.php public/office-login.php public/office-personal-dashboard.php .office-source-sha; do
  if test -f "$release/$file"; then echo "VPS_FILE_PRESENT=$file"; else echo "::error::VPS_FILE_MISSING=$file"; exit 1; fi
done
# Confirm the direct CRM handoff release payload exists before any privileged
# schema/feature-flag activation. This check is strictly read-only.
for file in \
  src/Crm/OfficeCrmDirectProjectAcceptance.php \
  src/Crm/OfficeCrmDirectProjectTransfer.php \
  src/Crm/OfficeCrmNativeProjectAdapter.php \
  src/Crm/OfficeCrmProjectHandoff.php \
  public/office-crm-opportunity-transition.php \
  deployment/vps/crm-direct-project-preflight.php; do
  if test ! -f "$release/$file"; then
    echo "::error::VPS_CRM_DIRECT_RELEASE_FILE_MISSING=$file"
    exit 1
  fi
  php -l "$release/$file" > /dev/null || {
    echo "::error::VPS_CRM_DIRECT_PHP_SYNTAX_INVALID=$file"
    exit 1
  }
done
echo "VPS_CRM_DIRECT_RELEASE_FILES=PASS"
# Verify the Startdossier execution gate is present in the exact active release.
# This is a source preflight, not proof of database migration or two-user acceptance.
for file in \
  src/Project/OfficeProjectKickstartPolicy.php \
  src/Project/OfficeProjectKickstartRepository.php \
  src/Project/OfficeProjectKickstartQuestionsRepository.php \
  src/Project/OfficeProjectKickstartActionHandler.php \
  src/Project/OfficeProjectExecutionStartGate.php; do
  if test ! -f "$release/$file"; then
    echo "::error::VPS_STARTDOSSIER_RELEASE_FILE_MISSING=$file"
    exit 1
  fi
  php -l "$release/$file" > /dev/null || {
    echo "::error::VPS_STARTDOSSIER_PHP_SYNTAX_INVALID=$file"
    exit 1
  }
done
echo "VPS_STARTDOSSIER_RELEASE_FILES=PASS"
echo "VPS_STARTDOSSIER_SCHEMA_AND_TWO_USER_ACCEPTANCE=NOT_VERIFIED"
# Inzet weekcalendar deployment proof: check the exact active release, not
# the source tree in GitHub. PHP-FPM flags and real user data remain unverified.
for file in \
  src/Inzet/OfficeInzetPlanningWorkbench.php \
  src/Inzet/OfficeInzetApprovedWeekReadRepository.php; do
  if test ! -f "$release/$file"; then
    echo "::error::VPS_INZET_WEEKCALENDAR_FILE_MISSING=$file"
    exit 1
  fi
  php -l "$release/$file" > /dev/null || {
    echo "::error::VPS_INZET_WEEKCALENDAR_PHP_SYNTAX_INVALID=$file"
    exit 1
  }
done
if ! grep -Fq "'green'=>" "$release/src/Inzet/OfficeInzetPlanningWorkbench.php" ||
   ! grep -Fq 'inzet-status-' "$release/src/Inzet/OfficeInzetPlanningWorkbench.php"; then
  echo "::error::VPS_INZET_TRAFFIC_LIGHT_SOURCE_MISSING"
  exit 1
fi
if ! grep -Fq 'OFFICE_TEST_INZET_ONSITE_WEEK_ACTUALS_ENABLED' "$release/public/office-projects.php"; then
  echo "::error::VPS_INZET_ONSITE_FLAG_WIRING_MISSING"
  exit 1
fi
echo "VPS_INZET_WEEKCALENDAR_RELEASE=PASS"
echo "VPS_INZET_ONSITE_FPM_FLAGS=NOT_VERIFIED"
echo "VPS_INZET_ONSITE_FUNCTIONAL_ACCEPTANCE=NOT_VERIFIED"


echo "VPS_CRM_DIRECT_SCHEMA_AND_FLAGS=PRIVILEGED_PREFLIGHT_REQUIRED"
active_sha="$(cat "$release/.office-source-sha")"
echo "VPS_ACTIVE_RELEASE_SHA=$active_sha"
if [ "${1:-}" != "" ]; then
  expected_sha="$1"
  [[ "$expected_sha" =~ ^[a-f0-9]{40}$ ]] || { echo "::error::VPS_EXPECTED_SHA_INVALID"; exit 1; }
  if [ "$active_sha" != "$expected_sha" ]; then
    echo "::error::VPS_ACTIVE_RELEASE_SHA_MISMATCH expected=$expected_sha actual=$active_sha"
    exit 1
  fi
  echo "VPS_ACTIVE_RELEASE_SHA_MATCH=PASS"
fi
for var in OFFICE_VPS_TEST_ROUTER_ENABLED OFFICE_NATIVE_PROJECTS_ENABLED OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED; do
  # The deploy SSH session is not the PHP-FPM worker; report only that the gate
  # is controlled by the pool, not a misleading SSH environment value.
  echo "VPS_PHP_FPM_GATE_REQUIRES_PRIVILEGED_VERIFICATION=$var"
done
for path in / /office-test/ /office-test/inloggen /office-test/mijn-dashboard; do
  status="$(curl -ksS --max-time 12 --resolve office-test.brebobv.nl:443:127.0.0.1 -o /dev/null -w '%{http_code}' "https://office-test.brebobv.nl$path")"
  echo "VPS_HTTP path=$path status=$status"
done
echo "VPS_ROUTER_NOT_ACTIVATED_BY_DIAGNOSTICS"
