#!/usr/bin/env bash
set -euo pipefail
umask 077

[[ "$(id -u)" == "0" ]]

BASE="$(cd "$(dirname "$0")" && pwd)"
STATE=/var/lib/brebo-mail-activation
MARKER="$STATE/recovery-required"

[[ -e /proc/$$/fd/9 ]]
[[ /proc/$$/fd/9 -ef "$STATE/worker.lock" ]]
flock -n 9

if [[ ! -e "$MARKER" ]]; then
    echo "MAIL_ACTIVATION_RECOVERY=CLEAR"
    exit 0
fi

[[ -f "$MARKER" && ! -L "$MARKER" ]]
[[ "$(stat -c '%U:%a' "$MARKER")" == "root:600" ]]

mapfile -t RECORD < "$MARKER"

[[ "${#RECORD[@]}" == "3" ]]

BACKUP="${RECORD[0]}"
PUBLICATION="${RECORD[1]}"
HASH="${RECORD[2]}"

[[ "$BACKUP" =~ ^/var/backups/brebo-mail-activation/activation\.[a-zA-Z0-9]+$ ]]
[[ "$PUBLICATION" =~ ^stage\.[a-zA-Z0-9]+$ ]]
[[ "$HASH" =~ ^[a-f0-9]{64}$ ]]

STATUS=$(runuser -u brebomail -- php -r '
$db=new PDO("sqlite:/var/lib/brebo-mail-gateway/gateway.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA query_only=ON");
$db->exec("PRAGMA busy_timeout=5000");

$stmt=$db->prepare(
    "SELECT manifest_sha256
     FROM mailbox_activation_publications
     WHERE publication_id=?"
);
$stmt->execute([$argv[1]]);
$value=$stmt->fetchColumn();

if ($value === false) {
    echo "UNCOMMITTED";
} elseif (hash_equals($value,$argv[2])) {
    echo "COMMITTED";
} else {
    exit(2);
}
' "$PUBLICATION" "$HASH")

case "$STATUS" in
    UNCOMMITTED)
        bash "$BASE/restore-active-test-mailboxes.sh" "$BACKUP"
        bash "$BASE/preflight-active-test-mailboxes.sh"

        sync -f /etc/postfix
        sync -f /etc/dovecot/brebo-auth

        rm -f "$MARKER"
        sync -f "$STATE"

        echo "MAIL_ACTIVATION_RECOVERY=RESTORED"
        ;;

    COMMITTED)
        STAGE="$STATE/$PUBLICATION"

        [[ -d "$STAGE" && ! -L "$STAGE" ]]
        [[ "$(stat -c '%U:%a' "$STAGE")" == "root:700" ]]

        [[ "$(sha256sum "$STAGE/activation-manifest.json" |
            awk '{print $1}')" == "$HASH" ]]

        cmp -s "$STAGE/users" \
            /etc/dovecot/brebo-auth/users

        cmp -s "$STAGE/virtual_mailboxes" \
            /etc/postfix/brebo_virtual_mailboxes

        diff -q \
            <(postmap -s "hash:$STAGE/virtual_mailboxes" | sort) \
            <(postmap -s hash:/etc/postfix/brebo_virtual_mailboxes | sort) \
            >/dev/null

        bash "$BASE/preflight-active-test-mailboxes.sh"

        while read -r ADDRESS VALUE; do
            [[ "$VALUE" == "1" ]]
            doveadm user "$ADDRESS" >/dev/null
        done < "$STAGE/virtual_mailboxes"

        rm -f "$MARKER"
        sync -f "$STATE"

        rm -rf -- "$STAGE"

        echo "MAIL_ACTIVATION_RECOVERY=COMMITTED_VERIFIED"
        ;;

    *)
        echo "MAIL_ACTIVATION_RECOVERY=BLOCKED"
        exit 2
        ;;
esac
