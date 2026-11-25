#!/usr/bin/env bash
set -euo pipefail
umask 077

[[ "$(id -u)" == "0" ]]
[[ "$#" == "1" ]]

STAGE=$(realpath -e "$1")
[[ "$STAGE" =~ ^/var/lib/brebo-mail-activation/stage\.[a-zA-Z0-9]+$ ]]
[[ "$(stat -c '%U:%a' "$STAGE")" == "root:700" ]]

MAP_STAGE=""

trap 'if [[ -n "$MAP_STAGE" ]]; then rm -rf -- "$MAP_STAGE"; fi' EXIT

if [[ -s "$STAGE/virtual_mailboxes" ]]; then
    MAP_STAGE=$(mktemp -d /run/brebo-maildir.XXXXXXXX)

    chown root:vmail "$MAP_STAGE"
    chmod 750 "$MAP_STAGE"

    install -o root -g vmail -m 440 \
        "$STAGE/virtual_mailboxes" \
        "$MAP_STAGE/virtual_mailboxes"

    runuser -u vmail -- env \
        BREBO_MAILDIR_MAILBOX_MAP="$MAP_STAGE/virtual_mailboxes" \
        BREBO_MAILDIR_ROOT=/var/vmail \
        /usr/bin/php \
        /usr/local/lib/brebo-mail/provision-maildirs.php
fi


echo "MAIL_ACTIVATION_STORAGE=PREPARED"
