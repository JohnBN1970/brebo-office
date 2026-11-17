#!/usr/bin/env bash
# Provision a NEW, isolated MariaDB database for VPS Office-test.
# Run once as root on the VPS. Never imports existing production data.
set -euo pipefail
umask 077
[[ "$(id -u)" == 0 ]] || { echo 'ERROR: root required'; exit 10; }
command -v mariadb >/dev/null
command -v openssl >/dev/null
install -d -o root -g root -m 700 /etc/brebo
secret=/etc/brebo/office-test-db.env
if [[ -e "$secret" ]]; then
  echo 'STOP: credentials already exist; refusing to rotate or overwrite'
  exit 11
fi
# No partial overwrite of an existing DB/user. This is intentionally create-once.
if mariadb --batch --skip-column-names -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='brebo_office_test'" | grep -qx brebo_office_test; then
  echo 'STOP: brebo_office_test already exists; inspect before provisioning'
  exit 12
fi
password="$(openssl rand -hex 32)"
# Password is hex only; safe for SQL string literal.
mariadb <<SQL
CREATE DATABASE brebo_office_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'brebo_office_test'@'localhost' IDENTIFIED BY '$password';
GRANT ALL PRIVILEGES ON brebo_office_test.* TO 'brebo_office_test'@'localhost';
SQL
# Only root can read this file. No credentials in logs or GitHub Actions.
printf 'OFFICE_TEST_DB_HOST=localhost\nOFFICE_TEST_DB_NAME=brebo_office_test\nOFFICE_TEST_DB_USER=brebo_office_test\nOFFICE_TEST_DB_PASSWORD=%s\n' "$password" > "$secret"
chmod 600 "$secret"
unset password
echo 'VPS_OFFICE_TEST_DB_PROVISIONED'
echo 'Credentials saved at /etc/brebo/office-test-db.env (root-only)'
echo 'Next: configure PHP-FPM-specific secret delivery; do not copy to webroot or Git'
