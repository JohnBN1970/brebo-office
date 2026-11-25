#!/usr/bin/env bash
set -euo pipefail

umask 077

[[ "$(id -u)" == "0" ]]
[[ "$#" == "1" ]]

STAGE=$(realpath -e "$1")

[[ "$(stat -c '%U:%a' "$STAGE")" == "root:700" ]]

for F in users virtual_mailboxes
do
    [[ -f "$STAGE/$F" && ! -L "$STAGE/$F" ]]
    [[ "$(stat -c '%U:%a' "$STAGE/$F")" == "root:600" ]]
done

BASE="$(cd "$(dirname "$0")" && pwd)"

if [[ -s "$STAGE/virtual_mailboxes" ]]; then
    BREBO_MAILSTACK_CONFIG_DIR="$STAGE" \
        php "$BASE/preflight-mailbox-auth.php"
else
    [[ ! -s "$STAGE/users" ]]
fi

awk '
    NF != 2 ||
    $1 !~ /^[a-z0-9][a-z0-9._+%-]*@testmail\.brebobv\.nl$/ ||
    $1 ~ /\.\./ ||
    $2 != "1" { exit 2 }
' "$STAGE/virtual_mailboxes"

postmap "hash:$STAGE/virtual_mailboxes"

diff -q \
    <(awk '{print $1,$2}' "$STAGE/virtual_mailboxes" | sort) \
    <(postmap -s "hash:$STAGE/virtual_mailboxes" |
      awk '{print $1,$2}' | sort) >/dev/null

echo "MAIL_PUBLICATION_CANDIDATE=VALIDATED"

# Publicatie uitsluitend vanuit de vergrendelde worker.
STATE=/var/lib/brebo-mail-activation

[[ "$STAGE" == "$STATE"/stage.* ]]
[[ -e /proc/$$/fd/9 ]]
[[ /proc/$$/fd/9 -ef "$STATE/worker.lock" ]]
flock -n 9

[[ ! -e "$STATE/recovery-required" ]]

bash "$BASE/preflight-active-test-mailboxes.sh"

BACKUP=$(bash "$BASE/backup-active-test-mailboxes.sh")

PUBLICATION_ID=$(basename "$STAGE")
MANIFEST_HASH=$(sha256sum "$STAGE/activation-manifest.json" | awk '{print $1}')

sync -f "$BACKUP"
sync -f "$STAGE"

printf '%s\n%s\n%s\n' \
    "$BACKUP" \
    "$PUBLICATION_ID" \
    "$MANIFEST_HASH" \
    > "$STATE/recovery-required.tmp"
mv -fT "$STATE/recovery-required.tmp" \
    "$STATE/recovery-required"

sync -f "$STATE"

rollback() {
    local RC=$?
    trap - EXIT
    if [[ "$RC" != "0" ]]; then
        echo "MAIL_PUBLICATION=FAILED"

        if bash "$BASE/restore-active-test-mailboxes.sh" "$BACKUP"; then
            rm -f "$STATE/recovery-required"
            echo "MAIL_PUBLICATION=RESTORED"
        else
            echo "MAIL_PUBLICATION=RECOVERY_REQUIRED"
        fi
    fi
    exit "$RC"
}

trap rollback EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

publish_file() {
    local SOURCE="$1"
    local TARGET="$2"
    local OWNER="$3"
    local GROUP="$4"
    local MODE="$5"
    local TEMP

    TEMP=$(mktemp "$(dirname "$TARGET")/.brebo-publish.XXXXXXXX")

    install -o "$OWNER" -g "$GROUP" -m "$MODE" \
        "$SOURCE" "$TEMP"

    mv -fT "$TEMP" "$TARGET"
}

publish_file "$STAGE/users" \
    /etc/dovecot/brebo-auth/users root dovecot 640

publish_file "$STAGE/virtual_mailboxes" \
    /etc/postfix/brebo_virtual_mailboxes root root 644

publish_file "$STAGE/virtual_mailboxes.db" \
    /etc/postfix/brebo_virtual_mailboxes.db root root 644

postfix check
doveconf -n >/dev/null

systemctl reload dovecot
systemctl reload postfix

bash "$BASE/preflight-active-test-mailboxes.sh"

while read -r ADDRESS VALUE
do
    [[ "$(postmap -q "$ADDRESS" \
        hash:/etc/postfix/brebo_virtual_mailboxes)" == "1" ]]

    doveadm auth cache flush "$ADDRESS" >/dev/null
    doveadm user "$ADDRESS" >/dev/null
done < "$STAGE/virtual_mailboxes"

sync -f /etc/postfix
sync -f /etc/dovecot/brebo-auth


# Ingetrokken mailboxrechten direct verwerken.
while read -r ADDRESS; do
    [[ -n "$ADDRESS" ]] || continue

    doveadm auth cache flush "$ADDRESS" >/dev/null

    if OUTPUT=$(doveadm kick "$ADDRESS" 2>&1); then
        :
    elif [[ "$OUTPUT" == "no users kicked" ]]; then
        :
    else
        echo "MAIL_SESSION_REVOCATION=FAILED"
        exit 2
    fi

    if doveadm user "$ADDRESS" >/dev/null 2>&1; then
        echo "MAIL_USERDB_REVOCATION=FAILED"
        exit 2
    fi

    echo "MAIL_ACCESS_REVOKED=$ADDRESS"

done < <(
    comm -23 \
        <(awk '{print $1}' "$BACKUP/virtual_mailboxes" | sort -u) \
        <(awk '{print $1}' "$STAGE/virtual_mailboxes" | sort -u)
)

echo "MAIL_PUBLICATION_ID=$PUBLICATION_ID"
echo "MAIL_PUBLICATION=APPLIED"
echo "MAIL_PUBLICATION_BACKUP=$BACKUP"

# De worker verwijdert de herstelmarkering pas
# nadat de toegepaste databasestatus is vastgelegd.

trap - EXIT INT TERM
