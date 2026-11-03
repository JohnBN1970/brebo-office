#!/usr/bin/env python3
"""Fail-closed V3 mount for the active Office-test controller.

Only the final HTML renderer is replaced. All authenticated data gathering,
contract/term/change handlers and V2.3 post-render processing remain intact.
"""
from pathlib import Path
import sys

MARKER = "// BREBO_PROJECT_DETAIL_V3_MOUNT"
ANCHOR = "echo '<!doctype html><html lang=\"nl\"><head>"
REQUIRED = (
    "OfficeNativeProjectAccess", "office_project_buildings",
    "$dashboardHtml", "$buildingHtml", "$housingHtml", "$totalsHtml",
    "$formHtml", "$moduleHtml", "$financeHtml", "$projectId",
    "$breboV23Render", "create_project_contract",
    "create_project_term", "create_project_change",
    "OfficeProjectFinanceOverview",
)
REPLACEMENT = r'''// BREBO_PROJECT_DETAIL_V3_MOUNT
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPageStructure.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectDetailV3View.php';
$projectV3Tab = (string) ($_GET['tab'] ?? 'overzicht');
$projectV3Sections = [
  'overzicht' => (string) $dashboardHtml,
  'gebouwen' => '<div id="project-gebouwen">' . $buildingHtml . $housingHtml . $totalsHtml . $formHtml . '</div>',
  'werkvoorbereiding' => (string) $moduleHtml,
  'uitvoering' => '<section><h2>Uitvoering</h2><p>Uitvoeringsgegevens worden vanuit de gekoppelde projectmodules ontsloten.</p></section>',
  'financieel' => (string) $financeHtml,
];
$projectV3Body = \Brebo\Office\Project\OfficeProjectDetailV3View::render(
  \Brebo\Office\Intake\OfficeMount::basePath(),
  (int) $projectId,
  (string) $project->title,
  (string) $project->identity->projectCode,
  isset($statusLabel) ? (string) $statusLabel : null,
  isset($buildings) && is_countable($buildings) ? count($buildings) : 0,
  $projectV3Tab,
  $projectV3Sections
);
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
  . '<title>Project - BREBO Office</title><style>'
  . 'body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}'
  . '.office-shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}'
  . '.office-sidebar{background:#162639;color:#fff;padding:24px 14px}'
  . '.office-brand{font-size:20px;font-weight:750;margin:0 10px 25px}'
  . '.office-side-item{display:block;padding:12px 14px;border-radius:8px;color:#e7edf5;text-decoration:none;margin:3px 0}'
  . '.office-side-item[aria-current]{background:#30516d;color:white}'
  . '.office-shell main{min-width:0;padding:28px clamp(18px,4vw,55px)}'
  . 'form{display:grid;gap:12px;max-width:640px}label{display:grid;gap:5px}input,select,button{padding:10px;font:inherit}'
  . 'table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #dbe1e8}'
  . '.modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}'
  . '.module{border:1px solid #dbe1e8;border-radius:10px;padding:18px;background:#f8fafc}'
  . '.module strong,.module small{display:block}'
  . '.project-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}'
  . '@media(max-width:700px){.office-shell{grid-template-columns:1fr}.office-sidebar{padding:12px}.office-shell main{padding:18px}}'
  . \Brebo\Office\Project\OfficeProjectDetailV3View::styles()
  . '</style></head><body><div class="office-shell"><aside class="office-sidebar">'
  . '<div class="office-brand">BREBO Office</div><nav aria-label="Hoofdnavigatie">'
  . '<a class="office-side-item" href="' . $escape($homeUrl) . '">Overzicht</a>'
  . '<a class="office-side-item" aria-current="page" href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/projecten') . '">Projecten</a>'
  . '<a class="office-side-item" href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/projecten?view=inzet') . '">Inzet</a>'
  . '</nav></aside><main>' . $dashboardCss . $dashboardVisibility . $projectV3Body
  . '</main></div></body></html>';
echo $breboV23Render((string) ob_get_clean());
'''

def transform(source: str) -> str:
    if MARKER in source:
        return source
    missing = [marker for marker in REQUIRED if marker not in source]
    if missing:
        raise ValueError("Missing required markers: " + ", ".join(missing))
    if source.count(ANCHOR) != 1:
        raise ValueError("Unexpected HTML render boundary")
    start = source.index(ANCHOR)
    tail = source[start:]
    if tail.count("echo $breboV23Render((string) ob_get_clean());") != 1:
        raise ValueError("Expected V2.3 output-buffer finalizer missing")
    if not tail.rstrip().endswith("echo $breboV23Render((string) ob_get_clean());"):
        raise ValueError("Unexpected controller statements after final renderer")
    if "create_project_term" in tail or "create_project_change" in tail:
        raise ValueError("POST handler found after render boundary")
    return source[:start] + REPLACEMENT

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: mount-project-detail-v3.py <office-project.php>")
    file = Path(sys.argv[1])
    original = file.read_text()
    result = transform(original)
    if result != original:
        file.write_text(result)
    print("PROJECT_DETAIL_V3_MOUNT_OK")
