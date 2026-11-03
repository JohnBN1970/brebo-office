#!/usr/bin/env python3
"""Safely replace ONLY the final project dossier rendering with V3.

Preserve the entire authenticated controller, all server-only POST handlers,
authorization and database queries. Refuse unexpected layouts.
"""
from pathlib import Path
import sys

MARKER = "// BREBO_PROJECT_DETAIL_V3_MOUNT"
ANCHOR = "echo '<!doctype html><html lang=\"nl\"><head>"
REQUIRED = (
    "OfficeNativeProjectAccess",
    "office_project_buildings",
    "$financialSectionHtml",
    "$buildingHtml",
    "$workbenchHtml",
    "$executionHtml",
    "$projectId",
    "$activeTab",
)
REPLACEMENT = r'''// BREBO_PROJECT_DETAIL_V3_MOUNT
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPageStructure.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectDetailV3View.php';
$projectV3Sections = [
  'gebouwen' => '<div id="project-gebouwen">' . $buildingHtml . $housingHtml . $totalsHtml . $formHtml . '</div>',
  'werkvoorbereiding' => $workbenchHtml,
  'uitvoering' => $executionHtml,
  'financieel' => $financialSectionHtml,
];
$projectV3Body = \\Brebo\\Office\\Project\\OfficeProjectDetailV3View::render(
  \\Brebo\\Office\\Intake\\OfficeMount::basePath(),
  (int) $projectId,
  (string) $project->title,
  (string) $project->identity->projectCode,
  isset($statusLabel) ? (string) $statusLabel : null,
  count($buildings),
  (string) $activeTab,
  $projectV3Sections
);
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
  . '<title>Projectdetailpagina - BREBO Office</title>'
  . '<style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}'
  . '.office-shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}'
  . '.office-sidebar{background:#162639;color:#fff;padding:24px 14px}'
  . '.office-brand{font-size:20px;font-weight:750;margin:0 10px 25px}'
  . '.office-side-item{display:block;padding:12px 14px;border-radius:8px;color:#e7edf5;text-decoration:none;margin:3px 0}'
  . '.office-side-item[aria-current]{background:#30516d;color:white}'
  . '.office-shell main{min-width:0;padding:28px clamp(18px,4vw,55px)}'
  . 'form{display:grid;gap:12px;max-width:640px}label{display:grid;gap:5px}input,select,button{padding:10px;font:inherit}'
  . 'table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #dbe1e8}'
  . '@media(max-width:700px){.office-shell{grid-template-columns:1fr}.office-sidebar{padding:12px}.office-shell main{padding:18px}}'
  . \\Brebo\\Office\\Project\\OfficeProjectDetailV3View::styles()
  . '</style></head><body><div class="office-shell"><aside class="office-sidebar">'
  . '<div class="office-brand">BREBO Office</div><nav aria-label="Hoofdnavigatie">'
  . '<a class="office-side-item" href="' . $escape($homeUrl) . '">Overzicht</a>'
  . '<a class="office-side-item" aria-current="page" href="' . $escape(\\Brebo\\Office\\Intake\\OfficeMount::basePath() . '/projecten') . '">Projecten</a>'
  . '<a class="office-side-item" href="' . $escape(\\Brebo\\Office\\Intake\\OfficeMount::basePath() . '/projecten?view=inzet') . '">Inzet</a>'
  . '</nav></aside><main>' . $projectV3Body . '</main></div></body></html>';
'''

def transform(source: str) -> str:
    if MARKER in source:
        return source
    if any(required not in source for required in REQUIRED):
        raise ValueError("Required project handler or data marker missing")
    if source.count(ANCHOR) != 1:
        raise ValueError("Unexpected project HTML render boundary")
    start = source.index(ANCHOR)
    if "create_project_term" in source[start:] or "create_project_change" in source[start:]:
        raise ValueError("POST handler found after render boundary")
    # Final renderer is the last statement. No controller logic may follow it.
    return source[:start] + REPLACEMENT.replace('\\\\', '\\')

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: mount-project-detail-v3.py <office-project.php>")
    file = Path(sys.argv[1])
    original = file.read_text()
    result = transform(original)
    if result != original:
        file.write_text(result)
    print("PROJECT_DETAIL_V3_MOUNT_OK")
