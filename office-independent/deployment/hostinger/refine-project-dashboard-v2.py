#!/usr/bin/env python3
"""Apply a guarded, presentation-only V2.1 refinement to deployed Office-test PHP."""
from pathlib import Path
import sys

source, destination = map(Path, sys.argv[1:3])
original = source.read_text(encoding="utf-8")
if "BREBO_DASHBOARD_V2_MERGE" not in original:
    raise SystemExit("NOT_DASHBOARD_V2")
if "BREBO_DASHBOARD_V21_LAYOUT" in original:
    raise SystemExit("ALREADY_REFINED")
needle = "$dashboardVisibility = '<style>'"
if original.count(needle) != 1:
    raise SystemExit("UNEXPECTED_DASHBOARD_LAYOUT")
# CSS targets only the new project detail view, retaining existing links and forms.
# In particular, old GOTIK controls are not deleted: users can still reach them.
addition = r'''// BREBO_DASHBOARD_V21_LAYOUT: full-width and overflow-safe, legacy actions retained.
$dashboardCss .= '<style>'
  . 'html,body{max-width:100%;overflow-x:clip}'
  . '.office-shell{width:100%;max-width:100%;grid-template-columns:230px minmax(0,1fr)}'
  . '.office-shell main,.office-shell main>section{width:100%;min-width:0;max-width:100%;box-sizing:border-box}'
  . '.office-shell main{padding:24px clamp(16px,2.2vw,36px)!important}'
  . '.office-shell main>section>*{max-width:100%;min-width:0;box-sizing:border-box}'
  . '.office-shell main>section table{max-width:100%;display:block;overflow-x:auto}'
  . '.office-shell main>section img,.office-shell main>section svg{max-width:100%}'
  . '.office-dossier-nav{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:20px}'
  . '.office-dossier-nav a{border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:650}'
  . '#project-gegevens{padding:clamp(18px,2vw,30px);box-shadow:0 2px 12px rgba(20,39,60,.03)}'
  . '#project-gegevens h1{letter-spacing:-.025em;line-height:1.2}'
  . '#project-gegevens>div,#project-gegevens>section,#project-gegevens>nav{min-width:0;max-width:100%}'
  . '#project-gegevens a{overflow-wrap:anywhere}'
  . '.brebo-dashboard-grid{grid-template-columns:repeat(4,minmax(0,1fr));margin:16px 0 22px}'
  . '.brebo-dashboard-card{box-shadow:0 2px 10px rgba(20,39,60,.025)}'
  . '.brebo-dashboard-card strong{overflow-wrap:anywhere}'
  . '.brebo-dashboard-panels>*{min-width:0}'
  . '@media(max-width:1150px){.brebo-dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}'
  . '@media(max-width:750px){.office-shell{grid-template-columns:minmax(0,1fr)}'
  . '.brebo-dashboard-grid,.brebo-dashboard-panels{grid-template-columns:1fr}}'
  . '</style>';
'''
updated = original.replace(needle, addition + needle, 1)
# The PHP action/controller portion must remain byte-identical.
boundary = "// BREBO_DASHBOARD_V2_MERGE"
if original.split(boundary, 1)[0] != updated.split(boundary, 1)[0]:
    raise SystemExit("CONTROLLER_CHANGED")
destination.write_text(updated, encoding="utf-8")
print("DASHBOARD_V21_LAYOUT_READY")
