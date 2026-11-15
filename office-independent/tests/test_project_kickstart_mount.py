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
assert "OfficeProjectKickstartFormView" in replacement, "Authorized form missing from mount"
assert "'office_project_start_events'" in replacement, "Forms require audit event schema"
assert "fetchColumn() !== 4" in replacement, "Incomplete schema must fail closed"
assert "$canManage" in replacement, "Forms must be restricted to project managers"

source = (root / "public/office-project.php").read_text()
assert "OfficeNativeProjectAccess($db))->canRead" in source, "Missing project ACL"
assert "$operator = $sessions->currentOperator()" in source, "Missing authenticated operator"
assert "if ($project === null)" in source, "Missing project lookup"
for marker in (
    "isset($_POST['kickstart_action'])",
    "OFFICE_TEST_PROJECT_KICKSTART_ENABLED",
    "OfficeProjectKickstartActionHandler",
    "'authorized_dossier_id'=>(int)$dossier['id']",
    "'can_submit'=>$canManage",
    "'csrf_token'=>$token",
):
    assert marker in source, f"Missing trusted POST contract: {marker}"
assert source.index("isset($_POST['kickstart_action'])") < source.index("if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')"), "Kickstart POST must precede housing guard"
repository = (root / "src/Project/OfficeProjectKickstartRepository.php").read_text()
assert "createForAuthorizedProject" in repository, "Audited dossier creation missing"
assert "'dossier_created'" in repository, "Creation audit event missing"
assert "beginTransaction()" in repository, "Dossier creation must be atomic"
assert "project_leader_user_id" in repository, "Project leader binding missing"
assert "INSERT INTO office_project_start_dossiers" in repository, "Dossier insert missing"
assert "INSERT INTO office_project_start_events" in repository, "Creation audit insert missing"
assert "createForAuthorizedProject" not in source, "Creation must remain disabled until trusted project leader resolution is wired"
# A synthetic V2 controller exercises the deploy mount without assuming
# the repository's unmounted controller contains prior V2 deployment markers.
synthetic = "<?php\\n" + "\\n".join(module.REQUIRED) + "\\n" + module.ANCHOR + "\\n" + "echo $breboV23Render((string) ob_get_clean());"
mounted = module.transform(synthetic)
assert "OfficeProjectKickstartFormView" in mounted
assert module.transform(mounted) == mounted, "Mount must remain idempotent"
stale = mounted.replace("OfficeProjectKickstartFormView", "LegacyKickstartFormView")
assert module.transform(stale) == mounted, "Stale renderer must be refreshed"
print("KICKSTART_MOUNT_CONTRACT_OK")
