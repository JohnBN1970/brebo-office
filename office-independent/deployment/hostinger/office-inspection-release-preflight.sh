#!/usr/bin/env bash
set -euo pipefail

# READ-ONLY Hostinger inspection acceptance. Does not print credentials.
base="$HOME/domains/brebobv.nl/office_calc_test"
expected="$base/release-office-test-contractform-20261030/private"
printf '%s\n' '=== OFFICE-TEST RELEASE CONTROL ==='
if [ ! -d "$base" ]; then
  echo 'FAIL: office-test base directory missing'
  exit 2
fi
if [ -d "$expected" ]; then
  echo 'EXPECTED RELEASE DIRECTORY: PRESENT'
else
  echo 'EXPECTED RELEASE DIRECTORY: MISSING'
fi
for path in "$base"/release-*/private; do
  [ -d "$path" ] || continue
  printf 'RELEASE: %s\n' "${path#"$base"/}"
done
printf '%s\n' '=== ROUTER CONTROL ==='
router="$HOME/domains/brebobv.nl/public_html/office-test/index.php"
if [ -f "$router" ]; then
  echo 'ROOT ROUTER: PRESENT'
  echo 'Router exists (contents intentionally not printed)'
else
  echo 'ROOT ROUTER: NOT FOUND'
fi
printf '%s\n' '=== ACTIVE RELEASE CONTROL ==='
config="$base/config/config.php"
if [ ! -f "$config" ]; then
  echo 'FAIL: Office-test config not found'
  exit 2
fi
active="$(php -r '$c=require $argv[1]; if (!is_array($c) || !isset($c["OFFICE_PRIVATE_ROOT"]) || !is_string($c["OFFICE_PRIVATE_ROOT"])) exit(1); echo $c["OFFICE_PRIVATE_ROOT"];' "$config")"
case "$active" in
  "$base"/release-*/private)
    if [ ! -d "$active" ]; then echo 'FAIL: active release missing'; exit 2; fi
    expected="$active"
    echo "ACTIVE RELEASE: ${active#"$base"/}"
    ;;
  *) echo 'FAIL: active root outside Office-test'; exit 2 ;;
esac
printf '%s\n' '=== INSPECTION FILE CONTROL ==='
if [ -d "$expected" ]; then
  for file in public/office-inspection.php public/office-inspection-create.php public/office-inspection-overview.php public/office-inspection-finding-save.php; do
    if [ -f "$expected/$file" ]; then
      echo "PRESENT: $file"
      php -l "$expected/$file"
    else
      echo "MISSING: $file"
    fi
  done
fi
printf '%s\n' '=== READ-ONLY CONTROL COMPLETE ==='
