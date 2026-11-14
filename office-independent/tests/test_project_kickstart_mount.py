#!/usr/bin/env python3
"""Regression contract for the guarded Office-test kickstart V3 mount."""
from pathlib import Path
import importlib.util

root = Path(__file__).resolve().parents[1]
mount = root / "deployment/hostinger/mount-project-detail-v3.py"
spec = importlib.util.spec_from_file_location("project_detail_mount", mount)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(module)

replacement = module.REPLACEMENT
for marker in (
    "OFFICE_TEST_PROJECT_KICKSTART_ENABLED",
    "OfficeProjectKickstartReadModel",
    "OfficeProjectKickstartView",
    "forAuthorizedScope((int)$projectId, 'project', 0)",
    "$projectV3Sections['kickstart']",
    "$projectV3Tab === 'werkvoorbereiding'",
    "OfficeMount::isTest()",
):
    assert marker in replacement, f"Missing guarded kickstart mount: {marker}"
assert replacement.index("OfficeMount::isTest()") < replacement.index("OfficeProjectKickstartReadModel")
assert "OfficeProjectKickstartActionHandler" not in replacement, "Read-only mount must not process writes"

source = (root / "public/office-project.php").read_text()
assert "OfficeNativeProjectAccess($db))->canRead" in source, "Missing project ACL"
assert "$operator = $sessions->currentOperator()" in source, "Missing authenticated operator"
assert "if ($project === null)" in source, "Missing project lookup"
print("KICKSTART_MOUNT_CONTRACT_OK")
