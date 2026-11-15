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
python3 "$helper/prepare_office_test_release.py" --active "$active" --candidate "$candidate" --incoming "$work/office-independent" --baseline "$baseline"
# Refresh the established renderer rather than replacing its runtime adapters.
python3 "$candidate/deployment/hostinger/mount-project-detail-v3.py" "$candidate/public/office-project.php"
find "$candidate/src" "$candidate/public" -type f -name '*.php' -print0 | xargs -0 -r -n 1 -P 4 php -l > "$work/php-lint.log" || { grep -v 'No syntax errors' "$work/php-lint.log"; exit 14; }
checkpoint="$base/development-checkpoints/$release_sha"
mkdir -p "$checkpoint"
chmod 700 "$checkpoint"
cp -p "$config" "$checkpoint/config.php"
chmod 600 "$checkpoint/config.php"
# Only additive reviewed migrations. On failure keep old release; never undo schema via DROP.
php "$helper/office_test_migrate.php" OFFICE-TEST "$candidate"
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
