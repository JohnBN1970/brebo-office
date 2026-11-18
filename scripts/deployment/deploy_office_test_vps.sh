#!/usr/bin/env bash
# Deploy an immutable Office-test VPS release as brebo-deploy; no root required.
set -euo pipefail
SHA="${1:?Expected 40-character commit SHA}"
[[ "$SHA" =~ ^[a-f0-9]{40}$ ]] || exit 2
BASE=/srv/brebo/office-test
REPO="$BASE/shared/source-cache/repo"
RELEASE="$BASE/releases/$SHA"
cd /tmp
test "$(id -un)" = brebo-deploy
test -d "$REPO/.git"
git -C "$REPO" fetch --no-tags origin develop
git -C "$REPO" cat-file -e "$SHA^{commit}"
test "$(git -C "$REPO" rev-parse origin/develop)" = "$SHA"
if [ ! -d "$RELEASE" ]; then
  STAGING="$(mktemp -d "$BASE/releases/.staging-XXXXXXXX")"
  trap 'rm -rf -- "$STAGING"' EXIT
  git -C "$REPO" archive "$SHA:office-independent" | tar -x -C "$STAGING"
  mkdir -p "$STAGING/packages/brebo-finance-core"
  git -C "$REPO" archive "$SHA:packages/brebo-finance-core" | tar -x -C "$STAGING/packages/brebo-finance-core"
  test -f "$STAGING/packages/brebo-finance-core/bootstrap.php"
  test -f "$STAGING/public/index.php"
  test -f "$STAGING/public/office-login.php"
  php -l "$STAGING/public/index.php"
  php -l "$STAGING/public/office-login.php"
  printf '%s\n' "$SHA" > "$STAGING/.office-source-sha"
  chmod 755 "$STAGING" "$STAGING/public"
  find "$STAGING" -type d -exec chmod 755 {} +
  mv "$STAGING" "$RELEASE"
  STAGING=''
fi
test "$(cat "$RELEASE/.office-source-sha")" = "$SHA"
test -r "$RELEASE/public/index.php"
PREVIOUS="$(readlink -f "$BASE/current")"
case "$PREVIOUS" in "$BASE"/releases/*) ;; *) echo 'Unsafe active release'; exit 1;; esac
test -d "$PREVIOUS"
test ! -e "$BASE/current.next"
ln -s "$RELEASE" "$BASE/current.next"
mv -Tf "$BASE/current.next" "$BASE/current"
rollback() {
  echo 'Release health check failed; rolling back' >&2
  ln -s "$PREVIOUS" "$BASE/current.rollback"
  mv -Tf "$BASE/current.rollback" "$BASE/current"
}
trap rollback ERR
# 503 is permitted while the fail-closed router is intentionally disabled.
status="$(curl -ksS --max-time 12 --resolve office-test.brebobv.nl:443:127.0.0.1 -o /dev/null -w '%{http_code}' https://office-test.brebobv.nl/)"
case "$status" in 200|302|303|503) ;; *) echo "Unexpected HTTP $status"; false;; esac
test "$(readlink -f "$BASE/current")" = "$RELEASE"
trap - ERR
printf 'VPS_RELEASE_DEPLOYED=%s HTTP=%s\n' "$SHA" "$status"
