#!/bin/sh
# Build and inspect a candidate .htaccess privately. Never modify live files.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
LIVE="$ROOT/public_html/office-test/.htaccess"
EXPECTED=6a6a5340df1c84a991b93e872868fe8b7152c1fc3ab7a74f56aba14ae96f910b
BACKUP="$ROOT/office_calc_test/router-swap-backups/20261025T191247Z/.htaccess"
OUT=/tmp/office-test-htaccess-candidate-20261025
test -f "$LIVE" && test -f "$BACKUP" || { echo "FILES: ROOD"; exit 1; }
hash() { sha256sum "$1" | cut -d ' ' -f 1; }
[ "$(hash "$LIVE")" = "$EXPECTED" ] && [ "$(hash "$BACKUP")" = "$EXPECTED" ] || { echo "HASH: ROOD"; exit 1; }
test ! -e "$OUT" || { echo "CANDIDATE_EXISTS: ROOD (niet overschreven)"; exit 1; }
# Add exact paths only, immediately after RewriteEngine On, preserving all existing rules.
# Do not use generic fallback rules which could affect API or CRM.
awk '
  { print }
  /^[[:space:]]*RewriteEngine[[:space:]]+On[[:space:]]*$/ && !added {
    print "RewriteRule ^inzet/dienst/nieuw/?$ index.php [L,QSA]"
    print "RewriteRule ^inzet/medewerker/personeelsnummer/?$ index.php [L,QSA]"
    added=1
  }
  END { if (!added) exit 12 }
' "$LIVE" > "$OUT" || { rm -f "$OUT"; echo "REWRITE_ENGINE: ROOD"; exit 1; }
chmod 600 "$OUT"
for path in 'inzet/dienst/nieuw' 'inzet/medewerker/personeelsnummer'; do
  n=$(grep -Fc "RewriteRule ^$path/?$ index.php [L,QSA]" "$OUT" || true)
  [ "$n" = 1 ] || { echo "RULE_$path: ROOD"; exit 1; }
done
# Only two added lines: no existing rule may be deleted or modified.
awk '$0 != "RewriteRule ^inzet/dienst/nieuw/?$ index.php [L,QSA]" && $0 != "RewriteRule ^inzet/medewerker/personeelsnummer/?$ index.php [L,QSA]" { print }' "$OUT" > "$OUT.verify"
if ! cmp -s "$LIVE" "$OUT.verify"; then
  rm -f "$OUT.verify"
  echo "PRESERVATION: ROOD"
  exit 1
fi
rm -f "$OUT.verify"
echo "LIVE_HASH: GROEN"
echo "BACKUP_HASH: GROEN"
echo "PRESERVATION: GROEN"
echo "NEW_RULES: 2"
echo "CANDIDATE_PATH: $OUT"
echo "CANDIDATE_SHA256:"
sha256sum "$OUT"
echo "LIVE_HTACCESS_UNCHANGED: JA"
