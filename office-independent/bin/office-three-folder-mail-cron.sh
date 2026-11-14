#!/bin/sh
# BREBO Office three-folder mail cron launcher.
# Install only after mailbox ACL, board routing, and config validation.
set -eu
umask 077
BASE="${OFFICE_MAIL_DEPLOY_BASE:-$HOME/domains/brebobv.nl/office_calc_test}"
IMPORT="$BASE/office-independent/bin/office-three-folder-mail-import.php"
ENVFILE="$BASE/config/office-three-folder-mail-cron.env"
if [ ! -r "$IMPORT" ] || [ ! -r "$ENVFILE" ]; then
  echo "Office mail cron prerequisites missing" >&2
  exit 2
fi
# ENVFILE must be owner-only (0600), shell-compatible and outside webroot.
# It must export OFFICE_INTAKE_PDO_DSN, OFFICE_INTAKE_DB_USER,
# OFFICE_INTAKE_DB_PASSWORD, BREBO_ZOHO_INFO_IMAP_SERVER,
# BREBO_ZOHO_INFO_IMAP_USER and BREBO_ZOHO_INFO_IMAP_PASSWORD.
# Do not copy credentials into the crontab or GitHub.
MODE=$(stat -c %a "$ENVFILE")
case "$MODE" in 600|400) ;; *) echo "Unsafe mail cron env permissions" >&2; exit 2;; esac
set -a
. "$ENVFILE"
set +a
export OFFICE_BASE_PATH=/office-test
export OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED=enabled
export OFFICE_THREE_FOLDER_CREDENTIAL_REF=BREBO_ZOHO_INFO
export OFFICE_THREE_FOLDER_MAILBOX_ID=1
# Keep imports disabled unless explicitly enabled in the private env file.
if [ "${OFFICE_THREE_FOLDER_IMPORT_ENABLED:-}" != enabled ] ||
   [ "${OFFICE_THREE_FOLDER_IMPORT_CONFIRMED:-}" != enabled ]; then
  echo "Office mail cron import not enabled" >&2
  exit 2
fi
exec /usr/bin/php "$IMPORT" --import
