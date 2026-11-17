#!/usr/bin/env bash
# Non-destructive VPS Office-test release foundation.
# Runs as the unprivileged brebo-deploy user. Never touches production.
set -eEuo pipefail
umask 027
base=/srv/brebo/office-test
test "$(id -un)" = brebo-deploy || { echo 'DEPLOY_USER_INVALID'; exit 10; }
test -d "$base" && test -w "$base" && test ! -L "$base" || { echo 'TEST_BASE_UNSAFE'; exit 11; }
for name in releases shared checkpoints; do
  target="$base/$name"
  if [[ -L "$target" ]]; then echo "SYMLINK_NOT_ALLOWED:$name"; exit 12; fi
  if [[ -e "$target" && ! -d "$target" ]]; then echo "NOT_DIRECTORY:$name"; exit 13; fi
done
exec 9>"$base/.release-foundation.lock"
flock -w 30 9 || { echo 'FOUNDATION_LOCKED'; exit 14; }
for name in releases shared checkpoints; do
  install -d -m 2750 "$base/$name"
done
# Secrets and application config are deliberately NOT created here.
printf 'VPS_TEST_RELEASE_FOUNDATION_READY\n'
for name in releases shared checkpoints; do
  stat -c '%n owner=%U group=%G mode=%a' "$base/$name"
done
