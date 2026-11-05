#!/usr/bin/env bash
set -euo pipefail
umask 077
ROOT="$HOME/domains/brebobv.nl/office_calc_test"
ROUTER="$HOME/domains/brebobv.nl/public_html/office-test/index.php"
CONFIG="$ROOT/config/config.php"
STAGE="$ROOT/.intake-runtime-install-stage/office-independent"
PHP=/opt/alt/php83/usr/bin/php
ACTIVE="$ROOT/release-office-test-contractform-20261030/private"
fail() { echo "ABORT: $1" >&2; exit 2; }
test -f "$CONFIG" && test -f "$ROUTER" || fail missing_router_or_config
test ! -L "$CONFIG" && test ! -L "$ROUTER" || fail unsafe_symlink
test -d "$ACTIVE/public" && test -d "$ACTIVE/src" || fail active_release_missing
actual="$("$PHP" -r '$c=require $argv[1]; if (!is_array($c) || ($c["OFFICE_BASE_PATH"]??null)!=="/office-test" || ($c["OFFICE_INTAKE_DB_USER"]??null)!=="u213420663_office_test" || ($c["OFFICE_INTAKE_RECEIVER_ENABLED"]??"disabled")==="enabled") {exit(2);} echo $c["OFFICE_PRIVATE_ROOT"]??"";' "$CONFIG")" || fail config_scope_invalid
test "$actual" = "$ACTIVE" || fail active_release_changed
test "$(realpath "$actual")" = "$(realpath "$ACTIVE")" || fail realpath_mismatch
grep -Fq "OFFICE_PRIVATE_ROOT" "$ROUTER" || fail unexpected_router
files=(
  public/office-intake-receive.php
  src/Intake/OfficeDisciplineCatalog.php
  src/Intake/OfficeProcessBlueprint.php
  src/Intake/OfficeIntakeProcessPlan.php
  src/Intake/OfficeCanonicalIntakeRepository.php
  src/Intake/OfficeCanonicalIntakeAdapter.php
  src/Buildings/OfficeIntakeBuildingResolver.php
  src/Intake/OfficeCanonicalIntakeBuildingHandoff.php
  src/Intake/OfficeIntakeSourceAuthenticator.php
  src/Intake/OfficeIntakeReplayGuard.php
  src/Intake/OfficeIntakeSecureReceiver.php
)
for rel in "${files[@]}"; do
  test -f "$STAGE/$rel" && test ! -L "$STAGE/$rel" || fail staged_file_missing
  "$PHP" -l "$STAGE/$rel" >/dev/null || fail invalid_php
  if test -e "$ACTIVE/$rel" || test -L "$ACTIVE/$rel"; then
    cmp -s "$STAGE/$rel" "$ACTIVE/$rel" || fail existing_file_conflict
  fi
done
export ROUTER
python3 - <<'PY'
from pathlib import Path
import os
s=Path(os.environ["ROUTER"]).read_text()
assert "/office-test/api/intake/v1/receive" not in s, "route already exists"
assert s.count("$routes = [")==1, "unsupported route layout"
assert "'/office-test/crm/aanvragen'" in s, "known route missing"
assert "OFFICE_PRIVATE_ROOT" in s, "private root missing"
PY
BACKUP="$ROOT/.intake-runtime-install-backups/$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -p "$BACKUP"
cp -p "$ROUTER" "$BACKUP/router.php"
test -f "$BACKUP/router.php" || fail backup_failed
installed=()
rollback() {
  cp -p "$BACKUP/router.php" "$ROUTER"
  for rel in "${installed[@]}"; do rm -f "$ACTIVE/$rel"; done
  echo "OFFICE_TEST_INTAKE_ROLLBACK_COMPLETE"
}
trap rollback ERR
for rel in "${files[@]}"; do
  if test ! -e "$ACTIVE/$rel"; then
    mkdir -p "$(dirname "$ACTIVE/$rel")"
    install -m 644 "$STAGE/$rel" "$ACTIVE/$rel"
    installed+=("$rel")
  fi
  "$PHP" -l "$ACTIVE/$rel" >/dev/null
done
python3 - <<'PY'
from pathlib import Path
import os
p=Path(os.environ["ROUTER"])
s=p.read_text()
needle="$routes = ["
assert s.count(needle)==1
guard="""// Secure Office-test intake receiver, disabled by default.
if ($path === '/office-test/api/intake/v1/receive') {
  if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_INTAKE_RECEIVER_ENABLED') !== 'enabled') {
    http_response_code(404);
    exit('Testpagina niet gevonden.');
  }
  require $private . '/public/office-intake-receive.php';
  exit;
}
"""
assert s.count("$path = parse_url(")==1, "path parser mismatch"
s=s.replace(needle,guard+needle,1)
p.write_text(s)
PY
"$PHP" -l "$ROUTER" >/dev/null
grep -Fq "OFFICE_INTAKE_RECEIVER_ENABLED" "$ROUTER"
test -f "$ACTIVE/public/office-intake-receive.php"
trap - ERR
echo "OFFICE_TEST_INTAKE_RUNTIME_INSTALLED_DISABLED"
echo "OFFICE_TEST_ACTIVE_RELEASE=release-office-test-contractform-20261030"
echo "OFFICE_TEST_BACKUP=$(basename "$BACKUP")"
echo "OFFICE_TEST_CONFIG_UNCHANGED"
