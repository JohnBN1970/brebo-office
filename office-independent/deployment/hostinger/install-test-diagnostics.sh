#!/usr/bin/env bash
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo ROOT_REQUIRED; exit 1; }
base=/srv/brebo/office-test/current
release=$(readlink -f "$base")
[[ "$release" =~ ^/srv/brebo/office-test/releases/[0-9a-f]{40}$ ]] || { echo TEST_RELEASE_REQUIRED; exit 1; }
src="$release/deployment/hostinger"
unit=brebo-office-test-diagnostics
dest=/usr/local/lib/brebo-office-test
for file in collect-mail-worker-diagnostics.py "$unit.service" "$unit.timer"; do
  test -s "$src/$file"
done
python3 -c 'import ast,sys; ast.parse(open(sys.argv[1]).read())' "$src/collect-mail-worker-diagnostics.py"
install -d -o root -g root -m 0755 "$dest"
install -o root -g root -m 0644 "$src/collect-mail-worker-diagnostics.py" "$dest/collect-mail-worker-diagnostics.py"
systemd-analyze verify "$src/$unit.service" "$src/$unit.timer"
install -o root -g root -m 0644 "$src/$unit.service" "/etc/systemd/system/$unit.service"
install -o root -g root -m 0644 "$src/$unit.timer" "/etc/systemd/system/$unit.timer"
systemctl daemon-reload
systemctl start "$unit.service"
systemctl enable --now "$unit.timer"
systemctl is-active "$unit.timer"
test -s /run/brebo-office-test/diagnostics/mail-workers.json
echo DIAGNOSTIEKTIMER=READY
