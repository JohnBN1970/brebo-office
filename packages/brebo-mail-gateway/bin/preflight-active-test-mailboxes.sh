#!/usr/bin/env bash
set -euo pipefail

umask 077

[[ "$(id -u)" == "0" ]]

MAP=/etc/postfix/brebo_virtual_mailboxes
USERS=/etc/dovecot/brebo-auth/users
DOMAINS=/etc/postfix/brebo_virtual_domains

for F in "$MAP" "$MAP.db" "$USERS" "$DOMAINS"
do
    [[ -f "$F" && ! -L "$F" ]]
    [[ "$(stat -c '%U' "$F")" == "root" ]]
done

[[ "$(postconf -h virtual_mailbox_maps)" \
    == "hash:$MAP" ]]

[[ "$(postconf -h virtual_mailbox_domains)" \
    == "hash:$DOMAINS" ]]

[[ "$(postconf -h virtual_transport)" \
    == "lmtp:unix:private/dovecot-lmtp" ]]

[[ "$(postmap -s "hash:$DOMAINS" | awk '{print $1}')" \
    == "testmail.brebobv.nl" ]]

diff -q \
    <(postmap -s "hash:$MAP" | awk '{print $1}' | sort) \
    <(cut -d: -f1 "$USERS" | sort) >/dev/null

diff -q \
    <(awk '{print $1}' "$MAP" | sort) \
    <(postmap -s "hash:$MAP" | awk '{print $1}' | sort) >/dev/null

systemctl is-active --quiet postfix
systemctl is-active --quiet dovecot

postfix check
doveconf -n >/dev/null

echo "MAIL_PUBLICATION_PREFLIGHT=PASS"
