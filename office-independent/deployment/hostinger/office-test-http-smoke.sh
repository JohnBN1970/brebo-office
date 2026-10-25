#!/bin/sh
# Read-only unauthenticated smoke test of the ACTIVE Office-test router.
# No POST, no cookies, no credentials, no database writes.
set -eu
BASE="${1:-https://brebobv.nl/office-test}"
case "$BASE" in
  https://*/office-test) ;;
  *) echo "BASE_URL: ONGELDIG (verwacht https://host/office-test)" >&2; exit 2 ;;
esac
failed=0
check() {
  path="$1"
  expected="$2"
  status=$(curl --silent --show-error --max-time 15 --output /dev/null --write-out '%{http_code}' "$BASE$path") || {
    echo "HTTP_FOUT: $path"
    failed=1
    return
  }
  echo "$path: HTTP $status (verwacht $expected)"
  if [ "$status" != "$expected" ]; then failed=1; fi
}
# Routes not yet registered in active router should remain inaccessible.
check /inzet/dienst/nieuw 404
check /inzet/medewerker/personeelsnummer 404
if [ "$failed" -eq 0 ]; then
  echo "ACTIVE_ROUTER_SMOKE: GROEN"
else
  echo "ACTIVE_ROUTER_SMOKE: ROOD"
fi
exit "$failed"
