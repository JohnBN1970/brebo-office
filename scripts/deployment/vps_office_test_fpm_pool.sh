#!/usr/bin/env bash
# Root-only, create-once dedicated PHP-FPM pool for isolated Office-test.
set -euo pipefail
umask 077
[[ $(id -u) -eq 0 ]] || { echo 'ROOT_REQUIRED' >&2; exit 10; }
[[ -f /etc/brebo/office-test-db.env && ! -L /etc/brebo/office-test-db.env ]] || { echo 'SECRET_MISSING_OR_UNSAFE' >&2; exit 11; }
[[ $(stat -c %a /etc/brebo/office-test-db.env) == 600 ]] || { echo 'SECRET_MODE_UNSAFE' >&2; exit 12; }
[[ $(stat -c %U /etc/brebo/office-test-db.env) == root ]] || { echo 'SECRET_OWNER_UNSAFE' >&2; exit 13; }
[[ -d /srv/brebo/office-test && ! -L /srv/brebo/office-test ]] || exit 14
[[ $(stat -c %U /srv/brebo/office-test) == brebo-deploy ]] || exit 15
pool=/etc/php/8.3/fpm/pool.d/brebo-office-test.conf
[[ ! -e $pool && ! -L $pool ]] || { echo 'POOL_ALREADY_EXISTS_REFUSING_OVERWRITE' >&2; exit 16; }
# Root owns the PHP-FPM pool config; secrets are never copied into the webroot.
set -a
source /etc/brebo/office-test-db.env
set +a
[[ $OFFICE_TEST_DB_HOST == localhost && $OFFICE_TEST_DB_NAME == brebo_office_test && $OFFICE_TEST_DB_USER == brebo_office_test ]] || exit 17
[[ $OFFICE_TEST_DB_PASSWORD =~ ^[a-f0-9]{64}$ ]] || exit 18
install -d -o root -g root -m 755 /run/php
tmp=$(mktemp /etc/php/8.3/fpm/pool.d/.brebo-office-test.XXXXXXXX)
trap 'rm -f -- "$tmp"' EXIT
cat > "$tmp" <<EOF
[brebo-office-test]
user = brebo-deploy
group = brebo
listen = /run/php/brebo-office-test.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 8
pm.process_idle_timeout = 20s
clear_env = yes
env[OFFICE_TEST_DB_HOST] = $OFFICE_TEST_DB_HOST
env[OFFICE_TEST_DB_NAME] = $OFFICE_TEST_DB_NAME
env[OFFICE_TEST_DB_USER] = $OFFICE_TEST_DB_USER
env[OFFICE_TEST_DB_PASSWORD] = $OFFICE_TEST_DB_PASSWORD
php_admin_flag[log_errors] = on
php_admin_value[expose_php] = Off
EOF
chown root:root "$tmp"
chmod 600 "$tmp"
# Validate before activation. PHP-FPM master reads the root-only pool file.
php-fpm8.3 -t -y /etc/php/8.3/fpm/php-fpm.conf -c /etc/php/8.3/fpm >/dev/null 2>&1 || { echo 'PREEXISTING_FPM_CONFIG_INVALID' >&2; exit 19; }
mv -- "$tmp" "$pool"
if ! php-fpm8.3 -t -y /etc/php/8.3/fpm/php-fpm.conf -c /etc/php/8.3/fpm >/dev/null 2>&1; then
  rm -f -- "$pool"
  echo 'NEW_POOL_VALIDATION_FAILED_ROLLED_BACK' >&2
  exit 20
fi
systemctl reload php8.3-fpm
unset OFFICE_TEST_DB_PASSWORD
echo 'VPS_OFFICE_TEST_FPM_POOL_READY'
echo 'SOCKET=/run/php/brebo-office-test.sock'
echo 'NGINX_NOT_CHANGED'
