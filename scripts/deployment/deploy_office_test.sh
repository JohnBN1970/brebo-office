#!/usr/bin/env bash
# Called only by the central develop workflow. No production paths are mutated.
set -eEuo pipefail
package="$1"
release_sha="$2"
[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]] || exit 10
base="$HOME/domains/brebobv.nl/office_calc_test"
[[ -d "$base" && ! -L "$base" ]] || exit 11
exec 9>"$base/.development-deploy.lock"
flock -w 120 9 || { echo OFFICE_TEST_DEPLOY_LOCKED; exit 12; }
work="$(mktemp -d "$base/.development-work.XXXXXXXX")"
trap 'rm -rf -- "$work"' EXIT
tar -xzf "$package" -C "$work"
helper="$work/scripts/deployment"
config="$base/config/config.php"
active="$(php -r 'require $argv[1]; echo officeTestEnvironment()[3];' "$helper/office_test_environment.php")"
candidate="$base/release-ci-$release_sha/private"
# Failed candidates are retained privately for diagnosis; never overwrite a release.
if [[ -e "$candidate" ]]; then
  if [[ "$active" == "$candidate" && -f "$candidate/.office-deployed-sha" && "$(cat "$candidate/.office-deployed-sha")" == "$release_sha" ]]; then
    php "$helper/office_test_acceptance.php" OFFICE-TEST "$candidate"
    echo "OFFICE_TEST_DEPLOY_ACCEPTED=$release_sha"
    exit 0
  fi
  [[ "$active" != "$candidate" ]] || { echo ACTIVE_UNACCEPTED_CANDIDATE; exit 13; }
  mv "$candidate" "$candidate.failed-$(date +%s)"
fi
mkdir -p "$(dirname "$candidate")"
baseline="$active/.development-source"
[[ -d "$baseline" ]] || baseline="$work/baseline/office-independent"
command -v git >/dev/null
command -v python3 >/dev/null
python3 "$helper/prepare_office_test_release.py" --active "$active" --candidate "$candidate" --incoming "$work/office-independent" --baseline "$baseline" --known-ancestor-blobs "$work/metadata/onsite-ancestor-blobs.txt" --known-calc-blobs "$work/metadata/calc-ancestor-blobs.txt" --known-calc-path-blobs "$work/metadata/calc-path-ancestor-blobs.txt" --office-test-authoritative
# Pin the shared Finance dependency inside this private candidate, never a retired release.
[[ -f "$work/packages/brebo-finance-core/bootstrap.php" && -d "$work/packages/brebo-finance-core/src" ]] || { echo FINANCE_CORE_PACKAGE_MISSING; exit 15; }
[[ ! -L "$candidate/packages" && ! -L "$candidate/packages/brebo-finance-core" ]] || { echo FINANCE_CORE_PACKAGE_UNSAFE; exit 15; }
mkdir -p "$candidate/packages"
cp -a "$work/packages/brebo-finance-core" "$candidate/packages/"
# Refresh the established renderer rather than replacing its runtime adapters.
python3 "$candidate/deployment/hostinger/mount-project-detail-v3.py" "$candidate/public/office-project.php"
# Hostinger's shared SSH environment must not spawn four PHP CLI workers
# concurrently; CI has already linted the repository's canonical sources.
echo "OFFICE_TEST_SERVER_PHP_LINT_START"
if ! find "$candidate/src" "$candidate/public" -type f -name '*.php' -print0 | xargs -0 -r -n 1 -P 1 php -l > "$work/php-lint.log"; then
  echo "OFFICE_TEST_SERVER_PHP_LINT_FAILED"
  grep -v 'No syntax errors' "$work/php-lint.log" || true
  exit 14
fi
echo "OFFICE_TEST_SERVER_PHP_LINT_PASS"
checkpoint="$base/development-checkpoints/$release_sha"
mkdir -p "$checkpoint"
chmod 700 "$checkpoint"
cp -p "$config" "$checkpoint/config.php"
chmod 600 "$checkpoint/config.php"
# Only additive reviewed migrations. On failure keep old release; never undo schema via DROP.
# Hostinger CLI may suppress fatal errors by default, returning only status 255.
# Surface the PHP error and phase boundary without printing config or credentials.
echo OFFICE_TEST_MIGRATION_START
if php -d display_errors=stderr -d log_errors=1 -d error_reporting=E_ALL "$helper/office_test_migrate.php" OFFICE-TEST "$candidate"; then
  echo OFFICE_TEST_MIGRATION_PASS
else
  status=$?
  echo "OFFICE_TEST_MIGRATION_FAILED_EXIT=$status"
  exit "$status"
fi
activated=0
rollback() {
  code=$?
  if [[ "$activated" == 1 ]]; then
    cp -p "$checkpoint/config.php" "$config.rollback"
    mv -f "$config.rollback" "$config"
    echo OFFICE_TEST_PREVIOUS_RELEASE_RESTORED
  fi
  rm -rf -- "$work"
  exit "$code"
}
trap rollback ERR HUP INT TERM
activated=1
php "$helper/office_test_activate.php" "$candidate" OFFICE-TEST
php "$helper/office_test_acceptance.php" OFFICE-TEST "$candidate"
printf '%s\n' "$release_sha" > "$candidate/.office-deployed-sha"
activated=0
trap - ERR INT TERM
echo "OFFICE_TEST_DEPLOY_ACCEPTED=$release_sha"
