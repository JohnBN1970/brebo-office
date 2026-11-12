#!/usr/bin/env python3
"""Guarded Office-test V3 work budget tab mount.

Move only the already-authorized live work budget panel from Finance into an
independent V3 tab; never replace the full active Office project controller.
Install two atomic file replacements with backed-up rollback on any failure.
"""
import hashlib
import os
import pathlib
import subprocess
import sys
import tempfile

if len(sys.argv) != 4:
    sys.exit("WORK_BUDGET_V3_USAGE")
project, reviewed_source, structure = map(pathlib.Path, sys.argv[1:])
if any(not p.is_file() or p.is_symlink() for p in (project, reviewed_source, structure)):
    sys.exit("WORK_BUDGET_V3_UNSAFE_FILE")
real_project = project.resolve()
real_structure = structure.resolve()
root = real_project.parent.parent
if (
    real_project.name != "office-project.php"
    or real_project.parent.name != "public"
    or "/office_calc_test/release-" not in str(real_project)
    or "/private/public/" not in str(real_project)
    or real_structure != root / "src/Project/OfficeProjectPageStructure.php"
    or os.getenv("OFFICE_BASE_PATH") != "/office-test"
):
    sys.exit("WORK_BUDGET_V3_UNSAFE_RELEASE_SCOPE")

current_project = project.read_bytes()
current_structure = structure.read_bytes()
source = reviewed_source.read_bytes()
if hashlib.sha256(current_project).hexdigest() != os.getenv("OFFICE_WORK_BUDGET_TAB_EXPECTED_SHA256"):
    sys.exit("WORK_BUDGET_V3_PROJECT_HASH_CHANGED")
if hashlib.sha256(current_structure).hexdigest() != os.getenv("OFFICE_WORK_BUDGET_STRUCTURE_EXPECTED_SHA256"):
    sys.exit("WORK_BUDGET_V3_STRUCTURE_HASH_CHANGED")

tab_label = b"'werkbegroting' => 'Werkbegroting'"
old_label = b"'financieel' => 'Financieel'"
new_label = tab_label + b", " + old_label
v3_tab_map = b"'werkbegroting' => (string) $workBudgetTabHtml,"
v3_finance_map = b"'financieel' => (string) $financeHtml,"
render_test = b"OfficeProjectDetailV3View::render("
panel_marker = b"// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only."

# A completed mount is safe to re-run, even though its backups already exist.
if (
    current_project.count(tab_label) == 1
    and current_project.count(v3_tab_map) == 1
    and current_structure.count(tab_label) == 1
    and b"$workBudgetTabHtml" in current_project
):
    print("WORK_BUDGET_V3_TAB_ALREADY_INSTALLED")
    sys.exit(0)
if (
    current_project.count(tab_label) != 0
    or current_structure.count(tab_label) != 0
    or b"$workBudgetTabHtml" in current_project
):
    sys.exit("WORK_BUDGET_V3_PARTIAL_INSTALL_REQUIRES_REVIEW")

required = (
    b"OfficeNativeProjectAccess($db))->canRead",
    b"// Financial dossier: explicit Finance ACL, independent of project read access.",
    b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED",
    b"OfficeMount::isTest()",
    b"is_array($financeContracts)",
    b"ProjectBudgetPanel::render($workBudgetView)",
    b"$projectV3Sections = [",
    render_test,
)
if not all(x in current_project for x in required):
    sys.exit("WORK_BUDGET_V3_ACTIVE_SECURITY_OR_RENDER_GUARD_MISSING")
if (
    current_project.count(panel_marker) != 1
    or current_project.count(old_label) != 1
    or current_project.count(v3_finance_map) != 1
    or current_project.count(b"$projectV3Sections = [") != 1
    or current_structure.count(old_label) != 1
    or b"function detailTabs()" not in current_structure
    or b"class OfficeProjectPageStructure" not in current_structure
    or source.count(tab_label) != 1
    or source.count(v3_finance_map) != 0
):
    # Reviewed source on develop is an independently rendered version, not V3.
    # Its content is used as an approved semantic reference only.
    if source.count(tab_label) != 1:
        sys.exit("WORK_BUDGET_V3_REVIEWED_SOURCE_TAB_MISSING")
    if source.count(v3_finance_map) != 0:
        sys.exit("WORK_BUDGET_V3_REVIEWED_SOURCE_UNEXPECTED_V3_MAP")
    sys.exit("WORK_BUDGET_V3_ACTIVE_RENDER_LAYOUT_UNEXPECTED")

# PHP tokenizer understands nested braces and quoted strings.
php_probe = r"""
$src = file_get_contents($argv[1]);
$start = strpos($src, '// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only.');
if ($start === false) exit(31);
$tokens = token_get_all($src);
$offset = 0; $started = false; $depth = 0; $end = null;
foreach ($tokens as $token) {
    $text = is_array($token) ? $token[1] : $token;
    if (!$started && $offset >= $start && $text === 'if') $started = true;
    if ($started) {
        if ($text === '{') $depth++;
        if ($text === '}') { $depth--; if ($depth === 0) { $end = $offset + 1; break; } }
    }
    $offset += strlen($text);
}
if ($end === null || $end <= $start) exit(32);
echo $end;
"""
probe = subprocess.run(
    ["php", "-r", php_probe, str(project)],
    check=False, capture_output=True, text=True
)
if probe.returncode or not probe.stdout.strip().isdigit():
    sys.exit("WORK_BUDGET_V3_PHP_PANEL_BOUNDARY_NOT_FOUND")
begin = current_project.index(panel_marker)
finish = int(probe.stdout.strip())
if finish <= begin or finish > len(current_project):
    sys.exit("WORK_BUDGET_V3_PHP_PANEL_BOUNDARY_INVALID")
panel = current_project[begin:finish]
if (
    panel.count(b"ProjectBudgetPanel::render($workBudgetView)") != 1
    or b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED" not in panel
    or b"OfficeMount::isTest()" not in panel
    or b"is_array($financeContracts)" not in panel
    or panel.count(b"$financeHtml .=") < 1
):
    sys.exit("WORK_BUDGET_V3_PANEL_AUTH_OR_OUTPUT_UNEXPECTED")

# Do not change the inkoop, contract, termijnen or Finance rendering after the panel.
moved_panel = panel.replace(b"$financeHtml .=", b"$workBudgetTabHtml .=")
if b"$financeHtml .=" in moved_panel:
    sys.exit("WORK_BUDGET_V3_PANEL_OUTPUT_REWRITE_INCOMPLETE")
intro = (
    b"$workBudgetTabHtml = '<div id=\"project-werkbegroting\"><h2>Werkbegroting</h2>"
    b"<p>Vastgestelde begroting, materialen, inkooporders en leveringen.</p>';\n"
)
new_project = current_project[:begin] + intro + moved_panel + b"\n$workBudgetTabHtml .= '</div>';\n" + current_project[finish:]
new_project = new_project.replace(old_label, new_label, 1)

map_start = new_project.index(b"$projectV3Sections = [")
map_end = new_project.index(b"];", map_start)
finance_at = new_project.index(v3_finance_map)
if not map_start < finance_at < map_end:
    sys.exit("WORK_BUDGET_V3_FINANCE_TAB_OUTSIDE_V3_MAP")
new_project = new_project.replace(v3_finance_map, v3_tab_map + b"\n    " + v3_finance_map, 1)
new_structure = current_structure.replace(old_label, new_label, 1)
if (
    new_project.count(tab_label) != 1
    or new_project.count(v3_tab_map) != 1
    or new_structure.count(tab_label) != 1
    or new_project.count(b"ProjectBudgetPanel::render($workBudgetView)") != 1
    or b"$financeHtml .= \\Brebo\\Office\\WorkingBudget\\ProjectBudgetPanel::render" in new_project
):
    sys.exit("WORK_BUDGET_V3_OUTPUT_INTEGRITY_CHECK_FAILED")

def create_candidate(path, content):
    with tempfile.NamedTemporaryFile(
        prefix=".work-budget-tab-v3-", dir=path.parent, delete=False
    ) as f:
        temp = pathlib.Path(f.name)
        f.write(content)
    temp.chmod(path.stat().st_mode & 0o777)
    return temp

def php_lint(path):
    subprocess.run(["php", "-l", str(path)], check=True, stdout=subprocess.DEVNULL)

temp_project = create_candidate(project, new_project)
temp_structure = create_candidate(structure, new_structure)
backup_project = project.with_name(project.name + ".pre-work-budget-tab-v3.bak")
backup_structure = structure.with_name(structure.name + ".pre-work-budget-tab-v3.bak")
try:
    php_lint(temp_project)
    php_lint(temp_structure)
    if backup_project.exists() or backup_structure.exists():
        sys.exit("WORK_BUDGET_V3_EXISTING_BACKUP_REQUIRES_REVIEW")
    # Create both backups before touching any live PHP file.
    backup_project.write_bytes(current_project)
    backup_project.chmod(0o600)
    backup_structure.write_bytes(current_structure)
    backup_structure.chmod(0o600)
    if backup_project.read_bytes() != current_project or backup_structure.read_bytes() != current_structure:
        sys.exit("WORK_BUDGET_V3_BACKUP_VERIFY_FAILED")
    try:
        temp_project.replace(project)
        temp_structure.replace(structure)
        php_lint(project)
        php_lint(structure)
        if project.read_bytes() != new_project or structure.read_bytes() != new_structure:
            raise RuntimeError("WORK_BUDGET_V3_POSTDEPLOY_CONTENT_MISMATCH")
    except BaseException:
        # Restore *both* originals if either replacement or validation fails.
        restore_project = create_candidate(project, current_project)
        restore_structure = create_candidate(structure, current_structure)
        restore_project.replace(project)
        restore_structure.replace(structure)
        print("WORK_BUDGET_V3_ROLLBACK_COMPLETED")
        raise
    print("WORK_BUDGET_V3_TAB_INSTALLED_BACKUPS_VERIFIED")
finally:
    temp_project.unlink(missing_ok=True)
    temp_structure.unlink(missing_ok=True)
