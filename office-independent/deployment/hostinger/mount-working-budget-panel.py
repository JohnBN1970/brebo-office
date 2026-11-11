#!/usr/bin/env python3
"""Fail-closed additive mount of the authorized Office-test budget panel.

Usage: python3 mount-working-budget-panel.py <active-office-project.php> <repo-office-project.php>
Only use against an explicitly verified Office-test release. This tool never
changes routes, environment flags, databases or production configuration.
"""
import os
import pathlib
import sys
import tempfile

if len(sys.argv) != 3:
    sys.exit("usage: mount-working-budget-panel.py <active-project.php> <repo-project.php>")
target, source = map(pathlib.Path, sys.argv[1:])
if target.is_symlink() or source.is_symlink() or not target.is_file() or not source.is_file():
    sys.exit("WORK_BUDGET_MOUNT_UNSAFE_PATH")
if os.environ.get("OFFICE_BASE_PATH") != "/office-test" or os.environ.get("OFFICE_WORK_BUDGET_MOUNT") != "explicit":
    sys.exit("WORK_BUDGET_MOUNT_SCOPE_DENIED")
active, repo = target.read_bytes(), source.read_bytes()
start = b"// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only."
end = b"$financialSectionHtml .= $financeHtml;"
if repo.count(start) != 1 or repo.count(end) != 1 or active.count(end) != 1:
    sys.exit("WORK_BUDGET_MOUNT_ANCHOR_INVALID")
begin = repo.index(start)
finish = repo.index(end, begin)
snippet = repo[begin:finish]
required = (b"OfficeMount::isTest()", b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED",
            b"is_array($financeContracts)", b"ProjectBudgetPanel::render($workBudgetView)")
if not all(item in snippet for item in required):
    sys.exit("WORK_BUDGET_MOUNT_SECURITY_GUARDS_MISSING")
if active.count(start) == 1:
    if snippet in active:
        print("WORK_BUDGET_PANEL_ALREADY_MOUNTED")
        sys.exit(0)
    sys.exit("WORK_BUDGET_PANEL_MOUNT_DIFFERS")
if active.count(start) != 0 or b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED" in active:
    sys.exit("WORK_BUDGET_PANEL_PARTIAL_MOUNT")
if b"OfficeNativeProjectAccess($db))->canRead" not in active or b"is_array($financeContracts)" not in active:
    sys.exit("WORK_BUDGET_MOUNT_ACTIVE_AUTH_MISSING")
# Require exact financial section anchor and preserve all other bytes.
updated = active.replace(end, snippet + end, 1)
if updated.replace(snippet + end, end, 1) != active:
    sys.exit("WORK_BUDGET_MOUNT_INTEGRITY_FAILED")
fd, temp = tempfile.mkstemp(prefix=".work-budget-", dir=str(target.parent))
try:
    with os.fdopen(fd, "wb") as stream:
        stream.write(updated)
        stream.flush()
        os.fsync(stream.fileno())
    os.chmod(temp, target.stat().st_mode & 0o777)
    # Atomic replace; operator must maintain a verified rollback copy.
    os.replace(temp, target)
finally:
    if os.path.exists(temp):
        os.unlink(temp)
print("WORK_BUDGET_PANEL_MOUNTED_FLAG_UNCHANGED")
