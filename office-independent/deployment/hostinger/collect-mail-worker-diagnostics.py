#!/usr/bin/env python3
"""Collect allowlisted systemd states without exposing logs or credentials."""
import json
import os
import subprocess
import tempfile
import time
from pathlib import Path

ROOT = Path("/run/brebo-office-test/diagnostics")
UNITS = ("brebo-office-test-mail-import.service",
         "brebo-office-test-mail-attachments.service")
STATES = {"active", "inactive", "failed", "activating", "deactivating"}

def main():
    ROOT.mkdir(mode=0o755, parents=True, exist_ok=True)
    states = {}
    for unit in UNITS:
        try:
            result = subprocess.run(
                ["/usr/bin/systemctl", "show", "--property=ActiveState",
                 "--value", unit], capture_output=True, text=True, timeout=10)
            state = result.stdout.strip()
            states[unit] = state if result.returncode == 0 and state in STATES else "unknown"
        except (OSError, subprocess.TimeoutExpired):
            states[unit] = "unknown"
    fd, path = tempfile.mkstemp(dir=ROOT, prefix=".mail-workers-")
    try:
        with os.fdopen(fd, "w") as stream:
            json.dump({"checked_at": int(time.time()), "units": states}, stream)
            stream.flush()
            os.fsync(stream.fileno())
            os.fchmod(stream.fileno(), 0o644)
        os.replace(path, ROOT / "mail-workers.json")
    finally:
        if os.path.exists(path):
            os.unlink(path)

if __name__ == "__main__":
    main()
