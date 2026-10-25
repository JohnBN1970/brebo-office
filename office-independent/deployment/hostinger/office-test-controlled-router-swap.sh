#!/bin/sh
# Office-test controlled router swap. Default is DRY RUN; APPLY requires explicit argument.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
DIR="$ROOT/public_html/office-test"
ACTIVE="$DIR/index.php"
RULES="$DIR/.htaccess"
CANDIDATE=/tmp/office-test-router-candidate.php
NEW_RULES=/tmp/office-test-htaccess-candidate-20261025
BACKUP="$ROOT/office_calc_test/router-swap-backups/20261025T191247Z"
EXPECTED_ACTIVE=15ed6a03fecad504f17595f0e29a987dd06268c3709dfd68cfae451c895df3a9
EXPECTED_RULES=6a6a5340df1c84a991b93e872868fe8b7152c1fc3ab7a74f56aba14ae96f910b
EXPECTED_CANDIDATE=d32b4dbc6fea1b307e29448e27586e8848ae86a6bf2dbe373d90534bdecedda5
EXPECTED_NEW_RULES=f285d896b4002a5cd4a7729b7e2d6ea2320bf8a00ac596d80366824e189db02c
PHP=/opt/alt/php83/usr/bin/php
MODE=${1:-CHECK}
case "$MODE" in CHECK|APPLY) ;; *) echo "USAGE: sh $0 [CHECK|APPLY]"; exit 2;; esac
hash() { sha256sum "$1" | cut -d ' ' -f 1; }
checkhash() { test -f "$1" && test "$(hash "$1")" = "$2" || { echo "HASH_ERROR: $1"; exit 1; }; }
checkhash "$ACTIVE" "$EXPECTED_ACTIVE"
checkhash "$RULES" "$EXPECTED_RULES"
checkhash "$CANDIDATE" "$EXPECTED_CANDIDATE"
checkhash "$NEW_RULES" "$EXPECTED_NEW_RULES"
checkhash "$BACKUP/index.php" "$EXPECTED_ACTIVE"
checkhash "$BACKUP/.htaccess" "$EXPECTED_RULES"
"$PHP" -l "$CANDIDATE" >/dev/null
echo "HASH_AND_SYNTAX: GROEN"
# Ensure a valid login page and unknown-route 404 before the change.
http() { curl -sS --max-time 15 -o /dev/null -w '%{http_code}' "https://brebobv.nl$1" || true; }
test "$(http /office-test/inloggen)" = 200 || { echo "LOGIN_BASELINE: ROOD"; exit 1; }
test "$(http /office-test/__router_preflight_missing_20261024)" = 404 || { echo "UNKNOWN_BASELINE: ROOD"; exit 1; }
echo "HTTP_BASELINE: GROEN"
if [ "$MODE" = CHECK ]; then
  echo "DRY_RUN: GROEN"
  echo "SWAP_PERFORMED: NEE"
  exit 0
fi
# Atomic rename per file; rollback both on any failure, including interrupted deployment.
TMP_ROUTER="$DIR/.router-stage-$$"
TMP_RULES="$DIR/.rules-stage-$$"
restore() {
  cp -p "$BACKUP/index.php" "$TMP_ROUTER"
  cp -p "$BACKUP/.htaccess" "$TMP_RULES"
  mv -f "$TMP_ROUTER" "$ACTIVE"
  mv -f "$TMP_RULES" "$RULES"
  echo "ROLLBACK_ROUTER: $(hash "$ACTIVE")"
  echo "ROLLBACK_RULES: $(hash "$RULES")"
}
SWAPPED=0
finish() {
  code=$?
  trap - EXIT HUP INT TERM
  if [ "$SWAPPED" -eq 1 ]; then
    echo "DEPLOY_FAILED: rollback gestart"
    restore || echo "ROLLBACK_ERROR: handmatige interventie vereist"
  fi
  rm -f "$TMP_ROUTER" "$TMP_RULES"
  exit "$code"
}
trap finish EXIT
trap 'exit 130' HUP INT TERM
cp -p "$CANDIDATE" "$TMP_ROUTER"
cp -p "$NEW_RULES" "$TMP_RULES"
chmod 644 "$TMP_ROUTER" "$TMP_RULES"
SWAPPED=1
mv -f "$TMP_ROUTER" "$ACTIVE"
mv -f "$TMP_RULES" "$RULES"
checkhash "$ACTIVE" "$EXPECTED_CANDIDATE"
checkhash "$RULES" "$EXPECTED_NEW_RULES"
"$PHP" -l "$ACTIVE" >/dev/null
test "$(http /office-test/inloggen)" = 200 || { echo "LOGIN_AFTER: ROOD"; exit 1; }
test "$(http /office-test/__router_preflight_missing_20261024)" = 404 || { echo "UNKNOWN_AFTER: ROOD"; exit 1; }
for route in /office-test/inzet/dienst/nieuw /office-test/inzet/medewerker/personeelsnummer; do
  test "$(http "$route")" = 405 || { echo "NEW_ROUTE_AFTER: ROOD $route"; exit 1; }
done
# Preserve protected OnSite API behavior; no mutation or credentials.
test "$(http /office-test/api/onsite/v1/activate)" = 405 || { echo "ONSITE_ACTIVATE_AFTER: ROOD"; exit 1; }
test "$(http /office-test/api/onsite/v1/bootstrap)" = 401 || { echo "ONSITE_BOOTSTRAP_AFTER: ROOD"; exit 1; }
test "$(http /office-test/api/onsite/v1/presence)" = 405 || { echo "ONSITE_PRESENCE_AFTER: ROOD"; exit 1; }
SWAPPED=0
echo "DEPLOY_AND_HTTP_SMOKE: GROEN"
echo "ROLLBACK_BACKUP: $BACKUP"
