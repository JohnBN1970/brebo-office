#!/usr/bin/env python3
"""Contract-preserving merge acceptance using an isolated synthetic fixture."""
import pathlib
import subprocess
import sys
import tempfile

root = pathlib.Path(__file__).resolve().parents[1]
merger = root / "deployment/hostinger/merge-project-dashboard-v2.py"
fixture = r'''<?php
$projectId = 1;
$project = (object) ['title' => 'Test', 'identity' => (object) ['projectId'=>1,'projectCode'=>'T-1']];
$escape = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$homeUrl = '/office-test';
$statusLabel = 'Actief';
$buildings = [];
// Existing POST handlers must remain unchanged:
$actions = ['create_project_contract','create_project_term','create_project_change'];
// OfficeProjectFinanceOverview remains guarded by Finance ACL.
$dossierNav = '<nav class="office-dossier-nav" aria-label="Projectdossier onderdelen">'
  . '<a href="#project-gegevens">Projectoverzicht</a></nav>';
$moduleHtml = '<div id="project-werkbank"><h2>Werkvoorbereiding</h2></div>';
echo '<!doctype html><html><body>' . $dossierNav . '<div id="project-gegevens"><h1>'
 . $escape($project->title) . '</h1><dl><dt>Status</dt><dd>' . $escape($statusLabel)
 . '</dd></dl></div><div id="project-gebouwen">Gebouwen</div>' . $moduleHtml . '</body></html>';
'''
with tempfile.TemporaryDirectory() as directory:
    original = pathlib.Path(directory) / "original.php"
    merged = pathlib.Path(directory) / "merged.php"
    original.write_text(fixture, encoding="utf-8")
    run = subprocess.run([sys.executable, str(merger), str(original), str(merged)], capture_output=True, text=True)
    if run.returncode:
        raise SystemExit(run.stdout + run.stderr)
    assert original.read_text(encoding="utf-8") == fixture
    out = merged.read_text(encoding="utf-8")
    for marker in ("create_project_contract", "create_project_term", "create_project_change", "BREBO_DASHBOARD_V2_MERGE", "DEMO"):
        assert marker in out, marker
    subprocess.run(["php", "-l", str(merged)], check=True)
    rerun = subprocess.run([sys.executable, str(merger), str(merged), str(original)], capture_output=True, text=True)
    assert rerun.returncode != 0, "Merger must refuse a second application"
print("PROJECT_DASHBOARD_MERGE_ACCEPTANCE_OK")
