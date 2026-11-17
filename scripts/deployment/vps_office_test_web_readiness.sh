#!/usr/bin/env bash
# Read-only VPS web readiness audit; never reload nginx or change DNS.
set -euo pipefail
[[ $(id -u) -eq 0 ]] || { echo ROOT_REQUIRED; exit 2; }
base=/srv/brebo/office-test
echo 'WEB_READINESS_READ_ONLY=YES'
for p in /srv /srv/brebo "$base" "$base/releases" "$base/current" /run/php/brebo-office-test.sock; do
 if [[ -e "$p" || -L "$p" ]]; then stat -c 'PATH=%n TYPE=%F MODE=%a OWNER=%U GROUP=%G' "$p"; else echo "MISSING_PATH=$p"; fi
done
if [[ -L "$base/current" ]]; then
 target=$(readlink -f "$base/current")
 echo "CURRENT_TARGET=$target"
 case "$target" in "$base"/releases/*) echo CURRENT_RELEASE_IN_SCOPE=YES;; *) echo CURRENT_RELEASE_IN_SCOPE=NO;; esac
fi
for p in "$base/current/public/office-home.php" "$base/current/public/index.php" /etc/nginx/sites-enabled/office-test.brebobv.nl; do
 [[ -f "$p" ]] && echo "FILE_PRESENT=$p" || echo "FILE_MISSING=$p"
done
if command -v nginx >/dev/null; then nginx -t 2>&1 | tail -n 4; fi
if command -v runuser >/dev/null; then
 for p in /srv /srv/brebo "$base" "$base/current" "$base/current/public"; do
  if runuser -u www-data -- test -x "$p" 2>/dev/null; then echo "WWW_TRAVERSE_OK=$p"; else echo "WWW_TRAVERSE_BLOCKED=$p"; fi
 done
fi
echo 'WEB_READINESS_FINISHED=YES'
