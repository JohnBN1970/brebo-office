#!/usr/bin/env python3
"""Guarded surgical mount: append Finance procurement widget, never replace project page."""
import os
import pathlib
import subprocess
import sys
import tempfile
from datetime import datetime, timezone

if os.environ.get("OFFICE_BASE_PATH") != "/office-test" or os.environ.get("OFFICE_GOVERNANCE_FINANCE_MOUNT") != "explicit":
    sys.exit("FINANCE_GOVERNANCE_SCOPE_DENIED")
if len(sys.argv) != 3:
    sys.exit("usage: mount-finance-governance-widget.py <active-project.php> <repository-project.php>")
target, source = (pathlib.Path(p) for p in sys.argv[1:])
if not target.is_file() or target.is_symlink() or not source.is_file():
    sys.exit("FINANCE_GOVERNANCE_TARGET_INVALID")
active = target.read_bytes()
repo = source.read_bytes()
start = b"// Read-only Office procurement commitments:"
end = b"$financialSectionHtml .= $financeHtml;"
if repo.count(start) != 1 or repo.count(end) != 1:
    sys.exit("FINANCE_GOVERNANCE_REPO_MARKERS_CHANGED")
widget = repo[repo.index(start):repo.index(end)]
if b"awardedProjectCommitment" not in widget or b"OfficeProcurementOrderSource" not in widget:
    sys.exit("FINANCE_GOVERNANCE_REPO_WIDGET_INCOMPLETE")
if b"awardedProjectCommitment" in active:
    if widget in active:
        print("FINANCE_GOVERNANCE_WIDGET_ALREADY_PRESENT")
        sys.exit(0)
    sys.exit("FINANCE_GOVERNANCE_WIDGET_CONFLICT")
if active.count(end) != 1 or active.count(b"$financeHtml") < 2:
    sys.exit("FINANCE_GOVERNANCE_LIVE_ANCHOR_MISMATCH")
for marker in [b"OfficeProjectFinanceOverview", b"OfficeNativeProjectAccess", b"create_project_contract",
               b"create_project_term", b"create_project_change", b"OfficeProjectDetailV3View"]:
    if marker not in active:
        sys.exit("FINANCE_GOVERNANCE_LIVE_REQUIRED_MARKER_MISSING")
new = active.replace(end, widget + end, 1)
if new.count(widget) != 1 or new.count(end) != 1:
    sys.exit("FINANCE_GOVERNANCE_PATCH_FAILED")
backup = target.with_name(target.name + ".backup-before-governance-" + datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S"))
with backup.open("xb") as f:
    f.write(active)
os.chmod(backup, 0o600)
fd, tmp = tempfile.mkstemp(prefix=".project-finance-governance-", suffix=".php", dir=target.parent)
try:
    with os.fdopen(fd, "wb") as f:
        f.write(new)
    os.chmod(tmp, target.stat().st_mode & 0o777)
    result = subprocess.run(["php", "-l", tmp], capture_output=True, text=True)
    if result.returncode:
        sys.exit("FINANCE_GOVERNANCE_CANDIDATE_PHP_INVALID")
    if target.read_bytes() != active:
        sys.exit("FINANCE_GOVERNANCE_CONCURRENT_CHANGE_ABORT")
    os.replace(tmp, target)
    result = subprocess.run(["php", "-l", str(target)], capture_output=True, text=True)
    if result.returncode:
        target.write_bytes(active)
        sys.exit("FINANCE_GOVERNANCE_RESTORED_AFTER_LINT_FAILURE")
    print("FINANCE_GOVERNANCE_WIDGET_MOUNTED")
    print("FINANCE_GOVERNANCE_BACKUP_PRESENT")
finally:
    if os.path.exists(tmp):
        os.unlink(tmp)
