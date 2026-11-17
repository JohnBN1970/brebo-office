#!/usr/bin/env bash
# VPS-specific, non-destructive pre-install gate. Does not alter production.
set -euo pipefail
base=/srv/brebo/office-test
prod=/srv/brebo/office
echo '=== VPS OFFICE-TEST ISOLATION GATE ==='
test "$(id -un)" = brebo-deploy || { echo 'WRONG_DEPLOY_IDENTITY'; exit 10; }
for path in "$base" "$prod" /srv/brebo/private; do
  test -d "$path" && test ! -L "$path" || { echo "UNSAFE_DIRECTORY:$path"; exit 11; }
done
test -w "$base" || { echo 'TEST_DIRECTORY_NOT_WRITABLE'; exit 12; }
test ! -w "$prod" || { echo 'BLOCKED: test deploy identity can write production'; exit 13; }
test ! -w /srv/brebo/private || { echo 'BLOCKED: test deploy identity can write private root'; exit 14; }
echo 'TEST_DEPLOY_PRODUCTION_WRITE=denied'
echo 'TEST_DEPLOY_PRIVATE_WRITE=denied'
for name in releases shared checkpoints; do
  path="$base/$name"
  test -d "$path" && test ! -L "$path" && test -w "$path" || { echo "RELEASE_DIRECTORY_NOT_READY:$name"; exit 15; }
done
echo 'VPS_TEST_ISOLATION_GATE_PASS'
