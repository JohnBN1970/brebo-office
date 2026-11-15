#!/usr/bin/env python3
"""Merge repository changes into a new private release; never modify active code."""
from pathlib import Path
import argparse
import hashlib
import shutil
import subprocess

AREAS = ('src', 'public', 'schema', 'deployment', 'tests')
KNOWN_ANCESTOR_REPLACEMENT = Path('src/Inzet/OfficeOnSiteApprovalService.php')
KNOWN_CALC_RUNTIME_FILE = Path('src/Calculation/OfficeCalcCrmDraftLineRepository.php')

def git_blob_id(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def reconcile(active, candidate, incoming, baseline, known_ancestor_blobs=frozenset(), known_calc_blobs=frozenset()):
    if candidate.exists():
        raise RuntimeError('Candidate already exists')
    shutil.copytree(active, candidate, symlinks=True)
    count = {'updated': 0, 'preserved': 0, 'merged': 0}
    for area in AREAS:
        for new in sorted((incoming / area).rglob('*')):
            if not new.is_file():
                continue
            relative = new.relative_to(incoming)
            target = candidate / relative
            old = baseline / relative
            if new.is_symlink() or target.is_symlink() or any(p.is_symlink() for p in target.parents if p != candidate.parent):
                raise RuntimeError(f'Symlink not allowed: {relative}')
            target.parent.mkdir(parents=True, exist_ok=True)
            incoming_bytes = new.read_bytes()
            if not target.exists() or target.read_bytes() == incoming_bytes:
                target.write_bytes(incoming_bytes)
                count['updated'] += 1
            elif old.is_file() and incoming_bytes == old.read_bytes():
                count['preserved'] += 1
            elif old.is_file() and target.read_bytes() == old.read_bytes():
                target.write_bytes(incoming_bytes)
                count['updated'] += 1
            elif old.is_file():
                merge = subprocess.run(['git', 'merge-file', '-p', str(target), str(old), str(new)], capture_output=True)
                if merge.returncode:
                    # Narrowly scoped recovery: an active file whose exact Git blob
                    # exists in develop's ancestry is repository-managed, not an
                    # untracked server customization. Preserve a private backup.
                    if merge.returncode == 1 and relative == KNOWN_ANCESTOR_REPLACEMENT and git_blob_id(target.read_bytes()) in known_ancestor_blobs:
                        backup = candidate / '.development-runtime-backups' / relative
                        backup.parent.mkdir(parents=True, exist_ok=True)
                        shutil.copy2(target, backup)
                        target.write_bytes(incoming_bytes)
                        count['updated'] += 1
                        print(f'RELEASE_RECONCILED_KNOWN_GIT_ANCESTOR={relative}')
                        continue
                    raise RuntimeError(f'RELEASE_MERGE_CONFLICT={relative}')
                target.write_bytes(merge.stdout)
                count['merged'] += 1
            else:
                # Only a byte-for-byte Git ancestor may replace an unmanaged Calc file.
                if relative == KNOWN_CALC_RUNTIME_FILE and git_blob_id(target.read_bytes()) in known_calc_blobs:
                    backup = candidate / '.development-runtime-backups' / relative
                    backup.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(target, backup)
                    target.write_bytes(incoming_bytes)
                    count['updated'] += 1
                    print(f'RELEASE_ADOPTED_KNOWN_CALC_GIT_ANCESTOR={relative}')
                    continue
                raise RuntimeError(f'UNTRACKED_RUNTIME_COLLISION={relative}')
    # Keep runtime-only dependencies; retire removed managed files only after explicit review.
    previous = candidate / '.development-source'
    if previous.exists():
        shutil.rmtree(previous)
    previous.mkdir()
    for area in AREAS:
        if (incoming / area).exists():
            shutil.copytree(incoming / area, previous / area)
    return count

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    for key in ('active','candidate','incoming','baseline'):
        parser.add_argument('--'+key, type=Path, required=True)
    parser.add_argument('--known-ancestor-blobs', type=Path)
    parser.add_argument('--known-calc-blobs', type=Path)
    a = parser.parse_args()
    known = frozenset(a.known_ancestor_blobs.read_text().splitlines()) if a.known_ancestor_blobs else frozenset()
    print('RELEASE_RECONCILIATION=' + str(reconcile(a.active,a.candidate,a.incoming,a.baseline, known)))
