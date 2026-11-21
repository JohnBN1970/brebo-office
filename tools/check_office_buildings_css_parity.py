#!/usr/bin/env python3
"""Verify Buildings CSS staging has not altered its existing declarations."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
page = (root / "office-independent/public/office-buildings.php").read_text(encoding="utf-8")
css = (root / "office-independent/public/assets/css/office-buildings.css").read_text(encoding="utf-8")
match = re.search(r"<style>(.*?)</style>", page, re.S)
if not match:
    sys.exit("FAIL: Buildings inline CSS block missing")
if css.split("\n", 1)[1].strip() != match.group(1).strip():
    sys.exit("FAIL: Buildings staged stylesheet differs from existing CSS")
print("PASS: Buildings CSS exact parity")
