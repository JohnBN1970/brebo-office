#!/usr/bin/env python3
"""Non-destructive, repeatable presentation-only merge into active Office-test page.

Runs on a COPY of the active release file. Never modifies POST handlers.
"""
import pathlib
import sys

source = pathlib.Path(sys.argv[1])
target = pathlib.Path(sys.argv[2])
s = source.read_text(encoding="utf-8")
required = [
    "create_project_contract", "create_project_term", "create_project_change",
    "OfficeProjectFinanceOverview", "$dossierNav = '<nav",
    "$moduleHtml = '<div id=\"project-werkbank\"",
    "echo '<!doctype html>",
    "' . $dossierNav . '<div id=\"project-gegevens\"",
]
for marker in required:
    if marker not in s:
        raise SystemExit("UNSUPPORTED_SERVER_SOURCE: " + marker)
if "BREBO_DASHBOARD_V2_MERGE" in s:
    raise SystemExit("ALREADY_MERGED")
start = s.index("// Project dossier orientation only:")
end = s.index("$moduleHtml = '<div id=\"project-werkbank\"", start)
replacement = r'''// BREBO_DASHBOARD_V2_MERGE: presentation only; all existing POST actions are retained.
$projectBaseUrl = \\Brebo\\Office\\Intake\\OfficeMount::basePath()
  . '/projecten/detail?id=' . (int) $projectId;
$dashboardTabs = [
  'overzicht' => 'Overzicht', 'gebouwen' => 'Gebouwen',
  'werkvoorbereiding' => 'Werkvoorbereiding',
  'uitvoering' => 'Uitvoering', 'financieel' => 'Financieel',
];
$dashboardTab = (string) ($_GET['tab'] ?? 'overzicht');
if (!isset($dashboardTabs[$dashboardTab])) $dashboardTab = 'overzicht';
$dashboardDemo = \\Brebo\\Office\\Intake\\OfficeMount::isTest()
  && ($_GET['demo'] ?? '') === '1' && $dashboardTab === 'overzicht';
$dossierNav = '<nav class="office-dossier-nav" aria-label="Projectdossier onderdelen">';
foreach ($dashboardTabs as $tabKey => $tabLabel) {
  $tabUrl = $projectBaseUrl . '&tab=' . rawurlencode($tabKey);
  $dossierNav .= '<a href="' . $escape($tabUrl) . '"'
    . ($dashboardTab === $tabKey ? ' aria-current="page"' : '') . '>'
    . $escape($tabLabel) . '</a>';
}
$dossierNav .= '</nav>';
$dashboardCss = '<style>'
  . '.office-shell main{max-width:none!important;padding:28px clamp(20px,3vw,52px)!important}'
  . '.office-shell main>section{background:transparent;border:0;padding:0}'
  . '.office-dossier-nav{background:white;padding:10px 14px;border:1px solid #dbe1e8;border-radius:14px}'
  . '.office-dossier-nav a{background:transparent}.office-dossier-nav a[aria-current]{background:#30516d;color:white}'
  . '#project-gegevens{background:white;border:1px solid #dbe1e8;border-radius:16px;padding:22px 26px;margin-bottom:20px}'
  . '#project-gegevens h1{margin:0 0 8px;font-size:clamp(24px,2.2vw,36px)}'
  . '#project-gegevens dl{display:flex;flex-wrap:wrap;gap:14px 38px;margin:10px 0}'
  . '#project-gegevens dt{margin-top:0;color:#63758a;font-size:12px;text-transform:uppercase}'
  . '#project-gegevens dd{font-weight:650}'
  . '.brebo-dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:20px 0}'
  . '.brebo-dashboard-card{background:white;border:1px solid #dbe1e8;border-radius:14px;padding:22px;min-width:0}'
  . '.brebo-dashboard-card small{display:block;color:#607086;font-weight:650}'
  . '.brebo-dashboard-card strong{display:block;font-size:clamp(22px,2vw,30px);margin:12px 0 4px}'
  . '.brebo-dashboard-panels{display:grid;grid-template-columns:2fr 1fr;gap:16px}'
  . '.brebo-demo-banner{background:#fff4d8;border:1px solid #e8c87c;border-radius:12px;padding:14px 18px;margin:16px 0}'
  . '.brebo-dashboard-card p{color:#52667b;line-height:1.5}'
  . '@media(max-width:1050px){.brebo-dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.brebo-dashboard-panels{grid-template-columns:1fr}}'
  . '@media(max-width:600px){.brebo-dashboard-grid{grid-template-columns:1fr}}'
  . '</style>';
$dashboardVisibility = '<style>'
  . ($dashboardTab !== 'gebouwen' ? '#project-gebouwen{display:none}' : '')
  . ($dashboardTab !== 'werkvoorbereiding' ? '#project-werkbank{display:none}' : '')
  . ($dashboardTab !== 'uitvoering' ? '#project-uitvoering{display:none}' : '')
  . ($dashboardTab !== 'financieel' ? '#project-financieel{display:none}' : '')
  . '</style>';
$dashboardHtml = '';
if ($dashboardTab === 'overzicht') {
  $dashboardHtml = '<div class="brebo-dashboard-grid">';
  if ($dashboardDemo) {
    $dashboardHtml = '<div class="brebo-demo-banner"><strong>DEMO — fictieve voorbeeldgegevens.</strong> '
      . 'Geen financiële of personeelsgegevens uit de database. '
      . '<a href="' . $escape($projectBaseUrl . '&tab=overzicht') . '">Echte projectgegevens tonen</a></div>'
      . '<div class="brebo-dashboard-grid">';
    foreach ([
      ['Contractwaarde', '€ 4.850.000', 'Fictief · exclusief btw'],
      ['Voortgang', '62%', 'Fictieve uitvoeringsstand'],
      ['Planning', '70%', 'Fictief · gepland'],
      ['Uren', '8.400 / 8.950', 'Fictief · werkelijk / begroot'],
    ] as $metric) {
      $dashboardHtml .= '<div class="brebo-dashboard-card"><small>' . $escape($metric[0])
        . '</small><strong>' . $escape($metric[1]) . '</strong><p>'
        . $escape($metric[2]) . '</p></div>';
    }
    $dashboardHtml .= '</div><div class="brebo-dashboard-panels">'
      . '<div class="brebo-dashboard-card"><h2>Uitvoering en mijlpalen · DEMO</h2>'
      . '<p>Start renovatie · 15 januari 2026</p><p>Oplevering fase 2 · 30 november 2026</p>'
      . '<p>Risico: levertijd kozijnen · fictief voorbeeld</p></div>'
      . '<div class="brebo-dashboard-card"><h2>Projectteam · DEMO</h2>'
      . '<p>Projectleider: voorbeeldpersoon</p><p>Bezetting: 12 medewerkers (fictief)</p>'
      . '</div></div>';
  } else {
    $buildingCount = is_array($buildings ?? null) ? count($buildings) : 0;
    $dashboardHtml .= '<div class="brebo-dashboard-card"><small>Gebouwen</small><strong>'
      . (int) $buildingCount . '</strong><p>Gekoppeld aan dit project</p></div>'
      . '<div class="brebo-dashboard-card"><small>Projectstatus</small><strong>'
      . $escape((string) ($statusLabel ?? 'Onbekend')) . '</strong><p>BREBO Office</p></div>'
      . '<div class="brebo-dashboard-card"><small>Contractwaarde</small><strong>—</strong>'
      . '<p>Alleen beschikbaar via geautoriseerd financieel dossier</p></div>'
      . '<div class="brebo-dashboard-card"><small>Voortgang</small><strong>—</strong>'
      . '<p>Nog geen gevalideerde uitvoeringsbron</p></div></div>'
      . '<p><a href="' . $escape($projectBaseUrl . '&tab=overzicht&demo=1')
      . '">Bekijk fictieve dashboarddemo</a></p>';
  }
}
''';
s = s[:start] + replacement + s[end:]
needle = "' . $dossierNav . '<div id=\"project-gegevens\""
assert s.count(needle) == 1
s = s.replace(needle, "' . $dashboardCss . $dashboardVisibility . $dossierNav . '<div id=\"project-gegevens\"", 1)
needle2 = "'</dd></dl></div><div id=\"project-gebouwen\"'"
# The live file has its own project summary; insert dashboard before buildings, without touching summary.
boundary = "'</dd></dl></div><div id=\"project-gebouwen\"'"
if boundary not in s:
    # The final echo has a slightly different closing literal; match the exact known fragment.
    boundary = "' . '</div><div id=\"project-gebouwen\"'"
if boundary not in s:
    # Last-resort bounded insertion at the first buildings opening in the final echo.
    needle3 = "</dl></div><div id=\"project-gebouwen\">"
    if s.count(needle3) != 1:
        raise SystemExit("UNSUPPORTED_RENDER_BOUNDARY")
    s = s.replace(needle3, "</dl></div>' . $dashboardHtml . '<div id=\"project-gebouwen\">", 1)
else:
    s = s.replace(boundary, boundary.replace("<div id=", "' . $dashboardHtml . '<div id="), 1)
for marker in ("create_project_contract", "create_project_term", "create_project_change"):
    if s.count(marker) != source.read_text(encoding="utf-8").count(marker):
        raise SystemExit("POST_HANDLER_CHANGED: " + marker)
target.write_text(s, encoding="utf-8")
print("MERGE_READY: presentation-only; original source untouched")
