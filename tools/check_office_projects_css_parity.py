#!/usr/bin/env python3
"""Exact source-to-staged-CSS parity checks for Projecten, Gantt and Kanban."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
pairs = [
    ("office-independent/public/office-projects.php", "office-projects.css"),
    ("office-independent/src/Project/OfficeProjectPortfolioGantt.php", "office-project-gantt.css"),
    ("office-independent/src/Project/OfficeProjectPortfolioKanban.php", "office-project-kanban.css"),
]
for source, name in pairs:
    source_text = (root / source).read_text(encoding="utf-8")
    css = (root / "office-independent/public/assets/css" / name).read_text(encoding="utf-8")
    blocks = re.findall(r"<style\b[^>]*>(.*?)</style\s*>", source_text, flags=re.I | re.S)
    if len(blocks) != 1:
        sys.exit(f"FAIL {source}: expected one CSS block, found {len(blocks)}")
    if css.split("\n", 1)[1].strip() != blocks[0].strip():
        sys.exit(f"FAIL {source}: staged CSS differs")
    print(f"PASS {name}: exact parity")
