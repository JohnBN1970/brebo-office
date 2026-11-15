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


def exercise_multi_hunk_calc_conflict() -> None:
    """Git merge-file returns 2+ for multiple hunks; trusted Calc paths still reconcile."""
    relative = Path("src/Calculation/OfficeCalcCrmTransferService.php")
    with tempfile.TemporaryDirectory() as temp:
        root = Path(temp)
        active, incoming, baseline, candidate = (root / name for name in ("active", "incoming", "baseline", "candidate"))
        for directory in (active, incoming, baseline):
            (directory / relative).parent.mkdir(parents=True, exist_ok=True)
        source = b"one\nstable-a\nstable-b\nstable-c\ntwo\nstable-d\nstable-e\nstable-f\nthree\n"
        old = source.replace(b"one\n", b"old-one\n").replace(b"three\n", b"old-three\n")
        new = source.replace(b"one\n", b"new-one\n").replace(b"three\n", b"new-three\n")
        (active / relative).write_bytes(old)
        (incoming / relative).write_bytes(new)
        (baseline / relative).write_bytes(source)
        # Confirm this is a multi-conflict merge, not a single-hunk fixture.
        check = subprocess.run(
            ["git", "merge-file", "-p", str(active / relative), str(baseline / relative), str(incoming / relative)],
            capture_output=True, check=False,
        )
        assert 2 <= check.returncode <= 127, check.returncode
        hashes = root / "trusted-path-blobs.txt"
        hashes.write_text(str(relative) + " " + git_blob_id(old) + "\n")
        result = subprocess.run([
            sys.executable, str(RECONCILER),
            "--active", str(active), "--candidate", str(candidate),
            "--incoming", str(incoming), "--baseline", str(baseline),
            "--known-calc-path-blobs", str(hashes),
        ], text=True, capture_output=True, check=False)
        assert result.returncode == 0, (result.stdout, result.stderr)
        assert (candidate / relative).read_bytes() == new
        assert (candidate / ".development-runtime-backups" / relative).read_bytes() == old
        assert (active / relative).read_bytes() == old
        # Unrecognized active revisions are not overwritten even on multi-hunk conflicts.
        blocked = root / "blocked"
        hashes.write_text(str(relative) + " " + "0" * 40 + "\n")
        failure = subprocess.run([
            sys.executable, str(RECONCILER),
            "--active", str(active), "--candidate", str(blocked),
            "--incoming", str(incoming), "--baseline", str(baseline),
            "--known-calc-path-blobs", str(hashes),
        ], text=True, capture_output=True, check=False)
        assert failure.returncode != 0
        assert "RELEASE_MERGE_CONFLICT=" in failure.stderr


exercise_multi_hunk_calc_conflict()
print("OFFICE_TEST_RUNTIME_CLI_RECONCILIATION=PASS")
