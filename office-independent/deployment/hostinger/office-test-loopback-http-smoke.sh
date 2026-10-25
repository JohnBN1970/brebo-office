#!/bin/sh
# Localhost-only HTTP transport smoke test; no public routes, no POST or DB writes.
set -eu
PHP=${PHP:-/opt/alt/php83/usr/bin/php}
CANDIDATE=${1:-/tmp/office-test-router-candidate.php}
[ -r "$CANDIDATE" ] || { echo "CANDIDATE: ROOD"; exit 1; }
command -v curl >/dev/null || { echo "CURL: ROOD"; exit 1; }
TMP=$(mktemp -d)
PID=''
cleanup() {
  [ -z "$PID" ] || kill "$PID" 2>/dev/null || true
  rm -rf "$TMP"
}
trap cleanup EXIT HUP INT TERM
# No config secrets copied. Candidate remains outside the webroot.
cat > "$TMP/index.php" <<'PHPFILE'
<?php
declare(strict_types=1);
$_SERVER['HTTPS'] = 'on'; // Simulate reverse-proxy HTTPS at loopback only.
require getenv('BREBO_CANDIDATE_ROUTER');
PHPFILE
PORT=18763
BREBO_CANDIDATE_ROUTER="$CANDIDATE" "$PHP" -S "127.0.0.1:$PORT" -t "$TMP" > "$TMP/server.log" 2>&1 &
PID=$!
READY=0
i=0
while [ "$i" -lt 20 ]; do
  if curl --noproxy '*' -sS --max-time 1 -o /dev/null "http://127.0.0.1:$PORT/"; then READY=1; break; fi
  i=$((i+1))
  sleep 1
done
[ "$READY" -eq 1 ] || { echo "LOOPBACK_SERVER: ROOD"; exit 1; }
echo "LOOPBACK_SERVER: GROEN"
FAILED=0
for item in 'UNKNOWN_ROUTE|/office-test/__router_preflight_missing_20261024|404' 'DIENST_GET|/office-test/inzet/dienst/nieuw|405' 'PERSONEEL_GET|/office-test/inzet/medewerker/personeelsnummer|405'; do
  label=${item%%|*}
  rest=${item#*|}
  route=${rest%%|*}
  expected=${rest##*|}
  actual=$(curl --noproxy '*' -sS --max-time 8 -o /dev/null -w '%{http_code}' -H 'Host: brebobv.nl' "http://127.0.0.1:$PORT$route") || actual=000
  if [ "$actual" = "$expected" ]; then
    echo "$label: GROEN (HTTP $actual)"
  else
    echo "$label: ROOD (HTTP $actual, verwacht $expected)"
    FAILED=1
  fi
done
if [ "$FAILED" -eq 0 ]; then echo "LOOPBACK_HTTP_SMOKE: GROEN"; else echo "LOOPBACK_HTTP_SMOKE: ROOD"; fi
exit "$FAILED"
