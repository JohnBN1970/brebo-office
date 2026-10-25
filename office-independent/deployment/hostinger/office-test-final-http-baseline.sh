#!/bin/sh
# Read-only baseline for active Office-test before any router swap.
# No POST, cookies, credentials, or changes to the live router.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
ACTIVE="$ROOT/public_html/office-test/index.php"
HTACCESS="$ROOT/public_html/office-test/.htaccess"
PHP=/opt/alt/php83/usr/bin/php
[ -r "$ACTIVE" ] && [ -r "$HTACCESS" ] || { echo "ACTIVE_FILES: ROOD"; exit 1; }
"$PHP" -l "$ACTIVE" >/dev/null || exit 1
echo "ACTIVE_FILES: GROEN"
echo "ACTIVE_ROUTER_SHA256:"
sha256sum "$ACTIVE"
echo "ACTIVE_HTACCESS_SHA256:"
sha256sum "$HTACCESS"
FAILED=0
# Existing login is publicly reachable; never follow redirects or supply session cookies.
for item in 'LOGIN|/office-test/inloggen|200' 'UNKNOWN|/office-test/__router_preflight_missing_20261024|404'; do
  label=${item%%|*}; rest=${item#*|}; route=${rest%%|*}; expected=${rest##*|}
  status=$(curl -sS --max-time 12 -o /dev/null -w '%{http_code}' "https://brebobv.nl$route") || status=000
  if [ "$status" = "$expected" ]; then
    echo "$label: GROEN (HTTP $status)"
  else
    echo "$label: ROOD (HTTP $status; verwacht $expected)"
    FAILED=1
  fi
done
# New routes must be handled by Apache before the PHP router can dispatch them.
for route in 'inzet/dienst/nieuw' 'inzet/medewerker/personeelsnummer'; do
  if grep -Fq "$route" "$HTACCESS"; then
    echo "REWRITE_$route: AANWEZIG"
  else
    echo "REWRITE_$route: ONTBREEKT (voorbereiden bij omschakeling)"
  fi
done
if [ "$FAILED" -eq 0 ]; then echo "ACTIVE_HTTP_BASELINE: GROEN"; else echo "ACTIVE_HTTP_BASELINE: ROOD"; fi
exit "$FAILED"
