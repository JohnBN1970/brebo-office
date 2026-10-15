<?php

declare(strict_types=1);

// Minimal independent Office home. Never display operational data before session validation.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
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
  ['CRM', $crmUrl, true],
  ['Calculatie', $calcUrl, $calcEnabled],
  ['Finance', null, false],
  ['Inkoop', null, false],
  ['Projecten', null, false],
  ['Gebouwen', null, false],
  ['Documenten', null, false],
  ['Inzet', null, false],
  ['Mail', null, false],
];
header('Content-Type: text/html; charset=utf-8');
$logoutUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('uitloggen') : ($queryRouter ? '/index.php?office_route=uitloggen' : '/office/uitloggen');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif}header{background:#162639;color:white;padding:24px max(24px,calc((100vw - 1100px)/2))}main{max-width:1100px;margin:36px auto;padding:0 24px}h1{margin:0 0 8px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:24px}.tile{display:block;background:white;border:1px solid #dbe1e8;border-radius:12px;padding:22px;min-height:88px;color:inherit;text-decoration:none}.tile strong{display:block;font-size:18px;margin-bottom:12px}.tile span{font-size:13px;color:#57677b}.active:hover{border-color:#2e688e;box-shadow:0 4px 18px #152c4215}.inactive{background:#eef1f5}nav a{color:white}footer{margin-top:32px;color:#57677b;font-size:13px}</style></head><body><header><strong>BREBO Office</strong><nav style="float:right"><form method="post" action="' . htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline"><button type="submit">Uitloggen</button></form></nav></header><main><h1>Mijn werkplek</h1><p>Een centrale ingang voor alle Office-modules. Alleen beschikbare onderdelen zijn aanklikbaar.</p>' . ($recentWork !== [] ? '<section><h2>Mijn laatste werkzaamheden</h2><ul>' . implode('', array_map(static function ($item): string {
  $label = $item->module === 'calculation' ? 'Calculatie' : 'Werkzaamheid';
  return '<li>' . htmlspecialchars($label . ' #' . $item->resourceId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' — Hervatten nog niet beschikbaar</li>';
}, $recentWork)) . '</ul></section>' : '') . '<div class="grid">';
foreach ($modules as [$name, $url, $active]) {
  $label = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  if ($active && is_string($url)) {
    echo '<a class="tile active" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"><strong>' . $label . '</strong><span>Open module →</span></a>';
  } else {
    echo '<div class="tile inactive"><strong>' . $label . '</strong><span>Nog niet aangesloten</span></div>';
  }
}
echo '</div><footer>Financiële KPI’s en rolgebonden taken verschijnen pas wanneer de onderliggende gegevens en rechten zijn aangesloten.</footer></main></body></html>';
