#!/usr/bin/env bash
set -euo pipefail

umask 077

[[ "$(id -u)" == "0" ]]

BASE="$(cd "$(dirname "$0")" && pwd)"
STATE=/var/lib/brebo-mail-activation

install -d -m 700 -o root -g root "$STATE"

exec 9>"$STATE/worker.lock"

if ! flock -n 9; then
    echo "MAIL_ACTIVATION_WORKER=BUSY"
    exit 0
fi

bash "$BASE/recover-mail-activation.sh"

bash "$BASE/preflight-active-test-mailboxes.sh"

echo "MAIL_ACTIVATION_LOCK=ACQUIRED"

export BREBO_MAIL_GATEWAY_DATA_DIR=/var/lib/brebo-mail-gateway

PENDING=$(php -r '
$db = new PDO(
    "sqlite:".getenv("BREBO_MAIL_GATEWAY_DATA_DIR")."/gateway.sqlite"
);
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA query_only=ON");
echo $db->query(
    "SELECT COUNT(*) FROM mailbox_activation WHERE status = ".chr(39)."pending".chr(39)
)->fetchColumn();
')

if [[ "$PENDING" == "0" ]]; then
    echo "MAIL_ACTIVATION_WORKER=IDLE"
    exit 0
fi

STAGE=$(mktemp -d "$STATE/stage.XXXXXXXX")
MAP_STAGE=""

cleanup() {
    if [[ ! -e "$STATE/recovery-required" ]]; then
        rm -rf -- "$STAGE"
    fi
    if [[ -n "$MAP_STAGE" ]]; then
        rm -rf -- "$MAP_STAGE"
    fi
}

trap cleanup EXIT


if php "$BASE/execute-mail-activation.php" "$STAGE"; then
    bash "$BASE/recover-mail-activation.sh"
    echo "MAIL_ACTIVATION_WORKER=COMMIT_COMPLETE"
else

if ! bash "$BASE/recover-mail-activation.sh"; then
    echo "MAIL_ACTIVATION_RECOVERY=MANUAL_REQUIRED"
    exit 2
fi
echo "MAIL_ACTIVATION_WORKER=FAILED"
    exit 2
fi

# Herstelmarkering en stagingbestanden behouden
# totdat de eindverificatie is aangesloten.
