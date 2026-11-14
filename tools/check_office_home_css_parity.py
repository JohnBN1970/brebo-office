#!/usr/bin/env python3
"""Verify staged home stylesheet exactly matches existing inline CSS."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
page = (root / "office-independent/public/office-home.php").read_text(encoding="utf-8")
css = (root / "office-independent/public/assets/css/office-home.css").read_text(encoding="utf-8")
match = re.search(r"<style>(.*?)</style>", page, re.S)
if not match:
    sys.exit("FAIL: Office home inline CSS not found; update migration contract")
staged = css.split("\n", 1)[1].strip()
if match.group(1).strip() != staged:
    sys.exit("FAIL: staged Office home CSS differs from live inline styles")
print("PASS: Office home staged CSS matches source exactly")
