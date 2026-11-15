#!/usr/bin/env python3
"""Merge repository changes into a new private release; never modify active code."""
from pathlib import Path
import argparse
import shutil
import subprocess

AREAS = ('src', 'public', 'schema', 'deployment', 'tests')

def reconcile(active, candidate, incoming, baseline):
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
                    raise RuntimeError(f'RELEASE_MERGE_CONFLICT={relative}')
                target.write_bytes(merge.stdout)
                count['merged'] += 1
            else:
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
    a = parser.parse_args()
    print('RELEASE_RECONCILIATION=' + str(reconcile(a.active,a.candidate,a.incoming,a.baseline)))
