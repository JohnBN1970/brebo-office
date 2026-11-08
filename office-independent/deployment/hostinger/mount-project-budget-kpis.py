#!/usr/bin/env python3
"""Fail-closed, additive Office-test financial project budget KPI mount."""
import pathlib
import sys

if len(sys.argv)!=3:
    sys.exit("usage: mount-project-budget-kpis.py <active-project.php> <repo-project.php>")
target,source=map(pathlib.Path,sys.argv[1:])
active=target.read_bytes()
repo=source.read_bytes()
marker=b"// Canonical locked work budget: read only, same authorized procurement scope."
anchor=b"// Read-only Office procurement commitments:"
end=b"$financialSectionHtml .= $financeHtml;"
if repo.count(marker)!=1 or repo.count(anchor)!=1 or repo.count(end)!=1:
    sys.exit("PROJECT_BUDGET_SOURCE_MARKERS_INVALID")
if active.count(marker)==1:
    print("PROJECT_BUDGET_ALREADY_MOUNTED")
    sys.exit(0)
if active.count(anchor)!=1 or active.count(end)!=1:
    sys.exit("PROJECT_BUDGET_ACTIVE_MARKERS_INVALID")
start=repo.index(marker)
snippet_end=repo.find(b"\n            }\n",start)
if snippet_end<0:
    sys.exit("PROJECT_BUDGET_SNIPPET_END_MISSING")
snippet=repo[start:snippet_end+len(b"\n            }\n")]
if b"OfficeWorkBudgetCommitmentComparison::summarize" not in snippet or b"OFFICE_TEST_WORK_BUDGET_KPIS_ENABLED" not in snippet:
    sys.exit("PROJECT_BUDGET_SNIPPET_INVALID")
# Insert immediately after existing procurement commitment card, without replacing it.
card=b"                . '</dd></dl><p>Verplichtingen zijn geen geboekte kosten, facturen of betalingen.</p></section>';"
if active.count(card)!=1:
    sys.exit("PROJECT_BUDGET_ACTIVE_CARD_MISMATCH")
if active.index(card)<active.index(anchor) or active.index(card)>active.index(end):
    sys.exit("PROJECT_BUDGET_ACTIVE_CARD_OUTSIDE_FINANCE")
target.write_bytes(active.replace(card,card+b"\n            "+snippet,1))
print("PROJECT_BUDGET_KPIS_PATCHED")
