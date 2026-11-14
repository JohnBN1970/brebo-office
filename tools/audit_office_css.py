#!/usr/bin/env python3
"""Inventory embedded CSS without changing application files."""
import argparse
import json
import re
from collections import Counter
from pathlib import Path

EXTENSIONS = {".php", ".html", ".htm", ".twig", ".js", ".jsx", ".ts", ".tsx", ".vue"}
SKIP = {".git", "vendor", "node_modules", "dist", "build", "coverage"}
STYLE_BLOCK = re.compile(r"<style\\b[^>]*>(.*?)</style\\s*>", re.I | re.S)
STYLE_ATTR = re.compile(r"(?<![\\w-])style\\s*=\\s*([\\"'])(.*?)\\1", re.I | re.S)
CSS_LINK = re.compile(r"<link\\b[^>]*\\bhref\\s*=\\s*([\\"'])([^\\"']+\\.css(?:\\?[^\\"']*)?)\\1", re.I | re.S)

def inventory(root):
    records = []
    for path in sorted(root.rglob("*")):
        if not path.is_file() or path.suffix.lower() not in EXTENSIONS:
            continue
        if any(part in SKIP for part in path.relative_to(root).parts):
            continue
        try:
            content = path.read_text(encoding="utf-8")
        except (UnicodeError, OSError):
            continue
        blocks = list(STYLE_BLOCK.finditer(content))
        attrs = list(STYLE_ATTR.finditer(content))
        links = list(CSS_LINK.finditer(content))
        if not (blocks or attrs or links):
            continue
        records.append({
            "path": path.relative_to(root).as_posix(),
            "style_blocks": len(blocks),
            "style_block_lines": sum(max(1, m.group(1).count("\n") + 1) for m in blocks),
            "style_attributes": len(attrs),
            "stylesheet_links": len(links),
            "style_block_start_lines": [content.count("\n", 0, m.start()) + 1 for m in blocks],
            "style_attribute_lines": [content.count("\n", 0, m.start()) + 1 for m in attrs],
        })
    return records

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=".")
    parser.add_argument("--json", dest="json_path")
    parser.add_argument("--markdown", dest="md_path")
    args = parser.parse_args()
    root = Path(args.root).resolve()
    rows = inventory(root)
    totals = {key: sum(r[key] for r in rows) for key in
              ("style_blocks", "style_block_lines", "style_attributes", "stylesheet_links")}
    report = {"root": str(root), "files_with_matches": len(rows), "totals": totals, "files": rows}
    md = ["# CSS inventory", "", "Static source scan; dynamic/generated markup may not be detected.",
          "Counts are occurrences, not unique CSS declarations.", "",
          f"Files with matches: **{len(rows)}**", "",
          "| File | Style blocks | Block lines | Style attributes | CSS links |",
          "|---|---:|---:|---:|---:|"]
    for row in sorted(rows, key=lambda x: (-x["style_attributes"] - x["style_blocks"], x["path"])):
        md.append(f'| \`{row["path"]}\` | {row["style_blocks"]} | {row["style_block_lines"]} | {row["style_attributes"]} | {row["stylesheet_links"]} |')
    md.extend(["", "## Totals", "", *[f"- {k}: {v}" for k, v in totals.items()], ""])
    if args.json_path:
        Path(args.json_path).write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    if args.md_path:
        Path(args.md_path).write_text("\n".join(md), encoding="utf-8")
    if not args.json_path and not args.md_path:
        print("\n".join(md))

if __name__ == "__main__":
    main()
