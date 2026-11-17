#!/usr/bin/env bash
# Read-only migration gate: no data, credentials, DNS or permissions are changed.
set -euo pipefail
echo '=== VPS OFFICE-TEST MIGRATION READINESS ==='
echo 'RELEASE_FOUNDATION'
for name in releases shared checkpoints; do
  if [[ -d "/srv/brebo/office-test/$name" && ! -L "/srv/brebo/office-test/$name" ]]; then
    echo "$name=ready"
  else
    echo "$name=missing"
  fi
done
echo 'SERVICE_READINESS'
for service in nginx php8.3-fpm mariadb; do
  if systemctl is-active --quiet "$service"; then echo "$service=active"; else echo "$service=inactive"; fi
done
echo 'SECURITY_ISOLATION'
test_owner="$(stat -c '%U' /srv/brebo/office-test)"
prod_owner="$(stat -c '%U' /srv/brebo/office)"
test_group="$(stat -c '%G' /srv/brebo/office-test)"
prod_group="$(stat -c '%G' /srv/brebo/office)"
if [[ "$test_owner" == "$prod_owner" || "$test_group" == "$prod_group" ]]; then
  echo 'TEST_PROD_UNIX_ISOLATION=BLOCKED_SHARED_IDENTITY'
else
  echo 'TEST_PROD_UNIX_ISOLATION=separate'
fi
echo 'LEGACY_HOSTINGER_ASSUMPTIONS'
echo 'Legacy deploy script hardcodes $HOME/domains/brebobv.nl/office_calc_test'
echo 'Legacy environment gate requires Hostinger database u213420663_office_test and brebobv.nl'
echo 'VPS_INSTALLATION=NOT_AUTHORIZED_UNTIL_CONFIG_AND_ISOLATION_REWORK'
echo 'READ_ONLY_AUDIT_COMPLETE'
