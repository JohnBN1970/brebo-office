#!/usr/bin/env bash
set -euo pipefail

umask 077

[[ "$(id -u)" == "0" ]]
[[ "$#" == "1" ]]

BACKUP=$(realpath -e "$1")

[[ "$BACKUP" =~ ^/var/backups/brebo-mail-activation/activation\.[a-zA-Z0-9]+$ ]]
[[ "$(stat -c '%U:%a' "$BACKUP")" == "root:700" ]]

for F in virtual_mailboxes virtual_mailboxes.db users checksums
do
    [[ -f "$BACKUP/$F" && ! -L "$BACKUP/$F" ]]
    [[ "$(stat -c '%U' "$BACKUP/$F")" == "root" ]]
done

(
    cd "$BACKUP"
    sha256sum -c --status checksums
)

restore_file() {
    local SOURCE="$1"
    local TARGET="$2"
    local TEMP

    TEMP=$(mktemp "$(dirname "$TARGET")/.brebo-restore.XXXXXXXX")

    cp --preserve=mode,ownership "$SOURCE" "$TEMP"
    mv -fT "$TEMP" "$TARGET"
}

restore_file "$BACKUP/users" \
    /etc/dovecot/brebo-auth/users

restore_file "$BACKUP/virtual_mailboxes" \
    /etc/postfix/brebo_virtual_mailboxes

restore_file "$BACKUP/virtual_mailboxes.db" \
    /etc/postfix/brebo_virtual_mailboxes.db

postfix check
doveconf -n >/dev/null

systemctl reload dovecot
systemctl reload postfix

systemctl is-active --quiet dovecot
systemctl is-active --quiet postfix

echo "MAIL_ACTIVATION_ROLLBACK=PASS"
