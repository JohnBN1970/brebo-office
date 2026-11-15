#!/usr/bin/env python3
"""End-to-end CLI regression for the Office-test release reconciliation allowlists."""
import hashlib
import subprocess
import sys
import tempfile
from pathlib import Path

RECONCILER = Path(__file__).with_name("prepare_office_test_release.py")
CALC = Path("src/Calculation/OfficeCalcCrmDraftLineRepository.php")
ONSITE = Path("src/Inzet/OfficeOnSiteApprovalService.php")


def git_blob_id(data: bytes) -> str:
    return hashlib.sha1(b"blob " + str(len(data)).encode() + b"\0" + data).hexdigest()


def exercise(relative: Path, allowlist_option: str, with_baseline: bool) -> None:
    for allow in (True, False):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            active, incoming, baseline, candidate = (root / name for name in ("active", "incoming", "baseline", "candidate"))
            for directory in (active, incoming, baseline):
                (directory / relative).parent.mkdir(parents=True, exist_ok=True)
            old = b"runtime-git-version\n"
            new = b"current-develop-version\n"
            (active / relative).write_bytes(old)
            (incoming / relative).write_bytes(new)
            if with_baseline:
                (baseline / relative).write_bytes(b"common-base-version\n")
            hashes = root / "trusted-blobs.txt"
            hashes.write_text(git_blob_id(old) + "\n" if allow else "0" * 40 + "\n")
            args = [
                sys.executable, str(RECONCILER),
                "--active", str(active), "--candidate", str(candidate),
                "--incoming", str(incoming), "--baseline", str(baseline),
                allowlist_option, str(hashes),
            ]
            result = subprocess.run(args, text=True, capture_output=True, check=False)
            if allow:
                assert result.returncode == 0, (relative, result.stdout, result.stderr)
                assert (candidate / relative).read_bytes() == new
                assert (candidate / ".development-runtime-backups" / relative).read_bytes() == old
            else:
                expected = "RELEASE_MERGE_CONFLICT" if with_baseline else "UNTRACKED_RUNTIME_COLLISION"
                assert result.returncode != 0, (relative, result.stdout, result.stderr)
                assert expected in result.stderr, (relative, result.stderr)
            # Both success and failure must leave the active runtime unchanged.
            assert (active / relative).read_bytes() == old


exercise(CALC, "--known-calc-blobs", with_baseline=False)
exercise(ONSITE, "--known-ancestor-blobs", with_baseline=True)
print("OFFICE_TEST_RUNTIME_CLI_RECONCILIATION=PASS")
