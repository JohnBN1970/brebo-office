#!/bin/sh
# Backup and preflight only. Does not change active Office-test files.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
ACTIVE="$ROOT/public_html/office-test/index.php"
RULES="$ROOT/public_html/office-test/.htaccess"
CANDIDATE=/tmp/office-test-router-candidate.php
EXPECTED_ACTIVE=15ed6a03fecad504f17595f0e29a987dd06268c3709dfd68cfae451c895df3a9
EXPECTED_RULES=6a6a5340df1c84a991b93e872868fe8b7152c1fc3ab7a74f56aba14ae96f910b
EXPECTED_CANDIDATE=d32b4dbc6fea1b307e29448e27586e8848ae86a6bf2dbe373d90534bdecedda5
PHP=/opt/alt/php83/usr/bin/php
for file in "$ACTIVE" "$RULES" "$CANDIDATE"; do
  test -f "$file" || { echo "MISSING: $file"; exit 1; }
done
verify() {
  actual=$(sha256sum "$1" | cut -d ' ' -f 1)
  [ "$actual" = "$2" ] || { echo "HASH_MISMATCH: $1"; exit 1; }
}
verify "$ACTIVE" "$EXPECTED_ACTIVE"
verify "$RULES" "$EXPECTED_RULES"
verify "$CANDIDATE" "$EXPECTED_CANDIDATE"
"$PHP" -l "$ACTIVE" >/dev/null
"$PHP" -l "$CANDIDATE" >/dev/null
echo "ROUTER_HASHES: GROEN"
BACKUPS="$ROOT/office_calc_test/router-swap-backups"
mkdir -p "$BACKUPS"
chmod 700 "$BACKUPS"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
DEST="$BACKUPS/$STAMP"
test ! -e "$DEST"
mkdir -m 700 "$DEST"
cp -p "$ACTIVE" "$DEST/index.php"
cp -p "$RULES" "$DEST/.htaccess"
chmod 600 "$DEST/index.php" "$DEST/.htaccess"
verify "$DEST/index.php" "$EXPECTED_ACTIVE"
verify "$DEST/.htaccess" "$EXPECTED_RULES"
echo "BACKUP_VERIFIED: GROEN"
echo "BACKUP_DIR: $DEST"
for route in 'inzet/dienst/nieuw' 'inzet/medewerker/personeelsnummer'; do
  if grep -Fq "$route" "$RULES"; then
    echo "REWRITE_$route: AANWEZIG"
  else
    echo "REWRITE_$route: NOG TOEVOEGEN"
  fi
done
echo "ACTIVE_ROUTER_UNCHANGED: JA"
echo "SWAP_PERFORMED: NEE"
