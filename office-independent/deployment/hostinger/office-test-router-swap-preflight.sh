#!/bin/sh
# Read-only deployment readiness check. Does not switch routers.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
ACTIVE="$ROOT/public_html/office-test/index.php"
CANDIDATE=${1:-/tmp/office-test-router-candidate.php}
PHP=/opt/alt/php83/usr/bin/php
FAILED=0
check() { if [ "$2" -eq 0 ]; then echo "$1: GROEN"; else echo "$1: ROOD"; FAILED=1; fi; }
[ -r "$ACTIVE" ] && a=0 || a=1
check ACTIVE_ROUTER "$a"
[ -r "$CANDIDATE" ] && c=0 || c=1
check CANDIDATE_ROUTER "$c"
if [ "$a" -eq 0 ]; then
  "$PHP" -l "$ACTIVE" >/dev/null 2>&1 && v=0 || v=1
  check ACTIVE_SYNTAX "$v"
fi
if [ "$c" -eq 0 ]; then
  "$PHP" -l "$CANDIDATE" >/dev/null 2>&1 && v=0 || v=1
  check CANDIDATE_SYNTAX "$v"
fi
if [ "$a" -eq 0 ] && [ "$c" -eq 0 ]; then
  cmp -s "$ACTIVE" "$CANDIDATE" && same=0 || same=1
  if [ "$same" -eq 0 ]; then echo "ROUTER_DIFF: IDENTIEK"; else echo "ROUTER_DIFF: VERSCHILLEND"; fi
  echo "ACTIVE_SHA256:"
  sha256sum "$ACTIVE"
  echo "CANDIDATE_SHA256:"
  sha256sum "$CANDIDATE"
fi
# Avoid creating publicly accessible test routes before access-control validation.
if [ "$FAILED" -eq 0 ]; then echo "SWAP_PREFLIGHT: GROEN (alleen bestand/syntax; HTTP nog niet bewezen)"; else echo "SWAP_PREFLIGHT: ROOD"; fi
exit "$FAILED"
