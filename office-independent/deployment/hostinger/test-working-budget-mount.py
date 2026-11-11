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
    active.write_text("<?php\nOfficeNativeProjectAccess($db))->canRead\nis_array($financeContracts)\n// Read-only Office procurement commitments: independent of invoicing and bank transactions.\n")
    repo = root_path / "repo-project.php"
    repo.write_text("<?php\n// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only.\n"
                    "if (OfficeMount::isTest() && getenv('OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED') === 'enabled' && is_array($financeContracts)) {\n"
                    "ProjectBudgetPanel::render($workBudgetView);\n}\n"
                    "// Read-only Office procurement commitments: independent of invoicing and bank transactions.\n")
    env = dict(os.environ, OFFICE_BASE_PATH="/office-test", OFFICE_WORK_BUDGET_MOUNT="explicit")
    result = subprocess.run(["python3", str(script), str(active), str(repo)], env=env,
                            capture_output=True, text=True, check=False)
    assert result.returncode != 0 and "WORK_BUDGET_MOUNT_TARGET_NOT_VERIFIED_TEST_RELEASE" in result.stderr
    assert b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED" not in active.read_bytes()
print("WORK_BUDGET_MOUNT_UNTRUSTED_PATH_TEST=PASS")

# Positive path: a verified-looking test release, staged dependencies, hash-pinned
# explicit apply, and byte-for-byte rollback copy. Never touch a live release.
with tempfile.TemporaryDirectory() as root:
    release = pathlib.Path(root) / "office_calc_test" / "release-fixture"
    active = release / "public" / "office-project.php"
    active.parent.mkdir(parents=True)
    active.write_text("""<?php
// OfficeNativeProjectAccess($db))->canRead
// is_array($financeContracts)
// Read-only Office procurement commitments: independent of invoicing and bank transactions.
""")
    original = active.read_bytes()
    source = pathlib.Path(root) / "source.php"
    source.write_text("""<?php
// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only.
if (OfficeMount::isTest() && getenv('OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED') === 'enabled'
    && is_array($financeContracts)) {
    $financeHtml .= ProjectBudgetPanel::render($workBudgetView);
}
// Read-only Office procurement commitments: independent of invoicing and bank transactions.
""")
    deps = release / "src" / "WorkingBudget"
    deps.mkdir(parents=True)
    for name in ("LockedBaselineLines.php", "WorkingBudgetKpiProjection.php",
                 "ProjectBudgetReadModel.php", "ProjectBudgetPanel.php",
                 "OnSiteLaborEvidence.php"):
        (deps / name).write_text("<?php\\n")
    env = dict(os.environ, OFFICE_BASE_PATH="/office-test",
               OFFICE_WORK_BUDGET_MOUNT="explicit")
    command = ["python3", str(script), str(active), str(source)]
    dry = subprocess.run(command, env=env, capture_output=True, text=True, check=False)
    assert dry.returncode == 0 and "WORK_BUDGET_MOUNT_DRY_RUN_OK" in dry.stdout, (dry.stdout, dry.stderr)
    assert active.read_bytes() == original
    import hashlib
    env["OFFICE_WORK_BUDGET_APPLY"] = "explicit"
    env["OFFICE_WORK_BUDGET_EXPECTED_SHA256"] = hashlib.sha256(original).hexdigest()
    applied = subprocess.run(command, env=env, capture_output=True, text=True, check=False)
    assert applied.returncode == 0 and "WORK_BUDGET_PANEL_MOUNTED_FLAG_UNCHANGED" in applied.stdout, (applied.stdout, applied.stderr)
    assert b"Native work budget panel" in active.read_bytes()
    assert active.with_name("office-project.php.pre-working-budget.bak").read_bytes() == original
    repeat = subprocess.run(command, env=env, capture_output=True, text=True, check=False)
    assert repeat.returncode == 0 and "WORK_BUDGET_PANEL_ALREADY_MOUNTED" in repeat.stdout
print("WORK_BUDGET_MOUNT_APPLY_BACKUP_IDEMPOTENCE_TEST=PASS")
