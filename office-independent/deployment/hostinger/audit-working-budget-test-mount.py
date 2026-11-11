#!/usr/bin/env python3
"""Fail-closed, read-only preflight for the Office-test working budget mount.

Usage: python3 audit-working-budget-test-mount.py <active-office-project.php> <repo-office-project.php>
No writes, configuration changes, database changes, or production access.
"""
import pathlib
import sys

if len(sys.argv) != 3:
    sys.exit("usage: audit-working-budget-test-mount.py <active-project.php> <repo-project.php>")
active_path, repo_path = map(pathlib.Path, sys.argv[1:])
if not active_path.is_file() or not repo_path.is_file():
    sys.exit("WORK_BUDGET_PROJECT_FILE_MISSING")
active = active_path.read_bytes()
repo = repo_path.read_bytes()
checks = {
    "REPO_AUTH_PROJECT": b"OfficeNativeProjectAccess($db))->canRead" in repo,
    "REPO_FINANCE_ACL": b"is_array($financeContracts)" in repo,
    "REPO_TEST_ONLY": b"OfficeMount::isTest()" in repo,
    "REPO_EXPLICIT_FLAG": b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED" in repo,
    "REPO_PANEL": b"ProjectBudgetPanel::render($workBudgetView)" in repo,
    "REPO_READ_MODEL": b"ProjectBudgetReadModel($db)" in repo,
    "ACTIVE_PROJECT_AUTH": b"OfficeNativeProjectAccess($db))->canRead" in active,
    "ACTIVE_FINANCE_SECTION": active.count(b"// Read-only Office procurement commitments: independent of invoicing and bank transactions.") == 1,
}
for key, valid in checks.items():
    print(f"{key}={'OK' if valid else 'FAIL'}")
if not all(checks.values()):
    sys.exit("WORK_BUDGET_PREFLIGHT_FAILED")
if active.count(b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED") == 1:
    print("WORK_BUDGET_PANEL_ALREADY_MOUNTED")
elif active.count(b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED") == 0:
    print("WORK_BUDGET_PANEL_NOT_MOUNTED")
else:
    sys.exit("WORK_BUDGET_PANEL_DUPLICATE")
print("WORK_BUDGET_PREFLIGHT_READ_ONLY_OK")
