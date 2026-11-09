#!/usr/bin/env bash
# Build a private candidate from the current test files. Never change the active tree/config/ACL.
set -euo pipefail
umask 077
stage="$(pwd -P)"
base="$HOME/domains/brebobv.nl/office_calc_test"
config="$base/config/config.php"
test -f "$config" && test ! -L "$config"
active="$(php -d display_errors=0 -r 'ob_start(); $c=require $argv[1]; ob_end_clean(); if (($c["OFFICE_BASE_PATH"]??null)!=="/office-test") exit(1); echo $c["OFFICE_PRIVATE_ROOT"]??"";' "$config")"
case "$active" in "$base"/release-*/private) ;; *) echo BANK_PLAN_INVALID_TEST_SCOPE; exit 1;; esac
test -d "$active" && test ! -L "$active" && test "$(realpath "$active")" = "$active"
test "$stage" != "$active"
files=(public/office-finance.php src/Finance/OfficeFinanceAccess.php src/Finance/OfficeFinanceBankPanel.php src/Finance/OfficeFinanceView.php src/Finance/OfficeMoneybirdBankSnapshot.php src/Finance/OfficeMoneybirdBankApiClient.php src/Intake/OfficeMount.php src/Finance/bootstrap.php)
candidate="$stage/activation-preview"
test ! -e "$candidate" && test ! -L "$candidate"
mkdir -m 700 "$candidate"
sha256sum "$config" > "$candidate/active-before.sha256"
ready=1
for path in "${files[@]}"; do
  source="$active/$path"
  if ! test -f "$source" || test -L "$source" || test "$(realpath "$source")" != "$source"; then
    ready=0
    continue
  fi
  sha256sum "$source" >> "$candidate/active-before.sha256"
  mkdir -p "$candidate/${path%/*}"
  cp "$source" "$candidate/$path"
done
echo "BANK_PLAN_TARGET_COMPONENTS_PRESENT=$ready"
if [ "$ready" = 1 ]; then
  if ! command -v patch >/dev/null; then
    ready=0
    echo BANK_PLAN_PATCH_TOOL_AVAILABLE=0
  else
    for path in "${files[@]:0:5}"; do
      change="$candidate/change.patch"
      awk -v target="--- a/$path" '/^--- a\// { keep=($0==target) } keep { print }' "$stage/deployment/hostinger/office-moneybird-bank-office.patch" > "$change"
      test -s "$change"
      if cmp -s "$candidate/$path" "$stage/$path"; then
        echo "BANK_PLAN_ALREADY_CURRENT=$path"
      elif awk -v target="$path" '$2==target { print $1 }' "$stage/deployment/hostinger/office-moneybird-bank-previous.sha256" | { read -r expected_hash && test "$(sha256sum "$candidate/$path" | cut -d' ' -f1)" = "$expected_hash"; }; then
        cp "$stage/$path" "$candidate/$path"
        echo "BANK_PLAN_REVIEWED_BALANCE_UPGRADE=$path"
      elif (cd "$candidate" && patch --batch --forward --fuzz=0 --dry-run -p1 < "$change") >/dev/null 2>&1; then
        (cd "$candidate" && patch --batch --forward --fuzz=0 -p1 < "$change") >/dev/null 2>&1
        echo "BANK_PLAN_PATCH_PREPARED=$path"
      elif (cd "$candidate" && patch --batch --reverse --fuzz=0 --dry-run -p1 < "$change") >/dev/null 2>&1; then
        echo "BANK_PLAN_PATCH_ALREADY_PRESENT=$path"
      elif php -d display_errors=0 "$stage/deployment/hostinger/office-moneybird-bank-preview-hooks.php" "$path"; then
        echo "BANK_PLAN_EXACT_HOOK_PREPARED=$path"
      else
        ready=0
        echo "BANK_PLAN_PATCH_CONFLICT=$path"
      fi
    done
    echo "BANK_PLAN_CURRENT_RELEASE_PATCH_COMPATIBLE=$ready"
  fi
fi
if [ "$ready" = 1 ]; then
  # Only the private preview is linted/tested. Keep current mount and unrelated hotfixes.
  mkdir -p "$candidate/tests"
  cp "$stage"/tests/Office*Bank*Test.php "$candidate/tests/"
  for path in "${files[@]}"; do php -l "$candidate/$path" >/dev/null; done
  for name in OfficeFinanceBankPanelTest OfficeFinanceBankViewTest OfficeMoneybirdBankApiClientTest OfficeMoneybirdBankSnapshotTest; do
    php "$candidate/tests/$name.php" >/dev/null
  done
  if php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'; then php "$candidate/tests/OfficeFinanceBankAccessTest.php" >/dev/null; else ready=0; fi
  echo "BANK_PLAN_TARGET_PREVIEW_TESTS_OK=$ready"
fi
sha256sum -c "$candidate/active-before.sha256" >/dev/null
echo BANK_PLAN_ACTIVE_FILES_AND_CONFIG_UNCHANGED=1
if [ "$ready" = 1 ]; then cp "$stage/SOURCE_SHA" "$candidate/PLAN_READY"; fi
echo "BANK_PLAN_PRIVATE_PREVIEW_READY=$ready"
echo BANK_PLAN_ACTIVATION_EXECUTED=0
