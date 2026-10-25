#!/bin/sh
# Verify Apache .htaccess deny rules on an isolated, temporary, non-sensitive URL.
# Does not publish candidate router or read secrets. Cleans up on exit.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl/public_html
NAME=office-access-probe-20261025
DIR="$ROOT/$NAME"
if [ -e "$DIR" ]; then echo "PROBE_DIR_ALREADY_EXISTS: ROOD"; exit 1; fi
umask 077
mkdir "$DIR"
cleanup() { rm -f "$DIR/.htaccess" "$DIR/index.txt"; rmdir "$DIR" 2>/dev/null || true; }
trap cleanup EXIT HUP INT TERM
printf 'Require all denied\n' > "$DIR/.htaccess"
printf 'BREBO_ACCESS_PROBE\n' > "$DIR/index.txt"
STATUS=$(curl -sS --max-time 12 -o /dev/null -w '%{http_code}' "https://brebobv.nl/$NAME/index.txt") || STATUS=000
if [ "$STATUS" = 403 ]; then
  echo "APACHE_HTACCESS_DENY: GROEN (HTTP 403)"
  echo "PROBE_CLEANUP: AUTOMATISCH"
else
  echo "APACHE_HTACCESS_DENY: ROOD (HTTP $STATUS; verwacht 403)"
  echo "PROBE_CLEANUP: AUTOMATISCH"
  exit 1
fi
