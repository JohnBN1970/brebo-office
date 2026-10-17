<?php

declare(strict_types=1);

// Isolated, read-only test review endpoint. No Drupal and no public import.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
  || getenv('OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED') !== 'enabled') {
  http_response_code(404);
  exit;
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$expectedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($expectedHost) || $expectedHost === ''
  || !hash_equals($expectedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  $officeDb = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
  );
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($officeDb),
    $_COOKIE,
    time()
  ))->currentOperator();
  if ($operator === null) {
    header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  // This workbook can contain multiple projects and sensitive worker data.
  // Only an explicitly designated test reviewer may access the whole file.
  $reviewerId = filter_var(getenv('OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'), FILTER_VALIDATE_INT,
    ['options'=>['min_range'=>1]]);
  if (!is_int($reviewerId) || $reviewerId !== (int)$operator->operatorId) {
    http_response_code(403);
    exit;
  }
  $path = getenv('OFFICE_TEST_INZET_SQLITE_PATH');
  if (!is_string($path) || $path === '' || !is_file($path)
    || !is_readable($path) || str_contains($path, "\0")) {
    throw new RuntimeException('Test source unavailable');
  }
  $db = new PDO('sqlite:file:'.$path.'?mode=ro', null, null, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT=>2,
  ]);
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetImportReviewReadRepository.php';
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSourceReviewWorkbench.php';
  $report = (new \Brebo\Office\Inzet\OfficeInzetImportReviewReadRepository($db))->summary();
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Urenverantwoording · BREBO Office test</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#182638;margin:0}main{max-width:1100px;margin:30px auto;padding:24px;background:white;border-radius:12px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #ddd}.inzet-kpis{display:flex;gap:16px;flex-wrap:wrap}.inzet-kpi{padding:16px;border:1px solid #ddd;border-radius:8px}.inzet-kpi span,.inzet-kpi strong{display:block}details{margin:20px 0}li{overflow-wrap:anywhere}</style></head><body><main>';
  echo \Brebo\Office\Inzet\OfficeInzetSourceReviewWorkbench::render($report);
  echo '</main></body></html>';
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Testwerkbank tijdelijk niet beschikbaar.');
}
