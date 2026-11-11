#!/usr/bin/env python3
"""Fail-closed additive mount of the authorized Office-test budget panel.

Usage: python3 mount-working-budget-panel.py <active-office-project.php> <repo-office-project.php>
Only use against an explicitly verified Office-test release. This tool never
changes routes, environment flags, databases or production configuration.
"""
import hashlib
import os
import pathlib
import subprocess
import sys
import tempfile

if len(sys.argv) != 3:
    sys.exit("usage: mount-working-budget-panel.py <active-project.php> <repo-project.php>")
target, source = map(pathlib.Path, sys.argv[1:])
if target.is_symlink() or source.is_symlink() or not target.is_file() or not source.is_file():
    sys.exit("WORK_BUDGET_MOUNT_UNSAFE_PATH")
# Do not allow arbitrary paths or production release roots.
resolved = target.resolve()
if resolved.name != "office-project.php" or resolved.parent.name != "public" or not any(
    part.startswith("release-") for part in resolved.parts
) or "/office_calc_test/" not in str(resolved):
    sys.exit("WORK_BUDGET_MOUNT_TARGET_NOT_VERIFIED_TEST_RELEASE")
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
print("WORK_BUDGET_MOUNT_SOURCE_SHA256=" + hashlib.sha256(active).hexdigest())
print("WORK_BUDGET_MOUNT_TARGET_SHA256=" + hashlib.sha256(updated).hexdigest())
if os.environ.get("OFFICE_WORK_BUDGET_APPLY") != "explicit":
    print("WORK_BUDGET_MOUNT_DRY_RUN_OK")
    sys.exit(0)
expected = os.environ.get("OFFICE_WORK_BUDGET_EXPECTED_SHA256", "")
if expected != hashlib.sha256(active).hexdigest():
    sys.exit("WORK_BUDGET_MOUNT_EXPECTED_HASH_MISMATCH")
# Ensure all PHP classes referenced by the new mount are staged before any write.
required_classes = ("LockedBaselineLines.php", "WorkingBudgetKpiProjection.php",
                    "ProjectBudgetReadModel.php", "ProjectBudgetPanel.php",
                    "OnSiteLaborEvidence.php")
working_budget_dir = resolved.parent.parent / "src" / "WorkingBudget"
if not all((working_budget_dir / name).is_file() and not (working_budget_dir / name).is_symlink()
           for name in required_classes):
    sys.exit("WORK_BUDGET_MOUNT_DEPENDENCIES_MISSING")
php = os.environ.get("OFFICE_PHP_BINARY", "php")
with tempfile.NamedTemporaryFile(prefix=".work-budget-lint-", suffix=".php",
                                 dir=str(target.parent), delete=False) as candidate:
    candidate.write(updated)
    candidate_path = pathlib.Path(candidate.name)
try:
    lint = subprocess.run([php, "-l", str(candidate_path)], capture_output=True,
                          text=True, check=False, timeout=30)
    if lint.returncode != 0:
        sys.exit("WORK_BUDGET_MOUNT_PHP_LINT_FAILED")
finally:
    candidate_path.unlink(missing_ok=True)
backup = target.with_name(target.name + ".pre-working-budget.bak")
if backup.exists() or backup.is_symlink():
    sys.exit("WORK_BUDGET_MOUNT_BACKUP_ALREADY_EXISTS")
backup_fd = os.open(str(backup), os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
try:
    with os.fdopen(backup_fd, "wb") as backup_stream:
        backup_stream.write(active)
        backup_stream.flush()
        os.fsync(backup_stream.fileno())
except BaseException:
    backup.unlink(missing_ok=True)
    raise
if hashlib.sha256(backup.read_bytes()).digest() != hashlib.sha256(active).digest():
    sys.exit("WORK_BUDGET_MOUNT_BACKUP_VERIFICATION_FAILED")
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
