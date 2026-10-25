#!/bin/sh
# Read-only, anonymous GET baseline for active Office-test protected endpoints.
# No credentials, cookies, POST requests or router changes.
set -eu
BASE=https://brebobv.nl
FAILED=0
for item in \
  'ONSITE_ACTIVATE|/office-test/api/onsite/v1/activate' \
  'ONSITE_BOOTSTRAP|/office-test/api/onsite/v1/bootstrap' \
  'ONSITE_PRESENCE|/office-test/api/onsite/v1/presence' \
  'ONSITE_DEVICES|/office-test/inzet/apparaten' \
  'ONSITE_INVITATION|/office-test/inzet/onsite/activatie-uitgeven' \
  'ONSITE_REVOKE|/office-test/inzet/onsite/apparaat-intrekken' \
  'INZET_PRESENCE|/office-test/inzet/aanwezigheid'; do
  label=${item%%|*}
  path=${item#*|}
  status=$(curl -sS --max-time 12 -o /dev/null -w '%{http_code}' "$BASE$path") || status=000
  case "$status" in
    301|302|303|307|308|401|403|405|422|503)
      echo "$label: GROEN (HTTP $status; geen anonieme 200)"
      ;;
    200)
      echo "$label: CONTROLE NODIG (HTTP 200; mogelijk loginpagina of openbare inhoud)"
      FAILED=1
      ;;
    404)
      echo "$label: ROOD (HTTP 404; route ontbreekt of wordt niet bereikt)"
      FAILED=1
      ;;
    *)
      echo "$label: ROOD (HTTP $status)"
      FAILED=1
      ;;
  esac
done
if [ "$FAILED" -eq 0 ]; then echo "ONSITE_ANONYMOUS_BASELINE: GROEN"; else echo "ONSITE_ANONYMOUS_BASELINE: CONTROLE NODIG"; fi
exit "$FAILED"
