<?php
declare(strict_types=1);

// Isolated test route; the existing Office router and session remain authoritative.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) {
  header('Allow: GET, POST'); http_response_code(405); exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_PERSONAL_DASHBOARD_ENABLED') !== 'enabled'
  || getenv('OFFICE_DASHBOARD_PREFERENCES_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(503); exit('Persoonlijk dashboard nog niet beschikbaar.');
}
$host = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host === '' || !hash_equals($host, (string)($_SERVER['HTTP_HOST'] ?? ''))) {
  http_response_code(403); exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  foreach (['OfficePersonalDashboard.php','OfficeDashboardPreferences.php','OfficeDashboardKpis.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Dashboard/' . $file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || $dsn === '') throw new RuntimeException('Database unavailable');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  $operator = $sessions->currentOperator();
  if ($operator === null) {
    if ($method === 'POST') { http_response_code(403); exit; }
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
  }
  $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=:id AND active = 1 LIMIT 1');
  $active->execute([':id' => $operator->operatorId]);
  if ($active->fetchColumn() === false) { http_response_code(403); exit('Geen Office-toegang.'); }
  $kpis = (new \Brebo\Office\Dashboard\OfficeDashboardKpis($db))->forOperator($operator->operatorId);
  // Widget visibility derives exclusively from server-side ACL-scoped providers.
  // No browser-provided role or authorization lists.
  $permitted = array_keys($kpis);
  $preferences = new \Brebo\Office\Dashboard\OfficeDashboardPreferences($db);
  $layout = $preferences->load($operator->operatorId, $permitted);
  $secret = getenv('OFFICE_DASHBOARD_CSRF_SECRET');
  if ($method === 'POST') {
    if (!is_string($secret) || strlen($secret) < 32) { http_response_code(503); exit; }
    $token = $_POST['csrf'] ?? null;
    $expected = hash_hmac('sha256', 'office-dashboard-preferences:v1:' . $operator->sessionId, $secret);
    if (!is_string($token) || !hash_equals($expected, $token)) {
      http_response_code(403); exit('Ongeldige sessiebeveiliging.');
    }
    $requested = $_POST['widgets'] ?? null;
    if (!is_array($requested) || count($requested) > 6
      || array_filter(array_keys($requested), static fn($k):bool => !is_int($k)) !== []) {
      http_response_code(400); exit('Ongeldige widgetselectie.');
    }
    $preferences->save($operator->operatorId, array_values($requested), $permitted);
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::basePath() . '/mijn-dashboard', true, 303);
    exit;
  }
} catch (Throwable $e) {
  http_response_code(503); exit('Dashboard tijdelijk niet beschikbaar.');
}
$escape = static fn(string $v):string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = is_string($secret) && strlen($secret) >= 32
  ? hash_hmac('sha256', 'office-dashboard-preferences:v1:' . $operator->sessionId, $secret) : null;
$home = \Brebo\Office\Intake\OfficeMount::route('home');
$self = \Brebo\Office\Intake\OfficeMount::basePath() . '/mijn-dashboard';
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mijn dashboard · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#182b40;font:16px system-ui,sans-serif}main{max-width:1160px;margin:32px auto;padding:20px}.panel,.office-personal-kpi{background:white;border:1px solid #e1e6ec;border-radius:12px;padding:20px;margin:0 0 16px}.office-personal-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.office-personal-kpi span,.office-personal-kpi small{display:block;color:#576c80}.office-personal-kpi strong{display:block;font-size:32px;margin:8px 0}a{color:#176196}button{padding:10px 15px;border:0;border-radius:8px;color:white;background:#173c60;cursor:pointer}.choice{display:block;padding:10px 0}</style></head><body><main><p><a href="'.$escape($home).'">Mijn werkplek</a></p><h1>Mijn dashboard</h1><p>Uitsluitend actuele gegevens waarvoor je Office-toegang hebt. Maximaal zes KPI-tegels.</p>';
echo \Brebo\Office\Dashboard\OfficePersonalDashboard::render($layout, $kpis);
echo '<section class="panel"><h2>Dashboard aanpassen</h2>';
if ($csrf === null) {
  echo '<p>Opslaan is niet beschikbaar totdat sessiebeveiliging is geconfigureerd.</p>';
} else {
  echo '<form method="post" action="'.$escape($self).'"><input type="hidden" name="csrf" value="'.$escape($csrf).'">';
  foreach ($permitted as $id) {
    echo '<label class="choice"><input type="checkbox" name="widgets[]" value="'.$escape($id).'"'.(in_array($id,$layout,true)?' checked':'').'> '.$escape($kpis[$id]['label']).'</label>';
  }
  echo '<p>Alleen tegels van aangesloten modules zijn beschikbaar.</p><button type="submit">Voorkeuren opslaan</button></form>';
}
echo '</section></main></body></html>';
