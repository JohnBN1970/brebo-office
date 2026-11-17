#!/usr/bin/env bash
# Stage an immutable Office-test source candidate. No activation or database changes.
set -euo pipefail
umask 027
[[ $(id -un) == brebo-deploy ]] || { echo 'RUN_AS_BREBO_DEPLOY' >&2; exit 10; }
command -v git >/dev/null && command -v php >/dev/null && command -v flock >/dev/null || exit 11
base=/srv/brebo/office-test
[[ -d $base/releases && ! -L $base && ! -L $base/releases ]] || exit 12
[[ $(stat -c %U "$base") == brebo-deploy ]] || exit 13
repo=https://github.com/JohnBN1970/brebo-office.git
exec 9>"$base/.source-stage.lock"
flock -w 60 9 || exit 14
work=$(mktemp -d "$base/releases/.source-stage.XXXXXXXX")
trap 'rm -rf -- "$work"' EXIT
git -c advice.detachedHead=false clone --quiet --depth 1 --branch develop "$repo" "$work/repo"
sha=$(git -C "$work/repo" rev-parse HEAD)
[[ $sha =~ ^[0-9a-f]{40}$ ]] || exit 15
target="$base/releases/$sha"
[[ ! -e $target && ! -L $target ]] || { echo "RELEASE_ALREADY_STAGED=$sha"; exit 0; }
[[ -f "$work/repo/office-independent/public/office-home.php" ]] || { echo 'ENTRYPOINT_MISSING' >&2; exit 16; }
[[ -f "$work/repo/packages/brebo-finance-core/bootstrap.php" ]] || { echo 'FINANCE_PACKAGE_MISSING' >&2; exit 17; }
mkdir -p "$work/candidate"
cp -a "$work/repo/office-independent/." "$work/candidate/"
mkdir -p "$work/candidate/packages"
cp -a "$work/repo/packages/brebo-finance-core" "$work/candidate/packages/"
find "$work/candidate/public" "$work/candidate/src" -type f -name '*.php' -print0 | xargs -0 -r -n 1 -P 2 php -l > "$work/php-lint.log" || { grep -v 'No syntax errors' "$work/php-lint.log" >&2; exit 18; }
printf '%s\n' "$sha" > "$work/candidate/.office-source-sha"
mv "$work/candidate" "$target"
echo "OFFICE_TEST_SOURCE_STAGED=$sha"
echo "RELEASE_PATH=$target"
echo 'NOT_ACTIVATED=YES'
echo 'DATABASE_NOT_CHANGED=YES'
echo 'NGINX_NOT_CHANGED=YES'
