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
    PDO::ATTR_EMULATE_PREPARES => false,
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
$crmUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::basePath() . '/crm/bedrijven' : ($queryRouter ? '/index.php?office_route=crm/aanvragen' : '/office/crm/aanvragen');
$calcUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('calc-werkbank') : ($queryRouter ? '/index.php?office_route=calc-werkbank' : '/office/calc-werkbank');
$projectsEnabled = \Brebo\Office\Intake\OfficeMount::isTest()
  && getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled'
  && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled';
$projectsUrl = $projectsEnabled ? \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten' : null;
$myProjectCount = null;
$myRecentProjects = [];
$recentProjectsUnavailable = false;
if ($projectsEnabled) {
  // The portfolio's active-membership rule is the only source for project access.
  // Count first: a failed query must disable the project navigation, not expose a stale link.
  try {
    $countStatement = $db->prepare(
      "SELECT COUNT(DISTINCT p.id) FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id=p.id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id
       WHERE m.operator_id=:operator_id AND m.permission IN ('read','manage') AND a.active=1"
    );
    $countStatement->execute([':operator_id' => $operator->operatorId]);
    $rawCount = $countStatement->fetchColumn();
    if (!is_int($rawCount) && (!is_string($rawCount) || !ctype_digit($rawCount))) {
      throw new RuntimeException('Project count unavailable');
    }
    $myProjectCount = (int)$rawCount;
    $projectsEnabled = $myProjectCount > 0;
  } catch (Throwable $e) {
    $projectsEnabled = false;
    $myProjectCount = null;
  }
}
if (!$projectsEnabled) {
  $projectsUrl = null;
}
if ($projectsEnabled) {
  try {
    $recentProjectsStatement = $db->prepare(
      "SELECT DISTINCT p.id, p.code, p.title, p.status FROM office_projects p
       INNER JOIN office_project_memberships m ON m.project_id=p.id
       INNER JOIN office_operator_accounts a ON a.id=m.operator_id
       WHERE m.operator_id=:operator_id AND m.permission IN ('read','manage') AND a.active=1
       ORDER BY p.id DESC LIMIT 5"
    );
    $recentProjectsStatement->execute([':operator_id' => $operator->operatorId]);
    $myRecentProjects = $recentProjectsStatement->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    // A failed optional preview must not expose unverified project names.
    $myRecentProjects = [];
    $recentProjectsUnavailable = true;
  }
}

$crmEnabled = !\Brebo\Office\Intake\OfficeMount::isTest()
  || (getenv('OFFICE_TEST_CRM_ENABLED') === 'enabled' && getenv('OFFICE_CRM_AUTH_MODE') === 'session_only');
// canEditCrm controls edit actions, not whether the operator may view CRM.
// Do not infer finance, project or organization permissions from this CRM-only flag.
// Navigation groups are presentation-only. Server-side route authorization remains authoritative.
$financeEnabled = false;
$financeUrl = null;
if (getenv('OFFICE_FINANCE_ENABLED') === 'enabled' && getenv('OFFICE_FINANCE_SCHEMA_VERIFIED') === 'enabled') {
  try {
    require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceAccess.php';
    $financeEnabled = (new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId))->allows();
    if ($financeEnabled) $financeUrl = \Brebo\Office\Intake\OfficeMount::route('finance');
  } catch (Throwable $e) {
    $financeEnabled = false;
  }
}
$modules = [
  ['Relaties & CRM', 'Aanvragen, klanten en kansen', [['CRM', $crmUrl, $crmEnabled]]],
  ['Projecten', 'Van voorbereiding tot uitvoering', [
    ['Projecten', $projectsUrl, $projectsEnabled],
    ['Inzet', $projectsEnabled ? \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten?view=inzet' : null, $projectsEnabled],
    ['Gebouwen', null, false],
    ['Documenten', null, false],
    ['Inkoop', null, false],
    ['Overleg', null, false],
  ]],
  ['Calculatie & offertes', 'Berekenen en offreren', [['Calculatie', $calcUrl, $calcEnabled]]],
  ['Financiën', 'Facturen en betalingen', [['Finance', $financeUrl, $financeEnabled]]],
  ['Organisatie', 'Medewerkers en bedrijfsvoering', [['Organisatie', null, false]]],
  ['Communicatie', 'Berichten en correspondentie', [['Mail', null, false]]],
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
$navItems = [];
foreach ($modules as [$groupName, $description, $entries]) {
  $links = [];
  foreach ($entries as [$name, $url, $active]) {
    if ($active && is_string($url) && $url !== '') {
      $links[] = [$name, $url];
    }
  }
  $navItems[] = [$groupName, $description, $links];
}
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mijn werkplek · BREBO Office</title><style>
:root{font-family:system-ui,sans-serif;color:#17283a;background:#f3f6fa}*{box-sizing:border-box}body{margin:0}a{color:#155c87}a:focus-visible,button:focus-visible{outline:3px solid #e9a72c;outline-offset:3px}.shell{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}.sidebar{background:#14263b;color:#eaf0f7;padding:28px 18px}.brand{font-size:22px;font-weight:800;letter-spacing:.02em;margin:0 0 32px 10px}.sidebar h2{font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:#a9bed3;margin:25px 10px 8px}.sidebar a{display:block;color:#f2f6fa;text-decoration:none;padding:10px 12px;border-radius:8px}.sidebar a:hover,.sidebar a:focus-visible,.sidebar a.current{background:#29445f}.sidebar .muted{display:block;padding:7px 12px;color:#96aabe;font-size:13px}.workspace{min-width:0}.topbar{background:#fff;border-bottom:1px solid #dce4ed;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px}.topbar button{background:#f2f5f9;border:1px solid #bfcbd7;border-radius:7px;padding:8px 12px;cursor:pointer}.content{max-width:1180px;margin:auto;padding:32px}h1{font-size:30px;margin:0 0 6px}.intro{color:#617185;margin:0 0 26px}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.panel{background:#fff;border:1px solid #dce4ed;border-radius:12px;padding:22px;margin-bottom:18px}.panel h2{font-size:19px;margin:0 0 12px}.panel li{margin:8px 0}.quicklinks{display:flex;gap:10px;flex-wrap:wrap}.quicklinks a{padding:12px 16px;border:1px solid #dce4ed;border-radius:9px;background:#fff;font-weight:650;text-decoration:none}.quicklinks a:hover{border-color:#155c87}.number{font-size:36px;font-weight:800}.muted-text{color:#617185}.testbanner{background:#fff0b3;color:#473500;padding:10px 32px;font-weight:750;border-bottom:2px solid #b8860b}.mobile-menu{display:none}@media(max-width:760px){.shell{display:block}.sidebar{padding:12px 16px}.brand{margin:4px 0 10px}.mobile-menu{display:block;width:100%;background:#29445f;color:white;border:0;padding:10px;text-align:left;border-radius:6px}.sidebar nav{display:block}.topbar{padding:14px 18px}.content{padding:22px 16px}h1{font-size:25px}}
.office-home-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 20px;padding:0 0 12px;border-bottom:1px solid #dce3eb}.office-home-tab{display:inline-block;padding:9px 14px;border-radius:8px;text-decoration:none;color:#26374b;background:#edf1f6;font-weight:600}.office-home-tab[aria-current=page]{background:#162639;color:#fff}</style></head><body><div class="shell"><aside class="sidebar"><div class="brand">BREBO Office</div><nav id="office-navigation" aria-label="Hoofdnavigatie"><a class="current" href="' . $e(\Brebo\Office\Intake\OfficeMount::basePath()) . '">Mijn werkplek</a>';
foreach ($navItems as [$groupName, $description, $links]) {
  echo '<h2>' . $e($groupName) . '</h2>';
  if ($links === []) {
    echo '<span class="muted">Nog niet aangesloten</span>';
  }
  foreach ($links as [$name, $url]) {
    echo '<a href="' . $e($url) . '">' . $e($name) . '</a>';
  }
}
echo '</nav></aside><div class="workspace">';
if ($isTestEnvironment) {
  echo '<div class="testbanner" role="status">TESTOMGEVING — geen productiegegevens</div>';
}
// Office 2.0 workspace overview uses only existing operator-scoped data.
$workspaceSummary = '<section class="panel" aria-label="Werkplekoverzicht"><h2>Mijn werkplek</h2><p class="muted-text">Persoonlijk overzicht op basis van je bestaande toegangsrechten.</p><div class="cards">';
if ($projectsEnabled && $myProjectCount !== null) {
  $workspaceSummary .= '<div><strong>Mijn projecten</strong><p class="number">' . (int)$myProjectCount . '</p></div>';
}
if ($importReviewEnabled) {
  $workspaceSummary .= '<div><strong>Importcontrole</strong><p class="number">' . ($importIssueCount === null ? 'Niet beschikbaar' : (string)(int)$importIssueCount) . '</p></div>';
}
$workspaceSummary .= '</div></section>';
echo '<header class="topbar"><strong>Mijn werkplek</strong><form method="post" action="' . $e($logoutUrl) . '"><button type="submit">Uitloggen</button></form></header><main class="content"><nav class="office-home-tabs" aria-label="Werkplek tabbladen"><a class="office-home-tab" aria-current="page" href="' . $e(\Brebo\Office\Intake\OfficeMount::basePath() . '/') . '">Overzicht</a></nav><h1>Goedemorgen, welkom in Office</h1><p class="intro">Jouw projecten, aandachtspunten en beschikbare werkzaamheden.</p><div class="cards">';
if ($projectsEnabled && $myProjectCount !== null) {
  echo '<section class="panel"><h2>Mijn projecten</h2><div class="number">' . $myProjectCount . '</div><p class="muted-text">Projecten waartoe je toegang hebt</p><a href="' . $e((string)$projectsUrl) . '">Open projecten →</a></section>';
}
if ($importReviewEnabled) {
  echo '<section class="panel"><h2>Urenverantwoording</h2><div class="number">' . ($importIssueCount === null ? '—' : (string)$importIssueCount) . '</div><p class="muted-text">Afwijkingen ter controle</p><a href="' . $e(\Brebo\Office\Intake\OfficeMount::route('inzet/importcontrole')) . '#afwijkingen">Bekijk afwijkingen →</a></section>';
}
if ($financeEnabled) {
  echo '<section class="panel"><h2>Finance</h2><p class="muted-text">Facturen, blokkades en betaalcontrole</p><a href="' . $e((string)$financeUrl) . '">Open Finance →</a></section>';
}
echo '</div>';
if ($isTestEnvironment && getenv('OFFICE_TEST_WORKSPACE_V2_ENABLED') === 'enabled') { echo $workspaceSummary; }
if ($recentProjectsUnavailable) {
  echo '<section class="panel"><h2>Mijn recente projecten</h2><p>Het overzicht is tijdelijk niet beschikbaar.</p></section>';
} elseif ($myRecentProjects !== []) {
  echo '<section class="panel"><h2>Mijn recente projecten</h2><ul>';
  foreach ($myRecentProjects as $project) {
    $id = filter_var($project['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id)) { continue; }
    $name = trim((string)($project['code'] ?? '') . ' · ' . (string)($project['title'] ?? ''), ' ·');
    echo '<li><a href="' . $e(\Brebo\Office\Intake\OfficeMount::basePath() . '/projecten/detail?id=' . $id) . '">' . $e($name !== '' ? $name : 'Project #' . $id) . '</a></li>';
  }
  echo '</ul></section>';
}
if ($recentWork !== []) {
  echo '<section class="panel"><h2>Mijn laatste werkzaamheden</h2><ul>';
  foreach ($recentWork as $item) {
    echo '<li>' . $e(($item->module === 'calculation' ? 'Calculatie' : 'Werkzaamheid') . ' #' . $item->resourceId) . ' — Hervatten nog niet beschikbaar</li>';
  }
  echo '</ul></section>';
}
echo '<section class="panel"><h2>Snel naar</h2><div class="quicklinks">';
$quickCount = 0;
foreach ($navItems as [$groupName, $description, $links]) {
  foreach ($links as [$name, $url]) {
    if ($quickCount >= 6) { break 2; }
    echo '<a href="' . $e($url) . '">' . $e($name) . ' →</a>';
    $quickCount++;
  }
}
if ($quickCount === 0) { echo '<p>Er zijn nog geen onderdelen beschikbaar voor jouw account.</p>'; }
echo '</div></section><p class="muted-text">Onderdelen worden alleen getoond wanneer de bestaande toegangscontroles ze beschikbaar stellen.</p></main></div></div></body></html>';
