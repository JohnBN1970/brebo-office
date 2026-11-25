#!/usr/bin/env bash
set -euo pipefail

umask 077

[[ "$(id -u)" == "0" ]]

BASE=/var/backups/brebo-mail-activation

install -d -m 700 -o root -g root "$BASE"

BACKUP=$(mktemp -d "$BASE/activation.XXXXXXXX")

MAP=/etc/postfix/brebo_virtual_mailboxes
USERS=/etc/dovecot/brebo-auth/users

cp -a "$MAP" "$BACKUP/virtual_mailboxes"
cp -a "$MAP.db" "$BACKUP/virtual_mailboxes.db"
cp -a "$USERS" "$BACKUP/users"

(
    cd "$BACKUP"
    sha256sum virtual_mailboxes \
        virtual_mailboxes.db users > checksums
    sha256sum -c --status checksums
)

chmod 700 "$BACKUP"

printf '%s\n' "$BACKUP"
