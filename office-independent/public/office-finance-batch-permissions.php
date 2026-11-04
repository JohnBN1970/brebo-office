<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET'); http_response_code(405); exit;
}
if (getenv('OFFICE_FINANCE_ENABLED') !== 'enabled'
  || getenv('OFFICE_FINANCE_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') {
  http_response_code(503); exit('Finance niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403); exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  foreach (['OfficeFinanceAccess.php','OfficeFinanceBatchPermissionCatalog.php',
    'OfficeFinanceBatchPermissionsView.php','OfficeFinanceBatchPermissionsOverview.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Finance/' . $file;
  }
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  ))->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
  }
  $access = new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId);
  if (!$access->allows('read')) {http_response_code(403); exit('Geen Finance-toegang.');}
  $overview = new \Brebo\Office\Finance\OfficeFinanceBatchPermissionsOverview($db);
  $snapshot = $overview->forOperator($operator->operatorId);
  if (!$snapshot['active']) {http_response_code(403); exit('Account niet actief.');}
  $panel = \Brebo\Office\Finance\OfficeFinanceBatchPermissionsView::render($snapshot['permissions']);
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>Mijn betaalbatchrechten - BREBO Office</title>'
    . '<style>body{font:16px system-ui,sans-serif;margin:2rem;max-width:850px}table{border-collapse:collapse;width:100%}th,td{padding:.75rem;border-bottom:1px solid #ccc;text-align:left}</style>'
    . '</head><body><main><h1>Mijn betaalbatchrechten</h1>'
    . '<p>Dit overzicht is alleen-lezen. Rechten worden centraal beheerd.</p>'
    . $panel . '</main></body></html>';
} catch (Throwable $e) {
  error_log('Office Finance batch permission page unavailable: ' . get_class($e));
  http_response_code(503); exit('Finance-rechten tijdelijk niet beschikbaar.');
}
