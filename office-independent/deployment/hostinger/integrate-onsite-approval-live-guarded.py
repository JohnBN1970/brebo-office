#!/usr/bin/env python3
"""Guarded Office-test OnSite integration into verified live release.

Reconciles only two verified historical page versions, preserving live-only
lines in the OnSite page. Stages, lints, snapshots and rolls back on failure.
No database changes, no feature-flag activation, no output of live source.
"""
import argparse
import difflib
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

EXPECTED_LIVE = {
    "public/office-projects.php": "46baab1ecb1ea1d095e2f518ed5c9b1fcee2fa67abca2392046d738f61ab92f9",
    "public/office-onsite-presence-review.php": "cbdd67e3df25efa7657617896c5685e896d2dbd4e02f5b825846bf841600126f",
}
EXPECTED_STAGE = {
    "public/office-projects.php": "1d3cb4dab9291737a7ceef4fb0d7a1618520016d056b77f61c3e0344bdc6bdd4",
    "public/office-onsite-presence-review.php": "6938d39794701c09c03f768f38ab6d4665fb54057248726f78ba8e0c9e6c2fb4",
}
EXPECTED_OPS = {
    "public/office-projects.php": [
        ("insert", 81, 81, 81, 82),
        ("replace", 119, 120, 120, 121),
        ("insert", 644, 644, 645, 740),
    ],
    "public/office-onsite-presence-review.php": [
        ("replace", 2, 3, 2, 3),
        ("replace", 8, 10, 8, 17),
        ("replace", 22, 23, 29, 30),
        ("insert", 41, 41, 48, 62),
        ("delete", 47, 49, 68, 68),
        ("delete", 56, 57, 75, 75),
        ("insert", 58, 58, 76, 87),
    ],
}
SERVICES = (
    "src/Inzet/OfficeOnSiteApprovalService.php",
    "src/Inzet/OfficeApprovedHoursWeekRepository.php",
)
DEPENDENCIES = (
    "src/Project/OfficeProjectPortfolioView.php",
    "src/Project/OfficeProjectPageStructure.php",
    "src/Project/OfficeProjectPortfolioPlannedHours.php",
    "src/Project/OfficeProjectPortfolioTotals.php",
    "src/Finance/OfficeFinanceAccess.php",
)
MARKERS = {
    "public/office-projects.php": ("OfficeProjectPortfolioGantt", "approved_week_seconds",
                                   "OfficeProjectPortfolioView"),
    "public/office-onsite-presence-review.php": (
        "OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED", "OfficeOnSiteApprovalService", "hash_equals("),
}

def ensure(ok, message):
    if not ok:
        raise RuntimeError(message)

def digest(data):
    return hashlib.sha256(data).hexdigest()

def safe_file(path):
    ensure(path.is_file() and not path.is_symlink(), "INVALID_FILE:" + str(path.name))
    return path.read_bytes()

def operations(a, b):
    return [op for op in difflib.SequenceMatcher(
        None, a.splitlines(), b.splitlines(), autojunk=False
    ).get_opcodes() if op[0] != "equal"]

def merge_page(rel, live_bytes, stage_bytes):
    ensure(digest(live_bytes) == EXPECTED_LIVE[rel], "LIVE_VERSION_CHANGED:" + rel)
    ensure(digest(stage_bytes) == EXPECTED_STAGE[rel], "CANDIDATE_VERSION_CHANGED:" + rel)
    old = live_bytes.decode("utf-8")
    new = stage_bytes.decode("utf-8")
    ops = operations(old, new)
    ensure(ops == EXPECTED_OPS[rel], "UNEXPECTED_DIFF_STRUCTURE:" + rel)
    if rel == "public/office-projects.php":
        result = new
    else:
        # Preserve three live-only lines which Git does not know about.
        old_lines = old.splitlines(keepends=True)
        merged_lines = new.splitlines(keepends=True)
        for tag, i1, i2, j1, j2 in reversed(ops):
            if tag == "delete":
                merged_lines[j1:j1] = old_lines[i1:i2]
        result = "".join(merged_lines)
        for tag, i1, i2, _, _ in ops:
            if tag == "delete":
                for original_line in old_lines[i1:i2]:
                    ensure(original_line in result, "LIVE_ONLY_LINE_NOT_PRESERVED:" + rel)
    for marker in MARKERS[rel]:
        ensure(marker in result, "INTEGRATION_MARKER_MISSING:" + rel)
    ensure("OfficeProjectPortfolioGantt" in result if rel.endswith("office-projects.php") else True,
           "GANTT_NOT_PRESERVED")
    return result.encode("utf-8")

def lint(path):
    proc = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True, check=False)
    ensure(proc.returncode == 0, "PHP_LINT_FAILED:" + path.name)
    print("PHP_LINT_OK:" + path.name)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--active", type=Path, required=True)
    parser.add_argument("--stage", type=Path, required=True)
    parser.add_argument("--backup", type=Path, required=True)
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    active = args.active
    stage = args.stage
    ensure(active.is_dir() and not active.is_symlink(), "ACTIVE_RELEASE_INVALID")
    ensure(stage.is_dir() and not stage.is_symlink(), "STAGE_INVALID")
    ensure("/office_calc_test/release-" in str(active) and str(active).endswith("/private"),
           "OFFICE_TEST_SCOPE_REJECTED")
    ensure(str(args.backup).startswith(str(Path.home() / "office_calc_test" / ".onsite-backups" / "")),
           "BACKUP_SCOPE_REJECTED")
    ensure(not args.backup.exists(), "BACKUP_ALREADY_EXISTS")
    staged = {}
    for rel in list(EXPECTED_LIVE) + list(SERVICES) + list(DEPENDENCIES):
        stage_file = stage / rel
        staged[rel] = safe_file(stage_file)
        target = active / rel
        ensure(target.parent.is_dir() and not target.parent.is_symlink(), "TARGET_DIR_INVALID:" + rel)
        ensure(not target.is_symlink(), "TARGET_SYMLINK_BLOCKED:" + rel)
        if rel in EXPECTED_LIVE:
            live = safe_file(target)
            staged[rel] = merge_page(rel, live, staged[rel])
            print("SAFE_MERGE_PREPARED:" + rel)
        elif target.exists():
            existing = safe_file(target)
            if rel in SERVICES or rel == "src/Project/OfficeProjectPortfolioView.php":
                ensure(existing == staged[rel], "EXISTING_MODULE_DIFFERS:" + rel)
            else:
                # Keep independently deployed modules; do not overwrite local changes.
                staged[rel] = existing
            print("EXISTING_DEPENDENCY_PRESERVED:" + rel)
        else:
            print("MISSING_MODULE_PREPARED:" + rel)
    # Stage exactly the files to install and lint before touching active content.
    with tempfile.TemporaryDirectory(prefix="onsite-validated-", dir=str(stage)) as directory:
        temp_dir = Path(directory)
        for rel, data in staged.items():
            p = temp_dir / rel
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_bytes(data)
            lint(p)
        if not args.apply:
            print("ON_SITE_SAFE_MERGE_DRY_RUN_OK")
            return 0

        # Preserve every existing file before performing any replacement.
        args.backup.mkdir(parents=True, mode=0o700)
        originals = {}
        for rel in staged:
            target = active / rel
            originals[rel] = safe_file(target) if target.exists() else None
            if originals[rel] is not None:
                copy = args.backup / rel
                copy.parent.mkdir(parents=True, exist_ok=True)
                copy.write_bytes(originals[rel])
                copy.chmod(0o600)

        changed = []
        try:
            # Install dependencies before controllers, so includes resolve immediately.
            order = list(SERVICES) + list(DEPENDENCIES) + list(EXPECTED_LIVE)
            for rel in order:
                target = active / rel
                data = staged[rel]
                if originals[rel] == data:
                    continue
                ensure((safe_file(target) if target.exists() else None) == originals[rel],
                       "CONCURRENT_CHANGE_BLOCKED:" + rel)
                with tempfile.NamedTemporaryFile(dir=str(target.parent), prefix=".onsite-", delete=False) as fh:
                    temp_path = Path(fh.name)
                    fh.write(data)
                try:
                    temp_path.chmod(0o644)
                    os.replace(temp_path, target)
                finally:
                    if temp_path.exists():
                        temp_path.unlink()
                changed.append(rel)
                lint(target)
                print("INTEGRATED:" + rel)
        except Exception:
            # Roll back every completed replacement, including newly installed modules.
            for rel in reversed(changed):
                target = active / rel
                old = originals[rel]
                if old is None:
                    target.unlink(missing_ok=True)
                else:
                    with tempfile.NamedTemporaryFile(dir=str(target.parent), prefix=".onsite-rollback-", delete=False) as fh:
                        tmp = Path(fh.name)
                        fh.write(old)
                    try:
                        tmp.chmod(0o644)
                        os.replace(tmp, target)
                    finally:
                        if tmp.exists():
                            tmp.unlink()
            print("ON_SITE_INTEGRATION_ROLLED_BACK")
            raise
        print("ON_SITE_GUARDED_INTEGRATION_COMPLETE")
        print("BACKUPS_SAVED_OUTSIDE_ACTIVE_RELEASE")

if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:
        # Only internal status labels, never print source or credentials.
        print("ON_SITE_INTEGRATION_BLOCKED:" + str(exc), file=sys.stderr)
        sys.exit(1)
