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
