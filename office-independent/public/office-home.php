<?php

declare(strict_types=1);

// Minimal independent Office home. Never display operational data before session validation.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(503);
  exit('Office is nog niet beschikbaar.');
}
try {
  foreach ([
    'OfficeMount.php',
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || $dsn === '') {
    throw new RuntimeException('Office database unavailable');
  }
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $sessions->currentOperator();
  if ($operator === null) {
    $queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
    header('Location: ' . (\Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('inloggen') : ($queryRouter ? '/index.php?office_route=inloggen' : '/office/inloggen')), true, 302);
    exit;
  }
} catch (Throwable $e) {
  http_response_code(503);
  exit('Office is tijdelijk niet beschikbaar.');
}
// Read-only recent-work panel: disabled until the test-database schema and ACL checks are verified.
$recentWork = [];
if (getenv('OFFICE_RECENT_WORK_ENABLED') === 'enabled'
  && getenv('OFFICE_RECENT_WORK_ACL_VERIFIED') === 'enabled') {
  try {
    require_once dirname(__DIR__) . '/src/RecentWork/PdoRecentWorkRepository.php';
    // No live module resolvers have been registered yet: fail closed, even if
    // flags are accidentally enabled. Never expose raw persisted work rows.
    require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkEvent.php';
    require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkAccess.php';
    require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkVisibleItems.php';
    require_once dirname(__DIR__) . '/src/Calculation/PdoOfficeCalculationPermissionRepository.php';
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcProjectScopeRepository.php';
    require_once dirname(__DIR__) . '/src/RecentWork/RecentWorkCalculationAccess.php';
    $rows = (new \Brebo\Office\RecentWork\PdoRecentWorkRepository($db))
      ->listForOperator($operator->operatorId);
    $recentWork = (new \Brebo\Office\RecentWork\RecentWorkVisibleItems(
      new \Brebo\Office\RecentWork\RecentWorkAccess([
        'calculation' => [new \Brebo\Office\RecentWork\RecentWorkCalculationAccess($db), 'canAccess'],
      ])
    ))->filter($operator->operatorId, $rows);
  } catch (Throwable $e) {
    // Optional dashboard panel must never break Office login or navigation.
    $recentWork = [];
  }
}
$calcEnabled = getenv('OFFICE_CALC_ENABLED') === 'enabled'
  && getenv('OFFICE_CALC_BROWSER_HANDOFF_ENABLED') === 'enabled'
  && getenv('OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED') === 'enabled';
$queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
$crmUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('crm/aanvragen') : ($queryRouter ? '/index.php?office_route=crm/aanvragen' : '/office/crm/aanvragen');
$calcUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('calc-werkbank') : ($queryRouter ? '/index.php?office_route=calc-werkbank' : '/office/calc-werkbank');
$modules = [
  ['CRM', $crmUrl, !\Brebo\Office\Intake\OfficeMount::isTest() || (getenv('OFFICE_TEST_CRM_ENABLED') === 'enabled' && getenv('OFFICE_CRM_AUTH_MODE') === 'session_only')],
  ['Calculatie', $calcUrl, $calcEnabled],
  ['Finance', null, false],
  ['Inkoop', null, false],
  ['Projecten', \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten' : null, \Brebo\Office\Intake\OfficeMount::isTest() && getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled' && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled'],
  ['Gebouwen', null, false],
  ['Documenten', null, false],
  ['Inzet', \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten?view=inzet' : null, \Brebo\Office\Intake\OfficeMount::isTest() && getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled' && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled'],
  ['Mail', null, false],
];
// Only the explicitly authorized test reviewer may see source-import KPIs.
$importReviewEnabled = \Brebo\Office\Intake\OfficeMount::isTest()
  && getenv('OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED') === 'enabled'
  && filter_var(getenv('OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'), FILTER_VALIDATE_INT) === (int)$operator->operatorId;
$importIssueCount = null;
$importRowCount = null;
if ($importReviewEnabled) {
  try {
    $sourcePath = getenv('OFFICE_TEST_INZET_SQLITE_PATH');
    $realSource = is_string($sourcePath) ? realpath($sourcePath) : false;
    $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($realSource === false || $root === false || !is_readable($realSource)
      || str_starts_with($realSource, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
      throw new RuntimeException('Private review source unavailable');
    }
    $sourceDb = new PDO('sqlite:file:'.str_replace('%2F', '/', rawurlencode($realSource)).'?mode=ro', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $importIssueCount = (int)$sourceDb->query('SELECT COUNT(*) FROM review_issues')->fetchColumn();
    $importRowCount = (int)$sourceDb->query('SELECT COUNT(*) FROM inzet_source_workbench')->fetchColumn();
  } catch (Throwable $e) {
    // Unknown is not zero; never present a false clean bill of health.
    $importIssueCount = null;
    $importRowCount = null;
  }
}
header('Content-Type: text/html; charset=utf-8');
$isTestEnvironment = \Brebo\Office\Intake\OfficeMount::isTest();
$environmentBanner = $isTestEnvironment
  ? '<div role="status" style="background:#fff0b3;color:#473500;padding:14px 24px;text-align:center;font-weight:800;border-bottom:3px solid #b8860b">TESTOMGEVING — geen productiegegevens</div>'
  : '';
$logoutUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('uitloggen') : ($queryRouter ? '/index.php?office_route=uitloggen' : '/office/uitloggen');
$projectUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten' : null;
$projectEnabled = \Brebo\Office\Intake\OfficeMount::isTest()
  && getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled'
  && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled';
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BREBO Office</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#1b2634;font:15px system-ui,sans-serif}
.shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}
.sidebar{background:#162639;color:white;padding:24px 14px}.brand{font-size:20px;font-weight:750;margin:0 10px 25px}
.side-item{display:block;padding:12px 14px;border-radius:8px;color:#e7edf5;text-decoration:none;margin:3px 0}
.side-item[aria-current]{background:#30516d;color:white}.side-item.disabled{opacity:.55}
.content{min-width:0;padding:28px clamp(18px,4vw,55px)}.top{display:flex;align-items:center;justify-content:space-between;gap:16px}
h1{margin:0 0 6px}p{color:#57677b}.tabs{display:flex;gap:20px;overflow:auto;border-bottom:1px solid #dbe1e8;margin:24px 0}
.tab{padding:12px 2px;color:#34465a;text-decoration:none;white-space:nowrap}.tab[aria-current]{border-bottom:3px solid #2e688e;font-weight:700}
.panel{border:1px solid #dbe1e8;border-radius:12px;background:white;margin:18px 0;padding:18px}
summary{cursor:pointer;font-weight:700}.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:16px}
.kpi{border:1px solid #e1e5ea;border-radius:8px;padding:15px}.kpi strong{display:block;margin-top:8px;font-size:19px}
.row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.link{color:#18547e;font-weight:650}.muted{color:#57677b}button{cursor:pointer}
@media(max-width:700px){.shell{grid-template-columns:1fr}.sidebar{padding:12px}.brand{margin:0 0 10px}.sidebar nav{display:flex;overflow:auto;gap:4px}.side-item{white-space:nowrap}.content{padding:18px}}
</style></head><body><div class="shell"><aside class="sidebar"><div class="brand">BREBO Office</div><nav aria-label="Hoofdnavigatie">';
echo '<a class="side-item" aria-current="page" href="' . $esc(\Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::basePath() . '/' : ($queryRouter ? '/index.php' : '/office/')) . '">Overzicht</a>';
foreach ($modules as [$name, $url, $active]) {
  $label = $esc($name);
  if ($active && is_string($url)) {
    echo '<a class="side-item" href="' . $esc($url) . '">' . $label . '</a>';
  } else {
    echo '<span class="side-item disabled" aria-disabled="true">' . $label . '</span>';
  }
}
echo '</nav></aside><main class="content">' . $environmentBanner . '<div class="top"><div><h1>Overzicht</h1><p>Mijn werkplek en projecten</p></div><form method="post" action="' . $esc($logoutUrl) . '"><button type="submit">Uitloggen</button></form></div>';
echo '<nav class="tabs" aria-label="Overzicht tabs"><span class="tab" aria-current="page">Projecten</span></nav>';
echo '<details class="panel"><summary>KPI’s en kritieke punten</summary><div class="kpis">';
foreach (['Actieve projecten','Omzet / prognose','Openstaande facturen','Kritieke punten'] as $metric) {
  echo '<div class="kpi"><span>' . $esc($metric) . '</span><strong>Niet beschikbaar</strong></div>';
}
echo '</div><p class="muted">KPI’s worden pas getoond wanneer gegevensbron en rechten zijn geverifieerd.</p></details>';
echo '<section class="panel"><div class="row"><h2>Projecten</h2>';
if ($projectEnabled && is_string($projectUrl)) {
  echo '<a class="link" href="' . $esc($projectUrl) . '">Open projectenlijst</a>';
} else {
  echo '<span class="muted">Projectenlijst nog niet aangesloten</span>';
}
echo '</div>';
if ($projectEnabled && is_string($projectUrl)) {
  try {
    // Same ACL-filtered read model as the existing native project portfolio.
    $projectsStatement = $db->prepare(
      "SELECT p.id, p.code, p.title, p.status, COUNT(DISTINCT pb.building_id) AS building_count
       FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id = p.id
       INNER JOIN office_operator_accounts a ON a.id = m.operator_id
       LEFT JOIN office_project_buildings pb ON pb.project_id = p.id
       WHERE m.operator_id = :operator_id AND m.permission IN ('read','manage') AND a.active = 1
       GROUP BY p.id, p.code, p.title, p.status
       ORDER BY p.id DESC LIMIT 100"
    );
    $projectsStatement->execute([':operator_id' => $operator->operatorId]);
    $visibleProjects = $projectsStatement->fetchAll(PDO::FETCH_ASSOC);
    if ($visibleProjects === []) {
      echo '<p class="muted">Geen toegankelijke projecten gevonden.</p>';
    } else {
      echo '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:12px;border-bottom:1px solid #dbe1e8">Project</th><th style="text-align:left;padding:12px;border-bottom:1px solid #dbe1e8">Status</th><th style="text-align:left;padding:12px;border-bottom:1px solid #dbe1e8">Gebouwen</th></tr></thead><tbody>';
      foreach ($visibleProjects as $project) {
        $detailUrl = $projectUrl . '/detail?id=' . (int)$project['id'];
        echo '<tr><td style="padding:12px;border-bottom:1px solid #dbe1e8"><a href="' . $esc($detailUrl) . '">' . $esc((string)$project['code']) . ' · ' . $esc((string)$project['title']) . '</a></td><td style="padding:12px;border-bottom:1px solid #dbe1e8">' . $esc((string)$project['status']) . '</td><td style="padding:12px;border-bottom:1px solid #dbe1e8">' . (int)$project['building_count'] . '</td></tr>';
      }
      echo '</tbody></table></div>';
    }
  } catch (Throwable $exception) {
    // Do not leak database details or bypass project access controls.
    echo '<p class="muted">Projectenlijst tijdelijk niet beschikbaar. <a href="' . $esc($projectUrl) . '">Open de projectenmodule</a>.</p>';
  }
}
echo '</section>';
if ($recentWork !== []) {
  echo '<section class="panel"><h2>Mijn laatste werkzaamheden</h2><ul>';
  foreach ($recentWork as $item) {
    $label = $item->module === 'calculation' ? 'Calculatie' : 'Werkzaamheid';
    echo '<li>' . $esc($label . ' #' . $item->resourceId) . ' — Hervatten nog niet beschikbaar</li>';
  }
  echo '</ul></section>';
}
if ($importReviewEnabled) {
  echo '<section class="panel"><h2>Inzet · urenverantwoording</h2><a class="link" href="' . $esc(\Brebo\Office\Intake\OfficeMount::route('inzet/importcontrole') . '#afwijkingen') . '">Afwijkingen ter controle: ' . ($importIssueCount === null ? 'Niet beschikbaar' : (string)$importIssueCount) . '</a><p>' . ($importRowCount === null ? 'Brongegevens niet beschikbaar' : (string)$importRowCount . ' bronregels') . '</p></section>';
}
echo '</main></div></body></html>';
