#!/usr/bin/env python3
"""Office-test V2.2: unobtrusive GOTIK controls, preserving all existing links and forms."""
from pathlib import Path
import sys

source, target = map(Path, sys.argv[1:3])
original = source.read_text(encoding="utf-8")
if "BREBO_DASHBOARD_V21_LAYOUT" not in original:
    raise SystemExit("REQUIRES_V21")
if "BREBO_DASHBOARD_V22_CLEAN" in original:
    raise SystemExit("ALREADY_V22")
needle = "$dashboardVisibility = '<style>'"
if original.count(needle) != 1:
    raise SystemExit("UNEXPECTED_DASHBOARD_ANCHOR")
# JS only reorganizes legacy presentation nodes; it never deletes actions or forms.
# All links remain in a native <details> section for keyboard/screen-reader access.
addition = r'''// BREBO_DASHBOARD_V22_CLEAN: GOTIK is the steering model, not primary navigation.
$dashboardCss .= '<style>'
  . '.brebo-legacy-tools{margin:12px 0 18px;border:1px solid #dbe1e8;border-radius:10px;background:#f8fafc;padding:8px 14px}'
  . '.brebo-legacy-tools summary{cursor:pointer;font-weight:650;color:#30516d;padding:6px 0}'
  . '.brebo-legacy-tools[open] summary{margin-bottom:12px}'
  . '.brebo-legacy-tools a{display:inline-block;margin:4px 10px 4px 0}'
  . '#project-gegevens>h1{margin-bottom:8px}'
  . '.office-dossier-nav{position:relative}'
  . '</style>';
$dashboardCss .= '<script>(function(){'
  . 'function organize(){'
  . 'var root=document.getElementById("project-gegevens");if(!root||root.dataset.breboV22)return;'
  . 'root.dataset.breboV22="1";'
  . 'var headings=Array.from(root.querySelectorAll("h1,h2,h3"));'
  . 'var gotik=headings.find(function(h){return /Projectsturing volgens GOTIK/i.test(h.textContent||"")});'
  . 'var legacy=gotik&&gotik.parentElement;'
  . 'var links=Array.from(root.querySelectorAll("a"));'
  . 'var navLinks=links.filter(function(a){return /^(Projectoverzicht|Calculatie & Offertes|Finance|Informatie|Geld|Organisatie|Tijd|Kwaliteit)$/i.test((a.textContent||"").trim())});'
  . 'if(!legacy&&!navLinks.length)return;'
  . 'var tools=document.createElement("details");tools.className="brebo-legacy-tools";'
  . 'var summary=document.createElement("summary");summary.textContent="Aanvullende projectfuncties";tools.appendChild(summary);'
  . 'if(legacy&&legacy!==root){legacy.parentNode.insertBefore(tools,legacy);tools.appendChild(legacy);}'
  . 'else{root.appendChild(tools);}'
  . 'navLinks.forEach(function(a){'
  . 'if(a.closest(".brebo-legacy-tools"))return;'
  . 'if(a.closest("form"))return;'
  . 'tools.appendChild(a);'
  . '});'
  . 'var title=root.querySelector("h1");if(title&&title.parentNode===root){root.insertBefore(title,root.firstChild);}'
  . '}'
  . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",organize)}else{organize()}'
  . '})();</script>';
'''
updated = original.replace(needle, addition + needle, 1)
if original.split("// BREBO_DASHBOARD_V2_MERGE", 1)[0] != updated.split("// BREBO_DASHBOARD_V2_MERGE", 1)[0]:
    raise SystemExit("CONTROLLER_CHANGED")
for marker in ("create_project_contract", "create_project_term", "create_project_change"):
    if updated.count(marker) < original.count(marker):
        raise SystemExit("POST_HANDLER_REMOVED: " + marker)
target.write_text(updated, encoding="utf-8")
print("DASHBOARD_V22_READY")
