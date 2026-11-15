#!/usr/bin/env python3
"""Regression: verified Calc runtime may be adopted; unknown changes must fail."""
import importlib.util
import tempfile
import subprocess
import sys
from pathlib import Path

module_path = Path(__file__).with_name("prepare_office_test_release.py")
spec = importlib.util.spec_from_file_location("release_reconcile", module_path)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

relative = module.KNOWN_CALC_RUNTIME_FILE
with tempfile.TemporaryDirectory() as temp:
    root = Path(temp)
    active, incoming, baseline = (root / name for name in ("active", "incoming", "baseline"))
    for base in (active, incoming, baseline):
        (base / relative).parent.mkdir(parents=True)
    (active / relative).write_bytes(b"old-git-version")
    (incoming / relative).write_bytes(b"new-version")
    verified = module.git_blob_id(b"old-git-version")
    result = module.reconcile(active, root / "accepted", incoming, baseline,
                              known_calc_blobs=frozenset({verified}))
    assert result["updated"] == 1
    assert (root / "accepted" / relative).read_bytes() == b"new-version"
    assert (root / "accepted" / ".development-runtime-backups" / relative).read_bytes() == b"old-git-version"
    allowlist = root / "calc-blobs.txt"
    allowlist.write_text(verified + "\n")
    cli = subprocess.run([
        sys.executable, str(module_path),
        "--active", str(active), "--candidate", str(root / "cli-accepted"),
        "--incoming", str(incoming), "--baseline", str(baseline),
        "--known-calc-blobs", str(allowlist),
    ], capture_output=True, text=True)
    assert cli.returncode == 0, cli.stderr
    assert "RELEASE_ADOPTED_KNOWN_CALC_GIT_ANCESTOR=" in cli.stdout
    assert (root / "cli-accepted" / relative).read_bytes() == b"new-version"
    try:
        module.reconcile(active, root / "blocked", incoming, baseline,
                         known_calc_blobs=frozenset({"0" * 40}))
    except RuntimeError as error:
        assert str(error).startswith("UNTRACKED_RUNTIME_COLLISION=")
    else:
        raise AssertionError("Unknown runtime customization was overwritten")
print("CALC_RUNTIME_RECONCILIATION=PASS")
