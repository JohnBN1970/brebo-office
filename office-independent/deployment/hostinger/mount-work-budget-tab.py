#!/usr/bin/env python3
"""Surgical Office-test-only project tab mount; preserve all existing project logic."""
import hashlib
import os
import pathlib
import re
import subprocess
import sys
import tempfile

if len(sys.argv) != 3:
    sys.exit("WORK_BUDGET_TAB_USAGE")
target, source = map(pathlib.Path, sys.argv[1:])
if not target.is_file() or target.is_symlink() or not source.is_file() or source.is_symlink():
    sys.exit("WORK_BUDGET_TAB_UNSAFE_FILE")
real = target.resolve()
if real.name != "office-project.php" or real.parent.name != "public" or "/office_calc_test/release-" not in str(real) or "/private/public/" not in str(real):
    sys.exit("WORK_BUDGET_TAB_UNSAFE_SCOPE")
if os.getenv("OFFICE_BASE_PATH") != "/office-test":
    sys.exit("WORK_BUDGET_TAB_SCOPE_DENIED")
active = target.read_bytes()
repo = source.read_bytes()
hash_now = hashlib.sha256(active).hexdigest()
if hash_now != os.getenv("OFFICE_WORK_BUDGET_TAB_EXPECTED_SHA256"):
    sys.exit("WORK_BUDGET_TAB_HASH_MISMATCH")

# Only accept an exact known existing finance-based panel, and preserve that panel's entire contents.
markers = (
    b"// Read-only Office procurement commitments: independent of invoicing and bank transactions.",
    b"// Financial dossier: explicit Finance ACL, independent of project read access.",
    b"OfficeMount::isTest()",
    b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED",
)
if not all(x in active for x in markers):
    sys.exit("WORK_BUDGET_TAB_INCOMPATIBLE_ACTIVE_RELEASE")
tab_old = b"'financieel' => 'Financieel'"
tab_new = b"'werkbegroting' => 'Werkbegroting', 'financieel' => 'Financieel'"
if active.count(tab_new) == 1 and b"$activeTab === 'werkbegroting'" in active:
    print("WORK_BUDGET_TAB_ALREADY_INSTALLED")
    sys.exit(0)
if active.count(tab_old) != 1 or active.count(tab_new) != 0:
    sys.exit("WORK_BUDGET_TAB_NAV_UNEXPECTED")
if repo.count(tab_new) != 1:
    sys.exit("WORK_BUDGET_TAB_REPO_NAV_UNEXPECTED")

# Source spans are copied from the reviewed branch (not reimplemented).
start = b"$workBudgetTabHtml = '<div id=\"project-werkbegroting\""
end = b"$financialSectionHtml .= $financeHtml;"
if repo.count(start) != 1 or repo.count(end) != 1:
    sys.exit("WORK_BUDGET_TAB_REPO_SPAN_INVALID")
a, b = repo.index(start), repo.index(end)
source_section = repo[a:b]
source_section = source_section.replace(b"// Native work budget panel:", b"// Native work budget panel:", 1)
if b"ProjectBudgetPanel::render($workBudgetView)" not in source_section:
    sys.exit("WORK_BUDGET_TAB_SOURCE_MISSING_PANEL")
# Actual live page already owns the panel inside the finance section.
live_start = b"// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only."
# Live Office-test uses a different Finance section terminator than the
# integrated source. Limit the moved panel to its own closing brace, followed
# by the next PHP statement; never consume Finance code after that boundary.
live_panel_stop = b"\n}"
if active.count(live_start) != 1:
    sys.exit("WORK_BUDGET_TAB_LIVE_PANEL_START_UNEXPECTED")
live_begin = active.index(live_start)
# The source panel's first-level closing brace is located by balanced PHP
# braces while skipping quoted strings/comments: use a token parser rather
# than brittle string matching or an unrelated Finance statement.
import json
php_probe = r"""<?php
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
probe = subprocess.run(["php", "-r", php_probe.replace("<?php", ""), str(target)],
                       capture_output=True, text=True)
if probe.returncode or not probe.stdout.strip().isdigit():
    sys.exit("WORK_BUDGET_TAB_LIVE_PANEL_BOUNDARY_NOT_FOUND")
live_finish = int(probe.stdout.strip())
if live_finish <= live_begin or live_finish > len(active):
    sys.exit("WORK_BUDGET_TAB_LIVE_PANEL_BOUNDARY_INVALID")
live_panel = active[live_begin:live_finish]
if live_panel.count(b"ProjectBudgetPanel::render") != 1:
    sys.exit("WORK_BUDGET_TAB_LIVE_PANEL_RENDER_UNEXPECTED")
if b"OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED" not in live_panel or b"is_array($financeContracts)" not in live_panel:
    sys.exit("WORK_BUDGET_TAB_LIVE_SECURITY_GUARDS_MISSING")
if b"$financeHtml .=" not in live_panel or b"ProjectBudgetPanel::render" not in live_panel:
    sys.exit("WORK_BUDGET_TAB_LIVE_PANEL_INCOMPATIBLE")
if b"$workBudgetTabHtml" in active:
    sys.exit("WORK_BUDGET_TAB_PARTIAL_CHANGE")
# Reuse EXACT active render logic, only move its output to the independent tab.
live_panel = live_panel.replace(b"$financeHtml .=", b"$workBudgetTabHtml .=")
if b"$financeHtml .=" in live_panel:
    sys.exit("WORK_BUDGET_TAB_OUTPUT_REWRITE_INCOMPLETE")
intro = b"$workBudgetTabHtml = '<div id=\"project-werkbegroting\"><h2>Werkbegroting</h2><p>Vastgestelde begroting, materialen, inkooporders en leveringen.</p>';\n"
panel = intro + live_panel + b"$workBudgetTabHtml .= '</div>';\n"
updated = active[:live_begin] + panel + active[live_finish:]
updated = updated.replace(tab_old, tab_new, 1)

# Only add approved tab output at exact legacy terminal expression, without changing any other view.
legacy = b"($activeTab === 'financieel' ? $financialSectionHtml : '')"
replacement = b"($activeTab === 'werkbegroting' ? $workBudgetTabHtml : '') . " + legacy
if updated.count(legacy) != 1 or updated.count(replacement) != 0:
    sys.exit("WORK_BUDGET_TAB_RENDER_ANCHOR_INVALID")
updated = updated.replace(legacy, replacement, 1)
if updated.count(b"$activeTab === 'werkbegroting'") != 1:
    sys.exit("WORK_BUDGET_TAB_RENDER_NOT_UNIQUE")
# Preserve user data and original file on disk; fail closed if backup already exists.
backup = target.with_name(target.name + ".pre-work-budget-tab.bak")
if backup.exists():
    sys.exit("WORK_BUDGET_TAB_BACKUP_EXISTS")
with tempfile.NamedTemporaryFile(dir=target.parent, prefix=".work-budget-tab-", delete=False) as f:
    temp = pathlib.Path(f.name)
    f.write(updated)
try:
    temp.chmod(target.stat().st_mode & 0o777)
    subprocess.run(["php", "-l", str(temp)], check=True, stdout=subprocess.DEVNULL)
    backup.write_bytes(active)
    backup.chmod(0o600)
    temp.replace(target)
    if target.read_bytes() != updated:
        target.write_bytes(active)
        sys.exit("WORK_BUDGET_TAB_INSTALL_VERIFY_FAILED")
    print("WORK_BUDGET_TAB_INSTALLED_WITH_BACKUP")
finally:
    temp.unlink(missing_ok=True)
