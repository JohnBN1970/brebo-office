#!/usr/bin/env python3
"""Regression test for fail-closed Office-test budget mount dry-run."""
import os
import pathlib
import subprocess
import tempfile

script = pathlib.Path(__file__).with_name("mount-working-budget-panel.py")
with tempfile.TemporaryDirectory(prefix="office_calc_test_") as root:
    # Test fixture must match the verified test-release path contract.
    # The mount intentionally rejects arbitrary temporary paths.
    root_path = pathlib.Path(root)
    active = root_path / "release-test" / "private" / "public" / "office-project.php"
    active.parent.mkdir(parents=True)
    active.write_text("<?php\nOfficeNativeProjectAccess($db))->canRead\nis_array($financeContracts)\n$financialSectionHtml .= $financeHtml;\n")
    repo = root_path / "repo-project.php"
    repo.write_text("<?php\n// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only.\n"
                    "if (OfficeMount::isTest() && getenv('OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED') === 'enabled' && is_array($financeContracts)) {\n"
                    "ProjectBudgetPanel::render($workBudgetView);\n}\n"
                    "$financialSectionHtml .= $financeHtml;\n")
    env = dict(os.environ, OFFICE_BASE_PATH="/office-test", OFFICE_WORK_BUDGET_MOUNT="explicit")
    result = subprocess.run(["python3", str(script), str(active), str(repo)], env=env,
                            capture_output=True, text=True, check=False)
    assert result.returncode != 0 and "WORK_BUDGET_MOUNT_TARGET_NOT_VERIFIED_TEST_RELEASE" in result.stderr
    assert b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED" not in active.read_bytes()
print("WORK_BUDGET_MOUNT_UNTRUSTED_PATH_TEST=PASS")
