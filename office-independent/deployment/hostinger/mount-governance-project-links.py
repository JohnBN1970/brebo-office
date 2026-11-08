#!/usr/bin/env python3
"""Guarded additive project detail navigation; never replace active project page."""
import os
import pathlib
import subprocess
import sys
import tempfile
from datetime import datetime, timezone

if os.environ.get("OFFICE_BASE_PATH") != "/office-test" or os.environ.get("OFFICE_GOVERNANCE_PROJECT_LINKS") != "explicit":
    sys.exit("PROJECT_LINKS_SCOPE_DENIED")
if len(sys.argv) != 2:
    sys.exit("usage: mount-governance-project-links.py <active-project.php>")
target = pathlib.Path(sys.argv[1])
if not target.is_file() or target.is_symlink():
    sys.exit("PROJECT_LINKS_TARGET_INVALID")
old = target.read_bytes()
anchor = b"$moduleHtml .= $financeHtml;"
marker = b"<!-- governance-project-links-v1 -->"
if marker in old:
    print("PROJECT_LINKS_ALREADY_PRESENT")
    sys.exit(0)
if old.count(anchor) != 1 or b"awardedProjectCommitment" not in old or b"$projectId" not in old:
    sys.exit("PROJECT_LINKS_LIVE_ANCHOR_MISMATCH")
addition = b"""// Project governance links use the same canonical project ID; module ACL remains enforced.
$governanceProjectId = (int) $projectId;
if ($governanceProjectId > 0 && getenv('OFFICE_TEST_GOVERNANCE_ENABLED') === 'enabled') {
    $governanceQuery = '?project_id=' . rawurlencode((string) $governanceProjectId);
    $financeHtml .= '<section class="module"><!-- governance-project-links-v1 --><h3>Projectbeheersing</h3><p>'
        . '<a href="/office-test/kwaliteit' . $governanceQuery . '">Kwaliteit</a> &middot; '
        . '<a href="/office-test/risicos' . $governanceQuery . '">Risico\\'s</a> &middot; '
        . '<a href="/office-test/inkoop' . $governanceQuery . '">Inkoop</a>'
        . '</p><p>Projectgebonden dossiers; toegang wordt per onderdeel gecontroleerd.</p></section>';
}
"""
new = old.replace(anchor, addition + anchor, 1)
backup = target.with_name(target.name + ".backup-before-project-links-" + datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S"))
with backup.open("xb") as f:
    f.write(old)
os.chmod(backup, 0o600)
fd, tmp = tempfile.mkstemp(prefix=".governance-project-links-", suffix=".php", dir=target.parent)
try:
    with os.fdopen(fd, "wb") as f:
        f.write(new)
    os.chmod(tmp, target.stat().st_mode & 0o777)
    lint = subprocess.run(["php", "-l", tmp], capture_output=True, text=True)
    if lint.returncode:
        print(lint.stderr.strip()[:1000])
        sys.exit("PROJECT_LINKS_CANDIDATE_PHP_INVALID")
    if target.read_bytes() != old:
        sys.exit("PROJECT_LINKS_CONCURRENT_CHANGE_ABORT")
    os.replace(tmp, target)
    if subprocess.run(["php", "-l", str(target)], capture_output=True).returncode:
        target.write_bytes(old)
        sys.exit("PROJECT_LINKS_RESTORED_AFTER_LINT_FAILURE")
    print("PROJECT_LINKS_MOUNTED_WITH_BACKUP")
finally:
    if os.path.exists(tmp):
        os.unlink(tmp)
