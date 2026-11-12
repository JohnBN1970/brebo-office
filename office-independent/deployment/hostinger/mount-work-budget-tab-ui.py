#!/usr/bin/env python3
"""Office-test-only, guarded presentation patch for the already-installed V3 tab."""
import hashlib
import os
import pathlib
import subprocess
import sys
import tempfile

if len(sys.argv) != 2:
    sys.exit("WORK_BUDGET_UI_USAGE")
target = pathlib.Path(sys.argv[1])
if not target.is_file() or target.is_symlink():
    sys.exit("WORK_BUDGET_UI_UNSAFE_TARGET")
real = target.resolve()
if (
    real.name != "office-project.php"
    or real.parent.name != "public"
    or "/office_calc_test/release-" not in str(real)
    or "/private/public/" not in str(real)
    or os.getenv("OFFICE_BASE_PATH") != "/office-test"
):
    sys.exit("WORK_BUDGET_UI_SCOPE_DENIED")
original = target.read_bytes()
if hashlib.sha256(original).hexdigest() != os.getenv("OFFICE_WORK_BUDGET_UI_EXPECTED_SHA256"):
    sys.exit("WORK_BUDGET_UI_SOURCE_CHANGED")
old = b"""$workBudgetTabHtml = '<div id="project-werkbegroting"><h2>Werkbegroting</h2><p>Vastgestelde begroting, materialen, inkooporders en leveringen.</p>';"""
if original.count(old) != 1 or original.count(b"'werkbegroting' => (string) $workBudgetTabHtml,") != 1:
    sys.exit("WORK_BUDGET_UI_EXPECTED_V3_TAB_NOT_FOUND")
css = (
    "#project-werkbegroting{max-width:1200px;margin:0 auto;padding:24px 0 40px;color:#203247}"
    "#project-werkbegroting>.office-work-budget{display:block}"
    "#project-werkbegroting .office-work-budget__kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:20px 0 28px}"
    "#project-werkbegroting .office-work-budget__kpi{display:flex;flex-direction:column;gap:10px;background:#fff;border:1px solid #dce5ed;border-radius:12px;padding:18px;min-width:0}"
    "#project-werkbegroting .office-work-budget__kpi strong{font-size:13px;color:#496278;line-height:1.45}"
    "#project-werkbegroting .office-work-budget__kpi span{font-size:19px;font-weight:700;overflow-wrap:anywhere}"
    "#project-werkbegroting .office-work-budget h2{margin:0 0 8px;font-size:24px}"
    "#project-werkbegroting .office-work-budget h3{margin:28px 0 14px;font-size:17px}"
    "#project-werkbegroting .office-work-budget dl{display:grid;grid-template-columns:minmax(140px,220px) minmax(0,1fr);gap:8px 18px;background:#fff;border:1px solid #dce5ed;border-radius:12px;padding:18px;margin:0}"
    "#project-werkbegroting .office-work-budget dt,#project-werkbegroting .office-work-budget dd{margin:0;padding:7px 0;overflow-wrap:anywhere}"
    "#project-werkbegroting .office-work-budget dt{font-weight:650;color:#425c73}"
    "#project-werkbegroting .office-work-budget dd{color:#203247}"
    "#project-werkbegroting .office-work-budget p{line-height:1.65}"
    "#project-werkbegroting .office-work-budget table{width:100%;border-collapse:collapse;background:#fff}"
    "#project-werkbegroting .office-work-budget th,#project-werkbegroting .office-work-budget td{text-align:left;padding:12px;border-bottom:1px solid #e1e7ef}"
    "@media(max-width:600px){#project-werkbegroting{padding:14px 0 24px}#project-werkbegroting .office-work-budget dl{grid-template-columns:1fr;gap:0}#project-werkbegroting .office-work-budget dd{padding-bottom:12px}}"
)
new = (
    b"$workBudgetTabHtml = '<div id=\"project-werkbegroting\"><style>"
    + css.encode("ascii")
    + b"</style><p class=\"work-budget-intro\">Vastgestelde begroting, materialen, inkooporders en leveringen.</p>';"
)
updated = original.replace(old, new, 1)
backup = target.with_name(target.name + ".pre-work-budget-ui.bak")
if backup.exists():
    if original.count(new) == 1:
        print("WORK_BUDGET_UI_ALREADY_INSTALLED")
        sys.exit(0)
    sys.exit("WORK_BUDGET_UI_BACKUP_EXISTS")
with tempfile.NamedTemporaryFile(dir=target.parent, prefix=".work-budget-ui-", delete=False) as f:
    temp = pathlib.Path(f.name)
    f.write(updated)
try:
    temp.chmod(target.stat().st_mode & 0o777)
    subprocess.run(["php", "-l", str(temp)], check=True, stdout=subprocess.DEVNULL)
    backup.write_bytes(original)
    backup.chmod(0o600)
    if backup.read_bytes() != original:
        sys.exit("WORK_BUDGET_UI_BACKUP_INVALID")
    temp.replace(target)
    if target.read_bytes() != updated:
        restore = target.with_name(target.name + ".work-budget-ui-restore")
        restore.write_bytes(original)
        restore.replace(target)
        sys.exit("WORK_BUDGET_UI_VERIFY_FAILED_ROLLED_BACK")
    print("WORK_BUDGET_UI_INSTALLED")
finally:
    temp.unlink(missing_ok=True)
